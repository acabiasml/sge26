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
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h2 id="diary-filter-title" class="h6 m-0 font-weight-bold text-primary">{{ __('Filtros') }}</h2>
            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="clearFilters">{{ __('Limpar') }}</button>
        </div>
        <div class="card-body">
            <form wire:submit="applyFilters" method="GET" action="{{ route('teacher-diaries.index') }}">
                <div class="sge-diary-basic-filters">
                    <div class="form-group mb-0">
                        <label for="diary-search">{{ __('Pesquisar') }}</label>
                        <input id="diary-search" name="q" type="search" wire:model.live.debounce.350ms="filters.q" class="form-control" placeholder="{{ __('Digite para pesquisar') }}">
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
                </div>
                @php($extraFiltersActive = collect(['school', 'year', 'status', 'class', 'component', 'teacher'])->filter(fn ($key) => $listingRequest->filled($key))->count())
                <details wire:ignore.self wire:key="diary-extra-filters-{{ $academicYear?->id ?? 'none' }}" class="sge-diary-extra-filters mt-3" @if($extraFiltersActive) open @endif>
                    <summary class="text-primary">{{ __('Mais filtros') }} @if($extraFiltersActive)<span class="badge badge-light">{{ $extraFiltersActive }}</span>@endif</summary>
                    <div class="sge-diary-filter-grid mt-3">
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
                        <div class="form-group sge-diary-filter-actions"><button class="btn btn-primary" type="submit">{{ __('Filtrar') }}</button></div>
                    </div>
                </details>
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
                @if($groupedClasses->isNotEmpty())
                    <nav class="sge-section-nav sge-academic-tabs sge-diary-class-tabs mb-3" role="tablist" aria-label="{{ __('Turmas') }}" data-diary-class-tabs>
                        @foreach($groupedClasses as $classGroup)
                            @php($tabClass = $classGroup['class'])
                            @php($selected = $diaryClassTab === (string) $tabClass->id)
                            <button type="button" wire:key="class-tab-{{ $tabClass->id }}" wire:click="selectDiaryClass({{ $tabClass->id }})" id="diary-class-tab-{{ $tabClass->id }}" role="tab" aria-controls="diary-class-panel-{{ $tabClass->id }}" aria-selected="{{ $selected ? 'true' : 'false' }}" tabindex="{{ $selected ? '0' : '-1' }}" class="sge-section-nav-item {{ $selected ? 'is-active' : '' }}">
                                <span>{{ $tabClass->name }}</span>
                                <small class="sge-diary-class-tab-meta">{{ $classGroup['stats']['total'] }} {{ __('diário(s)') }} · {{ $classGroup['stats']['pending'] }} {{ __('pendente(s)') }}</small>
                            </button>
                        @endforeach
                    </nav>
                @endif
                @forelse($groupedClasses as $group)
                    @php($class = $group['class'])
                    <section wire:key="diary-class-panel-{{ $class->id }}-{{ $period->id }}" id="diary-class-panel-{{ $class->id }}" class="sge-diary-class-group sge-diary-class-panel" role="tabpanel" aria-labelledby="diary-class-tab-{{ $class->id }}" tabindex="0" @if($diaryClassTab !== (string) $class->id) hidden @endif>
                        <div class="sge-diary-class-header">
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
                        </div>

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
                                                    <button type="button" class="btn btn-outline-warning btn-sm sge-icon-action" data-diary-alert-manager data-school-class="{{ $assignment->school_class_id }}" data-component="{{ $assignment->curriculum_component_id }}" data-period="{{ $period->id }}" aria-label="{{ __('Gerenciar alertas de') }} {{ $assignment->component?->name }}" title="{{ __('Gerenciar alertas') }}">
                                                        <i class="fas fa-bell" aria-hidden="true"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
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
