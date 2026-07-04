@csrf

@if (isset($academicYear))
    @method('PUT')
@endif

@php($backRoute = isset($academicYear) ? route('academic-years.show', $academicYear) : route('schools.academic-years.index', $school))

<div class="sge-form-context mb-4">
    <i class="fas fa-school" aria-hidden="true"></i>
    <div><span>Escola vinculada</span><strong>{{ $school->name }}</strong></div>
</div>

<fieldset class="sge-form-section">
    <legend>Identificação</legend>
    <div class="row">
        <div class="col-md-7 form-group">
            <label for="name">Nome do ano letivo</label>
            <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $academicYear->name ?? '') }}" placeholder="Educação Básica" required>
            <small class="form-text text-muted">Use um nome que identifique este calendário, como Educação Básica ou Ensino Técnico.</small>
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-5 form-group">
            <label for="reference_year">Ano principal</label>
            <input id="reference_year" name="reference_year" data-mask="year" inputmode="numeric" autocomplete="off" class="form-control @error('reference_year') is-invalid @enderror" value="{{ old('reference_year', $academicYear->reference_year ?? now()->year) }}" required>
            <small class="form-text text-muted">Usado para organização e busca.</small>
            @error('reference_year') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
</fieldset>

<fieldset class="sge-form-section">
    <legend>Critérios de aprovação</legend>
    <div class="row">
        <div class="col-md-6 form-group mb-md-0">
            <label for="passing_points">Soma mínima de pontos para aprovação</label>
            <input id="passing_points" name="passing_points" data-mask="decimal" inputmode="decimal" class="form-control @error('passing_points') is-invalid @enderror" value="{{ old('passing_points', $academicYear->passing_points ?? 24) }}" required>
            <small class="form-text text-muted">Informe a pontuação anual exigida, como 24 ou 22,5. Use múltiplos de 0,5.</small>
            @error('passing_points') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6 form-group mb-0">
            <label for="minimum_attendance_percentage">Frequência mínima para aprovação</label>
            <div class="input-group"><input id="minimum_attendance_percentage" name="minimum_attendance_percentage" data-mask="percentage" inputmode="numeric" class="form-control @error('minimum_attendance_percentage') is-invalid @enderror" value="{{ old('minimum_attendance_percentage', $academicYear->minimum_attendance_percentage ?? 75) }}" required><div class="input-group-append"><span class="input-group-text">%</span></div></div>
            <small class="form-text text-muted">Ausências justificadas permanecem registradas, mas contam como presença neste cálculo.</small>
            @error('minimum_attendance_percentage') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
</fieldset>

<fieldset class="sge-form-section">
    <legend>Vigência</legend>
    <div class="row">
        <div class="col-md-4 form-group">
            <label for="starts_at">Início</label>
            <input id="starts_at" name="starts_at" type="date" class="form-control @error('starts_at') is-invalid @enderror" value="{{ old('starts_at', isset($academicYear) ? $academicYear->starts_at?->toDateString() : '') }}" required>
            @error('starts_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4 form-group">
            <label for="ends_at">Fim</label>
            <input id="ends_at" name="ends_at" type="date" class="form-control @error('ends_at') is-invalid @enderror" value="{{ old('ends_at', isset($academicYear) ? $academicYear->ends_at?->toDateString() : '') }}" required>
            @error('ends_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        @if (isset($academicYear))
            <div class="col-md-4 form-group">
                <label for="approved_at">Data de aprovação</label>
                <input id="approved_at" name="approved_at" type="date" class="form-control @error('approved_at') is-invalid @enderror" value="{{ old('approved_at', $academicYear->approved_at?->toDateString()) }}">
                <small class="form-text text-muted">Administração e Gestão podem ajustar esta data.</small>
                @error('approved_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        @endif
        <div class="col-md-4 form-group d-flex align-items-end">
            <div class="custom-control custom-switch mb-2">
                <input type="checkbox" class="custom-control-input" id="active" name="active" value="1" @checked(old('active', $academicYear->active ?? true))>
                <label class="custom-control-label" for="active">Ano letivo ativo</label>
            </div>
        </div>
    </div>
</fieldset>

<fieldset class="sge-form-section">
    <legend>Observações</legend>
    <div class="form-group mb-0">
        <label for="notes">Anotações internas</label>
        <textarea id="notes" name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $academicYear->notes ?? '') }}</textarea>
        @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</fieldset>

@if (! isset($academicYear))
    <div class="card border-left-primary shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h6 font-weight-bold text-primary mb-2">Geração inicial do calendário</h2>
            <p class="small text-gray-700 mb-0">Ao salvar, os dias de segunda a sexta serão criados como férias (FF). Sábados e domingos ficam como fim de semana, sem FF. Ao cadastrar um período avaliativo, as datas dele passam a ser letivas automaticamente.</p>
        </div>
    </div>
@endif

<div class="sge-form-actions pt-2">
    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1" aria-hidden="true"></i>Salvar alterações</button>
    <a href="{{ $backRoute }}" class="btn btn-outline-secondary">Cancelar</a>
</div>
