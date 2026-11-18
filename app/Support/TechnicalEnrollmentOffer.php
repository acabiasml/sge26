<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\Person;
use App\Models\StudentAcademicHistoryYear;
use App\Models\StudentEnrollment;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class TechnicalEnrollmentOffer
{
    public function suggest(Person $student, string $enrolledAt, ?string $endsAt = null): ?string
    {
        $start = CarbonImmutable::parse($enrolledAt);
        $endYear = $endsAt ? CarbonImmutable::parse($endsAt)->year : PHP_INT_MAX;
        $years = StudentAcademicHistoryYear::whereHas('history', fn ($q) => $q->where('person_id', $student->id)->where('education_stage', 'medio'))->get();
        foreach ($years as $year) {
            $third = $year->grade_number === 3 || preg_match('/^3\s*[º°ª]?\s*(ano|s[eé]rie)/iu', $year->label);
            if ($third && in_array(mb_strtolower(trim($year->final_result ?? '')), ['aprovado', 'concluído', 'concluido'], true)
                && preg_match('/^\d{4}$/', $year->year) && CarbonImmutable::create((int) $year->year, 12, 31)->lt($start)) {
                return 'subsequente';
            }
        }
        if ($years->contains(fn ($y) => preg_match('/^\d{4}$/', $y->year) && (int) $y->year >= $start->year && (int) $y->year <= $endYear)) {
            return 'concomitante';
        }
        if ($student->studentEnrollments()->whereHas('courses', fn ($q) => $q->where('stage', 'medio'))
            ->whereHas('schoolClass.academicYear', fn ($q) => $q->whereBetween('reference_year', [$start->year, min($endYear, 2100)]))->exists()) {
            return 'concomitante';
        }

        return null;
    }

    public function resolve(StudentEnrollment $enrollment, AcademicCourse $course): ?string
    {
        $enrollment->loadMissing('courses');
        if ($enrollment->courses->contains('stage', 'medio')) {
            return in_array('integrado', $course->technicalProgram?->offer_modes ?? [], true) ? 'integrado' : 'concomitante';
        }
        $registered = $enrollment->courses->firstWhere('id', $course->id)?->pivot?->technical_offer_form;

        return $registered ?: ($enrollment->enrolled_at ? $this->suggest($enrollment->student, $enrollment->enrolled_at->toDateString(), $enrollment->schoolClass->academicYear->ends_at?->toDateString()) : null);
    }

    public function record(StudentEnrollment $enrollment, array $forms, bool $required = true): void
    {
        $enrollment->loadMissing('courses', 'student');
        $integrated = $enrollment->courses->contains('stage', 'medio');
        $suggested = $integrated ? 'concomitante' : ($enrollment->enrolled_at ? $this->suggest($enrollment->student, $enrollment->enrolled_at->toDateString(), $enrollment->schoolClass->academicYear->ends_at?->toDateString()) : null);
        $updates = [];
        foreach ($enrollment->courses->where('stage', 'tecnico') as $course) {
            $value = $integrated ? (in_array('integrado', $course->technicalProgram?->offer_modes ?? [], true) ? 'integrado' : 'concomitante') : ($forms[$course->id] ?? ($course->pivot?->technical_offer_form ?: $suggested));
            if (! $value && ! $required) {
                continue;
            }
            if (! in_array($value, ['concomitante', 'subsequente', 'integrado'], true)) {
                throw ValidationException::withMessages(['technical_offer_forms' => 'Marque concomitante ou subsequente para cada curso técnico da matrícula.']);
            }
            if ($value === 'integrado' && ! $integrated) {
                throw ValidationException::withMessages(['technical_offer_forms' => 'A oferta integrada exige a matrícula com Formação Geral Básica.']);
            }
            $authorized = $course->technicalProgram?->offer_modes ?? [];
            if ($authorized && ! in_array($value, $authorized, true)) {
                throw ValidationException::withMessages(['technical_offer_forms' => 'A forma de oferta selecionada não está autorizada no cadastro do curso técnico.']);
            }
            $updates[$course->id] = ['technical_offer_form' => $value];
        }
        foreach ($updates as $id => $values) {
            $enrollment->courses()->updateExistingPivot($id, $values);
        }
    }
}
