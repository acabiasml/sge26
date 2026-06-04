@extends('layouts.app')

@section('title', 'Início')
@section('page-title', 'Início')

@section('content')
    <div class="row">
        @can('manage-schools')
            <div class="col-xl-3 col-md-6 mb-4">
                <a class="card border-left-primary shadow h-100 py-2 text-decoration-none" href="{{ route('schools.index') }}">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Escolas ativas</div>
                                <div class="h4 mb-0 font-weight-bold text-gray-800">{{ number_format($schoolCount, 0, ',', '.') }}</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-school fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @endcan

        @can('manage-people')
            <div class="col-xl-3 col-md-6 mb-4">
                <a class="card border-left-primary shadow h-100 py-2 text-decoration-none" href="{{ route('people.index') }}">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Pessoas ativas</div>
                                <div class="h4 mb-0 font-weight-bold text-gray-800">{{ number_format($personCount, 0, ',', '.') }}</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-users fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Estudantes</div>
                                <div class="h4 mb-0 font-weight-bold text-gray-800">{{ number_format($roleCounts[\App\Models\PersonSchoolRole::ROLE_STUDENT], 0, ',', '.') }}</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-user-graduate fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <a class="card border-left-primary shadow h-100 py-2 text-decoration-none" href="{{ route('audit-logs.index') }}">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Controle</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">Auditoria</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-history fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @endcan
    </div>

    @can('manage-people')
        <div class="row">
            <div class="col-xl-5 col-lg-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h2 class="h6 m-0 font-weight-bold text-primary">Pessoas por papel</h2>
                    </div>
                    <div class="card-body">
                        @if (array_sum($roleChart['values']) > 0)
                            <div class="chart-pie pt-3 pb-2">
                                <canvas id="rolesChart"></canvas>
                            </div>
                        @else
                            <p class="text-gray-600 mb-0">Ainda não há vínculos ativos suficientes para montar o gráfico.</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-xl-7 col-lg-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h2 class="h6 m-0 font-weight-bold text-primary">Estudantes por escola</h2>
                    </div>
                    <div class="card-body">
                        @if (array_sum($studentsBySchoolChart['values']) > 0)
                            <div class="chart-bar">
                                <canvas id="studentsBySchoolChart"></canvas>
                            </div>
                        @else
                            <p class="text-gray-600 mb-0">Ainda não há estudantes vinculados a escolas.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-7">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h2 class="h6 m-0 font-weight-bold text-primary">Resumo de vínculos ativos</h2>
                    </div>
                    <div class="card-body">
                        @foreach ($roleChart['labels'] as $index => $label)
                            @php
                                $total = $roleChart['values'][$index] ?? 0;
                                $max = max($roleChart['values']) ?: 1;
                                $width = round(($total / $max) * 100);
                            @endphp
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="font-weight-bold text-gray-800">{{ $label }}</span>
                                    <span>{{ number_format($total, 0, ',', '.') }}</span>
                                </div>
                                <div class="progress" style="height: .65rem;">
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $width }}%;" aria-valuenow="{{ $width }}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h2 class="h6 m-0 font-weight-bold text-primary">Aniversariantes do mês</h2>
                    </div>
                    <div class="card-body">
                        @forelse ($birthdays as $person)
                            <div class="d-flex align-items-center justify-content-between border-bottom py-2">
                                <div>
                                    <div class="font-weight-bold text-gray-800">{{ $person->social_name ?: $person->full_name }}</div>
                                    <div class="small text-gray-600">{{ $person->institutional_email }}</div>
                                </div>
                                <span class="badge badge-primary">{{ $person->birth_date?->format('d/m') }}</span>
                            </div>
                        @empty
                            <p class="text-gray-600 mb-0">Nenhum aniversário cadastrado para este mês.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <script src="{{ asset('template/vendor/chart.js/Chart.min.js') }}"></script>
        <script>
            const chartColors = ['#7a3f27', '#5f7f3d', '#f37021', '#3f86a8', '#f6df8f'];
            const roleValues = @json($roleChart['values']);

            if (roleValues.some((value) => value > 0)) {
                new Chart(document.getElementById('rolesChart'), {
                    type: 'doughnut',
                    data: {
                        labels: @json($roleChart['labels']),
                        datasets: [{
                            data: roleValues,
                            backgroundColor: chartColors,
                            borderColor: '#fff',
                            borderWidth: 2,
                        }],
                    },
                    options: {
                        maintainAspectRatio: false,
                        legend: { position: 'bottom' },
                        cutoutPercentage: 62,
                    },
                });
            }

            const studentsCanvas = document.getElementById('studentsBySchoolChart');
            if (studentsCanvas) {
                new Chart(studentsCanvas, {
                    type: 'bar',
                    data: {
                        labels: @json($studentsBySchoolChart['labels']),
                        datasets: [{
                            label: 'Estudantes',
                            data: @json($studentsBySchoolChart['values']),
                            backgroundColor: '#5f7f3d',
                            borderColor: '#4f6d32',
                            borderWidth: 1,
                        }],
                    },
                    options: {
                        maintainAspectRatio: false,
                        legend: { display: false },
                        scales: {
                            yAxes: [{
                                ticks: {
                                    beginAtZero: true,
                                    precision: 0,
                                },
                            }],
                        },
                    },
                });
            }
        </script>
    @endcan
@endsection
