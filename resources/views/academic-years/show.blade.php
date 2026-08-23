@extends('layouts.app')

@php($canChangeCalendar = ! $academicYear->isClosed() && (! $academicYear->approved_at || auth()->user()->isAdministrator()))
@php($schoolDays = $academicYear->schoolDayCount())
@php($calendarMonths = \App\Support\AcademicCalendarGrid::forAcademicYear($academicYear))
@php($readyCourses = $academicYear->courses->filter->hasMatrixComponents())
@php($closureErrors = collect($closureIssues)->where('level', 'error'))
@php($closureWarnings = collect($closureIssues)->where('level', 'warning'))
@php($activeClasses = $academicYear->classes->where('active', true))
@php($enrollmentCount = $academicYear->classes->sum(fn ($class) => $class->enrollments->count()))
@php($defaultAcademicYearTab = $errors->hasAny(['date', 'date_end', 'type', 'counts_as_school_day', 'title', 'description']) ? 'calendario' : ($errors->hasAny(['approved_at', 'closed_at', 'closure_notes', 'reopen_reason']) ? 'gestao' : 'resumo'))

@section('title', $academicYear->name)
@section('page-title', $academicYear->name)

@section('page-actions')
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('academic-years.calendar-pdf', $academicYear) }}" aria-label="Emitir calendário oficial em PDF" title="Calendário oficial em PDF">
        <i class="fas fa-calendar-alt" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('academic-years.matrices-pdf', $academicYear) }}" aria-label="Emitir matrizes curriculares em PDF" title="Matrizes curriculares em PDF">
        <i class="fas fa-file-pdf" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('academic-years.schedules-pdf', $academicYear) }}" aria-label="Emitir horários das turmas em PDF" title="Horários das turmas em PDF">
        <i class="fas fa-clock" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('academic-years.closure', $academicYear) }}" aria-label="Conferir fechamento do ano letivo" title="Conferência de fechamento">
        <i class="fas fa-clipboard-check" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('academic-years.final-results.pdf', $academicYear) }}" aria-label="Emitir resultados finais do ano em PDF" title="Resultados finais do ano em PDF">
        <i class="fas fa-file-signature" aria-hidden="true"></i>
    </a>
    @if ($canChangeCalendar)
        <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('academic-years.edit', $academicYear) }}" aria-label="Editar ano letivo {{ $academicYear->name }}" title="Editar ano letivo">
            <i class="fas fa-pen" aria-hidden="true"></i>
        </a>
        <form class="d-inline" method="POST" action="{{ route('academic-years.destroy', $academicYear) }}" onsubmit="return confirm('Excluir este ano letivo? Os dias do calendário gerados para ele também serão apagados.')">
            @csrf
            @method('DELETE')
            <button class="btn btn-sm btn-outline-danger shadow-sm sge-icon-action" type="submit" aria-label="Excluir ano letivo {{ $academicYear->name }}" title="Excluir ano letivo">
                <i class="fas fa-trash-alt" aria-hidden="true"></i>
            </button>
        </form>
    @endif
@endsection

@section('content')
    <x-academic-trail :school="$academicYear->school" :academic-year="$academicYear" />

    @if ($academicYear->approved_at)
        <div class="alert alert-warning">
            Calendário aprovado em {{ $academicYear->approved_at->format('d/m/Y') }}.
            Alterações posteriores podem afetar turmas, diários, fichas individuais e documentos acadêmicos.
            Apenas a Administração global pode alterar dados sensíveis após a aprovação.
        </div>
    @endif

    @if ($academicYear->isClosed())
        <div class="alert alert-success">
            Ano letivo fechado em {{ $academicYear->closed_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}
            @if ($academicYear->closedBy)
                por {{ $academicYear->closedBy->full_name }}
            @endif.
            Os dados acadêmicos estão preservados para documentos oficiais.
        </div>
    @endif

    <x-structure-validation :issues="$structureIssues" title="Validação do ano letivo" empty="Ano letivo sem inconsistências estruturais." />

    <nav class="sge-section-nav sge-academic-nav sge-academic-tabs mb-4" aria-label="Áreas do ano letivo" role="tablist" data-section-tabs data-default-tab="{{ $defaultAcademicYearTab }}">
        <a href="#section-resumo" class="sge-section-nav-item" data-academic-tab="resumo" role="tab" aria-controls="section-resumo">
            <i class="fas fa-clipboard-list"></i>
            <span>Resumo</span>
            <small>{{ $schoolDays }} dias letivos</small>
        </a>
        <a href="#section-gestao" class="sge-section-nav-item" data-academic-tab="gestao" role="tab" aria-controls="section-gestao">
            <i class="fas fa-tasks"></i>
            <span>Gestão</span>
            <small>períodos e fechamento</small>
        </a>
        <a href="#section-calendario" class="sge-section-nav-item" data-academic-tab="calendario" role="tab" aria-controls="section-calendario">
            <i class="fas fa-calendar-alt"></i>
            <span>Calendário</span>
            <small>{{ $academicYear->days->count() }} dias</small>
        </a>
        <a href="#section-matrizes" class="sge-section-nav-item" data-academic-tab="matrizes" role="tab" aria-controls="section-matrizes">
            <i class="fas fa-book-open"></i>
            <span>Matrizes</span>
            <small>{{ $academicYear->courses->count() }} cadastradas</small>
        </a>
        <a href="#section-turmas" class="sge-section-nav-item" data-academic-tab="turmas" role="tab" aria-controls="section-turmas">
            <i class="fas fa-users"></i>
            <span>Turmas</span>
            <small>{{ $academicYear->classes->count() }} turmas</small>
        </a>
    </nav>

    <div class="row">
        <div class="col-12 mb-4" data-academic-panel="resumo" role="tabpanel">
            <div id="section-resumo" class="card shadow sge-anchor-section">
                <div class="card-header py-3">
                    <h2 class="h6 m-0 font-weight-bold text-primary">Resumo</h2>
                </div>
                <div class="card-body">
                    <div class="sge-academic-school">
                        <i class="fas fa-school" aria-hidden="true"></i>
                        <div><span>Unidade escolar</span><strong>{{ $academicYear->school?->name }}</strong></div>
                    </div>
                    <div class="sge-academic-metrics">
                        <div><strong>{{ $schoolDays }}/{{ $academicYear->minimum_school_days }}</strong><span>dias letivos previstos</span></div>
                        <div><strong>{{ $academicYear->periods->count() }}</strong><span>períodos</span></div>
                        <div><strong>{{ $readyCourses->count() }}/{{ $academicYear->courses->count() }}</strong><span>matrizes prontas</span></div>
                        <div><strong>{{ $activeClasses->count() }}/{{ $academicYear->classes->count() }}</strong><span>turmas ativas</span></div>
                        <div><strong>{{ $enrollmentCount }}</strong><span>matrículas</span></div>
                        <div><strong>{{ $closureErrors->count() }}</strong><span>impedimentos ao fechamento</span></div>
                    </div>
                    <dl class="mb-0 sge-academic-summary-details">
                        <dt>Ano principal</dt>
                        <dd>{{ $academicYear->reference_year }}</dd>
                        <dt>Período</dt>
                        <dd>{{ $academicYear->starts_at?->format('d/m/Y') }} a {{ $academicYear->ends_at?->format('d/m/Y') }}</dd>
                        <dt>Aprovação</dt>
                        <dd>{{ $academicYear->approved_at?->format('d/m/Y') ?? 'Pendente' }}</dd>
                        <dt>Estado</dt>
                        <dd><span class="badge badge-{{ $yearStatus['tone'] }}">{{ $yearStatus['label'] }}</span> <span class="small text-muted">{{ $yearStatus['description'] }}</span></dd>
                        <dt>Fechamento</dt>
                        <dd>
                            @if ($academicYear->isClosed())
                                <span class="badge badge-success">Fechado</span>
                                <span class="small text-muted d-block">{{ $academicYear->closed_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</span>
                            @else
                                <span class="badge badge-light border">Aberto</span>
                                <span class="small text-muted d-block">Resultados e documentos finais ainda podem ser apurados.</span>
                            @endif
                        </dd>
                        <dt>Critérios de aprovação</dt>
                        <dd>{{ number_format((float) $academicYear->passing_points, 1, ',', '.') }} pontos e {{ $academicYear->minimum_attendance_percentage }}% de frequência</dd>
                        <dt>Duração da hora-aula</dt>
                        <dd>{{ $academicYear->class_hour_minutes }} minutos</dd>
                        <dt>Dias letivos</dt>
                        <dd>{{ number_format($schoolDays, 0, ',', '.') }} cadastrados de {{ number_format((int) $academicYear->minimum_school_days, 0, ',', '.') }} exigidos</dd>
                        <dt>Calendário</dt>
                        <dd>{{ $academicYear->days->count() }} dias cadastrados</dd>
                        <dt>Estrutura acadêmica</dt>
                        <dd>{{ $academicYear->courses->count() }} matrizes · {{ $academicYear->classes->count() }} turmas · {{ $enrollmentCount }} matrículas</dd>
                        <dt>Observações</dt>
                        <dd>{{ $academicYear->notes ?: 'Nenhuma observação cadastrada' }}</dd>
                        @if($academicYear->isClosed())
                            <dt>Responsável pelo fechamento</dt>
                            <dd>{{ $academicYear->closedBy?->full_name ?? 'Não identificado' }}</dd>
                            <dt>Observações do fechamento</dt>
                            <dd>{{ $academicYear->closure_notes ?: 'Nenhuma observação registrada' }}</dd>
                        @endif
                    </dl>

                </div>
            </div>
        </div>

        <div id="section-gestao" class="col-12 mb-4 sge-anchor-section" data-academic-panel="gestao" role="tabpanel">
            <div class="card shadow">
                <div class="card-header py-3"><h2 class="h6 m-0 font-weight-bold text-primary">Gestão acadêmica</h2></div>
                <div class="card-body sge-academic-actions">
                    <div><i class="fas fa-layer-group" aria-hidden="true"></i><h3 class="h6">Períodos avaliativos</h3><p>Cadastre períodos, defina avaliações e configure a recuperação em um espaço próprio.</p><a class="btn btn-primary" href="{{ route('academic-years.periods.index', $academicYear) }}">Gerenciar períodos</a></div>
                    <div><i class="fas fa-book-open" aria-hidden="true"></i><h3 class="h6">Matrizes e turmas</h3><p>Continue a organização curricular e as turmas vinculadas a este calendário logo abaixo.</p><a class="btn btn-outline-primary" href="#section-matrizes">Ver matrizes</a></div>
                    <div><i class="fas fa-clipboard-check" aria-hidden="true"></i><h3 class="h6">Fechamento anual</h3><p>Confira consolidações, resultados finais e documentos antes de fechar o ano letivo.</p><a class="btn btn-outline-primary" href="{{ route('academic-years.closure', $academicYear) }}">Conferir fechamento</a></div>
                </div>
                <div class="card-body border-top">
                    @if (! $academicYear->approved_at)
                        <h3 class="h6">Aprovação do calendário</h3>
                        <form method="POST" action="{{ route('academic-years.approve', $academicYear) }}">
                            @csrf @method('PATCH')
                            <div class="form-group"><label for="approved_at">Data de aprovação</label><input id="approved_at" name="approved_at" type="date" class="form-control @error('approved_at') is-invalid @enderror" required>@error('approved_at') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                            <button class="btn btn-warning" type="submit">Registrar aprovação</button>
                        </form>
                    @elseif (! $academicYear->isClosed())
                        <h3 class="h6">Fechamento do ano letivo</h3>
                        @if ($closureErrors->isEmpty())<p class="small text-muted">O ano letivo pode ser fechado. Depois disso, reabra apenas se houver necessidade administrativa.</p>@else<div class="alert alert-warning small"><strong>Antes de fechar:</strong><ul class="mb-0 pl-3">@foreach ($closureErrors as $issue)<li>{{ $issue['message'] }}</li>@endforeach</ul></div>@endif
                        @if ($closureWarnings->isNotEmpty())<div class="alert alert-light border small"><strong>Avisos:</strong><ul class="mb-0 pl-3">@foreach ($closureWarnings as $issue)<li>{{ $issue['message'] }}</li>@endforeach</ul></div>@endif
                        <form method="POST" action="{{ route('academic-years.close', $academicYear) }}">
                            @csrf @method('PATCH')
                            <div class="row"><div class="col-md-4 form-group"><label for="closed_at">Data de fechamento</label><input id="closed_at" name="closed_at" type="date" class="form-control @error('closed_at') is-invalid @enderror" value="{{ old('closed_at', now('America/Sao_Paulo')->toDateString()) }}" required>@error('closed_at') <div class="invalid-feedback">{{ $message }}</div> @enderror</div><div class="col-md-8 form-group"><label for="closure_notes">Observações</label><textarea id="closure_notes" name="closure_notes" class="form-control" rows="2">{{ old('closure_notes') }}</textarea></div></div>
                            <button class="btn btn-success" type="submit" @disabled(! $canCloseAcademicYear)><i class="fas fa-lock mr-1" aria-hidden="true"></i>Fechar ano letivo</button>
                        </form>
                    @elseif (auth()->user()->isAdministrator())
                        <h3 class="h6">Reabertura administrativa</h3>
                        <form method="POST" action="{{ route('academic-years.reopen', $academicYear) }}">@csrf @method('PATCH')<div class="form-group"><label for="reopen_reason">Motivo da reabertura</label><textarea id="reopen_reason" name="reopen_reason" class="form-control @error('reopen_reason') is-invalid @enderror" rows="2" required>{{ old('reopen_reason') }}</textarea>@error('reopen_reason') <div class="invalid-feedback">{{ $message }}</div> @enderror</div><button class="btn btn-outline-warning" type="submit"><i class="fas fa-unlock mr-1" aria-hidden="true"></i>Reabrir ano letivo</button></form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div id="section-calendario" data-academic-panel="calendario" role="tabpanel" class="sge-calendar-tab sge-anchor-section">
    <div class="card shadow mb-3 sge-calendar-visual">
        <div class="card-header py-3 d-flex align-items-center justify-content-between flex-wrap">
            <h2 class="h6 m-0 font-weight-bold text-primary">Calendário visual</h2>
            <div class="sge-calendar-legend small">
                @foreach (\App\Models\CalendarDay::printLegend() as $code => $label)
                    <span><strong>{{ $code ?: 'S/D' }}</strong> {{ $label }}</span>
                @endforeach
            </div>
        </div>
        <div class="card-body">
            @include('academic-years._calendar-grid', [
                'months' => $calendarMonths,
                'interactive' => $canChangeCalendar,
            ])
        </div>
    </div>

    <div class="row sge-calendar-editor-row">
        <div class="col-12 mb-4">
            <div class="card shadow sge-calendar-editor">
                <div class="card-header py-3">
                    <h2 class="h6 m-0 font-weight-bold text-primary">Editar dia ou faixa do calendário</h2>
                </div>
                <div class="card-body">
                    @if ($canChangeCalendar)
                        <form method="POST" action="{{ route('academic-years.days.store', $academicYear) }}">
                            @csrf
                            <div class="row">
                                <div class="col-md-3 form-group">
                                    <label for="date">Data inicial</label>
                                    <input id="date" name="date" type="date" class="form-control @error('date') is-invalid @enderror" value="{{ old('date') }}" required>
                                    @error('date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3 form-group">
                                    <label for="date_end">Data final <span class="text-muted font-weight-normal">(opcional)</span></label>
                                    <input id="date_end" name="date_end" type="date" class="form-control @error('date_end') is-invalid @enderror" value="{{ old('date_end') }}">
                                    @error('date_end') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3 form-group">
                                    <label for="type">Tipo</label>
                                    <select id="type" name="type" class="form-control @error('type') is-invalid @enderror" required>
                                        @foreach (\App\Models\CalendarDay::TYPE_LABELS as $value => $label)
                                            <option value="{{ $value }}">{{ \App\Models\CalendarDay::labelWithPrintCode($value) }}</option>
                                        @endforeach
                                    </select>
                                    @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3 form-group d-flex align-items-end">
                                    <div class="custom-control custom-checkbox mb-2">
                                        <input class="custom-control-input" id="counts_as_school_day" name="counts_as_school_day" type="checkbox" value="1">
                                        <label class="custom-control-label" for="counts_as_school_day">Conta como letivo</label>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="title">Título</label>
                                <input id="title" name="title" class="form-control" placeholder="Feriado municipal, conselho de classe...">
                            </div>
                            <div class="form-group">
                                <label for="description">Observações</label>
                                <textarea id="description" name="description" class="form-control" rows="2"></textarea>
                            </div>
                            <p class="small text-muted mb-3">Deixe a data final vazia para alterar somente um dia. Em uma faixa, a mesma configuração será aplicada a todas as datas.</p>
                            <button class="btn btn-primary" type="submit">Salvar no calendário</button>
                        </form>
                    @else
                        <p class="mb-0 text-gray-600">Calendário aprovado. Dias do calendário estão bloqueados para edição.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
    </div>

    <div class="row">
        <div class="col-12 mb-4" data-academic-panel="matrizes" role="tabpanel">
            <div class="card shadow">
                <div class="card-header py-3 d-flex align-items-center justify-content-between">
                    <h2 id="section-matrizes" class="h6 m-0 font-weight-bold text-primary sge-anchor-section">Matrizes</h2>
                    @if ($canChangeCalendar)
                        <a class="btn btn-sm btn-primary" href="{{ route('academic-years.courses.create', $academicYear) }}">Nova matriz</a>
                    @endif
                </div>
                <div class="card-body">
                    @forelse ($academicYear->courses->sortBy('name') as $course)
                        @php($courseStatus = \App\Support\AcademicStructureStatus::course($course))
                        <div class="border rounded px-3 py-2 mb-2 sge-academic-list-item">
                            <div class="d-flex justify-content-between flex-wrap">
                                <div>
                                    <h3 class="h6 mb-1">{{ $course->name }}</h3>
                                    <div class="small text-gray-600">
                                        {{ $course->stageLabel() }} · {{ $course->modalityLabel() }} · {{ $course->components->count() }} componentes ·
                                        {{ $course->formattedCalculatedWorkloadHours() }} horas
                                        <span class="badge badge-{{ $courseStatus['tone'] }} ml-1">{{ $courseStatus['label'] }}</span>
                                    </div>
                                </div>
                                <div class="sge-action-buttons mt-2 mt-md-0" aria-label="Ações da matriz {{ $course->name }}">
                                    <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('academic-years.courses.show', [$academicYear, $course]) }}" aria-label="Gerenciar matriz {{ $course->name }}" title="Gerenciar matriz">
                                        <i class="fas fa-cog" aria-hidden="true"></i>
                                    </a>
                                    @if ($canChangeCalendar)
                                        <a class="btn btn-sm btn-outline-secondary sge-icon-action" href="{{ route('academic-years.courses.edit', [$academicYear, $course]) }}" aria-label="Editar matriz {{ $course->name }}" title="Editar matriz">
                                            <i class="fas fa-pen" aria-hidden="true"></i>
                                        </a>
                                        <form method="POST" action="{{ route('academic-years.courses.duplicate', [$academicYear, $course]) }}" onsubmit="return confirm('Duplicar esta matriz com seus componentes curriculares?')">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-secondary sge-icon-action" type="submit" aria-label="Duplicar matriz {{ $course->name }}" title="Duplicar matriz">
                                                <i class="fas fa-copy" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('academic-years.courses.destroy', [$academicYear, $course]) }}" onsubmit="return confirm('Excluir esta matriz? Os componentes curriculares também serão removidos.')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger sge-icon-action" type="submit" aria-label="Excluir matriz {{ $course->name }}" title="Excluir matriz">
                                                <i class="fas fa-trash-alt" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="sge-empty-state">
                            <i class="fas fa-book-open" aria-hidden="true"></i>
                            <p>Nenhuma matriz cadastrada. Cadastre a matriz curricular antes de criar turmas e matrículas.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-12 mb-4" data-academic-panel="turmas" role="tabpanel">
            <div class="card shadow">
                <div class="card-header py-3 d-flex align-items-center justify-content-between">
                    <h2 id="section-turmas" class="h6 m-0 font-weight-bold text-primary sge-anchor-section">Turmas</h2>
                    @if ($canChangeCalendar)
                        <a class="btn btn-sm btn-primary {{ $readyCourses->isEmpty() ? 'disabled' : '' }}" href="{{ $readyCourses->isEmpty() ? '#' : route('academic-years.classes.create', $academicYear) }}" @if($readyCourses->isEmpty()) aria-disabled="true" @endif>
                            Nova turma
                        </a>
                    @endif
                </div>
                <div class="card-body">
                    @if ($readyCourses->isEmpty())
                        <div class="alert alert-warning">
                            Para criar turma, cadastre primeiro ao menos uma matriz com componentes curriculares ativos.
                        </div>
                    @endif

                    @forelse ($academicYear->classes->sortBy('name') as $class)
                        @php($classStatus = \App\Support\AcademicStructureStatus::schoolClass($class))
                        <div class="border rounded px-3 py-2 mb-2 sge-academic-list-item">
                            <div class="d-flex justify-content-between flex-wrap">
                                <div>
                                    <h3 class="h6 mb-1">{{ $class->name }}</h3>
                                    <div class="small text-gray-600">
                                        {{ $class->shift ?: 'Turno não definido' }} ·
                                        {{ $class->courses->pluck('name')->join(' + ') ?: 'Sem matriz' }} ·
                                        {{ $class->formattedPlannedWorkloadHours() }} horas previstas ·
                                        {{ $class->enrollments->count() }} matrículas
                                        <span class="badge badge-{{ $classStatus['tone'] }} ml-1">{{ $classStatus['label'] }}</span>
                                    </div>
                                </div>
                                <div class="sge-action-buttons mt-2 mt-md-0" aria-label="Ações da turma {{ $class->name }}">
                                    <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('academic-years.classes.show', [$academicYear, $class]) }}" aria-label="Gerenciar turma {{ $class->name }}" title="Gerenciar turma">
                                        <i class="fas fa-cog" aria-hidden="true"></i>
                                    </a>
                                    @if ($canChangeCalendar)
                                        <a class="btn btn-sm btn-outline-secondary sge-icon-action" href="{{ route('academic-years.classes.edit', [$academicYear, $class]) }}" aria-label="Editar turma {{ $class->name }}" title="Editar turma">
                                            <i class="fas fa-pen" aria-hidden="true"></i>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="sge-empty-state">
                            <i class="fas fa-users" aria-hidden="true"></i>
                            <p>Nenhuma turma cadastrada. Crie turmas quando houver matriz curricular com componentes.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.querySelectorAll('[data-calendar-day]').forEach((button) => {
                button.addEventListener('click', () => {
                    const dateInput = document.getElementById('date');
                    const dateEndInput = document.getElementById('date_end');
                    const typeInput = document.getElementById('type');
                    const countsInput = document.getElementById('counts_as_school_day');
                    const titleInput = document.getElementById('title');
                    const descriptionInput = document.getElementById('description');

                    if (dateInput) {
                        dateInput.value = button.dataset.calendarDay;
                    }

                    if (dateEndInput) {
                        dateEndInput.value = button.dataset.calendarDay;
                    }

                    if (typeInput && button.dataset.calendarType) {
                        typeInput.value = button.dataset.calendarType;
                    }

                    if (countsInput) {
                        countsInput.checked = button.dataset.calendarCountsAsSchoolDay === '1';
                    }

                    if (titleInput) {
                        titleInput.value = button.dataset.calendarDayTitle || '';
                    }

                    if (descriptionInput) {
                        descriptionInput.value = button.dataset.calendarDescription || '';
                    }

                    document.getElementById('section-calendario')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    typeInput?.focus({ preventScroll: true });
                });
            });

        </script>
    @endpush
@endsection
