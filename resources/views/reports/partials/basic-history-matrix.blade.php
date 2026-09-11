@php
    $matrixComponents = $history->components->groupBy(fn ($component) => $component->formation ?: '-')
        ->flatMap(fn ($components) => $components->groupBy(fn ($component) => $component->knowledge_area ?: '-')->flatten(1))->values();
    $columnUnits = $history->years->sum(fn ($year) => $year->transcript_mode === 'detailed' ? 3 : 1);
    $unitWidth = 75 / max(1, $columnUnits);
@endphp
<table class="history-table basic-history-matrix">
    <colgroup>
        <col style="width:9%"><col style="width:16%">
        @foreach($history->years as $year)
            @for($i = 0; $i < ($year->transcript_mode === 'detailed' ? 3 : 1); $i++)<col style="width:{{ $unitWidth }}%">@endfor
        @endforeach
    </colgroup>
    <thead>
        <tr><th rowspan="2" style="width:9%">Área / formação</th><th rowspan="2" style="width:16%">Componente curricular</th>
            @foreach($history->years as $year)<th colspan="{{ $year->transcript_mode === 'detailed' ? 3 : 1 }}" class="center" style="width:{{ $unitWidth * ($year->transcript_mode === 'detailed' ? 3 : 1) }}%">@if($year->transcript_mode !== 'detailed')<div class="vertical-heading"><span>{{ $year->label }}</span></div>@else{{ $year->label }}@endif</th>@endforeach
        </tr>
        <tr>@foreach($history->years as $year)
            @if($year->transcript_mode === 'detailed')<th class="center">N</th><th class="center">CH</th><th class="center">F%</th>
            @else<th class="center"><div class="vertical-heading"><span>{{ $year->transcript_mode === 'summary' ? 'Global' : 'Sem transcrição' }}</span></div></th>@endif
        @endforeach</tr>
    </thead>
    <tbody>
        @foreach($matrixComponents as $component)
        @php($firstMatrixRow = $loop->first)
        <tr>
            @php($previous = $matrixComponents->get($loop->index - 1))
            @if(! $previous || $previous->formation !== $component->formation || $previous->knowledge_area !== $component->knowledge_area)
                <td rowspan="{{ $matrixComponents->filter(fn ($item) => $item->formation === $component->formation && $item->knowledge_area === $component->knowledge_area)->count() }}" class="area-label">{{ $component->knowledge_area ?: '-' }}@if(! $previous || $previous->formation !== $component->formation)<br><span class="muted">{{ $component->formation }}</span>@endif</td>
            @endif
            <td>{{ $component->name }}</td>
            @foreach($history->years as $year)
                @if($year->transcript_mode !== 'detailed')
                    @if($firstMatrixRow)<td rowspan="{{ $matrixComponents->count() }}" class="center global-year" data-global-year="{{ $year->id }}">
                        @if($year->transcript_mode === 'summary')<div class="vertical-result"><strong>{{ $year->final_result ?: 'Global' }}</strong></div>@if($year->attendance_label)<br><br>{{ $year->attendance_label }}@endif
                        @else{{ $year->final_result ?: 'Sem transcrição' }}@endif
                    </td>@endif
                @else
                    @php($record = $component->records->firstWhere('student_academic_history_year_id', $year->id))
                    <td class="center score-cell">{{ $record?->score_label ?: '-' }}</td>
                    <td class="center score-cell">{{ $record?->workload_hours !== null ? number_format((float) $record->workload_hours, 0, ',', '') : '-' }}</td>
                    <td class="center score-cell">{{ $record?->frequency_percentage !== null ? str_replace('.', ',', (string) round((float) $record->frequency_percentage, 1)) : ($record?->frequency_label ?: '-') }}</td>
                @endif
            @endforeach
        </tr>
        @endforeach
        <tr><td colspan="2"><strong>Carga horária total geral (h)</strong></td>
            @foreach($history->years as $year)
                @php($total = $year->workload_hours ?? $history->components->sum(fn ($component) => (float) ($component->records->firstWhere('student_academic_history_year_id', $year->id)?->workload_hours ?? 0)))
                <td colspan="{{ $year->transcript_mode === 'detailed' ? 3 : 1 }}" class="center"><strong>{{ $year->transcript_mode === 'no_transcription' ? '-' : number_format((float) $total, 0, ',', '.') }}</strong></td>
            @endforeach
        </tr>
    </tbody>
</table>
