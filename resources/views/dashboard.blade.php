@extends('layouts.app')

@section('title', 'Início')
@section('page-title', 'Início')

@php
    $studentCount = $roleCounts[\App\Models\PersonSchoolRole::ROLE_STUDENT] ?? 0;
    $teacherCount = $roleCounts[\App\Models\PersonSchoolRole::ROLE_TEACHER] ?? 0;
    $managerCount = $roleCounts[\App\Models\PersonSchoolRole::ROLE_MANAGER] ?? 0;
    $employeeCount = $roleCounts[\App\Models\PersonSchoolRole::ROLE_EMPLOYEE] ?? 0;
    $roleTotal = array_sum($roleChart['values']);
    $hasRoleChart = $roleTotal > 0;
    $hasStudentsChart = array_sum($studentsBySchoolChart['values']) > 0;
    $hasCalendarTypeChart = array_sum($calendarTypeChart['values']) > 0;
@endphp

@section('content')
    <section class="sge-dashboard-hero mb-4" aria-labelledby="dashboard-title">
        <div class="sge-dashboard-hero-content">
            <div class="sge-page-kicker">Painel de acompanhamento</div>
            <h2 id="dashboard-title">Visão geral do Beabá</h2>
            <p>Indicadores, recados e calendário letivo reunidos para acompanhar a rotina escolar sem caçar informação em cinco telas diferentes.</p>
        </div>
        <div class="sge-dashboard-date" aria-label="Data atual em Brasília">
            <span>{{ now('America/Sao_Paulo')->translatedFormat('d \d\e F') }}</span>
            <small>{{ now('America/Sao_Paulo')->format('Y') }} · Horário de Brasília</small>
        </div>
    </section>

    @can('manage-people')
        <section class="sge-dashboard-metrics mb-4" aria-label="Indicadores principais">
            @can('manage-schools')
                <a class="sge-metric-card sge-metric-brown" href="{{ route('schools.index') }}">
                    <span class="sge-metric-icon" aria-hidden="true"><i class="fas fa-school"></i></span>
                    <span class="sge-metric-label">Escolas ativas</span>
                    <strong>{{ number_format($schoolCount, 0, ',', '.') }}</strong>
                    <span class="sge-metric-note">Unidades em operação</span>
                </a>
            @endcan

            <a class="sge-metric-card sge-metric-green" href="{{ route('people.index') }}">
                <span class="sge-metric-icon" aria-hidden="true"><i class="fas fa-users"></i></span>
                <span class="sge-metric-label">Pessoas ativas</span>
                <strong>{{ number_format($personCount, 0, ',', '.') }}</strong>
                <span class="sge-metric-note">Cadastros com vínculo ativo</span>
            </a>

            <a class="sge-metric-card sge-metric-blue" href="{{ route('enrollments.index') }}">
                <span class="sge-metric-icon" aria-hidden="true"><i class="fas fa-user-graduate"></i></span>
                <span class="sge-metric-label">Matrículas</span>
                <strong>{{ number_format($activeEnrollmentCount, 0, ',', '.') }}</strong>
                <span class="sge-metric-note">Estudantes matriculados</span>
            </a>

            <a class="sge-metric-card sge-metric-gold" href="{{ route('data-quality.index') }}">
                <span class="sge-metric-icon" aria-hidden="true"><i class="fas fa-clipboard-check"></i></span>
                <span class="sge-metric-label">Pendências</span>
                <strong>{{ number_format($registrationPendingCount, 0, ',', '.') }}</strong>
                <span class="sge-metric-note">Cadastros ativos a revisar</span>
            </a>

            <div class="sge-metric-card sge-metric-orange">
                <span class="sge-metric-icon" aria-hidden="true"><i class="fas fa-calendar-check"></i></span>
                <span class="sge-metric-label">Anos letivos</span>
                <strong>{{ number_format($activeAcademicYearCount, 0, ',', '.') }}</strong>
                <span class="sge-metric-note">Ativos neste período</span>
            </div>
        </section>
    @endcan

    <div class="row">
        <div class="col-xl-8 mb-4">
            <section class="card sge-panel-card h-100" aria-labelledby="calendar-heading">
                <div class="card-header sge-panel-header">
                    <div>
                        <h2 id="calendar-heading">
                            Calendário do mês
                            <span class="sr-only">Calendários letivos do mês</span>
                        </h2>
                        <p>{{ $monthSchoolDayCount }} dia(s) letivo(s), {{ $birthdays->count() }} aniversário(s) e {{ $monthAcademicCalendars->count() }} calendário(s) visível(is).</p>
                    </div>
                    <div class="sge-calendar-legend" aria-label="Legenda do calendário">
                        <span><strong>L</strong> Letivo</span>
                        <span><strong class="sge-dot sge-dot-orange"></strong> Aniversário</span>
                    </div>
                </div>
                <div class="card-body">
                    @if ($monthAcademicCalendars->isNotEmpty() || $birthdays->isNotEmpty())
                        @include('dashboard._combined-calendar', ['month' => $combinedCalendarMonth])

                        <div class="sge-dashboard-subgrid mt-3">
                            <section aria-labelledby="visible-calendars-heading">
                                <h3 id="visible-calendars-heading">Calendários visíveis</h3>
                                <div class="sge-chip-list">
                                    @forelse ($monthAcademicCalendars as $academicYear)
                                        <span class="sge-info-chip">
                                            <strong>{{ $academicYear->school?->name }}</strong>
                                            {{ $academicYear->name }}
                                        </span>
                                    @empty
                                        <span class="text-muted">Nenhum calendário letivo ativo para este mês.</span>
                                    @endforelse
                                </div>
                            </section>

                            <section aria-labelledby="birthdays-heading">
                                <h3 id="birthdays-heading">Aniversariantes</h3>
                                @forelse ($birthdays->take(6) as $person)
                                    <div class="sge-mini-row">
                                        <span>{{ $person->birth_date?->format('d/m') }}</span>
                                        <strong>{{ $person->social_name ?: $person->full_name }}</strong>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">Nenhum aniversário cadastrado para este mês.</p>
                                @endforelse
                            </section>
                        </div>
                    @else
                        <p class="text-muted mb-0">Nenhum calendário ou aniversário ativo para este mês.</p>
                    @endif
                </div>
            </section>
        </div>

        <div class="col-xl-4 mb-4">
            <section class="card sge-panel-card h-100" aria-labelledby="announcements-heading">
                <div class="card-header sge-panel-header">
                    <div>
                        <h2 id="announcements-heading">Recados ativos</h2>
                        <p>{{ $announcements->count() }} recado(s) em exibição.</p>
                    </div>
                    @can('manage-people')
                        <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('announcements.index') }}" aria-label="Gerenciar recados" title="Gerenciar recados">
                            <i class="fas fa-pen" aria-hidden="true"></i>
                        </a>
                    @endcan
                </div>
                <div class="card-body">
                    @forelse ($announcements as $announcement)
                        <article class="sge-announcement-item">
                            <div class="sge-announcement-meta">
                                <span>{{ $announcement->school?->name ?? 'Global' }}</span>
                                @if ($announcement->highlight)
                                    <span class="badge badge-warning">Destaque</span>
                                @endif
                            </div>
                            <h3>{{ $announcement->title }}</h3>
                            <p>{{ $announcement->body }}</p>
                        </article>
                    @empty
                        <p class="text-muted mb-0">Nenhum recado ativo.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>

    @can('manage-people')
        <div class="row">
            <div class="col-xl-4 col-lg-6 mb-4">
                <section class="card sge-panel-card h-100" aria-labelledby="roles-chart-heading">
                    <div class="card-header sge-panel-header">
                        <div>
                            <h2 id="roles-chart-heading">Pessoas por papel</h2>
                            <p>Total de {{ number_format($roleTotal, 0, ',', '.') }} vínculo(s) ativo(s).</p>
                        </div>
                    </div>
                    <div class="card-body">
                        @if ($hasRoleChart)
                            <div class="sge-chart-wrap" aria-hidden="true">
                                <canvas id="rolesChart"></canvas>
                            </div>
                            <div class="sr-only">
                                @foreach ($roleChart['labels'] as $index => $label)
                                    {{ $label }}: {{ $roleChart['values'][$index] ?? 0 }}.
                                @endforeach
                            </div>
                            <div class="sge-chart-list" aria-hidden="true">
                                @foreach ($roleChart['labels'] as $index => $label)
                                    <div>
                                        <span>{{ $label }}</span>
                                        <strong>{{ number_format($roleChart['values'][$index] ?? 0, 0, ',', '.') }}</strong>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-muted mb-0">Ainda não há vínculos ativos suficientes para montar o gráfico.</p>
                        @endif
                    </div>
                </section>
            </div>

            <div class="col-xl-4 col-lg-6 mb-4">
                <section class="card sge-panel-card h-100" aria-labelledby="roles-summary-heading">
                    <div class="card-header sge-panel-header">
                        <div>
                            <h2 id="roles-summary-heading">Composição da comunidade</h2>
                            <p>Leitura rápida dos principais grupos.</p>
                        </div>
                    </div>
                    <div class="card-body">
                        @foreach ([
                            ['label' => 'Estudantes', 'value' => $studentCount, 'icon' => 'fa-user-graduate'],
                            ['label' => 'Docência', 'value' => $teacherCount, 'icon' => 'fa-chalkboard-teacher'],
                            ['label' => 'Gestão', 'value' => $managerCount, 'icon' => 'fa-user-tie'],
                            ['label' => 'Equipe escolar', 'value' => $employeeCount, 'icon' => 'fa-hands-helping'],
                        ] as $item)
                            @php
                                $percent = $roleTotal > 0 ? round(($item['value'] / $roleTotal) * 100) : 0;
                            @endphp
                            <div class="sge-progress-row">
                                <div>
                                    <i class="fas {{ $item['icon'] }}" aria-hidden="true"></i>
                                    <span>{{ $item['label'] }}</span>
                                </div>
                                <strong>{{ number_format($item['value'], 0, ',', '.') }}</strong>
                                <div class="progress" aria-label="{{ $item['label'] }}: {{ $percent }}%">
                                    <div class="progress-bar" role="progressbar" style="width: {{ $percent }}%;" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>

            <div class="col-xl-4 mb-4">
                <section class="card sge-panel-card h-100" aria-labelledby="calendar-chart-heading">
                    <div class="card-header sge-panel-header">
                        <div>
                            <h2 id="calendar-chart-heading">Dias do mês</h2>
                            <p>Distribuição dos tipos de dia nos calendários visíveis.</p>
                        </div>
                    </div>
                    <div class="card-body">
                        @if ($hasCalendarTypeChart)
                            <div class="sge-chart-wrap sge-chart-wrap-compact" aria-hidden="true">
                                <canvas id="calendarTypeChart"></canvas>
                            </div>
                            <div class="sge-chart-list">
                                @foreach ($calendarTypeChart['labels'] as $index => $label)
                                    <div>
                                        <span>{{ $label }}</span>
                                        <strong>{{ number_format($calendarTypeChart['values'][$index] ?? 0, 0, ',', '.') }}</strong>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-muted mb-0">Ainda não há dias do calendário neste mês.</p>
                        @endif
                    </div>
                </section>
            </div>
        </div>

        <section class="card sge-panel-card mb-4" aria-labelledby="students-school-heading">
            <div class="card-header sge-panel-header">
                <div>
                    <h2 id="students-school-heading">Estudantes por escola</h2>
                    <p>Ajuda a enxergar rapidamente a distribuição das matrículas e vínculos de estudantes.</p>
                </div>
            </div>
            <div class="card-body">
                @if ($hasStudentsChart)
                    <div class="sge-chart-wrap sge-chart-wrap-wide" aria-hidden="true">
                        <canvas id="studentsBySchoolChart"></canvas>
                    </div>
                    <table class="table table-sm sge-accessible-chart-table mt-3">
                        <caption class="sr-only">Dados do gráfico de estudantes por escola</caption>
                        <thead>
                            <tr>
                                <th>Escola</th>
                                <th class="text-right">Estudantes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($studentsBySchoolChart['labels'] as $index => $label)
                                <tr>
                                    <td>{{ $label }}</td>
                                    <td class="text-right">{{ number_format($studentsBySchoolChart['values'][$index] ?? 0, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-muted mb-0">Ainda não há estudantes vinculados a escolas.</p>
                @endif
            </div>
        </section>

        <script src="{{ asset('template/vendor/chart.js/Chart.min.js') }}"></script>
        <script>
            const chartColors = ['#6B3D2E', '#44693D', '#DB6B30', '#4A86A0', '#F1C64E', '#7F56D9', '#2A9D8F'];
            const roleValues = @json($roleChart['values']);
            const calendarTypeValues = @json($calendarTypeChart['values']);
            const studentsBySchoolValues = @json($studentsBySchoolChart['values']);

            Chart.defaults.global.defaultFontFamily = "'Inter', 'Atkinson Hyperlegible', sans-serif";
            Chart.defaults.global.defaultFontColor = '#51443d';

            if (document.getElementById('rolesChart') && roleValues.some((value) => value > 0)) {
                new Chart(document.getElementById('rolesChart'), {
                    type: 'doughnut',
                    data: {
                        labels: @json($roleChart['labels']),
                        datasets: [{
                            data: roleValues,
                            backgroundColor: chartColors,
                            borderColor: '#fff',
                            borderWidth: 3,
                        }],
                    },
                    options: {
                        maintainAspectRatio: false,
                        legend: { display: false },
                        cutoutPercentage: 68,
                        tooltips: {
                            callbacks: {
                                label: (tooltipItem, data) => `${data.labels[tooltipItem.index]}: ${data.datasets[0].data[tooltipItem.index]}`,
                            },
                        },
                    },
                });
            }

            if (document.getElementById('calendarTypeChart') && calendarTypeValues.some((value) => value > 0)) {
                new Chart(document.getElementById('calendarTypeChart'), {
                    type: 'doughnut',
                    data: {
                        labels: @json($calendarTypeChart['labels']),
                        datasets: [{
                            data: calendarTypeValues,
                            backgroundColor: chartColors,
                            borderColor: '#fff',
                            borderWidth: 3,
                        }],
                    },
                    options: {
                        maintainAspectRatio: false,
                        legend: { display: false },
                        cutoutPercentage: 64,
                    },
                });
            }

            if (document.getElementById('studentsBySchoolChart') && studentsBySchoolValues.some((value) => value > 0)) {
                new Chart(document.getElementById('studentsBySchoolChart'), {
                    type: 'horizontalBar',
                    data: {
                        labels: @json($studentsBySchoolChart['labels']),
                        datasets: [{
                            label: 'Estudantes',
                            data: studentsBySchoolValues,
                            backgroundColor: '#44693D',
                            borderColor: '#355330',
                            borderWidth: 1,
                        }],
                    },
                    options: {
                        maintainAspectRatio: false,
                        legend: { display: false },
                        scales: {
                            xAxes: [{
                                ticks: {
                                    beginAtZero: true,
                                    precision: 0,
                                },
                                gridLines: { color: 'rgba(107, 61, 46, .08)' },
                            }],
                            yAxes: [{
                                gridLines: { display: false },
                            }],
                        },
                    },
                });
            }
        </script>
    @endcan
@endsection
