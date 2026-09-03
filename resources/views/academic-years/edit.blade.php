@extends('layouts.app')

@section('title', __('screens.edit_school_year'))
@section('page-title', __('screens.edit_school_year'))

@section('page-actions')
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('academic-years.show', $academicYear) }}" aria-label="{{ __('screens.back_to_school_year', ['year' => $academicYear->name]) }}" title="{{ __('screens.back') }}">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    <div class="row">
        <aside class="col-xl-4 mb-4">
            <section class="card shadow h-100 sge-academic-context" aria-labelledby="academic-context-title">
                <div class="card-header py-3"><h2 id="academic-context-title" class="h6 m-0 font-weight-bold text-primary">{{ __('screens.calendar_context') }}</h2></div>
                <div class="card-body">
                    <div class="sge-context-school-mark"><i class="fas fa-school" aria-hidden="true"></i></div>
                    <h3 class="h6 font-weight-bold mb-1">{{ $academicYear->school?->name }}</h3>
                    <p class="small text-muted mb-4">{{ __('screens.calendar_scope_help') }}</p>
                    <dl class="mb-0">
                        <dt>{{ __('screens.current_period') }}</dt>
                        <dd>{{ $academicYear->starts_at?->format('d/m/Y') }} a {{ $academicYear->ends_at?->format('d/m/Y') }}</dd>
                        <dt>{{ __('screens.status') }}</dt>
                        <dd><span class="badge badge-{{ $academicYear->active ? 'success' : 'secondary' }}">{{ $academicYear->active ? __('screens.active_m') : __('screens.inactive_m') }}</span></dd>
                        <dt>{{ __('screens.calendar') }}</dt>
                        <dd>{{ $academicYear->approved_at ? __('screens.approved_on', ['date' => $academicYear->approved_at->format('d/m/Y')]) : __('screens.being_prepared') }}</dd>
                    </dl>
                </div>
            </section>
        </aside>
        <div class="col-xl-8 mb-4">
            <section class="card shadow sge-academic-edit-card" aria-labelledby="academic-edit-title">
                <div class="card-header py-3"><h2 id="academic-edit-title" class="h6 m-0 font-weight-bold text-primary">{{ __('screens.school_year_data') }}</h2></div>
                <div class="card-body">
                    <p class="text-muted mb-4">{{ __('screens.school_year_edit_help') }}</p>
                    <form method="POST" action="{{ route('academic-years.update', $academicYear) }}">
                        @include('academic-years._form', ['school' => $academicYear->school])
                    </form>
                </div>
            </section>
        </div>
    </div>
@endsection
