@extends('layouts.app')

@section('title', __('Central de emissão'))
@section('page-title', __('Central de emissão'))

@section('page-actions')
    @if(auth()->user()->canManagePeople())
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('official-documents.create') }}" aria-label="{{ __('Redigir novo documento oficial') }}" title="{{ __('Redigir documento') }}">
        <i class="fas fa-pen-fancy" aria-hidden="true"></i>
    </a>
    @endif
@endsection

@section('content')
    @php
        $sortLabel = fn (string $label): string => mb_strtolower(\Illuminate\Support\Str::ascii(__($label)));
        $typeGroups = collect($documentTypes)
            ->groupBy('group', true)
            ->map(fn ($types) => $types->sortBy(fn ($type) => $sortLabel($type['label'])))
            ->sortKeysUsing(fn ($left, $right) => strcmp($sortLabel($left), $sortLabel($right)));
        $typeCount = count($documentTypes);
    @endphp

    <section class="sge-document-hub-hero mb-4" aria-labelledby="document-hub-title">
        <div>
            <span class="sge-eyebrow">{{ __('Documentos oficiais') }}</span>
            <h2 id="document-hub-title">{{ __('Encontre e emita sem percorrer todo o sistema') }}</h2>
            <p>
                {{ __('Escolha o documento e localize a pessoa, matrícula, turma ou ano letivo. A emissão mantém o papel timbrado correspondente, o código de autenticidade e as regras de acesso da escola.') }}
            </p>
        </div>
        <div role="group" class="sge-document-hub-summary" aria-label="{{ __('Resumo da central') }}">
            <strong>{{ $typeCount }}</strong>
            <span>{{ __('tipos disponíveis') }}</span>
            <small>{{ $schools->count() }} {{ __('escola(s) ·') }} {{ $academicYears->count() }} {{ __('ano(s) letivo(s) ·') }} {{ $classCount }} {{ __('turma(s) acessíveis') }}</small>
        </div>
    </section>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>{{ __('Não foi possível preparar a emissão.') }}</strong>
            <span class="d-block">{{ __('Revise a seleção e tente novamente.') }}</span>
        </div>
    @endif

    @if(empty($documentTypes))
        <div class="alert alert-info" role="status">{{ __('Seu perfil não possui documentos disponíveis para emissão no momento.') }}</div>
    @endif

    <form method="GET" action="{{ route('document-issuance.issue') }}" id="document-issuance-form"
        class="sge-document-hub-form" target="_blank" rel="noopener" data-download-form="true">
        <section class="sge-document-flow" aria-label="{{ __('Preparar emissão de documento') }}">
            <div class="sge-document-step">
                <div class="sge-document-step-number" aria-hidden="true">1</div>
                <div class="sge-document-step-content">
                    <label for="document-type">{{ __('Qual documento deseja emitir?') }}</label>
                    <select id="document-type" name="type" class="form-control sge-document-type-list" size="8" required aria-describedby="document-type-help">
                        <option value="">{{ __('Selecione o tipo de documento') }}</option>
                        @foreach ($typeGroups as $group => $types)
                            <optgroup label="{{ __($group) }}">
                                @foreach ($types as $key => $type)
                                    <option value="{{ $key }}"
                                        data-target-kind="{{ $type['target'] }}"
                                        data-description="{{ __($type['description']) }}"
                                        data-icon="{{ $type['icon'] }}"
                                        data-score-view="{{ ! empty($type['score_view']) ? 'true' : 'false' }}"
                                        data-month="{{ ! empty($type['month']) ? 'true' : 'false' }}"
                                        data-attendance-scope="{{ ! empty($type['attendance_scope']) ? 'true' : 'false' }}"
                                        @selected(old('type') === $key)>
                                        {{ __($type['label']) }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <p id="document-type-help" class="sge-document-type-help mb-0">
                        {{ __('Selecione uma opção para preparar o documento.') }}
                    </p>
                </div>
            </div>

            <div class="sge-document-step is-muted" id="target-step">
                <div class="sge-document-step-number" aria-hidden="true">2</div>
                <div class="sge-document-step-content">
                    <div class="sge-document-step-heading">
                        <div>
                            <span class="sge-document-step-label">{{ __('Registro') }}</span>
                            <h3 id="target-heading">{{ __('Localize o destinatário') }}</h3>
                        </div>
                        <small>{{ __('Até 40 resultados por página.') }}</small>
                    </div>

                    <div class="sge-document-search">
                        <div class="form-group mb-0">
                            <label for="target-query" id="target-query-label">{{ __('Nome, CPF ou e-mail') }}</label>
                            <input id="target-query" type="search" class="form-control" maxlength="100"
                                autocomplete="off" enterkeyhint="search" disabled>
                        </div>
                        <button type="button" class="btn btn-outline-primary" id="target-search" disabled>
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <span>{{ __('Buscar') }}</span>
                        </button>
                    </div>

                    <p id="target-status" class="sge-document-search-status" role="status" aria-live="polite">
                        {{ __('Primeiro, escolha o tipo de documento.') }}
                    </p>
                    <div id="target-results" class="sge-target-results" role="region" aria-label="{{ __('Resultados da busca') }}"></div>
                    <button type="button" id="target-more" class="btn btn-outline-primary mt-3" hidden>{{ __('Carregar mais pessoas') }}</button>

                    <input type="hidden" name="target_id" id="target-id" value="{{ old('target_id') }}" required>
                    <input type="hidden" name="confirm_missing_student_cpf" id="confirm-missing-student-cpf" value="0">
                    <div id="selected-target" class="sge-selected-target" hidden>
                        <span class="sge-selected-target-icon" aria-hidden="true"><i class="fas fa-check"></i></span>
                        <div>
                            <small>{{ __('Selecionado para emissão') }}</small>
                            <strong id="selected-target-title"></strong>
                            <span id="selected-target-subtitle"></span>
                        </div>
                        <button type="button" class="btn btn-sm btn-link" id="clear-target">
                            {{ __('Trocar') }}
                            <span class="sr-only">{{ __('registro selecionado') }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="sge-document-step is-muted" id="options-step" hidden>
                <div class="sge-document-step-number" aria-hidden="true">3</div>
                <div class="sge-document-step-content">
                    <span class="sge-document-step-label">{{ __('Apresentação') }}</span>
                    <h3>{{ __('Defina as opções do documento') }}</h3>

                    <fieldset id="receipt-options" class="sge-document-options sge-receipt-options" hidden disabled>
                        <legend>{{ __('Dados do recibo de atividade voluntária') }}</legend>
                        <p class="sge-receipt-intro">{{ __('Confira a escola, o período e o valor antes de gerar o recibo.') }}</p>
                        <div class="sge-receipt-fields">
                            <div class="sge-receipt-field sge-receipt-field-wide">
                                <label for="receipt-school">{{ __('Escola emissora') }}</label>
                                <select id="receipt-school" name="receipt_school_id" class="form-control" required>
                                    @foreach ($schools as $school)
                                        <option value="{{ $school->id }}" @selected((string) old('receipt_school_id') === (string) $school->id)>{{ $school->name }}</option>
                                    @endforeach
                                </select>
                                <small>{{ __('Esta escola aparecerá no timbre do recibo.') }}</small>
                            </div>
                            <div class="sge-receipt-field">
                                <label for="period-start">{{ __('Início do período') }}</label>
                                <input id="period-start" name="period_start" type="date" class="form-control" value="{{ old('period_start') }}" required>
                            </div>
                            <div class="sge-receipt-field">
                                <label for="period-end">{{ __('Fim do período') }}</label>
                                <input id="period-end" name="period_end" type="date" class="form-control" value="{{ old('period_end') }}" required>
                            </div>
                            <div class="sge-receipt-field">
                                <label for="receipt-amount">{{ __('Valor do ressarcimento (R$)') }}</label>
                                <input id="receipt-amount" name="amount" type="number" min="0.01" max="999999.99" step="0.01" inputmode="decimal" placeholder="0,00" class="form-control" value="{{ old('amount') }}" required>
                            </div>
                            <div class="sge-receipt-field">
                                <label for="receipt-date">{{ __('Data de emissão') }}</label>
                                <input id="receipt-date" name="issued_date" type="date" class="form-control" value="{{ old('issued_date', now()->toDateString()) }}" required>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset id="score-view-options" class="sge-document-options" hidden>
                        <legend>{{ __('Como apresentar o desempenho?') }}</legend>
                        <label class="sge-choice-tile">
                            <input type="radio" name="score_view" value="conceitos" checked>
                            <span>
                                <strong>{{ __('Conceitos') }}</strong>
                                <small>{{ __('Converte as notas conforme a tabela vigente da escola.') }}</small>
                            </span>
                        </label>
                        <label class="sge-choice-tile">
                            <input type="radio" name="score_view" value="numeros">
                            <span>
                                <strong>{{ __('Notas numéricas') }}</strong>
                                <small>{{ __('Apresenta os valores registrados no sistema.') }}</small>
                            </span>
                        </label>
                    </fieldset>

                    <div id="month-options" class="form-group mb-0" hidden>
                        <label for="document-month">{{ __('Mês da lista de chamada') }}</label>
                        <input id="document-month" name="month" type="month" class="form-control"
                            value="{{ now()->format('Y-m') }}">
                    </div>

                    <fieldset id="attendance-scope-options" class="sge-document-options" hidden>
                        <legend>{{ __('Qual período de frequência deve constar?') }}</legend>
                        <label class="sge-choice-tile">
                            <input type="radio" name="attendance_scope" value="annual" checked>
                            <span>
                                <strong>{{ __('Anual') }}</strong>
                                <small>{{ __('Considera toda a duração do ano letivo selecionado.') }}</small>
                            </span>
                        </label>
                        <label class="sge-choice-tile">
                            <input type="radio" name="attendance_scope" value="period">
                            <span>
                                <strong>{{ __('Período avaliativo') }}</strong>
                                <small>{{ __('Considera somente um bimestre ou outro período cadastrado.') }}</small>
                            </span>
                        </label>
                        <label class="sge-choice-tile">
                            <input type="radio" name="attendance_scope" value="month">
                            <span>
                                <strong>{{ __('Mensal') }}</strong>
                                <small>{{ __('Considera somente o mês escolhido.') }}</small>
                            </span>
                        </label>

                        <div id="attendance-period-option" class="form-group mb-0 mt-3" hidden>
                            <label for="attendance-period">{{ __('Período avaliativo') }}</label>
                            <select id="attendance-period" name="academic_period_id" class="form-control" disabled>
                                <option value="">{{ __('Selecione o período') }}</option>
                                @foreach ($academicPeriods as $period)
                                    <option value="{{ $period->id }}" data-year-id="{{ $period->academic_year_id }}">
                                        {{ $period->name }} · {{ $period->starts_at?->format('d/m/Y') }} {{ __('a') }} {{ $period->ends_at?->format('d/m/Y') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div id="attendance-month-option" class="form-group mb-0 mt-3" hidden>
                            <label for="attendance-month">{{ __('Mês') }}</label>
                            <select id="attendance-month" name="attendance_month" class="form-control" disabled>
                                <option value="">{{ __('Selecione a matrícula ou o ano letivo') }}</option>
                            </select>
                        </div>

                        <label id="federal-aid-only-option" class="sge-choice-tile mt-3" hidden>
                            <input type="checkbox" name="federal_aid_only" value="1" disabled>
                            <span>
                                <strong>{{ __('Somente estudantes que recebem auxílio federal') }}</strong>
                                <small>{{ __('Quando marcado, exclui do relatório os demais estudantes.') }}</small>
                            </span>
                        </label>
                    </fieldset>
                </div>
            </div>

            <div class="sge-document-submit">
                <div>
                    <strong>{{ __('Documento oficial em PDF') }}</strong>
                    <span>{{ __('O documento será aberto no visualizador do navegador para conferência, impressão ou download.') }}</span>
                </div>
                <button type="submit" class="btn btn-primary" id="issue-document" disabled data-loading-label="{{ __('Abrindo PDF...') }}">
                    <i class="fas fa-file-pdf" aria-hidden="true"></i>
                    <span>{{ __('Visualizar documento') }}</span>
                </button>
            </div>
        </section>
    </form>

    <div class="modal fade" id="missing-student-cpf-modal" tabindex="-1" role="dialog"
        aria-labelledby="missing-student-cpf-title" aria-describedby="missing-student-cpf-description" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="missing-student-cpf-title">{{ __('Documento sem CPF do estudante') }}</h2>
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('Fechar') }}">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="missing-student-cpf-description">
                    <p>{{ __('O estudante selecionado ainda não possui CPF cadastrado.') }}</p>
                    <p class="mb-0">{{ __('O documento será emitido sem esse dado. O CPF da mãe ou do pai já foi conferido pelo sistema.') }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">{{ __('Cancelar') }}</button>
                    <button type="button" class="btn btn-primary" id="confirm-missing-student-cpf-button">
                        <i class="fas fa-check" aria-hidden="true"></i>
                        <span>{{ __('Estou ciente e emitir') }}</span>
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
            const targetStep = document.getElementById('target-step');
            const optionsStep = document.getElementById('options-step');
            const academicYears = @json($academicYears);
            const queryInput = document.getElementById('target-query');
            const queryLabel = document.getElementById('target-query-label');
            const targetHeading = document.getElementById('target-heading');
            const searchButton = document.getElementById('target-search');
            const status = document.getElementById('target-status');
            const results = document.getElementById('target-results');
            const moreButton = document.getElementById('target-more');
            const receiptSchool = document.getElementById('receipt-school');
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
            const federalAidOnlyOption = document.getElementById('federal-aid-only-option');
            const federalAidOnlyInput = federalAidOnlyOption.querySelector('input');
            const targetsUrl = @json(route('document-issuance.targets'));
            const targetCopy = {
                enrollment: [@js(__('Localize a matrícula')), @js(__('Nome ou CPF do estudante'))],
                course: [@js(__('Localize a matriz')), @js(__('Nome da matriz'))],
                direct: [@js(__('Abrir documento')), @js(__('Opções disponíveis'))],
                listing: [@js(__('Escolha o formato')), @js(__('PDF ou Excel'))],
                own_enrollment: [@js(__('Selecione sua matrícula')), @js(__('Suas matrículas'))],
                person: [@js(__('Localize a pessoa')), @js(__('Nome, CPF ou e-mail institucional'))],
                history: [@js(__('Localize o histórico')), @js(__('Nome do estudante, etapa ou título'))],
                class: [@js(__('Localize a turma')), @js(__('Nome da turma'))],
                academic_year: [@js(__('Localize o ano letivo')), @js(__('Nome ou ano de referência'))],
                school: [@js(__('Localize a escola')), @js(__('Nome da escola'))],
                diary: [@js(__('Localize o diário')), @js(__('Componente, turma ou docente'))],
            };
            let searchController = null;
            let selectedAcademicYearId = '';
            let selectedNeedsCpfConfirmation = false;
            let currentPage = 1;
            let lastSearchTerm = '';

            const selectedOption = () => typeSelect.options[typeSelect.selectedIndex];
            const targetKind = () => selectedOption()?.dataset.targetKind || '';

            const resetTarget = (message = @js(__('Use a busca para localizar o registro correto.'))) => {
                targetId.value = '';
                selectedTarget.hidden = true;
                selectedTitle.textContent = '';
                selectedSubtitle.textContent = '';
                selectedAcademicYearId = '';
                selectedNeedsCpfConfirmation = false;
                missingCpfConfirmation.value = '0';
                issueButton.disabled = true;
                results.replaceChildren();
                moreButton.hidden = true;
                Array.from(receiptSchool.options).forEach((option) => { option.disabled = false; });
                status.textContent = message;
            };

            const syncAttendanceOptions = () => {
                const enabled = selectedOption()?.dataset.attendanceScope === 'true';
                const scope = attendanceScopeOptions.querySelector('input[name="attendance_scope"]:checked')?.value || 'annual';
                const academicYearId = selectedAcademicYearId;

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

                const aidFilterEnabled = selectedOption()?.value === 'attendance-report';
                federalAidOnlyOption.hidden = !aidFilterEnabled;
                federalAidOnlyInput.disabled = !aidFilterEnabled;
                if (!aidFilterEnabled) federalAidOnlyInput.checked = false;

                const year = academicYears.find((item) => String(item.id) === String(academicYearId));
                const minimum = year ? (year.starts_at?.slice(0, 7) || `${year.reference_year}-01`) : '';
                const maximum = year ? (year.ends_at?.slice(0, 7) || `${year.reference_year}-12`) : '';
                const previousMonth = attendanceMonthInput.value;
                attendanceMonthInput.replaceChildren(new Option(@js(__('Selecione o mês')), ''));
                if (minimum && maximum) {
                    let [year, month] = minimum.split('-').map(Number);
                    while (`${year}-${String(month).padStart(2, '0')}` <= maximum) {
                        const value = `${year}-${String(month).padStart(2, '0')}`;
                        const label = new Intl.DateTimeFormat(@js(str_replace('_', '-', app()->getLocale())), { month: 'long', year: 'numeric' })
                            .format(new Date(year, month - 1, 1));
                        attendanceMonthInput.add(new Option(label, value));
                        if (++month > 12) { month = 1; year++; }
                    }
                    const currentMonth = @json(now()->format('Y-m'));
                    attendanceMonthInput.value = previousMonth >= minimum && previousMonth <= maximum
                        ? previousMonth
                        : (currentMonth < minimum ? minimum : (currentMonth > maximum ? maximum : currentMonth));
                }

            };

            const updateType = () => {
                const option = selectedOption();
                const kind = targetKind();
                const hasType = Boolean(option?.value && kind);

                typeHelp.textContent = hasType ? option.dataset.description : @js(__('Selecione uma opção para preparar o documento.'));
                targetStep.classList.toggle('is-muted', !hasType);
                queryInput.disabled = !hasType;
                searchButton.disabled = !hasType;

                const copy = targetCopy[kind] || [@js(__('Localize o registro')), @js(__('Digite para buscar'))];
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
                const hasReceiptOptions = typeSelect.value === 'volunteer-receipt';
                const receiptOptions = document.getElementById('receipt-options');
                receiptOptions.hidden = !hasReceiptOptions;
                receiptOptions.disabled = !hasReceiptOptions;
                optionsStep.hidden = !hasScoreOptions && !hasMonthOptions && !hasAttendanceOptions && !hasReceiptOptions;
                optionsStep.classList.toggle('is-muted', !hasType);

                resetTarget(hasType ? @js(__('Use a busca para localizar o registro correto.')) : @js(__('Primeiro, escolha o tipo de documento.')));
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
                moreButton.hidden = true;
                status.textContent = `${target.title} selecionado para emissão.`;
                if (typeSelect.value === 'volunteer-receipt') {
                    const allowedSchools = (target.school_ids || []).map(String);
                    Array.from(receiptSchool.options).forEach((option) => {
                        option.disabled = !allowedSchools.includes(option.value);
                    });
                    if (receiptSchool.selectedOptions[0]?.disabled) {
                        receiptSchool.value = allowedSchools[0] || '';
                    }
                }
                syncAttendanceOptions();
                selectedTarget.focus?.();
            };

            const renderResults = (targets, append = false, hasMore = false) => {
                if (!append) results.replaceChildren();

                if (!targets.length && !results.childElementCount) {
                    status.textContent = @js(__('Nenhum registro encontrado.'));
                    moreButton.hidden = true;
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
                    action.textContent = target.enabled ? @js(__('Selecionar')) : @js(__('Indisponível'));
                    item.append(icon, content, action);
                    fragment.appendChild(item);
                });

                const firstNewResult = fragment.querySelector('button');
                results.appendChild(fragment);
                moreButton.hidden = !hasMore;
                const count = results.childElementCount;
                status.textContent = `${count} ${count === 1 ? @js(__('resultado encontrado')) : @js(__('resultados encontrados'))}${hasMore ? @js(__(' · Há mais pessoas disponíveis.')) : '.'}`;
                (append ? firstNewResult : results.querySelector('button'))?.focus();
            };

            const searchTargets = async (append = false) => {
                if (!typeSelect.value) return;
                const term = queryInput.value.trim();
                if (append && term !== lastSearchTerm) return searchTargets();

                searchController?.abort();
                searchController = new AbortController();
                if (append) {
                    status.textContent = @js(__('Buscando mais pessoas...'));
                    moreButton.disabled = true;
                } else {
                    currentPage = 1;
                    lastSearchTerm = term;
                    resetTarget(@js(__('Buscando registros...')));
                }
                searchButton.disabled = true;
                searchButton.setAttribute('aria-busy', 'true');

                const parameters = new URLSearchParams({ type: typeSelect.value });
                if (term) parameters.set('q', term);
                if (append) parameters.set('page', String(currentPage + 1));

                try {
                    const response = await fetch(`${targetsUrl}?${parameters.toString()}`, {
                        headers: { Accept: 'application/json' },
                        signal: searchController.signal,
                    });
                    if (!response.ok) {
                        const messages = {
                            401: @js(__('Sua sessão expirou. Atualize a página e entre novamente.')),
                            403: @js(__('Você não tem permissão para consultar esses registros.')),
                            422: @js(__('Os filtros informados não são válidos. Revise a seleção.')),
                        };

                        throw new Error(messages[response.status] || @js(__('Não foi possível concluir a busca.')));
                    }
                    const payload = await response.json();
                    if (append) currentPage++;
                    renderResults(payload.targets || [], append, payload.has_more === true);
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        status.textContent = error.message || @js(__('Não foi possível concluir a busca.'));
                    }
                } finally {
                    searchButton.disabled = false;
                    searchButton.removeAttribute('aria-busy');
                    moreButton.disabled = false;
                }
            };

            typeSelect.addEventListener('change', () => {
                searchController?.abort();
                updateType();
                if (['direct', 'listing', 'own_enrollment'].includes(targetKind())) searchTargets();
            });
            attendanceScopeOptions.querySelectorAll('input[name="attendance_scope"]').forEach((input) => {
                input.addEventListener('change', syncAttendanceOptions);
            });
            searchButton.addEventListener('click', () => searchTargets());
            moreButton.addEventListener('click', () => searchTargets(true));
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
                    status.textContent = @js(__('Selecione um registro antes de emitir o documento.'));
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
