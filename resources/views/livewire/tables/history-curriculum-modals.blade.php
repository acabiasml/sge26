@php
    ['stages' => $stages, 'config' => $config] = \App\Support\HistoryCurriculumCatalog::formOptions();
    $modalComponents = collect($currentRows->items())->keyBy('id');
    // Reabre também erros de um formulário cujo registro tenha saído da página filtrada.
    if (preg_match('/^catalog-([0-9]+)$/', (string) old('catalog_form'), $matches) && ! $modalComponents->has((int) $matches[1])) {
        $submittedComponent = \App\Models\HistoryCurriculumComponent::find((int) $matches[1]);
        if ($submittedComponent) $modalComponents->put($submittedComponent->id, $submittedComponent);
    }
@endphp
@foreach($modalComponents as $component)
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
