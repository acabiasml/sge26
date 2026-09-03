@csrf

@if (isset($academicYear))
    @method('PUT')
@endif

@php($backRoute = isset($academicYear) ? route('academic-years.show', $academicYear) : route('schools.academic-years.index', $school))

<div class="sge-form-context mb-4">
    <i class="fas fa-school" aria-hidden="true"></i>
    <div><span>{{ __('screens.linked_school') }}</span><strong>{{ $school->name }}</strong></div>
</div>

<fieldset class="sge-form-section">
    <legend>{{ __('screens.identification') }}</legend>
    <div class="row">
        <div class="col-md-7 form-group">
            <label for="name">{{ __('screens.school_year_name') }}</label>
            <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $academicYear->name ?? '') }}" placeholder="{{ __('screens.basic_education') }}" required>
            <small class="form-text text-muted">{{ __('screens.school_year_name_help') }}</small>
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-5 form-group">
            <label for="reference_year">{{ __('screens.main_year') }}</label>
            <input id="reference_year" name="reference_year" data-mask="year" inputmode="numeric" autocomplete="off" class="form-control @error('reference_year') is-invalid @enderror" value="{{ old('reference_year', $academicYear->reference_year ?? now()->year) }}" required>
            <small class="form-text text-muted">{{ __('screens.main_year_help') }}</small>
            @error('reference_year') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
</fieldset>

<fieldset class="sge-form-section">
    <legend>{{ __('screens.approval_criteria') }}</legend>
    <div class="row">
        <div class="col-md-6 form-group mb-md-0">
            <label for="passing_points">{{ __('screens.minimum_points') }}</label>
            <input id="passing_points" name="passing_points" data-mask="decimal" inputmode="decimal" class="form-control @error('passing_points') is-invalid @enderror" value="{{ old('passing_points', $academicYear->passing_points ?? 24) }}" required>
            <small class="form-text text-muted">{{ __('screens.minimum_points_help') }}</small>
            @error('passing_points') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6 form-group mb-0">
            <label for="minimum_attendance_percentage">{{ __('screens.minimum_attendance') }}</label>
            <div class="input-group"><input id="minimum_attendance_percentage" name="minimum_attendance_percentage" data-mask="percentage" inputmode="numeric" class="form-control @error('minimum_attendance_percentage') is-invalid @enderror" value="{{ old('minimum_attendance_percentage', $academicYear->minimum_attendance_percentage ?? 75) }}" required><div class="input-group-append"><span class="input-group-text">%</span></div></div>
            <small class="form-text text-muted">{{ __('screens.attendance_help') }}</small>
            @error('minimum_attendance_percentage') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
</fieldset>

<fieldset class="sge-form-section">
    <legend>{{ __('screens.validity') }}</legend>
    <div class="row">
        <div class="col-md-4 form-group">
            <label for="starts_at">{{ __('screens.start') }}</label>
            <input id="starts_at" name="starts_at" type="date" class="form-control @error('starts_at') is-invalid @enderror" value="{{ old('starts_at', isset($academicYear) ? $academicYear->starts_at?->toDateString() : '') }}" required>
            @error('starts_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4 form-group">
            <label for="ends_at">{{ __('screens.end') }}</label>
            <input id="ends_at" name="ends_at" type="date" class="form-control @error('ends_at') is-invalid @enderror" value="{{ old('ends_at', isset($academicYear) ? $academicYear->ends_at?->toDateString() : '') }}" required>
            @error('ends_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        @if (isset($academicYear))
            <div class="col-md-4 form-group">
                <label for="approved_at">{{ __('screens.approval_date') }}</label>
                <input id="approved_at" name="approved_at" type="date" class="form-control @error('approved_at') is-invalid @enderror" value="{{ old('approved_at', $academicYear->approved_at?->toDateString()) }}">
                <small class="form-text text-muted">{{ __('screens.approval_date_help') }}</small>
                @error('approved_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        @endif
        <div class="col-md-4 form-group d-flex align-items-end">
            <div class="custom-control custom-switch mb-2">
                <input type="checkbox" class="custom-control-input" id="active" name="active" value="1" @checked(old('active', $academicYear->active ?? true))>
                <label class="custom-control-label" for="active">{{ __('screens.active_school_year') }}</label>
            </div>
        </div>
    </div>
</fieldset>

<fieldset class="sge-form-section">
    <legend>{{ __('screens.notes') }}</legend>
    <div class="form-group mb-0">
        <label for="notes">{{ __('screens.internal_notes') }}</label>
        <textarea id="notes" name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $academicYear->notes ?? '') }}</textarea>
        @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</fieldset>

@if (! isset($academicYear))
    <div class="card border-left-primary shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h6 font-weight-bold text-primary mb-2">{{ __('screens.initial_calendar') }}</h2>
            <p class="small text-gray-700 mb-0">{{ __('screens.initial_calendar_help') }}</p>
        </div>
    </div>
@endif

<div class="sge-form-actions pt-2">
    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1" aria-hidden="true"></i>{{ __('screens.save_changes') }}</button>
    <a href="{{ $backRoute }}" class="btn btn-outline-secondary">{{ __('screens.cancel') }}</a>
</div>
