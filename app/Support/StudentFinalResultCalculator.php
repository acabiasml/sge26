<?php

namespace App\Support;

use App\Models\StudentEnrollment;

class StudentFinalResultCalculator
{
    public function __construct(private readonly StudentReportCardBuilder $reportCardBuilder)
    {
    }

    /**
     * @return array{status: string, details: array<string, mixed>}
     */
    public function calculate(StudentEnrollment $enrollment): array
    {
        $enrollment->loadMissing('schoolClass.academicYear.school');

        if ($enrollment->status === StudentEnrollment::STATUS_TRANSFERRED) {
            return $this->movementResult(StudentEnrollment::FINAL_TRANSFERRED, 'Matrícula encerrada por transferência.');
        }

        if ($enrollment->status === StudentEnrollment::STATUS_RECLASSIFIED) {
            return $this->movementResult(StudentEnrollment::FINAL_RECLASSIFIED, 'Matrícula encerrada por reclassificação.');
        }

        if ($enrollment->status === StudentEnrollment::STATUS_CANCELLED) {
            return $this->movementResult(StudentEnrollment::FINAL_CANCELLED, 'Matrícula cancelada.');
        }

        $report = $this->reportCardBuilder->build($enrollment);
        $academicYear = $report['academicYear'];
        $school = $academicYear->school;
        $passingPoints = (float) $report['passingPoints'];
        $minimumAttendance = (int) $report['minimumAttendance'];
        $dependencyLimit = $school?->dependencyComponentLimitForDate($academicYear->ends_at) ?? 0;

        $incompleteComponents = $report['annualComponents']
            ->filter(fn (array $summary): bool => (int) ($summary['complete_periods'] ?? 0) < (int) ($summary['total_periods'] ?? 0))
            ->map(fn (array $summary): array => $this->componentSummary($summary, $passingPoints))
            ->values();

        $missingAttendance = ($report['annualAttendance']['percentage'] ?? null) === null;

        if ($incompleteComponents->isNotEmpty() || $missingAttendance) {
            return [
                'status' => StudentEnrollment::FINAL_PENDING,
                'details' => [
                    'reason' => 'Ainda há lançamentos necessários antes do fechamento anual.',
                    'pending_components' => $incompleteComponents->all(),
                    'missing_attendance' => $missingAttendance,
                    'passing_points' => $passingPoints,
                    'minimum_attendance_percentage' => $minimumAttendance,
                    'dependency_component_limit' => $dependencyLimit,
                ],
            ];
        }

        $attendancePercentage = (float) ($report['annualAttendance']['percentage'] ?? 0);
        $failedByPoints = $report['annualComponents']
            ->filter(fn (array $summary): bool => (float) ($summary['points'] ?? 0) < $passingPoints)
            ->map(fn (array $summary): array => $this->componentSummary($summary, $passingPoints))
            ->values();

        if ($attendancePercentage < $minimumAttendance) {
            return [
                'status' => StudentEnrollment::FINAL_RETAINED_ATTENDANCE,
                'details' => [
                    'reason' => 'Frequência anual efetiva abaixo do mínimo definido para aprovação.',
                    'attendance_percentage' => $attendancePercentage,
                    'minimum_attendance_percentage' => $minimumAttendance,
                    'failed_components' => $failedByPoints->all(),
                    'passing_points' => $passingPoints,
                    'dependency_component_limit' => $dependencyLimit,
                ],
            ];
        }

        if ($failedByPoints->isEmpty()) {
            return [
                'status' => StudentEnrollment::FINAL_APPROVED,
                'details' => [
                    'reason' => 'Estudante alcançou a soma mínima de pontos e a frequência mínima.',
                    'attendance_percentage' => $attendancePercentage,
                    'passing_points' => $passingPoints,
                    'minimum_attendance_percentage' => $minimumAttendance,
                    'dependency_component_limit' => $dependencyLimit,
                ],
            ];
        }

        if ($dependencyLimit > 0 && $failedByPoints->count() <= $dependencyLimit) {
            return [
                'status' => StudentEnrollment::FINAL_DEPENDENCY,
                'details' => [
                    'reason' => 'Estudante ficou abaixo da pontuação mínima dentro do limite de dependência da escola.',
                    'attendance_percentage' => $attendancePercentage,
                    'failed_components' => $failedByPoints->all(),
                    'passing_points' => $passingPoints,
                    'minimum_attendance_percentage' => $minimumAttendance,
                    'dependency_component_limit' => $dependencyLimit,
                ],
            ];
        }

        return [
            'status' => StudentEnrollment::FINAL_RETAINED_POINTS,
            'details' => [
                'reason' => 'Estudante ficou abaixo da pontuação mínima em componentes acima do limite de dependência da escola.',
                'attendance_percentage' => $attendancePercentage,
                'failed_components' => $failedByPoints->all(),
                'passing_points' => $passingPoints,
                'minimum_attendance_percentage' => $minimumAttendance,
                'dependency_component_limit' => $dependencyLimit,
            ],
        ];
    }

    /**
     * @return array{status: string, details: array<string, mixed>}
     */
    private function movementResult(string $status, string $reason): array
    {
        return [
            'status' => $status,
            'details' => ['reason' => $reason],
        ];
    }

    /**
     * @param array<string, mixed> $summary
     * @return array{name: string, points: float, passing_points: float}
     */
    private function componentSummary(array $summary, float $passingPoints): array
    {
        return [
            'name' => $summary['component']->name,
            'points' => round((float) ($summary['points'] ?? 0), 2),
            'passing_points' => $passingPoints,
        ];
    }
}
