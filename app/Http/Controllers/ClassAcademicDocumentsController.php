<?php

namespace App\Http\Controllers;

use App\Models\AcademicCourse;
use App\Models\AcademicYear;
use App\Models\CurriculumComponent;
use App\Models\IssuedDocument;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Support\AcademicContextLabel;
use App\Support\CurriculumCatalog;
use App\Support\OfficialDocumentCompliance;
use App\Support\PdfLetterhead;
use App\Support\StudentReportCardBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ClassAcademicDocumentsController extends Controller
{
    public function reportCards(
        Request $request,
        SchoolClass $class,
        StudentReportCardBuilder $builder,
    ): Response|RedirectResponse {
        [$academicYear, $enrollments] = $this->classContext($request, $class);

        if ($enrollments->isEmpty()) {
            return $this->blocked('Não foi possível emitir os boletins: a turma não possui matrículas ativas.');
        }

        if ($message = $this->complianceMessage($academicYear->school, $enrollments)) {
            return $this->blocked($message);
        }

        $scoreView = $this->scoreView($request);

        if ($message = $this->conceptsMessage($academicYear->school, $academicYear->ends_at, $scoreView)) {
            return $this->blocked($message);
        }

        $reports = $enrollments
            ->map(fn (StudentEnrollment $enrollment): array => $builder->build($enrollment))
            ->values();

        if ($reports->every(fn (array $report): bool => $report['annualComponents']->isEmpty())) {
            return $this->blocked('Não foi possível emitir os boletins: as matrículas ativas da turma não possuem componentes curriculares vinculados.');
        }

        $issuedDocument = $this->issuedDocument(
            $request,
            $class,
            'class-report-cards',
            'Boletins da turma - '.AcademicContextLabel::classWithStages($class->name, $class->courses),
            $enrollments->count(),
            $scoreView,
        );

        $pdf = Pdf::loadView('reports.class-report-cards', [
            'reports' => $reports,
            'scoreView' => $scoreView,
            'issuedDocument' => $issuedDocument,
            'verificationUrl' => route('documents.verify', $issuedDocument->verification_code),
            'letterhead' => PdfLetterhead::make($academicYear->school),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('beaba-boletins-turma-'.$class->id.'-'.now()->format('Ymd-His').'.pdf');
    }

    public function gradeMirror(
        Request $request,
        SchoolClass $class,
        StudentReportCardBuilder $builder,
    ): Response|RedirectResponse {
        [$academicYear, $enrollments] = $this->classContext($request, $class);

        if ($enrollments->isEmpty()) {
            return $this->blocked('Não foi possível emitir o espelho: a turma não possui matrículas ativas.');
        }

        if ($message = OfficialDocumentCompliance::schoolMessage($academicYear->school)) {
            return $this->blocked($message);
        }

        $scoreView = $this->scoreView($request);

        if ($message = $this->conceptsMessage($academicYear->school, $academicYear->ends_at, $scoreView)) {
            return $this->blocked($message);
        }

        $reports = $enrollments
            ->map(fn (StudentEnrollment $enrollment): array => $builder->build($enrollment))
            ->values();
        $components = $this->mirrorComponents($reports);
        $periods = $academicYear->periods()->orderBy('position')->get();

        if ($components->isEmpty()) {
            return $this->blocked('Não foi possível emitir o espelho: a turma não possui componentes curriculares vinculados às matrículas ativas.');
        }

        if ($periods->isEmpty()) {
            return $this->blocked('Não foi possível emitir o espelho: o ano letivo não possui períodos avaliativos cadastrados.');
        }

        $issuedDocument = $this->issuedDocument(
            $request,
            $class,
            'class-grade-mirror',
            'Espelho de notas - '.AcademicContextLabel::classWithStages($class->name, $class->courses),
            $enrollments->count(),
            $scoreView,
        );

        $pdf = Pdf::loadView('reports.class-grade-mirror', [
            'schoolClass' => $class,
            'academicYear' => $academicYear,
            'reports' => $reports,
            'components' => $components,
            'periods' => $periods,
            'scoreView' => $scoreView,
            'issuedDocument' => $issuedDocument,
            'verificationUrl' => route('documents.verify', $issuedDocument->verification_code),
            'letterhead' => PdfLetterhead::make($academicYear->school),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('beaba-espelho-notas-turma-'.$class->id.'-'.now()->format('Ymd-His').'.pdf');
    }

    /**
     * @return array{0: AcademicYear, 1: Collection<int, StudentEnrollment>}
     */
    private function classContext(Request $request, SchoolClass $class): array
    {
        $class->load(['academicYear.school', 'courses']);
        $academicYear = $class->academicYear;

        abort_unless($academicYear, 404);
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        $enrollments = StudentEnrollment::query()
            ->with('student')
            ->where('school_class_id', $class->id)
            ->where('status', StudentEnrollment::STATUS_ENROLLED)
            ->get()
            ->sortBy(fn (StudentEnrollment $enrollment): string => Str::ascii($enrollment->student?->full_name ?? ''))
            ->values();

        return [$academicYear, $enrollments];
    }

    private function scoreView(Request $request): string
    {
        return $request->query('notas') === 'numeros' ? 'numeros' : 'conceitos';
    }

    private function complianceMessage(School $school, Collection $enrollments): ?string
    {
        if ($message = OfficialDocumentCompliance::schoolMessage($school)) {
            return $message;
        }

        $incomplete = $enrollments
            ->filter(fn (StudentEnrollment $enrollment): bool => $enrollment->student?->missingSchoolDocumentFields() !== [])
            ->values();

        if ($incomplete->isEmpty()) {
            return null;
        }

        $names = $incomplete
            ->take(4)
            ->map(fn (StudentEnrollment $enrollment): string => $enrollment->student?->full_name ?? 'Estudante sem nome')
            ->join(', ');
        $remaining = $incomplete->count() - 4;

        return 'Emissão bloqueada: '
            .$incomplete->count()
            .' matrícula(s) ativa(s) possuem cadastro pessoal incompleto. Revise: '
            .$names
            .($remaining > 0 ? ' e mais '.$remaining.'.' : '.');
    }

    private function conceptsMessage(School $school, mixed $date, string $scoreView): ?string
    {
        if ($scoreView !== 'conceitos' || $school->conceptsForDate($date)->isNotEmpty()) {
            return null;
        }

        return 'Emissão bloqueada: cadastre uma tabela de conceitos vigente para a escola ou escolha notas numéricas.';
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $reports
     * @return Collection<int, array{id: int, component: CurriculumComponent, course: AcademicCourse, formation: string, area: string, name: string}>
     */
    private function mirrorComponents(Collection $reports): Collection
    {
        $components = $reports
            ->flatMap(function (array $report): Collection {
                return $report['annualComponents']
                    ->map(function (array $summary) use ($report): ?array {
                        $component = $summary['component'];
                        $course = $component->course ?? $report['courses']->first();

                        if (! $course) {
                            return null;
                        }

                        return [
                            'id' => $component->id,
                            'component' => $component,
                            'course' => $course,
                            'formation' => CurriculumCatalog::formationLabelForArea($course, $component->area),
                            'area' => $component->area?->name ?? 'Área não definida',
                            'name' => $component->name,
                        ];
                    })
                    ->filter();
            })
            ->unique('id')
            ->values();

        $nameCounts = $components
            ->countBy(fn (array $item): string => mb_strtolower(Str::ascii($item['name']), 'UTF-8'));

        return $components
            ->map(function (array $item) use ($nameCounts): array {
                $key = mb_strtolower(Str::ascii($item['name']), 'UTF-8');

                if (($nameCounts[$key] ?? 0) > 1) {
                    $item['name'] .= ' ('.$item['course']->name.')';
                }

                return $item;
            })
            ->sort(function (array $first, array $second): int {
                $formation = CurriculumCatalog::formationOrder($first['formation'])
                    <=> CurriculumCatalog::formationOrder($second['formation']);

                if ($formation !== 0) {
                    return $formation;
                }

                $area = strnatcasecmp(Str::ascii($first['area']), Str::ascii($second['area']));

                return $area !== 0
                    ? $area
                    : strnatcasecmp(Str::ascii($first['name']), Str::ascii($second['name']));
            })
            ->values();
    }

    private function blocked(string $message): RedirectResponse
    {
        return redirect()->route('document-issuance.index')->with('status', $message);
    }

    private function issuedDocument(
        Request $request,
        SchoolClass $class,
        string $type,
        string $title,
        int $rowsCount,
        string $scoreView,
    ): IssuedDocument {
        return IssuedDocument::query()->create([
            'uuid' => (string) Str::uuid(),
            'verification_code' => $this->verificationCode(),
            'type' => $type,
            'person_id' => $request->user()->person_id,
            'school_id' => $class->academicYear->school_id,
            'issued_by_user_id' => $request->user()->id,
            'payload' => [
                'title' => $title,
                'scope_label' => AcademicContextLabel::classWithStages($class->name, $class->courses),
                'school_class_id' => $class->id,
                'academic_year_id' => $class->academic_year_id,
                'rows_count' => $rowsCount,
                'score_view' => $scoreView,
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
