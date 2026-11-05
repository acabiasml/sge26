@php
    $accessOpen = request()->hasAny(['teacher_access_search', 'teacher_access_school', 'teacher_access_status', 'teacher_access_page']);
    $accessIcons = ['recent' => 'fa-check-circle', 'overdue' => 'fa-exclamation-circle', 'missing' => 'fa-question-circle'];
    $calendarQuery = request()->only(['calendar_month']);
@endphp
<details id="teacher-access" class="card sge-panel-card sge-teacher-access mb-4" @if($accessOpen) open @endif>
    <summary class="sge-access-summary">
        <span class="sge-access-title"><i class="fas fa-user-clock" aria-hidden="true"></i> {{ __('dashboard.access.title') }}</span>
        <span class="sge-access-counts">
            @foreach(['recent', 'overdue', 'missing'] as $accessStatus)
                <span class="sge-access-status sge-access-{{ $accessStatus }}">
                    <i class="fas {{ $accessIcons[$accessStatus] }}" aria-hidden="true"></i>
                    {{ __('dashboard.access.count_'.$accessStatus, ['count' => $access['summary'][$accessStatus]]) }}
                </span>
            @endforeach
        </span>
        <i class="fas fa-chevron-down sge-access-chevron" aria-hidden="true"></i>
    </summary>
    <div class="card-body">
        <p id="teacher-access-help" class="text-muted small">{{ __('dashboard.access.help') }}</p>
        <form method="GET" action="{{ route('dashboard') }}#teacher-access" class="sge-access-filters" aria-label="{{ __('dashboard.access.filters') }}">
            @foreach($calendarQuery as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <div class="sge-access-search">
                <label for="teacher-access-search">{{ __('dashboard.access.teacher') }}</label>
                <input id="teacher-access-search" class="form-control" type="search" name="teacher_access_search" maxlength="120" value="{{ $access['search'] }}">
            </div>
            @if($access['schools']->count() > 1)
                <div>
                    <label for="teacher-access-school">{{ __('dashboard.school') }}</label>
                    <select id="teacher-access-school" class="form-control" name="teacher_access_school">
                        <option value="">{{ __('dashboard.access.all_schools') }}</option>
                        @foreach($access['schools'] as $accessSchool)
                            <option value="{{ $accessSchool->id }}" @selected($access['school_id'] === $accessSchool->id)>{{ $accessSchool->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label for="teacher-access-status">{{ __('dashboard.access.status') }}</label>
                <select id="teacher-access-status" class="form-control" name="teacher_access_status">
                    @foreach(['all', 'recent', 'overdue', 'missing'] as $accessStatus)
                        <option value="{{ $accessStatus }}" @selected($access['status'] === $accessStatus)>{{ __('dashboard.access.'.$accessStatus) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sge-access-actions">
                <button class="btn btn-primary" type="submit"><i class="fas fa-filter" aria-hidden="true"></i> {{ __('dashboard.access.apply') }}</button>
                @if($accessOpen)
                    <a class="btn btn-outline-secondary" href="{{ route('dashboard', $calendarQuery) }}#teacher-access">{{ __('dashboard.access.clear') }}</a>
                @endif
            </div>
        </form>
        <div class="table-responsive" role="region" aria-label="{{ __('dashboard.access.title') }}" aria-describedby="teacher-access-help" tabindex="0">
            <table class="table table-hover mb-0">
                <thead><tr>
                    <th scope="col">{{ __('dashboard.access.teacher') }}</th>
                    <th scope="col">{{ __('dashboard.access.last_access') }}</th>
                    <th scope="col">{{ __('dashboard.access.status') }}</th>
                </tr></thead>
                <tbody>
                    @forelse($access['teachers'] as $accessTeacher)
                        <tr>
                            <th scope="row" class="font-weight-normal">
                                <span>{{ $accessTeacher['person']->full_name }}</span>
                                <small class="d-block text-muted">{{ $accessTeacher['schools']->implode(' · ') }}</small>
                            </th>
                            <td>
                                @if($accessTeacher['last_access_at'])
                                    <time datetime="{{ $accessTeacher['last_access_at']->toIso8601String() }}">{{ $accessTeacher['last_access_at']->setTimezone('America/Cuiaba')->format('d/m/Y H:i') }}</time>
                                @else
                                    {{ __('dashboard.access.'.($accessTeacher['has_account'] ? 'missing' : 'no_account')) }}
                                @endif
                            </td>
                            <td>
                                <span class="sge-access-status sge-access-{{ $accessTeacher['status'] }}">
                                    <i class="fas {{ $accessIcons[$accessTeacher['status']] }}" aria-hidden="true"></i>
                                    {{ __('dashboard.access.'.$accessTeacher['status']) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-muted">{{ __('dashboard.access.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($access['teachers']->hasPages())
            <nav class="mt-3" aria-label="{{ __('dashboard.access.pages') }}">{{ $access['teachers']->links('pagination::bootstrap-4') }}</nav>
        @endif
    </div>
</details>
