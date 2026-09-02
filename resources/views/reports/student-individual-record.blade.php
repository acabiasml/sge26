<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 150px 24px 72px; }
        body { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; color: #111; font-size: 11px; line-height: 1.16; }
        @include('reports.partials.letterhead-styles')
        .letterhead { margin-bottom: 6px; padding-bottom: 5px; }
        .letterhead-logo img { max-width: 66px; max-height: 50px; }
        .document-title { font-size: 14px; margin: 5px 0 2px; text-transform: uppercase; }
        .class-line { font-size: 11px; margin: 1px 0 7px; text-align: center; text-transform: uppercase; white-space: nowrap; }
        .section-title { color: #6f3a29; font-size: 11px; font-weight: 600; margin: 6px 0 3px; text-transform: uppercase; }
        .meta-table, .report-table, .summary-table { border-collapse: collapse; width: 100%; }
        .summary-table { page-break-inside: avoid; }
        .meta-table td, .summary-table td { border: .45px solid #d8ccc4; padding: 2.2px 3.5px; vertical-align: top; }
        .meta-table .label, .summary-table .label { background: #f3eee9; font-weight: 600; width: 18%; }
        .student-identification td { line-height: 1.06; padding: 1.5px 3px; }
        .report-table { margin: 6px 0 7px; }
        .report-table th, .report-table td { border: .55px solid #111; padding: 1.7px 2.2px; vertical-align: middle; }
        .report-table th { background: #f1ede9; font-size: 11px; font-weight: 600; text-align: center; }
        .report-table td { font-size: 11px; }
        .technical-regulation { margin: 5px 0; padding: 3px 5px; border: .5px solid #d8c8bf; background: #faf8f6; font-size: 11px; line-height: 1.16; page-break-inside: avoid; }
        .area-cell { font-size: 11px; text-align: center; text-transform: uppercase; width: 22%; word-wrap: break-word; }
        .formation-area-label { display: block; font-size: 11px; font-weight: 600; margin-top: 3px; }
        .component-cell { width: 20%; word-wrap: break-word; }
        .center { text-align: center; }
        .legend-table { border-collapse: collapse; font-size: 11px; line-height: 1.22; margin-top: 5px; width: 100%; }
        .legend-table th, .legend-table td { border: .45px solid #d8ccc4; padding: 3px 5px; text-align: left; vertical-align: top; }
        .legend-table th { background: #f3eee9; font-weight: 600; width: 10%; }
        .legend-table td { background: #faf8f6; }
        .legend-table span { display: inline-block; margin-right: 8px; white-space: nowrap; }
        .document-closing { min-height: 96px; page-break-inside: avoid; }
        .issue-place-date { margin: 9px 0 0; text-align: center; }
        .signatures { border-collapse: collapse; margin-top: 6px; page-break-inside: avoid; width: 100%; }
        .signatures td { border: 0; font-size: 11px; padding-top: 54px; text-align: center; width: 50%; }
        .signature-line { border-top: .6px solid #111; display: inline-block; min-width: 285px; padding-top: 6px; }
        .signature-name { display: block; font-weight: 600; }
        .signature-role { display: block; margin-top: 2px; }
        .document-footer { position: fixed; bottom: -23px; left: 0; right: 0; border-top: .6px solid #bbb; padding-top: 5px; text-align: center; font-size: 11px; color: #333; }
        .document-footer-contact { line-height: 1.08; white-space: normal; overflow-wrap: break-word; }
    </style>
</head>
<body>
@php
    use App\Support\CurriculumCatalog;

    $enrollment = $report['enrollment'];
    $student = $report['student'];
    $academicYear = $report['academicYear'];
    $schoolClass = $report['schoolClass'];
    $periodShortLabel = fn ($period): string => preg_replace(
        ['/\bBimestre\b/iu', '/\bTrimestre\b/iu', '/\bSemestre\b/iu'],
        ['Bim.', 'Trim.', 'Sem.'],
        $period->name,
    );
    $courses = $report['courses'];
    $technicalCourses = $courses->where('stage', \App\Models\AcademicCourse::STAGE_TECHNICAL)->unique('id')->values();
    $school = $academicYear->school;
    $issuePlace = collect([$school?->city, $school?->state ? mb_strtoupper($school->state, 'UTF-8') : null])->filter()->join('-') ?: 'Poxoréu-MT';
    $issueDate = ($issuedDocument->issued_at ?? now('America/Cuiaba'))->timezone('America/Cuiaba')->format('d/m/Y');
    $formatHours = fn (float|int|null $value): string => $value === null ? '-' : rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    $formatHoursWithUnit = fn (float|int|null $value): string => $value === null ? '-' : $formatHours($value).' h';
    $sortText = fn (?string $value): string => mb_strtolower(\Illuminate\Support\Str::ascii((string) $value), 'UTF-8');
    $friendlyConceptRange = function ($concept): string {
        $minimum = $concept->minimum_score !== null ? rtrim(rtrim(number_format((float) $concept->minimum_score, 1, ',', '.'), '0'), ',') : null;
        $maximum = $concept->maximum_score !== null ? rtrim(rtrim(number_format((float) $concept->maximum_score, 1, ',', '.'), '0'), ',') : null;

        if ($minimum === null && $maximum === null) {
            return 'para qualquer nota';
        }

        if ($minimum === null) {
            return ($concept->maximum_inclusive ? 'até ' : 'menor que ').$maximum;
        }

        if ($maximum === null) {
            return ($concept->minimum_inclusive ? 'a partir de ' : 'maior que ').$minimum;
        }

        return ($concept->minimum_inclusive ? 'de ' : 'maior que ')
            .$minimum
            .($concept->maximum_inclusive ? ' até ' : ' até menor que ')
            .$maximum;
    };
    $scoreLabel = function ($score, $date = null) use ($academicYear, $scoreView): string {
        if ($score === null || $score === '') {
            return '-';
        }

        if ($scoreView === 'conceitos') {
            $concept = $academicYear->school?->conceptForScore((float) $score, $date);

            return $concept?->shortLabel() ?? '-';
        }

        return number_format((float) $score, 1, ',', '.');
    };
    $stageLine = $courses
        ->pluck('stage')
        ->map(fn ($stage) => \App\Models\AcademicCourse::STAGE_LABELS[$stage] ?? $stage)
        ->filter()
        ->unique()
        ->join(' / ');
    $classLine = collect([
        $schoolClass->name,
        $stageLine,
        $academicYear->referenceYearsLabel(),
    ])->filter()->unique()->join(' · ');
    $annualComponents = $report['annualComponents']
        ->sort(function (array $first, array $second) use ($courses, $sortText): int {
            $firstComponent = $first['component'];
            $secondComponent = $second['component'];
            $firstFormation = CurriculumCatalog::formationLabelForArea($firstComponent->course ?? $courses->first(), $firstComponent->area);
            $secondFormation = CurriculumCatalog::formationLabelForArea($secondComponent->course ?? $courses->first(), $secondComponent->area);
            $formationComparison = CurriculumCatalog::formationOrder($firstFormation) <=> CurriculumCatalog::formationOrder($secondFormation);

            if ($formationComparison !== 0) {
                return $formationComparison;
            }

            $areaComparison = strnatcasecmp($sortText($firstComponent->area?->name ?? 'Área não definida'), $sortText($secondComponent->area?->name ?? 'Área não definida'));

            return $areaComparison !== 0 ? $areaComparison : strnatcasecmp($sortText($firstComponent->name), $sortText($secondComponent->name));
        })
        ->values();
    $groupedComponents = $annualComponents
        ->groupBy(fn (array $summary): string => CurriculumCatalog::formationLabelForArea($summary['component']->course ?? $courses->first(), $summary['component']->area))
        ->map(fn ($formationItems, string $formation): array => [
            'formation' => $formation,
            'rowspan' => $formationItems->count(),
            'periods' => $formationItems
                ->flatMap(fn (array $summary) => $summary['periods']->pluck('period'))
                ->unique('id')
                ->sortBy(fn ($period): string => sprintf('%s-%010d', $period->starts_at?->format('Y-m-d') ?? '9999-12-31', $period->position))
                ->values(),
            'areas' => $formationItems
                ->groupBy(fn (array $summary): string => $summary['component']->area?->name ?? 'Área não definida')
                ->map(fn ($areaItems, string $area): array => [
                    'area' => $area,
                    'rowspan' => $areaItems->count(),
                    'items' => $areaItems->values(),
                ])
                ->values(),
        ])
        ->values();
    $plannedHoursTotal = $annualComponents->sum(fn (array $summary): float => $summary['component']->calculatedWorkloadHours($summary['component']->course ?? $courses->first()));
    $completedHoursTotal = $annualComponents->sum(function (array $summary) use ($courses): float {
        $course = $summary['component']->course ?? $courses->first();

        return round(((int) ($summary['attendance']['lessons'] ?? 0) * (int) ($course?->class_hour_minutes ?? 0)) / 60, 2);
    });
    $conceptLegend = $school?->conceptsForDate($academicYear->ends_at ?? now()) ?? collect();
    $issuedAt = ($issuedDocument->issued_at ?? now('America/Cuiaba'))->timezone('America/Cuiaba');
    $issuedDate = $issuedAt->toDateString();
    $currentAcademicPeriod = $report['periods']->first(fn ($period): bool =>
        $period->academic_year_id === $academicYear->id
        && $period->starts_at
        && $period->ends_at
        && $issuedDate >= $period->starts_at->toDateString()
        && $issuedDate <= $period->ends_at->toDateString()
    );
    $finalResult = $report['finalResult'] ?? [];
    $finalResultDetails = $finalResult['details'] ?? [];
@endphp

@include('reports.partials.letterhead', [
    'title' => 'Ficha individual',
    'letterhead' => $letterhead,
    'issuedDocument' => $issuedDocument,
    'verificationUrl' => $verificationUrl,
    'showTechnicalRegulation' => false,
])

<div class="class-line">{{ $classLine }}</div>

@include('reports.partials.student-identification', ['student' => $student])

<div class="section-title">Dados da matrícula</div>
<table class="meta-table">
    <tr>
        <td class="label">Escola</td>
        <td>{{ $school->name }}</td>
        <td class="label">Turma e etapa</td>
        <td>{{ \App\Support\AcademicContextLabel::classWithStages($schoolClass->name, $courses) }}</td>
    </tr>
    <tr>
        <td class="label">Ano letivo</td>
        <td>{{ $academicYear->referenceYearsLabel() }}</td>
        <td class="label">Matrizes</td>
        <td>{{ $courses->pluck('name')->join(' + ') ?: '-' }}</td>
    </tr>
    <tr>
        <td class="label">Data da matrícula</td>
        <td>{{ $enrollment->enrolled_at?->format('d/m/Y') ?? '-' }}</td>
        <td class="label">Situação</td>
        <td>{{ $enrollment->statusLabel() }} · {{ $enrollment->typeLabel() }}</td>
    </tr>
    <tr>
        <td class="label">Período do ano letivo</td>
        <td>{{ $academicYear->starts_at?->format('d/m/Y') ?? '-' }} a {{ $academicYear->ends_at?->format('d/m/Y') ?? '-' }}</td>
        <td class="label">Dias letivos</td>
        <td>{{ $academicYear->schoolDayCount() }}</td>
    </tr>
</table>

<div class="section-title">Rendimento, frequência e carga horária</div>
@forelse($groupedComponents as $formationGroup)
    @php
        $formationPeriods = $formationGroup['periods'];
        $itineraryNames = $formationGroup['areas']
            ->flatMap(fn (array $areaGroup) => $areaGroup['items'])
            ->map(fn (array $summary): string => CurriculumCatalog::areaLabelForComponent(
                $summary['component']->course ?? $courses->first(),
                $summary['component']->area,
            ))
            ->filter()
            ->unique()
            ->join(' / ');
    @endphp
    @if($formationGroup['formation'] === CurriculumCatalog::FORMATION_ITINERARY && $technicalCourses->isNotEmpty())
        @include('reports.partials.technical-course-regulations', ['technicalCourses' => $technicalCourses])
    @endif
    <table class="report-table">
        <thead>
            <tr>
                <th colspan="2" rowspan="2">
                    {{ mb_strtoupper($formationGroup['formation'], 'UTF-8') }}
                    @if($formationGroup['formation'] === CurriculumCatalog::FORMATION_ITINERARY && $itineraryNames)
                        <span class="formation-area-label">{{ mb_strtoupper($itineraryNames, 'UTF-8') }}</span>
                    @endif
                </th>
                @foreach($formationPeriods as $period)
                    <th colspan="2">{{ $periodShortLabel($period) }}</th>
                @endforeach
                <th rowspan="2">PT</th>
                <th rowspan="2">TF</th>
                <th rowspan="2">Freq.</th>
                <th rowspan="2">CHP (h)</th>
                <th rowspan="2">CHC (h)</th>
            </tr>
            <tr>
                @foreach($formationPeriods as $period)
                    <th>N</th>
                    <th>F</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($formationGroup['areas'] as $areaGroup)
                @foreach($areaGroup['items'] as $summary)
                    @php
                        $component = $summary['component'];
                        $course = $component->course ?? $courses->first();
                        $plannedHours = $component->calculatedWorkloadHours($course);
                        $completedHours = round(((int) ($summary['attendance']['lessons'] ?? 0) * (int) ($course?->class_hour_minutes ?? 0)) / 60, 2);
                    @endphp
                    <tr>
                        @if($formationGroup['formation'] !== CurriculumCatalog::FORMATION_ITINERARY && $loop->first)
                            <td class="area-cell" rowspan="{{ $areaGroup['rowspan'] }}">{{ $areaGroup['area'] }}</td>
                        @endif
                        <td class="component-cell" @if($formationGroup['formation'] === CurriculumCatalog::FORMATION_ITINERARY) colspan="2" @endif>{{ $component->name }}</td>
                        @foreach($formationPeriods as $period)
                            @php
                                $periodComponent = $summary['periods']->first(fn (array $item): bool => $item['component']->id === $component->id && $period->is($item['period'] ?? $period));
                                $periodDate = $period->ends_at ?? $period->starts_at;
                            @endphp
                            <td class="center">{{ $periodComponent ? $scoreLabel($periodComponent['average']['value'], $periodDate) : '-' }}</td>
                            <td class="center">{{ $periodComponent ? (int) ($periodComponent['attendance']['absent'] ?? 0) : '-' }}</td>
                        @endforeach
                        <td class="center">{{ $formatHours($summary['points'] ?? 0) }}</td>
                        <td class="center">{{ (int) ($summary['attendance']['absent'] ?? 0) }}</td>
                        <td class="center">{{ $summary['attendance']['percentage'] !== null ? number_format((float) $summary['attendance']['percentage'], 1, ',', '.').'%' : '-' }}</td>
                        <td class="center">{{ $formatHoursWithUnit($plannedHours) }}</td>
                        <td class="center">{{ $formatHoursWithUnit($completedHours) }}</td>
                    </tr>
                @endforeach
            @endforeach
            @if($formationGroup['formation'] === CurriculumCatalog::FORMATION_FGB)
                <tr>
                    <td colspan="2"><strong>Comportamento</strong></td>
                    @foreach($formationPeriods as $period)
                        @php
                            $periodReport = $report['periodReports']->first(fn (array $item): bool => $period->is($item['period']));
                            $periodDate = $period->ends_at ?? $period->starts_at;
                        @endphp
                        <td class="center">{{ $scoreLabel($periodReport['behavior']?->score ?? null, $periodDate) }}</td>
                        <td class="center">-</td>
                    @endforeach
                    <td colspan="5" class="center">Registro por período avaliativo</td>
                </tr>
            @endif
        </tbody>
    </table>
@empty
    <table class="report-table">
        <tr><td class="center">Nenhum componente cadastrado.</td></tr>
    </table>
@endforelse

<table class="summary-table">
    <tr>
        <td colspan="2"><strong>Total de faltas justificadas (Atestados Médicos):</strong> {{ (int) ($report['annualAttendance']['justified'] ?? 0) }}</td>
        <td colspan="2"><strong>Período letivo:</strong> {{ $currentAcademicPeriod ? 'Em andamento: '.$currentAcademicPeriod->name : 'Nenhum período em andamento' }}</td>
    </tr>
    <tr>
        <td class="label">Frequência anual efetiva</td>
        <td>{{ $report['annualAttendance']['percentage'] !== null ? number_format((float) $report['annualAttendance']['percentage'], 1, ',', '.').'%' : '-' }}</td>
        <td class="label">Carga horária</td>
        <td>Prevista: {{ $formatHoursWithUnit($plannedHoursTotal) }} · Cursada: {{ $formatHoursWithUnit($completedHoursTotal) }}</td>
    </tr>
    <tr>
        <td class="label">Resultado final</td>
        <td>{{ $finalResult['label'] ?? 'Não calculado' }}</td>
        <td class="label">Registro do resultado</td>
        <td>
            @if($finalResult['calculated_at'] ?? null)
                Calculado em {{ $finalResult['calculated_at']->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}
                @if($finalResult['calculated_by'] ?? null)
                    por {{ $finalResult['calculated_by']->full_name }}
                @endif
            @else
                Ainda não calculado.
            @endif
        </td>
    </tr>
    @if($finalResultDetails['reason'] ?? null)
        <tr>
            <td class="label">Observação do resultado</td>
            <td colspan="3">{{ $finalResultDetails['reason'] }}</td>
        </tr>
    @endif
</table>

<table class="legend-table">
    <tr>
        <th>Legenda</th>
        <td>N (nota) · F (falta) · PT (pontos totais) · TF (total de faltas) · Freq. (frequência efetiva) · CHP (carga horária prevista) · CHC (carga horária cursada).</td>
    </tr>
    @if($conceptLegend->isNotEmpty())
        <tr>
            <th>Conceitos</th>
            <td>
            @foreach($conceptLegend as $concept)
                <span>{{ $concept->shortLabel() }} = {{ $concept->name }} ({{ $friendlyConceptRange($concept) }})</span>
            @endforeach
            </td>
        </tr>
    @endif
</table>

<section class="document-closing">
    <p class="issue-place-date">{{ $issuePlace }}, {{ $issueDate }}.</p>
    <table class="signatures">
        <tr>
            @include('reports.partials.signature-staff', ['signatureType' => 'pedagogical', 'signatureDate' => $issuedDocument->issued_at ?? now()])
        </tr>
    </table>
</section>

@include('reports.partials.document-footer', ['issuedDocument' => $issuedDocument])
</body>
</html>
