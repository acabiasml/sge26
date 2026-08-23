<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 landscape; margin: 13px 16px 72px; }
        body { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; color: #111; font-size: 11px; line-height: 1.1; }
        @include('reports.partials.letterhead-styles')
        .letterhead { margin-bottom: 6px; padding-bottom: 5px; }
        .letterhead-logo img { max-width: 62px; max-height: 44px; }
        .letterhead-line { font-size: 11px; line-height: 1.12; }
        .letterhead-line-main { font-size: 11px; }
        .document-title { font-size: 12.5px; margin: 4px 0 1px; text-transform: uppercase; }
        .mirror-context { margin: 0 0 6px; text-align: center; }
        .mirror-context strong { font-size: 11px; text-transform: uppercase; }
        .mirror-context span { color: #555; display: block; margin-top: 2px; }
        .mirror-table { border-collapse: collapse; table-layout: fixed; width: 100%; }
        .mirror-table th, .mirror-table td { border: .5px solid #111; padding: 1.8px 2px; vertical-align: middle; }
        .mirror-table th { background: #eee9e5; font-size: 11px; font-weight: 600; text-align: center; }
        .mirror-table td { font-size: 11px; }
        .mirror-table .number-column { text-align: center; width: 3%; }
        .mirror-table .student-column { width: 22%; }
        .mirror-table .student-name { font-weight: 600; }
        .mirror-table .score-cell { text-align: center; }
        .formation-heading { background: #ded7d2 !important; text-transform: uppercase; }
        .area-heading { background: #e9e4df !important; }
        .component-heading { height: 76px; padding: 1px; vertical-align: bottom; width: 3.2%; }
        .component-heading span { display: inline-block; line-height: 1; transform: rotate(-90deg); transform-origin: center; white-space: nowrap; width: 74px; }
        .behavior-heading { background: #f5e7ce !important; }
        .mirror-notes { color: #4f4945; font-size: 11px; margin-top: 5px; }
        .mirror-notes strong { color: #222; }
        .concept-legend span { display: inline-block; margin-right: 8px; white-space: nowrap; }
        .page-number { float: right; }
        .page-break { page-break-after: always; }
        .document-footer { position: fixed; bottom: -19px; left: 0; right: 0; border-top: .6px solid #bbb; padding-top: 4px; text-align: center; font-size: 11px; color: #333; }
    </style>
</head>
<body>
@php
    $componentChunks = $components->chunk(24)->values();
    $totalPages = max(1, $periods->count() * $componentChunks->count());
    $pageNumber = 0;
    $scoreLabel = function ($score, $date = null) use ($academicYear, $scoreView): string {
        if ($score === null || $score === '') {
            return '-';
        }

        if ($scoreView === 'conceitos') {
            return $academicYear->school?->conceptForScore((float) $score, $date)?->shortLabel() ?? '-';
        }

        return number_format((float) $score, 1, ',', '.');
    };
@endphp

@foreach($periods as $period)
    @php
        $conceptLegend = $academicYear->school?->conceptsForDate($period->ends_at ?? $period->starts_at) ?? collect();
    @endphp
    @foreach($componentChunks as $componentChunk)
        @php
            $pageNumber++;
            $formationGroups = $componentChunk
                ->groupBy('formation')
                ->map(fn ($formationItems, string $formation): array => [
                    'formation' => $formation,
                    'colspan' => $formationItems->count(),
                    'areas' => $formationItems
                        ->groupBy('area')
                        ->map(fn ($areaItems, string $area): array => [
                            'area' => $area,
                            'colspan' => $areaItems->count(),
                            'items' => $areaItems->values(),
                        ])
                        ->values(),
                ])
                ->values();
            $showBehavior = $loop->last;
        @endphp

        @include('reports.partials.letterhead', [
            'title' => 'Espelho de notas',
            'letterhead' => $letterhead,
            'issuedDocument' => $issuedDocument,
            'verificationUrl' => $verificationUrl,
        ])

        <div class="mirror-context">
            <strong>{{ $period->name }} · {{ \App\Support\AcademicContextLabel::classWithStages($schoolClass->name, $schoolClass->courses) }}</strong>
            <span>
                Ano letivo {{ $academicYear->referenceYearsLabel() }}
                · apresentação em {{ $scoreView === 'conceitos' ? 'conceitos' : 'notas numéricas' }}
                @if($componentChunks->count() > 1)
                    · componentes {{ (($loop->iteration - 1) * 24) + 1 }} a {{ (($loop->iteration - 1) * 24) + $componentChunk->count() }}
                @endif
            </span>
        </div>

        <table class="mirror-table">
            <thead>
                <tr>
                    <th class="number-column" rowspan="3">Nº</th>
                    <th class="student-column" rowspan="3">Estudante</th>
                    @foreach($formationGroups as $formationGroup)
                        <th class="formation-heading" colspan="{{ $formationGroup['colspan'] }}">{{ $formationGroup['formation'] }}</th>
                    @endforeach
                    @if($showBehavior)
                        <th class="behavior-heading" rowspan="3">Comportamento</th>
                    @endif
                </tr>
                <tr>
                    @foreach($formationGroups as $formationGroup)
                        @foreach($formationGroup['areas'] as $areaGroup)
                            <th class="area-heading" colspan="{{ $areaGroup['colspan'] }}">{{ $areaGroup['area'] }}</th>
                        @endforeach
                    @endforeach
                </tr>
                <tr>
                    @foreach($formationGroups as $formationGroup)
                        @foreach($formationGroup['areas'] as $areaGroup)
                            @foreach($areaGroup['items'] as $componentItem)
                                <th class="component-heading"><span>{{ $componentItem['name'] }}</span></th>
                            @endforeach
                        @endforeach
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($reports as $report)
                    @php
                        $periodReport = $report['periodReports']->first(
                            fn (array $item): bool => (int) $item['period']->id === (int) $period->id
                        );
                    @endphp
                    <tr>
                        <td class="number-column">{{ $loop->iteration }}</td>
                        <td class="student-name">{{ $report['student']->full_name }}</td>
                        @foreach($componentChunk as $componentItem)
                            @php
                                $componentReport = $periodReport
                                    ? $periodReport['components']->first(
                                        fn (array $item): bool => (int) $item['component']->id === (int) $componentItem['id']
                                    )
                                    : null;
                            @endphp
                            <td class="score-cell">
                                {{ $scoreLabel($componentReport['average']['value'] ?? null, $period->ends_at ?? $period->starts_at) }}
                            </td>
                        @endforeach
                        @if($showBehavior)
                            <td class="score-cell">
                                {{ $scoreLabel(($periodReport['behavior'] ?? null)?->score, $period->ends_at ?? $period->starts_at) }}
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="mirror-notes">
            <strong>Período:</strong> {{ $period->starts_at?->format('d/m/Y') ?? '-' }} a {{ $period->ends_at?->format('d/m/Y') ?? '-' }}.
            <strong>Matrículas:</strong> {{ $reports->count() }}.
            @if($scoreView === 'conceitos' && $conceptLegend->isNotEmpty())
                <span class="concept-legend">
                    <strong>Legenda:</strong>
                    @foreach($conceptLegend as $concept)
                        <span>{{ $concept->shortLabel() }} = {{ $concept->name }}</span>
                    @endforeach
                </span>
            @endif
        </div>

        @if($pageNumber < $totalPages)
            <div class="page-break"></div>
        @endif
    @endforeach
@endforeach

@include('reports.partials.document-footer', ['issuedDocument' => $issuedDocument])
</body>
</html>
