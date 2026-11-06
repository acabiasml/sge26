<div class="sge-action-buttons">
    <button type="button" class="btn btn-sm btn-outline-primary sge-icon-action" data-toggle="modal" data-target="#catalog-edit-{{ $component->id }}" aria-label="{{ __('Editar componente') }}: {{ $component->name }}" title="{{ __('Editar componente') }}"><i class="fas fa-edit" aria-hidden="true"></i></button>
    <form method="POST" action="{{ route('history-curriculum.destroy', $component) }}" data-confirm-message="{{ __('Remover este componente do catálogo? Os registros dos históricos serão preservados.') }}">
        @csrf
        @method('DELETE')
        <button class="btn btn-sm btn-outline-danger sge-icon-action" type="submit" aria-label="{{ __('Remover componente') }}: {{ $component->name }}" title="{{ __('Remover componente') }}"><i class="fas fa-trash" aria-hidden="true"></i></button>
    </form>
</div>
