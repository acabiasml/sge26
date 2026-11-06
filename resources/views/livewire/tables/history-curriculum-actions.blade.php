<div class="sge-action-buttons">
    <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('history-curriculum.merge-form', $component) }}" aria-label="{{ __('Mesclar componentes') }}: {{ $component->name }}" title="{{ __('Mesclar componentes') }}"><i class="fas fa-object-group" aria-hidden="true"></i></a>
    @if($component->isMatrixDerived())
        <button type="button" class="btn btn-sm btn-outline-primary sge-icon-action" data-toggle="modal" data-target="#catalog-links-{{ $component->id }}" aria-label="{{ __('Ver vínculos antes de excluir') }}: {{ $component->name }}" title="{{ __('Ver vínculos antes de excluir') }}"><i class="fas fa-lock" aria-hidden="true"></i></button>
    @else
    <button type="button" class="btn btn-sm btn-outline-primary sge-icon-action" data-toggle="modal" data-target="#catalog-edit-{{ $component->id }}" aria-label="{{ __('Editar componente') }}: {{ $component->name }}" title="{{ __('Editar componente') }}"><i class="fas fa-edit" aria-hidden="true"></i></button>
    @endif
    @if($component->matrixComponents->isEmpty() && $component->history_components_count === 0)
    <form method="POST" action="{{ route('history-curriculum.destroy', $component) }}" data-confirm-message="{{ __('Excluir este componente do catálogo? Ele não está vinculado a matrizes nem a históricos.') }}">
        @csrf
        @method('DELETE')
        <button class="btn btn-sm btn-outline-danger sge-icon-action" type="submit" aria-label="{{ __('Remover componente') }}: {{ $component->name }}" title="{{ __('Remover componente') }}"><i class="fas fa-trash" aria-hidden="true"></i></button>
    </form>
    @endif
</div>
