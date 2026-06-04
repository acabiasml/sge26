<div class="form-row">
    <div class="form-group col-md-4">
        <label for="role">Papel</label>
        <select id="role" name="role" class="form-control @error('role') is-invalid @enderror" data-role-select required>
            <option value="">Selecione</option>
            @foreach ($roles as $value => $label)
                <option value="{{ $value }}" @selected(old('role', $roleModel->role ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-4">
        <label for="school_id">Escola</label>
        <select id="school_id" name="school_id" class="form-control @error('school_id') is-invalid @enderror">
            <option value="">Global / sem escola</option>
            @foreach ($schools as $school)
                <option value="{{ $school->id }}" @selected((int) old('school_id', $roleModel->school_id ?? 0) === $school->id)>{{ $school->name }}</option>
            @endforeach
        </select>
        <small class="form-text text-muted">Administração é sempre global, sem escola vinculada.</small>
        @error('school_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-4">
        <label for="position">Área da gestão</label>
        <select id="position" name="position" class="form-control @error('position') is-invalid @enderror" data-manager-position>
            <option value="">Selecione quando o papel for Gestão</option>
            @foreach ($positions as $value => $label)
                <option value="{{ $value }}" @selected(old('position', $roleModel->position ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('position') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="form-row">
    <div class="form-group col-md-4">
        <label for="started_at">Início</label>
        <input id="started_at" name="started_at" type="date" class="form-control @error('started_at') is-invalid @enderror" value="{{ old('started_at', isset($roleModel) && $roleModel->started_at ? $roleModel->started_at->format('Y-m-d') : '') }}">
        <small class="form-text text-muted">Obrigatório para vínculos de escola. Administração recebe a data atual.</small>
        @error('started_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-4">
        <label for="ended_at">Fim</label>
        <input id="ended_at" name="ended_at" type="date" class="form-control @error('ended_at') is-invalid @enderror" value="{{ old('ended_at', isset($roleModel) && $roleModel->ended_at ? $roleModel->ended_at->format('Y-m-d') : '') }}">
        @error('ended_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-4 d-flex align-items-end">
        <div class="form-check mb-2">
            <input type="hidden" name="active" value="0">
            <input id="role_active" name="active" value="1" type="checkbox" class="form-check-input" @checked(old('active', $roleModel->active ?? true))>
            <label for="role_active" class="form-check-label">Vínculo ativo</label>
        </div>
    </div>
</div>
