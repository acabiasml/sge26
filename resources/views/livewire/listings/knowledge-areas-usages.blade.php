<div>
    @include('livewire.listings.search')

    <a data-navigation-return class="btn btn-outline-secondary mb-3" href="{{ route('knowledge-areas.index') }}"><i class="fas fa-arrow-left mr-1" aria-hidden="true"></i>{{ __('Áreas e formações') }}</a>
    <section class="card shadow-sm mb-4">
        <div class="card-body d-flex align-items-center justify-content-between flex-wrap">
            <div><span class="text-muted">{{ __('Formação') }}</span><h2 class="h5">{{ __($area->formation ?: 'Formação não definida') }}</h2><span>{{ $area->matrix_uses_count }} {{ __('componentes em matrizes') }} · {{ $area->history_uses_count }} {{ __('componentes em históricos') }}</span></div>
            @if($area->usageCount() > 0)
                <a class="btn btn-outline-primary" href="{{ route('history-curriculum.index', ['table-filters' => ['area_id' => $area->id]]) }}">{{ __('Ver componentes no catálogo') }} ({{ $area->history_catalog_components_count }})</a>
            @else
                @include('knowledge-areas._delete-form')
            @endif
        </div>
    </section>
    <p class="text-muted">{{ __('Para alterar a classificação, escolha outro componente do catálogo na matriz.') }}</p>
    <section class="card shadow-sm">
        <div class="card-header font-weight-bold">{{ __('Onde esta área é usada') }}</div>
        <div class="table-responsive"><table class="table table-hover mb-0">
            <thead><tr><th>{{ __('Componente') }}</th><th>{{ __('Matriz') }}</th><th>{{ __('Escola') }}</th><th>{{ __('Ano letivo') }}</th><th>{{ __('Ações') }}</th></tr></thead>
            <tbody>@forelse($components as $component)
                @php($course = $component->course)
                @php($year = $course->academicYear)
                <tr>
                    <td><a href="{{ route('academic-years.courses.components.show', [$year, $course, $component]) }}">{{ $component->name }}</a></td>
                    <td><a href="{{ route('academic-years.courses.show', [$year, $course]) }}">{{ $course->name }}</a></td>
                    <td>{{ $year->school?->name }}</td><td><a href="{{ route('academic-years.show', $year) }}">{{ $year->reference_year }} · {{ $year->name }}</a></td>
                    <td><a class="btn btn-sm btn-outline-primary" href="{{ route('academic-years.courses.components.show', [$year, $course, $component]) }}">{{ __('Gerenciar componente') }}</a></td>
                </tr>
            @empty<tr><td colspan="5" class="text-muted p-4">{{ __('Nenhum componente de matriz vinculado a esta área.') }}</td></tr>@endforelse</tbody>
        </table></div>
        @if($components->hasPages())<div class="card-footer">{{ $components->links('livewire::bootstrap') }}</div>@endif
    </section>

</div>
