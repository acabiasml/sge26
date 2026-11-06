<nav class="sge-section-nav mb-4" aria-label="{{ __('Cadastro curricular') }}">
    <a class="sge-section-nav-item {{ request()->routeIs('knowledge-areas.*') ? 'active' : '' }}" @if(request()->routeIs('knowledge-areas.*')) aria-current="page" @endif href="{{ route('knowledge-areas.index') }}"><i class="fas fa-layer-group" aria-hidden="true"></i><span>{{ __('Áreas e formações') }}</span></a>
    <a class="sge-section-nav-item {{ request()->routeIs('history-curriculum.*') ? 'active' : '' }}" @if(request()->routeIs('history-curriculum.*')) aria-current="page" @endif href="{{ route('history-curriculum.index') }}"><i class="fas fa-book" aria-hidden="true"></i><span>{{ __('Componentes') }}</span></a>
</nav>
