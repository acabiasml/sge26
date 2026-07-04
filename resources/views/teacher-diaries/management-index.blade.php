@extends('layouts.app')

@section('title', 'Acompanhamento dos diários')
@section('page-title', 'Acompanhamento dos diários')

@section('page-actions')
    @if($academicYear)
        <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('academic-years.periods.index', $academicYear) }}" aria-label="Gerenciar períodos avaliativos" title="Gerenciar períodos avaliativos">
            <i class="fas fa-sliders-h" aria-hidden="true"></i>
        </a>
        <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('academic-years.schedules-pdf', $academicYear) }}" aria-label="Imprimir horários das turmas" title="Imprimir horários das turmas">
            <i class="fas fa-calendar-week" aria-hidden="true"></i>
        </a>
    @endif
@endsection

@section('content')
    @php
        $baseDiaryQuery = request()->except('status', 'page');
        $quickStatusFilters = [
            ['key' => null, 'label' => 'Todos', 'count' => $stats['total'], 'icon' => 'fa-layer-group'],
            ['key' => 'pending', 'label' => 'Com pendência', 'count' => $stats['pending'], 'icon' => 'fa-exclamation-circle'],
            ['key' => 'waiting', 'label' => 'Aguardando', 'count' => $stats['waiting'], 'icon' => 'fa-hourglass-half'],
            ['key' => 'confirmed', 'label' => 'Confirmados', 'count' => $stats['confirmed'], 'icon' => 'fa-check-circle'],
            ['key' => 'reopened', 'label' => 'Reabertos', 'count' => $stats['reopened'], 'icon' => 'fa-unlock'],
        ];
    @endphp

    <section class="sge-diary-management-hero" aria-labelledby="diary-management-context">
        <div>
            <span class="sge-page-kicker">Gestão pedagógica</span>
            <h2 id="diary-management-context">{{ $academicYear?->school?->name ?? 'Nenhuma escola selecionada' }}</h2>
            <p>
                @if($academicYear && $period)
                    {{ $academicYear->name }} · {{ $period->name }} · {{ $period->starts_at->format('d/m/Y') }} a {{ $period->ends_at->format('d/m/Y') }}
                @else
                    Selecione um ano letivo aprovado para acompanhar os diários.
                @endif
            </p>
        </div>
        <div class="sge-diary-management-metrics" aria-label="Resumo dos diários filtrados">
            <div><strong>{{ $stats['total'] }}</strong><span>diários</span></div>
            <div><strong>{{ $stats['pending'] }}</strong><span>com pendência</span></div>
            <div><strong>{{ $stats['waiting'] }}</strong><span>aguardando</span></div>
            <div><strong>{{ $stats['confirmed'] }}</strong><span>confirmados</span></div>
        </div>
    </section>

    @if($academicYear && $period)
        <nav class="sge-status-filter-strip mb-4" aria-label="Filtros rápidos dos diários por situação">
            <span>Ver</span>
            @foreach($quickStatusFilters as $filter)
                @php($isActive = $filter['key'] === null ? ! request()->filled('status') : request('status') === $filter['key'])
                @php($query = $filter['key'] === null ? $baseDiaryQuery : array_merge($baseDiaryQuery, ['status' => $filter['key']]))
                <a class="sge-status-filter-chip {{ $isActive ? 'is-active' : '' }}" href="{{ route('teacher-diaries.index', $query) }}" @if($isActive) aria-current="page" @endif>
                    <i class="fas {{ $filter['icon'] }}" aria-hidden="true"></i>
                    <span>{{ $filter['label'] }}</span>
                    <strong>{{ $filter['count'] }}</strong>
                </a>
            @endforeach
        </nav>
    @endif

    <section class="card shadow mb-4" aria-labelledby="diary-filter-title">
        <div class="card-header py-3">
            <h2 id="diary-filter-title" class="h6 m-0 font-weight-bold text-primary">Filtros</h2>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('teacher-diaries.index') }}" class="sge-diary-filter-grid">
                <div class="form-group">
                    <label for="school">Escola</label>
                    <select id="school" name="school" class="custom-select">
                        <option value="">Todas disponíveis</option>
                        @foreach($schools as $school)
                            <option value="{{ $school->id }}" @selected((string) request('school') === (string) $school->id)>{{ $school->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="academic_year">Ano letivo</label>
                    <select id="academic_year" name="academic_year" class="custom-select">
                        @foreach($years as $year)
                            <option value="{{ $year->id }}" @selected($academicYear?->id === $year->id)>{{ $year->school?->name }} · {{ $year->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="period">Período avaliativo</label>
                    <select id="period" name="period" class="custom-select">
                        @foreach($periods as $availablePeriod)
                            <option value="{{ $availablePeriod->id }}" @selected($period?->id === $availablePeriod->id)>{{ $availablePeriod->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="status">Situação</label>
                    <select id="status" name="status" class="custom-select">
                        <option value="">Todas</option>
                        @foreach($statusLabels as $key => $label)
                            <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="class">Turma</label>
                    <select id="class" name="class" class="custom-select">
                        <option value="">Todas</option>
                        @foreach($classOptions as $class)
                            <option value="{{ $class->id }}" @selected((string) request('class') === (string) $class->id)>{{ $class->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="component">Componente</label>
                    <select id="component" name="component" class="custom-select">
                        <option value="">Todos</option>
                        @foreach($componentOptions as $component)
                            <option value="{{ $component->id }}" @selected((string) request('component') === (string) $component->id)>{{ $component->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="teacher">Docência</label>
                    <select id="teacher" name="teacher" class="custom-select">
                        <option value="">Todas</option>
                        @foreach($teacherOptions as $teacher)
                            <option value="{{ $teacher->id }}" @selected((string) request('teacher') === (string) $teacher->id)>{{ $teacher->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group sge-diary-filter-actions">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-filter mr-1" aria-hidden="true"></i> Filtrar
                    </button>
                    <a class="btn btn-outline-secondary" href="{{ route('teacher-diaries.index') }}">Limpar</a>
                </div>
            </form>
        </div>
    </section>

    @if(! $academicYear || ! $period)
        <section class="card shadow">
            <div class="card-body">
                <div class="sge-empty-state">
                    <i class="fas fa-calendar-check" aria-hidden="true"></i>
                    <p>Nenhum ano letivo aprovado com período avaliativo foi encontrado para acompanhamento.</p>
                </div>
            </div>
        </section>
    @else
        <section class="card shadow" aria-labelledby="diary-groups-title">
            <div class="card-header py-3 d-flex align-items-center justify-content-between flex-wrap">
                <h2 id="diary-groups-title" class="h6 m-0 font-weight-bold text-primary">Turmas</h2>
                <span class="badge badge-light">{{ $groupedClasses->count() }} turma(s)</span>
            </div>
            <div class="card-body">
                @forelse($groupedClasses as $group)
                    @php($class = $group['class'])
                    <details class="sge-diary-class-group" @if($group['stats']['pending'] > 0 || request()->filled('class')) open @endif>
                        <summary>
                            <span class="sge-diary-class-title">
                                <strong>{{ $class?->name }}</strong>
                                <small>{{ $group['stats']['total'] }} diário(s)</small>
                            </span>
                            <span class="sge-diary-class-badges">
                                @if($group['stats']['pending'] > 0)<span class="badge badge-danger">{{ $group['stats']['pending'] }} pendente(s)</span>@endif
                                @if($group['stats']['waiting'] > 0)<span class="badge badge-warning">{{ $group['stats']['waiting'] }} aguardando</span>@endif
                                @if($group['stats']['reopened'] > 0)<span class="badge badge-info">{{ $group['stats']['reopened'] }} reaberto(s)</span>@endif
                                @if($group['stats']['confirmed'] > 0)<span class="badge badge-success">{{ $group['stats']['confirmed'] }} confirmado(s)</span>@endif
                            </span>
                        </summary>

                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Componente</th>
                                        <th>Docência</th>
                                        <th>Frequência</th>
                                        <th>Conteúdo</th>
                                        <th>Notas</th>
                                        <th>Situação</th>
                                        <th class="text-right">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($group['summaries'] as $summary)
                                        @php($assignment = $summary['assignment'])
                                        @php($statusKey = $summary['pending']['is_pending'] ? 'pending' : ($summary['confirmation']?->confirmed ? 'confirmed' : ($summary['confirmation']?->reopened_at ? 'reopened' : 'waiting')))
                                        <tr>
                                            <td>
                                                <strong>{{ $assignment->component?->name }}</strong>
                                                <span class="d-block small text-muted">{{ $assignment->component?->area?->name ?? 'Área não definida' }}</span>
                                            </td>
                                            <td>{{ $assignment->teacher?->full_name ?? 'Não definida' }}</td>
                                            <td>{{ count($summary['pending']['content_without_attendance']) }} pendência(s)</td>
                                            <td>{{ count($summary['pending']['attendance_without_content']) }} pendência(s)</td>
                                            <td>{{ $summary['pending']['missing_grades'] }} pendência(s)</td>
                                            <td>
                                                <span class="badge badge-{{ $statusKey === 'confirmed' ? 'success' : ($statusKey === 'pending' ? 'danger' : ($statusKey === 'reopened' ? 'info' : 'warning')) }}">{{ $statusLabels[$statusKey] }}</span>
                                                @if(($summary['alert_count'] ?? 0) > 0)
                                                    <span class="badge badge-info mt-1">{{ $summary['alert_count'] }} alerta(s)</span>
                                                @endif
                                            </td>
                                            <td class="text-right">
                                                <div class="sge-action-buttons">
                                                    <a class="btn btn-primary btn-sm sge-icon-action" href="{{ route('teacher-diaries.show', [$assignment->schoolClass, $assignment->component, 'period' => $period->id]) }}" aria-label="Abrir diário de {{ $assignment->component?->name }}" title="Abrir diário">
                                                        <i class="fas fa-book-open" aria-hidden="true"></i>
                                                    </a>
                                                    <a class="btn btn-outline-primary btn-sm sge-icon-action" href="{{ route('teacher-diaries.pdf', [$assignment->schoolClass, $assignment->component, 'period' => $period->id]) }}" aria-label="Imprimir diário de {{ $assignment->component?->name }}" title="Imprimir diário">
                                                        <i class="fas fa-file-pdf" aria-hidden="true"></i>
                                                    </a>
                                                    <a class="btn btn-outline-primary btn-sm sge-icon-action" href="{{ route('teacher-diaries.attendance-sheet.pdf', [$assignment->schoolClass, $assignment->component]) }}" aria-label="Imprimir lista de chamada de {{ $assignment->component?->name }}" title="Lista de chamada">
                                                        <i class="fas fa-clipboard-list" aria-hidden="true"></i>
                                                    </a>
                                                    <details class="sge-inline-alert-form">
                                                        <summary class="btn btn-outline-warning btn-sm sge-icon-action" aria-label="Enviar alerta para {{ $assignment->teacher?->full_name ?? 'docência não definida' }}" title="Enviar alerta">
                                                            <i class="fas fa-bell" aria-hidden="true"></i>
                                                        </summary>
                                                        <form method="POST" action="{{ route('teacher-diaries.alerts.store', [$assignment->schoolClass, $assignment->component]) }}">
                                                            @csrf
                                                            <input type="hidden" name="academic_period_id" value="{{ $period->id }}">
                                                            <label class="sr-only" for="alert_message_{{ $assignment->id }}">Mensagem do alerta</label>
                                                            <textarea id="alert_message_{{ $assignment->id }}" name="message" class="form-control form-control-sm" rows="3" placeholder="Explique o que precisa ser corrigido ou lançado." required></textarea>
                                                            <button class="btn btn-warning btn-sm mt-2" type="submit">Enviar alerta</button>
                                                        </form>
                                                    </details>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @empty
                    <div class="sge-empty-state">
                        <i class="fas fa-filter" aria-hidden="true"></i>
                        <p>Nenhum diário encontrado com os filtros atuais. Ajuste os filtros ou confira se há turmas e componentes ativos.</p>
                    </div>
                @endforelse
            </div>
        </section>
    @endif
@endsection
