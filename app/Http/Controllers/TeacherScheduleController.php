<?php

namespace App\Http\Controllers;

use App\Models\IssuedDocument;
use App\Models\SchoolClassComponent;
use App\Models\SchoolClassScheduleSlot;
use App\Support\PdfLetterhead;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class TeacherScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $personId = $request->user()->person_id;

        return view('teacher-schedules.index', [
            'slots' => $this->slotsForTeacher($personId),
            'weekdays' => SchoolClassScheduleSlot::WEEKDAY_LABELS,
        ]);
    }

    public function pdf(Request $request): Response
    {
        $person = $request->user()->person;
        $slots = $this->slotsForTeacher($request->user()->person_id);
        $school = $slots->first()?->schedule?->schoolClass?->academicYear?->school;
        $issuedDocument = $this->issuedDocument($request, $school?->id, $slots->count());

        $pdf = Pdf::loadView('reports.teacher-schedule', [
            'person' => $person,
            'slots' => $slots,
            'weekdays' => SchoolClassScheduleSlot::WEEKDAY_LABELS,
            'issuedDocument' => $issuedDocument,
            'verificationUrl' => route('documents.verify', $issuedDocument->verification_code),
            'letterhead' => PdfLetterhead::make($school),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('beaba-meu-horario-'.now()->format('Ymd-His').'.pdf');
    }

    private function slotsForTeacher(?int $personId): Collection
    {
        if (! $personId) {
            return collect();
        }

        $assignmentIds = SchoolClassComponent::query()
            ->where('active', true)
            ->where(function (Builder $query) use ($personId): void {
                $query->where('teacher_person_id', $personId)
                    ->orWhereHas('substitutions', function (Builder $substitutions) use ($personId): void {
                        $substitutions
                            ->where('substitute_teacher_person_id', $personId)
                            ->whereDate('starts_at', '<=', now()->toDateString())
                            ->where(function (Builder $ends): void {
                                $ends->whereNull('ends_at')->orWhereDate('ends_at', '>=', now()->toDateString());
                            });
                    });
            })
            ->pluck('id');

        return SchoolClassScheduleSlot::query()
            ->with([
                'schedule.schoolClass.academicYear.school',
                'componentAssignment.component.course',
                'componentAssignment.teacher',
            ])
            ->where('type', SchoolClassScheduleSlot::TYPE_CLASS)
            ->whereIn('school_class_component_id', $assignmentIds)
            ->get()
            ->filter(function (SchoolClassScheduleSlot $slot): bool {
                $schedule = $slot->schedule;
                $academicYear = $schedule?->schoolClass?->academicYear;

                return $academicYear?->approved_at !== null
                    && (bool) $academicYear->active
                    && $schedule->starts_at <= now()
                    && ($schedule->ends_at === null || $schedule->ends_at >= now());
            })
            ->sortBy([
                ['weekday', 'asc'],
                ['starts_at', 'asc'],
                ['schedule.schoolClass.name', 'asc'],
            ])
            ->values();
    }

    private function issuedDocument(Request $request, ?int $schoolId, int $slotCount): IssuedDocument
    {
        return IssuedDocument::query()->create([
            'uuid' => (string) Str::uuid(),
            'verification_code' => $this->verificationCode(),
            'type' => 'teacher-schedule',
            'person_id' => $request->user()->person_id,
            'school_id' => $schoolId,
            'issued_by_user_id' => $request->user()->id,
            'payload' => [
                'title' => 'Meu horário docente',
                'slots_count' => $slotCount,
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
}
