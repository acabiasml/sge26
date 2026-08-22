<?php

namespace App\Console\Commands;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\CurriculumComponent;
use App\Models\DiaryAssessment;
use App\Models\DiaryAssessmentResult;
use App\Models\DiaryAttendanceEntry;
use App\Models\DiaryAttendanceRecord;
use App\Models\DiaryContent;
use App\Models\SchoolAssessmentRule;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportLegacyDiaryPdfManifest extends Command
{
    protected $signature = 'legacy:import-diary-pdfs
        {manifest=legacy-diary-manifest.json : Arquivo JSON no disco local privado}
        {--dry-run : Apenas simula e gera o relatório}
        {--source=* : Limita a beaba, lar ou laura}';

    protected $description = 'Preenche somente informações ausentes a partir dos diários legados em PDF.';

    /** @var array<string, int> */
    private array $stats = [
        'documentos' => 0,
        'documentos_correspondentes' => 0,
        'aulas_lidas' => 0,
        'chamadas_criadas' => 0,
        'chamadas_existentes' => 0,
        'marcacoes_criadas' => 0,
        'marcacoes_preservadas' => 0,
        'conteudos_criados' => 0,
        'conteudos_preenchidos' => 0,
        'conteudos_preservados' => 0,
        'notas_lidas' => 0,
        'notas_criadas' => 0,
        'notas_preservadas' => 0,
        'sem_turma' => 0,
        'sem_componente' => 0,
        'sem_periodo' => 0,
        'sem_estudante' => 0,
        'conflitos' => 0,
    ];

    /** @var list<array<string, mixed>> */
    private array $issues = [];

    public function handle(): int
    {
        $path = (string) $this->argument('manifest');
        if (! Storage::disk('local')->exists($path)) {
            $this->error('Manifesto não encontrado no armazenamento privado: '.$path);

            return self::FAILURE;
        }

        $manifest = json_decode(Storage::disk('local')->get($path), true, 512, JSON_THROW_ON_ERROR);
        if (($manifest['format'] ?? null) !== 'legacy-diary-pdf-manifest-v1' || ! is_array($manifest['documents'] ?? null)) {
            $this->error('Formato de manifesto inválido.');

            return self::FAILURE;
        }

        $selected = array_values(array_filter((array) $this->option('source')));
        $documents = collect($manifest['documents'])
            ->when($selected !== [], fn (Collection $items) => $items->whereIn('source', $selected));
        $dryRun = (bool) $this->option('dry-run');

        $operation = function () use ($documents, $dryRun): void {
            Model::withoutEvents(function () use ($documents, $dryRun): void {
                foreach ($documents as $document) {
                    $this->importDocument($document, $dryRun);
                }
            });
        };

        $dryRun ? $operation() : DB::transaction($operation);

        $report = $this->writeReport($path, $dryRun);
        $this->table(['Resultado', 'Quantidade'], collect($this->stats)->map(fn (int $value, string $key) => [$key, $value])->values()->all());
        $this->info(($dryRun ? 'Simulação' : 'Importação').' concluída. Relatório: '.$report);

        return $this->stats['sem_turma'] + $this->stats['sem_componente'] + $this->stats['sem_periodo'] > 0
            ? self::FAILURE
            : self::SUCCESS;
    }

    /** @param array<string, mixed> $document */
    private function importDocument(array $document, bool $dryRun): void
    {
        $this->stats['documentos']++;
        $source = (string) ($document['source'] ?? '');
        $file = (string) ($document['file'] ?? '');
        $year = AcademicYear::query()->where('reference_year', 2026)->where('legacy_source', $source)->first();
        $class = $year ? $this->matchClass($year, (string) ($document['class_name'] ?? '')) : null;
        if (! $class) {
            $this->stats['sem_turma']++;
            $this->issue($file, 'turma_nao_encontrada', ['fonte' => $source, 'turma' => $document['class_name'] ?? null]);

            return;
        }

        $component = $this->matchComponent($class, (string) ($document['component_name'] ?? ''));
        if (! $component) {
            $this->stats['sem_componente']++;
            $this->issue($file, 'componente_nao_encontrado', ['turma' => $class->name, 'componente' => $document['component_name'] ?? null]);

            return;
        }

        $this->stats['documentos_correspondentes']++;
        $enrollments = $class->enrollments()->with('student')->get();
        $students = $this->studentMap($enrollments);

        foreach ((array) ($document['sessions'] ?? []) as $session) {
            $this->importSession($file, $class, $component, $session, $enrollments, $students, $dryRun);
        }

        $this->importGrades($file, $source, $class, $component, (array) ($document['students'] ?? []), $enrollments, $students, $dryRun);
    }

    private function matchClass(AcademicYear $year, string $name): ?SchoolClass
    {
        $wanted = $this->canonicalClass($name);
        $matches = $year->classes()->get()->filter(fn (SchoolClass $class) => $this->canonicalClass($class->name) === $wanted);

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function matchComponent(SchoolClass $class, string $name): ?CurriculumComponent
    {
        $wanted = $this->canonicalComponent($name);
        $matches = CurriculumComponent::query()
            ->whereIn('id', $class->componentAssignments()->pluck('curriculum_component_id'))
            ->get()
            ->filter(fn (CurriculumComponent $component) => $this->canonicalComponent($component->name) === $wanted);

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /** @param Collection<int, StudentEnrollment> $enrollments
     *  @return array<string, StudentEnrollment|null>
     */
    private function studentMap(Collection $enrollments): array
    {
        return $enrollments->groupBy(fn (StudentEnrollment $enrollment) => $this->normalized($enrollment->student?->full_name))
            ->map(fn (Collection $matches) => $matches->count() === 1 ? $matches->first() : null)
            ->all();
    }

    /** @param array<string, mixed> $session
     *  @param Collection<int, StudentEnrollment> $enrollments
     *  @param array<string, StudentEnrollment|null> $students
     */
    private function importSession(string $file, SchoolClass $class, CurriculumComponent $component, array $session, Collection $enrollments, array $students, bool $dryRun): void
    {
        $this->stats['aulas_lidas']++;
        try {
            $date = Carbon::parse((string) ($session['date'] ?? ''))->startOfDay();
        } catch (\Throwable) {
            $this->stats['sem_periodo']++;
            $this->issue($file, 'data_invalida', ['data' => $session['date'] ?? null]);

            return;
        }
        $period = AcademicPeriod::query()->where('academic_year_id', $class->academic_year_id)
            ->whereDate('starts_at', '<=', $date)->whereDate('ends_at', '>=', $date)->first();
        if (! $period) {
            $this->stats['sem_periodo']++;
            $this->issue($file, 'periodo_da_aula_nao_encontrado', ['data' => $date->toDateString()]);

            return;
        }

        $record = DiaryAttendanceRecord::query()->where('school_class_id', $class->id)
            ->where('curriculum_component_id', $component->id)->whereDate('class_date', $date)->first();
        $this->stats[$record ? 'chamadas_existentes' : 'chamadas_criadas']++;
        if (! $record && ! $dryRun) {
            $record = DiaryAttendanceRecord::query()->create([
                'school_class_id' => $class->id,
                'curriculum_component_id' => $component->id,
                'academic_period_id' => $period->id,
                'teacher_person_id' => null,
                'updated_by_person_id' => null,
                'class_date' => $date,
                'lesson_count' => max(1, (int) ($session['lesson_count'] ?? 1)),
                'notes' => null,
                'legacy_source' => 'pdf-'.$file,
                'legacy_id' => null,
                'legacy_metadata' => ['source_file' => $file],
            ]);
        }

        foreach ((array) ($session['attendance'] ?? []) as $studentName => $status) {
            $enrollment = $students[$this->normalized($studentName)] ?? null;
            if (! $enrollment) {
                $this->stats['sem_estudante']++;
                $this->issue($file, 'estudante_da_frequencia_nao_encontrado', ['data' => $date->toDateString(), 'estudante' => $studentName, 'turma' => $class->name]);
                continue;
            }
            $existing = $record?->entries()->where('student_enrollment_id', $enrollment->id)->exists() ?? false;
            if ($existing) {
                $this->stats['marcacoes_preservadas']++;
                continue;
            }
            $this->stats['marcacoes_criadas']++;
            if (! $dryRun && $record) {
                $lessonCount = $record->lesson_count;
                $present = $status === 'present';
                DiaryAttendanceEntry::query()->create([
                    'diary_attendance_record_id' => $record->id,
                    'student_enrollment_id' => $enrollment->id,
                    'status' => $present ? DiaryAttendanceRecord::STATUS_PRESENT : DiaryAttendanceRecord::STATUS_ABSENT,
                    'attended_lessons' => $present ? $lessonCount : 0,
                    'lesson_presence' => array_fill(0, $lessonCount, $present),
                ]);
            }
        }

        $contentText = trim((string) ($session['content'] ?? ''));
        if ($contentText === '') {
            return;
        }
        $content = DiaryContent::query()->where('school_class_id', $class->id)
            ->where('curriculum_component_id', $component->id)->whereDate('class_date', $date)->first();
        if ($content && trim((string) $content->content) !== '') {
            $this->stats['conteudos_preservados']++;
            return;
        }
        $this->stats[$content ? 'conteudos_preenchidos' : 'conteudos_criados']++;
        if ($dryRun) {
            return;
        }
        $content ??= new DiaryContent;
        $content->fill([
            'school_class_id' => $class->id,
            'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id,
            'class_date' => $date,
            'content' => $contentText,
            'created_by_person_id' => $content->created_by_person_id,
            'updated_by_person_id' => null,
            'legacy_source' => $content->legacy_source ?? 'pdf-'.$file,
            'legacy_id' => $content->legacy_id,
            'legacy_metadata' => $content->legacy_metadata ?? ['source_file' => $file],
        ])->save();
    }

    /** @param list<array<string, mixed>> $rows
     *  @param Collection<int, StudentEnrollment> $enrollments
     *  @param array<string, StudentEnrollment|null> $students
     */
    private function importGrades(string $file, string $source, SchoolClass $class, CurriculumComponent $component, array $rows, Collection $enrollments, array $students, bool $dryRun): void
    {
        foreach ([1, 2, 3, 4] as $position) {
            $period = AcademicPeriod::query()->where('academic_year_id', $class->academic_year_id)->where('position', $position)->first();
            $periodRows = collect($rows)->filter(fn (array $row) => array_key_exists((string) $position, (array) ($row['grades'] ?? [])));
            if ($periodRows->isEmpty()) {
                continue;
            }
            if (! $period) {
                $this->stats['sem_periodo'] += $periodRows->count();
                continue;
            }
            $assessment = DiaryAssessment::query()->where('school_class_id', $class->id)
                ->where('curriculum_component_id', $component->id)->where('academic_period_id', $period->id)
                ->where('legacy_source', 'pdf-'.$source)->first();
            $manualResultsExist = ! $assessment && DiaryAssessmentResult::query()->whereHas('assessment', fn ($query) => $query
                ->where('school_class_id', $class->id)->where('curriculum_component_id', $component->id)->where('academic_period_id', $period->id))->exists();
            if ($manualResultsExist) {
                $this->stats['conflitos'] += $periodRows->count();
                $this->issue($file, 'notas_manuais_no_periodo_preservadas', ['turma' => $class->name, 'componente' => $component->name, 'periodo' => $period->name]);
                continue;
            }
            foreach ($periodRows as $row) {
                $this->stats['notas_lidas']++;
                $enrollment = $this->matchEnrollment($row, $enrollments, $students, $source);
                if (! $enrollment) {
                    $this->stats['sem_estudante']++;
                    $this->issue($file, 'estudante_da_nota_nao_encontrado', ['estudante' => $row['student_name'] ?? null, 'turma' => $class->name]);
                    continue;
                }
                $existing = $assessment?->results()->where('student_enrollment_id', $enrollment->id)->exists() ?? false;
                if ($existing) {
                    $this->stats['notas_preservadas']++;
                    continue;
                }
                $this->stats['notas_criadas']++;
                if ($dryRun) {
                    continue;
                }
                $assessment ??= $this->createAssessment($class, $component, $period, $source, $file);
                DiaryAssessmentResult::query()->create([
                    'diary_assessment_id' => $assessment->id,
                    'student_enrollment_id' => $enrollment->id,
                    'updated_by_person_id' => null,
                    'score' => (float) $row['grades'][(string) $position],
                    'notes' => null,
                ]);
            }
        }
    }

    /** @param array<string, mixed> $row
     *  @param Collection<int, StudentEnrollment> $enrollments
     *  @param array<string, StudentEnrollment|null> $students
     */
    private function matchEnrollment(array $row, Collection $enrollments, array $students, string $source): ?StudentEnrollment
    {
        $legacyId = (int) ($row['legacy_student_id'] ?? 0);
        $legacy = $legacyId > 0 ? $enrollments->first(fn (StudentEnrollment $enrollment) => $enrollment->student?->legacy_source === $source && (int) $enrollment->student?->legacy_id === $legacyId) : null;

        return $legacy ?: ($students[$this->normalized($row['student_name'] ?? '')] ?? null);
    }

    private function createAssessment(SchoolClass $class, CurriculumComponent $component, AcademicPeriod $period, string $source, string $file): DiaryAssessment
    {
        $rule = SchoolAssessmentRule::query()->where('academic_period_id', $period->id)->orderBy('position')->firstOrFail();

        return DiaryAssessment::query()->create([
            'school_class_id' => $class->id,
            'curriculum_component_id' => $component->id,
            'academic_period_id' => $period->id,
            'school_assessment_rule_id' => $rule->id,
            'is_recovery' => false,
            'teacher_person_id' => null,
            'title' => 'Média importada do diário',
            'weight' => 10,
            'maximum_score' => 10,
            'assessment_date' => null,
            'notes' => null,
            'legacy_source' => 'pdf-'.$source,
            'legacy_id' => null,
            'legacy_metadata' => ['source_file' => $file],
        ]);
    }

    private function canonicalClass(string $value): string
    {
        return str_replace([' ensino fundamental', ' ensino medio'], '', $this->normalized($value));
    }

    private function canonicalComponent(string $value): string
    {
        $value = $this->normalized($value);
        return match ($value) {
            'lingua e m ingles', 'lingua e m ingles ingles' => 'lingua inglesa',
            default => $value,
        };
    }

    private function normalized(mixed $value): string
    {
        return Str::of((string) $value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->value();
    }

    /** @param array<string, mixed> $context */
    private function issue(string $file, string $reason, array $context = []): void
    {
        $this->issues[] = ['arquivo' => $file, 'motivo' => $reason] + $context;
    }

    private function writeReport(string $manifest, bool $dryRun): string
    {
        $path = 'reports/importacao-pdfs-diarios-2026-'.now()->format('Ymd-His').'.json';
        Storage::disk('local')->put($path, json_encode([
            'modo' => $dryRun ? 'simulacao' : 'importacao',
            'manifesto' => $manifest,
            'gerado_em' => now()->toIso8601String(),
            'resumo' => $this->stats,
            'pendencias' => $this->issues,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return storage_path('app/private/'.$path);
    }
}
