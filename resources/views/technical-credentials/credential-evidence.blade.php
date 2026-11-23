<div class="col-12" data-registry-evidence>
    <div class="form-group">
        <label for="credential-history">{{ __('Código do histórico técnico registrado e assinado') }}</label>
        <input class="form-control" id="credential-history" name="history_code" maxlength="60" value="{{ old('history_code') }}">
        <small>{{ __('Use o código da via para certificação, do mesmo estudante e da mesma oferta. Se a versão assinada estiver no acervo externo, anexe-a abaixo.') }}</small>
    </div>
    <div class="form-group">
        <label for="credential-history-pdf">{{ __('Histórico técnico assinado no acervo externo (PDF)') }}</label>
        <input class="form-control-file" id="credential-history-pdf" name="history_pdf" type="file" accept="application/pdf">
    </div>
    @if($kind === 'diploma')
        <p>
            {{ __('Confira no SISTEC o ato autorizativo, a matrícula, a conclusão e o código autenticador. Para matrículas cadastradas a partir de 1º de fevereiro de 2027, confira a trava de seis meses entre o cadastro e a conclusão, ou a autorização para a exceção.') }}
            <a href="https://www.in.gov.br/web/dou/-/portaria-n-58-de-10-de-outubro-de-2026-741177750" target="_blank" rel="noopener">{{ __('Portaria SETEC/MEC nº 58/2026') }}</a>
        </p>
        @foreach(['authorization_pdf' => __('Ato autorizativo publicado (PDF)'), 'sistec_proof' => __('Comprovante da conferência no SISTEC (PDF)'), 'high_school_certificate' => __('Certificado de conclusão do Ensino Médio (PDF)')] as $field => $label)
            <div class="form-group">
                <label for="credential-{{ $field }}">{{ $label }}</label>
                <input class="form-control-file" id="credential-{{ $field }}" name="{{ $field }}" type="file" accept="application/pdf" required>
            </div>
        @endforeach
        @if($program->authorization_ends_at?->isPast())
            <div class="form-group">
                <label for="credential-continuation">{{ __('Comprovante de continuidade dos estudos após a vigência original (PDF)') }}</label>
                <input class="form-control-file" id="credential-continuation" name="continuation_proof" type="file" accept="application/pdf">
            </div>
        @endif
        <div class="form-group">
            <label for="credential-title-gender">{{ __('Flexão da titulação no diploma') }}</label>
            <select class="form-control" id="credential-title-gender" name="title_gender" required>
                <option value="">{{ __('Selecione') }}</option>
                <option value="masculine" @selected(old('title_gender') === 'masculine')>{{ __('Técnico') }}</option>
                <option value="feminine" @selected(old('title_gender') === 'feminine')>{{ __('Técnica') }}</option>
            </select>
        </div>
    @endif
    <label class="d-block mb-3"><input type="checkbox" name="evidence_reviewed" value="1" required> {{ __('Conferi que os comprovantes correspondem ao estudante, à escola e à oferta deste registro.') }}</label>
</div>
