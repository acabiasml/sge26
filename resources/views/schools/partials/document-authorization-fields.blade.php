<p class="small text-muted">{{ __('Preencha os atos das etapas e modalidades ofertadas, com a referência da publicação e as datas de vigência. Se um ato autorizar mais de uma oferta, registre a mesma referência nas ofertas correspondentes. Deixe o término em branco somente quando o ato tiver prazo indeterminado.') }}</p>
@foreach(\App\Models\School::documentAuthorizationLabels() as $kind => $label)
    @php
        $hasAct = filled(old("document_authorizations.$kind.act", $school->document_authorizations[$kind]['act'] ?? null));
        $fields = ['act' => __('Ato e referência do Diário Oficial'), 'published_on' => __('Publicação'), 'starts_on' => __('Início da vigência'), 'ends_on' => __('Fim da vigência'), 'source_url' => __('Link da publicação no Diário Oficial')];
        if (\App\Models\School::supportsDocumentAuthorizationRenewal($kind)) {
            $fields += ['renewal_protocol' => __('Protocolo de renovação no SIPE'), 'renewal_filed_on' => __('Data do protocolo')];
        }
    @endphp
    <details class="mb-3" @if($hasAct || isset(\App\Models\School::DOCUMENT_AUTHORIZATIONS[$kind]) || $errors->has("document_authorizations.$kind.*")) open @endif>
        <summary class="font-weight-bold mb-2">
            {{ __($label) }}
            @if(($endsOn = $school->document_authorizations[$kind]['ends_on'] ?? null) && $endsOn < now('America/Cuiaba')->toDateString())
                <small class="text-muted">{{ __('Vigência encerrada') }}</small>
                @if($protocol = $school->document_authorizations[$kind]['renewal_protocol'] ?? null)
                    <small class="text-muted">· {{ __('Renovação em andamento (SIPE nº :protocol)', ['protocol' => $protocol]) }}</small>
                @endif
            @endif
        </summary>
        <fieldset>
            <legend class="sr-only">{{ __($label) }}</legend>
            <div class="form-row">
                @foreach($fields as $field => $caption)
                    @php
                        $fieldKey = "document_authorizations.$kind.$field";
                    @endphp
                    <div class="form-group {{ in_array($field, ['act', 'source_url'], true) ? 'col-12 col-lg-6' : 'col-12 col-md-4 col-lg-2' }}">
                        <label for="authorization-{{ $kind }}-{{ $field }}">{{ $caption }}</label>
                        <input id="authorization-{{ $kind }}-{{ $field }}" name="document_authorizations[{{ $kind }}][{{ $field }}]" type="{{ match($field) { 'act', 'renewal_protocol' => 'text', 'source_url' => 'url', default => 'date' } }}" maxlength="350" class="form-control @error($fieldKey) is-invalid @enderror" value="{{ old($fieldKey, $school->document_authorizations[$kind][$field] ?? '') }}">
                        @error($fieldKey)<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @if($field === 'source_url' && str_starts_with($school->document_authorizations[$kind]['source_url'] ?? '', 'https://'))
                            <a class="small" href="{{ $school->document_authorizations[$kind]['source_url'] }}" target="_blank" rel="noopener">{{ __('Abrir publicação') }}</a>
                        @endif
                    </div>
                @endforeach
            </div>
        </fieldset>
    </details>
@endforeach
