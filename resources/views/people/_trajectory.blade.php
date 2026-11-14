<section class="card sge-panel-card sge-person-trajectory mb-4" aria-labelledby="person-trajectory-title">
    <div class="sge-panel-header">
        <div>
            <h2 id="person-trajectory-title">{{ __('Trajetória e movimentações') }}</h2>
            <p class="text-muted small mb-0">{{ __('Estudos, vínculos profissionais e turmas de docência cadastrados, dos mais recentes aos mais antigos.') }}</p>
        </div>
        <i class="fas fa-route" aria-hidden="true"></i>
    </div>
    <div class="card-body">
        <nav class="sge-section-nav sge-academic-tabs mb-3" aria-label="{{ __('Áreas da trajetória da pessoa') }}" role="tablist" data-section-tabs data-default-tab="trajetoria-{{ $trajectory['default_tab'] }}">
            @foreach($trajectory['groups'] as $key => $group)
                <a href="#section-trajetoria-{{ $key }}" class="sge-section-nav-item" data-academic-tab="trajetoria-{{ $key }}" role="tab">
                    <i class="{{ $group['icon'] }}" aria-hidden="true"></i><span>{{ $group['label'] }}</span>
                    <small>{{ trans_choice(':count registro|:count registros', $group['entries']->count(), ['count' => $group['entries']->count()]) }}</small>
                </a>
            @endforeach
        </nav>
        @foreach($trajectory['groups'] as $key => $group)
            <div id="section-trajetoria-{{ $key }}" data-academic-panel="trajetoria-{{ $key }}" role="tabpanel">
                @if($key === 'docencia' && $group['entries']->isNotEmpty())
                    <p class="text-muted small">{{ __('Os intervalos das turmas seguem o calendário letivo. As substituições usam as datas cadastradas; o tempo do vínculo profissional aparece na guia Vínculos profissionais.') }}</p>
                @endif
                <div class="table-responsive">
                    <table class="table mb-0 sge-person-trajectory-table" data-sge-client-pagination="6" aria-label="{{ $group['label'] }}">
                        <thead class="sr-only"><tr><th scope="col">{{ __('Vínculo e movimentação') }}</th><th scope="col">{{ __('Período e duração') }}</th></tr></thead>
                        <tbody>
                            @forelse($group['entries'] as $entry)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-start justify-content-between flex-wrap">
                                            <strong class="sge-person-trajectory-title">{{ $entry['title'] }}</strong>
                                            <span class="badge {{ $entry['ongoing'] ? 'badge-success' : 'badge-light' }}">{{ $entry['status'] }}</span>
                                        </div>
                                        @if($entry['subtitle'] && $entry['subtitle'] !== $entry['title'])<div class="small mt-1">{{ $entry['subtitle'] }}</div>@endif
                                        <div class="text-muted small mt-1">{{ $entry['school'] }}@if($entry['year']) · {{ $entry['year'] }}@endif</div>
                                        @if($entry['details'])
                                            <ul class="sge-person-trajectory-details small mb-0 mt-2">
                                                @foreach($entry['details'] as $detail)<li>{{ $detail }}</li>@endforeach
                                            </ul>
                                        @endif
                                        @if($entry['url'])<a class="small d-inline-block mt-2" href="{{ $entry['url'] }}" aria-label="{{ $entry['link_label'] }}: {{ $entry['title'] }}@if($entry['year']) · {{ $entry['year'] }}@endif">{{ $entry['link_label'] }} <i class="fas fa-arrow-right ml-1" aria-hidden="true"></i></a>@endif
                                    </td>
                                    <td class="sge-person-trajectory-period">
                                        <span class="d-block">{{ $entry['period'] }}</span>
                                        <span class="d-block small text-muted mt-1">{{ $entry['duration'] }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-muted text-center py-4">{{ __('Nenhum registro cadastrado nesta área.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>
</section>
