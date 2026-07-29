<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\AcademicCourse;
use App\Models\IssuedDocument;
use App\Models\PersonSchoolRole;
use App\Support\CurriculumCatalog;
use App\Support\PdfLetterhead;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AcademicMatricesPdfController extends Controller
{
    public function __invoke(Request $request, AcademicYear $academicYear, ?AcademicCourse $course = null): Response
    {
        abort_unless($request->user()->canManageSchool($academicYear->school_id), 403);

        if ($course) {
            abort_unless($course->academic_year_id === $academicYear->id, 404);
        }

        $academicYear->load([
            'school',
            'courses.components.area',
        ]);

        $courses = $course
            ? collect([$course->load(['components.area'])])
            : $academicYear->courses->sortBy('name')->values();

        $issuedDocument = $this->issuedDocument($request, $academicYear, $courses);
        $signatureDate = $academicYear->approved_at ?? now();

        $pdf = Pdf::loadView('reports.academic-matrices', [
            'academicYear' => $academicYear,
            'matrixGroups' => $this->matrixGroups($courses),
            'issuedDocument' => $issuedDocument,
            'verificationUrl' => route('documents.verify', $issuedDocument->verification_code),
            'letterhead' => PdfLetterhead::make($academicYear->school),
            'directorName' => $this->directorName($academicYear, $signatureDate),
            'signatureDate' => $signatureDate,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('beaba-matrizes-'.$academicYear->id.'-'.now()->format('Ymd-His').'.pdf');
    }

    private function matrixGroups(Collection $courses): Collection
    {
        return $courses
            ->sortBy([
                ['stage', 'asc'],
                ['name', 'asc'],
            ])
            ->groupBy('stage')
            ->map(function (Collection $stageCourses): array {
                $stageCourses = $stageCourses->sortBy('name')->values();
                $firstCourse = $stageCourses->first();
                $rows = collect();

                foreach ($stageCourses as $course) {
                    foreach ($course->componentsGroupedByFormationAndArea() as $formationGroup) {
                        foreach ($formationGroup['areas'] as $areaGroup) {
                            foreach ($areaGroup['components'] as $component) {
                                $key = implode('|', [
                                    $formationGroup['formation'],
                                    $areaGroup['area'],
                                    $component->name,
                                ]);

                                $row = $rows->get($key, [
                                    'formation' => $formationGroup['formation'],
                                    'area' => $areaGroup['area'],
                                    'component' => $component->name,
                                    'weekly_lessons' => [],
                                ]);

                                $row['weekly_lessons'][$course->id] = $component->weekly_lessons;
                                $rows->put($key, $row);
                            }
                        }
                    }
                }

                $rows = $rows
                    ->sort(function (array $first, array $second): int {
                        $formationComparison = CurriculumCatalog::formationOrder($first['formation'])
                            <=> CurriculumCatalog::formationOrder($second['formation']);

                        if ($formationComparison !== 0) {
                            return $formationComparison;
                        }

                        $areaComparison = strnatcasecmp($first['area'], $second['area']);

                        return $areaComparison !== 0
                            ? $areaComparison
                            : strnatcasecmp($first['component'], $second['component']);
                    })
                    ->values();

                return [
                    'stage' => $firstCourse?->stage,
                    'title' => $this->matrixTitle($stageCourses),
                    'courses' => $stageCourses,
                    'rows' => $this->groupRowsForPrinting($rows),
                    'total_weekly_lessons' => $stageCourses->mapWithKeys(
                        fn (AcademicCourse $course): array => [$course->id => (int) $course->components->sum('weekly_lessons')]
                    ),
                    'total_hours' => $stageCourses->mapWithKeys(
                        fn (AcademicCourse $course): array => [$course->id => $course->formattedCalculatedWorkloadHours()]
                    ),
                ];
            })
            ->values();
    }

    private function groupRowsForPrinting(Collection $rows): Collection
    {
        return $rows
            ->groupBy('formation')
            ->map(fn (Collection $formationRows, string $formation): array => [
                'formation' => $formation,
                'rowspan' => $formationRows->count(),
                'areas' => $formationRows
                    ->groupBy('area')
                    ->map(fn (Collection $areaRows, string $area): array => [
                        'area' => $area,
                        'rowspan' => $areaRows->count(),
                        'components' => $areaRows->values(),
                    ])
                    ->values(),
            ])
            ->values();
    }

    private function matrixTitle(Collection $courses): string
    {
        $firstCourse = $courses->first();
        $stageLabel = $firstCourse?->stageLabel() ?? 'Curso';
        $modalities = $courses
            ->map(fn (AcademicCourse $course): string => $course->modalityLabel())
            ->map(fn (string $modality): string => trim($modality))
            ->filter()
            ->unique()
            ->values();

        $title = 'Matriz do '.$stageLabel;

        if ($modalities->count() === 1) {
            $modality = $this->modalityWithoutRepeatedStageWords($modalities->first(), $stageLabel);

            if ($modality !== '') {
                $title .= ' '.$modality;
            }
        }

        return $title;
    }

    private function modalityWithoutRepeatedStageWords(string $modality, string $stageLabel): string
    {
        $stageWords = explode(' ', $this->comparableLabel($stageLabel));
        $modalityWords = preg_split('/\s+/u', trim($modality)) ?: [];

        $filteredWords = array_filter($modalityWords, function (string $word) use ($stageWords): bool {
            $comparableWord = $this->comparableLabel($word);

            return $comparableWord === '' || ! in_array($comparableWord, $stageWords, true);
        });

        return trim(implode(' ', $filteredWords));
    }

    private function comparableLabel(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }

    private function issuedDocument(Request $request, AcademicYear $academicYear, Collection $courses): IssuedDocument
    {
        return IssuedDocument::query()->create([
            'uuid' => (string) Str::uuid(),
            'verification_code' => $this->verificationCode(),
            'type' => 'academic-matrices',
            'person_id' => $request->user()->person_id,
            'school_id' => $academicYear->school_id,
            'issued_by_user_id' => $request->user()->id,
            'payload' => [
                'title' => 'Matrizes curriculares - '.$academicYear->name,
                'academic_year_id' => $academicYear->id,
                'school_id' => $academicYear->school_id,
                'rows_count' => $courses->count(),
            ],
            'issued_at' => now(),
        ]);
    }

    private function directorName(AcademicYear $academicYear, Carbon $date): ?string
    {
        $role = PersonSchoolRole::query()
            ->with('person')
            ->where('school_id', $academicYear->school_id)
            ->where('role', PersonSchoolRole::ROLE_MANAGER)
            ->where('position', PersonSchoolRole::POSITION_DIRECTOR)
            ->where('active', true)
            ->where(function ($query) use ($date): void {
                $query->whereNull('started_at')
                    ->orWhereDate('started_at', '<=', $date->toDateString());
            })
            ->where(function ($query) use ($date): void {
                $query->whereNull('ended_at')
                    ->orWhereDate('ended_at', '>=', $date->toDateString());
            })
            ->orderByDesc('started_at')
            ->first();

        return $role?->person?->social_name ?: $role?->person?->full_name;
    }

    private function verificationCode(): string
    {
        do {
            $code = 'BEABA-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
        } while (IssuedDocument::query()->where('verification_code', $code)->exists());

        return $code;
    }
}
