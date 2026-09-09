<div class="d-flex flex-wrap justify-content-center mt-4" style="gap: .75rem">
    @if ($showNewLookup)
        <a class="btn btn-outline-primary" href="{{ route('documents.verify.form') }}">{{ __('Verificar outro documento') }}</a>
    @endif
    @auth
        <a class="btn btn-primary" href="{{ route('dashboard') }}">{{ __('Voltar ao início') }}</a>
    @else
        <a class="btn btn-primary" href="https://ctjj.org/">{{ __('Voltar ao site do CTJJ') }}</a>
    @endauth
</div>
