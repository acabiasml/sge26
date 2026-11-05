@push('styles')
    <link href="{{ asset('template/css/sge-history-editor.css') }}?v={{ filemtime(public_path('template/css/sge-history-editor.css')) }}" rel="stylesheet">
@endpush

@php
    $curriculumOnly = $curriculumOnly ?? false;
    $manualOnly = $manualOnly ?? false;
    $history->loadMissing(['years.records', 'components.records.year']);
    $editableYears = $manualOnly ? $history->years->filter(fn ($year) => $year->source !== 'system')->values() : $history->years->values();
    $catalogStage = \App\Support\CurriculumAreaStage::historyStage($history) ?: 'fundamental';
    $modalityOptions = \App\Models\AcademicCourse::MODALITY_LABELS;
    $defaultYearCount = match ($history->education_stage) {
        'fundamental' => 9,
        'medio' => 3,
        default => 1,
    };
    $defaultYears = collect(range(1, $defaultYearCount))->map(fn ($number) => [
        'label' => $number.'º Ano', 'year' => '', 'stage' => $history->stage ?: '', 'modality' => 'Regular',
        'grade_phase' => $number.'º Ano', 'grade_number' => $history->education_stage ? $number : null,
        'school_name' => '', 'city' => '', 'state' => '', 'country' => 'Brasil',
        'transcript_mode' => 'detailed', 'final_result' => '', 'workload_hours' => '', 'school_days' => '',
        'attendance_label' => '', 'minimum_attendance_percentage' => '', 'notes' => '',
    ])->all();
    $registeredSystemGrades = $history->years->where('source', 'system')
        ->map(fn ($year) => app(\App\Support\StudentAcademicHistoryCompleteness::class)->gradeNumber((string) $year->grade_phase, $history->education_stage))
        ->filter()->all();
    $firstManualYear = collect($defaultYears)->first(fn ($year, $index) => ! in_array($index + 1, $registeredSystemGrades, true))
        ?? array_merge($defaultYears[0], ['label' => '', 'grade_phase' => '']);
    $yearsInput = collect(old('years', $history->exists && $editableYears->isNotEmpty()
        ? $editableYears->map(fn ($year) => [
            'id' => $year->id,
            'label' => $year->label,
            'source' => $year->source,
            'student_enrollment_id' => $year->student_enrollment_id,
            'year' => $year->year,
            'stage' => $year->stage,
            'modality' => $year->modality,
            'grade_phase' => $year->grade_phase,
            'grade_number' => $year->grade_number ?: app(\App\Support\StudentAcademicHistoryCompleteness::class)->gradeNumber((string) $year->grade_phase, $history->education_stage ?: match ($year->stage) { 'Ensino Médio' => 'medio', default => 'fundamental' }),
            'imported_from_history_year_id' => $year->imported_from_history_year_id,
            'school_name' => $year->school_name,
            'school_authorization' => $year->school_authorization,
            'source_document' => $year->source_document,
            'city' => $year->city,
            'state' => $year->state,
            'country' => $year->country,
            'transcript_mode' => $year->transcript_mode,
            'final_result' => $year->final_result,
            'workload_hours' => $year->workload_hours,
            'school_days' => $year->school_days,
            'attendance_label' => $year->attendance_label,
            'minimum_attendance_percentage' => $year->minimum_attendance_percentage,
            'notes' => $year->notes,
        ])->all()
        : ($manualOnly ? [$firstManualYear] : $defaultYears)))->values();

    $yearComponentsInput = [];
    foreach ($yearsInput as $yearIndex => $yearInput) {
        $yearComponentsInput[$yearIndex] = collect($history->components)->map(function ($component) use ($yearInput) {
            $record = $component->records->firstWhere('student_academic_history_year_id', $yearInput['id'] ?? null);
            if (! $record) return null;

            return [
                'formation' => $component->formation, 'knowledge_area' => $component->knowledge_area,
                'name' => $component->name, 'module_label' => $component->module_label, 'regulatory_reference' => $component->regulatory_reference,
                'record' => $record->only(['score_label', 'score_numeric', 'workload_hours', 'completed_workload_hours', 'frequency_label', 'frequency_percentage', 'absences', 'result']),
            ];
        })->filter()->values()->all();
    }
    $yearComponentsInput = old('year_components', $yearComponentsInput);

    $historyEditorConfig = [
    'fixedStage' => $history->education_stage ?: '',
    'stageLabels' => \App\Models\AcademicCourse::STAGE_LABELS,
    'catalogs' => config('curriculum.stages'),
    'errors' => $errors->keys(),
    'select' => __('Selecione'), 'newYear' => __('Novo ano'), 'preview' => __('Preencha os dados deste ano'),
    'empty' => __('Nenhum componente cadastrado neste ano.'),
    'components' => __('Notas e componentes'), 'area' => __('Área'),
    'confirmRemove' => __('Remover este ano e suas notas transcritas ao salvar o histórico?'),
    'formationLabels' => collect(['Formação Geral Básica', 'Parte Diversificada', 'Itinerário Formativo', 'Formação Técnica Profissional'])->mapWithKeys(fn ($value) => [$value => __($value)])->all(),
    'flexibleFormations' => ['fundamental' => 'Parte Diversificada', 'medio' => 'Itinerário Formativo', 'tecnico' => 'Formação Técnica Profissional'],
];

@endphp

<section class="sge-history-workspace mb-4" aria-label="{{ __('Cadastro de histórico escolar') }}">
    <input type="hidden" name="year_components_present" value="1">
    <input type="hidden" name="is_unified" value="{{ old('is_unified', $history->is_unified) ? 1 : 0 }}">
    @if($curriculumOnly)
        <input type="hidden" name="title" value="{{ $history->title }}">
        <input type="hidden" name="stage" value="{{ $history->stage }}">
        <input type="hidden" name="school_id" value="{{ $history->school_id }}">
        <input type="hidden" name="legal_basis" value="{{ $history->legal_basis }}">
        <input type="hidden" name="notes" value="{{ $history->notes }}">
        <input type="hidden" name="issued_place" value="{{ $history->issued_place }}">
        <input type="hidden" name="issued_date" value="{{ $history->issued_date?->toDateString() }}">
        @if($history->active)<input type="hidden" name="active" value="1">@endif
    @endif
    <div class="sge-history-main" style="grid-column: 1 / -1;">
        @unless($curriculumOnly)
            <details class="small mb-4">
                <summary class="text-primary">{{ __('Como preencher') }}</summary>
                <div class="text-muted mt-2">
                    <p>{{ __('Abra o bloco de cada ano para preencher a escola, o resultado e os componentes daquele ano.') }}</p>
                    <p>{{ __('Se o documento trouxer resultado global ou etapa sem transcrição, selecione esse tipo no próprio ano. Os componentes podem ser registrados sem nota.') }}</p>
                </div>
            </details>
            @if($schools->count() === 1)
                <input type="hidden" name="school_id" value="{{ $schools->first()->id }}">
                <p class="small text-muted">{{ __('Escola emissora:') }} {{ $schools->first()->name }}</p>
            @else
                <div class="form-group">
                    <label for="school_id">{{ __('Escola que emitirá o histórico') }}</label>
                    <select id="school_id" name="school_id" class="form-control @error('school_id') is-invalid @enderror" required>
                        <option value="">{{ __('Selecione a escola') }}</option>
                        @foreach($schools as $school)
                            <option value="{{ $school->id }}" @selected((string) old('school_id') === (string) $school->id)>{{ $school->name }}</option>
                        @endforeach
                    </select>
                    @error('school_id') <div class="invalid-feedback">{{ __($message) }}</div> @enderror
                </div>
            @endif
        @endunless
        <section class="card shadow sge-panel-card mb-4 sge-history-editor-section">
            <div class="sge-panel-header">
                <div>
                    <h2>{{ __('Anos cursados') }}</h2>
                    <p>{{ $manualOnly ? __('Abra um ano para conferir as informações da escola de origem.') : __('Abra um ano por vez para revisar a série e os dados da escola de origem.') }}</p>
                </div>
                <button class="btn btn-sm btn-outline-primary" type="button" data-add-history-year>
                    <i class="fas fa-plus mr-1" aria-hidden="true"></i>{{ __('Adicionar ano') }}
                </button>
            </div>
            <div class="card-body">
                <div id="history-years" class="sge-history-year-grid" aria-label="{{ __('Anos cadastrados') }}">
                    @foreach($yearsInput as $yearIndex => $year)
                        <details class="sge-history-year-card" data-history-year name="history-year">
                            <summary class="sge-history-year-summary">
                                <span class="sge-history-year-marker" aria-hidden="true"><i class="fas fa-calendar-alt"></i></span>
                                <span class="sge-history-year-summary-text">
                                    <strong>{{ filled($year['grade_phase'] ?? null) ? $year['grade_phase'] : __('Ano ').($yearIndex + 1) }}</strong>
                                    <small data-history-year-preview>{{ collect([$year['year'] ?? null, $year['school_name'] ?? null, filled($year['workload_hours'] ?? null) ? ($year['workload_hours'].' h') : null])->filter()->join(' · ') ?: __('Preencha os dados deste ano') }}</small>
                                </span>
                                <i class="fas fa-chevron-down sge-history-year-chevron" aria-hidden="true"></i>
                            </summary>
                            <div class="sge-history-year-body">
                            <input type="hidden" name="years[{{ $yearIndex }}][id]" value="{{ $year['id'] ?? '' }}">
                            <input type="hidden" name="years[{{ $yearIndex }}][source]" value="{{ $year['source'] ?? 'manual' }}">
                            <input type="hidden" name="years[{{ $yearIndex }}][student_enrollment_id]" value="{{ $year['student_enrollment_id'] ?? '' }}">
                            <input type="hidden" name="years[{{ $yearIndex }}][imported_from_history_year_id]" value="{{ $year['imported_from_history_year_id'] ?? '' }}">
                            <fieldset class="sge-history-year-fields">
                                <legend>{{ __('Identificação do ano') }}</legend>
                            <div class="form-row">
                                <div class="form-group col-md-3">
                                    <label>{{ __('Ano civil') }}</label>
                                    <input name="years[{{ $yearIndex }}][year]" data-mask="year" inputmode="numeric" autocomplete="off" class="form-control" value="{{ $year['year'] ?? '' }}" required>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>{{ __('Série/Fase no documento recebido') }}</label>
                                    <input name="years[{{ $yearIndex }}][grade_phase]" class="form-control" value="{{ $year['grade_phase'] ?? '' }}" required>
                                </div>
                                <div class="form-group col-md-3">
                                    <label>{{ __('Ano escolar correspondente') }}</label>
                                    <select name="years[{{ $yearIndex }}][grade_number]" class="form-control" data-history-grade>
                                        <option value="">{{ __('Selecione a série exata') }}</option>
                                        @for($grade = 1; $grade <= 9; $grade++)
                                            <option value="{{ $grade }}" @selected((int) ($year['grade_number'] ?? 0) === $grade)>{{ $grade }}º {{ __('ano') }}</option>
                                        @endfor
                                    </select>
                                    @error("years.{$yearIndex}.grade_number")<div class="invalid-feedback d-block">{{ __($message) }}</div>@enderror
                                </div>
                                <div class="form-group {{ $manualOnly ? 'col-md-6' : 'col-md-12' }}">
                                    <label>{{ __('Tipo de transcrição') }}</label>
                                    @php($transcriptMode = $year['transcript_mode'] ?? 'detailed')
                                    <select name="years[{{ $yearIndex }}][transcript_mode]" class="form-control" required>
                                        <option value="detailed" @selected($transcriptMode === 'detailed')>{{ __('Detalhada por componente curricular') }}</option>
                                        <option value="summary" @selected($transcriptMode === 'summary')>{{ __('Global/AP ou síntese sem componentes detalhados') }}</option>
                                        <option value="no_transcription" @selected($transcriptMode === 'no_transcription')>{{ __('Etapa sem transcrição no documento recebido') }}</option>
                                    </select>
                                </div>
                                @if($manualOnly)
                                    <input type="hidden" name="years[{{ $yearIndex }}][stage]" value="{{ \App\Models\AcademicCourse::STAGE_LABELS[$history->education_stage] ?? $history->stage }}">
                                @else
                                <div class="form-group col-md-6">
                                    <label>{{ __('Etapa') }}</label>
                                    <select name="years[{{ $yearIndex }}][stage]" class="form-control" data-history-stage required>
                                        <option value="">{{ __('Selecione') }}</option>
                                        @foreach(\App\Models\AcademicCourse::STAGE_LABELS as $stageCode => $stageLabel)
                                            <option value="{{ $stageLabel }}" @selected(($year['stage'] ?? '') === $stageLabel)>{{ __($stageLabel) }}</option>
                                        @endforeach
                                        @if(filled($year['stage'] ?? null) && ! in_array($year['stage'], \App\Models\AcademicCourse::STAGE_LABELS, true))<option value="{{ $year['stage'] }}" selected>{{ $year['stage'] }}</option>@endif
                                    </select>
                                </div>
                                @endif
                                <div class="form-group col-md-6">
                                    <label>{{ __('Modalidade') }}</label>
                                    @php($selectedModality = $year['modality'] ?? '')
                                    <select name="years[{{ $yearIndex }}][modality]" class="form-control" required>
                                        <option value="">{{ __('Selecione') }}</option>
                                        @foreach($modalityOptions as $modality)<option value="{{ $modality }}" @selected($selectedModality === $modality)>{{ __($modality) }}</option>@endforeach
                                        @if($selectedModality && ! in_array($selectedModality, $modalityOptions, true))<option value="{{ $selectedModality }}" selected>{{ $selectedModality }}</option>@endif
                                    </select>
                                </div>
                            </div>
                            </fieldset>
                            <fieldset class="sge-history-year-fields">
                                <legend>{{ __('Escola de origem') }}</legend>
                            <div class="form-row">
                                <div class="form-group col-md-12">
                                    <label>{{ __('Escola onde cursou') }}</label>
                                    <input name="years[{{ $yearIndex }}][school_name]" class="form-control" value="{{ $year['school_name'] ?? '' }}" required>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>{{ __('Cidade') }}</label>
                                    <input name="years[{{ $yearIndex }}][city]" class="form-control" value="{{ $year['city'] ?? '' }}" required>
                                </div>
                                <div class="form-group col-md-2">
                                    <label>{{ __('UF') }}</label>
                                    <select name="years[{{ $yearIndex }}][state]" class="form-control" required>
                                        <option value=""></option>
                                        @foreach($states as $uf => $stateName)
                                            <option value="{{ $uf }}" @selected(($year['state'] ?? '') === $uf)>{{ $uf }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>{{ __('País') }}</label>
                                    <input name="years[{{ $yearIndex }}][country]" class="form-control" value="{{ $year['country'] ?? 'Brasil' }}" required>
                                </div>
                            </div>
                            </fieldset>
                            <fieldset class="sge-history-year-fields">
                                <legend>{{ __('Carga horária e resultado') }}</legend>
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label>{{ __('Carga horária total (h)') }}</label>
                                    <input name="years[{{ $yearIndex }}][workload_hours]" data-mask="decimal" inputmode="decimal" class="form-control" value="{{ $year['workload_hours'] ?? '' }}" required>
                                </div>
                                <div class="form-group col-md-8">
                                    <label>{{ __('Resultado final nesta escola/ano') }}</label>
                                    <input name="years[{{ $yearIndex }}][final_result]" class="form-control" value="{{ $year['final_result'] ?? '' }}" required>
                                </div>
                            </div>
                            </fieldset>
                            @php($componentStage = $history->education_stage ?: (\App\Support\CurriculumAreaStage::stageFromLabel($year['stage'] ?? null) ?: $catalogStage))
                            <fieldset class="sge-history-year-fields sge-history-year-components" data-history-components>
                                <legend>{{ __('Notas e componentes') }}</legend>
                                <p class="small text-muted" data-history-global-hint @if(($year['transcript_mode'] ?? 'detailed') === 'detailed') hidden @endif>{{ __('Este ano usa resultado global ou não possui transcrição detalhada. Os componentes cadastrados e suas notas serão preservados.') }}</p>
                                <div class="sge-history-matrix-toolbar">
                                    <button class="btn btn-sm btn-outline-primary" type="button" data-add-history-component><i class="fas fa-plus mr-1" aria-hidden="true"></i>{{ __('Adicionar componente neste ano') }}</button>
                                </div>
                                <div class="table-responsive" role="region" tabindex="0" aria-label="{{ __('Notas e componentes') }}">
                                    <table class="table table-bordered table-sm sge-history-year-table" id="history-components-{{ $yearIndex }}" data-sge-client-pagination="10" aria-label="{{ __('Notas e componentes') }} — {{ $year['grade_phase'] ?? '' }}">
                                        <thead><tr><th scope="col">{{ __('Formação') }}</th><th scope="col">{{ __('Área') }}</th><th scope="col">{{ __('Componente') }}</th><th scope="col">{{ __('Nota/conceito') }}</th><th scope="col">{{ __('Ações') }}</th></tr></thead>
                                        <tbody>
                                            @forelse($yearComponentsInput[$yearIndex] ?? [] as $componentIndex => $component)
                                                @include('student-histories._year-component-row')
                                            @empty
                                                <tr data-history-empty><td colspan="5" class="text-muted">{{ __('Nenhum componente cadastrado neste ano.') }}</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </fieldset>
                            <details class="sge-history-year-extra">
                                <summary><i class="fas fa-sliders-h mr-2" aria-hidden="true"></i>{{ __('Informações complementares do ano') }}</summary>
                                <div class="form-row pt-3">
                                <div class="form-group col-md-7">
                                    <label>{{ __('Ato autorizativo/credenciamento') }}</label>
                                    <textarea name="years[{{ $yearIndex }}][school_authorization]" class="form-control" rows="2" placeholder="{{ __('Ato, resolução, portaria ou credenciamento da escola') }}">{{ $year['school_authorization'] ?? '' }}</textarea>
                                </div>
                                <div class="form-group col-md-5">
                                    <label>{{ __('Documento de origem') }}</label>
                                    <input name="years[{{ $yearIndex }}][source_document]" class="form-control" value="{{ $year['source_document'] ?? '' }}" placeholder="{{ __('Ex.: Histórico nº 123/2025') }}">
                                </div>
                                <div class="form-group col-md-4">
                                    <label>{{ __('Dias letivos') }}</label>
                                    <input name="years[{{ $yearIndex }}][school_days]" data-mask="digits" data-mask-max="3" inputmode="numeric" autocomplete="off" class="form-control" value="{{ $year['school_days'] ?? '' }}">
                                </div>
                                <div class="form-group col-md-4">
                                    <label>{{ __('Frequência mínima') }}</label>
                                    <div class="input-group">
                                        <input name="years[{{ $yearIndex }}][minimum_attendance_percentage]" data-mask="percentage" inputmode="numeric" class="form-control" value="{{ $year['minimum_attendance_percentage'] ?? '' }}">
                                        <div class="input-group-append"><span class="input-group-text">%</span></div>
                                    </div>
                                </div>
                                <div class="form-group col-md-12">
                                    <label>{{ __('Frequência geral ou observação de frequência') }}</label>
                                    <input name="years[{{ $yearIndex }}][attendance_label]" class="form-control" value="{{ $year['attendance_label'] ?? '' }}" placeholder="{{ __('Ex.: 92%, frequência suficiente, etapa sem informação') }}">
                                </div>
                                <div class="form-group col-md-12">
                                    <label>{{ __('Observações deste ano') }}</label>
                                    <textarea name="years[{{ $yearIndex }}][notes]" class="form-control" rows="2" placeholder="{{ __('Reclassificação, etapa sem transcrição, aproveitamento global...') }}">{{ $year['notes'] ?? '' }}</textarea>
                                </div>
                            </div>
                            </details>
                            <div class="sge-history-year-actions">
                                <button class="btn btn-sm btn-outline-danger" type="button" data-remove-history-year><i class="fas fa-trash mr-1" aria-hidden="true"></i>{{ __('Remover este ano do formulário') }}</button>
                            </div>
                            </div>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>


    </div>
</section>

<div class="sge-history-save-bar mb-4">
    <span class="text-muted">{{ __('As alterações de todos os anos são salvas juntas.') }}</span>
    <button class="btn btn-primary" type="submit">
        <i class="fas fa-save mr-1" aria-hidden="true"></i>{{ $history->exists ? __('Salvar histórico') : __('Adicionar ao histórico da etapa') }}
    </button>
</div>

<template id="history-component-template">
    @include('student-histories._year-component-row', ['component' => [], 'yearIndex' => '__YEAR__', 'componentIndex' => '__ROW__', 'componentStage' => $catalogStage])
</template>

@push('scripts')
<script type="application/json" id="history-editor-config">@json($historyEditorConfig)</script>
<script src="{{ asset('template/js/sge-history-editor.js') }}?v={{ filemtime(public_path('template/js/sge-history-editor.js')) }}" defer></script>
@endpush
