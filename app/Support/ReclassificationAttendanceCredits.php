<?php

namespace App\Support;

use App\Models\DiaryAttendanceRecord;
use App\Models\StudentEnrollment;
use App\Models\StudentPeriodConvalidation;
use Illuminate\Support\Str;

final class ReclassificationAttendanceCredits
{
    /** Aproveita registros existentes; não gera aulas ou notas novas. */
    public function synchronize(StudentEnrollment $origin, StudentEnrollment $destination): array
    {
        $source = app(StudentReportCardBuilder::class)->build($origin->fresh());
        $target = app(StudentReportCardBuilder::class)->build($destination->fresh());
        $effective = $destination->enrolled_at;
        $changes = [];
        $normalize = fn ($name) => Str::of($name)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->value();
        foreach ($source['periodReports'] as $period) {
            if (! $effective || $period['period']->starts_at->gte($effective)) {
                continue;
            }
            $targetPeriod = $target['periodReports']->first(fn ($p) => $p['period']->position === $period['period']->position);
            if (! $targetPeriod) {
                continue;
            }
            foreach ($period['components'] as $sourceComponent) {
                $matches = $targetPeriod['components']->filter(fn ($c) => $normalize($c['component']->name) === $normalize($sourceComponent['component']->name));
                if ($matches->count() > 1) {
                    throw new \RuntimeException('Componente de aproveitamento ambíguo.');
                }
                $match = $matches->first();
                $attendance = $sourceComponent['attendance'];
                $score = ($match['average']['complete'] ?? false) ? $match['average']['value']
                    : ($match && $period['period']->ends_at->lt($effective) && ($sourceComponent['average']['complete'] ?? false)
                        ? $sourceComponent['average']['value'] : null);
                if (($attendance['lessons'] ?? 0) === 0 && $score === null) {
                    continue;
                }
                $component = $match['component'] ?? $sourceComponent['component'];
                $credit = StudentPeriodConvalidation::firstOrNew([
                    'student_enrollment_id' => $destination->id, 'academic_period_id' => $targetPeriod['period']->id,
                    'curriculum_component_id' => $component->id,
                ]);
                $marker = 'Aproveitamento por reclassificação da matrícula '.$origin->id.'.';
                $reconstructed = DiaryAttendanceRecord::where('school_class_id', $origin->school_class_id)
                    ->where('curriculum_component_id', $sourceComponent['component']->id)
                    ->where('academic_period_id', $period['period']->id)
                    ->where('legacy_source', AdministrativeDiaryReconstruction::SOURCE)->exists();
                if ($credit->exists && ! str_starts_with((string) $credit->notes, $marker)) {
                    throw new \RuntimeException('Já há aproveitamento manual nesse componente; confira a correspondência.');
                }
                $credit->fill(['score' => $score, 'attendance_lessons' => $attendance['lessons'],
                    'attendance_absences' => $attendance['absent'], 'attendance_justified_absences' => $attendance['justified'],
                    'verified_diary_attended_minutes' => $attendance['diary_attended_minutes'] ?? null,
                    'source_school' => $source['academicYear']->school->name.' — '.$source['schoolClass']->name,
                    'notes' => $marker.' Frequência aproveitada dos registros da origem; nota de destino preservada quando já completa.'
                        .($reconstructed ? ' Origem inclui recomposição administrativa auditada.' : ''),
                    'convalidated_at' => $effective->toDateString()]);
                if ($credit->isDirty() || ! $credit->exists) {
                    $changes[] = ['component_id' => $component->id, 'name' => $component->name,
                        'period' => $targetPeriod['period']->name, 'lessons' => $attendance['lessons'],
                        'attended_minutes' => $attendance['diary_attended_minutes'] ?? null, 'credit_only' => ! $match];
                    $credit->save();
                }
            }
        }

        return $changes;
    }
}
