@extends('layouts.app')

@php($canChangeAcademicStructure = ! $academicYear->approved_at || auth()->user()->isAdministrator())
@php($curriculumSuggestionsByComponent = collect($curriculumSuggestions)->keyBy('component'))

@section('title', 'Matriz - '.$course->name)
@section('page-title', 'Matriz: '.$course->name)

@section('page-actions')
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('academic-years.courses.matrix-pdf', [$academicYear, $course]) }}" aria-label="Imprimir matriz {{ $course->name }} em PDF" title="Imprimir matriz">
        <i class="fas fa-file-pdf" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('academic-years.show', $academicYear) }}" aria-label="Voltar ao ano letivo {{ $academicYear->name }}" title="Voltar ao ano letivo">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
    @if ($canChangeAcademicStructure)
        <a class="btn btn-sm btn-primary shadow-sm sge-icon-action" href="{{ route('academic-years.courses.edit', [$academicYear, $course]) }}" aria-label="Editar matriz {{ $course->name }}" title="Editar matriz">
            <i class="fas fa-pen" aria-hidden="true"></i>
        </a>
    @endif
@endsection

@section('content')
    <x-academic-trail :school="$academicYear->school" :academic-year="$academicYear" :course="$course" />

    <x-structure-validation :issues="$structureIssues" title="Validação da matriz" empty="Matriz sem inconsistências estruturais." />

    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h2 class="h6 m-0 font-weight-bold text-primary">Resumo da matriz</h2>
                </div>
                <div class="card-body">
                    <dl class="mb-0">
                        <dt>Escola</dt>
                        <dd>{{ $academicYear->school?->name }}</dd>
                        <dt>Ano letivo</dt>
                        <dd>{{ $academicYear->name }}</dd>
                        <dt>Etapa</dt>
                        <dd>{{ $course->stageLabel() }}</dd>
                        <dt>Modalidade</dt>
                        <dd>{{ $course->modalityLabel() ?: '-' }}</dd>
                        <dt>Hora-aula</dt>
                        <dd>{{ $course->class_hour_minutes }} minutos</dd>
                        <dt>Carga horária calculada</dt>
                        <dd>{{ $course->formattedCalculatedWorkloadHours() }} horas</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-8 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h2 class="h6 m-0 font-weight-bold text-primary">Novo componente curricular</h2>
                </div>
                <div class="card-body">
                    @if ($canChangeAcademicStructure)
                        <form method="POST" action="{{ route('academic-years.courses.components.store', [$academicYear, $course]) }}">
                            @csrf
                            <div class="row">
                                <div class="col-md-4 form-group">
                                    <label for="new_component_name">Componente</label>
                                    <input id="new_component_name" name="name" class="form-control" placeholder="Língua Portuguesa" list="curriculum-component-suggestions" data-curriculum-component-name required>
                                    <datalist id="curriculum-component-suggestions">
                                        @foreach ($curriculumSuggestions as $suggestion)
                                            <option value="{{ $suggestion['component'] }}">{{ $suggestion['area'] }}</option>
                                        @endforeach
                                    </datalist>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="new_component_area_id">Área</label>
                                    <select id="new_component_area_id" name="knowledge_area_id" class="form-control" data-curriculum-area-select>
                                        <option value="">Não definida</option>
                                        @foreach ($knowledgeAreas as $area)
                                            <option value="{{ $area->id }}">{{ $area->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 form-group" data-workload-choice>
                                    <label class="d-block">Como informar a carga horária?</label>
                                    <div class="custom-control custom-radio custom-control-inline">
                                        <input class="custom-control-input" type="radio" id="new_workload_mode_weekly" name="workload_mode" value="weekly_lessons" @checked(old('workload_mode', 'weekly_lessons') === 'weekly_lessons') required>
                                        <label class="custom-control-label" for="new_workload_mode_weekly">Aulas por semana</label>
                                    </div>
                                    <div class="custom-control custom-radio custom-control-inline">
                                        <input class="custom-control-input" type="radio" id="new_workload_mode_total" name="workload_mode" value="workload_hours" @checked(old('workload_mode') === 'workload_hours') required>
                                        <label class="custom-control-label" for="new_workload_mode_total">Total de horas</label>
                                    </div>
                                    <div class="mt-2" data-workload-field="weekly_lessons">
                                        <label for="new_component_weekly_lessons">Aulas por semana</label>
                                        <input id="new_component_weekly_lessons" name="weekly_lessons" data-mask="digits" data-mask-max="2" inputmode="numeric" autocomplete="off" class="form-control" value="{{ old('weekly_lessons') }}">
                                    </div>
                                    <div class="mt-2" data-workload-field="workload_hours">
                                        <label for="new_component_workload_hours">Carga horária total</label>
                                        <div class="input-group"><input id="new_component_workload_hours" name="workload_hours" data-mask="digits" data-mask-max="5" inputmode="numeric" autocomplete="off" class="form-control" value="{{ old('workload_hours') }}"><div class="input-group-append"><span class="input-group-text">horas</span></div></div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 form-group">
                                    <label for="new_component_starts_period_id">Período inicial</label>
                                    <select id="new_component_starts_period_id" name="starts_period_id" class="form-control">
                                        <option value="">Desde o início da turma</option>
                                        @foreach ($academicYear->periods as $period)
                                            <option value="{{ $period->id }}">{{ $period->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="new_component_ends_period_id">Período final</label>
                                    <select id="new_component_ends_period_id" name="ends_period_id" class="form-control">
                                        <option value="">Até o fim da turma</option>
                                        @foreach ($academicYear->periods as $period)
                                            <option value="{{ $period->id }}">{{ $period->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="new_component_notes">Observações</label>
                                    <input id="new_component_notes" name="notes" class="form-control">
                                </div>
                            </div>
                            <input type="hidden" name="active" value="1">
                            <button class="btn btn-primary" type="submit">Adicionar componente</button>
                        </form>
                    @else
                        <p class="mb-0 text-gray-600">Ano letivo aprovado. A matriz está bloqueada para edição estrutural.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h2 class="h6 m-0 font-weight-bold text-primary">Componentes curriculares</h2>
        </div>
        <div class="card-body">
            @forelse ($course->componentsGroupedByArea() as $group)
                <section class="border rounded mb-3">
                    <div class="bg-light border-bottom px-3 py-2">
                        <h3 class="h6 mb-0 text-primary">{{ $group['area'] }}</h3>
                    </div>
                    <div class="list-group list-group-flush">
                        @foreach ($group['components'] as $component)
                            <div class="list-group-item d-flex align-items-center justify-content-between flex-wrap">
                                <div class="mr-3">
                                    <strong>{{ $component->name }}</strong>
                                    <div class="small text-gray-600">
                                        {{ $component->formattedCalculatedWorkloadHours($course) }} horas
                                        @if ($component->weekly_lessons !== null)
                                            · {{ $component->weekly_lessons }} aulas semanais
                                        @endif
                                        · {{ $component->startsPeriod?->name ?? 'início da turma' }} até {{ $component->endsPeriod?->name ?? 'fim da turma' }}
                                    </div>
                                </div>
                                <div class="sge-action-buttons mt-2 mt-md-0" aria-label="Ações do componente {{ $component->name }}">
                                    <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('academic-years.courses.components.show', [$academicYear, $course, $component]) }}" aria-label="Gerenciar componente {{ $component->name }}" title="Gerenciar componente">
                                        <i class="fas fa-cog" aria-hidden="true"></i>
                                    </a>
                                    @if ($canChangeAcademicStructure)
                                        <form method="POST" action="{{ route('academic-years.courses.components.destroy', [$academicYear, $course, $component]) }}" onsubmit="return confirm('Remover este componente?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger sge-icon-action" type="submit" aria-label="Remover componente {{ $component->name }}" title="Remover componente">
                                                <i class="fas fa-trash-alt" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @empty
                <p class="mb-0">Nenhum componente cadastrado. A turma só poderá ser criada depois que a matriz tiver ao menos um componente ativo.</p>
            @endforelse
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const curriculumSuggestions = @json($curriculumSuggestionsByComponent);
        const componentInput = document.querySelector('[data-curriculum-component-name]');
        const areaSelect = document.querySelector('[data-curriculum-area-select]');

        const normalizeCurriculumText = (value) => value.trim().replace(/\s+/g, ' ').toLocaleLowerCase('pt-BR');
        const suggestionEntries = Object.values(curriculumSuggestions);

        componentInput?.addEventListener('input', () => {
            const selectedSuggestion = suggestionEntries.find((suggestion) => {
                return normalizeCurriculumText(suggestion.component) === normalizeCurriculumText(componentInput.value);
            });

            if (selectedSuggestion?.area_id && areaSelect) {
                areaSelect.value = selectedSuggestion.area_id;
            }
        });

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
