<div class="sge-action-buttons">
    @if($component->isMatrixDerived())
        <span class="text-muted" title="{{ __('Definido na matriz') }}"><i class="fas fa-lock" aria-hidden="true"></i><span class="sr-only">{{ __('Definido na matriz') }}</span></span>
    @else
    <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('history-curriculum.merge-form', $component) }}" aria-label="{{ __('Mesclar componentes') }}: {{ $component->name }}" title="{{ __('Mesclar componentes') }}"><i class="fas fa-object-group" aria-hidden="true"></i></a>
    <button type="button" class="btn btn-sm btn-outline-primary sge-icon-action" data-toggle="modal" data-target="#catalog-edit-{{ $component->id }}" aria-label="{{ __('Editar componente') }}: {{ $component->name }}" title="{{ __('Editar componente') }}"><i class="fas fa-edit" aria-hidden="true"></i></button>
    @if($component->history_components_count === 0)
    <form method="POST" action="{{ route('history-curriculum.destroy', $component) }}" data-confirm-message="{{ __('Remover este componente do catálogo? Os registros dos históricos serão preservados.') }}">
        @csrf
        @method('DELETE')
        <button class="btn btn-sm btn-outline-danger sge-icon-action" type="submit" aria-label="{{ __('Remover componente') }}: {{ $component->name }}" title="{{ __('Remover componente') }}"><i class="fas fa-trash" aria-hidden="true"></i></button>
    </form>
    @endif
    @endif
</div>
