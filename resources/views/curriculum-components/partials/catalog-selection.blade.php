@php($selectedDefinition = old('catalog_component_id', $component?->historyCatalogEntries()->first()?->id))
<select id="{{ $selectId }}" name="catalog_component_id" class="form-control @error('catalog_component_id') is-invalid @enderror" aria-describedby="{{ $selectId }}-help" required>
    <option value="">{{ __('Selecione') }}</option>
    @foreach ($curriculumSuggestions as $suggestion)
        <option value="{{ $suggestion['id'] }}" data-name="{{ $suggestion['component'] }}" @selected((string) $selectedDefinition === (string) $suggestion['id'])>{{ $suggestion['component'] }} · {{ __($suggestion['formation']) }} · {{ $suggestion['area'] }}</option>
    @endforeach
</select>
<small id="{{ $selectId }}-help" class="form-text text-muted">
    @if($curriculumSuggestions === []){{ __('Não há componentes cadastrados no catálogo para esta etapa de ensino.') }}
    @else{{ __('Etapa, formação e área são mantidas conforme o componente escolhido no catálogo.') }}@endif
    @if(auth()->user()->isAdministrator())<a href="{{ route('history-curriculum.index') }}" target="_blank" rel="noopener">{{ __('Gerenciar catálogo') }}</a>@endif
</small>
@error('catalog_component_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
