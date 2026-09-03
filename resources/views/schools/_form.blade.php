<div class="form-row">
    <div class="form-group col-md-6">
        <label for="name">{{ __('screens.school_name') }}</label>
        <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $school->name ?? '') }}" required>
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-6">
        <label for="legal_name">{{ __('screens.legal_name') }}</label>
        <input id="legal_name" name="legal_name" class="form-control @error('legal_name') is-invalid @enderror" value="{{ old('legal_name', $school->legal_name ?? '') }}" required>
        @error('legal_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="form-row">
    <div class="form-group col-md-4">
        <label for="cnpj">CNPJ</label>
        <input id="cnpj" name="cnpj" data-mask="cnpj" inputmode="numeric" autocomplete="off" class="form-control @error('cnpj') is-invalid @enderror" value="{{ old('cnpj', $school->cnpj ?? '') }}" required>
        @error('cnpj') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-4">
        <label for="inep">INEP</label>
        <input id="inep" name="inep" data-mask="digits" data-mask-max="8" inputmode="numeric" autocomplete="off" class="form-control @error('inep') is-invalid @enderror" value="{{ old('inep', $school->inep ?? '') }}" required>
        @error('inep') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-4">
        <label for="phone">{{ __('screens.phone') }}</label>
        <input id="phone" name="phone" data-mask="phone" inputmode="tel" autocomplete="tel" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $school->phone ?? '') }}" required>
        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="form-group">
    <label for="email">E-mail</label>
    <input id="email" name="email" type="email" inputmode="email" autocomplete="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $school->email ?? '') }}" required>
    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<h2 class="h6 text-gray-800 mt-4">{{ __('screens.institutional_document_data') }}</h2>

<div class="form-row">
    <div class="form-group col-md-4">
        <label for="founded_at">{{ __('screens.foundation_date') }}</label>
        <input id="founded_at" name="founded_at" type="date" class="form-control @error('founded_at') is-invalid @enderror" value="{{ old('founded_at', isset($school) && $school->founded_at ? $school->founded_at->format('Y-m-d') : '') }}" required>
        @error('founded_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-8">
        <label for="website">{{ __('screens.website') }}</label>
        <input id="website" name="website" type="url" inputmode="url" class="form-control @error('website') is-invalid @enderror" value="{{ old('website', $school->website ?? '') }}" placeholder="https://">
        @error('website') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="form-group">
    <label for="letterhead_text">{{ __('screens.letterhead_text') }}</label>
    <textarea id="letterhead_text" name="letterhead_text" rows="4" class="form-control @error('letterhead_text') is-invalid @enderror" required>{{ old('letterhead_text', $school->letterhead_text ?? '') }}</textarea>
    @error('letterhead_text') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="form-row align-items-end">
    <div class="form-group col-md-8">
        <label for="logo">{{ __('screens.school_logo') }}</label>
        <input id="logo" name="logo" type="file" accept="image/*" class="form-control-file @error('logo') is-invalid @enderror">
        <small class="form-text text-muted">{{ __('screens.logo_help') }}</small>
        @error('logo') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    @if (! empty($school->logo_path))
        <div class="form-group col-md-4 text-md-right">
            <img class="sge-school-logo-preview" src="{{ $school->logoUrl() }}" alt="{{ __('screens.current_school_logo') }}">
        </div>
    @endif
</div>

<h2 class="h6 text-gray-800 mt-4">{{ __('screens.address') }}</h2>

<div class="form-row">
    <div class="form-group col-md-6">
        <label for="address">{{ __('screens.address') }}</label>
        <input id="address" name="address" class="form-control @error('address') is-invalid @enderror" value="{{ old('address', $school->address ?? '') }}" required>
        @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-2">
        <label for="number">{{ __('screens.number') }}</label>
        <input id="number" name="number" class="form-control @error('number') is-invalid @enderror" value="{{ old('number', $school->number ?? '') }}">
        @error('number') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-4">
        <label for="district">{{ __('screens.district') }}</label>
        <input id="district" name="district" class="form-control @error('district') is-invalid @enderror" value="{{ old('district', $school->district ?? '') }}">
        @error('district') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="form-row">
    <div class="form-group col-md-5">
        <label for="city">{{ __('screens.city') }}</label>
        <input id="city" name="city" class="form-control @error('city') is-invalid @enderror" value="{{ old('city', $school->city ?? '') }}" required>
        @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-2">
        <label for="state">{{ __('screens.state') }}</label>
        @php($selectedState = old('state', $school->state ?? ''))
        <select id="state" name="state" class="form-control @error('state') is-invalid @enderror" required>
            <option value="">{{ __('screens.select') }}</option>
            @foreach (\App\Support\BrazilianStates::codes() as $state)
                <option value="{{ $state }}" @selected($selectedState === $state)>{{ $state }}</option>
            @endforeach
        </select>
        @error('state') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-5">
        <label for="postal_code">{{ __('screens.postal_code') }}</label>
        <input id="postal_code" name="postal_code" data-mask="cep" inputmode="numeric" autocomplete="postal-code" class="form-control @error('postal_code') is-invalid @enderror" value="{{ old('postal_code', $school->postal_code ?? '') }}" required>
        @error('postal_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="form-group form-check">
    <input type="hidden" name="active" value="0">
    <input id="active" name="active" value="1" type="checkbox" class="form-check-input" @checked(old('active', $school->active ?? true))>
    <label for="active" class="form-check-label">{{ __('screens.active_school') }}</label>
</div>
