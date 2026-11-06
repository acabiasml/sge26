<form class="d-inline" method="POST" action="{{ route('knowledge-areas.destroy', $area) }}" data-confirm-message="{{ $area->history_catalog_components_count > 0 ? __('Excluir a área :name e seus :count componentes do catálogo sem uso?', ['name' => $area->name, 'count' => $area->history_catalog_components_count]) : __('Excluir a área :name, que não possui usos?', ['name' => $area->name]) }}">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-sm btn-outline-danger sge-icon-action" aria-label="{{ __('Excluir área') }}: {{ $area->name }}" title="{{ __('Excluir área') }}"><i class="fas fa-trash" aria-hidden="true"></i></button>
</form>
