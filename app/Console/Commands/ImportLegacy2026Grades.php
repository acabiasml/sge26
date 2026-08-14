<?php

namespace App\Console\Commands;

use App\Models\AcademicPeriod;
use App\Models\CurriculumComponent;
use App\Models\DiaryAssessment;
use App\Models\DiaryAssessmentResult;
use App\Models\Person;
use App\Models\SchoolAssessmentRule;
use App\Models\SchoolClass;
use App\Models\StudentBehaviorGrade;
use App\Models\StudentEnrollment;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportLegacy2026Grades extends Command
{
    protected $signature = 'legacy:import-2026-grades
        {--dry-run : Apenas confere as correspondências, sem alterar dados}
        {--source=* : Limita a importação a beaba, lar ou laura}';

    protected $description = 'Importa as médias do 1º e 2º períodos de 2026 das bases anteriores.';

    private const SOURCES = [
        'beaba' => 'u810745753_beaba.sql',
        'lar' => 'u810745753_lar.sql',
        'laura' => 'u810745753_laura.sql',
    ];

    /** @var array<string, int> */
    private array $stats = [
        'lidas' => 0,
        'correspondencias' => 0,
        'criadas' => 0,
        'atualizadas' => 0,
        'inalteradas' => 0,
        'sem_periodo' => 0,
        'sem_componente' => 0,
        'sem_turma' => 0,
        'sem_estudante' => 0,
        'sem_matricula' => 0,
        'conflitos' => 0,
        'invalidas' => 0,
    ];

    /** @var list<array<string, mixed>> */
    private array $issues = [];

    public function handle(): int
    {
        $selected = $this->option('source');
        $sources = $selected === []
            ? self::SOURCES
            : array_intersect_key(self::SOURCES, array_flip($selected));

        if ($sources === []) {
            $this->error('Informe uma fonte válida: beaba, lar ou laura.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $operation = function () use ($sources, $dryRun): void {
            Model::withoutEvents(function () use ($sources, $dryRun): void {
                foreach ($sources as $source => $filename) {
                    $this->importSource($source, $filename, $dryRun);
                }
            });
        };

        $dryRun ? $operation() : DB::transaction($operation);

        $reportPath = $this->writeReport($dryRun);
        $this->newLine();
        $this->table(['Resultado', 'Quantidade'], collect($this->stats)->map(fn (int $value, string $key): array => [$key, $value])->values()->all());
        $this->info(($dryRun ? 'Simulação' : 'Importação').' concluída. Relatório: '.$reportPath);

        return self::SUCCESS;
    }

    private function importSource(string $source, string $filename, bool $dryRun): void
    {
        if (! Storage::disk('local')->exists($filename)) {
            $this->recordIssue($source, 0, 'arquivo_ausente', ['arquivo' => $filename]);

            return;
        }

        $tables = $this->parseDump(Storage::disk('local')->get($filename));
        $calendarIds = collect($tables['calendarios'] ?? [])
            ->filter(fn (array $calendar): bool => (int) ($calendar['ano'] ?? 0) === 2026)
            ->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $periodIds = collect($tables['periodos'] ?? [])
            ->filter(fn (array $period): bool => in_array((int) ($period['calendarios_id'] ?? 0), $calendarIds, true))
            ->groupBy(fn (array $period): int => (int) $period['calendarios_id'])
            ->flatMap(fn ($periods) => $periods->sortBy(fn (array $period): string => (string) ($period['inicio'] ?? '9999-12-31'))->take(2))
            ->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $grades = collect($tables['medias'] ?? [])
            ->filter(fn (array $grade): bool => in_array((int) ($grade['periodos_id'] ?? 0), $periodIds, true))
            ->sortBy(fn (array $grade): int => (int) ($grade['id'] ?? 0))
            ->keyBy(fn (array $grade): string => implode(':', [
                (int) ($grade['users_id'] ?? 0),
                (int) ($grade['componentes_id'] ?? 0),
                (int) ($grade['periodos_id'] ?? 0),
            ]));

        $legacyUsers = collect($tables['users'] ?? [])->keyBy(fn (array $user): int => (int) ($user['id'] ?? 0));
        $legacyComponents = collect($tables['componentes'] ?? [])->keyBy(fn (array $component): int => (int) ($component['id'] ?? 0));

        foreach ($grades as $grade) {
            $this->stats['lidas']++;
            $this->importGrade(
                $source,
                $grade,
                $legacyUsers->get((int) ($grade['users_id'] ?? 0)),
                $legacyComponents->get((int) ($grade['componentes_id'] ?? 0)),
                $dryRun,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $grade
     * @param  array<string, mixed>|null  $legacyUser
     * @param  array<string, mixed>|null  $legacyComponent
     */
    private function importGrade(string $source, array $grade, ?array $legacyUser, ?array $legacyComponent, bool $dryRun): void
    {
        $legacyGradeId = (int) ($grade['id'] ?? 0);
        $legacyPeriodId = (int) ($grade['periodos_id'] ?? 0);
        $legacyComponentId = (int) ($grade['componentes_id'] ?? 0);
        $legacyUserId = (int) ($grade['users_id'] ?? 0);
        $score = $this->numericScore($grade['nota'] ?? null);

        if ($legacyGradeId <= 0 || $score === null || $score < 0 || $score > 10) {
            $this->stats['invalidas']++;
            $this->recordIssue($source, $legacyGradeId, 'nota_invalida', ['nota' => $grade['nota'] ?? null]);

            return;
        }

        $period = AcademicPeriod::query()->with('academicYear')->where('legacy_source', $source)->where('legacy_id', $legacyPeriodId)->first();
        if (! $period || ! $period->academicYear || (int) $period->academicYear->reference_year !== 2026) {
            $this->stats['sem_periodo']++;
            $this->recordIssue($source, $legacyGradeId, 'periodo_nao_encontrado', ['periodo_antigo' => $legacyPeriodId]);

            return;
        }

        $class = SchoolClass::query()
            ->where('legacy_source', $source)
            ->where('legacy_id', (int) ($legacyComponent['cursos_id'] ?? 0))
            ->where('academic_year_id', $period->academic_year_id)
            ->first();
        if (! $class) {
            $this->stats['sem_turma']++;
            $this->recordIssue($source, $legacyGradeId, 'turma_equivalente_nao_encontrada', ['componente_antigo' => $legacyComponent['nome'] ?? $legacyComponentId]);

            return;
        }

        $person = Person::query()->where('legacy_source', $source)->where('legacy_id', $legacyUserId)->first();
        $cpf = preg_replace('/\D+/', '', (string) ($legacyUser['cpf'] ?? ''));
        if (! $person && strlen($cpf) === 11) {
            $person = Person::query()->where('cpf', $cpf)->first();
        }

        if (! $person) {
            $birthDate = $legacyUser['nascimento'] ?? null;
            $candidates = $class->enrollments()->with('student')->get()
                ->pluck('student')
                ->filter(fn (?Person $candidate): bool => $candidate
                    && $this->normalized($candidate->full_name) === $this->normalized($legacyUser['nome'] ?? '')
                    && (! $birthDate || $candidate->birth_date?->format('Y-m-d') === $birthDate));
            $person = $candidates->count() === 1 ? $candidates->first() : null;
        }

        if (! $person) {
            $this->stats['sem_estudante']++;
            $this->recordIssue($source, $legacyGradeId, 'estudante_nao_encontrado', ['usuario_antigo' => $legacyUserId]);

            return;
        }

        $isBehavior = $this->normalized($legacyComponent['nome'] ?? '') === 'comportamento';
        $component = null;

        if (! $isBehavior) {
            $component = CurriculumComponent::query()->with('course')->where('legacy_source', $source)->where('legacy_id', $legacyComponentId)->first();
            if (! $component) {
                $courseIds = $class->courses()->pluck('academic_courses.id');
                $componentCandidates = CurriculumComponent::query()
                    ->whereIn('academic_course_id', $courseIds)
                    ->get()
                    ->filter(fn (CurriculumComponent $candidate): bool => $this->normalized($candidate->name) === $this->normalized($legacyComponent['nome'] ?? ''));
                $component = $componentCandidates->count() === 1 ? $componentCandidates->first() : null;
            }

            if (! $component) {
                $this->stats['sem_componente']++;
                $this->recordIssue($source, $legacyGradeId, 'componente_nao_encontrado', [
                    'componente_antigo' => $legacyComponentId,
                    'nome' => $legacyComponent['nome'] ?? null,
                    'turma' => $class->name,
                ]);

                return;
            }
        }

        $enrollment = StudentEnrollment::query()->where('school_class_id', $class->id)->where('person_id', $person->id)->first();
        if (! $enrollment) {
            $yearEnrollmentCandidates = StudentEnrollment::query()
                ->where('person_id', $person->id)
                ->whereHas('schoolClass', fn ($query) => $query->where('academic_year_id', $period->academic_year_id))
                ->with('schoolClass.courses')
                ->get();
            $compatibleCandidates = $yearEnrollmentCandidates
                ->filter(fn (StudentEnrollment $candidate): bool => ! $component
                    || $candidate->schoolClass->courses->contains('id', $component->academic_course_id));
            $enrollment = $compatibleCandidates->count() === 1
                ? $compatibleCandidates->first()
                : ($yearEnrollmentCandidates->count() === 1 ? $yearEnrollmentCandidates->first() : null);
        }

        if (! $enrollment) {
            $this->stats['sem_matricula']++;
            $this->recordIssue($source, $legacyGradeId, 'matricula_equivalente_nao_encontrada', ['estudante' => $person->full_name, 'turma' => $class->name]);

            return;
        }

        if ($isBehavior) {
            $existingBehavior = StudentBehaviorGrade::query()
                ->where('academic_period_id', $period->id)
                ->where('student_enrollment_id', $enrollment->id)
                ->first();
            $this->stats['correspondencias']++;

            if ($existingBehavior && abs((float) $existingBehavior->score - $score) < 0.001) {
                $this->stats['inalteradas']++;

                return;
            }

            if (! $dryRun) {
                StudentBehaviorGrade::query()->updateOrCreate(
                    ['academic_period_id' => $period->id, 'student_enrollment_id' => $enrollment->id],
                    ['updated_by_person_id' => null, 'score' => $score, 'notes' => null],
                );
            }

            $this->stats[$existingBehavior ? 'atualizadas' : 'criadas']++;

            return;
        }

        $legacyAssessmentId = $legacyComponentId * 100000 + $legacyPeriodId;
        $assessment = DiaryAssessment::query()->where('legacy_source', $source)->where('legacy_id', $legacyAssessmentId)->first();

        if (! $assessment && DiaryAssessment::query()
            ->where('school_class_id', $class->id)
            ->where('curriculum_component_id', $component->id)
            ->where('academic_period_id', $period->id)
            ->whereHas('results')
            ->exists()) {
            $this->stats['conflitos']++;
            $this->recordIssue($source, $legacyGradeId, 'diario_possui_notas_sem_correspondencia_segura', [
                'estudante' => $person->name,
                'turma' => $class->name,
                'componente' => $component->name,
                'periodo' => $period->name,
            ]);

            return;
        }

        $existingResult = $assessment?->results()->where('student_enrollment_id', $enrollment->id)->first();
        $this->stats['correspondencias']++;

        if ($existingResult && abs((float) $existingResult->score - $score) < 0.001) {
            $this->stats['inalteradas']++;

            return;
        }

        if ($dryRun) {
            $this->stats[$existingResult ? 'atualizadas' : 'criadas']++;

            return;
        }

        if (! $assessment) {
            $rule = $this->periodAverageRule($period);
            $assessment = DiaryAssessment::query()->create([
                'school_class_id' => $class->id,
                'curriculum_component_id' => $component->id,
                'academic_period_id' => $period->id,
                'school_assessment_rule_id' => $rule->id,
                'is_recovery' => false,
                'teacher_person_id' => null,
                'title' => 'Média do período',
                'weight' => 1,
                'maximum_score' => 10,
                'assessment_date' => null,
                'notes' => null,
                'legacy_source' => $source,
                'legacy_id' => $legacyAssessmentId,
            ]);
        } else {
            $assessment->update(['teacher_person_id' => null]);
        }

        DiaryAssessmentResult::query()->updateOrCreate(
            ['diary_assessment_id' => $assessment->id, 'student_enrollment_id' => $enrollment->id],
            ['updated_by_person_id' => null, 'score' => $score, 'notes' => null],
        );
        $this->stats[$existingResult ? 'atualizadas' : 'criadas']++;
    }

    private function periodAverageRule(AcademicPeriod $period): SchoolAssessmentRule
    {
        $schoolId = (int) $period->academicYear->school_id;
        $rule = SchoolAssessmentRule::query()
            ->where('school_id', $schoolId)
            ->where('academic_period_id', $period->id)
            ->where('name', 'Média do período')
            ->first();

        if ($rule) {
            return $rule;
        }

        $reusable = SchoolAssessmentRule::query()
            ->where('school_id', $schoolId)
            ->where('academic_period_id', $period->id)
            ->whereDoesntHave('assessments.results')
            ->orderBy('position')
            ->first();

        if ($reusable) {
            $reusable->update(['name' => 'Média do período', 'weight' => 1, 'maximum_score' => 10]);

            return $reusable;
        }

        return SchoolAssessmentRule::query()->create([
            'school_id' => $schoolId,
            'academic_period_id' => $period->id,
            'name' => 'Média do período',
            'position' => ((int) SchoolAssessmentRule::query()->where('academic_period_id', $period->id)->max('position')) + 1,
            'weight' => 1,
            'maximum_score' => 10,
        ]);
    }

    private function numericScore(mixed $value): ?float
    {
        $value = str_replace(',', '.', trim((string) $value));

        return $value !== '' && is_numeric($value) ? round((float) $value, 2) : null;
    }

    private function normalized(mixed $value): string
    {
        return Str::of((string) $value)->ascii()->lower()->squish()->value();
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function parseDump(string $sql): array
    {
        preg_match_all('/INSERT INTO `([^`]+)` \((.*?)\) VALUES\s*(.*?);/s', $sql, $matches, PREG_SET_ORDER);
        $tables = [];

        foreach ($matches as $match) {
            $columns = array_map(fn (string $column): string => trim($column, " `\r\n\t"), explode(',', $match[2]));
            foreach ($this->splitRows(trim($match[3])) as $row) {
                $fields = str_getcsv($row, ',', "'", '\\');
                $record = [];
                foreach ($columns as $index => $column) {
                    $value = isset($fields[$index]) ? trim((string) $fields[$index]) : null;
                    $record[$column] = ($value === '' || $value === 'NULL') ? null : $value;
                }
                $tables[$match[1]][] = $record;
            }
        }

        return $tables;
    }

    /** @return list<string> */
    private function splitRows(string $values): array
    {
        $rows = [];
        $depth = 0;
        $quote = false;
        $escape = false;
        $buffer = '';

        for ($i = 0, $length = strlen($values); $i < $length; $i++) {
            $char = $values[$i];
            if ($quote) {
                $buffer .= $char;
                if ($escape) {
                    $escape = false;
                } elseif ($char === '\\') {
                    $escape = true;
                } elseif ($char === "'") {
                    $quote = false;
                }

                continue;
            }
            if ($char === "'") {
                $quote = true;
                $buffer .= $char;

                continue;
            }
            if ($char === '(') {
                if ($depth > 0) {
                    $buffer .= $char;
                }
                $depth++;

                continue;
            }
            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    $rows[] = $buffer;
                    $buffer = '';
                } else {
                    $buffer .= $char;
                }

                continue;
            }
            if ($depth > 0) {
                $buffer .= $char;
            }
        }

        return $rows;
    }

    /** @param array<string, mixed> $context */
    private function recordIssue(string $source, int $legacyGradeId, string $reason, array $context = []): void
    {
        $this->issues[] = ['fonte' => $source, 'nota_antiga_id' => $legacyGradeId, 'motivo' => $reason] + $context;
    }

    private function writeReport(bool $dryRun): string
    {
        $path = 'reports/importacao-notas-2026-'.now()->format('Ymd-His').'.json';
        Storage::disk('local')->put($path, json_encode([
            'modo' => $dryRun ? 'simulacao' : 'importacao',
            'gerado_em' => now()->toIso8601String(),
            'resumo' => $this->stats,
            'pendencias' => $this->issues,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return storage_path('app/private/'.$path);
    }
}
