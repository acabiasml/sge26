<form method="POST" action="{{ isset($justification) ? route('attendance-justifications.update', $justification) : route('attendance-justifications.store') }}">
    @csrf
    @isset($justification) @method('PUT') @endisset
    <div class="form-group">
        <label for="justification_enrollment">{{ __('Estudante e turma') }}</label>
        <select id="justification_enrollment" name="student_enrollment_id" class="form-control @error('student_enrollment_id') is-invalid @enderror" required>
            <option value="">{{ __('Selecione') }}</option>
            @foreach ($enrollments as $enrollment)
                <option value="{{ $enrollment->id }}" @selected((int) old('student_enrollment_id', $justification->student_enrollment_id ?? null) === $enrollment->id)>{{ $enrollment->student?->full_name }} · {{ $enrollment->schoolClass?->name }} · {{ $enrollment->schoolClass?->academicYear?->referenceYearsLabel() }}</option>
            @endforeach
        </select>
        @error('student_enrollment_id')<div class="invalid-feedback">{{ __($message) }}</div>@enderror
    </div>
    <div class="row">
        <div class="col-md-6 form-group">
            <label for="justification_starts_at">{{ __('Início') }}</label>
            <input id="justification_starts_at" name="starts_at" type="date" class="form-control @error('starts_at') is-invalid @enderror" value="{{ old('starts_at', isset($justification) ? $justification->starts_at?->toDateString() : '') }}" required>
            @error('starts_at')<div class="invalid-feedback">{{ __($message) }}</div>@enderror
        </div>
        <div class="col-md-6 form-group">
            <label for="justification_ends_at">{{ __('Fim') }}</label>
            <input id="justification_ends_at" name="ends_at" type="date" class="form-control @error('ends_at') is-invalid @enderror" value="{{ old('ends_at', isset($justification) ? $justification->ends_at?->toDateString() : '') }}" required>
            @error('ends_at')<div class="invalid-feedback">{{ __($message) }}</div>@enderror
        </div>
    </div>
    <div class="form-group">
        <label for="justification_reason">{{ __('Motivo') }}</label>
        <textarea id="justification_reason" name="reason" class="form-control @error('reason') is-invalid @enderror" rows="3" required>{{ old('reason', $justification->reason ?? '') }}</textarea>
        @error('reason')<div class="invalid-feedback">{{ __($message) }}</div>@enderror
    </div>
    <div class="form-group">
        <label for="attendance-effect">{{ __('Efeito na frequência') }}</label>
        <select id="attendance-effect" class="form-control" name="attendance_effect" required>
            @foreach(\App\Models\DiaryAttendanceJustification::EFFECTS as $effect => $label)
                <option value="{{ $effect }}" @selected(old('attendance_effect', $justification->attendance_effect ?? 'documented') === $effect)>{{ __($label) }}</option>
            @endforeach
        </select>
        <small class="form-text text-muted">{{ __('A justificativa registra a ausência. O abono ou a compensação só entra no cálculo quando houver fundamento e comprovação.') }}</small>
        @error('attendance_effect')<div class="text-danger">{{ $message }}</div>@enderror
    </div>
    <div class="form-group">
        <label for="attendance-basis">{{ __('Fundamento e referência da comprovação para abono ou compensação') }}</label>
        <input id="attendance-basis" name="attendance_basis" class="form-control" maxlength="500" value="{{ old('attendance_basis', $justification->attendance_basis ?? '') }}">
        @error('attendance_basis')<div class="text-danger">{{ $message }}</div>@enderror
    </div>
    <button class="btn btn-primary" type="submit"><i class="fas fa-{{ isset($justification) ? 'save' : 'notes-medical' }} mr-1" aria-hidden="true"></i>{{ isset($justification) ? __('Salvar alterações') : __('Registrar justificativa') }}</button>
</form>
