<div class="form-row align-items-end mb-3" role="search" aria-label="{{ __('Pesquisar nesta listagem') }}">
    <div class="col-md-8 form-group mb-md-0">
        <label for="listing-search-{{ $this->getId() }}">{{ __('Pesquisar') }}</label>
        <input type="search" id="listing-search-{{ $this->getId() }}" class="form-control" wire:model.live.debounce.350ms="filters.q" placeholder="{{ __('Digite para pesquisar') }}" autocomplete="off">
    </div>
    <div class="col-md-4"><button type="button" class="btn btn-outline-secondary" wire:click="clearFilters">{{ __('Limpar') }}</button></div>
</div>
<div wire:loading.delay role="status" class="small text-muted mb-2">{{ __('Pesquisando…') }}</div>
