@php
    ['stages' => $stages, 'config' => $config] = \App\Support\HistoryCurriculumCatalog::formOptions();
    $modalComponents = collect($currentRows->items())->keyBy('id');
    // Reabre também erros de um formulário cujo registro tenha saído da página filtrada.
    if (preg_match('/^catalog-([0-9]+)$/', (string) old('catalog_form'), $matches) && ! $modalComponents->has((int) $matches[1])) {
        $submittedComponent = \App\Models\HistoryCurriculumComponent::with('matrixComponents.course.academicYear.school')->withCount('historyComponents')->find((int) $matches[1]);
        if ($submittedComponent) $modalComponents->put($submittedComponent->id, $submittedComponent);
    }
@endphp
@foreach($modalComponents->reject(fn ($component) => $component->isMatrixDerived()) as $component)
<div class="modal fade" id="catalog-edit-{{ $component->id }}" wire:key="catalog-modal-{{ $component->id }}" wire:ignore.self tabindex="-1" role="dialog" aria-labelledby="catalog-title-{{ $component->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title h5" id="catalog-title-{{ $component->id }}">{{ __('Editar componente') }}: {{ $component->name }}</h2>
            <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('Fechar') }}"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body"><form action="{{ route('history-curriculum.update', $component) }}" method="POST" data-catalog-form>
            @csrf
            @method('PUT')
            @include('knowledge-areas._catalog-form', ['catalogComponent' => $component])
        </form></div>
    </div></div>
</div>
@endforeach

@foreach($modalComponents->filter(fn ($component) => $component->isMatrixDerived()) as $component)
<div class="modal fade" id="catalog-links-{{ $component->id }}" wire:key="catalog-links-modal-{{ $component->id }}" wire:ignore.self tabindex="-1" role="dialog" aria-labelledby="catalog-links-title-{{ $component->id }}" aria-describedby="catalog-links-description-{{ $component->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document"><div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title h5" id="catalog-links-title-{{ $component->id }}">{{ __('Excluir componente') }}: {{ $component->name }}</h2>
            <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('Fechar') }}"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body">
            @if($component->matrixComponents->isNotEmpty())
                <p id="catalog-links-description-{{ $component->id }}">{{ __('Este componente está vinculado a matrizes. Revise os vínculos nas matrizes antes de excluí-lo do catálogo.') }}</p>
                <p class="small text-muted">{{ __('A contagem de históricos não inclui os vínculos com matrizes.') }}</p>
                <ul class="list-unstyled mb-3">
                    @foreach($component->matrixComponents->groupBy('academic_course_id') as $matrixComponents)
                        @php
                            $course = $matrixComponents->first()->course;
                            $academicYear = $course->academicYear;
                        @endphp
                        <li class="border rounded p-3 mb-2">
                            <a href="{{ route('academic-years.courses.show', [$academicYear, $course]) }}" class="d-block"><i class="fas fa-table mr-1" aria-hidden="true"></i>{{ __('Abrir matriz') }}: {{ $course->name }}</a>
                            <span class="small text-muted">{{ $academicYear->school?->name }} · {{ $academicYear->name }} · {{ $academicYear->referenceYearsLabel() }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p id="catalog-links-description-{{ $component->id }}">{{ __('A matriz de origem não está mais vinculada a este componente.') }}</p>
                @if($component->history_components_count > 0)
                    <p>{{ __('Este componente é utilizado em históricos. Os vínculos precisam ser preservados.') }}</p>
                @else
                    <p>{{ __('Este componente não possui vínculos. Use o botão de lixeira na listagem para excluí-lo.') }}</p>
                @endif
            @endif
            @if($component->history_components_count > 0)
                <a class="btn btn-outline-primary" href="{{ route('history-curriculum.usages', $component) }}"><i class="fas fa-list mr-1" aria-hidden="true"></i>{{ __('Ver históricos vinculados') }} ({{ $component->history_components_count }})</a>
            @endif
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">{{ __('Fechar') }}</button>
        </div>
    </div></div>
</div>
@endforeach
