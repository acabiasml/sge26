@php
    $technicalComponents = $history->components->sortBy(fn ($component) => collect([$component->module_label, $component->position])->filter()->join('|'))->values();
    $modules = $technicalComponents->pluck('module_label')->filter()->unique()->sort()->values();
    $technicalArea = $technicalComponents->pluck('knowledge_area')->filter()->first() ?: __('Curso técnico');
    $historyYear = $history->years->first();
@endphp
<div class="px-3 pt-3 small text-muted">{{ __('Cada período avaliativo concluído gera sua certificação intermediária. A conclusão de todos os períodos e demais requisitos gera o diploma técnico.') }}</div>
<div class="table-responsive">
    <table class="table table-sm table-bordered mb-0 sge-history-read-table">
        <thead><tr><th>{{ __('Área') }}</th><th>{{ __('Componente') }}</th>@foreach($modules as $module)<th class="text-center">{{ \Illuminate\Support\Str::before($module, ' —') }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse($technicalComponents as $component)
                @php($record = $component->records->firstWhere('student_academic_history_year_id', $historyYear?->id))
                <tr>
                    @if($loop->first)<td rowspan="{{ $technicalComponents->count() }}" class="align-middle"><strong>{{ $technicalArea }}</strong></td>@endif
                    <td><strong>{{ $component->name }}</strong></td>
                    @foreach($modules as $module)
                        <td class="text-center">
                            @if($component->module_label === $module)
                                <strong>{{ $record?->score_label ?: '-' }}</strong>
                                <span class="d-block small text-muted">{{ __('CH') }} {{ $record?->workload_hours !== null ? number_format((float) $record->workload_hours, 2, ',', '.') : '-' }}</span>
                                <span class="d-block small text-muted">{{ __('Freq.') }} {{ $record?->frequency_percentage !== null ? number_format((float) $record->frequency_percentage, 1, ',', '.').'%' : '-' }}</span>
                            @else - @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ 2 + $modules->count() }}" class="text-center text-muted">{{ __('Histórico sem componentes técnicos.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if(($technicalPeriodDurations ?? collect())->isNotEmpty())
    <div class="px-3 py-2 small text-muted border-top">
        <strong>{{ __('Duração dos períodos avaliativos:') }}</strong>
        {{ $technicalPeriodDurations->map(fn ($period) => $period->name.': '.$period->starts_at?->format('d/m/Y').__(' a ').$period->ends_at?->format('d/m/Y'))->join(' · ') }}
    </div>
@endif
