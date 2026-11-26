@php
    $historyKnowledgeAreas = $historyKnowledgeAreas ?? \App\Models\KnowledgeArea::withSubtitlesByName();
    $elementary = $history->education_stage === 'fundamental';
    $baseFormation = 'Formação Geral Básica';
    $hasGlobalSummary = $history->years->contains('transcript_mode', 'summary');
    // Global assessment changes the results column, not the stage's curriculum rows.
    // These display-only rows must not create records or invent grades/workloads.
    $displayComponents = collect($history->components->all());
    if ($elementary && $hasGlobalSummary) {
        $normalize = fn ($value) => \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii(trim($value ?? '')));
        $formation = config('curriculum.stages.fundamental.formations.formacao_geral_basica.label');
        $existingNames = $displayComponents->filter(fn ($component) => $normalize($component->formation) === $normalize($formation))
            ->map(fn ($component) => $normalize($component->name));
        $missingComponents = collect();
        foreach (config('curriculum.stages.fundamental.formations.formacao_geral_basica.areas', []) as $area) {
            foreach ($area['components'] as $name) {
                if ($normalize($name) !== 'ensino religioso' && ! $existingNames->contains($normalize($name))) {
                    $component = new \App\Models\StudentAcademicHistoryComponent(['formation' => $formation, 'knowledge_area' => $area['name'], 'name' => $name]);
                    $component->setRelation('records', collect());
                    $missingComponents->push($component);
                }
            }
        }
        $displayComponents = $missingComponents->concat($displayComponents);
    }
    $displayComponents = $displayComponents->reject(fn ($component) => $hasGlobalSummary
        && (\Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii(trim($component->knowledge_area ?? ''))) === 'sintese curricular'
            || \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii(trim($component->name ?? ''))) === 'sintese global do documento de origem'));
    $yearSubtotals = [];
    $yearTotals = [];
    if ($elementary) {
        $formations = \App\Support\HistoryFormationWorkloads::formations($history);
        foreach ($formations as $formation) {
            if (! $displayComponents->contains('formation', $formation)) {
                $component = new \App\Models\StudentAcademicHistoryComponent(['formation' => $formation]);
                $component->setRelation('records', collect());
                $displayComponents->push($component);
            }
        }
        $formationPositions = array_flip($formations);
        $displayComponents = $displayComponents->sortBy(fn ($component) => $formationPositions[$component->formation] ?? count($formations));
        foreach ($history->years as $year) {
            $yearSubtotals[$year->id] = $history->formationWorkloadTotals($year);
            $yearTotals[$year->id] = \App\Support\HistoryFormationWorkloads::completedYearTotal($year, $yearSubtotals[$year->id]);
        }
    } else {
        foreach ($history->years as $year) {
            $yearTotals[$year->id] = $year->completedWorkloadHours() ?? ($year->source !== 'system'
                ? $history->components->sum(fn ($component) => (float) ($component->records->firstWhere('student_academic_history_year_id', $year->id)?->completedWorkloadHours($year) ?? 0))
                : null);
        }
    }
    $formatHours = fn ($hours) => $hours === null ? '-' : rtrim(rtrim(number_format($hours, 2, ',', '.'), '0'), ',');
    $allMatrixComponents = $displayComponents
        ->groupBy(fn ($component) => $component->formation ?: '-')
        ->flatMap(fn ($components) => $components->groupBy(fn ($component) => $component->knowledge_area ?: '-')->flatten(1))->values();
    $lastComponentByFormation = $allMatrixComponents->groupBy(fn ($component) => $component->formation ?: '-')->map->last();
    $headerRows = $history->years->contains(fn ($year) => $year->transcript_mode === 'detailed') ? 2 : 1;
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
    // A compact matrix fits on one page: merge global results across formations too.
    if ($hasGlobalSummary && $allMatrixComponents->count() <= 18 && $allMatrixComponents->groupBy('formation')->count() <= ($elementary ? 1 : 3)) {
        $matrixSections = collect([$allMatrixComponents]);
    }
    $matrixComponentIndexes = $allMatrixComponents->mapWithKeys(fn ($component, $index) => [spl_object_id($component) => $index]);
    // A emissão distribui componentes conforme o espaço medido em cada página.
    if (isset($historyMatrixComponentGroups)) {
        $matrixSections = collect($historyMatrixComponentGroups)->map(fn ($indexes) => collect($indexes)
            ->map(fn ($index) => $allMatrixComponents[$index])->values());
    }
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
        <tr><th rowspan="{{ $headerRows }}" style="width:{{ $formationWidth }}%"><div class="vertical-heading"><span>Formação</span></div></th><th rowspan="{{ $headerRows }}" style="width:{{ $areaWidth }}%">Área</th><th rowspan="{{ $headerRows }}" style="width:{{ $componentWidth }}%">Componente curricular</th>
            @foreach($history->years as $year)<th @if($year->transcript_mode !== 'detailed') rowspan="{{ $headerRows }}" @endif colspan="{{ $year->transcript_mode === 'detailed' ? 2 : 1 }}" class="center" style="width:{{ $unitWidth * ($year->transcript_mode === 'detailed' ? 2 : 1) }}%">@if($year->transcript_mode !== 'detailed')@php($yearLabelLines = preg_split('/\s+(?=\d+\s*[º°o]?\s*ciclo\b)/iu', trim($year->label), 2))
                <div class="vertical-heading {{ count($yearLabelLines) > 1 ? 'vertical-heading-multiline' : '' }}"><span>@foreach($yearLabelLines as $line){{ $line }}@unless($loop->last)<br>@endunless@endforeach</span></div>@else{{ $year->label }}@endif</th>@endforeach
        </tr>
        @if($headerRows === 2)
        <tr>@foreach($history->years as $year)
            @if($year->transcript_mode === 'detailed')<th class="center">N</th><th class="center workload-heading">CHC</th>
            @endif
        @endforeach</tr>
        @endif
    </thead>
    <tbody>
        @foreach($matrixSections as $matrixComponents)
        @php($lastMatrixSection = $loop->last)
        <tr><td colspan="{{ 3 + $columnUnits }}" class="matrix-section-cell" style="padding:0;border:0;" data-history-section="{{ $loop->index }}" data-history-formation="{{ $matrixComponents->pluck('formation')->unique()->count() === 1 ? $matrixComponents->first()->formation : '' }}" data-history-components="{{ json_encode($matrixComponents->map(fn ($component) => $matrixComponentIndexes[spl_object_id($component)])->values()->all()) }}">
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
        @unless($elementary && $component->name === null)
        <tr>
            @php($previous = $matrixComponents->get($loop->index - 1))
            @if(! $previous || $previous->formation !== $component->formation)
                <td rowspan="{{ $matrixComponents->where('formation', $component->formation)->count() }}" class="center formation-cell" style="width:{{ $formationWidth }}%"><div class="vertical-formation"><span>{{ $component->formation ?: '-' }}</span></div></td>
            @endif
            @if(! $previous || $previous->formation !== $component->formation || $previous->knowledge_area !== $component->knowledge_area)
                <td rowspan="{{ $matrixComponents->filter(fn ($item) => $item->formation === $component->formation && $item->knowledge_area === $component->knowledge_area)->count() }}" class="area-label" style="width:{{ $areaWidth }}%">@include('partials.knowledge-area-label', ['labelName' => $component->knowledge_area ?: '-', 'labelArea' => $historyKnowledgeAreas->get(mb_strtolower(trim($component->knowledge_area ?? '')))])</td>
            @endif
            <td style="width:{{ $componentWidth }}%">{{ $component->name }}</td>
            @foreach($history->years as $year)
                @php($record = $component->records->firstWhere('student_academic_history_year_id', $year->id))
                @if($elementary && $component->formation !== $baseFormation && $year->transcript_mode !== 'detailed')
                    <td class="center" @if($year->transcript_mode === 'summary') data-summary-component-year="{{ $year->id }}" @endif>{{ $year->transcript_mode === 'summary' && $record ? 'AP' : '-' }}</td>
                @elseif($year->transcript_mode !== 'detailed')
                    @if($firstMatrixRow)<td rowspan="{{ $matrixComponents->count() }}" class="center global-year" style="width:{{ $unitWidth }}%" data-global-year="{{ $year->id }}">
                        @if($year->transcript_mode === 'summary')<div class="vertical-result"><strong>Síntese Global - {{ $year->final_result ?: '-' }}</strong></div>
                        @else<div class="vertical-result"><strong>{{ $year->final_result ?: 'Sem transcrição' }}</strong></div>@endif
                    </td>@endif
                @else
                    <td class="center score-cell" style="width:{{ $unitWidth }}%">{{ filled($record?->score_label) ? $record->score_label : '-' }}@if($history->education_stage === 'medio' && filled($record?->frequency_label))<br><small>{{ $record->frequency_label }}</small>@endif</td>
                    <td class="center score-cell" style="width:{{ $unitWidth }}%">{{ $year->displaysCompletedWorkload() ? $formatHours($record?->completedWorkloadHours($year)) : '-' }}</td>
                @endif
            @endforeach
        </tr>
        @endunless
        @if($elementary && $lastComponentByFormation->get($component->formation ?: '-') === $component)
        <tr class="formation-subtotal" data-formation-subtotal="{{ $component->formation }}">
            <td colspan="3"><strong>{{ __('Total de :formation (h)', ['formation' => __($component->formation ?: 'Formação')]) }}</strong></td>
            @foreach($history->years as $year)
                <td colspan="{{ $year->transcript_mode === 'detailed' ? 2 : 1 }}" class="center">{{ $formatHours($yearSubtotals[$year->id][$component->formation]['completed'] ?? null) }}</td>
            @endforeach
        </tr>
        @endif
        @endforeach
        @if($lastMatrixSection)
        <tr><td colspan="3"><strong>Carga horária cursada total (h)</strong></td>
            @foreach($history->years as $year)
                <td colspan="{{ $year->transcript_mode === 'detailed' ? 2 : 1 }}" class="center"><strong>{{ $year->displaysCompletedWorkload() ? $formatHours($yearTotals[$year->id]) : '-' }}</strong></td>
            @endforeach
        </tr>
        @endif
        </tbody>
        </table>
        </td></tr>
        @endforeach
    </tbody>
</table>
