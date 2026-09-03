@php
    $lockInstitutionalEmail = $lockInstitutionalEmail ?? false;
    $lockOwnIdentity = $lockOwnIdentity ?? false;
    $requiresCompleteActiveData = $requiresCompleteActiveData ?? true;
    $lockFullName = $lockOwnIdentity && filled($person->full_name ?? null);
    $lockCpf = $lockOwnIdentity && filled($person->cpf ?? null);
    $lockBirthDate = $lockOwnIdentity && filled($person->birth_date ?? null);
    $lockMotherName = $lockOwnIdentity && filled($person->mother_name ?? null);
    $nationalityValue = old('nationality', $person->nationality ?? ($person->legacy_metadata['nacionalidade'] ?? 'Brasileira'));
    $normalizedNationalityValue = \Illuminate\Support\Str::of((string) $nationalityValue)->ascii()->lower()->trim()->toString();
    if (in_array($normalizedNationalityValue, ['brasil', 'brasileiro', 'brasileira'], true)) {
        $nationalityValue = 'Brasileira';
    }
    $nationalityOptions = \App\Support\Nationalities::options();
    $requiresCompleteFields = $requiresCompleteActiveData;
    $requiresBrazilianBirthPlace = $requiresCompleteFields && $nationalityValue === 'Brasileira';
    $willGenerateInstitutionalEmail = ! ($person->exists ?? false);
@endphp

<div class="form-row">
    <div class="form-group col-md-6">
        <label for="full_name">{{ __('screens.full_name') }}</label>
        <input id="full_name" name="full_name" class="form-control @error('full_name') is-invalid @enderror" value="{{ old('full_name', $person->full_name ?? '') }}" @readonly($lockFullName) required>
        @if ($lockFullName)
            <small class="form-text text-muted">{{ __('screens.own_name_locked') }}</small>
        @endif
        @error('full_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-6">
        <label for="social_name">{{ __('screens.social_name') }}</label>
        <input id="social_name" name="social_name" class="form-control @error('social_name') is-invalid @enderror" value="{{ old('social_name', $person->social_name ?? '') }}">
        @error('social_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="form-row">
    <div class="form-group col-md-4">
        <label for="cpf">CPF</label>
        <input id="cpf" name="cpf" data-mask="cpf" inputmode="numeric" autocomplete="off" class="form-control @error('cpf') is-invalid @enderror" value="{{ old('cpf', $person->cpf ?? '') }}" @readonly($lockCpf) @required($requiresCompleteFields)>
        @if ($lockCpf)
            <small class="form-text text-muted">{{ __('screens.own_cpf_locked') }}</small>
        @endif
        @error('cpf') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-4">
        <label for="birth_date">{{ __('screens.birth_date') }}</label>
        <input id="birth_date" name="birth_date" type="date" class="form-control @error('birth_date') is-invalid @enderror" value="{{ old('birth_date', isset($person) && $person->birth_date ? $person->birth_date->format('Y-m-d') : '') }}" @readonly($lockBirthDate) @required($requiresCompleteFields)>
        @if ($lockBirthDate)
            <small class="form-text text-muted">{{ __('screens.own_birth_locked') }}</small>
        @endif
        @error('birth_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-4 d-flex align-items-end">
        <p class="form-text text-muted mb-2">
            {{ __('screens.automatic_status_help') }}
        </p>
    </div>
</div>

<div class="form-row">
    <div class="form-group col-md-5">
        <label for="birth_city">{{ __('screens.birth_place') }}</label>
        <input id="birth_city" name="birth_city" class="form-control @error('birth_city') is-invalid @enderror" value="{{ old('birth_city', $person->birth_city ?? ($person->legacy_metadata['naturalidade'] ?? '')) }}" data-brazilian-birth-field @required($requiresBrazilianBirthPlace)>
        @error('birth_city') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-2">
        <label for="birth_state">{{ __('screens.birth_state') }}</label>
        @php($selectedBirthState = old('birth_state', $person->birth_state ?? ($person->legacy_metadata['naturalidade_uf'] ?? '')))
        <select id="birth_state" name="birth_state" class="form-control @error('birth_state') is-invalid @enderror" data-brazilian-birth-field @required($requiresBrazilianBirthPlace)>
            <option value="">{{ __('screens.select') }}</option>
            @foreach (\App\Support\BrazilianStates::codes() as $state)
                <option value="{{ $state }}" @selected($selectedBirthState === $state)>{{ $state }}</option>
            @endforeach
        </select>
        @error('birth_state') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-5">
        <label for="nationality">{{ __('screens.nationality') }}</label>
        <select id="nationality" name="nationality" class="form-control @error('nationality') is-invalid @enderror" data-nationality-input data-requires-complete-active-data="{{ $requiresCompleteActiveData ? '1' : '0' }}" @required($requiresCompleteFields)>
            <option value="">{{ __('screens.select') }}</option>
            @foreach ($nationalityOptions as $value => $label)
                <option value="{{ $value }}" @selected($nationalityValue === $value)>{{ $label }}</option>
            @endforeach
            @if (filled($nationalityValue) && ! array_key_exists($nationalityValue, $nationalityOptions))
                <option value="{{ $nationalityValue }}" selected>{{ $nationalityValue }}</option>
            @endif
        </select>
        @error('nationality') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="form-row">
    <div class="form-group col-md-4">
        <label for="student_inep">{{ __('screens.student_inep') }}</label>
        <input id="student_inep" name="student_inep" data-mask="digits" data-mask-max="12" inputmode="numeric" autocomplete="off" class="form-control @error('student_inep') is-invalid @enderror" value="{{ old('student_inep', $person->student_inep ?? '') }}">
        <small class="form-text text-muted">{{ __('screens.student_inep_help') }}</small>
        @error('student_inep') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="form-group col-md-4">
        <label for="nis">{{ __('screens.student_nis') }}</label>
        <input id="nis" name="nis" data-mask="digits" data-mask-max="11" inputmode="numeric" autocomplete="off" class="form-control @error('nis') is-invalid @enderror" value="{{ old('nis', $person->nis ?? '') }}">
        <small class="form-text text-muted">{{ __('screens.student_nis_help') }}</small>
        @error('nis') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="form-group col-md-4 d-flex align-items-center">
        <div class="form-check mt-3">
            <input type="hidden" name="receives_federal_aid" value="0">
            <input id="receives_federal_aid" name="receives_federal_aid" value="1" type="checkbox" class="form-check-input" @checked(old('receives_federal_aid', $person->receives_federal_aid ?? false))>
            <label for="receives_federal_aid" class="form-check-label">{{ __('screens.federal_aid') }}</label>
        </div>
    </div>
</div>

<div class="form-row">
    <div class="form-group col-md-6">
        <label for="mother_name">{{ __('screens.mother_name') }}</label>
        <input id="mother_name" name="mother_name" class="form-control @error('mother_name') is-invalid @enderror" value="{{ old('mother_name', $person->mother_name ?? '') }}" @readonly($lockMotherName) @required($requiresCompleteFields)>
        @if ($lockMotherName)
            <small class="form-text text-muted">{{ __('screens.own_mother_locked') }}</small>
        @endif
        @error('mother_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-6">
        <label for="father_name">{{ __('screens.father_name') }}</label>
        <input id="father_name" name="father_name" class="form-control @error('father_name') is-invalid @enderror" value="{{ old('father_name', $person->father_name ?? '') }}">
        @error('father_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="form-row">
    <div class="form-group col-md-4">
        <label for="institutional_email">{{ __('screens.institutional_email') }}</label>
        <input id="institutional_email" @unless($willGenerateInstitutionalEmail) name="institutional_email" @endunless type="email" inputmode="email" autocomplete="email" class="form-control @error('institutional_email') is-invalid @enderror" value="{{ $willGenerateInstitutionalEmail ? '' : old('institutional_email', $person->institutional_email ?? '') }}" placeholder="{{ $willGenerateInstitutionalEmail ? __('screens.generated_email') : '' }}" @disabled($willGenerateInstitutionalEmail) @readonly($lockInstitutionalEmail)>
        @if ($willGenerateInstitutionalEmail)
            <small class="form-text text-muted">{{ __('screens.generated_email_help') }}</small>
        @elseif ($lockInstitutionalEmail)
            <small class="form-text text-muted">{{ __('screens.own_email_locked') }}</small>
        @endif
        @error('institutional_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-4">
        <label for="personal_email">{{ __('screens.personal_email') }}</label>
        <input id="personal_email" name="personal_email" type="email" inputmode="email" autocomplete="email" class="form-control @error('personal_email') is-invalid @enderror" value="{{ old('personal_email', $person->personal_email ?? '') }}">
        @error('personal_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-4">
        <label for="phone">{{ __('screens.phone') }}</label>
        <input id="phone" name="phone" data-mask="phone" inputmode="tel" autocomplete="tel" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $person->phone ?? '') }}">
        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<h2 class="h6 text-gray-800 mt-4">{{ __('screens.address') }}</h2>

<div class="form-row">
    <div class="form-group col-md-6">
        <label for="address">{{ __('screens.address') }}</label>
        <input id="address" name="address" autocomplete="street-address" class="form-control @error('address') is-invalid @enderror" value="{{ old('address', $person->address ?? '') }}" @required($requiresCompleteFields)>
        @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-2">
        <label for="number">{{ __('screens.number') }}</label>
        <input id="number" name="number" class="form-control @error('number') is-invalid @enderror" value="{{ old('number', $person->number ?? '') }}">
        @error('number') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-4">
        <label for="district">{{ __('screens.district') }}</label>
        <input id="district" name="district" class="form-control @error('district') is-invalid @enderror" value="{{ old('district', $person->district ?? '') }}">
        @error('district') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="form-row">
    <div class="form-group col-md-5">
        <label for="city">{{ __('screens.city') }}</label>
        <input id="city" name="city" class="form-control @error('city') is-invalid @enderror" value="{{ old('city', $person->city ?? '') }}" @required($requiresCompleteFields)>
        @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-2">
        <label for="state">{{ __('screens.state') }}</label>
        @php($selectedState = old('state', $person->state ?? ''))
        <select id="state" name="state" class="form-control @error('state') is-invalid @enderror" @required($requiresCompleteFields)>
            <option value="">{{ __('screens.select') }}</option>
            @foreach (\App\Support\BrazilianStates::codes() as $state)
                <option value="{{ $state }}" @selected($selectedState === $state)>{{ $state }}</option>
            @endforeach
        </select>
        @error('state') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-group col-md-5">
        <label for="postal_code">{{ __('screens.postal_code') }}</label>
        <input id="postal_code" name="postal_code" data-mask="cep" inputmode="numeric" autocomplete="postal-code" class="form-control @error('postal_code') is-invalid @enderror" value="{{ old('postal_code', $person->postal_code ?? '') }}" @required($requiresCompleteFields)>
        @error('postal_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="form-group">
    <label for="address_complement">{{ __('screens.address_complement') }}</label>
    <input id="address_complement" name="address_complement" class="form-control @error('address_complement') is-invalid @enderror" value="{{ old('address_complement', $person->address_complement ?? '') }}">
    @error('address_complement') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

@once
    @push('scripts')
        <script>
            document.querySelectorAll('[data-nationality-input]').forEach((nationalityInput) => {
                const form = nationalityInput.closest('form');
                const birthFields = form?.querySelectorAll('[data-brazilian-birth-field]');

                if (!form || !birthFields?.length) {
                    return;
                }

                const requiresCompleteActiveData = nationalityInput.dataset.requiresCompleteActiveData === '1';
                const isBrazilian = () => nationalityInput.value
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .trim()
                    .toLowerCase() === 'brasileira';

                const syncBirthPlaceRequirement = () => {
                    const requiresBirthPlace = requiresCompleteActiveData
                        && isBrazilian();

                    birthFields.forEach((field) => {
                        field.required = requiresBirthPlace;
                    });
                };

                nationalityInput.addEventListener('input', syncBirthPlaceRequirement);
                nationalityInput.addEventListener('change', syncBirthPlaceRequirement);
                syncBirthPlaceRequirement();
            });
        </script>
    @endpush
@endonce
