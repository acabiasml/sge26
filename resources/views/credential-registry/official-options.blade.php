@include('credential-registry.book-choice', ['requiresBook' => true])
@if($digitalOnly ?? false)
    <input type="hidden" name="signature_mode" value="digital">
    <p>{{ __('Após o registro, encaminhe o original para assinatura digital ICP-Brasil. A expedição em papel é tratada no registro conforme as hipóteses do art. 31.') }}
        <x-school-document-regulation :articles="__('arts. 6º, 7º e 31')" :page="23" />
    </p>
@else
<div class="form-group">
    <label for="school-record-signature">{{ __('Forma de assinatura') }}</label>
    <select class="form-control" id="school-record-signature" name="signature_mode" required>
        <option value="physical" @selected(old('signature_mode') === 'physical')>{{ __('Física · imprimir, carimbar e assinar') }}</option>
        <option value="digital" @selected(old('signature_mode') === 'digital')>{{ __('Digital · assinar externamente com ICP-Brasil') }}</option>
    </select>
    <p class="small text-muted mt-1">{{ __('Após o registro, abra o PDF original e arquive a versão assinada. Para entregas ao interessado, anexe o protocolo de recebimento assinado.') }}
        <x-school-document-regulation :articles="__('arts. 7º e 38')" />
    </p>
</div>
@endif
