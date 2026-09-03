@extends('layouts.app')

@section('title', __('screens.school_histories'))

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <div class="sge-page-kicker">{{ __('screens.school_life') }}</div>
        <h1 class="h3 mb-0 text-gray-800">{{ __('screens.school_histories') }}</h1>
        <p class="mb-0 text-muted">{{ __('screens.history_intro') }}</p>
    </div>
</div>
<div class="card shadow mb-4"><div class="card-body">
    <form method="get" class="form-row align-items-end mb-4">
        <div class="form-group {{ $isAdministrator ? 'col-md-5' : 'col-md-8' }} mb-md-0"><label for="q">{{ __('screens.student') }}</label><input id="q" name="q" class="form-control" value="{{ request('q') }}" placeholder="{{ __('screens.student_placeholder') }}"></div>
        @if($isAdministrator)
            <div class="form-group col-md-4 mb-md-0"><label for="school">{{ __('screens.school') }}</label><select id="school" name="school" class="form-control"><option value="">{{ __('screens.all_schools') }}</option>@foreach($schools as $school)<option value="{{ $school->id }}" @selected($selectedSchoolId === $school->id)>{{ $school->name }}</option>@endforeach</select></div>
        @endif
        <div class="form-group {{ $isAdministrator ? 'col-md-3' : 'col-md-4' }} mb-0"><button class="btn btn-primary" type="submit"><i class="fas fa-search mr-1" aria-hidden="true"></i>{{ __('screens.filter') }}</button> <a class="btn btn-outline-secondary" href="{{ route('student-histories.index') }}">{{ __('screens.clear') }}</a></div>
    </form>
    <div class="table-responsive"><table class="table table-hover">
        <thead><tr><th>{{ __('screens.student') }}</th><th>{{ __('screens.internal_records') }}</th><th>{{ __('screens.progress') }}</th><th class="text-right">{{ __('screens.actions') }}</th></tr></thead>
        <tbody>
        @forelse($students as $student)
            <tr>
                <td><strong>{{ $student->full_name }}</strong><br><span class="small text-muted">INEP {{ $student->student_inep ?: __('screens.not_informed') }}</span></td>
                <td>{{ __('screens.enrollment_count', ['count' => $student->student_enrollments_count]) }}</td>
                <td>{{ __('screens.registered_years', ['count' => $student->academicHistories->sum('years_count')]) }}</td>
                <td class="text-right text-nowrap">
                    <a class="btn btn-sm btn-primary" href="{{ route('student-histories.student', $student) }}">{{ __('screens.manage_histories') }}</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="text-center text-muted py-4">{{ __('screens.no_student') }}</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="d-flex justify-content-center mt-3">{{ $students->links('pagination::bootstrap-4') }}</div>
</div></div>
@endsection
