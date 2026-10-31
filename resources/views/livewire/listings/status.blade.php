@if (isset($listingResultCount))
    <div class="sr-only" role="status" aria-live="polite" aria-atomic="true" data-listing-status>
        <span wire:loading.delay>{{ __('Pesquisando…') }}</span>
        <span wire:loading.remove data-listing-result>
            @if (trim((string) ($filters['q'] ?? '')) !== '')
                {{ __('Pesquisa por “:query”: :count resultado(s).', ['query' => trim((string) $filters['q']), 'count' => $listingResultCount]) }}
            @else
                {{ __('Pesquisa concluída: :count resultado(s).', ['count' => $listingResultCount]) }}
            @endif
        </span>
        <span data-listing-error hidden>{{ __('Não foi possível atualizar a listagem. Tente novamente.') }}</span>
    </div>
@endif
