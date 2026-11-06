@php
    $manualYear = $component->records->filter(fn ($record) => $record->year && $record->year->source !== 'system')->sortBy(fn ($record) => $record->year->position)->first()?->year;
@endphp
<div class="sge-action-buttons">
    <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('people.histories.show', [$component->history->person_id, $component->history]) }}#history-component-{{ $component->id }}" aria-label="{{ __('Consultar histórico') }}: {{ $component->history->student?->full_name }}" title="{{ __('Consultar histórico') }}"><i class="fas fa-eye" aria-hidden="true"></i></a>
    @if($manualYear)
        <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('people.histories.edit', [$component->history->person_id, $component->history]) }}#history-year-{{ $manualYear->id }}" aria-label="{{ __('Editar anos externos') }}: {{ $component->history->student?->full_name }}" title="{{ __('Editar anos externos') }}"><i class="fas fa-edit" aria-hidden="true"></i></a>
    @endif
</div>
