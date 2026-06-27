@php($slotType = old('type', $slot?->type ?? \App\Models\SchoolClassScheduleSlot::TYPE_CLASS))
@php($selectedAssignmentId = (int) old('school_class_component_id', $slot?->school_class_component_id ?? 0))

<div class="form-group">
    <label for="{{ $prefix }}_weekday">Dia</label>
    <select id="{{ $prefix }}_weekday" name="weekday" class="form-control @error('weekday') is-invalid @enderror" required>
        @foreach($weekdays as $weekday => $label)
            <option value="{{ $weekday }}" @selected((int) old('weekday', $slot?->weekday ?? 1) === (int) $weekday)>{{ $label }}</option>
        @endforeach
    </select>
    @error('weekday')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="form-group">
    <label for="{{ $prefix }}_type">Tipo</label>
    <select id="{{ $prefix }}_type" name="type" class="form-control @error('type') is-invalid @enderror" data-slot-type>
        @foreach(\App\Models\SchoolClassScheduleSlot::TYPE_LABELS as $type => $label)
            <option value="{{ $type }}" @selected($slotType === $type)>{{ $label }}</option>
        @endforeach
    </select>
    @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="form-group" data-slot-component>
    <label for="{{ $prefix }}_component">Componente curricular</label>
    <select id="{{ $prefix }}_component" name="school_class_component_id" class="form-control @error('school_class_component_id') is-invalid @enderror">
        <option value="">Selecione</option>
        @foreach($assignments as $assignment)
            @php($used = (int) $assignmentUsage->get($assignment->id, 0))
            @php($limit = (int) ($assignment->component?->weekly_lessons ?? 0))
            @php($currentSlotUsesThisAssignment = $slot && (int) $slot->school_class_component_id === (int) $assignment->id)
            @php($available = ($limit > 0 && $used < $limit) || $currentSlotUsesThisAssignment)
            <option value="{{ $assignment->id }}" @selected($selectedAssignmentId === $assignment->id) @disabled(! $available)>
                {{ $assignment->component?->name }} · {{ $assignment->component?->course?->name }}
                @if($assignment->teacher) · {{ $assignment->teacher->full_name }} @endif
                · {{ $used }}/{{ $limit }} aula(s)
            </option>
        @endforeach
    </select>
    @error('school_class_component_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="form-group" data-slot-label hidden>
    <label for="{{ $prefix }}_label">Nome do intervalo</label>
    <input id="{{ $prefix }}_label" name="label" class="form-control @error('label') is-invalid @enderror" value="{{ old('label', $slot?->label) }}" placeholder="Intervalo, almoço, estudo...">
    @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="row">
    <div class="col-6 form-group">
        <label for="{{ $prefix }}_starts_at">Início</label>
        <input id="{{ $prefix }}_starts_at" name="starts_at" type="time" min="06:00" max="22:00" class="form-control @error('starts_at') is-invalid @enderror" value="{{ old('starts_at', $slot ? substr($slot->starts_at, 0, 5) : '') }}" required data-slot-start>
        @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-6 form-group">
        <label for="{{ $prefix }}_ends_at">Fim</label>
        <input id="{{ $prefix }}_ends_at" name="ends_at" type="time" min="06:00" max="22:00" class="form-control @error('ends_at') is-invalid @enderror" value="{{ old('ends_at', $slot ? substr($slot->ends_at, 0, 5) : '') }}" required data-slot-end @if($slot) data-touched="1" @endif>
        @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
