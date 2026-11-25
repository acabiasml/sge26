@php
    $fields = [
        'act' => __('Ato e referência do Diário Oficial'),
        'published_on' => __('Publicação'),
        'starts_on' => __('Início da vigência'),
        'ends_on' => __('Fim da vigência'),
        'source_url' => __('Link da publicação no Diário Oficial'),
        'renewal_protocol' => __('Protocolo de renovação no SIPE'),
        'renewal_filed_on' => __('Data do protocolo'),
    ];
@endphp
<fieldset>
    <legend class="sr-only">{{ $label }}</legend>
    <div class="form-row">
        @foreach ($fields as $field => $caption)
            @php
                $renewal = str_starts_with($field, 'renewal_');
                $accreditationEnd = $field === 'ends_on' && $kind === 'accreditation';
                $hideEnd = $accreditationEnd && $matoGrosso;
                $fieldKey = "document_authorizations.$kind.$field";
                $id = "authorization-$kind-$field";
            @endphp
            @continue($renewal && ! $supportsRenewal)
            @if($hideEnd)
                @error($fieldKey)<div class="col-12 text-danger small mb-2">{{ $message }}</div>@enderror
            @endif
            <div @if($renewal) data-authorization-renewal @endif @if($field === 'ends_on') data-authorization-end-date @endif
                @if($accreditationEnd) data-accreditation-end-date @endif @if($hideEnd) hidden @endif class="form-group {{ in_array($field, ['act', 'source_url'], true) ? 'col-12 col-lg-6' : 'col-12 col-md-4 col-lg-2' }}">
                <label for="{{ $id }}">{{ $caption }}</label>
                <input id="{{ $id }}" name="document_authorizations[{{ $kind }}][{{ $field }}]" type="{{ match($field) { 'act', 'renewal_protocol' => 'text', 'source_url' => 'url', default => 'date' } }}" maxlength="350" @disabled($hideEnd) class="form-control @error($fieldKey) is-invalid @enderror" value="{{ old($fieldKey, $act[$field] ?? '') }}">
                @error($fieldKey)<div class="invalid-feedback">{{ $message }}</div>@enderror
                @if($field === 'source_url' && str_starts_with($act['source_url'] ?? '', 'https://'))
                    <a class="small" href="{{ $act['source_url'] }}" target="_blank" rel="noopener">{{ __('Abrir publicação') }}</a>
                @endif
            </div>
        @endforeach
    </div>
</fieldset>
