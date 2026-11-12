<?php

namespace App\Http\Controllers;

use App\Models\AcademicCourse;
use App\Models\Person;
use App\Models\StudentEnrollment;
use App\Support\StudentTechnicalItinerary;
use Illuminate\Http\Request;

class StudentTechnicalItineraryController extends Controller
{
    public function index(Request $request, Person $person, StudentTechnicalItinerary $itineraries)
    {
        abort_unless($request->user()->canManagePeople(), 403);
        $enrollments = $person->studentEnrollments()
            ->whereHas('courses', fn ($q) => $q->where('stage', AcademicCourse::STAGE_HIGH_SCHOOL))
            ->when(! $request->user()->isAdministrator(), fn ($q) => $q->whereHas('schoolClass.academicYear', fn ($q) => $q->whereIn('school_id', $request->user()->manageableSchoolIds())))
            ->with(['courses', 'schoolClass.academicYear.school', 'technicalItinerary'])->latest('enrolled_at')->get();
        abort_if($enrollments->isEmpty(), 404);

        return view('student-histories.itineraries', ['person' => $person, 'enrollments' => $enrollments,
            'options' => $enrollments->mapWithKeys(fn ($e) => [$e->id => $itineraries->options($e)])]);
    }

    public function update(Request $request, Person $person, StudentEnrollment $enrollment, StudentTechnicalItinerary $itineraries)
    {
        abort_unless($enrollment->person_id === $person->id, 404);
        abort_unless($request->user()->canManagePeople() && $request->user()->canManageSchool($enrollment->schoolClass->academicYear->school_id), 403);
        $data = $request->validate(['technical_itinerary_course_id' => ['nullable', 'integer']]);
        $itineraries->assign($enrollment, ! empty($data['technical_itinerary_course_id']) ? (int) $data['technical_itinerary_course_id'] : null);

        return redirect()->route('people.itineraries.index', $person)->with('status', __('Itinerário do estudante atualizado.'));
    }
}
