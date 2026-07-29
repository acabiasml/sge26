<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Support\StudentFinalResultCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StudentEnrollmentController extends Controller
{
    public function overview(Request $request): View
    {
        abort_unless($request->user()->canManagePeople(), 403);

        $schools = School::query()
            ->where('active', true)
            ->when(! $request->user()->isAdministrator(), function ($query) use ($request): void {
                $query->whereIn('id', $request->user()->manageableSchoolIds());
            })
            ->with([
                'academicYears' => function ($years): void {
                    $years
                        ->where('active', true)
                        ->with([
                            'classes' => function ($classes): void {
                                $classes
                                    ->where('active', true)
                                    ->whereHas('courses')
                                    ->with([
                                        'courses' => fn ($courses) => $courses->orderBy('name'),
                                        'enrollments',
                                    ])
                                    ->orderBy('name');
                            },
                        ])
                        ->orderByDesc('reference_year')
                        ->orderBy('name');
                },
            ])
            ->orderBy('name')
            ->get();

        return view('student-enrollments.overview', [
            'schools' => $schools,
        ]);
    }

    public function index(Request $request, SchoolClass $class): View
    {
        $academicYear = $class->academicYear()->with('school')->firstOrFail();
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        $availableCourses = $class->courses()
            ->orderBy('name')
            ->get();

        abort_unless($class->active && $academicYear->active, 404);

        return view('student-enrollments.index', [
            'academicYear' => $academicYear,
            'class' => $class->load([
                'courses' => fn ($courses) => $courses->orderBy('name'),
                'enrollments.student',
                'enrollments.courses',
                'enrollments.reclassifiedFrom.schoolClass',
            ]),
            'availableCourses' => $availableCourses,
            'students' => Person::query()
                ->whereActiveByRoles()
                ->whereNotNull('cpf')
                ->orderBy('full_name')
                ->get(),
            'targetClasses' => $academicYear->classes()
                ->whereKeyNot($class->id)
                ->where('active', true)
                ->whereHas('courses')
                ->with(['courses' => fn ($courses) => $courses->orderBy('name')])
                ->orderBy('name')
                ->get(),
            'targetClassCourseOptions' => $academicYear->classes()
                ->whereKeyNot($class->id)
                ->where('active', true)
                ->with(['courses' => fn ($courses) => $courses->orderBy('name')])
                ->get()
                ->mapWithKeys(fn (SchoolClass $targetClass) => [
                    $targetClass->id => $targetClass->courses
                        ->map(fn ($course) => ['id' => $course->id, 'name' => $course->name])
                        ->values(),
                ]),
            'enrollmentStartsAt' => $this->enrollmentWindow($academicYear, $class)['starts_at'],
            'enrollmentEndsAt' => $this->enrollmentWindow($academicYear, $class)['ends_at'],
        ]);
    }

    public function store(Request $request, SchoolClass $class): RedirectResponse
    {
        $academicYear = $class->academicYear()->firstOrFail();
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        if (! $academicYear->active) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'Não é possível matricular estudante em ano letivo inativo.',
            ]);
        }

        if (! $class->active) {
            throw ValidationException::withMessages([
                'school_class_id' => 'Não é possível matricular estudante em turma inativa.',
            ]);
        }
        $this->ensureAcademicYearIsOpen($academicYear);

        $data = $this->validatedData($request, $academicYear, $class);
        $courseIds = $data['course_ids'];
        unset($data['course_ids']);

        $student = Person::query()->findOrFail($data['person_id']);

        if (! $student->hasRequiredIdentityForOfficialUse()) {
            throw ValidationException::withMessages([
                'person_id' => 'A matrícula exige estudante com CPF.',
            ]);
        }

        $hasActiveEnrollmentInYear = StudentEnrollment::query()
            ->where('person_id', $student->id)
            ->where('status', StudentEnrollment::STATUS_ENROLLED)
            ->whereHas('schoolClass', fn ($classes) => $classes->where('academic_year_id', $academicYear->id))
            ->exists();

        if ($hasActiveEnrollmentInYear) {
            throw ValidationException::withMessages([
                'person_id' => 'Este estudante já possui uma matrícula ativa neste calendário acadêmico. Use a turma já vinculada ou registre uma reclassificação.',
            ]);
        }

        $data['enrolled_by_person_id'] = $request->user()->person_id;
        $data['status'] = StudentEnrollment::STATUS_ENROLLED;

        DB::transaction(function () use ($class, $data, $courseIds, $student, $academicYear): void {
            $enrollment = $class->enrollments()->create($data);
            $enrollment->courses()->sync($courseIds);

            $this->syncStudentRole($student, $academicYear->school_id);
        });

        return redirect()->route('classes.enrollments.index', $class)
            ->with('status', 'Matrícula cadastrada com sucesso.');
    }

    public function transfer(Request $request, StudentEnrollment $enrollment): RedirectResponse
    {
        $class = $enrollment->schoolClass()->firstOrFail();
        $academicYear = $class->academicYear()->firstOrFail();
        abort_unless($enrollment->school_class_id === $class->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureAcademicYearIsOpen($academicYear);
        $this->ensureActiveEnrollment($enrollment);

        $window = $this->enrollmentWindow($academicYear, $class, $enrollment);

        $data = $request->validate([
            'transferred_at' => ['required', 'date', 'after_or_equal:'.$window['starts_at'], 'before_or_equal:'.$window['ends_at']],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $enrollment->update([
            'status' => StudentEnrollment::STATUS_TRANSFERRED,
            'transferred_at' => $data['transferred_at'],
            'transferred_by_person_id' => $request->user()->person_id,
            'notes' => $this->appendNote($enrollment->notes, $data['notes'] ?? null),
        ]);

        $this->syncStudentRole($enrollment->student()->firstOrFail(), $academicYear->school_id);

        return redirect()->route('classes.enrollments.index', $class)
            ->with('status', 'Transferência registrada com sucesso.');
    }

    public function reclassify(Request $request, StudentEnrollment $enrollment): RedirectResponse
    {
        $class = $enrollment->schoolClass()->firstOrFail();
        $academicYear = $class->academicYear()->firstOrFail();
        abort_unless($enrollment->school_class_id === $class->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureAcademicYearIsOpen($academicYear);
        $this->ensureActiveEnrollment($enrollment);

        $targetClassIds = $academicYear->classes()
            ->whereKeyNot($class->id)
            ->where('active', true)
            ->whereHas('courses')
            ->pluck('id')
            ->all();

        $window = $this->enrollmentWindow($academicYear, $class, $enrollment);

        $data = $request->validate([
            'target_school_class_id' => ['required', Rule::in($targetClassIds)],
            'course_ids' => ['required', 'array', 'min:1'],
            'course_ids.*' => ['required', 'distinct'],
            'reclassified_at' => ['required', 'date', 'after_or_equal:'.$window['starts_at'], 'before_or_equal:'.$window['ends_at']],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $targetClass = SchoolClass::query()
            ->where('academic_year_id', $academicYear->id)
            ->findOrFail($data['target_school_class_id']);

        $targetWindow = $this->enrollmentWindow($academicYear, $targetClass);

        if ($data['reclassified_at'] < $targetWindow['starts_at'] || $data['reclassified_at'] > $targetWindow['ends_at']) {
            throw ValidationException::withMessages([
                'reclassified_at' => 'A data da reclassificação precisa estar dentro do período da turma de destino.',
            ]);
        }

        $targetCourseIds = $targetClass->courses()->pluck('academic_courses.id')->all();
        $selectedCourseIds = collect($data['course_ids'])->map(fn ($id): int => (int) $id)->all();

        if (array_diff($selectedCourseIds, $targetCourseIds) !== []) {
            throw ValidationException::withMessages([
                'course_ids' => 'Selecione apenas matrizes vinculadas à turma de destino.',
            ]);
        }

        if ($targetClass->enrollments()->where('person_id', $enrollment->person_id)->exists()) {
            throw ValidationException::withMessages([
                'target_school_class_id' => 'Este estudante já possui matrícula nesta turma de destino.',
            ]);
        }

        DB::transaction(function () use ($targetClass, $enrollment, $request, $data, $selectedCourseIds, $academicYear): void {
            $newEnrollment = $targetClass->enrollments()->create([
                'person_id' => $enrollment->person_id,
                'enrolled_by_person_id' => $request->user()->person_id,
                'reclassified_by_person_id' => $request->user()->person_id,
                'reclassified_from_enrollment_id' => $enrollment->id,
                'enrolled_at' => $data['reclassified_at'],
                'reclassified_at' => $data['reclassified_at'],
                'status' => StudentEnrollment::STATUS_ENROLLED,
                'type' => $enrollment->type,
                'notes' => $data['notes'] ?? null,
            ]);
            $newEnrollment->courses()->sync($selectedCourseIds);

            $enrollment->update([
                'status' => StudentEnrollment::STATUS_RECLASSIFIED,
                'transferred_at' => $data['reclassified_at'],
                'transferred_by_person_id' => $request->user()->person_id,
                'reclassified_at' => $data['reclassified_at'],
                'reclassified_by_person_id' => $request->user()->person_id,
                'notes' => $this->appendNote($enrollment->notes, $data['notes'] ?? null),
            ]);

            $this->syncStudentRole($enrollment->student()->firstOrFail(), $academicYear->school_id);
        });

        return redirect()->route('classes.enrollments.index', $class)
            ->with('status', 'Reclassificação registrada com sucesso.');
    }

    public function cancel(Request $request, StudentEnrollment $enrollment): RedirectResponse
    {
        $class = $enrollment->schoolClass()->firstOrFail();
        $academicYear = $class->academicYear()->firstOrFail();
        abort_unless($enrollment->school_class_id === $class->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureAcademicYearIsOpen($academicYear);
        $this->ensureActiveEnrollment($enrollment);

        $window = $this->enrollmentWindow($academicYear, $class, $enrollment);

        $data = $request->validate([
            'cancelled_at' => ['required', 'date', 'after_or_equal:'.$window['starts_at'], 'before_or_equal:'.$window['ends_at']],
            'notes' => ['required', 'string', 'max:5000'],
        ]);

        $enrollment->update([
            'status' => StudentEnrollment::STATUS_CANCELLED,
            'cancelled_at' => $data['cancelled_at'],
            'cancelled_by_person_id' => $request->user()->person_id,
            'notes' => $this->appendNote($enrollment->notes, $data['notes']),
        ]);

        $this->syncStudentRole($enrollment->student()->firstOrFail(), $academicYear->school_id);

        return redirect()->route('classes.enrollments.index', $class)
            ->with('status', 'Matrícula cancelada com sucesso. O histórico foi preservado.');
    }

    public function restoreCancellation(Request $request, StudentEnrollment $enrollment): RedirectResponse
    {
        $class = $enrollment->schoolClass()->firstOrFail();
        $academicYear = $class->academicYear()->firstOrFail();
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureAcademicYearIsOpen($academicYear);

        if ($enrollment->status !== StudentEnrollment::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'enrollment' => 'Apenas matrículas canceladas podem ter o cancelamento desfeito.',
            ]);
        }

        $hasActiveEnrollmentInYear = StudentEnrollment::query()
            ->whereKeyNot($enrollment->id)
            ->where('person_id', $enrollment->person_id)
            ->where('status', StudentEnrollment::STATUS_ENROLLED)
            ->whereHas('schoolClass', fn ($classes) => $classes->where('academic_year_id', $academicYear->id))
            ->exists();

        if ($hasActiveEnrollmentInYear) {
            throw ValidationException::withMessages([
                'enrollment' => 'Este estudante já possui matrícula ativa neste ano letivo.',
            ]);
        }

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $restorationNote = trim(collect([
            'Cancelamento desfeito em '.now()->timezone('America/Sao_Paulo')->format('d/m/Y H:i').' por '.($request->user()->person?->full_name ?? 'usuário identificado').'.',
            $data['notes'] ?? null,
        ])->filter()->implode(' '));

        $enrollment->update([
            'status' => StudentEnrollment::STATUS_ENROLLED,
            'cancelled_at' => null,
            'cancelled_by_person_id' => null,
            'notes' => $this->appendNote($enrollment->notes, $restorationNote),
        ]);

        $this->syncStudentRole($enrollment->student()->firstOrFail(), $academicYear->school_id);

        return redirect()->route('classes.enrollments.index', $class)
            ->with('status', 'Cancelamento de matrícula desfeito com sucesso.');
    }

    public function calculateFinalResults(Request $request, SchoolClass $class, StudentFinalResultCalculator $calculator): RedirectResponse
    {
        $academicYear = $class->academicYear()->firstOrFail();
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureAcademicYearIsOpen($academicYear);

        $enrollments = $class->enrollments()
            ->with(['student', 'courses.components'])
            ->get();

        DB::transaction(function () use ($enrollments, $calculator, $request): void {
            foreach ($enrollments as $enrollment) {
                $result = $calculator->calculate($enrollment);

                $enrollment->update([
                    'final_result_status' => $result['status'],
                    'final_result_details' => $result['details'],
                    'final_result_calculated_at' => now(),
                    'final_result_calculated_by_person_id' => $request->user()->person_id,
                ]);
            }
        });

        return redirect()->route('classes.enrollments.index', $class)
            ->with('status', 'Resultados finais calculados para esta turma.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, AcademicYear $academicYear, SchoolClass $class): array
    {
        if (! $class->active) {
            throw ValidationException::withMessages([
                'school_class_id' => 'Não é possível matricular estudante em turma inativa.',
            ]);
        }

        $studentIds = Person::query()
            ->where('active', true)
            ->whereNotNull('cpf')
            ->pluck('id')
            ->all();

        $courseIds = $class->courses()
            ->pluck('academic_courses.id')
            ->all();

        return $request->validate([
            'person_id' => [
                'required',
                Rule::in($studentIds),
                Rule::unique('student_enrollments')
                    ->where('school_class_id', $class->id),
            ],
            'course_ids' => ['required', 'array', 'min:1'],
            'course_ids.*' => ['required', 'distinct', Rule::in($courseIds)],
            'enrolled_at' => ['required', 'date', 'after_or_equal:'.$this->enrollmentWindow($academicYear, $class)['starts_at'], 'before_or_equal:'.$this->enrollmentWindow($academicYear, $class)['ends_at']],
            'type' => ['required', Rule::in(array_keys(StudentEnrollment::TYPE_LABELS))],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function appendNote(?string $current, ?string $addition): ?string
    {
        if (blank($addition)) {
            return $current;
        }

        return trim(collect([$current, $addition])->filter()->implode("\n\n"));
    }

    /**
     * @return array{starts_at: string, ends_at: string}
     */
    private function enrollmentWindow(AcademicYear $academicYear, SchoolClass $class, ?StudentEnrollment $enrollment = null): array
    {
        $startsAt = $class->starts_at?->toDateString() ?? $academicYear->starts_at->toDateString();
        $endsAt = $class->ends_at?->toDateString() ?? $academicYear->ends_at->toDateString();

        if ($enrollment?->enrolled_at) {
            $startsAt = max($startsAt, $enrollment->enrolled_at->toDateString());
        }

        return [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ];
    }

    private function syncStudentRole(Person $student, int $schoolId): void
    {
        $enrollments = StudentEnrollment::query()
            ->where('person_id', $student->id)
            ->where('status', '!=', StudentEnrollment::STATUS_CANCELLED)
            ->whereHas('schoolClass.academicYear', fn ($years) => $years->where('school_id', $schoolId))
            ->with(['schoolClass.academicYear', 'courses'])
            ->get();

        $role = $student->schoolRoles()->firstOrNew([
            'school_id' => $schoolId,
            'role' => PersonSchoolRole::ROLE_STUDENT,
        ]);

        if ($enrollments->isEmpty()) {
            if ($role->exists) {
                $role->forceFill([
                    'active' => false,
                    'ended_at' => now()->toDateString(),
                ])->save();
            }

            return;
        }

        $startedAt = $enrollments
            ->map(fn (StudentEnrollment $enrollment): ?string => $this->enrollmentStartDate($enrollment))
            ->filter()
            ->min();

        $endedAt = $enrollments
            ->map(fn (StudentEnrollment $enrollment): ?string => $this->enrollmentEndDate($enrollment))
            ->filter()
            ->max();

        $role->forceFill([
            'position' => null,
            'active' => true,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
        ])->save();
    }

    private function enrollmentStartDate(StudentEnrollment $enrollment): ?string
    {
        return $enrollment->enrolled_at?->toDateString()
            ?? $enrollment->schoolClass?->starts_at?->toDateString()
            ?? $enrollment->schoolClass?->academicYear?->starts_at?->toDateString();
    }

    private function enrollmentEndDate(StudentEnrollment $enrollment): ?string
    {
        return $enrollment->schoolClass?->ends_at?->toDateString()
            ?? $enrollment->schoolClass?->academicYear?->ends_at?->toDateString();
    }

    private function ensureActiveEnrollment(StudentEnrollment $enrollment): void
    {
        if (! $enrollment->isActive()) {
            throw ValidationException::withMessages([
                'enrollment' => 'Esta matrícula já foi encerrada e não pode receber outra movimentação.',
            ]);
        }
    }

    private function ensureAcademicYearIsOpen(AcademicYear $academicYear): void
    {
        if (! $academicYear->isClosed()) {
            return;
        }

        throw ValidationException::withMessages([
            'academic_year' => 'Este ano letivo está fechado. Reabra o ano letivo antes de alterar matrículas ou resultados finais.',
        ]);
    }
}
