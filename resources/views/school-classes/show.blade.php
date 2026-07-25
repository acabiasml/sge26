@extends('layouts.app')

@php($canChangeAcademicStructure = ! $academicYear->approved_at || auth()->user()->isAdministrator())
@php($enrollmentCount = $class->enrollments->count())
@php($assignments = $class->componentAssignments->sortBy(fn ($assignment) => ($assignment->component?->area?->name ?? '').' '.$assignment->component?->name)->values())
@php($assignmentGroups = $assignments->groupBy(fn ($assignment) => $assignment->component?->area?->name ?? 'Área não definida'))
@php($activeAssignments = $assignments->where('active', true)->count())

@section('title', 'Turma - '.$class->name)
@section('page-title', 'Turma: '.$class->name)

@section('page-actions')
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('academic-years.show', $academicYear) }}" aria-label="Voltar ao ano letivo {{ $academicYear->name }}" title="Voltar ao ano letivo">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('classes.enrollments.index', $class) }}" aria-label="Gerenciar matrículas da turma {{ $class->name }}" title="Matrículas">
        <i class="fas fa-user-graduate" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('academic-years.classes.schedules.index', [$academicYear, $class]) }}" aria-label="Gerenciar horários da turma {{ $class->name }}" title="Horários">
        <i class="fas fa-clock" aria-hidden="true"></i>
    </a>
    @if ($canChangeAcademicStructure)
        <a class="btn btn-sm btn-primary shadow-sm sge-icon-action" href="{{ route('academic-years.classes.edit', [$academicYear, $class]) }}" aria-label="Editar turma {{ $class->name }}" title="Editar turma">
            <i class="fas fa-pen" aria-hidden="true"></i>
        </a>
    @endif
@endsection

@section('content')
    <x-academic-trail :school="$academicYear->school" :academic-year="$academicYear" :class="$class" />

    <x-structure-validation :issues="$structureIssues" title="Validação da turma" empty="Turma sem inconsistências estruturais." />

    <div class="row">
        <div class="col-xl-8 mb-4">
            <section class="card shadow h-100 sge-class-hero" aria-labelledby="class-summary-title">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start flex-wrap mb-3">
                        <div class="mr-3">
                            <span class="sge-eyebrow">Turma</span>
                            <h2 id="class-summary-title" class="h4 mb-1">{{ $class->name }}</h2>
                            <p class="mb-0 text-gray-700">{{ $academicYear->school?->name }} · {{ $academicYear->name }}</p>
                        </div>
                        <span class="badge badge-{{ $classStatus['tone'] }} mt-2" title="{{ $classStatus['description'] }}">{{ $classStatus['label'] }}</span>
                    </div>

                    <div class="sge-class-metrics" aria-label="Resumo da turma">
                        <div><strong>{{ $enrollmentCount }}</strong><span>matrículas</span></div>
                        <div><strong>{{ $class->courses->count() }}</strong><span>matrizes</span></div>
                        <div><strong>{{ $assignments->count() }}</strong><span>componentes</span></div>
                        <div><strong>{{ $activeAssignments }}</strong><span>docências ativas</span></div>
                    </div>

                    <dl class="sge-inline-definition-list mb-0">
                        <div><dt>Turno</dt><dd>{{ $class->shift ?: '-' }}</dd></div>
                        <div><dt>Datas da turma</dt><dd>{{ $class->starts_at?->format('d/m/Y') ?? '-' }} a {{ $class->ends_at?->format('d/m/Y') ?? '-' }}</dd></div>
                        <div><dt>Períodos avaliativos</dt><dd>{{ $class->startsPeriod?->name ?? 'início do ano letivo' }} até {{ $class->endsPeriod?->name ?? 'fim do ano letivo' }}</dd></div>
                        <div><dt>Critérios</dt><dd>{{ number_format((float) $academicYear->passing_points, 1, ',', '.') }} pontos · {{ $academicYear->minimum_attendance_percentage }}% frequência</dd></div>
                    </dl>
                </div>
            </section>
        </div>

        <div class="col-xl-4 mb-4">
            <section class="card shadow h-100" aria-labelledby="class-actions-title">
                <div class="card-header py-3">
                    <h2 id="class-actions-title" class="h6 m-0 font-weight-bold text-primary">Ações rápidas</h2>
                </div>
                <div class="card-body">
                    <div class="sge-class-action-grid">
                        <a href="{{ route('classes.enrollments.index', $class) }}">
                            <i class="fas fa-user-graduate" aria-hidden="true"></i>
                            <span>Matrículas</span>
                        </a>
                        <a href="{{ route('academic-years.classes.schedules.index', [$academicYear, $class]) }}">
                            <i class="fas fa-clock" aria-hidden="true"></i>
                            <span>Horários</span>
                        </a>
                        @if ($canChangeAcademicStructure)
                            <a href="{{ route('academic-years.classes.edit', [$academicYear, $class]) }}">
                                <i class="fas fa-pen" aria-hidden="true"></i>
                                <span>Editar turma</span>
                            </a>
                        @endif
                    </div>

                    <hr>
                    <h3 class="h6 font-weight-bold">Matrizes vinculadas</h3>
                    <div class="sge-class-course-chips">
                        @forelse ($class->courses->sortBy('name') as $course)
                            <span><strong>{{ $course->name }}</strong><small>{{ $course->stageLabel() }} · {{ $course->modalityLabel() }}</small></span>
                        @empty
                            <p class="mb-0 text-gray-600">Nenhuma matriz vinculada.</p>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex align-items-center justify-content-between flex-wrap">
            <div>
                <h2 class="h6 m-0 font-weight-bold text-primary">Docências da turma</h2>
                <p class="small text-muted mb-0 mt-1">Componentes agrupados por área. Abra apenas o que precisar ajustar.</p>
            </div>
            <span class="badge badge-light border mt-2 mt-md-0">{{ $assignments->count() }} componentes</span>
        </div>
        <div class="card-body">
            @forelse ($assignmentGroups as $areaName => $areaAssignments)
                <section class="sge-class-area-group" aria-labelledby="area-{{ \Illuminate\Support\Str::slug($areaName) }}">
                    <div class="sge-class-area-heading">
                        <h3 id="area-{{ \Illuminate\Support\Str::slug($areaName) }}">{{ $areaName }}</h3>
                        <span>{{ $areaAssignments->count() }} componente(s)</span>
                    </div>

                    <div class="sge-class-component-list">
                        @foreach ($areaAssignments as $assignment)
                            <details class="sge-class-component-card">
                                <summary>
                                    <span class="sge-component-main">
                                        <strong>{{ $assignment->component?->name }}</strong>
                                        <small>{{ $assignment->component?->course?->name }} · {{ $assignment->teacher?->full_name ?? 'Docência não definida' }}</small>
                                    </span>
                                    <span class="sge-component-badges">
                                        @if($assignment->substitutions->isNotEmpty())
                                            <span class="badge badge-info">{{ $assignment->substitutions->count() }} substituição(ões)</span>
                                        @endif
                                        <span class="badge badge-{{ $assignment->active ? 'success' : 'secondary' }}">{{ $assignment->active ? 'Ativa' : 'Inativa' }}</span>
                                        <i class="fas fa-chevron-down" aria-hidden="true"></i>
                                    </span>
                                </summary>

                                <div class="sge-class-component-body">
                                    <form method="POST" action="{{ route('academic-years.classes.components.update', [$academicYear, $class, $assignment]) }}" class="mb-3">
                                        @csrf
                                        @method('PUT')
                                        <div class="row align-items-end">
                                            <div class="col-lg-7 form-group">
                                                <label for="teacher_person_id_{{ $assignment->id }}">Docência titular</label>
                                                <select id="teacher_person_id_{{ $assignment->id }}" name="teacher_person_id" class="form-control" @disabled(! $canChangeAcademicStructure)>
                                                    <option value="">Definir depois</option>
                                                    @foreach ($teachers as $teacher)
                                                        <option value="{{ $teacher->id }}" @selected((int) old('teacher_person_id', $assignment->teacher_person_id) === $teacher->id)>{{ $teacher->full_name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-lg-3 form-group">
                                                <div class="custom-control custom-checkbox mt-2">
                                                    <input class="custom-control-input" id="assignment_active_{{ $assignment->id }}" name="active" type="checkbox" value="1" @checked(old('active', $assignment->active)) @disabled(! $canChangeAcademicStructure)>
                                                    <label class="custom-control-label" for="assignment_active_{{ $assignment->id }}">Docência ativa</label>
                                                </div>
                                            </div>
                                            @if ($canChangeAcademicStructure)
                                                <div class="col-lg-2 form-group">
                                                    <button class="btn btn-primary btn-block" type="submit"><i class="fas fa-save mr-1" aria-hidden="true"></i>Salvar</button>
                                                </div>
                                            @endif
                                        </div>
                                    </form>

                                    <h4 class="h6 font-weight-bold">Substituições docentes</h4>
                                    <div class="table-responsive mb-3">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Substituição</th>
                                                    <th>Início</th>
                                                    <th>Fim</th>
                                                    <th>Observações</th>
                                                    <th class="text-right">Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse ($assignment->substitutions->sortBy('starts_at') as $substitution)
                                                    <tr>
                                                        <td>{{ $substitution->substituteTeacher?->full_name }}</td>
                                                        <td>{{ $substitution->starts_at?->format('d/m/Y') }}</td>
                                                        <td>{{ $substitution->ends_at?->format('d/m/Y') ?? 'Indeterminado' }}</td>
                                                        <td>{{ $substitution->notes ?: '-' }}</td>
                                                        <td class="text-right">
                                                            @if ($canChangeAcademicStructure)
                                                                <form method="POST" action="{{ route('academic-years.classes.components.substitutions.destroy', [$academicYear, $class, $assignment, $substitution]) }}" onsubmit="return confirm('Remover esta substituição?')">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button class="btn btn-sm btn-outline-danger sge-icon-action" type="submit" aria-label="Remover substituição docente de {{ $substitution->substituteTeacher?->full_name }}" title="Remover substituição">
                                                                        <i class="fas fa-trash-alt" aria-hidden="true"></i>
                                                                    </button>
                                                                </form>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="5">Nenhuma substituição cadastrada.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>

                                    @if ($canChangeAcademicStructure)
                                        <form method="POST" action="{{ route('academic-years.classes.components.substitutions.store', [$academicYear, $class, $assignment]) }}" class="sge-substitution-form">
                                            @csrf
                                            <div class="row">
                                                <div class="col-lg-4 form-group">
                                                    <label for="substitute_teacher_person_id_{{ $assignment->id }}">Docência substituta</label>
                                                    <select id="substitute_teacher_person_id_{{ $assignment->id }}" name="substitute_teacher_person_id" class="form-control" required>
                                                        <option value="">Selecione</option>
                                                        @foreach ($teachers as $teacher)
                                                            <option value="{{ $teacher->id }}">{{ $teacher->full_name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-lg-3 form-group">
                                                    <label for="substitution_starts_at_{{ $assignment->id }}">Início</label>
                                                    <input id="substitution_starts_at_{{ $assignment->id }}" name="starts_at" type="date" class="form-control" required>
                                                </div>
                                                <div class="col-lg-3 form-group">
                                                    <label for="substitution_ends_at_{{ $assignment->id }}">Fim</label>
                                                    <input id="substitution_ends_at_{{ $assignment->id }}" name="ends_at" type="date" class="form-control">
                                                </div>
                                                <div class="col-lg-2 form-group d-flex align-items-end">
                                                    <button class="btn btn-primary btn-block" type="submit"><i class="fas fa-plus mr-1" aria-hidden="true"></i>Adicionar</button>
                                                </div>
                                            </div>
                                            <label class="sr-only" for="substitution_notes_{{ $assignment->id }}">Observações da substituição</label>
                                            <input id="substitution_notes_{{ $assignment->id }}" name="notes" class="form-control" placeholder="Observações da substituição">
                                        </form>
                                    @endif
                                </div>
                            </details>
                        @endforeach
                    </div>
                </section>
            @empty
                <p class="mb-0 text-gray-600">Nenhum componente disponível para esta turma.</p>
            @endforelse
        </div>
    </div>
@endsection
