@extends('layouts.app')

@php($canChangeAcademicStructure = ! $academicYear->approved_at || auth()->user()->isAdministrator())
@php($curriculumSuggestionsByComponent = collect($curriculumSuggestions)->keyBy('component'))
@php($activeComponents = $course->components->where('active', true))
@php($linkedEnrollmentCount = $course->classes->sum(fn ($class) => $class->enrollments->count()))

@section('title', __('Matriz - ').$course->name)
@section('page-title', __('Matriz: ').$course->name)

@section('page-actions')
    <a target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('academic-years.courses.matrix-pdf', [$academicYear, $course]) }}" aria-label="{{ __('Imprimir matriz') }} {{ $course->name }} {{ __('em PDF') }}" title="{{ __('Imprimir matriz') }}">
        <i class="fas fa-file-pdf" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('academic-years.show', $academicYear) }}" aria-label="{{ __('Voltar ao ano letivo') }} {{ $academicYear->name }}" title="{{ __('Voltar ao ano letivo') }}">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
    @if ($canChangeAcademicStructure)
        <a class="btn btn-sm btn-primary shadow-sm sge-icon-action" href="{{ route('academic-years.courses.edit', [$academicYear, $course]) }}" aria-label="{{ __('Editar matriz') }} {{ $course->name }}" title="{{ __('Editar matriz') }}">
            <i class="fas fa-pen" aria-hidden="true"></i>
        </a>
    @endif
@endsection

@section('content')
    <x-academic-trail :school="$academicYear->school" :academic-year="$academicYear" :course="$course" />

    <x-structure-validation :issues="$structureIssues" title="{{ __('Validação da matriz') }}" empty="{{ __('Matriz sem inconsistências estruturais.') }}" />

    <nav class="sge-section-nav sge-academic-tabs mb-4" aria-label="{{ __('Áreas da matriz curricular') }}" role="tablist" data-section-tabs data-default-tab="{{ $errors->any() ? 'adicionar' : 'resumo' }}">
        <a href="#section-resumo" class="sge-section-nav-item" data-academic-tab="resumo" role="tab"><i class="fas fa-clipboard-list"></i><span>{{ __('Resumo') }}</span><small>{{ __('dados da matriz') }}</small></a>
        <a href="#section-adicionar" class="sge-section-nav-item" data-academic-tab="adicionar" role="tab"><i class="fas fa-plus-circle"></i><span>{{ __('Adicionar') }}</span><small>{{ __('novo componente') }}</small></a>
        <a href="#section-componentes" class="sge-section-nav-item" data-academic-tab="componentes" role="tab"><i class="fas fa-book-open"></i><span>{{ __('Componentes') }}</span><small>{{ $course->components->count() }} {{ __('cadastrados') }}</small></a>
    </nav>

    <div class="row">
        <div id="section-resumo" class="col-12 mb-4 sge-anchor-section" data-academic-panel="resumo" role="tabpanel">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h2 class="h6 m-0 font-weight-bold text-primary">{{ __('Resumo da matriz') }}</h2>
                </div>
                <div class="card-body">
                    <div class="sge-academic-metrics">
                        <div><strong>{{ $activeComponents->count() }}</strong><span>{{ __('componentes ativos') }}</span></div>
                        <div><strong>{{ $course->classes->count() }}</strong><span>{{ __('turmas vinculadas') }}</span></div>
                        <div><strong>{{ $linkedEnrollmentCount }}</strong><span>{{ __('matrículas vinculadas') }}</span></div>
                        <div><strong>{{ $course->formattedCalculatedWorkloadHours() }}h</strong><span>{{ __('carga prevista') }}</span></div>
                    </div>
                    <dl class="mb-0 sge-academic-summary-details">
                        <dt>{{ __('Matriz') }}</dt>
                        <dd>{{ $course->name }}</dd>
                        <dt>{{ __('Escola') }}</dt>
                        <dd>{{ $academicYear->school?->name }}</dd>
                        <dt>{{ __('Ano letivo') }}</dt>
                        <dd>{{ $academicYear->name }} · {{ $academicYear->referenceYearsLabel() }}</dd>
                        <dt>{{ __('Situação') }}</dt>
                        <dd><span class="badge badge-{{ $course->active ? 'success' : 'secondary' }}">{{ $course->active ? __('Ativa') : __('Inativa') }}</span></dd>
                        <dt>{{ __('Etapa') }}</dt>
                        <dd>{{ __($course->stageLabel()) }}</dd>
                        <dt>{{ __('Modalidade') }}</dt>
                        <dd>{{ __($course->modalityLabel() ?: '-') }}</dd>
                        <dt>{{ __('Formação') }}</dt>
                        <dd>{{ $course->isItineraryMatrix() ? __('Itinerário Formativo') : __('Formação Geral Básica') }}</dd>
                        @if(in_array($course->stage, [\App\Models\AcademicCourse::STAGE_HIGH_SCHOOL, \App\Models\AcademicCourse::STAGE_TECHNICAL], true))
                            <dt>{{ __('Itinerário formativo') }}</dt>
                            <dd>{{ $course->stage === \App\Models\AcademicCourse::STAGE_TECHNICAL ? $course->name : ($course->itinerary_name ?: __('Aprofundamento de Estudos')) }}</dd>
                        @endif
                        @if($course->stage === \App\Models\AcademicCourse::STAGE_TECHNICAL)
                            <dt class="col-sm-4">{{ __('Fundamento legal e atos') }}</dt><dd class="col-sm-8">{{ $course->regulatoryReference() }}</dd>
                            <dt class="col-sm-4">{{ __('Eixo / oferta') }}</dt><dd class="col-sm-8">{{ collect([$course->technological_axis, $course->offer_forms])->filter()->join(' · ') ?: '-' }}</dd>
                            <dt class="col-sm-4">{{ __('Vigência da autorização') }}</dt><dd class="col-sm-8">{{ $course->authorization_starts_at?->format('d/m/Y') ?: '-' }} {{ __('a') }} {{ $course->authorization_ends_at?->format('d/m/Y') ?: '-' }}</dd>
                        @endif
                        <dt>{{ __('Hora-aula') }}</dt>
                        <dd>{{ $course->class_hour_minutes }} {{ __('minutos') }}</dd>
                        <dt>{{ __('Carga horária calculada') }}</dt>
                        <dd>{{ $course->formattedCalculatedWorkloadHours() }} {{ __('horas') }}</dd>
                        <dt>{{ __('Formas de carga horária') }}</dt>
                        <dd>{{ $activeComponents->whereNotNull('weekly_lessons')->count() }} {{ __('por aulas semanais ·') }} {{ $activeComponents->whereNotNull('workload_hours')->count() }} {{ __('por carga total') }}</dd>
                        <dt>{{ __('Turmas') }}</dt>
                        <dd>{{ $course->classes->sortBy('name')->pluck('name')->join(', ') ?: __('Nenhuma turma vinculada') }}</dd>
                        <dt>{{ __('Observações') }}</dt>
                        <dd>{{ $course->notes ?: __('Nenhuma observação cadastrada') }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div id="section-adicionar" class="col-12 mb-4 sge-anchor-section" data-academic-panel="adicionar" role="tabpanel">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h2 class="h6 m-0 font-weight-bold text-primary">{{ __('Novo componente curricular') }}</h2>
                </div>
                <div class="card-body">
                    @if ($canChangeAcademicStructure)
                        <form method="POST" action="{{ route('academic-years.courses.components.store', [$academicYear, $course]) }}">
                            @csrf
                            <div class="row">
                                <div class="col-md-4 form-group">
                                    <label for="new_component_name">{{ __('Componente') }}</label>
                                    <input id="new_component_name" name="name" class="form-control" placeholder="{{ __('Língua Portuguesa') }}" list="curriculum-component-suggestions" data-curriculum-component-name required>
                                    <datalist id="curriculum-component-suggestions">
                                        @foreach ($curriculumSuggestions as $suggestion)
                                            <option value="{{ $suggestion['component'] }}">{{ $suggestion['area'] }}</option>
                                        @endforeach
                                    </datalist>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="new_component_area_id">{{ __('Área') }}</label>
                                    <select id="new_component_area_id" name="knowledge_area_id" class="form-control" data-curriculum-area-select>
                                        <option value="">{{ __('Não definida') }}</option>
                                        @foreach ($knowledgeAreas as $area)
                                            <option value="{{ $area->id }}">{{ $area->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 form-group" data-workload-choice>
                                    <label class="d-block">{{ __('Como informar a carga horária?') }}</label>
                                    <div class="custom-control custom-radio custom-control-inline">
                                        <input class="custom-control-input" type="radio" id="new_workload_mode_weekly" name="workload_mode" value="weekly_lessons" @checked(old('workload_mode', 'weekly_lessons') === 'weekly_lessons') required>
                                        <label class="custom-control-label" for="new_workload_mode_weekly">{{ __('Aulas por semana') }}</label>
                                    </div>
                                    <div class="custom-control custom-radio custom-control-inline">
                                        <input class="custom-control-input" type="radio" id="new_workload_mode_total" name="workload_mode" value="workload_hours" @checked(old('workload_mode') === 'workload_hours') required>
                                        <label class="custom-control-label" for="new_workload_mode_total">{{ __('Total de horas') }}</label>
                                    </div>
                                    <div class="mt-2" data-workload-field="weekly_lessons">
                                        <label for="new_component_weekly_lessons">{{ __('Aulas por semana') }}</label>
                                        <input id="new_component_weekly_lessons" name="weekly_lessons" data-mask="digits" data-mask-max="2" inputmode="numeric" autocomplete="off" class="form-control" value="{{ old('weekly_lessons') }}">
                                    </div>
                                    <div class="mt-2" data-workload-field="workload_hours">
                                        <label for="new_component_workload_hours">{{ __('Carga horária total') }}</label>
                                        <div class="input-group"><input id="new_component_workload_hours" name="workload_hours" data-mask="digits" data-mask-max="5" inputmode="numeric" autocomplete="off" class="form-control" value="{{ old('workload_hours') }}"><div class="input-group-append"><span class="input-group-text">{{ __('horas') }}</span></div></div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 form-group">
                                    <label for="new_component_starts_period_id">{{ __('Período inicial') }}</label>
                                    <select id="new_component_starts_period_id" name="starts_period_id" class="form-control">
                                        <option value="">{{ __('Desde o início da turma') }}</option>
                                        @foreach ($academicYear->periods as $period)
                                            <option value="{{ $period->id }}">{{ $period->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="new_component_ends_period_id">{{ __('Período final') }}</label>
                                    <select id="new_component_ends_period_id" name="ends_period_id" class="form-control">
                                        <option value="">{{ __('Até o fim da turma') }}</option>
                                        @foreach ($academicYear->periods as $period)
                                            <option value="{{ $period->id }}">{{ $period->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="new_component_notes">{{ __('Observações') }}</label>
                                    <input id="new_component_notes" name="notes" class="form-control">
                                </div>
                            </div>
                            <input type="hidden" name="active" value="1">
                            <button class="btn btn-primary" type="submit">{{ __('Adicionar componente') }}</button>
                        </form>
                    @else
                        <p class="mb-0 text-gray-600">{{ __('Ano letivo aprovado. A matriz está bloqueada para edição estrutural.') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div id="section-componentes" class="card shadow mb-4 sge-anchor-section" data-academic-panel="componentes" role="tabpanel">
        <div class="card-header py-3">
            <h2 class="h6 m-0 font-weight-bold text-primary">{{ __('Componentes curriculares') }}</h2>
        </div>
        <div class="card-body">
            @forelse ($course->componentsGroupedByArea() as $group)
                <section class="border rounded mb-3">
                    <div class="bg-light border-bottom px-3 py-2">
                        <h3 class="h6 mb-0 text-primary">{{ $group['area'] === 'Área não definida' ? __('Área não definida') : $group['area'] }}</h3>
                    </div>
                    <div class="list-group list-group-flush">
                        @foreach ($group['components'] as $component)
                            <div class="list-group-item d-flex align-items-center justify-content-between flex-wrap">
                                <div class="mr-3">
                                    <strong>{{ $component->name }}</strong>
                                    <div class="small text-gray-600">
                                        {{ $component->formattedCalculatedWorkloadHours($course) }} {{ __('horas') }}
                                        @if ($component->weekly_lessons !== null)
                                            · {{ $component->weekly_lessons }} {{ __('aulas semanais') }}
                                        @endif
                                        · {{ $component->startsPeriod?->name ?? __('início da turma') }} {{ __('até') }} {{ $component->endsPeriod?->name ?? __('fim da turma') }}
                                    </div>
                                </div>
                                <div class="sge-action-buttons mt-2 mt-md-0" aria-label="{{ __('Ações do componente') }} {{ $component->name }}">
                                    <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('academic-years.courses.components.show', [$academicYear, $course, $component]) }}" aria-label="{{ __('Gerenciar componente') }} {{ $component->name }}" title="{{ __('Gerenciar componente') }}">
                                        <i class="fas fa-cog" aria-hidden="true"></i>
                                    </a>
                                    @if ($canChangeAcademicStructure)
                                        <form method="POST" action="{{ route('academic-years.courses.components.destroy', [$academicYear, $course, $component]) }}" onsubmit="return confirm(@js(__('Remover este componente?')))">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger sge-icon-action" type="submit" aria-label="{{ __('Remover componente') }} {{ $component->name }}" title="{{ __('Remover componente') }}">
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
                <p class="mb-0">{{ __('Nenhum componente cadastrado. A turma só poderá ser criada depois que a matriz tiver ao menos um componente ativo.') }}</p>
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
