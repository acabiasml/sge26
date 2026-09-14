@php
    $technicalComponents = $history->components->sortBy(fn ($component) => collect([$component->module_label, $component->position])->filter()->join('|'))->values();
    $modules = $technicalComponents->pluck('module_label')->filter()->unique()->sort()->values();
    $technicalArea = $technicalComponents->pluck('knowledge_area')->filter()->first() ?: 'Curso técnico';
    $historyYear = $history->years->first();
@endphp

<div class="formation-title">Formação Técnica Profissional</div>
<div class="muted" style="margin:2px 0 3px;">A conclusão de cada período avaliativo assegura sua certificação intermediária; a conclusão de todos os períodos e demais requisitos assegura o diploma técnico.</div>
@foreach($technicalComponents->isEmpty() ? collect([collect()]) : $technicalComponents->chunk(14) as $sectionComponents)
@php($lastTechnicalSection = $loop->last)
<table class="history-table technical-history-matrix">
    <thead>
        <tr>
            <th rowspan="2" style="width:5%;"><div class="vertical-heading"><span>Formação</span></div></th><th rowspan="2" style="width:15%;">Área</th>
            <th rowspan="2" style="width:28%;">Componente curricular</th>
            @foreach($modules as $module)
                <th colspan="2" class="center">{{ \Illuminate\Support\Str::before($module, ' —') }}</th>
            @endforeach
        </tr>
        <tr>
            @foreach($modules as $module)<th class="center">N</th><th class="center">CHC</th>@endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($sectionComponents as $component)
            @php($record = $component->records->firstWhere('student_academic_history_year_id', $historyYear?->id))
            <tr>
                @if($loop->first)<td rowspan="{{ $sectionComponents->count() }}" class="center"><div class="vertical-formation"><span>Formação Técnica Profissional</span></div></td><td rowspan="{{ $sectionComponents->count() }}">{{ $technicalArea }}</td>@endif
                <td>{{ $component->name }}</td>
                @foreach($modules as $module)
                    @if($component->module_label === $module)
                        <td class="center score-cell">{{ $record?->score_label ?: '-' }}</td>
                        <td class="center score-cell">{{ $record?->workload_hours !== null ? number_format((float) $record->workload_hours, 0, ',', '.').'h' : '-' }}</td>
                    @else
                        <td class="center">-</td><td class="center">-</td>
                    @endif
                @endforeach
            </tr>
        @endforeach
    </tbody>
    @if($lastTechnicalSection)
    <tfoot>
        <tr>
            <td colspan="3"><strong>Carga horária cursada por período avaliativo</strong></td>
            @foreach($modules as $module)
                @php($moduleHours = $technicalComponents->where('module_label', $module)->sum(fn ($component) => (float) ($component->records->firstWhere('student_academic_history_year_id', $historyYear?->id)?->workload_hours ?? 0)))
                <td class="center">-</td><td class="center"><strong>{{ $moduleHours > 0 ? number_format($moduleHours, 0, ',', '.').'h' : '-' }}</strong></td>
            @endforeach
        </tr>
        <tr>
            @php($completedTechnicalHours = $technicalComponents->sum(fn ($component) => (float) ($component->records->firstWhere('student_academic_history_year_id', $historyYear?->id)?->workload_hours ?? 0)))
            <td colspan="3"><strong>Carga horária total cursada</strong></td>
            <td colspan="{{ max(1, 2 * $modules->count()) }}" class="center"><strong>{{ number_format($completedTechnicalHours, 0, ',', '.').'h' }}</strong></td>
        </tr>
    </tfoot>
    @endif
</table>
@endforeach
@if(($technicalPeriodDurations ?? collect())->isNotEmpty())
    <div class="muted" style="margin:3px 2px 5px;line-height:1.2;">
        <strong>Duração dos períodos avaliativos:</strong>
        {{ $technicalPeriodDurations->map(fn ($period) => $period->name.': '.$period->starts_at?->format('d/m/Y').' a '.$period->ends_at?->format('d/m/Y'))->join(' · ') }}
    </div>
@endif
