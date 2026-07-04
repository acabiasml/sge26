<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Support\AcademicYearClosureStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AcademicYearClosureController extends Controller
{
    public function __invoke(Request $request, AcademicYear $academicYear, AcademicYearClosureStatus $closureStatus): View
    {
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        $academicYear->load([
            'school',
            'closedBy',
            'periods.diaryConsolidation.consolidatedBy',
            'classes.courses',
            'classes.enrollments.student',
            'classes.enrollments.courses',
            'classes.enrollments.finalResultCalculatedBy',
        ]);

        return view('academic-years.closure', [
            'academicYear' => $academicYear,
            'issues' => $closureStatus->issues($academicYear),
            'overview' => $closureStatus->overview($academicYear),
            'canCloseAcademicYear' => $closureStatus->canClose($academicYear),
        ]);
    }
}
