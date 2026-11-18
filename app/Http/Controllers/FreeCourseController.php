<?php

namespace App\Http\Controllers;

use App\Models\FreeCourse;
use App\Models\FreeCourseOffering;
use App\Models\Person;
use App\Models\School;
use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FreeCourseController extends Controller
{
    public function create(Request $request)
    {
        abort_unless($request->user()->canManagePeople(), 403);
        $course = new FreeCourse(['school_id' => $request->integer('school_id') ?: null, 'active' => true]);

        return $this->form($request, $course);
    }

    public function edit(Request $request, FreeCourse $freeCourse)
    {
        return $this->form($request, $freeCourse);
    }

    private function form(Request $request, FreeCourse $course)
    {
        abort_unless($request->user()->canManagePeople(), 403);
        if ($course->school_id) {
            abort_unless($request->user()->canManageSchool($course->school_id), 403);
        }
        $schools = School::when(! $request->user()->isAdministrator(), fn ($q) => $q->whereIn('id', $request->user()->manageableSchoolIds()))->orderBy('name')->get();

        return view('free-courses.form', compact('course', 'schools'));
    }

    public function store(Request $request)
    {
        return $this->save($request, new FreeCourse);
    }

    public function update(Request $request, FreeCourse $freeCourse)
    {
        $this->authorizeCourse($request, $freeCourse);

        return $this->save($request, $freeCourse);
    }

    private function save(Request $request, FreeCourse $course)
    {
        abort_unless($request->user()->canManagePeople(), 403);
        $data = $request->validate(['school_id' => ['required', 'integer', 'exists:schools,id'],
            'name' => ['required', 'string', 'max:255', Rule::unique('free_courses')->where('school_id', $request->integer('school_id'))->ignore($course->id)],
            'workload_hours' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:10000'],
            'syllabus' => ['required', 'string', 'max:12000'], 'active' => ['required', 'boolean']]);
        abort_unless($request->user()->canManageSchool($data['school_id']), 403);
        if ($course->exists && $course->school_id !== (int) $data['school_id']) {
            throw ValidationException::withMessages(['school_id' => 'O curso deve permanecer na escola de origem.']);
        }
        $course->fill($data)->save();

        return redirect()->route('free-courses.edit', $course)->with('status', 'Curso livre salvo. Cadastre uma turma e seus participantes para emitir certificados.');
    }

    public function offeringCreate(Request $request, FreeCourse $freeCourse)
    {
        $this->authorizeCourse($request, $freeCourse);
        $offering = new FreeCourseOffering(['free_course_id' => $freeCourse->id, 'delivery_mode' => 'presencial', 'location' => $freeCourse->school->city]);

        return $this->offeringView($freeCourse, $offering);
    }

    public function offeringEdit(Request $request, FreeCourse $freeCourse, FreeCourseOffering $offering)
    {
        $this->authorizeCourse($request, $freeCourse);
        abort_unless($offering->free_course_id === $freeCourse->id, 404);

        return $this->offeringView($freeCourse, $offering->load('participants.person'));
    }

    private function offeringView(FreeCourse $course, FreeCourseOffering $offering)
    {
        return view('free-courses.offering', ['course' => $course, 'offering' => $offering,
            'classes' => SchoolClass::whereHas('academicYear', fn ($q) => $q->where('school_id', $course->school_id))
                ->with('academicYear')->orderByDesc('academic_year_id')->orderBy('name')->get()]);
    }

    public function classParticipants(Request $request, FreeCourse $freeCourse, SchoolClass $class)
    {
        $this->authorizeCourse($request, $freeCourse);
        abort_unless($class->academicYear->school_id === $freeCourse->school_id, 403);
        $data = $request->validate(['starts_at' => ['required', 'date_format:Y-m-d'], 'ends_at' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_at']]);
        if ($class->academicYear->starts_at->toDateString() > $data['ends_at'] || $class->academicYear->ends_at->toDateString() < $data['starts_at']) {
            return response()->json([]);
        }
        $people = $class->enrollments()->with('student')->whereDate('enrolled_at', '<=', $data['ends_at'])->get()
            ->filter(fn ($enrollment) => (! $enrollment->attendanceEndsAt() || $enrollment->attendanceEndsAt() >= $data['starts_at'])
                && ! ($enrollment->status === 'cancelado' && ! $enrollment->cancelled_at))
            ->map(fn ($enrollment) => ['id' => $enrollment->person_id, 'full_name' => $enrollment->student->full_name])->unique('id')->sortBy('full_name')->values();

        return response()->json($people);
    }

    public function offeringStore(Request $request, FreeCourse $freeCourse)
    {
        return $this->saveOffering($request, $freeCourse, new FreeCourseOffering);
    }

    public function offeringUpdate(Request $request, FreeCourse $freeCourse, FreeCourseOffering $offering)
    {
        abort_unless($offering->free_course_id === $freeCourse->id, 404);

        return $this->saveOffering($request, $freeCourse, $offering);
    }

    private function saveOffering(Request $request, FreeCourse $course, FreeCourseOffering $offering)
    {
        $this->authorizeCourse($request, $course);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'starts_at' => ['required', 'date_format:Y-m-d'],
            'ends_at' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_at'],
            'delivery_mode' => ['required', Rule::in(array_keys(FreeCourseOffering::MODES))], 'location' => ['required', 'string', 'max:255'],
            'participants' => ['nullable', 'array', 'max:300'], 'participants.*.person_id' => ['required', 'integer', 'distinct', 'exists:people,id'],
            'participants.*.completed' => ['nullable', 'boolean']]);
        $people = $data['participants'] ?? [];
        $ids = array_column($people, 'person_id');
        $allowed = $this->peopleQuery($request, $course)->whereKey($ids)->pluck('id');
        if (count($ids) !== $allowed->count()) {
            throw ValidationException::withMessages(['participants' => 'Selecione somente pessoas acessíveis nesta escola.']);
        }
        if (collect($people)->contains(fn ($p) => $p['completed'] ?? false) && $data['ends_at'] > now('America/Cuiaba')->toDateString()) {
            throw ValidationException::withMessages(['ends_at' => 'A conclusão só pode ser confirmada após o término da turma.']);
        }
        unset($data['participants']);
        DB::transaction(function () use ($course, $offering, $data, $people, $ids): void {
            FreeCourse::whereKey($course->id)->lockForUpdate()->firstOrFail();
            if ($offering->exists) {
                FreeCourseOffering::whereKey($offering->id)->lockForUpdate()->firstOrFail();
            }
            $offering->fill($data + ['free_course_id' => $course->id])->save();
            foreach ($people as $person) {
                $offering->participants()->updateOrCreate(['person_id' => $person['person_id']], [
                    'completed_at' => ($person['completed'] ?? false) ? $data['ends_at'] : null, 'removed_at' => null]);
            }
            foreach ($offering->participants()->whereNotIn('person_id', $ids)->whereNull('removed_at')->get() as $removed) {
                $removed->update(['removed_at' => now()]);
            }
        });

        return redirect()->route('free-courses.offerings.edit', [$course, $offering])->with('status', 'Turma e participantes salvos.');
    }

    public function people(Request $request, FreeCourse $freeCourse)
    {
        $this->authorizeCourse($request, $freeCourse);
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $query = $this->peopleQuery($request, $freeCourse);
        foreach (preg_split('/\s+/u', trim($data['q'])) as $word) {
            $query->where('full_name', 'like', '%'.$word.'%');
        }

        return response()->json($query->orderBy('full_name')->limit(30)->get(['id', 'full_name']));
    }

    private function peopleQuery(Request $request, FreeCourse $course)
    {
        return Person::when(! $request->user()->isAdministrator(), fn ($q) => $q->where(fn ($p) => $p
            ->whereHas('schoolRoles', fn ($r) => $r->where('school_id', $course->school_id))
            ->orWhereHas('studentEnrollments.schoolClass.academicYear', fn ($y) => $y->where('school_id', $course->school_id))
            ->orWhereHas('freeCourseParticipants.offering.freeCourse', fn ($c) => $c->where('school_id', $course->school_id))
            ->orWhereHas('issuedDocuments', fn ($d) => $d->where('school_id', $course->school_id))));
    }

    private function authorizeCourse(Request $request, FreeCourse $course): void
    {
        abort_unless($request->user()->canManagePeople() && $request->user()->canManageSchool($course->school_id), 403);
    }
}
