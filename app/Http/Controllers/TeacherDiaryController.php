<?php

namespace App\Http\Controllers;

use App\Models\AcademicPeriod;
use App\Models\AcademicPeriodDiaryConsolidation;
use App\Models\AcademicYear;
use App\Models\CalendarDay;
use App\Models\CurriculumComponent;
use App\Models\DiaryAlert;
use App\Models\DiaryAssessment;
use App\Models\DiaryAttendanceJustification;
use App\Models\DiaryAttendanceRecord;
use App\Models\DiaryContent;
use App\Models\DiaryPeriodConfirmation;
use App\Models\IssuedDocument;
use App\Models\School;
use App\Models\SchoolAssessmentRule;
use App\Models\SchoolClass;
use App\Models\SchoolClassComponent;
use App\Models\StudentEnrollment;
use App\Support\DiaryGradeCalculator;
use App\Support\DiaryPeriodStatus;
use App\Support\PdfLetterhead;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class TeacherDiaryController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user->isAdministrator() || $user->isManager()) {
            return $this->managementIndex($request, new DiaryPeriodStatus);
        }

        $assignments = SchoolClassComponent::query()
            ->with(['component.area', 'component.course.academicYear.school', 'schoolClass'])
            ->where($this->teacherScope($user->person_id))
            ->where('active', true)
            ->whereHas('schoolClass', fn (Builder $query) => $query->where('active', true))
            ->whereHas('component.course.academicYear', fn (Builder $query) => $query->whereNotNull('approved_at')->where('active', true))
            ->get();

        $diaries = $assignments->map(function (SchoolClassComponent $assignment): array {
            return [
                'assignment' => $assignment,
                'component' => $assignment->component,
                'class' => $assignment->schoolClass,
                'course' => $assignment->component->course,
                'academicYear' => $assignment->component->course->academicYear,
            ];
        })->sortBy([
            ['academicYear.school.name', 'asc'],
            ['class.name', 'asc'],
            ['component.name', 'asc'],
        ])->values();

        return view('teacher-diaries.index', [
            'diaries' => $diaries,
            'isManagement' => false,
        ]);
    }

    private function managementIndex(Request $request, DiaryPeriodStatus $status): View
    {
        $user = $request->user();
        $schoolIds = $user->isAdministrator()
            ? School::query()->where('active', true)->orderBy('name')->pluck('id')->all()
            : $user->manageableSchoolIds();

        $schools = School::query()
            ->whereIn('id', $schoolIds)
            ->orderBy('name')
            ->get();

        $years = AcademicYear::query()
            ->with('school')
            ->whereIn('school_id', $schoolIds)
            ->where('active', true)
            ->whereNotNull('approved_at')
            ->when($request->filled('school'), fn (Builder $query) => $query->where('school_id', $request->integer('school')))
            ->orderByDesc('starts_at')
            ->get();

        $academicYear = $years->firstWhere('id', $request->integer('academic_year')) ?? $years->first();
        $periods = $academicYear
            ? $academicYear->periods()->orderBy('starts_at')->get()
            : collect();
        $period = $periods->firstWhere('id', $request->integer('period'))
            ?? $periods->first(fn (AcademicPeriod $candidate): bool => now()->betweenIncluded($candidate->starts_at, $candidate->ends_at))
            ?? $periods->first();

        $summaries = collect();
        $classOptions = collect();
        $teacherOptions = collect();
        $componentOptions = collect();
        $groupedClasses = collect();
        $stats = [
            'total' => 0,
            'pending' => 0,
            'confirmed' => 0,
            'waiting' => 0,
            'reopened' => 0,
        ];

        if ($academicYear && $period) {
            $alertCounts = DiaryAlert::query()
                ->where('academic_period_id', $period->id)
                ->whereNull('resolved_at')
                ->whereNull('dismissed_at')
                ->get()
                ->countBy(fn (DiaryAlert $alert): string => $alert->school_class_id.'-'.$alert->curriculum_component_id);

            $summaries = $status->summaries($academicYear, $period)
                ->map(function (array $summary) use ($alertCounts): array {
                    $summary['alert_count'] = (int) ($alertCounts->get($summary['assignment']->school_class_id.'-'.$summary['assignment']->curriculum_component_id) ?? 0);

                    return $summary;
                })
                ->filter(function (array $summary) use ($request): bool {
                    /** @var SchoolClassComponent $assignment */
                    $assignment = $summary['assignment'];

                    if ($request->filled('class') && $assignment->school_class_id !== $request->integer('class')) {
                        return false;
                    }

                    if ($request->filled('component') && $assignment->curriculum_component_id !== $request->integer('component')) {
                        return false;
                    }

                    if ($request->filled('teacher') && $assignment->teacher_person_id !== $request->integer('teacher')) {
                        return false;
                    }

                    $statusKey = $this->diaryManagementStatusKey($summary);

                    return ! $request->filled('status') || $statusKey === $request->input('status');
                })
                ->values();

            $allAssignments = $status->assignments($academicYear);
            $classOptions = $allAssignments->pluck('schoolClass')->filter()->unique('id')->sortBy('name')->values();
            $teacherOptions = $allAssignments->pluck('teacher')->filter()->unique('id')->sortBy('full_name')->values();
            $componentOptions = $allAssignments->pluck('component')->filter()->unique('id')->sortBy('name')->values();

            $stats = [
                'total' => $summaries->count(),
                'pending' => $summaries->filter(fn (array $summary): bool => $this->diaryManagementStatusKey($summary) === 'pending')->count(),
                'confirmed' => $summaries->filter(fn (array $summary): bool => $this->diaryManagementStatusKey($summary) === 'confirmed')->count(),
                'waiting' => $summaries->filter(fn (array $summary): bool => $this->diaryManagementStatusKey($summary) === 'waiting')->count(),
                'reopened' => $summaries->filter(fn (array $summary): bool => $this->diaryManagementStatusKey($summary) === 'reopened')->count(),
            ];

            $groupedClasses = $summaries
                ->groupBy(fn (array $summary): int => $summary['assignment']->school_class_id)
                ->map(function (Collection $classSummaries): array {
                    $class = $classSummaries->first()['assignment']->schoolClass;

                    return [
                        'class' => $class,
                        'summaries' => $classSummaries->values(),
                        'stats' => [
                            'total' => $classSummaries->count(),
                            'pending' => $classSummaries->filter(fn (array $summary): bool => $this->diaryManagementStatusKey($summary) === 'pending')->count(),
                            'confirmed' => $classSummaries->filter(fn (array $summary): bool => $this->diaryManagementStatusKey($summary) === 'confirmed')->count(),
                            'waiting' => $classSummaries->filter(fn (array $summary): bool => $this->diaryManagementStatusKey($summary) === 'waiting')->count(),
                            'reopened' => $classSummaries->filter(fn (array $summary): bool => $this->diaryManagementStatusKey($summary) === 'reopened')->count(),
                        ],
                    ];
                })
                ->sortBy(fn (array $group): string => $group['class']?->name ?? '')
                ->values();
        }

        return view('teacher-diaries.management-index', [
            'schools' => $schools,
            'years' => $years,
            'periods' => $periods,
            'academicYear' => $academicYear,
            'period' => $period,
            'classOptions' => $classOptions,
            'teacherOptions' => $teacherOptions,
            'componentOptions' => $componentOptions,
            'groupedClasses' => $groupedClasses,
            'stats' => $stats,
            'statusLabels' => $this->diaryManagementStatusLabels(),
        ]);
    }

    private function diaryManagementStatusKey(array $summary): string
    {
        $confirmation = $summary['confirmation'];

        if ($summary['pending']['is_pending']) {
            return 'pending';
        }

        if ($confirmation?->confirmed) {
            return 'confirmed';
        }

        if ($confirmation?->reopened_at) {
            return 'reopened';
        }

        return 'waiting';
    }

    private function diaryManagementStatusLabels(): array
    {
        return [
            'pending' => 'Com pendência',
            'waiting' => 'Aguardando confirmação',
            'confirmed' => 'Confirmado',
            'reopened' => 'Reaberto',
        ];
    }

    public function show(Request $request, SchoolClass $schoolClass, CurriculumComponent $component): View
    {
        $this->authorizeDiaryAccess($request, $schoolClass, $component);

        $course = $component->course()->with('academicYear.school')->firstOrFail();
        $academicYear = $course->academicYear;
        $schoolClass->loadMissing('startsPeriod', 'endsPeriod');
        $assignment = SchoolClassComponent::query()
            ->with('teacher')
            ->where('school_class_id', $schoolClass->id)
            ->where('curriculum_component_id', $component->id)
            ->firstOrFail();
        $periods = $academicYear->periods()->orderBy('starts_at')->get();
        $periods = $periods->filter(fn (AcademicPeriod $availablePeriod): bool => $this->diaryIsActiveInPeriod($schoolClass, $component, $availablePeriod))->values();
        abort_if($periods->isEmpty(), 404);
        $period = $this->selectedPeriod($request, $periods);
        $enrollments = $this->enrollments($schoolClass);

        $assessmentRules = SchoolAssessmentRule::query()
            ->where('school_id', $academicYear->school_id)
            ->when($period, fn (Builder $query) => $query->where('academic_period_id', $period->id))
            ->orderBy('position')
            ->get();

        if ($period) {
            $this->ensureRegularAssessments($schoolClass, $component, $period, $assignment, $assessmentRules);
            $this->ensureRecoveryAssessment($schoolClass, $component, $period, $assignment);
        }

        $assessments = DiaryAssessment::query()
            ->with('results.updatedBy')
            ->where('school_class_id', $schoolClass->id)
            ->where('curriculum_component_id', $component->id)
            ->where('academic_period_id', $period?->id)
            ->where(function (Builder $query): void {
                $query->whereNotNull('school_assessment_rule_id')
                    ->orWhere('is_recovery', true);
            })
            ->orderBy('assessment_date')
            ->orderBy('title')
            ->get();

        $attendanceRecords = DiaryAttendanceRecord::query()
            ->with(['entries', 'updatedBy'])
            ->where('school_class_id', $schoolClass->id)
            ->where('curriculum_component_id', $component->id)
            ->when($period, fn (Builder $query) => $query->where('academic_period_id', $period->id))
            ->orderByDesc('class_date')
            ->get();

        $contents = DiaryContent::query()
            ->with('updatedBy')
            ->where('school_class_id', $schoolClass->id)
            ->where('curriculum_component_id', $component->id)
            ->when($period, fn (Builder $query) => $query->where('academic_period_id', $period->id))
            ->orderByDesc('class_date')
            ->get();
        $confirmation = $period
            ? DiaryPeriodConfirmation::query()->with(['confirmedBy', 'reopenedBy'])->where([
                'school_class_id' => $schoolClass->id,
                'curriculum_component_id' => $component->id,
                'academic_period_id' => $period->id,
            ])->first()
            : null;

        $justifications = DiaryAttendanceJustification::query()
            ->with(['enrollment.student', 'grantedBy'])
            ->whereIn('student_enrollment_id', $enrollments->pluck('id'))
            ->whereDate('starts_at', '<=', $period?->ends_at?->toDateString() ?? $academicYear->ends_at->toDateString())
            ->whereDate('ends_at', '>=', $period?->starts_at?->toDateString() ?? $academicYear->starts_at->toDateString())
            ->orderBy('starts_at')
            ->get();
        $alerts = $period
            ? DiaryAlert::query()
                ->with('fromPerson')
                ->where('school_class_id', $schoolClass->id)
                ->where('curriculum_component_id', $component->id)
                ->where('academic_period_id', $period->id)
                ->whereNull('resolved_at')
                ->whereNull('dismissed_at')
                ->when(! $request->user()->canManageSchool($academicYear->school_id), function (Builder $query) use ($request): void {
                    $query->where('to_person_id', $request->user()->person_id);
                })
                ->latest()
                ->get()
            : collect();

        return view('teacher-diaries.show', [
            'academicYear' => $academicYear,
            'course' => $course,
            'schoolClass' => $schoolClass->load('courses'),
            'component' => $component->load('area'),
            'assignment' => $assignment,
            'periods' => $periods,
            'period' => $period,
            'enrollments' => $enrollments,
            'assessments' => $assessments,
            'assessmentRules' => $assessmentRules,
            'averages' => $this->averages($enrollments, $assessments),
            'attendanceRecords' => $attendanceRecords,
            'contents' => $contents,
            'diaryPending' => $period ? $this->periodPending($enrollments, $assessments, $attendanceRecords, $contents) : null,
            'confirmation' => $confirmation,
            'canManageDiary' => $request->user()->canManageSchool($academicYear->school_id),
            'justifications' => $justifications,
            'attendanceSummary' => $this->attendanceSummary($attendanceRecords, $justifications),
            'alerts' => $alerts,
        ]);
    }

    public function print(Request $request, SchoolClass $schoolClass, CurriculumComponent $component): Response
    {
        $this->authorizeDiaryAccess($request, $schoolClass, $component);

        $course = $component->course()->with('academicYear.school.concepts')->firstOrFail();
        $academicYear = $course->academicYear;
        $scoreView = $request->query('notas') === 'conceitos' ? 'conceitos' : 'numeros';
        $assignment = SchoolClassComponent::query()
            ->with('teacher')
            ->where('school_class_id', $schoolClass->id)
            ->where('curriculum_component_id', $component->id)
            ->firstOrFail();
        $periods = $academicYear->periods()
            ->when($request->filled('period'), fn (Builder $query) => $query->whereKey($request->integer('period')))
            ->orderBy('position')
            ->get()
            ->filter(fn (AcademicPeriod $period): bool => $component->isActiveInPeriod($period))
            ->values();
        abort_if($periods->isEmpty(), 404);

        $enrollments = $this->enrollments($schoolClass);
        $periodReports = $periods->map(function (AcademicPeriod $period) use ($schoolClass, $component, $assignment, $enrollments): array {
            $this->ensureRecoveryAssessment($schoolClass, $component, $period, $assignment);

            $assessments = DiaryAssessment::query()
                ->with('results')
                ->where('school_class_id', $schoolClass->id)
                ->where('curriculum_component_id', $component->id)
                ->where('academic_period_id', $period->id)
                ->orderBy('is_recovery')
                ->orderBy('school_assessment_rule_id')
                ->get();
            $attendance = DiaryAttendanceRecord::query()
                ->with('entries')
                ->where('school_class_id', $schoolClass->id)
                ->where('curriculum_component_id', $component->id)
                ->where('academic_period_id', $period->id)
                ->orderBy('class_date')
                ->get();
            $contents = DiaryContent::query()
                ->where('school_class_id', $schoolClass->id)
                ->where('curriculum_component_id', $component->id)
                ->where('academic_period_id', $period->id)
                ->orderBy('class_date')
                ->get();
            $confirmation = DiaryPeriodConfirmation::query()
                ->with('confirmedBy')
                ->where('school_class_id', $schoolClass->id)
                ->where('curriculum_component_id', $component->id)
                ->where('academic_period_id', $period->id)
                ->first();

            return [
                'period' => $period,
                'assessments' => $assessments,
                'attendance' => $attendance,
                'contents' => $contents,
                'averages' => $this->averages($enrollments, $assessments),
                'confirmation' => $confirmation,
            ];
        });
        $issuedDocument = $this->issuedDiaryDocument($request, $academicYear, $schoolClass, $component, $periods);
        $periodLabel = $periods->count() === 1 ? $periods->first()->name : 'Ano completo';
        $documentTitle = collect([
            'Diário de classe',
            $schoolClass->name,
            $component->name,
            $periodLabel,
            $issuedDocument->verification_code,
        ])->implode(' · ');
        $filename = collect([
            'beaba-diario',
            Str::slug($schoolClass->name),
            Str::slug($component->name),
            Str::slug($periodLabel),
            Str::lower($issuedDocument->verification_code),
        ])->implode('-').'.pdf';

        $pdf = Pdf::loadView('reports.teacher-diary', [
            'academicYear' => $academicYear,
            'schoolClass' => $schoolClass,
            'course' => $course,
            'component' => $component->load('area'),
            'assignment' => $assignment,
            'enrollments' => $enrollments,
            'periodReports' => $periodReports,
            'scoreView' => $scoreView,
            'issuedDocument' => $issuedDocument,
            'verificationUrl' => route('documents.verify', $issuedDocument->verification_code),
            'letterhead' => PdfLetterhead::make($academicYear->school),
        ])->setPaper('a4', 'landscape');

        $pdf->render();
        $pdf->addInfo([
            'Title' => $documentTitle,
            'Subject' => 'Diário de classe emitido pelo Beabá',
            'Author' => $issuedDocument->person?->full_name ?? 'Beabá',
        ]);

        return $pdf->stream($filename);
    }

    public function attendanceSheet(Request $request, SchoolClass $schoolClass, CurriculumComponent $component): Response
    {
        $this->authorizeDiaryAccess($request, $schoolClass, $component);

        $course = $component->course()->with('academicYear.school')->firstOrFail();
        $academicYear = $course->academicYear;
        $assignment = SchoolClassComponent::query()
            ->with('teacher')
            ->where('school_class_id', $schoolClass->id)
            ->where('curriculum_component_id', $component->id)
            ->firstOrFail();
        $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $month = $request->filled('month')
            ? Carbon::createFromFormat('Y-m-d', $request->input('month').'-01')->startOfMonth()
            : now()->startOfMonth();
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        if ($monthStart->lt($academicYear->starts_at)) {
            $monthStart = $academicYear->starts_at->copy()->startOfDay();
        }

        if ($monthEnd->gt($academicYear->ends_at)) {
            $monthEnd = $academicYear->ends_at->copy()->startOfDay();
        }
        $period = $academicYear->periods()
            ->whereDate('starts_at', '<=', $monthEnd->toDateString())
            ->whereDate('ends_at', '>=', $monthStart->toDateString())
            ->orderBy('position')
            ->get()
            ->first(fn (AcademicPeriod $period): bool => $component->isActiveInPeriod($period));

        if ($period && $this->usesScheduledDiary($schoolClass, $assignment, $period)) {
            $days = $this->scheduledDiaryDays($academicYear, $schoolClass, $assignment, $period)
                ->filter(fn (CalendarDay $day): bool => $day->date->betweenIncluded($monthStart, $monthEnd))
                ->values();
        } else {
            $days = CalendarDay::query()
                ->where('academic_year_id', $academicYear->id)
                ->whereDate('date', '>=', $monthStart->toDateString())
                ->whereDate('date', '<=', $monthEnd->toDateString())
                ->where('counts_as_school_day', true)
                ->orderBy('date')
                ->get()
                ->each(fn (CalendarDay $day) => $day->setAttribute('scheduled_lessons', null));
        }

        $enrollments = $this->enrollments($schoolClass);
        $issuedDocument = $this->issuedAttendanceSheetDocument($request, $academicYear, $schoolClass, $component, $month);

        $pdf = Pdf::loadView('reports.attendance-sheet', [
            'academicYear' => $academicYear,
            'schoolClass' => $schoolClass,
            'course' => $course,
            'component' => $component->load('area'),
            'assignment' => $assignment,
            'month' => $month,
            'days' => $days,
            'enrollments' => $enrollments,
            'issuedDocument' => $issuedDocument,
            'verificationUrl' => route('documents.verify', $issuedDocument->verification_code),
            'letterhead' => PdfLetterhead::make($academicYear->school),
        ])->setPaper('a4', 'landscape');

        return \App\Support\PdfMetadata::stream($pdf, 'beaba-lista-chamada-'.$schoolClass->id.'-'.$component->id.'-'.$month->format('Ym').'.pdf');
    }

    public function attendance(Request $request, SchoolClass $schoolClass, CurriculumComponent $component): View
    {
        $this->authorizeDiaryAccess($request, $schoolClass, $component);

        $course = $component->course()->with('academicYear.school')->firstOrFail();
        $academicYear = $course->academicYear;
        $assignment = SchoolClassComponent::query()->where('school_class_id', $schoolClass->id)->where('curriculum_component_id', $component->id)->firstOrFail();
        $periods = $academicYear->periods()->orderBy('starts_at')->get();
        $periods = $periods->filter(fn (AcademicPeriod $availablePeriod): bool => $component->isActiveInPeriod($availablePeriod))->values();
        $period = $this->selectedPeriod($request, $periods);
        abort_unless($period, 404);

        $usesScheduledDiary = $this->usesScheduledDiary($schoolClass, $assignment, $period);
        if ($usesScheduledDiary) {
            $scheduledDays = $this->scheduledDiaryDays($academicYear, $schoolClass, $assignment, $period);
            [$days, $page, $totalPages] = $this->diaryDaysPage($request, $scheduledDays);
            $startsAt = $days->first()?->date;
            $endsAt = $days->last()?->date;
        } else {
            [$startsAt, $endsAt] = $this->attendanceRange($request, $period);
            $days = CalendarDay::query()->where('academic_year_id', $academicYear->id)->whereDate('date', '>=', $startsAt->toDateString())->whereDate('date', '<=', $endsAt->toDateString())->where('counts_as_school_day', true)->orderBy('date')->get();
            $page = 1;
            $totalPages = 1;
            $scheduledDays = $days;
        }
        $dayDates = $days->pluck('date')->map(fn ($date): string => $date->toDateString())->all();
        $records = DiaryAttendanceRecord::query()
            ->with(['entries', 'updatedBy'])
            ->where('school_class_id', $schoolClass->id)
            ->where('curriculum_component_id', $component->id)
            ->when(
                $dayDates === [],
                fn (Builder $query) => $query->whereRaw('1 = 0'),
                fn (Builder $query) => $this->whereDates($query, 'class_date', $dayDates),
            )
            ->get()
            ->keyBy(fn (DiaryAttendanceRecord $record): string => $record->class_date->toDateString());
        $contents = DiaryContent::query()
            ->where('school_class_id', $schoolClass->id)
            ->where('curriculum_component_id', $component->id)
            ->when(
                $dayDates === [],
                fn (Builder $query) => $query->whereRaw('1 = 0'),
                fn (Builder $query) => $this->whereDates($query, 'class_date', $dayDates),
            )
            ->get()
            ->keyBy(fn (DiaryContent $content): string => $content->class_date->toDateString());
        $enrollments = $this->enrollments($schoolClass);
        $justifications = DiaryAttendanceJustification::query()
            ->with(['enrollment.student', 'grantedBy'])
            ->whereIn('student_enrollment_id', $enrollments->pluck('id'))
            ->when($days->isNotEmpty(), fn (Builder $query) => $query->whereDate('starts_at', '<=', $days->last()->date->toDateString())->whereDate('ends_at', '>=', $days->first()->date->toDateString()))
            ->orderBy('starts_at')
            ->get();

        return view('teacher-diaries.attendance', [
            'academicYear' => $academicYear,
            'course' => $course,
            'schoolClass' => $schoolClass,
            'component' => $component->load('area'),
            'assignment' => $assignment,
            'periods' => $periods,
            'period' => $period,
            'page' => $page,
            'totalPages' => $totalPages,
            'scheduledDayCount' => $scheduledDays->count(),
            'usesScheduledDiary' => $usesScheduledDiary,
            'startsAt' => $startsAt,
            'endsAt' => $endsAt,
            'days' => $days,
            'records' => $records,
            'contents' => $contents,
            'enrollments' => $enrollments,
            'justifications' => $justifications,
            'canManageDiary' => $request->user()->canManageSchool($academicYear->school_id),
        ]);
    }

    public function storeAttendanceBatch(Request $request, SchoolClass $schoolClass, CurriculumComponent $component): RedirectResponse
    {
        $this->authorizeDiaryAccess($request, $schoolClass, $component);
        $course = $component->course()->with('academicYear')->firstOrFail();
        $academicYear = $course->academicYear;
        $period = $academicYear->periods()->findOrFail($request->integer('academic_period_id'));
        $this->ensureComponentActiveInPeriod($component, $period);
        $this->ensurePeriodOpen($schoolClass, $component, $period);
        $assignment = SchoolClassComponent::query()->where('school_class_id', $schoolClass->id)->where('curriculum_component_id', $component->id)->firstOrFail();
        $enrollmentIds = $this->activeEnrollments($schoolClass)->pluck('id')->all();

        $data = $request->validate([
            'academic_period_id' => ['required', Rule::in([$period->id])],
            'scheduled_dates' => ['nullable', 'array'],
            'scheduled_dates.*' => ['date'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'page' => ['nullable', 'integer', 'min:1'],
            'lesson_counts' => ['nullable', 'array'],
            'lesson_counts.*' => ['nullable', 'integer', 'min:0', 'max:24'],
            'attendance' => ['nullable', 'array'],
            'attendance.*' => ['nullable', 'array'],
            'attendance.*.*' => ['nullable', 'array'],
            'attendance.*.*.*' => ['nullable', 'accepted'],
        ]);

        $usesScheduledDiary = $this->usesScheduledDiary($schoolClass, $assignment, $period);
        if ($usesScheduledDiary) {
            if (empty($data['scheduled_dates'])) {
                throw ValidationException::withMessages(['scheduled_dates' => 'Não há dias previstos pelo horário da turma para lançamento.']);
            }
            $days = $this->scheduledDiaryDays($academicYear, $schoolClass, $assignment, $period)
                ->filter(fn (CalendarDay $day): bool => in_array($day->date->toDateString(), $data['scheduled_dates'], true))
                ->values();
        } else {
            if (empty($data['starts_at']) || empty($data['ends_at'])) {
                throw ValidationException::withMessages(['starts_at' => 'Informe o intervalo de dias para lançamento.']);
            }
            $startsAt = Carbon::parse($data['starts_at'])->startOfDay();
            $endsAt = Carbon::parse($data['ends_at'])->startOfDay();
            if ($startsAt->lt($period->starts_at) || $endsAt->gt($period->ends_at) || $startsAt->diffInDays($endsAt) > 14) {
                throw ValidationException::withMessages(['starts_at' => 'Selecione datas dentro do período avaliativo, em um intervalo máximo de 15 dias.']);
            }
            $days = CalendarDay::query()->where('academic_year_id', $academicYear->id)->whereDate('date', '>=', $startsAt->toDateString())->whereDate('date', '<=', $endsAt->toDateString())->where('counts_as_school_day', true)->orderBy('date')->get();
        }

        if ($days->isEmpty()) {
            throw ValidationException::withMessages([
                'scheduled_dates' => 'Não há dias disponíveis para lançamento.',
            ]);
        }

        DB::transaction(function () use ($days, $data, $schoolClass, $component, $period, $enrollmentIds, $request): void {
            foreach ($days as $day) {
                $date = $day->date->toDateString();
                $lessonCount = (int) ($data['lesson_counts'][$date] ?? 0);
                if ($lessonCount === 0) {
                    DiaryAttendanceRecord::query()
                        ->where('school_class_id', $schoolClass->id)
                        ->where('curriculum_component_id', $component->id)
                        ->whereDate('class_date', $date)
                        ->delete();

                    continue;
                }
                $record = DiaryAttendanceRecord::query()
                    ->where('school_class_id', $schoolClass->id)
                    ->where('curriculum_component_id', $component->id)
                    ->whereDate('class_date', $date)
                    ->first();

                if (! $record) {
                    $record = new DiaryAttendanceRecord([
                        'school_class_id' => $schoolClass->id,
                        'curriculum_component_id' => $component->id,
                        'class_date' => $date,
                    ]);
                }

                $record->fill([
                    'academic_period_id' => $period->id,
                    'teacher_person_id' => $request->user()->person_id,
                    'updated_by_person_id' => $request->user()->person_id,
                    'lesson_count' => $lessonCount,
                ]);
                $record->save();

                foreach ($enrollmentIds as $enrollmentId) {
                    $lessonPresence = $this->lessonPresence($data['attendance'][$date][$enrollmentId] ?? [], $lessonCount);
                    $attendedLessons = count(array_filter($lessonPresence));
                    $record->entries()->updateOrCreate(
                        ['student_enrollment_id' => $enrollmentId],
                        [
                            'status' => $this->attendanceStatus($attendedLessons, $lessonCount),
                            'attended_lessons' => $attendedLessons,
                            'lesson_presence' => $lessonPresence,
                        ]
                    );
                }
            }
        });

        $redirectParameters = [$schoolClass, $component, 'period' => $period->id];
        if ($usesScheduledDiary) {
            $redirectParameters['page'] = $data['page'] ?? 1;
        } else {
            $redirectParameters['starts_at'] = $startsAt->toDateString();
            $redirectParameters['ends_at'] = $endsAt->toDateString();
        }

        return redirect()->route('teacher-diaries.attendance', $redirectParameters)
            ->with('status', 'Frequências atualizadas com sucesso.');
    }

    public function contents(Request $request, SchoolClass $schoolClass, CurriculumComponent $component): View
    {
        $this->authorizeDiaryAccess($request, $schoolClass, $component);
        $course = $component->course()->with('academicYear.school')->firstOrFail();
        $academicYear = $course->academicYear;
        $periods = $academicYear->periods()->orderBy('starts_at')->get();
        $periods = $periods->filter(fn (AcademicPeriod $availablePeriod): bool => $component->isActiveInPeriod($availablePeriod))->values();
        $period = $this->selectedPeriod($request, $periods);
        abort_unless($period, 404);
        $assignment = SchoolClassComponent::query()->where('school_class_id', $schoolClass->id)->where('curriculum_component_id', $component->id)->firstOrFail();
        $usesScheduledDiary = $this->usesScheduledDiary($schoolClass, $assignment, $period);
        $attendanceDates = DiaryAttendanceRecord::query()
            ->where('school_class_id', $schoolClass->id)
            ->where('curriculum_component_id', $component->id)
            ->where('academic_period_id', $period->id)
            ->pluck('class_date')
            ->map(fn ($date): string => Carbon::parse($date)->toDateString())
            ->all();
        if ($usesScheduledDiary) {
            $scheduledDays = $this->scheduledDiaryDays($academicYear, $schoolClass, $assignment, $period);
            [$days, $page, $totalPages] = $this->diaryDaysPage($request, $scheduledDays);
            $selectedDates = $days->pluck('date')->map(fn ($date): string => $date->toDateString())->all();
        } else {
            $selectedDates = collect($this->selectedContentDates($request, $academicYear->id, $period))
                ->merge($attendanceDates)
                ->unique()
                ->sort()
                ->values()
                ->all();
            $days = CalendarDay::query()
                ->where('academic_year_id', $academicYear->id)
                ->when(
                    $selectedDates === [],
                    fn (Builder $query) => $query->whereRaw('1 = 0'),
                    fn (Builder $query) => $this->whereDates($query, 'date', $selectedDates),
                )
                ->where('counts_as_school_day', true)
                ->orderBy('date')
                ->get();
            $page = 1;
            $totalPages = 1;
        }
        $contents = DiaryContent::query()->with('updatedBy')
            ->where('school_class_id', $schoolClass->id)->where('curriculum_component_id', $component->id)
            ->where('academic_period_id', $period->id)->get()
            ->keyBy(fn (DiaryContent $content): string => $content->class_date->toDateString());

        return view('teacher-diaries.contents', compact('academicYear', 'course', 'schoolClass', 'component', 'periods', 'period', 'days', 'contents', 'attendanceDates', 'selectedDates', 'page', 'totalPages', 'usesScheduledDiary'));
    }

    public function updateContents(Request $request, SchoolClass $schoolClass, CurriculumComponent $component): RedirectResponse
    {
        $this->authorizeDiaryAccess($request, $schoolClass, $component);
        $course = $component->course()->with('academicYear')->firstOrFail();
        $period = $course->academicYear->periods()->findOrFail($request->integer('academic_period_id'));
        $this->ensureComponentActiveInPeriod($component, $period);
        $this->ensurePeriodOpen($schoolClass, $component, $period);
        $assignment = SchoolClassComponent::query()->where('school_class_id', $schoolClass->id)->where('curriculum_component_id', $component->id)->firstOrFail();
        $data = $request->validate([
            'academic_period_id' => ['required', Rule::in([$period->id])],
            'selected_dates' => ['required', 'array', 'min:1'],
            'selected_dates.*' => ['required', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'delete_dates' => ['nullable', 'array'],
            'delete_dates.*' => ['date'],
            'contents' => ['nullable', 'array'],
            'contents.*' => ['nullable', 'string', 'max:5000'],
        ]);
        $usesScheduledDiary = $this->usesScheduledDiary($schoolClass, $assignment, $period);
        $validDates = $usesScheduledDiary
            ? $this->scheduledDiaryDays($course->academicYear, $schoolClass, $assignment, $period)
                ->filter(fn (CalendarDay $day): bool => in_array($day->date->toDateString(), $data['selected_dates'], true))
                ->pluck('date')->map(fn ($date): string => $date->toDateString())->all()
            : CalendarDay::query()->where('academic_year_id', $course->academicYear->id)->whereIn('date', $data['selected_dates'])->where('counts_as_school_day', true)->pluck('date')->map(fn ($date): string => Carbon::parse($date)->toDateString())->all();

        DB::transaction(function () use ($data, $validDates, $schoolClass, $component, $period, $request): void {
            foreach (($data['contents'] ?? []) as $date => $content) {
                if (! in_array($date, $validDates, true)) {
                    continue;
                }
                if (blank($content)) {
                    DiaryContent::query()->where('school_class_id', $schoolClass->id)->where('curriculum_component_id', $component->id)->whereDate('class_date', $date)->delete();

                    continue;
                }
                $record = DiaryContent::query()
                    ->where('school_class_id', $schoolClass->id)
                    ->where('curriculum_component_id', $component->id)
                    ->whereDate('class_date', $date)
                    ->first();
                if (! $record) {
                    $record = new DiaryContent([
                        'school_class_id' => $schoolClass->id,
                        'curriculum_component_id' => $component->id,
                        'class_date' => $date,
                    ]);
                }
                $record->academic_period_id = $period->id;
                $record->content = $content;
                $record->created_by_person_id ??= $request->user()->person_id;
                $record->updated_by_person_id = $request->user()->person_id;
                $record->save();
            }

            foreach (($data['delete_dates'] ?? []) as $date) {
                if (! in_array($date, $validDates, true)) {
                    continue;
                }
                DiaryContent::query()->where('school_class_id', $schoolClass->id)->where('curriculum_component_id', $component->id)->whereDate('class_date', $date)->delete();
            }
        });

        $redirectParameters = ['schoolClass' => $schoolClass, 'component' => $component, 'period' => $period->id];
        if ($usesScheduledDiary) {
            $redirectParameters['page'] = $data['page'] ?? 1;
        } else {
            $redirectParameters['dates'] = implode(',', $validDates);
        }

        return redirect()->route('teacher-diaries.contents', $redirectParameters)
            ->with('status', 'Conteúdos atualizados com sucesso.');
    }

    public function updateGrades(Request $request, SchoolClass $schoolClass, CurriculumComponent $component): RedirectResponse
    {
        $this->authorizeDiaryAccess($request, $schoolClass, $component);
        $course = $component->course()->with('academicYear')->firstOrFail();
        $periodId = (int) $request->input('academic_period_id');
        $period = $course->academicYear->periods()->findOrFail($periodId);
        $this->ensureComponentActiveInPeriod($component, $period);
        $this->ensurePeriodOpen($schoolClass, $component, $period);
        $assignment = SchoolClassComponent::query()
            ->where('school_class_id', $schoolClass->id)
            ->where('curriculum_component_id', $component->id)
            ->firstOrFail();
        $this->ensureRecoveryAssessment($schoolClass, $component, $period, $assignment);

        $enrollmentIds = $this->activeEnrollments($schoolClass)->pluck('id')->all();
        $assessments = DiaryAssessment::query()
            ->where('school_class_id', $schoolClass->id)
            ->where('curriculum_component_id', $component->id)
            ->where('academic_period_id', $periodId)
            ->get()
            ->keyBy('id');

        $request->merge([
            'scores' => collect($request->input('scores', []))
                ->map(fn ($scores) => is_array($scores) ? collect($scores)
                    ->map(fn ($score) => is_string($score) ? str_replace(',', '.', $score) : $score)
                    ->all() : $scores)
                ->all(),
        ]);

        $data = $request->validate([
            'academic_period_id' => ['required', Rule::in($course->academicYear->periods()->pluck('id')->all())],
            'scores' => ['nullable', 'array'],
            'scores.*' => ['nullable', 'array'],
            'scores.*.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        foreach (($data['scores'] ?? []) as $assessmentId => $scores) {
            $assessment = $assessments->get((int) $assessmentId);

            if (! $assessment) {
                continue;
            }

            foreach ($scores as $enrollmentId => $score) {
                if (! in_array((int) $enrollmentId, $enrollmentIds, true)) {
                    continue;
                }

                if ($score !== null && $score !== '' && (float) $score > (float) $assessment->maximum_score) {
                    throw ValidationException::withMessages([
                        "scores.{$assessmentId}.{$enrollmentId}" => "A nota não pode ser maior que {$assessment->maximum_score}.",
                    ]);
                }

                if ($score === null || $score === '') {
                    $assessment->results()->where('student_enrollment_id', (int) $enrollmentId)->delete();
                    continue;
                }

                $assessment->results()->updateOrCreate(
                    ['student_enrollment_id' => (int) $enrollmentId],
                    [
                        'score' => $score,
                        'updated_by_person_id' => $request->user()->person_id,
                    ]
                );
            }
        }

        return redirect()->route('teacher-diaries.show', [
            'schoolClass' => $schoolClass,
            'component' => $component,
            'period' => $periodId,
        ])->with('status', 'Notas atualizadas com sucesso.');
    }

    public function confirmPeriod(Request $request, SchoolClass $schoolClass, CurriculumComponent $component): RedirectResponse
    {
        $this->authorizeDiaryAccess($request, $schoolClass, $component);
        $course = $component->course()->with('academicYear')->firstOrFail();
        $period = $course->academicYear->periods()->findOrFail($request->integer('academic_period_id'));
        $this->ensureComponentActiveInPeriod($component, $period);
        $this->ensureAcademicYearIsOpen($course->academicYear);

        if ($this->periodIsConsolidated($period)) {
            throw ValidationException::withMessages(['academic_period_id' => 'Este período já foi consolidado pela gestão. Reabra o período antes de confirmar novamente.']);
        }

        if (now()->startOfDay()->lt($period->ends_at->copy()->startOfDay())) {
            throw ValidationException::withMessages(['academic_period_id' => 'A confirmação estará disponível a partir do último dia do período avaliativo.']);
        }

        $enrollments = $this->enrollments($schoolClass);
        $assessments = DiaryAssessment::query()->with('results')->where('school_class_id', $schoolClass->id)
            ->where('curriculum_component_id', $component->id)->where('academic_period_id', $period->id)
            ->where(fn (Builder $query) => $query->whereNotNull('school_assessment_rule_id')->orWhere('is_recovery', true))->get();
        $attendance = DiaryAttendanceRecord::query()->where('school_class_id', $schoolClass->id)
            ->where('curriculum_component_id', $component->id)->where('academic_period_id', $period->id)->get();
        $contents = DiaryContent::query()->where('school_class_id', $schoolClass->id)
            ->where('curriculum_component_id', $component->id)->where('academic_period_id', $period->id)->get();
        $pending = $this->periodPending($enrollments, $assessments, $attendance, $contents);
        if ($pending['content_without_attendance'] !== [] || $pending['attendance_without_content'] !== [] || $pending['missing_grades'] > 0) {
            throw ValidationException::withMessages(['academic_period_id' => 'Conclua os conteúdos, as frequências e as notas pendentes antes de confirmar o período.']);
        }

        DiaryPeriodConfirmation::query()->updateOrCreate([
            'school_class_id' => $schoolClass->id,
            'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id,
        ], [
            'confirmed' => true,
            'confirmed_at' => now(),
            'confirmed_by_person_id' => $request->user()->person_id,
            'reopened_at' => null,
            'reopened_by_person_id' => null,
            'reopen_reason' => null,
        ]);

        return redirect()->route('teacher-diaries.show', [$schoolClass, $component, 'period' => $period->id])
            ->with('status', 'Período confirmado com sucesso.');
    }

    public function reopenPeriod(Request $request, SchoolClass $schoolClass, CurriculumComponent $component): RedirectResponse
    {
        $component->loadMissing('course.academicYear');
        $academicYear = $component->course?->academicYear;
        abort_unless($academicYear && $request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureAcademicYearIsOpen($academicYear);
        $period = $academicYear->periods()->findOrFail($request->integer('academic_period_id'));
        $this->ensureComponentActiveInPeriod($component, $period);
        $data = $request->validate(['reopen_reason' => ['required', 'string', 'max:2000']]);

        if ($this->periodIsConsolidated($period)) {
            throw ValidationException::withMessages([
                'academic_period_id' => 'Este período foi consolidado pela gestão. Reabra o período inteiro antes de reabrir um diário individual.',
            ]);
        }

        DiaryPeriodConfirmation::query()->updateOrCreate([
            'school_class_id' => $schoolClass->id,
            'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id,
        ], [
            'confirmed' => false,
            'reopened_at' => now(),
            'reopened_by_person_id' => $request->user()->person_id,
            'reopen_reason' => $data['reopen_reason'],
        ]);

        return redirect()->route('teacher-diaries.show', [$schoolClass, $component, 'period' => $period->id])
            ->with('status', 'Período reaberto. Os lançamentos podem ser ajustados novamente.');
    }

    public function storeAlert(Request $request, SchoolClass $schoolClass, CurriculumComponent $component): RedirectResponse
    {
        $component->loadMissing('course.academicYear');
        $academicYear = $component->course?->academicYear;
        abort_unless($academicYear && $schoolClass->academic_year_id === $academicYear->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureAcademicYearIsOpen($academicYear);

        $assignment = SchoolClassComponent::query()
            ->where('school_class_id', $schoolClass->id)
            ->where('curriculum_component_id', $component->id)
            ->firstOrFail();
        $period = $academicYear->periods()->findOrFail($request->integer('academic_period_id'));
        $this->ensureComponentActiveInPeriod($component, $period);
        $data = $request->validate([
            'academic_period_id' => ['required', Rule::in([$period->id])],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        DiaryAlert::query()->create([
            'school_class_id' => $schoolClass->id,
            'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id,
            'from_person_id' => $request->user()->person_id,
            'to_person_id' => $assignment->teacher_person_id,
            'message' => $data['message'],
        ]);

        return back()->with('status', 'Alerta enviado para a docência.');
    }

    public function dismissAlert(Request $request, DiaryAlert $alert): RedirectResponse
    {
        abort_unless($alert->to_person_id === $request->user()->person_id, 403);

        $alert->update([
            'dismissed_at' => now(),
        ]);

        return back()->with('status', 'Alerta dispensado.');
    }

    private function authorizeDiaryAccess(Request $request, SchoolClass $schoolClass, CurriculumComponent $component): void
    {
        $component->loadMissing('course.academicYear');
        $course = $component->course;
        $academicYear = $course?->academicYear;
        $assignment = SchoolClassComponent::query()
            ->where('school_class_id', $schoolClass->id)
            ->where('curriculum_component_id', $component->id)
            ->first();

        abort_unless($academicYear && $schoolClass->academic_year_id === $academicYear->id, 404);
        abort_unless($schoolClass->courses()->whereKey($course->id)->exists(), 404);
        abort_unless($assignment && $assignment->active, 404);
        abort_unless($academicYear->approved_at !== null && $academicYear->active, 403);

        if ($request->user()->canManageSchool($academicYear->school_id)) {
            return;
        }

        abort_unless($this->teacherCanAccess($request->user()->person_id, $assignment), 403);
    }

    private function ensurePeriodOpen(SchoolClass $schoolClass, CurriculumComponent $component, AcademicPeriod $period): void
    {
        $period->loadMissing('academicYear');
        $this->ensureAcademicYearIsOpen($period->academicYear);

        if ($this->periodIsConsolidated($period)) {
            throw ValidationException::withMessages(['academic_period_id' => 'Este período foi consolidado pela gestão. Reabra o período antes de novos lançamentos.']);
        }

        $confirmed = DiaryPeriodConfirmation::query()->where([
            'school_class_id' => $schoolClass->id,
            'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id,
        ])->where('confirmed', true)->exists();

        if ($confirmed) {
            throw ValidationException::withMessages(['academic_period_id' => 'Este período está confirmado. A gestão precisa reabri-lo antes de novos lançamentos.']);
        }
    }

    private function ensureAcademicYearIsOpen(?AcademicYear $academicYear): void
    {
        if ($academicYear && ! $academicYear->isClosed()) {
            return;
        }

        throw ValidationException::withMessages([
            'academic_period_id' => 'Este ano letivo está fechado. Reabra o ano letivo antes de alterar diários.',
        ]);
    }

    private function periodIsConsolidated(AcademicPeriod $period): bool
    {
        return AcademicPeriodDiaryConsolidation::query()
            ->where('academic_period_id', $period->id)
            ->where('consolidated', true)
            ->exists();
    }

    private function ensureComponentActiveInPeriod(CurriculumComponent $component, AcademicPeriod $period): void
    {
        if ($component->isActiveInPeriod($period)) {
            return;
        }

        throw ValidationException::withMessages([
            'academic_period_id' => 'Este componente curricular não está previsto para este período avaliativo.',
        ]);
    }

    private function ensureRecoveryAssessment(SchoolClass $schoolClass, CurriculumComponent $component, AcademicPeriod $period, SchoolClassComponent $assignment): void
    {
        if (($period->recovery_mode ?? AcademicPeriod::RECOVERY_NONE) === AcademicPeriod::RECOVERY_NONE) {
            return;
        }

        DiaryAssessment::query()->updateOrCreate(
            [
                'school_class_id' => $schoolClass->id,
                'curriculum_component_id' => $component->id,
                'academic_period_id' => $period->id,
                'is_recovery' => true,
            ],
            [
                'school_assessment_rule_id' => null,
                'teacher_person_id' => $assignment->teacher_person_id,
                'title' => 'Recuperação',
                'weight' => $period->recovery_mode === AcademicPeriod::RECOVERY_WEIGHTED ? $period->recovery_weight : 0,
                'maximum_score' => 10,
                'assessment_date' => now()->toDateString(),
                'recovery_mode' => $period->recovery_mode,
                'recovery_replaced_rule_id' => $period->recovery_replaced_rule_id,
            ]
        );
    }

    private function issuedDiaryDocument(Request $request, AcademicYear $academicYear, SchoolClass $schoolClass, CurriculumComponent $component, Collection $periods): IssuedDocument
    {
        return IssuedDocument::query()->create([
            'uuid' => (string) Str::uuid(),
            'verification_code' => $this->verificationCode(),
            'type' => 'teacher-diary',
            'person_id' => $request->user()->person_id,
            'school_id' => $academicYear->school_id,
            'issued_by_user_id' => $request->user()->id,
            'payload' => [
                'title' => 'Diário de classe - '.$component->name,
                'academic_year_id' => $academicYear->id,
                'school_class_id' => $schoolClass->id,
                'component_id' => $component->id,
                'periods' => $periods->pluck('name')->all(),
            ],
            'issued_at' => now(),
        ]);
    }

    private function issuedAttendanceSheetDocument(Request $request, AcademicYear $academicYear, SchoolClass $schoolClass, CurriculumComponent $component, Carbon $month): IssuedDocument
    {
        return IssuedDocument::query()->create([
            'uuid' => (string) Str::uuid(),
            'verification_code' => $this->verificationCode(),
            'type' => 'attendance-sheet',
            'person_id' => $request->user()->person_id,
            'school_id' => $academicYear->school_id,
            'issued_by_user_id' => $request->user()->id,
            'payload' => [
                'title' => 'Lista de chamada - '.$component->name,
                'academic_year_id' => $academicYear->id,
                'school_class_id' => $schoolClass->id,
                'component_id' => $component->id,
                'month' => $month->format('Y-m'),
            ],
            'issued_at' => now(),
        ]);
    }

    private function verificationCode(): string
    {
        do {
            $code = 'BEABA-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
        } while (IssuedDocument::query()->where('verification_code', $code)->exists());

        return $code;
    }

    /**
     * @return array{attendance_without_content: list<string>, content_without_attendance: list<string>, missing_grades: int}
     */
    private function periodPending(Collection $enrollments, Collection $assessments, Collection $attendance, Collection $contents): array
    {
        $activeEnrollments = $enrollments->filter->isActive()->values();
        $attendanceDates = collect($attendance->map(fn (DiaryAttendanceRecord $record): string => $record->class_date->toDateString())->all())->unique()->sort()->values();
        $contentDates = collect($contents->map(fn (DiaryContent $content): string => $content->class_date->toDateString())->all())->unique()->sort()->values();
        $regularAssessments = $assessments->where('is_recovery', false);
        $missingGrades = $regularAssessments->sum(function (DiaryAssessment $assessment) use ($activeEnrollments): int {
            return $activeEnrollments->filter(fn (StudentEnrollment $enrollment): bool => $assessment->results->firstWhere('student_enrollment_id', $enrollment->id)?->score === null)->count();
        });

        return [
            'attendance_without_content' => $attendanceDates->diff($contentDates)->all(),
            'content_without_attendance' => $contentDates->diff($attendanceDates)->all(),
            'missing_grades' => $missingGrades,
        ];
    }

    private function teacherCanAccess(?int $personId, SchoolClassComponent $assignment): bool
    {
        if (! $personId) {
            return false;
        }

        if ((int) $assignment->teacher_person_id === $personId) {
            return true;
        }

        return $assignment->substitutions()
            ->where('substitute_teacher_person_id', $personId)
            ->whereDate('starts_at', '<=', now()->toDateString())
            ->where(function ($query): void {
                $query->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>=', now()->toDateString());
            })
            ->exists();
    }

    private function diaryIsActiveInPeriod(SchoolClass $schoolClass, CurriculumComponent $component, AcademicPeriod $period): bool
    {
        if (! $component->isActiveInPeriod($period)) {
            return false;
        }

        if ($schoolClass->startsPeriod && $period->position < $schoolClass->startsPeriod->position) {
            return false;
        }

        if ($schoolClass->endsPeriod && $period->position > $schoolClass->endsPeriod->position) {
            return false;
        }

        return true;
    }

    private function selectedPeriod(Request $request, Collection $periods): ?AcademicPeriod
    {
        if ($periods->isEmpty()) {
            return null;
        }

        if ($request->filled('period')) {
            return $periods->firstWhere('id', (int) $request->integer('period')) ?? $periods->first();
        }

        return $periods->first(fn (AcademicPeriod $period): bool => $period->starts_at <= now() && $period->ends_at >= now())
            ?? $periods->first();
    }

    private function usesScheduledDiary(SchoolClass $schoolClass, SchoolClassComponent $assignment, AcademicPeriod $period): bool
    {
        return $schoolClass->schedules()
            ->whereDate('starts_at', '<=', $period->ends_at->toDateString())
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhereDate('ends_at', '>=', $period->starts_at->toDateString()))
            ->whereHas('slots', fn (Builder $query) => $query->where('type', 'aula')->where('school_class_component_id', $assignment->id))
            ->exists();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function attendanceRange(Request $request, AcademicPeriod $period): array
    {
        $periodStartsAt = $period->starts_at->copy()->startOfDay();
        $periodEndsAt = $period->ends_at->copy()->startOfDay();
        $defaultStart = now()->betweenIncluded($periodStartsAt, $periodEndsAt)
            ? now()->startOfDay()
            : $periodStartsAt;
        $startsAt = $request->filled('starts_at')
            ? Carbon::parse($request->input('starts_at'))->startOfDay()
            : $defaultStart;
        $startsAt = $startsAt->lt($periodStartsAt) ? $periodStartsAt : $startsAt;
        $startsAt = $startsAt->gt($periodEndsAt) ? $periodEndsAt : $startsAt;
        $latestEnd = $startsAt->copy()->addDays(14);
        $requestedEnd = $request->filled('ends_at')
            ? Carbon::parse($request->input('ends_at'))->startOfDay()
            : $latestEnd;
        $endsAt = $requestedEnd->lt($startsAt) ? $startsAt : $requestedEnd;
        $endsAt = $endsAt->gt($latestEnd) ? $latestEnd : $endsAt;

        return [$startsAt, $endsAt->gt($periodEndsAt) ? $periodEndsAt : $endsAt];
    }

    /**
     * Applies date-only matching for SQLite and MySQL consistently.
     *
     * @param  list<string>  $dates
     */
    private function whereDates(Builder $query, string $column, array $dates): Builder
    {
        return $query->where(function (Builder $dateQuery) use ($column, $dates): void {
            foreach ($dates as $date) {
                $dateQuery->orWhereDate($column, $date);
            }
        });
    }

    /** @return list<string> */
    private function selectedContentDates(Request $request, int $academicYearId, AcademicPeriod $period): array
    {
        $dates = collect(explode(',', (string) $request->input('dates', '')))
            ->filter()
            ->when($request->filled('add_date'), fn (Collection $items) => $items->push((string) $request->input('add_date')))
            ->filter(fn (string $date): bool => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1)
            ->unique()->sort()->values();

        if ($dates->isEmpty()) {
            return [];
        }

        $availableDates = CalendarDay::query()->where('academic_year_id', $academicYearId)->whereIn('date', $dates)
            ->whereDate('date', '>=', $period->starts_at->toDateString())->whereDate('date', '<=', $period->ends_at->toDateString())
            ->where('counts_as_school_day', true)->orderBy('date')->pluck('date')
            ->map(fn ($date): string => Carbon::parse($date)->toDateString())->all();
        if ($request->filled('add_date') && ! in_array($request->input('add_date'), $availableDates, true)) {
            throw ValidationException::withMessages(['add_date' => 'Selecione um dia letivo dentro deste período avaliativo.']);
        }

        return $availableDates;
    }

    /** @return Collection<int, CalendarDay> */
    private function scheduledDiaryDays(AcademicYear $academicYear, SchoolClass $schoolClass, SchoolClassComponent $assignment, AcademicPeriod $period): Collection
    {
        $schedules = $schoolClass->schedules()->with('slots')->whereDate('starts_at', '<=', $period->ends_at->toDateString())
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhereDate('ends_at', '>=', $period->starts_at->toDateString()))
            ->get();
        $calendarDays = CalendarDay::query()->where('academic_year_id', $academicYear->id)
            ->whereDate('date', '>=', $period->starts_at->toDateString())->whereDate('date', '<=', $period->ends_at->toDateString())
            ->where('counts_as_school_day', true)->orderBy('date')->get();

        return $calendarDays->filter(function (CalendarDay $day) use ($schedules, $assignment): bool {
            $date = $day->date->toDateString();
            $lessonCount = $schedules->sum(function ($schedule) use ($date, $day, $assignment): int {
                if ($schedule->starts_at->toDateString() > $date || ($schedule->ends_at && $schedule->ends_at->toDateString() < $date)) {
                    return 0;
                }

                return $schedule->slots
                    ->where('type', 'aula')
                    ->where('weekday', $day->date->dayOfWeekIso)
                    ->where('school_class_component_id', $assignment->id)
                    ->count();
            });
            $day->setAttribute('scheduled_lessons', $lessonCount);

            return $lessonCount > 0;
        })->values();
    }

    /** @return array{0: Collection<int, CalendarDay>, 1: int, 2: int} */
    private function diaryDaysPage(Request $request, Collection $scheduledDays): array
    {
        $perPage = 10;
        $totalPages = max(1, (int) ceil($scheduledDays->count() / $perPage));
        $todayIndex = $scheduledDays->search(fn (CalendarDay $day): bool => $day->date->toDateString() >= now()->toDateString());
        $defaultPage = $todayIndex === false ? 1 : ((int) floor($todayIndex / $perPage) + 1);
        $page = min($totalPages, max(1, $request->integer('page', $defaultPage)));

        return [collect($scheduledDays->forPage($page, $perPage)->values()->all()), $page, $totalPages];
    }

    private function enrollments(SchoolClass $schoolClass): Collection
    {
        return $schoolClass->enrollments()
            ->with('student')
            ->whereIn('status', [
                StudentEnrollment::STATUS_ENROLLED,
                StudentEnrollment::STATUS_TRANSFERRED,
                StudentEnrollment::STATUS_RECLASSIFIED,
            ])
            ->get()
            ->sort(function (StudentEnrollment $first, StudentEnrollment $second): int {
                $firstKey = [
                    $first->isActive() ? 0 : 1,
                    $first->isActive()
                        ? ($first->enrolled_at?->format('Y-m-d') ?? '9999-12-31')
                        : ($first->transferred_at?->format('Y-m-d') ?? $first->reclassified_at?->format('Y-m-d') ?? '9999-12-31'),
                    $first->id,
                ];
                $secondKey = [
                    $second->isActive() ? 0 : 1,
                    $second->isActive()
                        ? ($second->enrolled_at?->format('Y-m-d') ?? '9999-12-31')
                        : ($second->transferred_at?->format('Y-m-d') ?? $second->reclassified_at?->format('Y-m-d') ?? '9999-12-31'),
                    $second->id,
                ];

                return $firstKey <=> $secondKey;
            })
            ->values();
    }

    private function activeEnrollments(SchoolClass $schoolClass): Collection
    {
        return $this->enrollments($schoolClass)
            ->filter->isActive()
            ->values();
    }

    private function averages(Collection $enrollments, Collection $assessments): array
    {
        return app(DiaryGradeCalculator::class)->averages($enrollments, $assessments);
    }

    /** @param Collection<int, SchoolAssessmentRule> $rules */
    private function ensureRegularAssessments(
        SchoolClass $schoolClass,
        CurriculumComponent $component,
        AcademicPeriod $period,
        SchoolClassComponent $assignment,
        Collection $rules,
    ): void {
        foreach ($rules as $rule) {
            DiaryAssessment::query()->updateOrCreate(
                [
                    'school_class_id' => $schoolClass->id,
                    'curriculum_component_id' => $component->id,
                    'academic_period_id' => $period->id,
                    'school_assessment_rule_id' => $rule->id,
                ],
                [
                    'teacher_person_id' => $assignment->teacher_person_id,
                    'title' => $rule->label(),
                    'weight' => $rule->weight,
                    'maximum_score' => $rule->maximum_score,
                    'assessment_date' => $period->ends_at,
                ],
            );
        }
    }

    /**
     * @param  array<int|string, mixed>  $submittedLessons
     * @return array<int, bool>
     */
    private function lessonPresence(array $submittedLessons, int $lessonCount): array
    {
        $selectedLessons = collect(array_keys($submittedLessons))
            ->map(fn (mixed $lesson): int => (int) $lesson)
            ->filter(fn (int $lesson): bool => $lesson >= 1 && $lesson <= $lessonCount)
            ->all();

        return collect(range(1, $lessonCount))
            ->map(fn (int $lesson): bool => in_array($lesson, $selectedLessons, true))
            ->all();
    }

    private function attendanceStatus(int $attendedLessons, int $lessonCount): string
    {
        if ($attendedLessons === $lessonCount) {
            return DiaryAttendanceRecord::STATUS_PRESENT;
        }

        if ($attendedLessons === 0) {
            return DiaryAttendanceRecord::STATUS_ABSENT;
        }

        return DiaryAttendanceRecord::STATUS_PARTIAL;
    }

    /**
     * @return array<int, array{attended: int, effective_attended: int, absent: int, justified: int, lessons: int}>
     */
    private function attendanceSummary(Collection $records, Collection $justifications): array
    {
        return $records->mapWithKeys(function (DiaryAttendanceRecord $record) use ($justifications): array {
            $attended = $record->entries->sum('attended_lessons');
            $lessons = $record->entries->count() * $record->lesson_count;
            $justified = $record->entries->sum(function ($entry) use ($justifications, $record): int {
                $isJustified = $entry->status === DiaryAttendanceRecord::STATUS_EXCUSED
                    || $justifications
                        ->where('student_enrollment_id', $entry->student_enrollment_id)
                        ->contains(fn (DiaryAttendanceJustification $justification): bool => $justification->appliesTo($record->class_date->toDateString()));

                return $isJustified ? max(0, $record->lesson_count - $entry->attended_lessons) : 0;
            });

            return [$record->id => [
                'attended' => $attended,
                'effective_attended' => min($lessons, $attended + $justified),
                'absent' => max(0, $lessons - $attended),
                'justified' => $justified,
                'lessons' => $record->lesson_count,
            ]];
        })->all();
    }

    private function teacherScope(?int $personId): callable
    {
        return function (Builder $query) use ($personId): void {
            $query->where('teacher_person_id', $personId)
                ->orWhereHas('substitutions', function (Builder $substitutions) use ($personId): void {
                    $substitutions
                        ->where('substitute_teacher_person_id', $personId)
                        ->whereDate('starts_at', '<=', now()->toDateString())
                        ->where(function (Builder $period): void {
                            $period->whereNull('ends_at')
                                ->orWhereDate('ends_at', '>=', now()->toDateString());
                        });
                });
        };
    }
}
