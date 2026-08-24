@php
    $technicalComponents = $history->components->sortBy(fn ($component) => collect([$component->module_label, $component->position])->filter()->join('|'))->values();
    $modules = $technicalComponents->pluck('module_label')->filter()->unique()->sort()->values();
    $technicalArea = $technicalComponents->pluck('knowledge_area')->filter()->first() ?: 'Curso técnico';
    $historyYear = $history->years->first();
@endphp
<div class="px-3 pt-3 small text-muted">Cada módulo concluído gera sua certificação intermediária. A conclusão de todos os módulos e demais requisitos gera o diploma técnico.</div>
<div class="table-responsive">
    <table class="table table-sm table-bordered mb-0 sge-history-read-table">
        <thead><tr><th>Área</th><th>Componente</th>@foreach($modules as $module)<th class="text-center">{{ \Illuminate\Support\Str::before($module, ' —') }}</th>@endforeach</tr></thead>
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
                                <span class="d-block small text-muted">CH {{ $record?->workload_hours !== null ? number_format((float) $record->workload_hours, 2, ',', '.') : '-' }}</span>
                            @else - @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ 2 + $modules->count() }}" class="text-center text-muted">Histórico sem componentes técnicos.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
