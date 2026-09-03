@extends('layouts.app')

@section('title', __('screens.compliance'))
@section('page-title', __('screens.document_academic_compliance'))

@section('content')
    @php
        $severityMeta = [
            'danger' => ['label' => __('screens.blocks'), 'class' => 'danger', 'icon' => 'fa-lock', 'hint' => __('screens.blocks_hint')],
            'warning' => ['label' => __('screens.warnings'), 'class' => 'warning', 'icon' => 'fa-triangle-exclamation', 'hint' => __('screens.warnings_hint')],
            'info' => ['label' => __('screens.attention'), 'class' => 'info', 'icon' => 'fa-circle-info', 'hint' => __('screens.attention_hint')],
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
                'roles' => $item->person?->full_name ?? __('screens.person_not_found'),
                'contacts' => $item->name,
                'schools' => $item->name,
                'years' => $item->name,
                'enrollments' => $item->student?->full_name ?? __('screens.student_not_found'),
                'history_enrollments' => $item->student?->full_name ?? __('screens.student_not_found'),
                'periods' => $item->name,
                'classes' => $item->name,
                'assignments' => $item->component?->name ?? __('screens.component_not_found'),
                default => __('screens.record_generic'),
            };
        };

        $itemSubtitle = function ($check, $item) {
            return match ($check['type']) {
                'people' => $item->institutional_email ?: __('screens.no_institutional_email'),
                'roles' => ($item->label().' / '.($item->school?->name ?? __('screens.global'))),
                'contacts' => $item->person ? __('screens.responsible_for', ['name' => $item->person->full_name]) : __('screens.person_not_found'),
                'schools' => trim(($item->city ?? '').' / '.($item->state ?? ''), ' /') ?: __('screens.city_state_missing'),
                'years' => ($item->school?->name ?? __('screens.school_not_found')).' · '.optional($item->starts_at)->format('d/m/Y').' a '.optional($item->ends_at)->format('d/m/Y'),
                'enrollments' => ($item->schoolClass?->name ?? __('screens.class_not_found')).' · '.($item->schoolClass?->academicYear?->school?->name ?? __('screens.school_not_found')),
                'history_enrollments' => $item->history_missing_message,
                'periods' => ($item->academicYear?->school?->name ?? __('screens.school_not_found')).' · '.($item->academicYear?->referenceYearsLabel() ?? __('screens.year_not_informed')),
                'classes' => ($item->academicYear?->school?->name ?? __('screens.school_not_found')).' · '.($item->academicYear?->referenceYearsLabel() ?? __('screens.year_not_informed')),
                'assignments' => ($item->schoolClass?->name ?? __('screens.class_not_found')).' · '.($item->schoolClass?->academicYear?->school?->name ?? __('screens.school_not_found')),
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
            <h2 id="quality-title">{{ __('screens.compliance_center') }}</h2>
            <p>{{ __('screens.compliance_intro') }}</p>
        </div>
        <div class="sge-quality-actions">
            <a class="btn btn-outline-primary" href="{{ route('data-quality.pdf', $filterQuery) }}">
                <i class="fas fa-file-pdf" aria-hidden="true"></i>
                <span>{{ __('screens.compliance_pdf') }}</span>
            </a>
            <a class="btn btn-primary" href="{{ route('data-quality.index', ['severity' => 'danger'] + array_filter(['school_id' => $selectedSchoolId])) }}">
                <i class="fas fa-lock" aria-hidden="true"></i>
                <span>{{ __('screens.view_blocks') }}</span>
            </a>
        </div>
    </section>

    <div class="sge-quality-summary mb-4" aria-label="{{ __('screens.compliance_summary') }}">
        <article>
            <span>{{ __('screens.total_under_review') }}</span>
            <strong>{{ number_format($summary['total'], 0, ',', '.') }}</strong>
            <small>{{ __('screens.current_rule_occurrences') }}</small>
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
                <h2 class="h5 font-weight-bold text-gray-900 mb-1">{{ __('screens.review_filters') }}</h2>
                <p class="text-gray-700 mb-0">{{ __('screens.review_scope_help') }}</p>
            </div>

            <form method="GET" action="{{ route('data-quality.index') }}" class="form-inline" aria-label="{{ __('screens.compliance_filters') }}">
                <label for="school_id" class="sr-only">{{ __('screens.filter_school') }}</label>
                <select id="school_id" name="school_id" class="form-control mr-2 mb-2">
                    @if (auth()->user()->isAdministrator())
                        <option value="">{{ __('screens.all_schools') }}</option>
                    @endif
                    @foreach ($schools as $school)
                        <option value="{{ $school->id }}" @selected($selectedSchoolId === $school->id)>{{ $school->name }}</option>
                    @endforeach
                </select>

                <label for="severity" class="sr-only">{{ __('screens.filter_severity') }}</label>
                <select id="severity" name="severity" class="form-control mr-2 mb-2">
                    <option value="">{{ __('screens.all_severities') }}</option>
                    @foreach ($severityMeta as $severity => $meta)
                        <option value="{{ $severity }}" @selected($selectedSeverity === $severity)>{{ $meta['label'] }}</option>
                    @endforeach
                </select>

                <button class="btn btn-primary mb-2" type="submit">
                    <i class="fas fa-filter" aria-hidden="true"></i>
                    <span>{{ __('screens.apply') }}</span>
                </button>
                @if ($selectedSchoolId || $selectedSeverity)
                    <a class="btn btn-outline-secondary mb-2 ml-2" href="{{ route('data-quality.index') }}">{{ __('screens.clear') }}</a>
                @endif
            </form>
        </div>
    </div>

    @if ($compliantGroups->isNotEmpty())
        <section class="sge-quality-ok mb-4" aria-labelledby="quality-ok-title">
            <span class="sge-quality-ok-icon"><i class="fas fa-check" aria-hidden="true"></i></span>
            <div>
                <h2 id="quality-ok-title">{{ __('screens.no_occurrences_areas') }}</h2>
                <p>{{ $compliantGroups->map(fn ($title) => __($title))->join(', ', ' '.__('screens.and').' ') }}.</p>
            </div>
        </section>
    @endif

    <section class="mb-4" aria-labelledby="workflow-title">
        <div class="d-flex justify-content-between align-items-end flex-wrap mb-3">
            <div>
                <h2 id="workflow-title" class="h5 font-weight-bold text-gray-900 mb-1">{{ __('screens.tracked_workflows') }}</h2>
                <p class="text-gray-700 mb-0">{{ __('screens.workflow_help') }}</p>
            </div>
        </div>
        <div class="sge-workflow-grid">
            @foreach ($workflows as $workflow)
                <a class="sge-workflow-card" href="{{ $workflow['route'] }}">
                    <span class="sge-workflow-icon"><i class="fas {{ $workflow['icon'] }}" aria-hidden="true"></i></span>
                    <span>
                        <strong>{{ __($workflow['title']) }}</strong>
                        <small>{{ __($workflow['description']) }}</small>
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
                        <h2 id="group-{{ $loop->index }}" class="h5 m-0 font-weight-bold text-primary">{{ __($group['title']) }}</h2>
                        <p class="small text-gray-600 mb-0">{{ __($group['description']) }}</p>
                    </div>
                </div>
                <span class="badge badge-primary mt-2 mt-md-0">
                    {{ __('screens.occurrence_count', ['count' => number_format($group['checks']->sum('count'), 0, ',', '.')]) }}
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
                                    <strong>{{ __($check['title']) }}</strong>
                                    <small>{{ __($check['description']) }}</small>
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
                                                <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ $url }}" aria-label="{{ __('screens.resolve_item', ['item' => $itemTitle($check, $item)]) }}" title="{{ __('screens.resolve') }}">
                                                    <i class="fas fa-arrow-right" aria-hidden="true"></i>
                                                </a>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                                @if ($check['count'] > $check['items']->count())
                                    <p class="small text-gray-600 mt-3 mb-0">
                                        {{ __('screens.showing_records', ['shown' => $check['items']->count(), 'total' => number_format($check['count'], 0, ',', '.')]) }}
                                    </p>
                                @endif
                            @else
                                <p class="text-gray-600 mb-0 mt-3">
                                    <i class="fas fa-check-circle text-success" aria-hidden="true"></i>
                                    {{ __('screens.no_occurrence') }}
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
                    {{ $summary['total'] === 0 ? __('screens.review_up_to_date') : __('screens.nothing_filters') }}
                </h2>
                <p class="text-gray-700 mb-0">
                    {{ $summary['total'] === 0 ? __('screens.no_scope_issue') : __('screens.change_filters') }}
                </p>
            </div>
        </div>
    @endforelse
@endsection
