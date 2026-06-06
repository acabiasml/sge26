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
                <label for="active" class="form-check-label">Cadastro ativo</label>
                <small class="form-text text-muted">Cadastros inativos não acessam o sistema, não recebem novos vínculos e não emitem documentos sem CPF e e-mail institucional.</small>
            </div>
        </div>
    @endif
</div>

<h2 class="h6 text-gray-800 mt-4">Endereço</h2>

<div class="form-row">
    <div class="form-group col-md-6">
        <label for="address">Endereço</label>
        <input id="address" name="address" autocomplete="street-address" class="form-control @error('address') is-invalid @enderror" value="{{ old('address', $person->address ?? '') }}">
        @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-2">
        <label for="number">Número</label>
        <input id="number" name="number" class="form-control @error('number') is-invalid @enderror" value="{{ old('number', $person->number ?? '') }}">
        @error('number') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-4">
        <label for="district">Bairro</label>
        <input id="district" name="district" class="form-control @error('district') is-invalid @enderror" value="{{ old('district', $person->district ?? '') }}">
        @error('district') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="form-row">
    <div class="form-group col-md-5">
        <label for="city">Cidade</label>
        <input id="city" name="city" class="form-control @error('city') is-invalid @enderror" value="{{ old('city', $person->city ?? '') }}">
        @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-2">
        <label for="state">UF</label>
        @php($selectedState = old('state', $person->state ?? ''))
        <select id="state" name="state" class="form-control @error('state') is-invalid @enderror">
            <option value="">Selecione</option>
            @foreach (\App\Support\BrazilianStates::codes() as $state)
                <option value="{{ $state }}" @selected($selectedState === $state)>{{ $state }}</option>
            @endforeach
        </select>
        @error('state') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-5">
        <label for="postal_code">CEP</label>
        <input id="postal_code" name="postal_code" data-mask="cep" inputmode="numeric" autocomplete="postal-code" class="form-control @error('postal_code') is-invalid @enderror" value="{{ old('postal_code', $person->postal_code ?? '') }}">
        @error('postal_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="form-group">
    <label for="address_complement">Complemento</label>
    <input id="address_complement" name="address_complement" class="form-control @error('address_complement') is-invalid @enderror" value="{{ old('address_complement', $person->address_complement ?? '') }}">
    @error('address_complement') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
