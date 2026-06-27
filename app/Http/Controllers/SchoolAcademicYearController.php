<?php

namespace App\Http\Controllers;

use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolAcademicYearController extends Controller
{
    public function index(Request $request, School $school): View
    {
        abort_unless($request->user()->canManageSchool($school->id), 403);

        return view('schools.academic-years.index', [
            'school' => $school->load(['academicYears' => fn ($query) => $query->orderByDesc('reference_year')->orderBy('name')]),
        ]);
    }
}
