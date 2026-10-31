<div class="sge-listing-search mb-3" role="search" aria-label="{{ __('Pesquisar nesta listagem') }}">
    <div class="sge-listing-search-field form-group mb-0">
        <label for="listing-search-{{ $this->getId() }}">{{ __('Pesquisar') }}</label>
        <input type="search" id="listing-search-{{ $this->getId() }}" class="form-control" wire:model.live.debounce.350ms="filters.q" placeholder="{{ __('Digite para pesquisar') }}" autocomplete="off">
    </div>
    <button type="button" class="btn btn-outline-secondary" wire:click="clearFilters">{{ __('Limpar') }}</button>
</div>
@include('livewire.listings.status')
