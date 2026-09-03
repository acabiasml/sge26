@php($fieldPrefix = $fieldPrefix ?? 'role_')

<div class="form-row">
    <div class="form-group col-md-4">
        <label for="{{ $fieldPrefix }}role">{{ __('screens.role') }}</label>
        <select id="{{ $fieldPrefix }}role" name="role" class="form-control @error('role') is-invalid @enderror" data-role-select required>
            <option value="">{{ __('screens.select') }}</option>
            @foreach ($roles as $value => $label)
                <option value="{{ $value }}" @selected(old('role', $roleModel->role ?? '') === $value)>{{ __('roles.roles.'.$value) }}</option>
            @endforeach
        </select>
        @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-4">
        <label for="{{ $fieldPrefix }}school_id">{{ __('screens.school') }}</label>
        <select id="{{ $fieldPrefix }}school_id" name="school_id" class="form-control @error('school_id') is-invalid @enderror">
            <option value="">{{ __('screens.global_without_school') }}</option>
            @foreach ($schools as $school)
                <option value="{{ $school->id }}" @selected((int) old('school_id', $roleModel->school_id ?? 0) === $school->id)>{{ $school->name }}</option>
            @endforeach
        </select>
        <small class="form-text text-muted">{{ __('screens.administration_global_help') }}</small>
        @error('school_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-4">
        <label for="{{ $fieldPrefix }}position">{{ __('screens.management_area') }}</label>
        <select id="{{ $fieldPrefix }}position" name="position" class="form-control @error('position') is-invalid @enderror" data-manager-position>
            <option value="">{{ __('screens.select_manager_role') }}</option>
            @foreach ($positions as $value => $label)
                <option value="{{ $value }}" @selected(old('position', $roleModel->position ?? '') === $value)>{{ __('roles.positions.'.$value) }}</option>
            @endforeach
        </select>
        @error('position') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="form-row">
    <div class="form-group col-md-4">
        <label for="{{ $fieldPrefix }}started_at">{{ __('screens.start') }}</label>
        <input id="{{ $fieldPrefix }}started_at" name="started_at" type="date" class="form-control @error('started_at') is-invalid @enderror" value="{{ old('started_at', isset($roleModel) && $roleModel->started_at ? $roleModel->started_at->format('Y-m-d') : '') }}">
        <small class="form-text text-muted">{{ __('screens.school_relationship_start_help') }}</small>
        @error('started_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-4">
        <label for="{{ $fieldPrefix }}ended_at">{{ __('screens.end') }}</label>
        <input id="{{ $fieldPrefix }}ended_at" name="ended_at" type="date" class="form-control @error('ended_at') is-invalid @enderror" value="{{ old('ended_at', isset($roleModel) && $roleModel->ended_at ? $roleModel->ended_at->format('Y-m-d') : '') }}">
        @error('ended_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-4 d-flex align-items-end">
        <div class="form-check mb-2">
            <input type="hidden" name="active" value="0">
            <input id="{{ $fieldPrefix }}active" name="active" value="1" type="checkbox" class="form-check-input" @checked(old('active', $roleModel->active ?? true))>
            <label for="{{ $fieldPrefix }}active" class="form-check-label">{{ __('screens.active_relationship') }}</label>
        </div>
    </div>
</div>
