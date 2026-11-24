@php($transfer = $evidenceType === 'received-transfer')
<div class="form-group">
    <label for="act-origin">{{ $transfer ? __('Escola de origem') : __('Identificação ou referência do processo') }}</label>
    <input class="form-control" id="act-origin" name="origin" maxlength="250" value="{{ old('origin') }}" required>
</div>
<div class="form-group">
    <label for="act-date">{{ $transfer ? __('Data do recebimento') : __('Data do ato') }}</label>
    <input class="form-control" type="date" id="act-date" name="recorded_on" max="{{ now('America/Cuiaba')->toDateString() }}" value="{{ old('recorded_on') }}" required>
</div>
@if($transfer)
    <div class="form-group">
        <label for="transferred-during-period">{{ __('A transferência ocorreu no meio do período letivo?') }}</label>
        <select class="form-control" name="transfer_during_period" id="transferred-during-period" required>
            <option value="">{{ __('Selecione') }}</option>
            <option value="1" @selected(old('transfer_during_period') === '1')>{{ __('Sim — anexar também a ficha individual') }}</option>
            <option value="0" @selected(old('transfer_during_period') === '0')>{{ __('Não') }}</option>
        </select>
    </div>
@else
    <div class="form-group">
        <label for="process-kind">{{ __('Tipo de processo') }}</label>
        <select class="form-control" id="process-kind" name="process_kind" required>
            <option value="classification">{{ __('Classificação') }}</option>
            <option value="reclassification">{{ __('Reclassificação') }}</option>
            <option value="placement">{{ __('Enturmação') }}</option>
        </select>
    </div>
@endif
<div class="form-group">
    <label for="act-method">{{ __('Forma do original') }}</label>
    <select class="form-control" id="act-method" name="signature_mode" required>
        <option value="physical">{{ __('Via em papel digitalizada') }}</option>
        <option value="digital">{{ __('Via digital') }}</option>
    </select>
</div>
<div class="form-group">
    <label for="act-original">{{ $transfer ? __('Histórico ou atestado recebido (PDF)') : __('Ata individual e súmula assinada (PDF)') }}</label>
    <input class="form-control-file" id="act-original" name="original_pdf" type="file" accept="application/pdf" required>
</div>
<div class="form-group">
    <label for="act-proof">{{ $transfer ? __('Ficha individual dos períodos parciais, quando aplicável (PDF)') : __('Documentação comprobatória (PDF)') }}</label>
    <input class="form-control-file" id="act-proof" name="supporting_pdf" type="file" accept="application/pdf" @required(!$transfer)>
</div>
<label class="d-block"><input type="checkbox" name="reviewed" value="1" required> {{ __('Conferi a identificação, a procedência, as assinaturas e os comprovantes do ato.') }}</label>
