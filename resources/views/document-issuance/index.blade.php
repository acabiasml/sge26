@extends('layouts.app')

@section('title', 'Central de emissão')
@section('page-title', 'Central de emissão')

@section('page-actions')
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('official-documents.create') }}" aria-label="Redigir novo documento oficial" title="Redigir documento">
        <i class="fas fa-pen-fancy" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    @php
        $typeGroups = collect($documentTypes)->groupBy('group', true);
        $typeCount = count($documentTypes);
    @endphp

    <section class="sge-document-hub-hero mb-4" aria-labelledby="document-hub-title">
        <div>
            <span class="sge-eyebrow">Documentos oficiais</span>
            <h2 id="document-hub-title">Encontre e emita sem percorrer todo o sistema</h2>
            <p>
                Escolha o documento e localize a pessoa, matrícula, turma ou ano letivo. A emissão mantém o papel
                timbrado correspondente, o código de autenticidade e as regras de acesso da escola.
            </p>
        </div>
        <div class="sge-document-hub-summary" aria-label="Resumo da central">
            <strong>{{ $typeCount }}</strong>
            <span>tipos disponíveis</span>
            <small>{{ $schools->count() }} escola(s) · {{ $academicYears->count() }} ano(s) letivo(s) · {{ $classes->count() }} turma(s) acessíveis</small>
        </div>
    </section>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>Não foi possível preparar a emissão.</strong>
            <span class="d-block">Revise a seleção e tente novamente.</span>
        </div>
    @endif

    <form method="GET" action="{{ route('document-issuance.issue') }}" id="document-issuance-form"
        class="sge-document-hub-form" target="_blank" rel="noopener" data-download-form="true">
        <section class="sge-document-flow" aria-label="Preparar emissão de documento">
            <div class="sge-document-step">
                <div class="sge-document-step-number" aria-hidden="true">1</div>
                <div class="sge-document-step-content">
                    <label for="document-type">Qual documento deseja emitir?</label>
                    <select id="document-type" name="type" class="form-control" required aria-describedby="document-type-help">
                        <option value="">Selecione o tipo de documento</option>
                        @foreach ($typeGroups as $group => $types)
                            <optgroup label="{{ $group }}">
                                @foreach ($types as $key => $type)
                                    <option value="{{ $key }}"
                                        data-target-kind="{{ $type['target'] }}"
                                        data-description="{{ $type['description'] }}"
                                        data-icon="{{ $type['icon'] }}"
                                        data-score-view="{{ ! empty($type['score_view']) ? 'true' : 'false' }}"
                                        data-month="{{ ! empty($type['month']) ? 'true' : 'false' }}"
                                        data-attendance-scope="{{ ! empty($type['attendance_scope']) ? 'true' : 'false' }}"
                                        @selected(old('type') === $key)>
                                        {{ $type['label'] }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <p id="document-type-help" class="sge-document-type-help mb-0">
                        Selecione uma opção para ver os filtros adequados.
                    </p>
                </div>
            </div>

            <div class="sge-document-step is-muted" id="context-step">
                <div class="sge-document-step-number" aria-hidden="true">2</div>
                <div class="sge-document-step-content">
                    <div class="sge-document-step-heading">
                        <div>
                            <span class="sge-document-step-label">Contexto</span>
                            <h3>Restrinja a busca</h3>
                        </div>
                        <small>Os filtros são opcionais.</small>
                    </div>

                    <div class="sge-document-context-grid">
                        <div class="form-group mb-0" data-context-filter="school">
                            <label for="document-school">Escola</label>
                            <select id="document-school" class="form-control">
                                @if ($schools->count() > 1)
                                    <option value="">Todas as escolas acessíveis</option>
                                @endif
                                @foreach ($schools as $school)
                                    <option value="{{ $school->id }}" @selected($schools->count() === 1)>{{ $school->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group mb-0" data-context-filter="year">
                            <label for="document-year">Ano letivo</label>
                            <select id="document-year" class="form-control">
                                <option value="">Todos os anos letivos</option>
                                @foreach ($academicYears as $year)
                                    <option value="{{ $year->id }}" data-school-id="{{ $year->school_id }}"
                                        data-starts-at="{{ $year->starts_at?->format('Y-m-d') }}"
                                        data-ends-at="{{ $year->ends_at?->format('Y-m-d') }}">
                                        {{ $year->referenceYearsLabel() }} · {{ $year->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group mb-0" data-context-filter="class">
                            <label for="document-class">Turma</label>
                            <select id="document-class" class="form-control">
                                <option value="">Todas as turmas</option>
                                @foreach ($classes as $class)
                                    <option value="{{ $class->id }}"
                                        data-year-id="{{ $class->academic_year_id }}"
                                        data-school-id="{{ $class->academicYear?->school_id }}">
                                        {{ \App\Support\AcademicContextLabel::classWithStages($class->name, $class->courses) }} · {{ $class->academicYear?->referenceYearsLabel() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sge-document-step is-muted" id="target-step">
                <div class="sge-document-step-number" aria-hidden="true">3</div>
                <div class="sge-document-step-content">
                    <div class="sge-document-step-heading">
                        <div>
                            <span class="sge-document-step-label">Registro</span>
                            <h3 id="target-heading">Localize o destinatário</h3>
                        </div>
                        <small>Até 40 resultados por busca.</small>
                    </div>

                    <div class="sge-document-search">
                        <div class="form-group mb-0">
                            <label for="target-query" id="target-query-label">Nome, CPF ou e-mail</label>
                            <input id="target-query" type="search" class="form-control" maxlength="100"
                                autocomplete="off" enterkeyhint="search" disabled>
                        </div>
                        <button type="button" class="btn btn-outline-primary" id="target-search" disabled>
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <span>Buscar</span>
                        </button>
                    </div>

                    <p id="target-status" class="sge-document-search-status" role="status" aria-live="polite">
                        Primeiro, escolha o tipo de documento.
                    </p>
                    <div id="target-results" class="sge-target-results" aria-label="Resultados da busca"></div>

                    <input type="hidden" name="target_id" id="target-id" value="{{ old('target_id') }}" required>
                    <input type="hidden" name="confirm_missing_student_cpf" id="confirm-missing-student-cpf" value="0">
                    <div id="selected-target" class="sge-selected-target" hidden>
                        <span class="sge-selected-target-icon" aria-hidden="true"><i class="fas fa-check"></i></span>
                        <div>
                            <small>Selecionado para emissão</small>
                            <strong id="selected-target-title"></strong>
                            <span id="selected-target-subtitle"></span>
                        </div>
                        <button type="button" class="btn btn-sm btn-link" id="clear-target">
                            Trocar
                            <span class="sr-only">registro selecionado</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="sge-document-step is-muted" id="options-step" hidden>
                <div class="sge-document-step-number" aria-hidden="true">4</div>
                <div class="sge-document-step-content">
                    <span class="sge-document-step-label">Apresentação</span>
                    <h3>Defina as opções do documento</h3>

                    <fieldset id="score-view-options" class="sge-document-options" hidden>
                        <legend>Como apresentar o desempenho?</legend>
                        <label class="sge-choice-tile">
                            <input type="radio" name="score_view" value="conceitos" checked>
                            <span>
                                <strong>Conceitos</strong>
                                <small>Converte as notas conforme a tabela vigente da escola.</small>
                            </span>
                        </label>
                        <label class="sge-choice-tile">
                            <input type="radio" name="score_view" value="numeros">
                            <span>
                                <strong>Notas numéricas</strong>
                                <small>Apresenta os valores registrados no sistema.</small>
                            </span>
                        </label>
                    </fieldset>

                    <div id="month-options" class="form-group mb-0" hidden>
                        <label for="document-month">Mês da lista de chamada</label>
                        <input id="document-month" name="month" type="month" class="form-control"
                            value="{{ now()->format('Y-m') }}">
                    </div>

                    <fieldset id="attendance-scope-options" class="sge-document-options" hidden>
                        <legend>Qual período de frequência deve constar?</legend>
                        <label class="sge-choice-tile">
                            <input type="radio" name="attendance_scope" value="annual" checked>
                            <span>
                                <strong>Anual</strong>
                                <small>Considera toda a duração do ano letivo selecionado.</small>
                            </span>
                        </label>
                        <label class="sge-choice-tile">
                            <input type="radio" name="attendance_scope" value="period">
                            <span>
                                <strong>Período avaliativo</strong>
                                <small>Considera somente um bimestre ou outro período cadastrado.</small>
                            </span>
                        </label>
                        <label class="sge-choice-tile">
                            <input type="radio" name="attendance_scope" value="month">
                            <span>
                                <strong>Mensal</strong>
                                <small>Considera somente o mês escolhido.</small>
                            </span>
                        </label>

                        <div id="attendance-period-option" class="form-group mb-0 mt-3" hidden>
                            <label for="attendance-period">Período avaliativo</label>
                            <select id="attendance-period" name="academic_period_id" class="form-control" disabled>
                                <option value="">Selecione o período</option>
                                @foreach ($academicPeriods as $period)
                                    <option value="{{ $period->id }}" data-year-id="{{ $period->academic_year_id }}">
                                        {{ $period->name }} · {{ $period->starts_at?->format('d/m/Y') }} a {{ $period->ends_at?->format('d/m/Y') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div id="attendance-month-option" class="form-group mb-0 mt-3" hidden>
                            <label for="attendance-month">Mês</label>
                            <input id="attendance-month" name="attendance_month" type="month" class="form-control" disabled>
                        </div>
                    </fieldset>
                </div>
            </div>

            <div class="sge-document-submit">
                <div>
                    <strong>Documento oficial em PDF</strong>
                    <span>O documento será aberto no visualizador do navegador para conferência, impressão ou download.</span>
                </div>
                <button type="submit" class="btn btn-primary" id="issue-document" disabled data-loading-label="Abrindo PDF...">
                    <i class="fas fa-file-pdf" aria-hidden="true"></i>
                    <span>Visualizar documento</span>
                </button>
            </div>
        </section>
    </form>

    <div class="modal fade" id="missing-student-cpf-modal" tabindex="-1" role="dialog"
        aria-labelledby="missing-student-cpf-title" aria-describedby="missing-student-cpf-description" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="missing-student-cpf-title">Documento sem CPF do estudante</h2>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="missing-student-cpf-description">
                    <p>O estudante selecionado ainda não possui CPF cadastrado.</p>
                    <p class="mb-0">O documento será emitido sem esse dado. O CPF da mãe ou do pai já foi conferido pelo sistema.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="confirm-missing-student-cpf-button">
                        <i class="fas fa-check" aria-hidden="true"></i>
                        <span>Estou ciente e emitir</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.getElementById('document-issuance-form');
            const typeSelect = document.getElementById('document-type');
            const typeHelp = document.getElementById('document-type-help');
            const contextStep = document.getElementById('context-step');
            const targetStep = document.getElementById('target-step');
            const optionsStep = document.getElementById('options-step');
            const schoolSelect = document.getElementById('document-school');
            const yearSelect = document.getElementById('document-year');
            const classSelect = document.getElementById('document-class');
            const queryInput = document.getElementById('target-query');
            const queryLabel = document.getElementById('target-query-label');
            const targetHeading = document.getElementById('target-heading');
            const searchButton = document.getElementById('target-search');
            const status = document.getElementById('target-status');
            const results = document.getElementById('target-results');
            const targetId = document.getElementById('target-id');
            const missingCpfConfirmation = document.getElementById('confirm-missing-student-cpf');
            const missingCpfModal = document.getElementById('missing-student-cpf-modal');
            const confirmMissingCpfButton = document.getElementById('confirm-missing-student-cpf-button');
            const selectedTarget = document.getElementById('selected-target');
            const selectedTitle = document.getElementById('selected-target-title');
            const selectedSubtitle = document.getElementById('selected-target-subtitle');
            const clearTargetButton = document.getElementById('clear-target');
            const issueButton = document.getElementById('issue-document');
            const scoreOptions = document.getElementById('score-view-options');
            const monthOptions = document.getElementById('month-options');
            const monthInput = document.getElementById('document-month');
            const attendanceScopeOptions = document.getElementById('attendance-scope-options');
            const attendancePeriodOption = document.getElementById('attendance-period-option');
            const attendancePeriodSelect = document.getElementById('attendance-period');
            const attendanceMonthOption = document.getElementById('attendance-month-option');
            const attendanceMonthInput = document.getElementById('attendance-month');
            const targetsUrl = @json(route('document-issuance.targets'));
            const targetCopy = {
                enrollment: ['Localize a matrícula', 'Nome ou CPF do estudante'],
                person: ['Localize a pessoa', 'Nome, CPF ou e-mail institucional'],
                history: ['Localize o histórico', 'Nome do estudante, etapa ou título'],
                class: ['Localize a turma', 'Nome da turma'],
                academic_year: ['Localize o ano letivo', 'Nome ou ano de referência'],
                school: ['Localize a escola', 'Nome da escola'],
                diary: ['Localize o diário', 'Componente, turma ou docente'],
            };
            const contextVisibility = {
                enrollment: ['school', 'year', 'class'],
                person: ['school'],
                history: ['school'],
                class: ['school', 'year', 'class'],
                academic_year: ['school', 'year'],
                school: [],
                diary: ['school', 'year', 'class'],
            };
            let searchController = null;
            let selectedAcademicYearId = '';
            let selectedNeedsCpfConfirmation = false;

            const selectedOption = () => typeSelect.options[typeSelect.selectedIndex];
            const targetKind = () => selectedOption()?.dataset.targetKind || '';

            const resetTarget = (message = 'Use a busca para localizar o registro correto.') => {
                targetId.value = '';
                selectedTarget.hidden = true;
                selectedTitle.textContent = '';
                selectedSubtitle.textContent = '';
                selectedAcademicYearId = '';
                selectedNeedsCpfConfirmation = false;
                missingCpfConfirmation.value = '0';
                issueButton.disabled = true;
                results.replaceChildren();
                status.textContent = message;
            };

            const syncAttendanceOptions = () => {
                const enabled = selectedOption()?.dataset.attendanceScope === 'true';
                const scope = attendanceScopeOptions.querySelector('input[name="attendance_scope"]:checked')?.value || 'annual';
                const academicYearId = selectedAcademicYearId || yearSelect.value;

                Array.from(attendancePeriodSelect.options).forEach((option) => {
                    if (!option.value) return;
                    option.hidden = Boolean(academicYearId && option.dataset.yearId !== String(academicYearId));
                });
                if (attendancePeriodSelect.selectedOptions[0]?.hidden) attendancePeriodSelect.value = '';

                const periodEnabled = enabled && scope === 'period';
                attendancePeriodOption.hidden = !periodEnabled;
                attendancePeriodSelect.disabled = !periodEnabled;
                attendancePeriodSelect.required = periodEnabled;

                const monthEnabled = enabled && scope === 'month';
                attendanceMonthOption.hidden = !monthEnabled;
                attendanceMonthInput.disabled = !monthEnabled;
                attendanceMonthInput.required = monthEnabled;

                const yearOption = Array.from(yearSelect.options).find((option) => option.value === String(academicYearId));
                attendanceMonthInput.min = yearOption?.dataset.startsAt?.slice(0, 7) || '';
                attendanceMonthInput.max = yearOption?.dataset.endsAt?.slice(0, 7) || '';
                if (monthEnabled && !attendanceMonthInput.value) {
                    const currentMonth = @json(now()->format('Y-m'));
                    const minimum = attendanceMonthInput.min;
                    const maximum = attendanceMonthInput.max;
                    attendanceMonthInput.value = minimum && currentMonth < minimum
                        ? minimum
                        : (maximum && currentMonth > maximum ? maximum : currentMonth);
                }
            };

            const syncFilterOptions = () => {
                const schoolId = schoolSelect.value;

                Array.from(yearSelect.options).forEach((option) => {
                    if (!option.value) return;
                    option.hidden = Boolean(schoolId && option.dataset.schoolId !== schoolId);
                });
                if (yearSelect.selectedOptions[0]?.hidden) yearSelect.value = '';

                const yearId = yearSelect.value;
                Array.from(classSelect.options).forEach((option) => {
                    if (!option.value) return;
                    option.hidden = Boolean((schoolId && option.dataset.schoolId !== schoolId)
                        || (yearId && option.dataset.yearId !== yearId));
                });
                if (classSelect.selectedOptions[0]?.hidden) classSelect.value = '';
            };

            const updateType = () => {
                const option = selectedOption();
                const kind = targetKind();
                const hasType = Boolean(option?.value && kind);
                const visibleFilters = contextVisibility[kind] || [];

                typeHelp.textContent = hasType ? option.dataset.description : 'Selecione uma opção para ver os filtros adequados.';
                contextStep.classList.toggle('is-muted', !hasType);
                targetStep.classList.toggle('is-muted', !hasType);
                queryInput.disabled = !hasType;
                searchButton.disabled = !hasType;

                document.querySelectorAll('[data-context-filter]').forEach((filter) => {
                    filter.hidden = !visibleFilters.includes(filter.dataset.contextFilter);
                });

                const copy = targetCopy[kind] || ['Localize o registro', 'Digite para buscar'];
                targetHeading.textContent = copy[0];
                queryLabel.textContent = copy[1];
                queryInput.placeholder = hasType ? copy[1] : '';

                const hasScoreOptions = option?.dataset.scoreView === 'true';
                const hasMonthOptions = option?.dataset.month === 'true';
                const hasAttendanceOptions = option?.dataset.attendanceScope === 'true';
                scoreOptions.hidden = !hasScoreOptions;
                scoreOptions.disabled = !hasScoreOptions;
                monthOptions.hidden = !hasMonthOptions;
                monthInput.disabled = !hasMonthOptions;
                attendanceScopeOptions.hidden = !hasAttendanceOptions;
                attendanceScopeOptions.disabled = !hasAttendanceOptions;
                optionsStep.hidden = !hasScoreOptions && !hasMonthOptions && !hasAttendanceOptions;
                optionsStep.classList.toggle('is-muted', !hasType);

                resetTarget(hasType ? 'Use a busca para localizar o registro correto.' : 'Primeiro, escolha o tipo de documento.');
                syncFilterOptions();
                syncAttendanceOptions();
            };

            const selectResult = (target) => {
                targetId.value = target.id;
                selectedTitle.textContent = target.title;
                selectedSubtitle.textContent = target.subtitle || '';
                selectedAcademicYearId = target.academic_year_id ? String(target.academic_year_id) : '';
                selectedNeedsCpfConfirmation = target.missing_student_cpf === true;
                missingCpfConfirmation.value = '0';
                selectedTarget.hidden = false;
                issueButton.disabled = false;
                results.replaceChildren();
                status.textContent = `${target.title} selecionado para emissão.`;
                syncAttendanceOptions();
                selectedTarget.focus?.();
            };

            const renderResults = (targets) => {
                results.replaceChildren();

                if (!targets.length) {
                    status.textContent = 'Nenhum registro encontrado com esses filtros.';
                    return;
                }

                const fragment = document.createDocumentFragment();
                targets.forEach((target) => {
                    const item = document.createElement(target.enabled ? 'button' : 'div');
                    item.className = `sge-target-result${target.enabled ? '' : ' is-disabled'}`;

                    if (target.enabled) {
                        item.type = 'button';
                        item.addEventListener('click', () => selectResult(target));
                    } else {
                        item.setAttribute('aria-disabled', 'true');
                    }

                    const icon = document.createElement('span');
                    icon.className = 'sge-target-result-icon';
                    icon.setAttribute('aria-hidden', 'true');
                    const iconGlyph = document.createElement('i');
                    iconGlyph.className = `fas ${target.enabled ? 'fa-file-alt' : 'fa-lock'}`;
                    icon.appendChild(iconGlyph);

                    const content = document.createElement('span');
                    content.className = 'sge-target-result-content';
                    const title = document.createElement('strong');
                    title.textContent = target.title;
                    const subtitle = document.createElement('span');
                    subtitle.textContent = target.subtitle || '';
                    content.append(title, subtitle);

                    if (target.reason) {
                        const reason = document.createElement('small');
                        reason.textContent = target.reason;
                        content.appendChild(reason);
                    }

                    const action = document.createElement('span');
                    action.className = 'sge-target-result-action';
                    action.textContent = target.enabled ? 'Selecionar' : 'Indisponível';
                    item.append(icon, content, action);
                    fragment.appendChild(item);
                });

                results.appendChild(fragment);
                status.textContent = `${targets.length} ${targets.length === 1 ? 'resultado encontrado' : 'resultados encontrados'}.`;
                results.querySelector('button')?.focus();
            };

            const searchTargets = async () => {
                if (!typeSelect.value) return;

                searchController?.abort();
                searchController = new AbortController();
                resetTarget('Buscando registros...');
                searchButton.disabled = true;
                searchButton.setAttribute('aria-busy', 'true');

                const parameters = new URLSearchParams({ type: typeSelect.value });
                if (queryInput.value.trim()) parameters.set('q', queryInput.value.trim());
                if (!document.querySelector('[data-context-filter="school"]').hidden && schoolSelect.value) parameters.set('school_id', schoolSelect.value);
                if (!document.querySelector('[data-context-filter="year"]').hidden && yearSelect.value) parameters.set('academic_year_id', yearSelect.value);
                if (!document.querySelector('[data-context-filter="class"]').hidden && classSelect.value) parameters.set('class_id', classSelect.value);

                try {
                    const response = await fetch(`${targetsUrl}?${parameters.toString()}`, {
                        headers: { Accept: 'application/json' },
                        signal: searchController.signal,
                    });
                    if (!response.ok) {
                        const messages = {
                            401: 'Sua sessão expirou. Atualize a página e entre novamente.',
                            403: 'Você não tem permissão para consultar esses registros.',
                            422: 'Os filtros informados não são válidos. Revise a seleção.',
                        };

                        throw new Error(messages[response.status] || 'Não foi possível concluir a busca.');
                    }
                    const payload = await response.json();
                    renderResults(payload.targets || []);
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        status.textContent = error.message || 'Não foi possível concluir a busca.';
                    }
                } finally {
                    searchButton.disabled = false;
                    searchButton.removeAttribute('aria-busy');
                }
            };

            typeSelect.addEventListener('change', updateType);
            schoolSelect.addEventListener('change', () => {
                yearSelect.value = '';
                classSelect.value = '';
                syncFilterOptions();
                syncAttendanceOptions();
                resetTarget();
            });
            yearSelect.addEventListener('change', () => {
                classSelect.value = '';
                syncFilterOptions();
                syncAttendanceOptions();
                resetTarget();
            });
            classSelect.addEventListener('change', () => resetTarget());
            attendanceScopeOptions.querySelectorAll('input[name="attendance_scope"]').forEach((input) => {
                input.addEventListener('change', syncAttendanceOptions);
            });
            searchButton.addEventListener('click', searchTargets);
            queryInput.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    searchTargets();
                }
            });
            clearTargetButton.addEventListener('click', () => {
                resetTarget();
                queryInput.focus();
            });
            form.addEventListener('submit', (event) => {
                if (!targetId.value) {
                    event.preventDefault();
                    status.textContent = 'Selecione um registro antes de emitir o documento.';
                    queryInput.focus();
                    return;
                }

                if (selectedNeedsCpfConfirmation && missingCpfConfirmation.value !== '1') {
                    event.preventDefault();
                    window.jQuery(missingCpfModal).modal('show');
                }
            });
            confirmMissingCpfButton.addEventListener('click', () => {
                missingCpfConfirmation.value = '1';
                window.jQuery(missingCpfModal).modal('hide');
                form.requestSubmit();
            });

            updateType();
        })();
    </script>
@endpush
