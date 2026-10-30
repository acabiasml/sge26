<div>
<div class="d-flex justify-content-end mb-3 sge-action-buttons">
    @if($academicYear)
        <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('academic-years.periods.index', $academicYear) }}" aria-label="{{ __('Gerenciar períodos avaliativos') }}" title="{{ __('Gerenciar períodos avaliativos') }}">
            <i class="fas fa-sliders-h" aria-hidden="true"></i>
        </a>
        <a target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('academic-years.schedules-pdf', $academicYear) }}" aria-label="{{ __('Imprimir horários das turmas') }}" title="{{ __('Imprimir horários das turmas') }}">
            <i class="fas fa-calendar-week" aria-hidden="true"></i>
        </a>
    @endif

</div>
    @include('livewire.listings.search')

    @php
        $baseDiaryQuery = $listingRequest->except('status', 'page');
        $quickStatusFilters = [
            ['key' => null, 'label' => __('Todos'), 'count' => $stats['total'], 'icon' => 'fa-layer-group'],
            ['key' => 'pending', 'label' => __('Com pendência'), 'count' => $stats['pending'], 'icon' => 'fa-exclamation-circle'],
            ['key' => 'waiting', 'label' => __('Aguardando'), 'count' => $stats['waiting'], 'icon' => 'fa-hourglass-half'],
            ['key' => 'confirmed', 'label' => __('Confirmados'), 'count' => $stats['confirmed'], 'icon' => 'fa-check-circle'],
            ['key' => 'reopened', 'label' => __('Reabertos'), 'count' => $stats['reopened'], 'icon' => 'fa-unlock'],
        ];
    @endphp

    <section class="sge-diary-management-hero" aria-labelledby="diary-management-context">
        <div>
            <span class="sge-page-kicker">{{ __('Gestão pedagógica') }}</span>
            <h2 id="diary-management-context">{{ $academicYear?->school?->name ?? __('Nenhuma escola selecionada') }}</h2>
            <p>
                @if($academicYear && $period)
                    {{ $academicYear->name }} · {{ $period->name }} · {{ $period->starts_at->format('d/m/Y') }} {{ __('a') }} {{ $period->ends_at->format('d/m/Y') }}
                @else
                    {{ __('Selecione um ano letivo e um período para acompanhar os diários.') }}
                @endif
            </p>
        </div>
        <div role="group" class="sge-diary-management-metrics" aria-label="{{ __('Resumo dos diários filtrados') }}">
            <div><strong>{{ $stats['total'] }}</strong><span>{{ __('diários') }}</span></div>
            <div><strong>{{ $stats['pending'] }}</strong><span>{{ __('com pendência') }}</span></div>
            <div><strong>{{ $stats['waiting'] }}</strong><span>{{ __('aguardando') }}</span></div>
            <div><strong>{{ $stats['confirmed'] }}</strong><span>{{ __('confirmados') }}</span></div>
            <div><strong>{{ $stats['reopened'] }}</strong><span>{{ __('reabertos') }}</span></div>
        </div>
    </section>

    @if($academicYear && $period)
        <nav class="sge-status-filter-strip mb-4" aria-label="{{ __('Filtros rápidos dos diários por situação') }}">
            <span>{{ __('Ver') }}</span>
            @foreach($quickStatusFilters as $filter)
                @php($isActive = $filter['key'] === null ? ! $listingRequest->filled('status') : $listingRequest->input('status') === $filter['key'])
                @php($query = $filter['key'] === null ? $baseDiaryQuery : array_merge($baseDiaryQuery, ['status' => $filter['key']]))
                <a class="sge-status-filter-chip {{ $isActive ? 'is-active' : '' }}" wire:click.prevent="selectFilter('status', '{{ $filter['key'] ?? '' }}')" data-status="{{ $filter['key'] ?? 'all' }}" href="{{ route('teacher-diaries.index', $query) }}" @if($isActive) aria-current="page" @endif>
                    <i class="fas {{ $filter['icon'] }}" aria-hidden="true"></i>
                    <span>{{ $filter['label'] }}</span>
                    <strong>{{ $filter['count'] }}</strong>
                </a>
            @endforeach
        </nav>
    @endif

    <section class="card shadow mb-4" aria-labelledby="diary-filter-title">
        <div class="card-header py-3">
            <h2 id="diary-filter-title" class="h6 m-0 font-weight-bold text-primary">{{ __('Filtros') }}</h2>
        </div>
        <div class="card-body">
            <form wire:submit="applyFilters" method="GET" action="{{ route('teacher-diaries.index') }}" class="sge-diary-filter-grid">
                <div class="form-group">
                    <label for="school">{{ __('Escola') }}</label>
                    <select id="school" wire:model.live="filters.school" name="school" class="custom-select">
                        <option value="">{{ __('Todas disponíveis') }}</option>
                        @foreach($schools as $school)
                            <option value="{{ $school->id }}" @selected((string) $listingRequest->input('school') === (string) $school->id)>{{ $school->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="year">{{ __('Ano') }}</label>
                    <select id="year" wire:model.live="filters.year" name="year" class="custom-select">
                        <option value="">{{ __('Todos') }}</option>
                        @foreach($referenceYears as $referenceYear)<option value="{{ $referenceYear }}" @selected((string) $listingRequest->input('year') === (string) $referenceYear)>{{ $referenceYear }}</option>@endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="academic_year">{{ __('Ano letivo') }}</label>
                    <select id="academic_year" wire:model.live="filters.academic_year" name="academic_year" class="custom-select">
                        @foreach($years as $year)
                            <option value="{{ $year->id }}" @selected($academicYear?->id === $year->id)>{{ $year->reference_year }} · {{ $year->school?->name }} · {{ $year->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="period">{{ __('Período avaliativo') }}</label>
                    <select id="period" wire:model.live="filters.period" name="period" class="custom-select">
                        @foreach($periods as $availablePeriod)
                            <option value="{{ $availablePeriod->id }}" @selected($period?->id === $availablePeriod->id)>{{ $availablePeriod->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="status">{{ __('Situação') }}</label>
                    <select id="status" wire:model.live="filters.status" name="status" class="custom-select">
                        <option value="">{{ __('Todas') }}</option>
                        @foreach($statusLabels as $key => $label)
                            <option value="{{ $key }}" @selected($listingRequest->input('status') === $key)>{{ __($label) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="class">{{ __('Turma') }}</label>
                    <select id="class" wire:model.live="filters.class" name="class" class="custom-select">
                        <option value="">{{ __('Todas') }}</option>
                        @foreach($classOptions as $class)
                            <option value="{{ $class->id }}" @selected((string) $listingRequest->input('class') === (string) $class->id)>{{ $class->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="component">{{ __('Componente') }}</label>
                    <select id="component" wire:model.live="filters.component" name="component" class="custom-select">
                        <option value="">{{ __('Todos') }}</option>
                        @foreach($componentOptions as $component)
                            <option value="{{ $component->id }}" @selected($selectedComponentName === mb_strtolower(trim($component->name)))>{{ $component->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="teacher">{{ __('Docência') }}</label>
                    <select id="teacher" wire:model.live="filters.teacher" name="teacher" class="custom-select">
                        <option value="">{{ __('Todas') }}</option>
                        @foreach($teacherOptions as $teacher)
                            <option value="{{ $teacher->id }}" @selected((string) $listingRequest->input('teacher') === (string) $teacher->id)>{{ $teacher->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group sge-diary-filter-actions">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-filter mr-1" aria-hidden="true"></i> {{ __('Filtrar') }}
                    </button>
                    <a class="btn btn-outline-secondary" wire:click.prevent="clearFilters" href="{{ route('teacher-diaries.index') }}">{{ __('Limpar') }}</a>
                </div>
            </form>
        </div>
    </section>

    @if(! $academicYear || ! $period)
        <section class="card shadow">
            <div class="card-body">
                <div class="sge-empty-state">
                    <i class="fas fa-calendar-check" aria-hidden="true"></i>
                    <p>{{ __('Nenhum ano letivo aprovado com período avaliativo foi encontrado para acompanhamento.') }}</p>
                </div>
            </div>
        </section>
    @else
        <section class="card shadow" aria-labelledby="diary-groups-title">
            <div class="card-header py-3 d-flex align-items-center justify-content-between flex-wrap">
                <h2 id="diary-groups-title" class="h6 m-0 font-weight-bold text-primary">{{ __('Turmas') }}</h2>
                <span class="badge badge-light">{{ $groupedClasses->count() }} {{ __('turma(s)') }}</span>
            </div>
            <div class="card-body">
                @forelse($groupedClasses as $group)
                    @php($class = $group['class'])
                    <details class="sge-diary-class-group" @if($group['stats']['pending'] > 0 || $listingRequest->filled('class')) open @endif>
                        <summary>
                            <span class="sge-diary-class-title">
                                <strong>{{ $class?->name }}</strong>
                                <small>{{ $group['stats']['total'] }} {{ __('diário(s)') }}</small>
                            </span>
                            <span class="sge-diary-class-badges">
                                @if($group['stats']['pending'] > 0)<span class="badge badge-danger">{{ $group['stats']['pending'] }} {{ __('pendente(s)') }}</span>@endif
                                @if($group['stats']['waiting'] > 0)<span class="badge badge-warning">{{ $group['stats']['waiting'] }} {{ __('aguardando') }}</span>@endif
                                @if($group['stats']['reopened'] > 0)<span class="badge badge-info">{{ $group['stats']['reopened'] }} {{ __('reaberto(s)') }}</span>@endif
                                @if($group['stats']['confirmed'] > 0)<span class="badge badge-success">{{ $group['stats']['confirmed'] }} {{ __('confirmado(s)') }}</span>@endif
                            </span>
                        </summary>

                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('Componente') }}</th>
                                        <th>{{ __('Docência') }}</th>
                                        <th>{{ __('Frequência') }}</th>
                                        <th>{{ __('Conteúdo') }}</th>
                                        <th>{{ __('Notas') }}</th>
                                        <th>{{ __('Situação') }}</th>
                                        <th class="text-right">{{ __('Ações') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($group['summaries'] as $summary)
                                        @php($assignment = $summary['assignment'])
                                        @php($statusKey = $summary['pending']['is_pending'] ? 'pending' : ($summary['confirmation']?->confirmed ? 'confirmed' : ($summary['confirmation']?->reopened_at ? 'reopened' : 'waiting')))
                                        <tr>
                                            <td>
                                                <strong>{{ $assignment->component?->name }}</strong>
                                                <span class="d-block small text-muted">{{ $assignment->component?->area?->name ?? __('Área não definida') }}</span>
                                            </td>
                                            <td>{{ $assignment->teacher?->full_name ?? __('Não definida') }}</td>
                                            <td>{{ count($summary['pending']['content_without_attendance']) }} {{ __('pendência(s)') }}</td>
                                            <td>{{ count($summary['pending']['attendance_without_content']) }} {{ __('pendência(s)') }}</td>
                                            <td>{{ $summary['pending']['missing_grades'] }} {{ __('pendência(s)') }}</td>
                                            <td>
                                                <span class="badge badge-{{ $statusKey === 'confirmed' ? 'success' : ($statusKey === 'pending' ? 'danger' : ($statusKey === 'reopened' ? 'info' : 'warning')) }}">{{ $statusLabels[$statusKey] }}</span>
                                                @if(($summary['alert_count'] ?? 0) > 0)
                                                    <span class="badge badge-info mt-1">{{ $summary['alert_count'] }} {{ __('alerta(s)') }}</span>
                                                @endif
                                            </td>
                                            <td class="text-right">
                                                <div class="sge-action-buttons">
                                                    <a class="btn btn-primary btn-sm sge-icon-action" href="{{ route('teacher-diaries.show', [$assignment->schoolClass, $assignment->component, 'period' => $period->id]) }}" aria-label="{{ __('Abrir diário de') }} {{ $assignment->component?->name }}" title="{{ __('Abrir diário') }}">
                                                        <i class="fas fa-book-open" aria-hidden="true"></i>
                                                    </a>
                                                    <a target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm sge-icon-action" href="{{ route('teacher-diaries.pdf', [$assignment->schoolClass, $assignment->component, 'period' => $period->id]) }}" aria-label="{{ __('Imprimir diário de') }} {{ $assignment->component?->name }}" title="{{ __('Imprimir diário') }}">
                                                        <i class="fas fa-file-pdf" aria-hidden="true"></i>
                                                    </a>
                                                    <a target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm sge-icon-action" href="{{ route('teacher-diaries.attendance-sheet.pdf', [$assignment->schoolClass, $assignment->component]) }}" aria-label="{{ __('Imprimir lista de chamada de') }} {{ $assignment->component?->name }}" title="{{ __('Lista de chamada') }}">
                                                        <i class="fas fa-clipboard-list" aria-hidden="true"></i>
                                                    </a>
                                                    <details class="sge-inline-alert-form">
                                                        <summary class="btn btn-outline-warning btn-sm sge-icon-action" aria-label="{{ __('Enviar alerta para') }} {{ $assignment->teacher?->full_name ?? __('docência não definida') }}" title="{{ __('Enviar alerta') }}">
                                                            <i class="fas fa-bell" aria-hidden="true"></i>
                                                        </summary>
                                                        <form method="POST" action="{{ route('teacher-diaries.alerts.store', [$assignment->schoolClass, $assignment->component]) }}">
                                                            @csrf
                                                            <input type="hidden" name="academic_period_id" value="{{ $period->id }}">
                                                            <label class="sr-only" for="alert_message_{{ $assignment->id }}">{{ __('Mensagem do alerta') }}</label>
                                                            <textarea id="alert_message_{{ $assignment->id }}" name="message" class="form-control form-control-sm" rows="3" placeholder="{{ __('Explique o que precisa ser corrigido ou lançado.') }}" required></textarea>
                                                            <button class="btn btn-warning btn-sm mt-2" type="submit">{{ __('Enviar alerta') }}</button>
                                                        </form>
                                                    </details>
                                                </div>
                                            </td>
                                        </tr>
                                        @if($summary['alerts']->isNotEmpty())
                                            <tr>
                                                <td colspan="7">
                                                    <details class="py-1" @if(($summary['alert_count'] ?? 0) > 0) open @endif>
                                                        <summary class="font-weight-bold text-primary">
                                                            <i class="fas fa-bell mr-1" aria-hidden="true"></i>
                                                            {{ __('Alertas deste diário') }} ({{ $summary['alerts']->count() }})
                                                        </summary>
                                                        @foreach($summary['alerts'] as $alert)
                                                            <div class="border rounded p-2 mt-2 d-flex align-items-start justify-content-between flex-wrap">
                                                                <div class="mr-3">
                                                                    <span class="badge badge-{{ $alert->dismissed_at ? 'secondary' : 'warning' }}">
                                                                        {{ $alert->dismissed_at ? __('Já visto') : __('Não visto') }}
                                                                    </span>
                                                                    <span class="small text-muted ml-2">
                                                                        {{ $alert->fromPerson?->full_name ?? __('Gestão') }} → {{ $alert->toPerson?->full_name ?? __('Não definido') }}
                                                                        · {{ $alert->created_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}
                                                                        @if($alert->dismissed_at)
                                                                            · {{ __('Visto em:') }} {{ $alert->dismissed_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}
                                                                        @endif
                                                                    </span>
                                                                    <p class="mb-0 mt-1">{{ $alert->message }}</p>
                                                                </div>
                                                                <form method="POST" action="{{ route('teacher-diaries.alerts.destroy', $alert) }}" data-confirm-message="{{ __('Excluir este alerta do diário? Esta ação é permanente.') }}">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button class="btn btn-sm btn-outline-danger" type="submit" aria-label="{{ __('Excluir alerta') }}">
                                                                        <i class="fas fa-trash-alt mr-1" aria-hidden="true"></i>{{ __('Excluir') }}
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        @endforeach
                                                    </details>
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @empty
                    <div class="sge-empty-state">
                        <i class="fas fa-filter" aria-hidden="true"></i>
                        <p>{{ __('Nenhum diário encontrado com os filtros atuais. Ajuste os filtros ou confira se há turmas e componentes ativos.') }}</p>
                    </div>
                @endforelse
            </div>
        </section>
    @endif

</div>
