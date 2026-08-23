@extends('layouts.app')

@section('title', 'Conformidade')
@section('page-title', 'Conformidade documental e acadêmica')

@section('content')
    @php
        $severityMeta = [
            'danger' => ['label' => 'Bloqueios', 'class' => 'danger', 'icon' => 'fa-lock', 'hint' => 'impedem emissão, matrícula, acesso ou fechamento'],
            'warning' => ['label' => 'Avisos', 'class' => 'warning', 'icon' => 'fa-triangle-exclamation', 'hint' => 'pedem conferência antes da rotina avançar'],
            'info' => ['label' => 'Atenções', 'class' => 'info', 'icon' => 'fa-circle-info', 'hint' => 'não bloqueiam, mas melhoram a qualidade dos dados'],
        ];

        $itemUrl = function ($check, $item) {
            return match ($check['type']) {
                'people' => route('people.show', $item),
                'roles' => $item->person ? route('people.show', $item->person) : null,
                'contacts' => $item->person ? route('people.show', $item->person) : null,
                'schools' => route('schools.edit', $item),
                'years' => route('academic-years.show', $item),
                'enrollments' => $item->student ? route('enrollments.documents', $item) : null,
                'history_enrollments' => $item->student ? route('student-histories.student', $item->student) : null,
                'periods' => $item->academicYear ? route('academic-years.periods.index', $item->academicYear) : null,
                'classes' => $item->academicYear ? route('academic-years.classes.show', [$item->academicYear, $item]) : null,
                'assignments' => $item->schoolClass?->academicYear
                    ? route('academic-years.classes.show', [$item->schoolClass->academicYear, $item->schoolClass])
                    : null,
                default => null,
            };
        };

        $itemTitle = function ($check, $item) {
            return match ($check['type']) {
                'people' => $item->full_name,
                'roles' => $item->person?->full_name ?? 'Pessoa não localizada',
                'contacts' => $item->name,
                'schools' => $item->name,
                'years' => $item->name,
                'enrollments' => $item->student?->full_name ?? 'Estudante não localizado',
                'history_enrollments' => $item->student?->full_name ?? 'Estudante não localizado',
                'periods' => $item->name,
                'classes' => $item->name,
                'assignments' => $item->component?->name ?? 'Componente não localizado',
                default => 'Registro',
            };
        };

        $itemSubtitle = function ($check, $item) {
            return match ($check['type']) {
                'people' => $item->institutional_email ?: 'Sem e-mail institucional',
                'roles' => ($item->label().' / '.($item->school?->name ?? 'Global')),
                'contacts' => $item->person ? 'Responsável de '.$item->person->full_name : 'Pessoa não localizada',
                'schools' => trim(($item->city ?? '').' / '.($item->state ?? ''), ' /') ?: 'Sem cidade/UF',
                'years' => ($item->school?->name ?? 'Escola não localizada').' · '.optional($item->starts_at)->format('d/m/Y').' a '.optional($item->ends_at)->format('d/m/Y'),
                'enrollments' => ($item->schoolClass?->name ?? 'Turma não localizada').' · '.($item->schoolClass?->academicYear?->school?->name ?? 'Escola não localizada'),
                'history_enrollments' => $item->history_missing_message,
                'periods' => ($item->academicYear?->school?->name ?? 'Escola não localizada').' · '.($item->academicYear?->referenceYearsLabel() ?? 'Ano não informado'),
                'classes' => ($item->academicYear?->school?->name ?? 'Escola não localizada').' · '.($item->academicYear?->referenceYearsLabel() ?? 'Ano não informado'),
                'assignments' => ($item->schoolClass?->name ?? 'Turma não localizada').' · '.($item->schoolClass?->academicYear?->school?->name ?? 'Escola não localizada'),
                default => '',
            };
        };

        $filterQuery = array_filter([
            'school_id' => $selectedSchoolId,
            'severity' => $selectedSeverity,
        ]);
    @endphp

    <section class="sge-quality-hero mb-4" aria-labelledby="quality-title">
        <div>
            <span class="sge-eyebrow">Beabá</span>
            <h2 id="quality-title">Central única de conformidade</h2>
            <p>
                Uma leitura operacional dos dados que realmente sustentam acesso, matrículas, documentos, diários e fechamentos.
                Comece pelos bloqueios; avisos podem ser tratados conforme a rotina da escola.
            </p>
        </div>
        <div class="sge-quality-actions">
            <a class="btn btn-outline-primary" href="{{ route('data-quality.pdf', $filterQuery) }}">
                <i class="fas fa-file-pdf" aria-hidden="true"></i>
                <span>PDF da conferência</span>
            </a>
            <a class="btn btn-primary" href="{{ route('data-quality.index', ['severity' => 'danger'] + array_filter(['school_id' => $selectedSchoolId])) }}">
                <i class="fas fa-lock" aria-hidden="true"></i>
                <span>Ver bloqueios</span>
            </a>
        </div>
    </section>

    <div class="sge-quality-summary mb-4" aria-label="Resumo de conformidade">
        <article>
            <span>Total em análise</span>
            <strong>{{ number_format($summary['total'], 0, ',', '.') }}</strong>
            <small>ocorrências encontradas nas regras atuais</small>
        </article>
        @foreach ($severityMeta as $severity => $meta)
            <a href="{{ route('data-quality.index', array_filter(['severity' => $severity, 'school_id' => $selectedSchoolId])) }}"
                class="sge-quality-summary-card is-{{ $meta['class'] }} {{ $selectedSeverity === $severity ? 'is-active' : '' }}">
                <span>{{ $meta['label'] }}</span>
                <strong>{{ number_format($summary[$severity], 0, ',', '.') }}</strong>
                <small>{{ $meta['hint'] }}</small>
            </a>
        @endforeach
    </div>

    <div class="card shadow mb-4">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap">
            <div class="mb-3 mb-lg-0">
                <h2 class="h5 font-weight-bold text-gray-900 mb-1">Filtros da conferência</h2>
                <p class="text-gray-700 mb-0">
                    Administração visualiza todas as escolas. Gestão confere somente as unidades em que possui vínculo atual.
                </p>
            </div>

            <form method="GET" action="{{ route('data-quality.index') }}" class="form-inline" aria-label="Filtros da conformidade">
                <label for="school_id" class="sr-only">Filtrar por escola</label>
                <select id="school_id" name="school_id" class="form-control mr-2 mb-2">
                    @if (auth()->user()->isAdministrator())
                        <option value="">Todas as escolas</option>
                    @endif
                    @foreach ($schools as $school)
                        <option value="{{ $school->id }}" @selected($selectedSchoolId === $school->id)>{{ $school->name }}</option>
                    @endforeach
                </select>

                <label for="severity" class="sr-only">Filtrar por gravidade</label>
                <select id="severity" name="severity" class="form-control mr-2 mb-2">
                    <option value="">Todas as gravidades</option>
                    @foreach ($severityMeta as $severity => $meta)
                        <option value="{{ $severity }}" @selected($selectedSeverity === $severity)>{{ $meta['label'] }}</option>
                    @endforeach
                </select>

                <button class="btn btn-primary mb-2" type="submit">
                    <i class="fas fa-filter" aria-hidden="true"></i>
                    <span>Aplicar</span>
                </button>
                @if ($selectedSchoolId || $selectedSeverity)
                    <a class="btn btn-outline-secondary mb-2 ml-2" href="{{ route('data-quality.index') }}">Limpar</a>
                @endif
            </form>
        </div>
    </div>

    @if ($compliantGroups->isNotEmpty())
        <section class="sge-quality-ok mb-4" aria-labelledby="quality-ok-title">
            <span class="sge-quality-ok-icon"><i class="fas fa-check" aria-hidden="true"></i></span>
            <div>
                <h2 id="quality-ok-title">Sem ocorrências nestas áreas</h2>
                <p>{{ $compliantGroups->join(', ', ' e ') }}.</p>
            </div>
        </section>
    @endif

    <section class="mb-4" aria-labelledby="workflow-title">
        <div class="d-flex justify-content-between align-items-end flex-wrap mb-3">
            <div>
                <h2 id="workflow-title" class="h5 font-weight-bold text-gray-900 mb-1">Fluxos acompanhados</h2>
                <p class="text-gray-700 mb-0">Atalhos para resolver o que normalmente trava o trabalho da secretaria e da gestão.</p>
            </div>
        </div>
        <div class="sge-workflow-grid">
            @foreach ($workflows as $workflow)
                <a class="sge-workflow-card" href="{{ $workflow['route'] }}">
                    <span class="sge-workflow-icon"><i class="fas {{ $workflow['icon'] }}" aria-hidden="true"></i></span>
                    <span>
                        <strong>{{ $workflow['title'] }}</strong>
                        <small>{{ $workflow['description'] }}</small>
                    </span>
                    <em>{{ number_format($workflow['count'], 0, ',', '.') }}</em>
                </a>
            @endforeach
        </div>
    </section>

    @forelse ($displayGroups as $group)
        <section class="card shadow mb-4 sge-quality-group" aria-labelledby="group-{{ $loop->index }}">
            <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap">
                <div class="d-flex align-items-center">
                    <span class="sge-quality-group-icon mr-3"><i class="fas {{ $group['icon'] }}" aria-hidden="true"></i></span>
                    <div>
                        <h2 id="group-{{ $loop->index }}" class="h5 m-0 font-weight-bold text-primary">{{ $group['title'] }}</h2>
                        <p class="small text-gray-600 mb-0">{{ $group['description'] }}</p>
                    </div>
                </div>
                <span class="badge badge-primary mt-2 mt-md-0">
                    {{ number_format($group['checks']->sum('count'), 0, ',', '.') }} ocorrência(s)
                </span>
            </div>
            <div class="card-body">
                <div class="sge-quality-checks">
                    @foreach ($group['checks'] as $check)
                        @php($meta = $severityMeta[$check['severity']] ?? $severityMeta['info'])
                        <details class="sge-quality-check" @if($check['count'] > 0 && $check['severity'] === 'danger') open @endif>
                            <summary>
                                <span>
                                    <i class="fas {{ $meta['icon'] }} text-{{ $meta['class'] }}" aria-hidden="true"></i>
                                    <strong>{{ $check['title'] }}</strong>
                                    <small>{{ $check['description'] }}</small>
                                </span>
                                <span class="badge badge-{{ $meta['class'] }}">{{ number_format($check['count'], 0, ',', '.') }}</span>
                            </summary>

                            @if ($check['items']->isNotEmpty())
                                <div class="sge-quality-items">
                                    @foreach ($check['items'] as $item)
                                        @php($url = $itemUrl($check, $item))
                                        <div class="sge-quality-item">
                                            <span>
                                                @if ($url)
                                                    <a class="font-weight-bold" href="{{ $url }}">{{ $itemTitle($check, $item) }}</a>
                                                @else
                                                    <strong>{{ $itemTitle($check, $item) }}</strong>
                                                @endif
                                                <small>{{ $itemSubtitle($check, $item) }}</small>
                                            </span>
                                            @if ($url)
                                                <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ $url }}" aria-label="Resolver {{ $itemTitle($check, $item) }}" title="Resolver">
                                                    <i class="fas fa-arrow-right" aria-hidden="true"></i>
                                                </a>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                                @if ($check['count'] > $check['items']->count())
                                    <p class="small text-gray-600 mt-3 mb-0">
                                        Mostrando {{ $check['items']->count() }} de {{ number_format($check['count'], 0, ',', '.') }} registro(s). Use os filtros para reduzir a lista.
                                    </p>
                                @endif
                            @else
                                <p class="text-gray-600 mb-0 mt-3">
                                    <i class="fas fa-check-circle text-success" aria-hidden="true"></i>
                                    Nenhuma ocorrência encontrada.
                                </p>
                            @endif
                        </details>
                    @endforeach
                </div>
            </div>
        </section>
    @empty
        <div class="card shadow">
            <div class="card-body text-center py-5">
                <i class="fas fa-check-circle fa-3x text-success mb-3" aria-hidden="true"></i>
                <h2 class="h5 font-weight-bold text-gray-900">
                    {{ $summary['total'] === 0 ? 'Conferência em dia' : 'Nada encontrado com estes filtros' }}
                </h2>
                <p class="text-gray-700 mb-0">
                    {{ $summary['total'] === 0 ? 'Nenhum bloqueio, aviso ou ponto de atenção foi encontrado para este escopo.' : 'Troque a escola ou a gravidade para conferir outras ocorrências.' }}
                </p>
            </div>
        </div>
    @endforelse
@endsection
