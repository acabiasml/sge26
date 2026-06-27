@extends('layouts.app')

@php($canChangeCalendar = ! $academicYear->approved_at || auth()->user()->isAdministrator())
@php($schoolDays = $academicYear->schoolDayCount())
@php($calendarMonths = \App\Support\AcademicCalendarGrid::forAcademicYear($academicYear))
@php($readyCourses = $academicYear->courses->filter->hasMatrixComponents())

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

    <x-structure-validation :issues="$structureIssues" title="Validação do ano letivo" empty="Ano letivo sem inconsistências estruturais." />

    <nav class="sge-section-nav sge-academic-nav mb-4" aria-label="Áreas do ano letivo">
        <a href="#section-resumo" class="sge-section-nav-item">
            <i class="fas fa-clipboard-list"></i>
            <span>Resumo</span>
            <small>{{ $schoolDays }} dias letivos</small>
        </a>
        <a href="#section-calendario" class="sge-section-nav-item">
            <i class="fas fa-calendar-alt"></i>
            <span>Calendário</span>
            <small>{{ $academicYear->days->count() }} dias</small>
        </a>
        <a href="#section-matrizes" class="sge-section-nav-item">
            <i class="fas fa-book-open"></i>
            <span>Matrizes</span>
            <small>{{ $academicYear->courses->count() }} cadastradas</small>
        </a>
        <a href="#section-turmas" class="sge-section-nav-item">
            <i class="fas fa-users"></i>
            <span>Turmas</span>
            <small>{{ $academicYear->classes->count() }} turmas</small>
        </a>
    </nav>

    <div class="row">
        <div class="col-lg-4 mb-4">
            <div id="section-resumo" class="card shadow h-100 sge-anchor-section">
                <div class="card-header py-3">
                    <h2 class="h6 m-0 font-weight-bold text-primary">Resumo</h2>
                </div>
                <div class="card-body">
                    <div class="sge-academic-school">
                        <i class="fas fa-school" aria-hidden="true"></i>
                        <div><span>Unidade escolar</span><strong>{{ $academicYear->school?->name }}</strong></div>
                    </div>
                    <div class="sge-academic-metrics">
                        <div><strong>{{ $schoolDays }}</strong><span>dias letivos</span></div>
                        <div><strong>{{ $academicYear->periods->count() }}</strong><span>períodos</span></div>
                        <div><strong>{{ $academicYear->classes->where('active', true)->count() }}</strong><span>turmas ativas</span></div>
                    </div>
                    <dl class="mb-0">
                        <dt>Ano principal</dt>
                        <dd>{{ $academicYear->reference_year }}</dd>
                        <dt>Período</dt>
                        <dd>{{ $academicYear->starts_at?->format('d/m/Y') }} a {{ $academicYear->ends_at?->format('d/m/Y') }}</dd>
                        <dt>Aprovação</dt>
                        <dd>{{ $academicYear->approved_at?->format('d/m/Y') ?? 'Pendente' }}</dd>
                        <dt>Estado</dt>
                        <dd><span class="badge badge-{{ $yearStatus['tone'] }}">{{ $yearStatus['label'] }}</span> <span class="small text-muted">{{ $yearStatus['description'] }}</span></dd>
                        <dt>Critérios de aprovação</dt>
                        <dd>{{ number_format((float) $academicYear->passing_points, 1, ',', '.') }} pontos e {{ $academicYear->minimum_attendance_percentage }}% de frequência</dd>
                        <dt>Dias letivos</dt>
                        <dd>
                            <span class="badge badge-{{ $schoolDays >= 200 ? 'success' : 'warning' }}">{{ $schoolDays >= 200 ? 'Mínimo legal atendido' : 'Abaixo do mínimo legal' }}</span>
                            @if ($schoolDays < 200)
                                <span class="d-block small text-warning mt-1">Atenção: abaixo do mínimo legal de 200 dias letivos.</span>
                            @endif
                        </dd>
                    </dl>

                    @if (! $academicYear->approved_at)
                        <hr>
                        <form method="POST" action="{{ route('academic-years.approve', $academicYear) }}">
                            @csrf
                            @method('PATCH')
                            <div class="form-group">
                                <label for="approved_at">Data de aprovação</label>
                                <input id="approved_at" name="approved_at" type="date" class="form-control @error('approved_at') is-invalid @enderror" required>
                                @error('approved_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <button class="btn btn-warning btn-block" type="submit">Registrar aprovação</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-8 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3"><h2 class="h6 m-0 font-weight-bold text-primary">Gestão acadêmica</h2></div>
                <div class="card-body sge-academic-actions">
                    <div><i class="fas fa-layer-group" aria-hidden="true"></i><h3 class="h6">Períodos avaliativos</h3><p>Cadastre períodos, defina avaliações e configure a recuperação em um espaço próprio.</p><a class="btn btn-primary" href="{{ route('academic-years.periods.index', $academicYear) }}">Gerenciar períodos</a></div>
                    <div><i class="fas fa-book-open" aria-hidden="true"></i><h3 class="h6">Matrizes e turmas</h3><p>Continue a organização curricular e as turmas vinculadas a este calendário logo abaixo.</p><a class="btn btn-outline-primary" href="#section-matrizes">Ver matrizes</a></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- A gestão dos períodos avaliativos fica em uma página própria. --}}
        {{--
        <div class="col-lg-5 mb-4">
            <section class="card shadow sge-periods-panel">
                <div class="card-header py-3">
                    <h2 class="h6 m-0 font-weight-bold text-primary">Períodos cadastrados</h2>
                </div>
                <div class="card-body">
                    <table class="table table-sm sge-periods-table">
                        <tbody>
                            @forelse ($academicYear->periods->sortBy('position') as $period)
                                <tr>
                                    <td>
                                        <strong>{{ $period->name }}</strong>
                                        <div class="small text-gray-600">{{ $period->starts_at?->format('d/m/Y') }} a {{ $period->ends_at?->format('d/m/Y') }}</div>
                                        @php($periodSchoolDays = $period->schoolDayCount())
                                        <span class="badge badge-light border mt-1">
                                            {{ $periodSchoolDays }} {{ $periodSchoolDays === 1 ? 'dia letivo' : 'dias letivos' }}
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        @if ($canChangeCalendar)
                                            <form method="POST" action="{{ route('academic-years.periods.destroy', [$academicYear, $period]) }}" onsubmit="return confirm('Remover este período?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger sge-icon-action" type="submit" aria-label="Remover período {{ $period->name }}" title="Remover período">
                                                    <i class="fas fa-trash-alt" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td>Nenhum período cadastrado.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    @foreach ($academicYear->periods->sortBy('position') as $period)
                        <details class="sge-period-assessments" data-assessment-rules-form>
                            <summary class="font-weight-bold">Avaliações de {{ $period->name }}</summary>
                            <div class="pt-3">
                                <p class="small text-muted">Defina quantas avaliações este período terá e o peso de cada uma. A configuração é aplicada a todas as turmas e componentes desta escola. Depois que houver nota lançada, ela é bloqueada.</p>
                                <form method="POST" action="{{ route('academic-years.periods.assessment-rules.update', [$academicYear, $period]) }}">
                                    @csrf
                                    @method('PUT')
                                    @php($ruleCount = (int) old('assessment_count', max(1, $period->assessmentRules->count())))
                                    <div class="row align-items-end">
                                        <div class="col-md-4 form-group">
                                            <label for="assessment_count_{{ $period->id }}">Quantidade de avaliações</label>
                                            <select id="assessment_count_{{ $period->id }}" name="assessment_count" class="form-control" data-assessment-count>
                                                @for ($count = 1; $count <= 10; $count++)
                                                    <option value="{{ $count }}" @selected($ruleCount === $count)>{{ $count }}</option>
                                                @endfor
                                            </select>
                                        </div>
                                        <div class="col-md-8 form-group mb-0">
                                            <div class="row">
                                                @for ($position = 1; $position <= 10; $position++)
                                                    @php($existingRule = $period->assessmentRules->firstWhere('position', $position))
                                                    <div class="col-sm-4 mb-2" data-assessment-weight="{{ $position }}">
                                                        <label for="assessment_weight_{{ $period->id }}_{{ $position }}">Peso da avaliação {{ $position }}</label>
                                                        <input id="assessment_weight_{{ $period->id }}_{{ $position }}" name="weights[]" type="number" min="1" max="100" class="form-control" value="{{ old('weights.'.($position - 1), $existingRule?->weight ?? 1) }}">
                                                    </div>
                                                @endfor
                                            </div>
                                        </div>
                                    </div>
                                    <button class="btn btn-primary mt-2" type="submit"><i class="fas fa-save mr-1" aria-hidden="true"></i>Salvar avaliações</button>
                                </form>
                            </div>
                        </details>
                    @endforeach
                </div>
            </section>
        </div>
        --}}

        <div class="col-12 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h2 id="section-calendario" class="h6 m-0 font-weight-bold text-primary sge-anchor-section">Editar dia do calendário</h2>
                </div>
                <div class="card-body">
                    @if ($canChangeCalendar)
                        <form method="POST" action="{{ route('academic-years.days.store', $academicYear) }}">
                            @csrf
                            <div class="row">
                                <div class="col-md-4 form-group">
                                    <label for="date">Data</label>
                                    <input id="date" name="date" type="date" class="form-control @error('date') is-invalid @enderror" required>
                                    @error('date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="type">Tipo</label>
                                    <select id="type" name="type" class="form-control @error('type') is-invalid @enderror" required>
                                        @foreach (\App\Models\CalendarDay::TYPE_LABELS as $value => $label)
                                            <option value="{{ $value }}">{{ \App\Models\CalendarDay::labelWithPrintCode($value) }}</option>
                                        @endforeach
                                    </select>
                                    @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4 form-group d-flex align-items-end">
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
                            <button class="btn btn-primary" type="submit">Salvar dia</button>
                        </form>
                    @else
                        <p class="mb-0 text-gray-600">Calendário aprovado. Dias do calendário estão bloqueados para edição.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 d-flex align-items-center justify-content-between">
                    <h2 id="section-matrizes" class="h6 m-0 font-weight-bold text-primary sge-anchor-section">Matrizes</h2>
                    @if ($canChangeCalendar)
                        <a class="btn btn-sm btn-primary" href="{{ route('academic-years.courses.create', $academicYear) }}">Nova matriz</a>
                    @endif
                </div>
                <div class="card-body">
                    @forelse ($academicYear->courses->sortBy('name') as $course)
                        @php($courseStatus = \App\Support\AcademicStructureStatus::course($course))
                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between flex-wrap">
                                <div>
                                    <h3 class="h6 mb-1">{{ $course->name }}</h3>
                                    <div class="small text-gray-600">
                                        {{ $course->stageLabel() }} · {{ $course->components->count() }} componentes ·
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
                        <p class="mb-0">Nenhuma matriz cadastrada.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card shadow h-100">
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
                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between flex-wrap">
                                <div>
                                    <h3 class="h6 mb-1">{{ $class->name }}</h3>
                                    <div class="small text-gray-600">
                                        {{ $class->shift ?: 'Turno não definido' }} ·
                                        {{ $class->courses->pluck('name')->join(' + ') ?: 'Sem matriz' }} ·
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
                        <p class="mb-0">Nenhuma turma cadastrada.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex align-items-center justify-content-between">
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

    @push('scripts')
        <script>
            document.querySelectorAll('[data-calendar-day]').forEach((button) => {
                button.addEventListener('click', () => {
                    const dateInput = document.getElementById('date');
                    const typeInput = document.getElementById('type');
                    const countsInput = document.getElementById('counts_as_school_day');
                    const titleInput = document.getElementById('title');
                    const descriptionInput = document.getElementById('description');

                    if (dateInput) {
                        dateInput.value = button.dataset.calendarDay;
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
