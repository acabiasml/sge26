<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\IssuedDocument;
use App\Models\SchoolClass;
use App\Models\SchoolClassSchedule;
use App\Models\SchoolClassScheduleSlot;
use App\Models\StudentEnrollment;
use App\Support\PdfLetterhead;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SchoolClassSchedulePdfController extends Controller
{
    public function schoolClass(Request $request, AcademicYear $academicYear, SchoolClass $class): Response
    {
        abort_unless($class->academic_year_id === $academicYear->id, 404);
        abort_unless($this->canAccessClassSchedule($request, $academicYear, $class), 403);

        $academicYear->load('school', 'days');
        $class->load([
            'courses',
            'schedules.slots.componentAssignment.component.course',
            'schedules.slots.componentAssignment.teacher',
        ]);

        if ($request->filled('schedule')) {
            $selectedSchedule = $class->schedules->firstWhere('id', $request->integer('schedule'));
            abort_unless($selectedSchedule, 404);
            $class->setRelation('schedules', collect([$selectedSchedule]));
        }

        $issuedDocument = $this->issuedDocument($request, $academicYear, 'class-schedule', 'Horário da turma', [
            'school_class_id' => $class->id,
            'school_class_name' => $class->name,
        ]);

        $pdf = Pdf::loadView('reports.school-class-schedules', [
            'academicYear' => $academicYear,
            'classes' => collect([$class]),
            'issuedDocument' => $issuedDocument,
            'verificationUrl' => route('documents.verify', $issuedDocument->verification_code),
            'letterhead' => PdfLetterhead::make($academicYear->school),
            'weekdays' => $this->weekdays($academicYear),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('beaba-horario-turma-'.$class->id.'-'.now()->format('Ymd-His').'.pdf');
    }

    private function canAccessClassSchedule(Request $request, AcademicYear $academicYear, SchoolClass $class): bool
    {
        $user = $request->user();

        if ($user->canManageSchool($academicYear->school_id)) {
            return true;
        }

        if ($user->person_id && $class->componentAssignments()
            ->where('active', true)
            ->where(function ($query) use ($user): void {
                $query->where('teacher_person_id', $user->person_id)
                    ->orWhereHas('substitutions', function ($substitutions) use ($user): void {
                        $substitutions
                            ->where('substitute_teacher_person_id', $user->person_id)
                            ->whereDate('starts_at', '<=', now()->toDateString())
                            ->where(function ($ends): void {
                                $ends->whereNull('ends_at')->orWhereDate('ends_at', '>=', now()->toDateString());
                            });
                    });
            })
            ->exists()) {
            return true;
        }

        return StudentEnrollment::query()
            ->where('school_class_id', $class->id)
            ->where('person_id', $user->person_id)
            ->where('status', StudentEnrollment::STATUS_ENROLLED)
            ->exists();
    }

    public function academicYear(Request $request, AcademicYear $academicYear): Response
    {
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        $academicYear->load([
            'school',
            'days',
            'classes.courses',
            'classes.schedules.slots.componentAssignment.component.course',
            'classes.schedules.slots.componentAssignment.teacher',
        ]);

        $classes = $academicYear->classes
            ->filter(fn (SchoolClass $class): bool => $class->schedules->isNotEmpty())
            ->sortBy('name')
            ->values();

        $issuedDocument = $this->issuedDocument($request, $academicYear, 'academic-year-schedules', 'Horários das turmas', [
            'academic_year_id' => $academicYear->id,
            'classes_count' => $classes->count(),
        ]);

        $pdf = Pdf::loadView('reports.school-class-schedules', [
            'academicYear' => $academicYear,
            'classes' => $classes,
            'issuedDocument' => $issuedDocument,
            'verificationUrl' => route('documents.verify', $issuedDocument->verification_code),
            'letterhead' => PdfLetterhead::make($academicYear->school),
            'weekdays' => $this->weekdays($academicYear),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('beaba-horarios-'.$academicYear->id.'-'.now()->format('Ymd-His').'.pdf');
    }

    /**
     * @return array<int, string>
     */
    private function weekdays(AcademicYear $academicYear): array
    {
        $weekdays = SchoolClassScheduleSlot::WEEKDAY_LABELS;
        $hasSchoolSaturday = $academicYear->days
            ->contains(fn ($day): bool => (bool) $day->counts_as_school_day && $day->date->isSaturday());

        if (! $hasSchoolSaturday) {
            unset($weekdays[6]);
        }

        return $weekdays;
    }

    private function issuedDocument(Request $request, AcademicYear $academicYear, string $type, string $title, array $payload = []): IssuedDocument
    {
        return IssuedDocument::query()->create([
            'uuid' => (string) Str::uuid(),
            'verification_code' => $this->verificationCode(),
            'type' => $type,
            'person_id' => $request->user()->person_id,
            'school_id' => $academicYear->school_id,
            'issued_by_user_id' => $request->user()->id,
            'payload' => [
                'title' => $title,
                'academic_year_id' => $academicYear->id,
                'academic_year_name' => $academicYear->name,
                'school_id' => $academicYear->school_id,
            ] + $payload,
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
}
