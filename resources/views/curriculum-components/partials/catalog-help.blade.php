<small id="{{ $helpId }}" class="form-text text-muted">
    @if ($curriculumSuggestions === [])
        {{ __('Não há componentes ativos no catálogo para esta etapa de ensino.') }}
    @else
        {{ __('Escolha um componente do catálogo desta etapa de ensino.') }}
    @endif
    @if (auth()->user()->isAdministrator())
        <a href="{{ route('history-curriculum.index') }}" target="_blank" rel="noopener">{{ __('Gerenciar catálogo') }}</a>
    @endif
</small>
@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
