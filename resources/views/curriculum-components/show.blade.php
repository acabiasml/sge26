@extends('layouts.app')

@php($canChangeAcademicStructure = ! $academicYear->approved_at || auth()->user()->isAdministrator())

@section('title', __('Componente - ').$component->name)
@section('page-title', __('Componente: ').$component->name)

@section('page-actions')
    <a data-navigation-return class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('academic-years.courses.show', [$academicYear, $course]) }}" aria-label="{{ __('Voltar à matriz') }} {{ $course->name }}" title="{{ __('Voltar à matriz') }}">
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
    <nav class="sge-section-nav sge-academic-tabs mb-4" aria-label="{{ __('Áreas do componente curricular') }}" role="tablist" data-section-tabs data-default-tab="{{ $errors->any() ? 'edicao' : 'resumo' }}">
        <a href="#section-resumo" class="sge-section-nav-item" data-academic-tab="resumo" role="tab"><i class="fas fa-clipboard-list"></i><span>{{ __('Resumo') }}</span></a>
        <a href="#section-edicao" class="sge-section-nav-item" data-academic-tab="edicao" role="tab"><i class="fas fa-pen"></i><span>{{ __('Edição') }}</span></a>
    </nav>

    <div class="row">
        <div id="section-resumo" class="col-12 mb-4 sge-anchor-section" data-academic-panel="resumo" role="tabpanel">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h2 class="h6 m-0 font-weight-bold text-primary">{{ __('Resumo') }}</h2>
                </div>
                <div class="card-body">
                    <dl class="mb-0 sge-academic-summary-details">
                        <dt>{{ __('Componente') }}</dt>
                        <dd>{{ $component->name }}</dd>
                        <dt>{{ __('Escola') }}</dt>
                        <dd>{{ $academicYear->school?->name }}</dd>
                        <dt>{{ __('Ano letivo') }}</dt>
                        <dd>{{ $academicYear->name }} · {{ $academicYear->referenceYearsLabel() }}</dd>
                        <dt>{{ __('Matriz') }}</dt>
                        <dd>{{ $course->name }} · {{ __($course->stageLabel()) }} · {{ __($course->modalityLabel()) }}</dd>
                        <dt>{{ __('Formação') }}</dt>
                        <dd>{{ \App\Support\CurriculumCatalog::formationLabelForComponent($component) }}</dd>
                        <dt>{{ __('Área') }}</dt>
                        <dd>@include('partials.knowledge-area-label', ['labelArea' => $component->area, 'labelName' => $component->area?->name ?? __('Não definida')])</dd>
                        <dt>{{ __('Situação') }}</dt>
                        <dd><span class="badge badge-{{ $component->active ? 'success' : 'secondary' }}">{{ $component->active ? __('Ativo') : __('Inativo') }}</span></dd>
                        <dt>{{ __('Duração') }}</dt>
                        <dd>{{ $component->startsPeriod?->name ?? __('início da turma') }} {{ __('até') }} {{ $component->endsPeriod?->name ?? __('fim da turma') }}</dd>
                        <dt>{{ __('Carga horária') }}</dt>
                        <dd>{{ $component->formattedCalculatedWorkloadHours($course) }} {{ __('horas') }}</dd>
                        <dt>{{ __('Forma de definição') }}</dt>
                        <dd>{{ $component->hasPeriodLoads() ? __('Por período letivo').' ('.$component->weeklyLessonsLabel().')' : ($component->workload_hours === null ? $component->weekly_lessons.__(' aulas por semana de ').$course->class_hour_minutes.__(' minutos') : number_format((float) $component->workload_hours, 2, ',', '.').__(' horas totais informadas')) }}</dd>
                        <dt>{{ __('Observações') }}</dt>
                        <dd>{{ $component->notes ?: __('Nenhuma observação cadastrada') }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div id="section-edicao" class="col-12 mb-4 sge-anchor-section" data-academic-panel="edicao" role="tabpanel">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h2 class="h6 m-0 font-weight-bold text-primary">{{ __('Gerenciar componente') }}</h2>
                </div>
                <div class="card-body">
                    @if ($canChangeAcademicStructure)
                        <form method="POST" action="{{ route('academic-years.courses.components.update', [$academicYear, $course, $component]) }}">
                            @csrf
                            @method('PUT')
                            <div class="row">
                                <div class="col-12 form-group">
                                    <label for="component_name">{{ __('Componente') }}</label>
                                    @include('curriculum-components.partials.catalog-selection', ['selectId' => 'component_name'])
                                </div>
                            </div>
                            @include('curriculum-components.partials.workload-fields', ['workloadPrefix' => 'component', 'workloadComponent' => $component, 'workloadPeriods' => $periods])
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label for="component_starts_period_id">{{ __('Período inicial') }}</label>
                                    <select id="component_starts_period_id" name="starts_period_id" class="form-control">
                                        <option value="">{{ __('Desde o início da turma') }}</option>
                                        @foreach ($periods as $period)
                                            <option value="{{ $period->id }}" @selected((int) old('starts_period_id', $component->starts_period_id) === $period->id)>{{ $period->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for="component_ends_period_id">{{ __('Período final') }}</label>
                                    <select id="component_ends_period_id" name="ends_period_id" class="form-control">
                                        <option value="">{{ __('Até o fim da turma') }}</option>
                                        @foreach ($periods as $period)
                                            <option value="{{ $period->id }}" @selected((int) old('ends_period_id', $component->ends_period_id) === $period->id)>{{ $period->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="component_notes">{{ __('Observações') }}</label>
                                <textarea id="component_notes" name="notes" class="form-control" rows="3">{{ old('notes', $component->notes) }}</textarea>
                            </div>
                            <input type="hidden" name="active" value="1">
                            <button class="btn btn-primary" type="submit">{{ __('Salvar componente') }}</button>
                        </form>
                    @else
                        <p class="mb-0 text-gray-600">{{ __('Ano letivo aprovado. O componente está bloqueado para edição estrutural.') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
