@php
    $socioData = old('socioeconomic_data', $person->socioeconomic_data ?? []);
    if (! is_array($socioData)) $socioData = [];
    $socioFields = \App\Support\SocioeconomicProfile::fields();
@endphp
<details class="card sge-panel-card my-4" @if($errors->has('socioeconomic_data') || $errors->has('socioeconomic_data.*')) open @endif>
    <summary class="sge-panel-header sge-history-section-summary"><h2>{{ __('Ficha socioeconômica') }}</h2><i class="fas fa-chevron-down" aria-hidden="true"></i></summary>
    <div class="card-body">
        <p class="text-muted">{{ __('Complete os dados informados pela família. Campos vazios permanecem não informados na ficha. Responsáveis e telefones são aproveitados dos contatos cadastrados.') }}</p>
        <input type="hidden" name="socioeconomic_profile_present" value="1">
        @error('socioeconomic_data')<p class="text-danger" role="alert">{{ $message }}</p>@enderror
        @foreach(\App\Support\SocioeconomicProfile::sections() as $section => $keys)
        <fieldset class="mb-4">
            <legend class="h6 font-weight-bold">{{ __($section) }}</legend>
            @if($section === 'Concessão de benefícios')<p class="small text-muted">{{ __('Marque somente os benefícios concedidos e informe seu ano de referência. Eles serão assinalados apenas nas fichas desse ano.') }}</p>@endif
            <div class="form-row">
            @foreach($keys as $key)
                @php
                    $field = $socioFields[$key];
                    $value = $socioData[$key] ?? null;
                    if ($field['type'] !== 'choices' && ! is_scalar($value)) $value = null;
                    $id = 'socio-'.$key;
                    $errorKey = 'socioeconomic_data.'.$key;
                    $hasError = $errors->has($errorKey) || $errors->has($errorKey.'.*');
                @endphp
                <div class="form-group {{ in_array($field['type'], ['choices', 'textarea']) ? 'col-12' : 'col-md-6' }}">
                @if($field['type'] === 'choices')
                    <fieldset aria-describedby="{{ $id }}-error">
                        <legend class="h6">{{ __($field['label']) }}</legend>
                        @foreach($field['options'] as $option => $label)
                        <div class="form-check form-check-inline mb-2">
                            <input class="form-check-input" id="{{ $id }}-{{ $option }}" name="socioeconomic_data[{{ $key }}][]" type="checkbox" value="{{ $option }}" @checked(in_array($option, is_array($value) ? $value : [], true))>
                            <label class="form-check-label" for="{{ $id }}-{{ $option }}">{{ __($label) }}</label>
                        </div>
                        @endforeach
                    </fieldset>
                @else
                    <label for="{{ $id }}">{{ __($field['label']) }}</label>
                    @if(in_array($field['type'], ['boolean', 'select']))
                        <select id="{{ $id }}" name="socioeconomic_data[{{ $key }}]" class="form-control @if($hasError) is-invalid @endif" aria-describedby="{{ $id }}-error" @if($hasError) aria-invalid="true" @endif>
                            <option value="">{{ __('Não informado') }}</option>
                            @foreach($field['type'] === 'boolean' ? [1 => 'Sim', 0 => 'Não'] : $field['options'] as $option => $label)
                                <option value="{{ $option }}" @selected($value !== null && (string) $value === (string) $option || ($field['type'] === 'boolean' && $value === false && $option === 0))>{{ __($label) }}</option>
                            @endforeach
                        </select>
                    @elseif($field['type'] === 'textarea')
                        <textarea id="{{ $id }}" name="socioeconomic_data[{{ $key }}]" class="form-control @if($hasError) is-invalid @endif" rows="2" maxlength="{{ $field['max'] }}" aria-describedby="{{ $id }}-error" @if($hasError) aria-invalid="true" @endif>{{ is_scalar($value) ? $value : '' }}</textarea>
                    @else
                        <input id="{{ $id }}" name="socioeconomic_data[{{ $key }}]" class="form-control @if($hasError) is-invalid @endif" type="{{ $field['type'] === 'date' ? 'date' : ($field['type'] === 'year' ? 'number' : 'text') }}" value="{{ is_scalar($value) ? $value : '' }}" @if(isset($field['max'])) maxlength="{{ $field['max'] }}" @endif @if($field['type'] === 'year') min="1900" max="2100" @endif @if($field['type'] === 'digits') inputmode="numeric" data-mask="digits" data-mask-max="{{ $field['max'] }}" @endif aria-describedby="{{ $id }}-error" @if($hasError) aria-invalid="true" @endif>
                    @endif
                @endif
                @if($field['type'] === 'digits')<small class="form-text text-muted">{{ __('Informe :count dígitos, preservando os zeros à esquerda.', ['count' => $field['max']]) }}</small>@endif
                <div id="{{ $id }}-error" class="{{ $hasError ? 'text-danger small mt-1' : 'd-none' }}" @if($hasError) role="alert" @endif>{{ $errors->first($errorKey) ?: $errors->first($errorKey.'.*') }}</div>
                </div>
            @endforeach
            </div>
        </fieldset>
        @endforeach
    </div>
</details>
