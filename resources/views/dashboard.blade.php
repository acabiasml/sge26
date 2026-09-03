@extends('layouts.app')

@section('title', __('navigation.home'))
@section('page-title', __('navigation.home'))

@php
    $dashboardUser = auth()->user();
    $dashboardFirstName = \Illuminate\Support\Str::before(trim($dashboardUser->name), ' ');
    $canManageDashboard = $dashboardUser->can('manage-people');
    $dashboardShortcuts = [];

    if ($canManageDashboard) {
        $dashboardShortcuts = [
            [
                'label' => __('dashboard.diary_management'),
                'description' => __('dashboard.diary_management_description'),
                'icon' => 'fa-book-open',
                'url' => route('teacher-diaries.index'),
            ],
            [
                'label' => __('dashboard.enrollments'),
                'description' => __('dashboard.enrollments_description'),
                'icon' => 'fa-id-card',
                'url' => route('enrollments.index'),
            ],
            [
                'label' => __('dashboard.compliance'),
                'description' => __('dashboard.compliance_description'),
                'icon' => 'fa-clipboard-check',
                'url' => route('data-quality.index'),
            ],
            $dashboardUser->can('manage-schools')
                ? [
                    'label' => __('dashboard.schools_years'),
                    'description' => __('dashboard.schools_years_description'),
                    'icon' => 'fa-school',
                    'url' => route('schools.index'),
                ]
                : [
                    'label' => __('dashboard.people'),
                    'description' => __('dashboard.people_description'),
                    'icon' => 'fa-users',
                    'url' => route('people.index'),
                ],
        ];
        $dashboardIntroduction = __('dashboard.manager_intro');
    } elseif ($dashboardUser->hasActiveRole(\App\Models\PersonSchoolRole::ROLE_TEACHER) || $dashboardUser->hasTeachingDiaries()) {
        $dashboardShortcuts = [
            [
                'label' => __('dashboard.my_diaries'),
                'description' => __('dashboard.my_diaries_description'),
                'icon' => 'fa-book-open',
                'url' => route('teacher-diaries.index'),
            ],
            [
                'label' => __('dashboard.my_schedules'),
                'description' => __('dashboard.my_schedules_description'),
                'icon' => 'fa-clock',
                'url' => route('teacher-schedules.index'),
            ],
            [
                'label' => __('dashboard.my_profile'),
                'description' => __('dashboard.my_profile_description'),
                'icon' => 'fa-address-card',
                'url' => route('profile.edit'),
            ],
            [
                'label' => __('dashboard.school_calendar'),
                'description' => __('dashboard.school_calendar_description'),
                'icon' => 'fa-calendar-alt',
                'url' => route('dashboard').'#calendar-heading',
            ],
        ];
        $dashboardIntroduction = __('dashboard.teacher_intro');
    } elseif ($dashboardUser->hasStudentMap()) {
        $dashboardShortcuts = [
            [
                'label' => __('dashboard.my_diary'),
                'description' => __('dashboard.my_diary_description'),
                'icon' => 'fa-book-reader',
                'url' => route('student-diaries.index'),
            ],
            [
                'label' => __('dashboard.my_school_life'),
                'description' => __('dashboard.my_school_life_description'),
                'icon' => 'fa-graduation-cap',
                'url' => route('people.student-map.show', $dashboardUser->person_id),
            ],
            [
                'label' => __('dashboard.my_profile'),
                'description' => __('dashboard.my_profile_description'),
                'icon' => 'fa-address-card',
                'url' => route('profile.edit'),
            ],
            [
                'label' => __('dashboard.school_calendar'),
                'description' => __('dashboard.school_calendar_description'),
                'icon' => 'fa-calendar-alt',
                'url' => route('dashboard').'#calendar-heading',
            ],
        ];
        $dashboardIntroduction = __('dashboard.student_intro');
    } else {
        $dashboardShortcuts = [
            [
                'label' => __('dashboard.my_profile'),
                'description' => __('dashboard.my_profile_description'),
                'icon' => 'fa-address-card',
                'url' => route('profile.edit'),
            ],
            [
                'label' => __('dashboard.school_calendar'),
                'description' => __('dashboard.school_calendar_description'),
                'icon' => 'fa-calendar-alt',
                'url' => route('dashboard').'#calendar-heading',
            ],
        ];
        $dashboardIntroduction = __('dashboard.other_intro');
    }

    $studentCount = $roleCounts[\App\Models\PersonSchoolRole::ROLE_STUDENT] ?? 0;
    $teacherCount = $roleCounts[\App\Models\PersonSchoolRole::ROLE_TEACHER] ?? 0;
    $managerCount = $roleCounts[\App\Models\PersonSchoolRole::ROLE_MANAGER] ?? 0;
    $employeeCount = $roleCounts[\App\Models\PersonSchoolRole::ROLE_EMPLOYEE] ?? 0;
    $roleTotal = array_sum($roleChart['values']);
    $hasRoleChart = $roleTotal > 0;
    $hasStudentsChart = array_sum($studentsBySchoolChart['values']) > 0;
    $hasCalendarTypeChart = array_sum($calendarTypeChart['values']) > 0;
@endphp

@section('content')
    <section class="sge-dashboard-hero mb-4" aria-labelledby="dashboard-title">
        <div class="sge-dashboard-hero-content">
            <div class="sge-page-kicker">{{ __('dashboard.tracking_panel') }}</div>
            <h2 id="dashboard-title">{{ __('dashboard.hello', ['name' => $dashboardFirstName]) }}</h2>
            <p>{{ $dashboardIntroduction }}</p>
        </div>
        <div class="sge-dashboard-date" aria-label="{{ __('dashboard.date_brasilia') }}">
            <span>{{ now('America/Sao_Paulo')->translatedFormat('d \d\e F') }}</span>
            <small>{{ now('America/Sao_Paulo')->format('Y') }} · {{ __('dashboard.brasilia_time') }}</small>
        </div>
    </section>

    <nav class="sge-dashboard-shortcuts mb-4" aria-label="{{ __('dashboard.quick_access') }}">
        @foreach ($dashboardShortcuts as $shortcut)
            <a class="sge-dashboard-shortcut" href="{{ $shortcut['url'] }}">
                <span class="sge-dashboard-shortcut-icon" aria-hidden="true">
                    <i class="fas {{ $shortcut['icon'] }}"></i>
                </span>
                <span class="sge-dashboard-shortcut-copy">
                    <strong>{{ $shortcut['label'] }}</strong>
                    <small>{{ $shortcut['description'] }}</small>
                </span>
                <i class="fas fa-chevron-right sge-dashboard-shortcut-arrow" aria-hidden="true"></i>
            </a>
        @endforeach
    </nav>

    @can('manage-people')
        <section class="sge-dashboard-metrics mb-4" aria-label="{{ __('dashboard.main_metrics') }}">
            @can('manage-schools')
                <a class="sge-metric-card sge-metric-brown" href="{{ route('schools.index') }}">
                    <span class="sge-metric-icon" aria-hidden="true"><i class="fas fa-school"></i></span>
                    <span class="sge-metric-label">{{ __('dashboard.active_schools') }}</span>
                    <strong>{{ number_format($schoolCount, 0, ',', '.') }}</strong>
                    <span class="sge-metric-note">{{ __('dashboard.operating_units') }}</span>
                </a>
            @endcan

            <a class="sge-metric-card sge-metric-green" href="{{ route('people.index') }}">
                <span class="sge-metric-icon" aria-hidden="true"><i class="fas fa-users"></i></span>
                <span class="sge-metric-label">{{ __('dashboard.active_people') }}</span>
                <strong>{{ number_format($personCount, 0, ',', '.') }}</strong>
                <span class="sge-metric-note">{{ __('dashboard.active_records') }}</span>
            </a>

            <a class="sge-metric-card sge-metric-blue" href="{{ route('enrollments.index') }}">
                <span class="sge-metric-icon" aria-hidden="true"><i class="fas fa-user-graduate"></i></span>
                <span class="sge-metric-label">{{ __('dashboard.enrollments') }}</span>
                <strong>{{ number_format($activeEnrollmentCount, 0, ',', '.') }}</strong>
                <span class="sge-metric-note">{{ __('dashboard.enrolled_students') }}</span>
            </a>

            <a class="sge-metric-card sge-metric-gold" href="{{ route('data-quality.index') }}">
                <span class="sge-metric-icon" aria-hidden="true"><i class="fas fa-clipboard-check"></i></span>
                <span class="sge-metric-label">{{ __('dashboard.compliance') }}</span>
                <strong>{{ number_format($registrationPendingCount, 0, ',', '.') }}</strong>
                <span class="sge-metric-note">{{ __('dashboard.review_notices') }}</span>
            </a>

            <div class="sge-metric-card sge-metric-orange">
                <span class="sge-metric-icon" aria-hidden="true"><i class="fas fa-calendar-check"></i></span>
                <span class="sge-metric-label">{{ __('dashboard.school_years') }}</span>
                <strong>{{ number_format($activeAcademicYearCount, 0, ',', '.') }}</strong>
                <span class="sge-metric-note">{{ __('dashboard.active_in_period') }}</span>
            </div>
        </section>
    @endcan

    <div class="row">
        <div class="col-xl-8 mb-4">
            <section class="card sge-panel-card h-100" aria-labelledby="calendar-heading">
                <div class="card-header sge-panel-header">
                    <div>
                        <h2 id="calendar-heading">
                            {{ __('dashboard.month_calendar') }}
                            <span class="sr-only">{{ __('dashboard.month_school_calendars') }}</span>
                        </h2>
                        <p>{{ __('dashboard.month_summary', ['days' => $monthSchoolDayCount, 'birthdays' => $birthdays->count(), 'calendars' => $monthAcademicCalendars->count()]) }}</p>
                    </div>
                    <nav class="d-flex align-items-center" aria-label="{{ __('dashboard.calendar_navigation') }}">
                        @if($calendarPreviousMonth)
                            <a class="btn btn-sm btn-outline-primary sge-icon-action mr-2" href="{{ route('dashboard', array_merge(request()->except('calendar_month'), ['calendar_month' => $calendarPreviousMonth->format('Y-m')])) }}#calendar-heading" aria-label="{{ __('dashboard.view_month', ['month' => ucfirst($calendarPreviousMonth->translatedFormat('F Y'))]) }}" title="{{ __('dashboard.previous_month') }}"><i class="fas fa-chevron-left" aria-hidden="true"></i></a>
                        @endif
                        <strong class="text-nowrap">{{ ucfirst($calendarMonth->translatedFormat('F/Y')) }}</strong>
                        @if($calendarNextMonth)
                            <a class="btn btn-sm btn-outline-primary sge-icon-action ml-2" href="{{ route('dashboard', array_merge(request()->except('calendar_month'), ['calendar_month' => $calendarNextMonth->format('Y-m')])) }}#calendar-heading" aria-label="{{ __('dashboard.view_month', ['month' => ucfirst($calendarNextMonth->translatedFormat('F Y'))]) }}" title="{{ __('dashboard.next_month') }}"><i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                        @endif
                    </nav>
                    <div class="sge-calendar-legend" aria-label="{{ __('dashboard.calendar_legend') }}">
                        <span><strong>L</strong> {{ __('dashboard.school_day') }}</span>
                        <span><strong class="sge-dot sge-dot-orange"></strong> {{ __('dashboard.birthday') }}</span>
                    </div>
                </div>
                <div class="card-body">
                    @if ($monthAcademicCalendars->isNotEmpty() || $birthdays->isNotEmpty() || $weekBirthdays->isNotEmpty())
                        @include('dashboard._combined-calendar', ['month' => $combinedCalendarMonth])

                        <div class="sge-dashboard-subgrid mt-3">
                            <section aria-labelledby="visible-calendars-heading">
                                <h3 id="visible-calendars-heading">{{ __('dashboard.visible_calendars') }}</h3>
                                <div class="sge-chip-list">
                                    @forelse ($monthAcademicCalendars as $academicYear)
                                        <span class="sge-info-chip">
                                            <strong>{{ $academicYear->school?->name }}</strong>
                                            {{ $academicYear->name }}
                                        </span>
                                    @empty
                                        <span class="text-muted">{{ __('dashboard.no_active_calendar') }}</span>
                                    @endforelse
                                </div>
                            </section>

                            <section aria-labelledby="birthdays-heading">
                                <div class="sge-subsection-heading">
                                    <h3 id="birthdays-heading">{{ __('dashboard.week_birthdays') }}</h3>
                                    <span>{{ $birthdayWeekStartsAt->format('d/m') }} a {{ $birthdayWeekEndsAt->format('d/m') }}</span>
                                </div>
                                <div class="sge-birthday-list" role="list" aria-label="{{ __('dashboard.birthdays_between', ['start' => $birthdayWeekStartsAt->format('d/m'), 'end' => $birthdayWeekEndsAt->format('d/m')]) }}">
                                @forelse ($weekBirthdays as $person)
                                    <div class="sge-mini-row" role="listitem">
                                        <span>{{ $person->birth_date?->format('d/m') }}</span>
                                        <strong>{{ $person->social_name ?: $person->full_name }}</strong>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">{{ __('dashboard.no_week_birthday') }}</p>
                                @endforelse
                                </div>
                            </section>
                        </div>
                    @else
                        <p class="text-muted mb-0">{{ __('dashboard.no_calendar_or_birthday') }}</p>
                    @endif
                </div>
            </section>
        </div>

        <div class="col-xl-4 mb-4">
            <section class="card sge-panel-card h-100" aria-labelledby="announcements-heading">
                <div class="card-header sge-panel-header">
                    <div>
                        <h2 id="announcements-heading">{{ __('dashboard.active_announcements') }}</h2>
                        <p>{{ __('dashboard.announcement_count', ['count' => $announcements->count()]) }}</p>
                    </div>
                    @can('manage-people')
                        <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('announcements.index') }}" aria-label="{{ __('dashboard.manage_announcements') }}" title="{{ __('dashboard.manage_announcements') }}">
                            <i class="fas fa-pen" aria-hidden="true"></i>
                        </a>
                    @endcan
                </div>
                <div class="card-body">
                    @forelse ($announcements as $announcement)
                        <article class="sge-announcement-item">
                            <div class="sge-announcement-meta">
                                <span>{{ $announcement->school?->name ?? __('dashboard.global') }}</span>
                                @if ($announcement->highlight)
                                    <span class="badge badge-warning">{{ __('dashboard.highlight') }}</span>
                                @endif
                            </div>
                            <h3>{{ $announcement->title }}</h3>
                            <p>{{ $announcement->body }}</p>
                        </article>
                    @empty
                        <p class="text-muted mb-0">{{ __('dashboard.no_active_announcement') }}</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>

    @can('manage-people')
        <div class="row">
            <div class="col-xl-4 col-lg-6 mb-4">
                <section class="card sge-panel-card h-100" aria-labelledby="roles-chart-heading">
                    <div class="card-header sge-panel-header">
                        <div>
                            <h2 id="roles-chart-heading">{{ __('dashboard.people_by_role') }}</h2>
                            <p>{{ __('dashboard.active_roles_total', ['count' => number_format($roleTotal, 0, ',', '.')]) }}</p>
                        </div>
                    </div>
                    <div class="card-body">
                        @if ($hasRoleChart)
                            <div class="sge-chart-wrap" aria-hidden="true">
                                <canvas id="rolesChart"></canvas>
                            </div>
                            <div class="sr-only">
                                @foreach ($roleChart['labels'] as $index => $label)
                                    {{ $label }}: {{ $roleChart['values'][$index] ?? 0 }}.
                                @endforeach
                            </div>
                            <div class="sge-chart-list" aria-hidden="true">
                                @foreach ($roleChart['labels'] as $index => $label)
                                    <div>
                                        <span>{{ $label }}</span>
                                        <strong>{{ number_format($roleChart['values'][$index] ?? 0, 0, ',', '.') }}</strong>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-muted mb-0">{{ __('dashboard.no_role_chart') }}</p>
                        @endif
                    </div>
                </section>
            </div>

            <div class="col-xl-4 col-lg-6 mb-4">
                <section class="card sge-panel-card h-100" aria-labelledby="roles-summary-heading">
                    <div class="card-header sge-panel-header">
                        <div>
                            <h2 id="roles-summary-heading">{{ __('dashboard.community_composition') }}</h2>
                            <p>{{ __('dashboard.main_groups') }}</p>
                        </div>
                    </div>
                    <div class="card-body">
                        @foreach ([
                            ['label' => __('dashboard.students'), 'value' => $studentCount, 'icon' => 'fa-user-graduate'],
                            ['label' => __('dashboard.teachers'), 'value' => $teacherCount, 'icon' => 'fa-chalkboard-teacher'],
                            ['label' => __('dashboard.management'), 'value' => $managerCount, 'icon' => 'fa-user-tie'],
                            ['label' => __('dashboard.school_staff'), 'value' => $employeeCount, 'icon' => 'fa-hands-helping'],
                        ] as $item)
                            @php
                                $percent = $roleTotal > 0 ? round(($item['value'] / $roleTotal) * 100) : 0;
                            @endphp
                            <div class="sge-progress-row">
                                <div>
                                    <i class="fas {{ $item['icon'] }}" aria-hidden="true"></i>
                                    <span>{{ $item['label'] }}</span>
                                </div>
                                <strong>{{ number_format($item['value'], 0, ',', '.') }}</strong>
                                <div class="progress" aria-label="{{ $item['label'] }}: {{ $percent }}%">
                                    <div class="progress-bar" role="progressbar" style="width: {{ $percent }}%;" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>

            <div class="col-xl-4 mb-4">
                <section class="card sge-panel-card h-100" aria-labelledby="calendar-chart-heading">
                    <div class="card-header sge-panel-header">
                        <div>
                            <h2 id="calendar-chart-heading">{{ __('dashboard.month_days') }}</h2>
                            <p>{{ __('dashboard.day_type_distribution') }}</p>
                        </div>
                    </div>
                    <div class="card-body">
                        @if ($hasCalendarTypeChart)
                            <div class="sge-chart-wrap sge-chart-wrap-compact" aria-hidden="true">
                                <canvas id="calendarTypeChart"></canvas>
                            </div>
                            <div class="sge-chart-list">
                                @foreach ($calendarTypeChart['labels'] as $index => $label)
                                    <div>
                                        <span>{{ $label }}</span>
                                        <strong>{{ number_format($calendarTypeChart['values'][$index] ?? 0, 0, ',', '.') }}</strong>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-muted mb-0">{{ __('dashboard.no_calendar_days') }}</p>
                        @endif
                    </div>
                </section>
            </div>
        </div>

        <section class="card sge-panel-card mb-4" aria-labelledby="students-school-heading">
            <div class="card-header sge-panel-header">
                <div>
                    <h2 id="students-school-heading">{{ __('dashboard.students_by_school') }}</h2>
                    <p>{{ __('dashboard.students_by_school_help') }}</p>
                </div>
            </div>
            <div class="card-body">
                @if ($hasStudentsChart)
                    <div class="sge-chart-wrap sge-chart-wrap-wide" aria-hidden="true">
                        <canvas id="studentsBySchoolChart"></canvas>
                    </div>
                    <div class="table-responsive mt-3">
                        <table class="table table-sm sge-accessible-chart-table mb-0">
                            <caption class="sr-only">{{ __('dashboard.students_chart_data') }}</caption>
                            <thead>
                                <tr>
                                    <th>{{ __('dashboard.school') }}</th>
                                    <th class="text-right">{{ __('dashboard.students') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($studentsBySchoolChart['labels'] as $index => $label)
                                    <tr>
                                        <td>{{ $label }}</td>
                                        <td class="text-right">{{ number_format($studentsBySchoolChart['values'][$index] ?? 0, 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted mb-0">{{ __('dashboard.no_students_by_school') }}</p>
                @endif
            </div>
        </section>

        <script src="{{ asset('template/vendor/chart.js/Chart.min.js') }}"></script>
        <script>
            const chartColors = ['#6B3D2E', '#44693D', '#DB6B30', '#4A86A0', '#F1C64E', '#7F56D9', '#2A9D8F'];
            const roleValues = @json($roleChart['values']);
            const calendarTypeValues = @json($calendarTypeChart['values']);
            const studentsBySchoolValues = @json($studentsBySchoolChart['values']);

            Chart.defaults.global.defaultFontFamily = "'Atkinson Hyperlegible Next', 'Atkinson Hyperlegible', sans-serif";
            Chart.defaults.global.defaultFontColor = '#51443d';

            if (document.getElementById('rolesChart') && roleValues.some((value) => value > 0)) {
                new Chart(document.getElementById('rolesChart'), {
                    type: 'doughnut',
                    data: {
                        labels: @json($roleChart['labels']),
                        datasets: [{
                            data: roleValues,
                            backgroundColor: chartColors,
                            borderColor: '#fff',
                            borderWidth: 3,
                        }],
                    },
                    options: {
                        maintainAspectRatio: false,
                        legend: { display: false },
                        cutoutPercentage: 68,
                        tooltips: {
                            callbacks: {
                                label: (tooltipItem, data) => `${data.labels[tooltipItem.index]}: ${data.datasets[0].data[tooltipItem.index]}`,
                            },
                        },
                    },
                });
            }

            if (document.getElementById('calendarTypeChart') && calendarTypeValues.some((value) => value > 0)) {
                new Chart(document.getElementById('calendarTypeChart'), {
                    type: 'doughnut',
                    data: {
                        labels: @json($calendarTypeChart['labels']),
                        datasets: [{
                            data: calendarTypeValues,
                            backgroundColor: chartColors,
                            borderColor: '#fff',
                            borderWidth: 3,
                        }],
                    },
                    options: {
                        maintainAspectRatio: false,
                        legend: { display: false },
                        cutoutPercentage: 64,
                    },
                });
            }

            if (document.getElementById('studentsBySchoolChart') && studentsBySchoolValues.some((value) => value > 0)) {
                new Chart(document.getElementById('studentsBySchoolChart'), {
                    type: 'horizontalBar',
                    data: {
                        labels: @json($studentsBySchoolChart['labels']),
                        datasets: [{
                            label: @json(__('dashboard.students')),
                            data: studentsBySchoolValues,
                            backgroundColor: '#44693D',
                            borderColor: '#355330',
                            borderWidth: 1,
                        }],
                    },
                    options: {
                        maintainAspectRatio: false,
                        legend: { display: false },
                        scales: {
                            xAxes: [{
                                ticks: {
                                    beginAtZero: true,
                                    precision: 0,
                                },
                                gridLines: { color: 'rgba(107, 61, 46, .08)' },
                            }],
                            yAxes: [{
                                gridLines: { display: false },
                            }],
                        },
                    },
                });
            }
        </script>
    @endcan
@endsection
