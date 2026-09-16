@php
    $allMatrixComponents = $history->components->groupBy(fn ($component) => $component->formation ?: '-')
        ->flatMap(fn ($components) => $components->groupBy(fn ($component) => $component->knowledge_area ?: '-')->flatten(1))->values();
    $columnUnits = $history->years->sum(fn ($year) => $year->transcript_mode === 'detailed' ? 2 : 1);
    $wideComponentColumn = $history->education_stage === 'medio' && $history->years->count() <= 4;
    // Keep merged cells within indivisible rows of the outer table. Its single
    // thead repeats only at actual page boundaries, never between sections.
    $sectionSize = $wideComponentColumn ? 12 : 10;
    $matrixSections = $allMatrixComponents->isEmpty() ? collect([collect()]) : $allMatrixComponents->groupBy(fn ($component) => $component->formation ?: '-')
        ->flatMap(function ($components) use ($sectionSize) {
            $balancedSize = (int) ceil($components->count() / ceil($components->count() / $sectionSize));

            return $components->chunk($balancedSize)->map(fn ($section) => $section->values());
        })->values();
    [$formationWidth, $areaWidth, $componentWidth] = $wideComponentColumn ? [4, 18, 36] : [5, 12, 23];
    $unitWidth = (100 - $formationWidth - $areaWidth - $componentWidth) / max(1, $columnUnits);
@endphp
<table class="history-table basic-history-matrix">
    <colgroup>
        <col style="width:{{ $formationWidth }}%"><col style="width:{{ $areaWidth }}%"><col style="width:{{ $componentWidth }}%">
        @foreach($history->years as $year)
            @for($i = 0; $i < ($year->transcript_mode === 'detailed' ? 2 : 1); $i++)<col style="width:{{ $unitWidth }}%">@endfor
        @endforeach
    </colgroup>
    <thead>
        <tr><th rowspan="2" style="width:{{ $formationWidth }}%"><div class="vertical-heading"><span>Formação</span></div></th><th rowspan="2" style="width:{{ $areaWidth }}%">Área</th><th rowspan="2" style="width:{{ $componentWidth }}%">Componente curricular</th>
            @foreach($history->years as $year)<th colspan="{{ $year->transcript_mode === 'detailed' ? 2 : 1 }}" class="center" style="width:{{ $unitWidth * ($year->transcript_mode === 'detailed' ? 2 : 1) }}%">@if($year->transcript_mode !== 'detailed')<div class="vertical-heading"><span>{{ $year->label }}</span></div>@else{{ $year->label }}@endif</th>@endforeach
        </tr>
        <tr>@foreach($history->years as $year)
            @if($year->transcript_mode === 'detailed')<th class="center">N</th><th class="center">CHC</th>
            @else<th class="center"><div class="vertical-heading"><span>{{ $year->transcript_mode === 'summary' ? 'Global' : 'Sem transcrição' }}</span></div></th>@endif
        @endforeach</tr>
    </thead>
    <tbody>
        @foreach($matrixSections as $matrixComponents)
        @php($lastMatrixSection = $loop->last)
        <tr><td colspan="{{ 3 + $columnUnits }}" class="matrix-section-cell" style="padding:0;border:0;">
        <table class="history-table matrix-section" style="margin:0;">
    <colgroup>
        <col style="width:{{ $formationWidth }}%"><col style="width:{{ $areaWidth }}%"><col style="width:{{ $componentWidth }}%">
        @foreach($history->years as $year)
            @for($i = 0; $i < ($year->transcript_mode === 'detailed' ? 2 : 1); $i++)<col style="width:{{ $unitWidth }}%">@endfor
        @endforeach
    </colgroup>
        <tbody>
        @foreach($matrixComponents as $component)
        @php($firstMatrixRow = $loop->first)
        <tr>
            @php($previous = $matrixComponents->get($loop->index - 1))
            @if(! $previous || $previous->formation !== $component->formation)
                <td rowspan="{{ $matrixComponents->where('formation', $component->formation)->count() }}" class="center formation-cell" style="width:{{ $formationWidth }}%"><div class="vertical-formation"><span>{{ $component->formation ?: '-' }}</span></div></td>
            @endif
            @if(! $previous || $previous->formation !== $component->formation || $previous->knowledge_area !== $component->knowledge_area)
                <td rowspan="{{ $matrixComponents->filter(fn ($item) => $item->formation === $component->formation && $item->knowledge_area === $component->knowledge_area)->count() }}" class="area-label" style="width:{{ $areaWidth }}%">{{ $component->knowledge_area ?: '-' }}</td>
            @endif
            <td style="width:{{ $componentWidth }}%">{{ $component->name }}</td>
            @foreach($history->years as $year)
                @if($year->transcript_mode !== 'detailed')
                    @if($firstMatrixRow)<td rowspan="{{ $matrixComponents->count() }}" class="center global-year" style="width:{{ $unitWidth }}%" data-global-year="{{ $year->id }}">
                        @if($year->transcript_mode === 'summary')<div class="vertical-result"><strong>{{ $year->final_result ?: 'Global' }}</strong></div>
                        @else{{ $year->final_result ?: 'Sem transcrição' }}@endif
                    </td>@endif
                @else
                    @php($record = $component->records->firstWhere('student_academic_history_year_id', $year->id))
                    <td class="center score-cell" style="width:{{ $unitWidth }}%">{{ $record?->score_label ?: '-' }}</td>
                    <td class="center score-cell" style="width:{{ $unitWidth }}%">{{ $year->displaysCompletedWorkload() && $record?->workload_hours !== null ? number_format((float) $record->workload_hours, 0, ',', '') : '-' }}</td>
                @endif
            @endforeach
        </tr>
        @endforeach
        @if($lastMatrixSection)
        <tr><td colspan="3"><strong>Carga horária cursada total (h)</strong></td>
            @foreach($history->years as $year)
                @php($total = $year->workload_hours ?? $history->components->sum(fn ($component) => (float) ($component->records->firstWhere('student_academic_history_year_id', $year->id)?->workload_hours ?? 0)))
                <td colspan="{{ $year->transcript_mode === 'detailed' ? 2 : 1 }}" class="center"><strong>{{ $year->displaysCompletedWorkload() ? number_format((float) $total, 0, ',', '.') : '-' }}</strong></td>
            @endforeach
        </tr>
        @endif
        </tbody>
        </table>
        </td></tr>
        @endforeach
    </tbody>
</table>
