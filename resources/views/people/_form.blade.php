@php
    $lockInstitutionalEmail = $lockInstitutionalEmail ?? false;
    $showActiveControl = $showActiveControl ?? true;
@endphp

<div class="form-row">
    <div class="form-group col-md-6">
        <label for="full_name">Nome completo</label>
        <input id="full_name" name="full_name" class="form-control @error('full_name') is-invalid @enderror" value="{{ old('full_name', $person->full_name ?? '') }}" required>
        @error('full_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-6">
        <label for="social_name">Nome social</label>
        <input id="social_name" name="social_name" class="form-control @error('social_name') is-invalid @enderror" value="{{ old('social_name', $person->social_name ?? '') }}">
        @error('social_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="form-row">
    <div class="form-group col-md-4">
        <label for="institutional_email">E-mail institucional</label>
        <input id="institutional_email" name="institutional_email" type="email" inputmode="email" autocomplete="email" class="form-control @error('institutional_email') is-invalid @enderror" value="{{ old('institutional_email', $person->institutional_email ?? '') }}" @readonly($lockInstitutionalEmail) required>
        @if ($lockInstitutionalEmail)
            <small class="form-text text-muted">Seu e-mail institucional não pode ser alterado por você.</small>
        @endif
        @error('institutional_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-4">
        <label for="personal_email">E-mail pessoal</label>
        <input id="personal_email" name="personal_email" type="email" inputmode="email" autocomplete="email" class="form-control @error('personal_email') is-invalid @enderror" value="{{ old('personal_email', $person->personal_email ?? '') }}">
        @error('personal_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-4">
        <label for="phone">Telefone</label>
        <input id="phone" name="phone" data-mask="phone" inputmode="tel" autocomplete="tel" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $person->phone ?? '') }}">
        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="form-row">
    <div class="form-group col-md-4">
        <label for="cpf">CPF</label>
        <input id="cpf" name="cpf" data-mask="cpf" inputmode="numeric" autocomplete="off" class="form-control @error('cpf') is-invalid @enderror" value="{{ old('cpf', $person->cpf ?? '') }}" required>
        @error('cpf') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-4">
        <label for="birth_date">Data de nascimento</label>
        <input id="birth_date" name="birth_date" type="date" class="form-control @error('birth_date') is-invalid @enderror" value="{{ old('birth_date', isset($person) && $person->birth_date ? $person->birth_date->format('Y-m-d') : '') }}">
        @error('birth_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    @if ($showActiveControl)
        <div class="form-group col-md-4 d-flex align-items-end">
            <div class="form-check mb-2">
                <input type="hidden" name="active" value="0">
                <input id="active" name="active" value="1" type="checkbox" class="form-check-input" @checked(old('active', $person->active ?? true))>
                <label for="active" class="form-check-label">Cadastro ativo no sistema</label>
            </div>
        </div>
    @endif
</div>
