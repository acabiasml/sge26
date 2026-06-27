<div class="form-row">
    <div class="form-group col-md-6">
        <label for="name">Nome da escola</label>
        <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $school->name ?? '') }}" required>
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-6">
        <label for="legal_name">Razão social</label>
        <input id="legal_name" name="legal_name" class="form-control @error('legal_name') is-invalid @enderror" value="{{ old('legal_name', $school->legal_name ?? '') }}">
        @error('legal_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="form-row">
    <div class="form-group col-md-4">
        <label for="cnpj">CNPJ</label>
        <input id="cnpj" name="cnpj" data-mask="cnpj" inputmode="numeric" autocomplete="off" class="form-control @error('cnpj') is-invalid @enderror" value="{{ old('cnpj', $school->cnpj ?? '') }}">
        @error('cnpj') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-4">
        <label for="inep">INEP</label>
        <input id="inep" name="inep" class="form-control @error('inep') is-invalid @enderror" value="{{ old('inep', $school->inep ?? '') }}">
        @error('inep') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-4">
        <label for="phone">Telefone</label>
        <input id="phone" name="phone" data-mask="phone" inputmode="tel" autocomplete="tel" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $school->phone ?? '') }}">
        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="form-group">
    <label for="email">E-mail</label>
    <input id="email" name="email" type="email" inputmode="email" autocomplete="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $school->email ?? '') }}">
    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<h2 class="h6 text-gray-800 mt-4">Dados institucionais para documentos</h2>

<div class="form-row">
    <div class="form-group col-md-4">
        <label for="founded_at">Data de fundação</label>
        <input id="founded_at" name="founded_at" type="date" class="form-control @error('founded_at') is-invalid @enderror" value="{{ old('founded_at', isset($school) && $school->founded_at ? $school->founded_at->format('Y-m-d') : '') }}">
        @error('founded_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-8">
        <label for="website">Site</label>
        <input id="website" name="website" type="url" inputmode="url" class="form-control @error('website') is-invalid @enderror" value="{{ old('website', $school->website ?? '') }}" placeholder="https://">
        @error('website') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="form-group">
    <label for="letterhead_text">Texto institucional para papel timbrado</label>
    <textarea id="letterhead_text" name="letterhead_text" rows="4" class="form-control @error('letterhead_text') is-invalid @enderror">{{ old('letterhead_text', $school->letterhead_text ?? '') }}</textarea>
    @error('letterhead_text') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="form-row align-items-end">
    <div class="form-group col-md-8">
        <label for="logo">Logo da escola</label>
        <input id="logo" name="logo" type="file" accept="image/*" class="form-control-file @error('logo') is-invalid @enderror">
        <small class="form-text text-muted">PNG ou JPG, até 2 MB.</small>
        @error('logo') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    @if (! empty($school->logo_path))
        <div class="form-group col-md-4 text-md-right">
            <img class="sge-school-logo-preview" src="{{ $school->logoUrl() }}" alt="Logo atual da escola">
        </div>
    @endif
</div>

<h2 class="h6 text-gray-800 mt-4">Endereço</h2>

<div class="form-row">
    <div class="form-group col-md-6">
        <label for="address">Endereço</label>
        <input id="address" name="address" class="form-control @error('address') is-invalid @enderror" value="{{ old('address', $school->address ?? '') }}">
        @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-2">
        <label for="number">Número</label>
        <input id="number" name="number" class="form-control @error('number') is-invalid @enderror" value="{{ old('number', $school->number ?? '') }}">
        @error('number') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-4">
        <label for="district">Bairro</label>
        <input id="district" name="district" class="form-control @error('district') is-invalid @enderror" value="{{ old('district', $school->district ?? '') }}">
        @error('district') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="form-row">
    <div class="form-group col-md-5">
        <label for="city">Cidade</label>
        <input id="city" name="city" class="form-control @error('city') is-invalid @enderror" value="{{ old('city', $school->city ?? '') }}">
        @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-2">
        <label for="state">UF</label>
        @php($selectedState = old('state', $school->state ?? ''))
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
        <input id="postal_code" name="postal_code" data-mask="cep" inputmode="numeric" autocomplete="postal-code" class="form-control @error('postal_code') is-invalid @enderror" value="{{ old('postal_code', $school->postal_code ?? '') }}">
        @error('postal_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="form-group form-check">
    <input type="hidden" name="active" value="0">
    <input id="active" name="active" value="1" type="checkbox" class="form-check-input" @checked(old('active', $school->active ?? true))>
    <label for="active" class="form-check-label">Escola ativa</label>
</div>
