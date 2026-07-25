<?php

namespace App\Http\Controllers;

use App\Models\AcademicPeriod;
use App\Models\AcademicPeriodDiaryConsolidation;
use App\Models\AcademicYear;
use App\Models\CalendarDay;
use App\Models\DiaryAssessment;
use App\Models\DiaryPeriodConfirmation;
use App\Models\Person;
use App\Models\SchoolAssessmentRule;
use App\Models\SchoolClassComponent;
use App\Models\StudentBehaviorGrade;
use App\Models\StudentEnrollment;
use App\Support\DiaryPeriodStatus;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AcademicPeriodController extends Controller
{
    public function index(Request $request, AcademicYear $academicYear, DiaryPeriodStatus $diaryStatus): \Illuminate\View\View
    {
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        $academicYear->load('school', 'periods.assessmentRules', 'periods.diaryConsolidation.consolidatedBy', 'periods.diaryConsolidation.reopenedBy');
        $behaviorEnrollments = $this->behaviorEnrollments($academicYear);
        $behaviorGrades = StudentBehaviorGrade::query()
            ->with('updatedBy')
            ->whereIn('academic_period_id', $academicYear->periods->pluck('id'))
            ->whereIn('student_enrollment_id', $behaviorEnrollments->pluck('id'))
            ->get()
            ->keyBy(fn (StudentBehaviorGrade $grade): string => $grade->academic_period_id.'-'.$grade->student_enrollment_id);
        $periodBehaviorStatus = $academicYear->periods
            ->mapWithKeys(fn (AcademicPeriod $period): array => [
                $period->id => [
                    'total' => $behaviorEnrollments->count(),
                    'missing' => $behaviorEnrollments
                        ->filter(fn (StudentEnrollment $enrollment): bool => ! $behaviorGrades->has($period->id.'-'.$enrollment->id))
                        ->count(),
                ],
            ]);
        $periodDiaryStatus = $academicYear->periods
            ->mapWithKeys(fn (AcademicPeriod $period): array => [$period->id => $diaryStatus->summaries($academicYear, $period)]);

        return view('academic-years.periods.index', [
            'academicYear' => $academicYear,
            'canChangeCalendar' => ! $academicYear->approved_at || $request->user()->isAdministrator(),
            'periodDiaryStatus' => $periodDiaryStatus,
            'behaviorEnrollments' => $behaviorEnrollments,
            'behaviorGrades' => $behaviorGrades,
            'periodBehaviorStatus' => $periodBehaviorStatus,
        ]);
    }

    public function consolidate(Request $request, AcademicYear $academicYear, AcademicPeriod $period, DiaryPeriodStatus $diaryStatus): RedirectResponse
    {
        abort_unless($period->academic_year_id === $academicYear->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureYearIsOpen($academicYear);

        $summaries = $diaryStatus->summaries($academicYear, $period);
        $behaviorMissing = $this->missingBehaviorGrades($academicYear, $period);
        if ($summaries->isEmpty()) {
            throw ValidationException::withMessages(['period' => 'Não há diários ativos para consolidar neste período.']);
        }

        $hasUnconfirmedOrPending = $summaries->contains(fn (array $summary): bool => ! $summary['confirmation']?->confirmed || $summary['pending']['is_pending']);
        if ($hasUnconfirmedOrPending) {
            throw ValidationException::withMessages(['period' => 'Todos os diários precisam estar confirmados e sem pendências antes da consolidação.']);
        }

        if ($behaviorMissing > 0) {
            throw ValidationException::withMessages(['period' => 'Todos os estudantes precisam ter a nota de comportamento lançada antes da consolidação.']);
        }

        AcademicPeriodDiaryConsolidation::query()->updateOrCreate([
            'academic_period_id' => $period->id,
        ], [
            'consolidated' => true,
            'consolidated_at' => now(),
            'consolidated_by_person_id' => $request->user()->person_id,
            'reopened_at' => null,
            'reopened_by_person_id' => null,
            'reopen_reason' => null,
        ]);

        return redirect()->route('academic-years.periods.index', $academicYear)
            ->with('status', 'Período consolidado pela gestão.');
    }

    public function reopenConsolidation(Request $request, AcademicYear $academicYear, AcademicPeriod $period): RedirectResponse
    {
        abort_unless($period->academic_year_id === $academicYear->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureYearIsOpen($academicYear);
        $data = $request->validate([
            'reopen_reason' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($period, $request, $data): void {
            AcademicPeriodDiaryConsolidation::query()->updateOrCreate([
                'academic_period_id' => $period->id,
            ], [
                'consolidated' => false,
                'reopened_at' => now(),
                'reopened_by_person_id' => $request->user()->person_id,
                'reopen_reason' => $data['reopen_reason'],
            ]);

            DiaryPeriodConfirmation::query()
                ->where('academic_period_id', $period->id)
                ->update([
                    'confirmed' => false,
                    'reopened_at' => now(),
                    'reopened_by_person_id' => $request->user()->person_id,
                    'reopen_reason' => $data['reopen_reason'],
                    'updated_at' => now(),
                ]);
        });

        return redirect()->route('academic-years.periods.index', $academicYear)
            ->with('status', 'Período reaberto. Os diários voltaram para lançamento e confirmação.');
    }

    public function updateBehaviorGrades(Request $request, AcademicYear $academicYear, AcademicPeriod $period): RedirectResponse
    {
        abort_unless($period->academic_year_id === $academicYear->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureYearIsOpen($academicYear);

        if ($period->diaryConsolidation()->where('consolidated', true)->exists()) {
            throw ValidationException::withMessages([
                'behavior_scores' => 'Este período já foi consolidado. Reabra o período antes de alterar comportamento.',
            ]);
        }

        $request->merge([
            'behavior_scores' => collect($request->input('behavior_scores', []))
                ->map(fn ($score) => is_string($score) ? str_replace(',', '.', $score) : $score)
                ->all(),
        ]);

        $data = $request->validate([
            'behavior_scores' => ['nullable', 'array'],
            'behavior_scores.*' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'behavior_notes' => ['nullable', 'array'],
            'behavior_notes.*' => ['nullable', 'string', 'max:2000'],
        ]);

        $enrollments = $this->behaviorEnrollments($academicYear)->keyBy('id');
        $scores = $data['behavior_scores'] ?? [];
        $notes = $data['behavior_notes'] ?? [];

        DB::transaction(function () use ($period, $request, $enrollments, $scores, $notes): void {
            foreach ($scores as $enrollmentId => $score) {
                if (! $enrollments->has((int) $enrollmentId)) {
                    continue;
                }

                if ($score === null || $score === '') {
                    StudentBehaviorGrade::query()
                        ->where('academic_period_id', $period->id)
                        ->where('student_enrollment_id', $enrollmentId)
                        ->delete();

                    continue;
                }

                StudentBehaviorGrade::query()->updateOrCreate(
                    [
                        'academic_period_id' => $period->id,
                        'student_enrollment_id' => $enrollmentId,
                    ],
                    [
                        'updated_by_person_id' => $request->user()->person_id,
                        'score' => $score,
                        'notes' => $notes[$enrollmentId] ?? null,
                    ]
                );
            }
        });

        return redirect()->route('academic-years.periods.index', $academicYear)
            ->with('status', 'Comportamento do período salvo pela gestão.');
    }

    public function store(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureYearIsOpen($academicYear);
        $this->ensureCanChangeApprovedCalendar($request, $academicYear);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date', 'after_or_equal:'.$academicYear->starts_at->toDateString(), 'before_or_equal:'.$academicYear->ends_at->toDateString()],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at', 'before_or_equal:'.$academicYear->ends_at->toDateString()],
            'ignore_saturdays' => ['nullable', 'boolean'],
            'ignore_sundays' => ['nullable', 'boolean'],
            'position' => ['required', 'integer', 'min:1', 'max:99'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['ignore_saturdays'] = $request->boolean('ignore_saturdays');
        $data['ignore_sundays'] = $request->boolean('ignore_sundays');

        $overlaps = $academicYear->periods()
            ->where(function (Builder $query) use ($data): void {
                $query->whereDate('starts_at', '<=', $data['ends_at'])
                    ->whereDate('ends_at', '>=', $data['starts_at']);
            })
            ->exists();

        if ($overlaps) {
            return back()->withErrors(['starts_at' => 'O período informado se sobrepõe a outro período deste ano letivo.'])->withInput();
        }

        $period = $academicYear->periods()->create($data);
        $this->applyPeriodToCalendarDays($period);
        $this->normalizeRecessBetweenPeriods($academicYear);

        return redirect()->route('academic-years.periods.index', $academicYear)
            ->with('status', 'Período cadastrado com sucesso.');
    }

    public function destroy(Request $request, AcademicYear $academicYear, AcademicPeriod $period): RedirectResponse
    {
        abort_unless($period->academic_year_id === $academicYear->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureYearIsOpen($academicYear);
        $this->ensureCanChangeApprovedCalendar($request, $academicYear);

        $periodStartsAt = $period->starts_at;
        $periodEndsAt = $period->ends_at;

        $period->delete();

        if ($academicYear->periods()->doesntExist()) {
            $this->resetCalendarDaysToVacation($academicYear, $academicYear->starts_at, $academicYear->ends_at);
        } else {
            $this->resetCalendarDaysToVacation($academicYear, $periodStartsAt, $periodEndsAt);
            $this->normalizeRecessBetweenPeriods($academicYear);
        }

        return redirect()->route('academic-years.periods.index', $academicYear)
            ->with('status', 'Período removido com sucesso.');
    }

    public function update(Request $request, AcademicYear $academicYear, AcademicPeriod $period): RedirectResponse
    {
        abort_unless($period->academic_year_id === $academicYear->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureYearIsOpen($academicYear);
        $this->ensureCanChangeApprovedCalendar($request, $academicYear);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date', 'after_or_equal:'.$academicYear->starts_at->toDateString(), 'before_or_equal:'.$academicYear->ends_at->toDateString()],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at', 'before_or_equal:'.$academicYear->ends_at->toDateString()],
            'ignore_saturdays' => ['nullable', 'boolean'],
            'ignore_sundays' => ['nullable', 'boolean'],
            'position' => ['required', 'integer', 'min:1', 'max:99'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['ignore_saturdays'] = $request->boolean('ignore_saturdays');
        $data['ignore_sundays'] = $request->boolean('ignore_sundays');

        $overlaps = $academicYear->periods()
            ->whereKeyNot($period->id)
            ->where(function (Builder $query) use ($data): void {
                $query->whereDate('starts_at', '<=', $data['ends_at'])
                    ->whereDate('ends_at', '>=', $data['starts_at']);
            })
            ->exists();

        if ($overlaps) {
            return back()->withErrors(['starts_at' => 'O período informado se sobrepõe a outro período deste ano letivo.'])->withInput();
        }

        $oldStartsAt = $period->starts_at->copy();
        $oldEndsAt = $period->ends_at->copy();

        DB::transaction(function () use ($academicYear, $period, $data, $oldStartsAt, $oldEndsAt): void {
            $period->update($data);
            $period->refresh();

            $this->resetCalendarDaysOutsidePeriods($academicYear, $oldStartsAt, $oldEndsAt);
            $this->applyPeriodToCalendarDays($period);
            $this->normalizeRecessBetweenPeriods($academicYear);
        });

        return redirect()->route('academic-years.periods.index', $academicYear)
            ->with('status', 'Período atualizado com sucesso.');
    }

    public function updateAssessmentRules(Request $request, AcademicYear $academicYear, AcademicPeriod $period): RedirectResponse
    {
        abort_unless($period->academic_year_id === $academicYear->id, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);
        $this->ensureYearIsOpen($academicYear);

        if ($period->diaryConsolidation()->where('consolidated', true)->exists()) {
            throw ValidationException::withMessages([
                'assessment_count' => 'Este período já foi consolidado pela gestão. Reabra o período antes de alterar as regras de avaliação.',
            ]);
        }

        $data = $request->validate([
            'assessment_count' => ['required', 'integer', 'min:1', 'max:10'],
            'weights' => ['required', 'array'],
            'weights.*' => ['required', 'integer', 'min:1', 'max:100'],
            'assessment_names' => ['nullable', 'array'],
            'assessment_names.*' => ['required', 'string', 'max:100'],
            'recovery_mode' => ['required', Rule::in(array_keys(AcademicPeriod::RECOVERY_MODE_LABELS))],
            'recovery_weight' => ['nullable', 'integer', 'min:1', 'max:100'],
            'recovery_replaced_position' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);
        $weights = array_values(array_slice($data['weights'], 0, $data['assessment_count']));
        $names = array_values(array_slice($data['assessment_names'] ?? [], 0, $data['assessment_count']));
        if (count($weights) !== (int) $data['assessment_count']) {
            throw ValidationException::withMessages(['weights' => 'Informe um peso para cada avaliação.']);
        }
        $names = collect(range(1, (int) $data['assessment_count']))
            ->map(fn (int $position): string => $names[$position - 1] ?? 'Avaliação '.$position)
            ->all();

        if ($data['recovery_mode'] === AcademicPeriod::RECOVERY_WEIGHTED && empty($data['recovery_weight'])) {
            $data['recovery_weight'] = 1;
        }
        if ($data['recovery_mode'] === AcademicPeriod::RECOVERY_REPLACE_ASSESSMENT && empty($data['recovery_replaced_position'])) {
            $data['recovery_replaced_position'] = 1;
        }
        if ($data['recovery_mode'] === AcademicPeriod::RECOVERY_REPLACE_ASSESSMENT && $data['recovery_replaced_position'] > $data['assessment_count']) {
            throw ValidationException::withMessages(['recovery_replaced_position' => 'Selecione uma avaliação existente neste período.']);
        }

        $existingRules = $period->assessmentRules()
            ->with('assessments.results')
            ->where('school_id', $academicYear->school_id)
            ->orderBy('position')
            ->get();
        $regularHasResults = $existingRules->flatMap->assessments
            ->contains(fn (DiaryAssessment $assessment): bool => ! $assessment->is_recovery && $assessment->results->isNotEmpty());
        $recoveryHasResults = DiaryAssessment::query()
            ->where('academic_period_id', $period->id)
            ->where('is_recovery', true)
            ->whereHas('results')
            ->exists();
        $regularConfigurationChanged = $this->regularAssessmentConfigurationChanged($existingRules, $weights, $names);
        $recoveryConfigurationChanged = $this->recoveryConfigurationChanged($period, $data);

        if ($regularHasResults && $regularConfigurationChanged && ! $recoveryConfigurationChanged) {
            throw ValidationException::withMessages([
                'assessment_count' => 'As avaliações já têm notas lançadas. Não é possível mudar quantidade, nomes ou pesos das avaliações já usadas.',
            ]);
        }

        if ($recoveryHasResults && $recoveryConfigurationChanged) {
            throw ValidationException::withMessages([
                'recovery_mode' => 'A recuperação já tem notas lançadas. Reabra e corrija os lançamentos antes de mudar esta regra.',
            ]);
        }

        if ($regularHasResults || $recoveryHasResults) {
            DB::transaction(function () use ($existingRules, $academicYear, $period, $data): void {
                $this->updateRecoveryConfiguration($academicYear, $period, $existingRules, $data);
            });

            return redirect()->route('academic-years.periods.index', $academicYear)
                ->with('status', 'Recuperação do período atualizada para a escola.');
        }

        DB::transaction(function () use ($existingRules, $weights, $names, $academicYear, $period, $data): void {
            $existingRules->flatMap->assessments->each->delete();
            $existingRules->each->delete();
            DiaryAssessment::query()->where('academic_period_id', $period->id)->where('is_recovery', true)->delete();

            $createdRules = collect();
            foreach ($weights as $index => $weight) {
                $rule = SchoolAssessmentRule::query()->create([
                    'school_id' => $academicYear->school_id,
                    'academic_period_id' => $period->id,
                    'name' => $names[$index],
                    'position' => $index + 1,
                    'weight' => $weight,
                    'maximum_score' => 10,
                ]);
                $this->createAssessmentsForRule($academicYear->id, $rule);
                $createdRules->put($rule->position, $rule);
            }

            $recoveryRule = $data['recovery_mode'] === AcademicPeriod::RECOVERY_REPLACE_ASSESSMENT
                ? $createdRules->get((int) $data['recovery_replaced_position'])
                : null;
            $period->update([
                'recovery_mode' => $data['recovery_mode'],
                'recovery_weight' => $data['recovery_mode'] === AcademicPeriod::RECOVERY_WEIGHTED ? $data['recovery_weight'] : null,
                'recovery_replaced_rule_id' => $recoveryRule?->id,
            ]);

            if ($data['recovery_mode'] !== AcademicPeriod::RECOVERY_NONE) {
                $this->createRecoveryAssessments($academicYear->id, $period);
            }
        });

        return redirect()->route('academic-years.periods.index', $academicYear)
            ->with('status', 'Avaliações do período configuradas para a escola.');
    }

    /**
     * @param Collection<int, SchoolAssessmentRule> $existingRules
     * @param array<int, int> $weights
     * @param array<int, string> $names
     */
    private function regularAssessmentConfigurationChanged(Collection $existingRules, array $weights, array $names): bool
    {
        $orderedRules = $existingRules->sortBy('position')->values();

        if ($orderedRules->count() !== count($weights)) {
            return true;
        }

        foreach ($orderedRules as $index => $rule) {
            if ((int) $rule->position !== $index + 1) {
                return true;
            }

            if ((int) $rule->weight !== (int) $weights[$index]) {
                return true;
            }

            if ((string) $rule->name !== (string) $names[$index]) {
                return true;
            }
        }

        return false;
    }

    private function recoveryConfigurationChanged(AcademicPeriod $period, array $data): bool
    {
        $period->loadMissing('recoveryReplacedRule');

        $currentMode = $period->recovery_mode ?? AcademicPeriod::RECOVERY_NONE;
        $requestedMode = $data['recovery_mode'];

        if ($currentMode !== $requestedMode) {
            return true;
        }

        if ($requestedMode === AcademicPeriod::RECOVERY_WEIGHTED) {
            return (int) $period->recovery_weight !== (int) $data['recovery_weight'];
        }

        if ($requestedMode === AcademicPeriod::RECOVERY_REPLACE_ASSESSMENT) {
            return (int) ($period->recoveryReplacedRule?->position ?? 0) !== (int) $data['recovery_replaced_position'];
        }

        return false;
    }

    /**
     * @param Collection<int, SchoolAssessmentRule> $rules
     */
    private function updateRecoveryConfiguration(AcademicYear $academicYear, AcademicPeriod $period, Collection $rules, array $data): void
    {
        DiaryAssessment::query()
            ->where('academic_period_id', $period->id)
            ->where('is_recovery', true)
            ->delete();

        $recoveryRule = $data['recovery_mode'] === AcademicPeriod::RECOVERY_REPLACE_ASSESSMENT
            ? $rules->firstWhere('position', (int) $data['recovery_replaced_position'])
            : null;

        $period->update([
            'recovery_mode' => $data['recovery_mode'],
            'recovery_weight' => $data['recovery_mode'] === AcademicPeriod::RECOVERY_WEIGHTED ? $data['recovery_weight'] : null,
            'recovery_replaced_rule_id' => $recoveryRule?->id,
        ]);

        if ($data['recovery_mode'] !== AcademicPeriod::RECOVERY_NONE) {
            $this->createRecoveryAssessments($academicYear->id, $period->fresh());
        }
    }

    private function ensureCanChangeApprovedCalendar(Request $request, AcademicYear $academicYear): void
    {
        if (! $academicYear->approved_at || $request->user()->isAdministrator()) {
            return;
        }

        throw ValidationException::withMessages([
            'approved_at' => 'Calendário aprovado só pode ser alterado pela Administração global.',
        ]);
    }

    private function ensureYearIsOpen(AcademicYear $academicYear): void
    {
        if (! $academicYear->isClosed()) {
            return;
        }

        throw ValidationException::withMessages([
            'closed_at' => 'Este ano letivo está fechado. Reabra o ano letivo antes de alterar períodos, consolidações ou comportamento.',
        ]);
    }

    /** @return Collection<int, StudentEnrollment> */
    private function behaviorEnrollments(AcademicYear $academicYear): Collection
    {
        return StudentEnrollment::query()
            ->with(['student', 'schoolClass'])
            ->where('status', StudentEnrollment::STATUS_ENROLLED)
            ->whereHas('schoolClass', fn (Builder $query) => $query
                ->where('academic_year_id', $academicYear->id)
                ->where('active', true))
            ->orderBy(Person::query()->select('full_name')->whereColumn('people.id', 'student_enrollments.person_id'))
            ->get();
    }

    private function missingBehaviorGrades(AcademicYear $academicYear, AcademicPeriod $period): int
    {
        $enrollmentIds = $this->behaviorEnrollments($academicYear)->pluck('id');

        if ($enrollmentIds->isEmpty()) {
            return 0;
        }

        $launched = StudentBehaviorGrade::query()
            ->where('academic_period_id', $period->id)
            ->whereIn('student_enrollment_id', $enrollmentIds)
            ->count();

        return max(0, $enrollmentIds->count() - $launched);
    }

    private function createAssessmentsForRule(int $academicYearId, SchoolAssessmentRule $rule): void
    {
        $assignments = SchoolClassComponent::query()
            ->where('active', true)
            ->whereHas('schoolClass', fn (Builder $query) => $query->where('academic_year_id', $academicYearId)->where('active', true))
            ->whereHas('component.course', fn (Builder $query) => $query->where('academic_year_id', $academicYearId))
            ->get();

        foreach ($assignments as $assignment) {
            DiaryAssessment::query()->updateOrCreate(
                [
                    'school_class_id' => $assignment->school_class_id,
                    'curriculum_component_id' => $assignment->curriculum_component_id,
                    'academic_period_id' => $rule->academic_period_id,
                    'school_assessment_rule_id' => $rule->id,
                ],
                [
                    'teacher_person_id' => $assignment->teacher_person_id,
                    'title' => $rule->label(),
                    'weight' => $rule->weight,
                    'maximum_score' => $rule->maximum_score,
                    'assessment_date' => now()->toDateString(),
                ]
            );
        }
    }

    private function createRecoveryAssessments(int $academicYearId, AcademicPeriod $period): void
    {
        $assignments = SchoolClassComponent::query()
            ->where('active', true)
            ->whereHas('schoolClass', fn (Builder $query) => $query->where('academic_year_id', $academicYearId)->where('active', true))
            ->whereHas('component.course', fn (Builder $query) => $query->where('academic_year_id', $academicYearId))
            ->get();

        foreach ($assignments as $assignment) {
            DiaryAssessment::query()->updateOrCreate(
                [
                    'school_class_id' => $assignment->school_class_id,
                    'curriculum_component_id' => $assignment->curriculum_component_id,
                    'academic_period_id' => $period->id,
                    'is_recovery' => true,
                ],
                [
                    'school_assessment_rule_id' => null,
                    'teacher_person_id' => $assignment->teacher_person_id,
                    'title' => 'Recuperação',
                    'weight' => $period->recovery_mode === AcademicPeriod::RECOVERY_WEIGHTED ? $period->recovery_weight : 0,
                    'maximum_score' => 10,
                    'assessment_date' => now()->toDateString(),
                    'recovery_mode' => $period->recovery_mode,
                    'recovery_replaced_rule_id' => $period->recovery_replaced_rule_id,
                ]
            );
        }
    }

    private function applyPeriodToCalendarDays(AcademicPeriod $period): void
    {
        $now = now();

        foreach (CarbonPeriod::create($period->starts_at, $period->ends_at) as $date) {
            $isIgnoredWeekend = ($period->ignore_saturdays && $date->isSaturday())
                || ($period->ignore_sundays && $date->isSunday());

            CalendarDay::query()->updateOrCreate(
                [
                    'academic_year_id' => $period->academic_year_id,
                    'date' => $date->toDateTimeString(),
                ],
                [
                    'type' => $isIgnoredWeekend ? CalendarDay::TYPE_WEEKEND : CalendarDay::TYPE_SCHOOL_DAY,
                    'counts_as_school_day' => ! $isIgnoredWeekend,
                    'title' => $isIgnoredWeekend ? 'Fim de semana' : null,
                    'description' => null,
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function resetCalendarDaysToVacation(AcademicYear $academicYear, $startsAt, $endsAt): void
    {
        $academicYear->days()
            ->whereDate('date', '>=', $startsAt->toDateString())
            ->whereDate('date', '<=', $endsAt->toDateString())
            ->update([
                'type' => CalendarDay::TYPE_FINAL_VACATION,
                'counts_as_school_day' => false,
                'title' => 'Férias',
                'description' => null,
            ]);
    }

    private function resetCalendarDaysOutsidePeriods(AcademicYear $academicYear, $startsAt, $endsAt): void
    {
        $periods = $academicYear->periods()->get(['id', 'starts_at', 'ends_at']);
        $preservedTypes = [
            CalendarDay::TYPE_HOLIDAY,
            CalendarDay::TYPE_TRAINING,
            CalendarDay::TYPE_CLASS_COUNCIL,
            CalendarDay::TYPE_OTHER,
        ];

        foreach (CarbonPeriod::create($startsAt, $endsAt) as $date) {
            $belongsToPeriod = $periods->contains(
                fn (AcademicPeriod $period): bool => $date->betweenIncluded($period->starts_at, $period->ends_at)
            );

            if ($belongsToPeriod) {
                continue;
            }

            $calendarDay = $academicYear->days()
                ->whereDate('date', $date->toDateString())
                ->first();

            if (in_array($calendarDay?->type, $preservedTypes, true)) {
                continue;
            }

            CalendarDay::query()->updateOrCreate(
                [
                    'academic_year_id' => $academicYear->id,
                    'date' => $date->toDateTimeString(),
                ],
                [
                    'type' => $date->isWeekend() ? CalendarDay::TYPE_WEEKEND : CalendarDay::TYPE_FINAL_VACATION,
                    'counts_as_school_day' => false,
                    'title' => $date->isWeekend() ? null : 'Férias',
                    'description' => null,
                ]
            );
        }
    }

    private function normalizeRecessBetweenPeriods(AcademicYear $academicYear): void
    {
        $periods = $academicYear->periods()
            ->orderBy('starts_at')
            ->get()
            ->values();

        if ($periods->count() < 2) {
            return;
        }

        $preservedTypes = [
            CalendarDay::TYPE_WEEKEND,
            CalendarDay::TYPE_HOLIDAY,
            CalendarDay::TYPE_TRAINING,
            CalendarDay::TYPE_CLASS_COUNCIL,
            CalendarDay::TYPE_OTHER,
        ];

        $periods->each(function (AcademicPeriod $period, int $index) use ($periods, $academicYear, $preservedTypes): void {
            $nextPeriod = $periods->get($index + 1);

            if (! $nextPeriod) {
                return;
            }

            $startsAt = $period->ends_at->copy()->addDay();
            $endsAt = $nextPeriod->starts_at->copy()->subDay();

            if ($startsAt->gt($endsAt)) {
                return;
            }

            foreach (CarbonPeriod::create($startsAt, $endsAt) as $date) {
                $calendarDay = $academicYear->days()
                    ->whereDate('date', $date->toDateString())
                    ->first();

                if ($date->isWeekend()) {
                    if (! $calendarDay) {
                        CalendarDay::query()->create([
                            'academic_year_id' => $academicYear->id,
                            'date' => $date->toDateTimeString(),
                            'type' => CalendarDay::TYPE_WEEKEND,
                            'counts_as_school_day' => false,
                            'title' => null,
                            'description' => null,
                        ]);
                    }

                    continue;
                }

                if ($calendarDay?->counts_as_school_day || in_array($calendarDay?->type, $preservedTypes, true)) {
                    continue;
                }

                CalendarDay::query()->updateOrCreate(
                    [
                        'academic_year_id' => $academicYear->id,
                        'date' => $date->toDateTimeString(),
                    ],
                    [
                        'type' => CalendarDay::TYPE_RECESS,
                        'counts_as_school_day' => false,
                        'title' => 'Recesso escolar',
                        'description' => null,
                    ]
                );
            }
        });
    }
}
