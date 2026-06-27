@extends('layouts.app')

@section('title', 'Matrículas')
@section('page-title', 'Matrículas')

@section('content')
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h2 class="h6 m-0 font-weight-bold text-primary">Turmas disponíveis para matrícula</h2>
        </div>
        <div class="card-body">
            <p class="text-muted mb-4">
                Escolha uma turma para cadastrar, transferir, reclassificar ou emitir fichas de matrícula.
            </p>

            @forelse ($schools as $school)
                <section class="mb-4">
                    <div class="d-flex align-items-center justify-content-between flex-wrap mb-2">
                        <h3 class="h5 mb-2 mb-md-0">{{ $school->name }}</h3>
                        <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('schools.academic-years.index', $school) }}" aria-label="Abrir anos letivos de {{ $school->name }}" title="Anos letivos">
                            <i class="fas fa-calendar-alt" aria-hidden="true"></i>
                        </a>
                    </div>

                    @php($yearsWithClasses = $school->academicYears->filter(fn ($year) => $year->classes->isNotEmpty()))

                    @forelse ($yearsWithClasses as $year)
                        <div class="border rounded mb-3">
                            <div class="px-3 py-2 bg-light border-bottom">
                                <strong>{{ $year->name }}</strong>
                                <span class="text-muted small ml-2">{{ $year->starts_at?->format('d/m/Y') }} a {{ $year->ends_at?->format('d/m/Y') }}</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Turma</th>
                                            <th>Matrizes ativas</th>
                                            <th>Matrículas</th>
                                            <th class="text-right">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($year->classes as $class)
                                            <tr>
                                                <td>{{ $class->name }}</td>
                                                <td>{{ $class->courses->pluck('name')->join(' + ') ?: '-' }}</td>
                                                @php($activeEnrollmentCount = $class->enrollments->where('status', \App\Models\StudentEnrollment::STATUS_ENROLLED)->count())
                                                <td>
                                                    <strong>{{ $activeEnrollmentCount }} ativa(s)</strong>
                                                    @if ($class->enrollments->count() !== $activeEnrollmentCount)
                                                        <span class="d-block small text-muted">{{ $class->enrollments->count() }} no histórico</span>
                                                    @endif
                                                </td>
                                                <td class="text-right">
                                                    <a class="btn btn-sm btn-primary sge-icon-action" href="{{ route('classes.enrollments.index', $class) }}" aria-label="Gerenciar matrículas da turma {{ $class->name }}" title="Gerenciar matrículas">
                                                        <i class="fas fa-user-graduate" aria-hidden="true"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted">Nenhuma turma ativa com matriz ativa encontrada nesta escola.</p>
                    @endforelse
                </section>
            @empty
                <p class="mb-0">Nenhuma escola disponível para matrícula.</p>
            @endforelse
        </div>
    </div>
@endsection
