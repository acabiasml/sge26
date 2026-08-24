<?php

namespace App\Http\Controllers;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\DiaryAttendanceJustification;
use App\Models\DiaryAttendanceRecord;
use App\Models\IssuedDocument;
use App\Models\StudentEnrollment;
use App\Support\AttendanceSummaryCalculator;
use App\Support\PdfLetterhead;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AcademicYearAttendanceReportPdfController extends Controller
{
    public function __invoke(Request $request, AcademicYear $academicYear, AttendanceSummaryCalculator $calculator): Response
    {
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        $data = $request->validate([
            'attendance_scope' => ['nullable', Rule::in(['annual', 'period', 'month', 'months'])],
            'academic_period_id' => ['nullable', 'required_if:attendance_scope,period', 'integer'],
            'attendance_month' => ['nullable', 'required_if:attendance_scope,month', 'date_format:Y-m'],
            'attendance_start_month' => ['nullable', 'required_if:attendance_scope,months', 'date_format:Y-m'],
            'attendance_end_month' => ['nullable', 'required_if:attendance_scope,months', 'date_format:Y-m', 'after_or_equal:attendance_start_month'],
            'federal_aid_only' => ['nullable', 'boolean'],
        ]);

        [$startsAt, $endsAt, $scopeLabel] = $this->scope($academicYear, $data);
        $academicYear->load('school');

        $enrollments = StudentEnrollment::query()
            ->whereHas('schoolClass', fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->when(! empty($data['federal_aid_only']), fn ($query) => $query->whereHas(
                'student',
                fn ($students) => $students->where('receives_federal_aid', true),
            ))
            ->with(['student:id,full_name,cpf,student_inep,nis,receives_federal_aid', 'schoolClass:id,academic_year_id,name,shift'])
            ->get()
            ->sortBy(fn (StudentEnrollment $enrollment): string => mb_strtolower($enrollment->student?->full_name ?? ''))
            ->values();

        $enrollmentIds = $enrollments->pluck('id');
        $classIds = $enrollments->pluck('school_class_id')->unique();
        $records = DiaryAttendanceRecord::query()
            ->whereIn('school_class_id', $classIds)
            ->whereDate('class_date', '>=', $startsAt)
            ->whereDate('class_date', '<=', $endsAt)
            ->with(['entries' => fn ($query) => $query->whereIn('student_enrollment_id', $enrollmentIds)])
            ->orderBy('class_date')
            ->get();
        $justifications = DiaryAttendanceJustification::query()
            ->whereIn('student_enrollment_id', $enrollmentIds)
            ->whereDate('starts_at', '<=', $endsAt)
            ->whereDate('ends_at', '>=', $startsAt)
            ->get()
            ->groupBy('student_enrollment_id');

        $rows = $enrollments->map(function (StudentEnrollment $enrollment) use ($records, $justifications, $calculator, $startsAt, $endsAt): array {
            $studentStartsAt = max($startsAt, $enrollment->enrolled_at?->toDateString() ?? $startsAt);
            $studentEndsAt = min(
                $endsAt,
                $enrollment->transferred_at?->toDateString()
                    ?? $enrollment->cancelled_at?->toDateString()
                    ?? $enrollment->reclassified_at?->toDateString()
                    ?? $endsAt,
            );

            $studentRecords = $records
                ->where('school_class_id', $enrollment->school_class_id)
                ->filter(fn (DiaryAttendanceRecord $record): bool => $record->class_date->toDateString() >= $studentStartsAt
                    && $record->class_date->toDateString() <= $studentEndsAt
                    && $record->entries->contains('student_enrollment_id', $enrollment->id))
                ->map(function (DiaryAttendanceRecord $record) use ($enrollment): DiaryAttendanceRecord {
                    $record = clone $record;
                    $record->setRelation('entries', $record->entries->where('student_enrollment_id', $enrollment->id)->values());

                    return $record;
                });

            return [
                'enrollment' => $enrollment,
                'summary' => $calculator->summarize($studentRecords, $justifications->get($enrollment->id, collect())),
            ];
        });

        $totals = $calculator->aggregate($rows->pluck('summary'));
        $belowThreshold = $rows->filter(fn (array $row): bool => $row['summary']['percentage'] !== null
            && $row['summary']['percentage'] < 85)->count();

        $issuedDocument = IssuedDocument::query()->create([
            'uuid' => (string) Str::uuid(),
            'verification_code' => $this->verificationCode(),
            'type' => 'attendance-report',
            'person_id' => $request->user()->person_id,
            'school_id' => $academicYear->school_id,
            'issued_by_user_id' => $request->user()->id,
            'payload' => [
                'title' => 'Relatório de Frequência',
                'academic_year_id' => $academicYear->id,
                'scope' => $scopeLabel,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'students_count' => $rows->count(),
                'federal_aid_only' => ! empty($data['federal_aid_only']),
            ],
            'issued_at' => now(),
        ]);

        $pdf = Pdf::loadView('reports.academic-year-attendance', [
            'academicYear' => $academicYear,
            'rows' => $rows,
            'totals' => $totals,
            'belowThreshold' => $belowThreshold,
            'startsAt' => CarbonImmutable::parse($startsAt),
            'endsAt' => CarbonImmutable::parse($endsAt),
            'scopeLabel' => $scopeLabel,
            'federalAidOnly' => ! empty($data['federal_aid_only']),
            'issuedDocument' => $issuedDocument,
            'verificationUrl' => route('documents.verify', $issuedDocument->verification_code),
            'letterhead' => PdfLetterhead::make($academicYear->school),
        ])->setPaper('a4', 'landscape');

        return \App\Support\PdfMetadata::stream(
            $pdf,
            'beaba-relatorio-frequencia-'.$academicYear->id.'-'.now()->format('Ymd-His').'.pdf',
            'Relatório de Frequência - '.$academicYear->school->name.' - Beabá',
        );
    }

    /** @return array{string, string, string} */
    private function scope(AcademicYear $academicYear, array $data): array
    {
        $scope = $data['attendance_scope'] ?? 'annual';
        $yearStart = CarbonImmutable::parse($academicYear->starts_at);
        $yearEnd = CarbonImmutable::parse($academicYear->ends_at);

        if ($scope === 'period') {
            $period = AcademicPeriod::query()
                ->where('academic_year_id', $academicYear->id)
                ->findOrFail($data['academic_period_id']);

            return [$period->starts_at->toDateString(), $period->ends_at->toDateString(), $period->name];
        }

        if ($scope === 'month') {
            $month = CarbonImmutable::createFromFormat('Y-m-d', $data['attendance_month'].'-01');
            if ($month->endOfMonth()->isBefore($yearStart) || $month->startOfMonth()->isAfter($yearEnd)) {
                throw ValidationException::withMessages(['attendance_month' => 'O mês selecionado está fora deste ano letivo.']);
            }
            $start = $month->startOfMonth()->max($yearStart);
            $end = $month->endOfMonth()->min($yearEnd);

            return [$start->toDateString(), $end->toDateString(), ucfirst($month->translatedFormat('F \d\e Y'))];
        }

        if ($scope === 'months') {
            $firstMonth = CarbonImmutable::createFromFormat('Y-m-d', $data['attendance_start_month'].'-01');
            $lastMonth = CarbonImmutable::createFromFormat('Y-m-d', $data['attendance_end_month'].'-01');

            if ($lastMonth->endOfMonth()->isBefore($yearStart) || $firstMonth->startOfMonth()->isAfter($yearEnd)) {
                throw ValidationException::withMessages([
                    'attendance_start_month' => 'O intervalo de meses selecionado está fora deste ano letivo.',
                ]);
            }

            $start = $firstMonth->startOfMonth()->max($yearStart);
            $end = $lastMonth->endOfMonth()->min($yearEnd);

            return [
                $start->toDateString(),
                $end->toDateString(),
                ucfirst($firstMonth->translatedFormat('F \d\e Y')).' a '.ucfirst($lastMonth->translatedFormat('F \d\e Y')),
            ];
        }

        return [$yearStart->toDateString(), $yearEnd->toDateString(), 'Anual'];
    }

    private function verificationCode(): string
    {
        do {
            $code = 'BEABA-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
        } while (IssuedDocument::query()->where('verification_code', $code)->exists());

        return $code;
    }
}
