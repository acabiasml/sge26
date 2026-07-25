@extends('layouts.app')

@section('title', 'Matrículas')
@section('page-title', 'Matrículas')

@section('page-actions')
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('data-quality.index') }}" aria-label="Abrir conformidade antes de matricular" title="Conformidade">
        <i class="fas fa-clipboard-check" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    <section class="sge-flow-hero mb-4" aria-labelledby="enrollment-overview-title">
        <div>
            <div class="sge-page-kicker">Rotina acadêmica</div>
            <h2 id="enrollment-overview-title">Escolha a turma para gerenciar matrículas</h2>
            <p>
                Matrículas, transferências, reclassificações e fichas físicas ficam dentro da turma.
                Pessoas com bloqueio de conformidade precisam ser regularizadas antes de novas emissões oficiais.
            </p>
        </div>
        <a class="btn btn-primary" href="{{ route('people.index') }}">
            <i class="fas fa-user-plus mr-1" aria-hidden="true"></i>
            Localizar estudante
        </a>
    </section>

    @forelse ($schools as $school)
        @php($yearsWithClasses = $school->academicYears->filter(fn ($year) => $year->classes->isNotEmpty()))

        <section class="card shadow mb-4" aria-labelledby="school-enrollments-{{ $school->id }}">
            <div class="card-header py-3 d-flex align-items-center justify-content-between flex-wrap">
                <div>
                    <h2 id="school-enrollments-{{ $school->id }}" class="h5 mb-1 text-primary font-weight-bold">{{ $school->name }}</h2>
                    <p class="small text-gray-600 mb-0">
                        {{ $yearsWithClasses->count() }} ano(s) letivo(s) com turma ·
                        {{ $yearsWithClasses->flatMap->classes->count() }} turma(s)
                    </p>
                </div>
                <a class="btn btn-sm btn-outline-primary sge-icon-action mt-2 mt-md-0" href="{{ route('schools.academic-years.index', $school) }}" aria-label="Abrir anos letivos de {{ $school->name }}" title="Anos letivos">
                    <i class="fas fa-calendar-alt" aria-hidden="true"></i>
                </a>
            </div>
            <div class="card-body">
                @forelse ($yearsWithClasses as $year)
                    <article class="sge-enrollment-year">
                        <header class="sge-enrollment-year-header">
                            <div>
                                <span class="sge-page-kicker">Ano letivo</span>
                                <h3>{{ $year->name }}</h3>
                                <p>{{ $year->starts_at?->format('d/m/Y') }} a {{ $year->ends_at?->format('d/m/Y') }}</p>
                            </div>
                            <div class="sge-enrollment-year-stats" aria-label="Resumo de {{ $year->name }}">
                                <span><strong>{{ $year->classes->count() }}</strong> turma(s)</span>
                                <span><strong>{{ $year->classes->sum(fn ($class) => $class->enrollments->where('status', \App\Models\StudentEnrollment::STATUS_ENROLLED)->count()) }}</strong> ativa(s)</span>
                            </div>
                        </header>

                        <div class="sge-enrollment-class-grid">
                            @foreach ($year->classes->sortBy('name') as $class)
                                @php($activeEnrollmentCount = $class->enrollments->where('status', \App\Models\StudentEnrollment::STATUS_ENROLLED)->count())
                                <article class="sge-enrollment-class-card">
                                    <div class="sge-enrollment-class-main">
                                        <h4>{{ $class->name }}</h4>
                                        <p>{{ $class->shift ?: 'Turno não definido' }}</p>
                                        <div class="sge-class-course-chips">
                                            @forelse ($class->courses as $course)
                                                <span>
                                                    <strong>{{ $course->name }}</strong>
                                                    <small>{{ $course->stageLabel() }}</small>
                                                </span>
                                            @empty
                                                <span>
                                                    <strong>Sem matriz</strong>
                                                    <small>Regularize antes de matricular</small>
                                                </span>
                                            @endforelse
                                        </div>
                                    </div>
                                    <div class="sge-enrollment-class-side">
                                        <strong>{{ $activeEnrollmentCount }}</strong>
                                        <span>ativa(s)</span>
                                        @if ($class->enrollments->count() !== $activeEnrollmentCount)
                                            <small>{{ $class->enrollments->count() }} no histórico</small>
                                        @endif
                                        <a class="btn btn-sm btn-primary mt-2" href="{{ route('classes.enrollments.index', $class) }}">
                                            <i class="fas fa-user-graduate mr-1" aria-hidden="true"></i>
                                            Gerenciar
                                        </a>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </article>
                @empty
                    <div class="sge-empty-state">
                        <i class="fas fa-users" aria-hidden="true"></i>
                        <p>Nenhuma turma com matriz curricular encontrada nesta escola.</p>
                    </div>
                @endforelse
            </div>
        </section>
    @empty
        <section class="card shadow">
            <div class="card-body">
                <div class="sge-empty-state">
                    <i class="fas fa-school" aria-hidden="true"></i>
                    <p>Nenhuma escola disponível para matrícula.</p>
                </div>
            </div>
        </section>
    @endforelse
@endsection
