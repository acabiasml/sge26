@php
    $curriculumOnly = $curriculumOnly ?? false;
    $manualOnly = $manualOnly ?? false;
    $history->loadMissing(['years.records', 'components.records.year']);
    $editableYears = $manualOnly ? $history->years->where('source', 'manual')->values() : $history->years->values();
    $catalogStage = $history->education_stage ?: 'fundamental';
    $bnccAreas = collect(config("curriculum.stages.{$catalogStage}.formations.formacao_geral_basica.areas", []));
    $bnccComponentsByArea = $bnccAreas->mapWithKeys(fn ($area) => [$area['name'] => $area['components'] ?? []]);
    $flexibleFormation = $catalogStage === 'medio' ? 'Itinerário Formativo' : 'Parte Diversificada';
    $formationOptions = ['Formação Geral Básica', $flexibleFormation];
    $defaultYearCount = $history->education_stage === 'fundamental' ? 9 : 3;
    $defaultYears = collect(range(1, $defaultYearCount))->map(fn ($number) => [
        'label' => $number.'º Ano', 'year' => '', 'stage' => $history->stage ?: '', 'modality' => 'Regular',
        'grade_phase' => $number.'º Ano', 'school_name' => '', 'city' => '', 'state' => '', 'country' => 'Brasil',
        'transcript_mode' => 'detailed', 'final_result' => '', 'workload_hours' => '', 'school_days' => '',
        'attendance_label' => '', 'minimum_attendance_percentage' => '', 'notes' => '',
    ])->all();
    $yearsInput = collect(old('years', $history->exists && $editableYears->isNotEmpty()
        ? $editableYears->map(fn ($year) => [
            'label' => $year->label,
            'source' => $year->source,
            'student_enrollment_id' => $year->student_enrollment_id,
            'year' => $year->year,
            'stage' => $year->stage,
            'modality' => $year->modality,
            'grade_phase' => $year->grade_phase,
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
        : ($manualOnly ? [$defaultYears[0]] : $defaultYears)))->values();

    $componentsInput = collect(old('components', $history->exists && $history->components->isNotEmpty()
        ? $history->components->map(function ($component) use ($editableYears) {
            $records = [];
            foreach ($editableYears as $yearIndex => $year) {
                $record = $component->records->firstWhere('student_academic_history_year_id', $year->id);
                $records[$yearIndex] = [
                    'score_label' => $record?->score_label,
                    'score_numeric' => $record?->score_numeric,
                    'workload_hours' => $record?->workload_hours,
                    'frequency_label' => $record?->frequency_label,
                    'frequency_percentage' => $record?->frequency_percentage,
                    'absences' => $record?->absences,
                    'result' => $record?->result,
                ];
            }

            return [
                'formation' => $component->formation,
                'knowledge_area' => $component->knowledge_area,
                'name' => $component->name,
                'records' => $records,
            ];
        })->filter(fn ($component) => collect($component['records'])->flatten(1)->filter(fn ($value) => filled($value))->isNotEmpty() || ! $manualOnly)->values()->all()
        : [
            ['formation' => 'Formação Geral Básica', 'knowledge_area' => 'Linguagens', 'name' => 'Língua Portuguesa', 'records' => []],
            ['formation' => 'Formação Geral Básica', 'knowledge_area' => 'Matemática', 'name' => 'Matemática', 'records' => []],
            ['formation' => 'Formação Geral Básica', 'knowledge_area' => 'Ciências da Natureza', 'name' => 'Ciências', 'records' => []],
        ]))->values();
    if ($componentsInput->isEmpty()) {
        $componentsInput = collect([
            ['formation' => 'Formação Geral Básica', 'knowledge_area' => '', 'name' => '', 'records' => []],
        ]);
    }
@endphp

<section class="sge-history-workspace mb-4" aria-label="Cadastro de histórico escolar">
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
    @unless($curriculumOnly)
    <aside class="sge-history-sidebar">
        <div class="card shadow sge-panel-card mb-4">
            <div class="sge-panel-header">
                <div>
                    <h2>Documento</h2>
                    <p>Identificação geral do histórico recebido.</p>
                </div>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label for="title">Título</label>
                    <input id="title" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $history->title) }}" required>
                    @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label for="stage">Etapa</label>
                    <input id="stage" name="stage" class="form-control" value="{{ old('stage', $history->stage) }}" placeholder="Ensino Fundamental, Ensino Médio..." required>
                </div>

                <div class="form-group">
                    <label for="school_id">Escola relacionada</label>
                    <select id="school_id" name="school_id" class="form-control" required>
                        <option value="">Sem escola definida</option>
                        @foreach($schools as $school)
                            <option value="{{ $school->id }}" @selected((string) old('school_id', $history->school_id) === (string) $school->id)>{{ $school->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="legal_basis">Fundamento legal</label>
                    <textarea id="legal_basis" name="legal_basis" class="form-control" rows="3" required>{{ old('legal_basis', $history->legal_basis) }}</textarea>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-7">
                        <label for="issued_place">Local</label>
                        <input id="issued_place" name="issued_place" class="form-control" value="{{ old('issued_place', $history->issued_place) }}" required>
                    </div>
                    <div class="form-group col-md-5">
                        <label for="issued_date">Data</label>
                        <input id="issued_date" name="issued_date" type="date" class="form-control" value="{{ old('issued_date', $history->issued_date?->toDateString()) }}" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes">Observações gerais</label>
                    <textarea id="notes" name="notes" class="form-control" rows="5" placeholder="Reclassificação, estudos realizados, observações do documento de origem...">{{ old('notes', $history->notes) }}</textarea>
                </div>

                <div class="custom-control custom-switch">
                    <input id="active" name="active" value="1" type="checkbox" class="custom-control-input" @checked(old('active', $history->active ?? true))>
                    <label class="custom-control-label" for="active">Histórico ativo</label>
                </div>
            </div>
        </div>

        <div class="sge-helper-card">
            <i class="fas fa-lightbulb" aria-hidden="true"></i>
            <div>
                <strong>Como preencher</strong>
                <p>Use uma coluna para cada ano, série, fase ou etapa que aparece no documento recebido. Nos componentes, mantenha os nomes exatamente como vieram da outra escola.</p>
                <p>Se o documento trouxer apenas AP, conceito global ou etapa sem transcrição, selecione o tipo correspondente na coluna e use uma linha de síntese.</p>
            </div>
        </div>
    </aside>
    @endunless

    <div class="sge-history-main" @if($curriculumOnly) style="grid-column: 1 / -1;" @endif>
        <section class="card shadow sge-panel-card mb-4">
            <div class="sge-panel-header">
                <div>
                    <h2>{{ $manualOnly ? 'Séries cursadas fora do sistema' : 'Anos, séries ou fases' }}</h2>
                    <p>{{ $manualOnly ? 'Cadastre somente anos realizados em outras instituições.' : 'Cada cartão abaixo vira uma coluna na tabela do histórico.' }}</p>
                </div>
                <button class="btn btn-sm btn-outline-primary" type="button" data-add-history-year>
                    <i class="fas fa-plus mr-1" aria-hidden="true"></i>Adicionar coluna
                </button>
            </div>
            <div class="card-body">
                <div id="history-years" class="sge-history-year-grid">
                    @foreach($yearsInput as $yearIndex => $year)
                        <article class="sge-history-year-card" data-history-year>
                            <input type="hidden" name="years[{{ $yearIndex }}][source]" value="{{ $year['source'] ?? 'manual' }}">
                            <input type="hidden" name="years[{{ $yearIndex }}][student_enrollment_id]" value="{{ $year['student_enrollment_id'] ?? '' }}">
                            <header>
                                <strong>Coluna {{ $loop->iteration }}</strong>
                                <button class="btn btn-sm btn-outline-danger sge-icon-action" type="button" data-remove-history-year aria-label="Remover esta coluna do histórico" title="Remover coluna">
                                    <i class="fas fa-trash" aria-hidden="true"></i>
                                </button>
                            </header>
                            <div class="form-row">
                                <div class="form-group col-md-5">
                                    <label>Rótulo</label>
                                    <input name="years[{{ $yearIndex }}][label]" class="form-control" value="{{ $year['label'] ?? '' }}" required>
                                </div>
                                <div class="form-group col-md-3">
                                    <label>Ano</label>
                                    <input name="years[{{ $yearIndex }}][year]" data-mask="year" inputmode="numeric" autocomplete="off" class="form-control" value="{{ $year['year'] ?? '' }}" required>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Série/Fase</label>
                                    <input name="years[{{ $yearIndex }}][grade_phase]" class="form-control" value="{{ $year['grade_phase'] ?? '' }}" required>
                                </div>
                                <div class="form-group col-md-12">
                                    <label>Tipo de transcrição</label>
                                    @php($transcriptMode = $year['transcript_mode'] ?? 'detailed')
                                    <select name="years[{{ $yearIndex }}][transcript_mode]" class="form-control" required>
                                        <option value="detailed" @selected($transcriptMode === 'detailed')>Detalhada por componente curricular</option>
                                        <option value="summary" @selected($transcriptMode === 'summary')>Global/AP ou síntese sem componentes detalhados</option>
                                        <option value="no_transcription" @selected($transcriptMode === 'no_transcription')>Etapa sem transcrição no documento recebido</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Etapa</label>
                                    <input name="years[{{ $yearIndex }}][stage]" class="form-control" value="{{ $year['stage'] ?? '' }}" required>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Modalidade</label>
                                    <input name="years[{{ $yearIndex }}][modality]" class="form-control" value="{{ $year['modality'] ?? '' }}" required>
                                </div>
                                <div class="form-group col-md-12">
                                    <label>Escola onde cursou</label>
                                    <input name="years[{{ $yearIndex }}][school_name]" class="form-control" value="{{ $year['school_name'] ?? '' }}" required>
                                </div>
                                <div class="form-group col-md-7">
                                    <label>Ato autorizativo/credenciamento</label>
                                    <textarea name="years[{{ $yearIndex }}][school_authorization]" class="form-control" rows="2" placeholder="Ato, resolução, portaria ou credenciamento da escola">{{ $year['school_authorization'] ?? '' }}</textarea>
                                </div>
                                <div class="form-group col-md-5">
                                    <label>Documento de origem</label>
                                    <input name="years[{{ $yearIndex }}][source_document]" class="form-control" value="{{ $year['source_document'] ?? '' }}" placeholder="Ex.: Histórico nº 123/2025">
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Cidade</label>
                                    <input name="years[{{ $yearIndex }}][city]" class="form-control" value="{{ $year['city'] ?? '' }}" required>
                                </div>
                                <div class="form-group col-md-3">
                                    <label>UF</label>
                                    <select name="years[{{ $yearIndex }}][state]" class="form-control" required>
                                        <option value=""></option>
                                        @foreach($states as $uf => $stateName)
                                            <option value="{{ $uf }}" @selected(($year['state'] ?? '') === $uf)>{{ $uf }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-5">
                                    <label>País</label>
                                    <input name="years[{{ $yearIndex }}][country]" class="form-control" value="{{ $year['country'] ?? 'Brasil' }}" required>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>CH total</label>
                                    <input name="years[{{ $yearIndex }}][workload_hours]" data-mask="decimal" inputmode="decimal" class="form-control" value="{{ $year['workload_hours'] ?? '' }}" required>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Dias letivos</label>
                                    <input name="years[{{ $yearIndex }}][school_days]" data-mask="digits" data-mask-max="3" inputmode="numeric" autocomplete="off" class="form-control" value="{{ $year['school_days'] ?? '' }}">
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Frequência mínima</label>
                                    <div class="input-group">
                                        <input name="years[{{ $yearIndex }}][minimum_attendance_percentage]" data-mask="percentage" inputmode="numeric" class="form-control" value="{{ $year['minimum_attendance_percentage'] ?? '' }}">
                                        <div class="input-group-append"><span class="input-group-text">%</span></div>
                                    </div>
                                </div>
                                <div class="form-group col-md-12">
                                    <label>Resultado final nesta escola/ano</label>
                                    <input name="years[{{ $yearIndex }}][final_result]" class="form-control" value="{{ $year['final_result'] ?? '' }}" required>
                                </div>
                                <div class="form-group col-md-12">
                                    <label>Frequência geral ou observação de frequência</label>
                                    <input name="years[{{ $yearIndex }}][attendance_label]" class="form-control" value="{{ $year['attendance_label'] ?? '' }}" placeholder="Ex.: 92%, frequência suficiente, etapa sem informação">
                                </div>
                                <div class="form-group col-md-12">
                                    <label>Observações desta coluna</label>
                                    <textarea name="years[{{ $yearIndex }}][notes]" class="form-control" rows="2" placeholder="Reclassificação, etapa sem transcrição, aproveitamento global...">{{ $year['notes'] ?? '' }}</textarea>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="card shadow sge-panel-card mb-4">
            <div class="sge-panel-header">
                <div>
                    <h2>Componentes e resultados{{ $manualOnly ? ' externos' : '' }}</h2>
                    <p>Notas, conceitos, frequência, carga horária e resultado final conforme o documento da outra instituição.</p>
                </div>
                <button class="btn btn-sm btn-outline-primary" type="button" data-add-history-component>
                    <i class="fas fa-plus mr-1" aria-hidden="true"></i>Adicionar componente
                </button>
            </div>
            <div class="card-body">
                <div class="sge-history-table-hint">
                    <span><strong>Nota/conceito</strong> é informada por componente.</span>
                    <span><strong>Carga horária e resultado final</strong> são informados uma única vez no cartão do ano letivo.</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm sge-history-edit-table" id="history-components">
                        <thead>
                            <tr>
                                <th style="min-width: 150px;">Formação</th>
                                <th style="min-width: 150px;">Área</th>
                                <th style="min-width: 180px;">Componente</th>
                                @foreach($yearsInput as $year)
                                    <th style="min-width: 230px;">{{ $year['label'] ?? 'Período' }}</th>
                                @endforeach
                                <th class="text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($componentsInput as $componentIndex => $component)
                                <tr data-history-component>
                                    @php($selectedFormation = ($component['formation'] ?? '') === 'Formação Geral Básica' ? 'Formação Geral Básica' : $flexibleFormation)
                                    @php($selectedArea = $component['knowledge_area'] ?? '')
                                    @php($selectedComponent = $component['name'] ?? '')
                                    @php($catalogComponents = collect($bnccComponentsByArea[$selectedArea] ?? []))
                                    <td>
                                        <select name="components[{{ $componentIndex }}][formation]" class="form-control form-control-sm" data-history-formation required>
                                            @foreach($formationOptions as $formation)<option value="{{ $formation }}" @selected($selectedFormation === $formation)>{{ $formation }}</option>@endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="components[{{ $componentIndex }}][knowledge_area]" class="form-control form-control-sm" data-history-area required>
                                            <option value="">Selecione</option>
                                            @foreach($bnccAreas as $area)<option value="{{ $area['name'] }}" @selected($selectedArea === $area['name'])>{{ $area['name'] }}</option>@endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select class="form-control form-control-sm" data-history-component-select @disabled($selectedFormation !== 'Formação Geral Básica')>
                                            <option value="">Selecione</option>
                                            @foreach($catalogComponents as $catalogComponent)<option value="{{ $catalogComponent }}" @selected($selectedComponent === $catalogComponent)>{{ $catalogComponent }}</option>@endforeach
                                            @if($selectedComponent && ! $catalogComponents->contains($selectedComponent))<option value="{{ $selectedComponent }}" selected>{{ $selectedComponent }}</option>@endif
                                        </select>
                                        <input name="components[{{ $componentIndex }}][name]" class="form-control form-control-sm" data-history-component-input value="{{ $selectedComponent }}" @if($selectedFormation === 'Formação Geral Básica') type="hidden" @else type="text" @endif required>
                                    </td>
                                    @foreach($yearsInput as $yearIndex => $year)
                                        @php($record = $component['records'][$yearIndex] ?? [])
                                        <td data-history-record-cell>
                                            <div class="sge-history-record-grid">
                                                <label><span>Nota/conceito</span><input name="components[{{ $componentIndex }}][records][{{ $yearIndex }}][score_label]" class="form-control form-control-sm" value="{{ $record['score_label'] ?? '' }}"></label>
                                            </div>
                                        </td>
                                    @endforeach
                                    <td class="text-right">
                                        <button class="btn btn-sm btn-outline-danger sge-icon-action" type="button" data-remove-history-row aria-label="Remover componente" title="Remover componente">
                                            <i class="fas fa-trash" aria-hidden="true"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</section>

<div class="d-flex justify-content-end mb-4">
    <button class="btn btn-primary" type="submit">
        <i class="fas fa-save mr-1" aria-hidden="true"></i>{{ $history->exists ? 'Salvar histórico' : 'Cadastrar histórico' }}
    </button>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const yearWrapper = document.querySelector('#history-years');
    const table = document.querySelector('#history-components');
    const componentsByArea = @json($bnccComponentsByArea);

    function refreshCurriculumRow(row, resetComponent) {
        const formation = row.querySelector('[data-history-formation]');
        const area = row.querySelector('[data-history-area]');
        const select = row.querySelector('[data-history-component-select]');
        const input = row.querySelector('[data-history-component-input]');
        if (!formation || !area || !select || !input) return;

        const isBasicFormation = formation.value === 'Formação Geral Básica';
        if (isBasicFormation) {
            const current = resetComponent ? '' : input.value;
            select.innerHTML = '<option value="">Selecione</option>';
            (componentsByArea[area.value] || []).forEach(function (component) {
                const option = document.createElement('option');
                option.value = component;
                option.textContent = component;
                option.selected = component === current;
                select.appendChild(option);
            });
            select.disabled = false;
            input.type = 'hidden';
            input.value = select.value;
        } else {
            select.disabled = true;
            input.type = 'text';
            if (resetComponent) input.value = '';
        }
    }

    table?.querySelectorAll('[data-history-component]').forEach(function (row) { refreshCurriculumRow(row, false); });
    table?.addEventListener('change', function (event) {
        const row = event.target.closest('[data-history-component]');
        if (!row) return;
        if (event.target.matches('[data-history-formation], [data-history-area]')) refreshCurriculumRow(row, true);
        if (event.target.matches('[data-history-component-select]')) row.querySelector('[data-history-component-input]').value = event.target.value;
    });

    function replaceGroupIndex(html, group, index) {
        return html.replace(new RegExp(group + '\\[[0-9]+\\]', 'g'), group + '[' + index + ']');
    }

    function renumberYears() {
        if (!yearWrapper || !table) return;

        yearWrapper.querySelectorAll('[data-history-year]').forEach(function (card, yearIndex) {
            card.querySelector('header strong').textContent = 'Coluna ' + (yearIndex + 1);
            card.querySelectorAll('[name^="years["]').forEach(function (field) {
                field.name = field.name.replace(/years\[[0-9]+\]/, 'years[' + yearIndex + ']');
            });
        });

        table.querySelectorAll('tbody tr').forEach(function (row, componentIndex) {
            row.querySelectorAll('[name^="components["]').forEach(function (field) {
                field.name = field.name.replace(/components\[[0-9]+\]/, 'components[' + componentIndex + ']');
                let recordIndex = 0;
                const cell = field.closest('[data-history-record-cell]');
                if (cell) {
                    recordIndex = Array.from(row.querySelectorAll('[data-history-record-cell]')).indexOf(cell);
                    field.name = field.name.replace(/records\[[0-9]+\]/, 'records[' + recordIndex + ']');
                }
            });
        });
    }

    function bindRemove(scope) {
        scope.querySelectorAll('[data-remove-history-row]').forEach(function (button) {
            if (button.dataset.bound) return;
            button.dataset.bound = '1';
            button.addEventListener('click', function () {
                const row = button.closest('[data-history-component]');
                if (row && row.parentElement.children.length > 1) {
                    row.remove();
                    renumberYears();
                }
            });
        });

        scope.querySelectorAll('[data-remove-history-year]').forEach(function (button) {
            if (button.dataset.bound) return;
            button.dataset.bound = '1';
            button.addEventListener('click', function () {
                if (!yearWrapper || !table || yearWrapper.children.length <= 1) return;

                const card = button.closest('[data-history-year]');
                const index = Array.from(yearWrapper.querySelectorAll('[data-history-year]')).indexOf(card);
                card.remove();

                const header = table.querySelector('thead tr')?.children[index + 3];
                header?.remove();

                table.querySelectorAll('tbody tr').forEach(function (row) {
                    row.children[index + 3]?.remove();
                });

                renumberYears();
            });
        });
    }

    bindRemove(document);

    document.querySelector('[data-add-history-component]')?.addEventListener('click', function () {
        const tbody = table?.querySelector('tbody');
        const source = tbody?.querySelector('[data-history-component]:last-child');
        if (!tbody || !source) return;

        const next = tbody.querySelectorAll('[data-history-component]').length;
        const clone = source.cloneNode(true);
        clone.innerHTML = replaceGroupIndex(clone.innerHTML, 'components', next);
        clone.querySelectorAll('input').forEach(function (input) { input.value = ''; });
        clone.querySelectorAll('textarea').forEach(function (textarea) { textarea.value = ''; });
        tbody.appendChild(clone);
        refreshCurriculumRow(clone, true);
        bindRemove(clone);
        renumberYears();
    });

    document.querySelector('[data-add-history-year]')?.addEventListener('click', function () {
        const source = yearWrapper?.querySelector('[data-history-year]:last-child');
        if (!yearWrapper || !source || !table) return;

        const next = yearWrapper.querySelectorAll('[data-history-year]').length;
        const clone = source.cloneNode(true);
        clone.innerHTML = replaceGroupIndex(clone.innerHTML, 'years', next);
        clone.querySelectorAll('input').forEach(function (input) { input.value = ''; });
        clone.querySelectorAll('textarea').forEach(function (textarea) { textarea.value = ''; });
        clone.querySelectorAll('select').forEach(function (select) { select.value = ''; });
        const sourceField = clone.querySelector('[name$="[source]"]');
        if (sourceField) sourceField.value = 'manual';
        const country = clone.querySelector('[name$="[country]"]');
        if (country) country.value = 'Brasil';
        const mode = clone.querySelector('[name$="[transcript_mode]"]');
        if (mode) mode.value = 'detailed';
        yearWrapper.appendChild(clone);
        bindRemove(clone);

        const header = document.createElement('th');
        header.style.minWidth = '230px';
        header.textContent = 'Nova coluna';
        table.querySelector('thead tr').insertBefore(header, table.querySelector('thead tr th:last-child'));

        table.querySelectorAll('tbody tr').forEach(function (row, componentIndex) {
            const cell = document.createElement('td');
            cell.setAttribute('data-history-record-cell', '');
            cell.innerHTML = `
                <div class="sge-history-record-grid">
                    <label><span>Nota/conceito</span><input name="components[${componentIndex}][records][${next}][score_label]" class="form-control form-control-sm"></label>
                </div>`;
            row.insertBefore(cell, row.querySelector('td:last-child'));
        });

        renumberYears();
    });
});
</script>
@endpush
