@extends('layouts.app')

@php($canChangeAcademicStructure = ! $academicYear->approved_at || auth()->user()->isAdministrator())

@section('title', 'Componente - '.$component->name)
@section('page-title', 'Componente: '.$component->name)

@section('page-actions')
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('academic-years.courses.show', [$academicYear, $course]) }}" aria-label="Voltar à matriz {{ $course->name }}" title="Voltar à matriz">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
@endsection

@push('scripts')
    <script>
        const resetCurriculumComponentScroll = () => {
            requestAnimationFrame(() => window.scrollTo({ top: 0, left: 0, behavior: 'auto' }));
        };

        window.addEventListener('load', resetCurriculumComponentScroll, { once: true });
        window.addEventListener('pageshow', resetCurriculumComponentScroll, { once: true });
    </script>
@endpush

@section('content')
    <nav class="sge-section-nav sge-academic-tabs mb-4" aria-label="Áreas do componente curricular" role="tablist" data-section-tabs data-default-tab="{{ $errors->any() ? 'edicao' : 'resumo' }}">
        <a href="#section-resumo" class="sge-section-nav-item" data-academic-tab="resumo" role="tab"><i class="fas fa-clipboard-list"></i><span>Resumo</span><small>dados do componente</small></a>
        <a href="#section-edicao" class="sge-section-nav-item" data-academic-tab="edicao" role="tab"><i class="fas fa-pen"></i><span>Edição</span><small>configuração curricular</small></a>
    </nav>

    <div class="row">
        <div id="section-resumo" class="col-12 mb-4 sge-anchor-section" data-academic-panel="resumo" role="tabpanel">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h2 class="h6 m-0 font-weight-bold text-primary">Resumo</h2>
                </div>
                <div class="card-body">
                    <dl class="mb-0 sge-academic-summary-details">
                        <dt>Componente</dt>
                        <dd>{{ $component->name }}</dd>
                        <dt>Escola</dt>
                        <dd>{{ $academicYear->school?->name }}</dd>
                        <dt>Ano letivo</dt>
                        <dd>{{ $academicYear->name }} · {{ $academicYear->referenceYearsLabel() }}</dd>
                        <dt>Matriz</dt>
                        <dd>{{ $course->name }} · {{ $course->stageLabel() }} · {{ $course->modalityLabel() }}</dd>
                        <dt>Formação</dt>
                        <dd>{{ \App\Support\CurriculumCatalog::formationLabelForArea($course, $component->area) }}</dd>
                        <dt>Área</dt>
                        <dd>{{ $component->area?->name ?? 'Não definida' }}</dd>
                        <dt>Situação</dt>
                        <dd><span class="badge badge-{{ $component->active ? 'success' : 'secondary' }}">{{ $component->active ? 'Ativo' : 'Inativo' }}</span></dd>
                        <dt>Duração</dt>
                        <dd>{{ $component->startsPeriod?->name ?? 'início da turma' }} até {{ $component->endsPeriod?->name ?? 'fim da turma' }}</dd>
                        <dt>Carga horária</dt>
                        <dd>{{ $component->formattedCalculatedWorkloadHours($course) }} horas</dd>
                        <dt>Forma de definição</dt>
                        <dd>{{ $component->weekly_lessons !== null ? $component->weekly_lessons.' aulas por semana de '.$course->class_hour_minutes.' minutos' : number_format((float) $component->workload_hours, 2, ',', '.').' horas totais informadas' }}</dd>
                        <dt>Observações</dt>
                        <dd>{{ $component->notes ?: 'Nenhuma observação cadastrada' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div id="section-edicao" class="col-12 mb-4 sge-anchor-section" data-academic-panel="edicao" role="tabpanel">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h2 class="h6 m-0 font-weight-bold text-primary">Gerenciar componente</h2>
                </div>
                <div class="card-body">
                    @if ($canChangeAcademicStructure)
                        <form method="POST" action="{{ route('academic-years.courses.components.update', [$academicYear, $course, $component]) }}">
                            @csrf
                            @method('PUT')
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label for="component_name">Componente</label>
                                    <input id="component_name" name="name" class="form-control" value="{{ old('name', $component->name) }}" required>
                                </div>
                                <div class="col-md-6 form-group" data-workload-choice>
                                    @php($workloadMode = old('workload_mode', $component->weekly_lessons !== null ? 'weekly_lessons' : 'workload_hours'))
                                    <label class="d-block">Como informar a carga horária?</label>
                                    <div class="custom-control custom-radio custom-control-inline">
                                        <input class="custom-control-input" type="radio" id="component_workload_mode_weekly" name="workload_mode" value="weekly_lessons" @checked($workloadMode === 'weekly_lessons') required>
                                        <label class="custom-control-label" for="component_workload_mode_weekly">Aulas por semana</label>
                                    </div>
                                    <div class="custom-control custom-radio custom-control-inline">
                                        <input class="custom-control-input" type="radio" id="component_workload_mode_total" name="workload_mode" value="workload_hours" @checked($workloadMode === 'workload_hours') required>
                                        <label class="custom-control-label" for="component_workload_mode_total">Total de horas</label>
                                    </div>
                                    <div class="mt-2" data-workload-field="weekly_lessons">
                                        <label for="component_weekly_lessons">Aulas por semana</label>
                                        <input id="component_weekly_lessons" name="weekly_lessons" data-mask="digits" data-mask-max="2" inputmode="numeric" autocomplete="off" class="form-control" value="{{ old('weekly_lessons', $component->weekly_lessons) }}">
                                    </div>
                                    <div class="mt-2" data-workload-field="workload_hours">
                                        <label for="component_workload_hours">Carga horária total</label>
                                        <div class="input-group"><input id="component_workload_hours" name="workload_hours" data-mask="digits" data-mask-max="5" inputmode="numeric" autocomplete="off" class="form-control" value="{{ old('workload_hours', $component->workload_hours) }}"><div class="input-group-append"><span class="input-group-text">horas</span></div></div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 form-group">
                                    <label for="component_area">Área</label>
                                    <input id="component_area" class="form-control" value="{{ $component->area?->name ?? 'Não definida' }}" disabled>
                                    <input type="hidden" name="knowledge_area_id" value="{{ $component->knowledge_area_id }}">
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="component_starts_period_id">Período inicial</label>
                                    <select id="component_starts_period_id" name="starts_period_id" class="form-control">
                                        <option value="">Desde o início da turma</option>
                                        @foreach ($periods as $period)
                                            <option value="{{ $period->id }}" @selected((int) old('starts_period_id', $component->starts_period_id) === $period->id)>{{ $period->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="component_ends_period_id">Período final</label>
                                    <select id="component_ends_period_id" name="ends_period_id" class="form-control">
                                        <option value="">Até o fim da turma</option>
                                        @foreach ($periods as $period)
                                            <option value="{{ $period->id }}" @selected((int) old('ends_period_id', $component->ends_period_id) === $period->id)>{{ $period->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="component_notes">Observações</label>
                                <textarea id="component_notes" name="notes" class="form-control" rows="3">{{ old('notes', $component->notes) }}</textarea>
                            </div>
                            <input type="hidden" name="active" value="1">
                            <button class="btn btn-primary" type="submit">Salvar componente</button>
                        </form>
                    @else
                        <p class="mb-0 text-gray-600">Ano letivo aprovado. O componente está bloqueado para edição estrutural.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-workload-choice]').forEach((choice) => {
            const syncWorkloadChoice = () => {
                const mode = choice.querySelector('input[name="workload_mode"]:checked')?.value;
                choice.querySelectorAll('[data-workload-field]').forEach((field) => {
                    const active = field.dataset.workloadField === mode;
                    field.hidden = !active;
                    field.querySelector('input').disabled = !active;
                    field.querySelector('input').required = active;
                });
            };
            choice.querySelectorAll('input[name="workload_mode"]').forEach((radio) => radio.addEventListener('change', syncWorkloadChoice));
            syncWorkloadChoice();
        });
    </script>
@endpush
