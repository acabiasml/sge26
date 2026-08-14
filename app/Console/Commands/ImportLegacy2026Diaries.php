<?php

namespace App\Console\Commands;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\CurriculumComponent;
use App\Models\DiaryAttendanceEntry;
use App\Models\DiaryAttendanceRecord;
use App\Models\DiaryContent;
use App\Models\Person;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportLegacy2026Diaries extends Command
{
    protected $signature = 'legacy:import-2026-diaries
        {--dry-run : Apenas confere as correspondências, sem alterar dados}
        {--source=* : Limita a importação a beaba, lar ou laura}';

    protected $description = 'Importa datas, frequências e conteúdos de 2026 das bases anteriores.';

    private const SOURCES = [
        'beaba' => 'u810745753_beaba.sql',
        'lar' => 'u810745753_lar.sql',
        'laura' => 'u810745753_laura.sql',
    ];

    /** @var array<string, int> */
    private array $stats = [
        'datas_lidas' => 0,
        'datas_correspondentes' => 0,
        'chamadas_criadas' => 0,
        'chamadas_atualizadas' => 0,
        'chamadas_inalteradas' => 0,
        'conteudos_criados' => 0,
        'conteudos_atualizados' => 0,
        'conteudos_inalterados' => 0,
        'presencas' => 0,
        'faltas' => 0,
        'sem_ano_letivo' => 0,
        'sem_periodo' => 0,
        'sem_turma' => 0,
        'sem_componente' => 0,
        'faltas_sem_matricula' => 0,
        'conflitos' => 0,
    ];

    /** @var list<array<string, mixed>> */
    private array $issues = [];

    /** @var list<array<string, mixed>> */
    private array $unmatchedAbsences = [];

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
        $components = collect($tables['componentes'] ?? [])->keyBy(fn (array $row): int => (int) ($row['id'] ?? 0));
        $courses = collect($tables['cursos'] ?? [])->keyBy(fn (array $row): int => (int) ($row['id'] ?? 0));
        $users = collect($tables['users'] ?? [])->keyBy(fn (array $row): int => (int) ($row['id'] ?? 0));
        $absences = collect($tables['frequencias'] ?? [])
            ->filter(fn (array $row): bool => (int) ($row['diarios_id'] ?? 0) > 0)
            ->groupBy(fn (array $row): int => (int) ($row['diarios_id'] ?? 0));

        $diaries = collect($tables['diarios'] ?? [])
            ->filter(fn (array $row): bool => str_starts_with((string) ($row['data'] ?? ''), '2026-'))
            ->sortBy(fn (array $row): string => (string) ($row['data'] ?? '').str_pad((string) ($row['id'] ?? 0), 12, '0', STR_PAD_LEFT));

        foreach ($diaries as $diary) {
            $this->stats['datas_lidas']++;
            $legacyComponent = $components->get((int) ($diary['componentes_id'] ?? 0));
            $legacyCourse = $courses->get((int) ($legacyComponent['cursos_id'] ?? 0));
            $legacyAbsences = $absences->get((int) ($diary['id'] ?? 0), collect());
            $this->importDiary($source, $diary, $legacyComponent, $legacyCourse, $legacyAbsences, $users, $dryRun);
        }
    }

    /**
     * @param  array<string, mixed>  $diary
     * @param  array<string, mixed>|null  $legacyComponent
     * @param  array<string, mixed>|null  $legacyCourse
     * @param  Collection<int, array<string, mixed>>  $legacyAbsences
     * @param  Collection<int, array<string, mixed>>  $users
     */
    private function importDiary(
        string $source,
        array $diary,
        ?array $legacyComponent,
        ?array $legacyCourse,
        Collection $legacyAbsences,
        Collection $users,
        bool $dryRun,
    ): void {
        $legacyDiaryId = (int) ($diary['id'] ?? 0);
        $date = $this->date($diary['data'] ?? null);

        $academicYear = AcademicYear::query()
            ->where('legacy_source', $source)
            ->where('legacy_id', (int) ($legacyCourse['calendarios_id'] ?? 0))
            ->first();
        if (! $date || ! $academicYear || ! $date->betweenIncluded($academicYear->starts_at, $academicYear->ends_at)) {
            $this->stats['sem_ano_letivo']++;
            $this->recordIssue($source, $legacyDiaryId, 'ano_letivo_nao_encontrado', ['data' => $diary['data'] ?? null]);

            return;
        }

        $period = AcademicPeriod::query()
            ->where('academic_year_id', $academicYear->id)
            ->whereDate('starts_at', '<=', $date)
            ->whereDate('ends_at', '>=', $date)
            ->first();
        if (! $period) {
            $this->stats['sem_periodo']++;
            $this->recordIssue($source, $legacyDiaryId, 'periodo_nao_encontrado', ['data' => $date->toDateString()]);

            return;
        }

        $class = SchoolClass::query()
            ->where('legacy_source', $source)
            ->where('legacy_id', (int) ($legacyCourse['id'] ?? 0))
            ->where('academic_year_id', $academicYear->id)
            ->first();
        if (! $class) {
            $this->stats['sem_turma']++;
            $this->recordIssue($source, $legacyDiaryId, 'turma_nao_encontrada', ['curso_antigo' => $legacyCourse['id'] ?? null]);

            return;
        }

        $legacyComponentId = (int) ($legacyComponent['id'] ?? 0);
        $component = CurriculumComponent::query()
            ->where('legacy_source', $source)
            ->where('legacy_id', $legacyComponentId)
            ->first();
        if (! $component) {
            $courseIds = $class->courses()->pluck('academic_courses.id');
            $candidates = CurriculumComponent::query()->whereIn('academic_course_id', $courseIds)->get()
                ->filter(fn (CurriculumComponent $candidate): bool => $this->normalized($candidate->name) === $this->normalized($legacyComponent['nome'] ?? ''));
            $component = $candidates->count() === 1 ? $candidates->first() : null;
        }
        if (! $component) {
            $this->stats['sem_componente']++;
            $this->recordIssue($source, $legacyDiaryId, 'componente_nao_encontrado', ['nome' => $legacyComponent['nome'] ?? null, 'turma' => $class->name]);

            return;
        }

        $record = DiaryAttendanceRecord::query()
            ->where('school_class_id', $class->id)
            ->where('curriculum_component_id', $component->id)
            ->whereDate('class_date', $date)
            ->first();
        if ($record && $record->legacy_source === null && $record->entries()->exists()) {
            $this->stats['conflitos']++;
            $this->recordIssue($source, $legacyDiaryId, 'chamada_manual_ja_existente', [
                'data' => $date->toDateString(), 'turma' => $class->name, 'componente' => $component->name,
            ]);

            return;
        }

        $lessonCount = max(1, (int) ($diary['geminada'] ?? 1));
        $eligibleEnrollments = $this->eligibleEnrollments($class, $date);
        $absentEnrollmentIds = $this->absentEnrollmentIds(
            $source,
            $legacyAbsences,
            $users,
            $eligibleEnrollments,
        );

        if ($absentEnrollmentIds === null) {
            $this->stats['faltas_sem_matricula'] += $legacyAbsences->count();
            $this->recordIssue($source, $legacyDiaryId, 'falta_sem_matricula_inequivoca', [
                'data' => $date->toDateString(),
                'turma' => $class->name,
                'componente' => $component->name,
                'faltas_nao_correspondentes' => $this->unmatchedAbsences,
            ]);

            return;
        }

        $this->stats['datas_correspondentes']++;
        $recordChanged = ! $record
            || $record->academic_period_id !== $period->id
            || $record->lesson_count !== $lessonCount
            || $record->entries()->count() !== $eligibleEnrollments->count();
        $this->stats[$record ? ($recordChanged ? 'chamadas_atualizadas' : 'chamadas_inalteradas') : 'chamadas_criadas']++;

        foreach ($eligibleEnrollments as $enrollment) {
            $absent = in_array($enrollment->id, $absentEnrollmentIds, true);
            $this->stats[$absent ? 'faltas' : 'presencas'] += $lessonCount;
        }

        $content = trim((string) ($diary['conteudo'] ?? ''));
        $existingContent = DiaryContent::query()
            ->where('school_class_id', $class->id)
            ->where('curriculum_component_id', $component->id)
            ->whereDate('class_date', $date)
            ->first();
        $contentConflict = $content !== '' && $existingContent && $existingContent->legacy_source === null
            && $this->normalized($existingContent->content) !== $this->normalized($content);
        if ($contentConflict) {
            $this->stats['conflitos']++;
            $this->recordIssue($source, $legacyDiaryId, 'conteudo_manual_diferente', [
                'data' => $date->toDateString(), 'turma' => $class->name, 'componente' => $component->name,
            ]);

            return;
        }

        if ($content !== '') {
            $contentChanged = ! $existingContent || $existingContent->content !== $content || $existingContent->academic_period_id !== $period->id;
            $this->stats[$existingContent ? ($contentChanged ? 'conteudos_atualizados' : 'conteudos_inalterados') : 'conteudos_criados']++;
        }

        if ($dryRun) {
            return;
        }

        $record ??= new DiaryAttendanceRecord;
        $record->fill([
            'school_class_id' => $class->id,
            'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id,
            'teacher_person_id' => null,
            'updated_by_person_id' => null,
            'class_date' => $date,
            'lesson_count' => $lessonCount,
            'notes' => null,
            'legacy_source' => $record->legacy_source ?? $source,
            'legacy_id' => $record->legacy_id ?? $legacyDiaryId,
            'legacy_metadata' => null,
        ])->save();

        foreach ($eligibleEnrollments as $enrollment) {
            $absent = in_array($enrollment->id, $absentEnrollmentIds, true);
            DiaryAttendanceEntry::query()->updateOrCreate(
                ['diary_attendance_record_id' => $record->id, 'student_enrollment_id' => $enrollment->id],
                [
                    'status' => $absent ? DiaryAttendanceRecord::STATUS_ABSENT : DiaryAttendanceRecord::STATUS_PRESENT,
                    'attended_lessons' => $absent ? 0 : $lessonCount,
                    'lesson_presence' => array_fill(0, $lessonCount, ! $absent),
                ],
            );
        }
        $record->entries()->whereNotIn('student_enrollment_id', $eligibleEnrollments->pluck('id'))->delete();

        if ($content !== '') {
            $existingContent ??= new DiaryContent;
            $existingContent->fill([
                'school_class_id' => $class->id,
                'curriculum_component_id' => $component->id,
                'academic_period_id' => $period->id,
                'class_date' => $date,
                'content' => $content,
                'created_by_person_id' => $existingContent->created_by_person_id,
                'updated_by_person_id' => null,
                'legacy_source' => $existingContent->legacy_source ?? $source,
                'legacy_id' => $existingContent->legacy_id ?? $legacyDiaryId,
                'legacy_metadata' => null,
            ])->save();
        }
    }

    /** @return Collection<int, StudentEnrollment> */
    private function eligibleEnrollments(SchoolClass $class, Carbon $date): Collection
    {
        return $class->enrollments()
            ->where(fn ($query) => $query->whereNull('enrolled_at')->orWhereDate('enrolled_at', '<=', $date))
            ->where(fn ($query) => $query->whereNull('transferred_at')->orWhereDate('transferred_at', '>=', $date))
            ->where(fn ($query) => $query->whereNull('cancelled_at')->orWhereDate('cancelled_at', '>=', $date))
            ->get();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $legacyAbsences
     * @param  Collection<int, array<string, mixed>>  $users
     * @param  Collection<int, StudentEnrollment>  $enrollments
     * @return list<int>|null
     */
    private function absentEnrollmentIds(string $source, Collection $legacyAbsences, Collection $users, Collection $enrollments): ?array
    {
        $ids = [];
        $this->unmatchedAbsences = [];
        foreach ($legacyAbsences as $absence) {
            $legacyUserId = (int) ($absence['users_id'] ?? 0);
            $legacyUser = $users->get($legacyUserId);
            $person = Person::query()->where('legacy_source', $source)->where('legacy_id', $legacyUserId)->first();

            if (! $person) {
                $person = Person::query()->where('legacy_id', $legacyUserId)
                    ->whereIn('id', $enrollments->pluck('person_id'))->first();
            }
            if (! $person) {
                $cpf = preg_replace('/\D+/', '', (string) ($legacyUser['cpf'] ?? ''));
                $person = strlen($cpf) === 11 ? Person::query()->where('cpf', $cpf)->first() : null;
            }
            if (! $person) {
                $matches = $enrollments->loadMissing('student')->filter(fn (StudentEnrollment $enrollment): bool => $this->normalized($enrollment->student?->full_name) === $this->normalized($legacyUser['nome'] ?? ''));
                $person = $matches->count() === 1 ? $matches->first()->student : null;
            }

            $enrollment = $person ? $enrollments->firstWhere('person_id', $person->id) : null;
            if (! $enrollment) {
                $this->unmatchedAbsences[] = [
                    'usuario_antigo_id' => $legacyUserId,
                    'nome' => $legacyUser['nome'] ?? null,
                    'cpf' => $legacyUser['cpf'] ?? null,
                    'pessoa_encontrada_id' => $person?->id,
                ];

                continue;
            }
            $ids[] = $enrollment->id;
        }

        return $this->unmatchedAbsences === [] ? array_values(array_unique($ids)) : null;
    }

    private function date(mixed $value): ?Carbon
    {
        try {
            return $value ? Carbon::parse((string) $value)->startOfDay() : null;
        } catch (\Throwable) {
            return null;
        }
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
    private function recordIssue(string $source, int $legacyDiaryId, string $reason, array $context = []): void
    {
        $this->issues[] = ['fonte' => $source, 'diario_antigo_id' => $legacyDiaryId, 'motivo' => $reason] + $context;
    }

    private function writeReport(bool $dryRun): string
    {
        $path = 'reports/importacao-diarios-2026-'.now()->format('Ymd-His').'.json';
        Storage::disk('local')->put($path, json_encode([
            'modo' => $dryRun ? 'simulacao' : 'importacao',
            'gerado_em' => now()->toIso8601String(),
            'resumo' => $this->stats,
            'pendencias' => $this->issues,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return storage_path('app/private/'.$path);
    }
}
