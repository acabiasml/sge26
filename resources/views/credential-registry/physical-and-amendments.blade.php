@php
    $credential = in_array($entry->book->kind, ['certificate', 'diploma', 'high-school'], true);
    $physicalReasons = [
        'lost' => __('Perda da via física anterior'),
        'damaged' => __('Original deteriorado'),
        'name' => __('Alteração de nome'),
        'gender' => __('Flexão de gênero'),
        'correction' => __('Correção de redação ou registro'),
    ];
@endphp
<details class="card mb-3">
    <summary class="card-header">{{ __('Arquivar via assinada em papel') }}</summary>
    <div class="card-body">
        <p>{{ __('Digitalize a via carimbada e assinada. Informe onde o original em papel ficará guardado.') }}
            <x-school-document-regulation :articles="__('art. 39')" :page="23" />
        </p>
        <form method="POST" enctype="multipart/form-data" action="{{ route('credential-registry.physical', $entry) }}">
            @csrf
            @if($credential)
                <label for="paper-reason">{{ __('Hipótese de expedição em papel') }}</label>
                <select class="form-control mb-2" id="paper-reason" name="physical_reason" required>
                    <option value="">{{ __('Selecione') }}</option>
                    @foreach($physicalReasons as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <p class="small text-muted">{{ __('Confira o enquadramento e conserve o comprovante do pedido.') }} <x-school-document-regulation :articles="__('art. 31')" :page="23" /></p>
            @endif
            <label for="paper-location">{{ __('Localização do original no acervo da escola') }}</label>
            <input class="form-control mb-2" id="paper-location" name="physical_location" maxlength="300" required>
            <label for="paper-copy">{{ __('Via assinada digitalizada (PDF)') }}</label>
            <input class="form-control-file mb-2" type="file" id="paper-copy" name="signed_pdf" accept="application/pdf" required>
            <label class="d-block"><input type="checkbox" name="reviewed" value="1" required> {{ __('Conferi o número do registro, o conteúdo e as assinaturas da via em papel.') }}</label>
            <button class="btn btn-primary">{{ __('Arquivar via assinada') }}</button>
        </form>
    </div>
</details>
@if($credential)
    <details class="card mb-3">
        <summary class="card-header">{{ __('Segunda via ou apostilamento') }}</summary>
        <div class="card-body">
            <p>{{ __('Anexe o requerimento e os comprovantes. Prepare a via assinada com a identificação do registro original e a indicação de segunda via ou apostilamento no verso, conforme o caso.') }}
                <x-school-document-regulation :articles="__('arts. 31 a 35')" :page="23" />
            </p>
            <form method="POST" enctype="multipart/form-data" action="{{ route('credential-registry.amendment', $entry) }}">
                @csrf
                <label for="amendment-type">{{ __('Ato') }}</label>
                <select class="form-control mb-2" id="amendment-type" name="action">
                    <option value="second_copy">{{ __('Segunda via') }}</option>
                    <option value="apostille">{{ __('Apostilamento') }}</option>
                </select>
                <label for="amendment-method">{{ __('Forma da via') }}</label>
                <select class="form-control mb-2" id="amendment-method" name="method">
                    <option value="digital">{{ __('Digital') }}</option>
                    <option value="physical">{{ __('Papel') }}</option>
                </select>
                <label for="amendment-paper-reason">{{ __('Hipótese legal para via em papel, quando aplicável') }}</label>
                <select class="form-control mb-2" id="amendment-paper-reason" name="physical_reason">
                    <option value="">{{ __('Selecione') }}</option>
                    @foreach($physicalReasons as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <label for="amendment-reason">{{ __('Motivo e informações alteradas') }}</label>
                <textarea class="form-control mb-2" id="amendment-reason" name="reason" maxlength="2000" rows="3" required></textarea>
                @foreach(['signed_pdf' => __('Nova via assinada (PDF)'), 'supporting_pdf' => __('Requerimento e comprovantes (PDF)'), 'validation_report' => __('Relatório de validação da via digital (PDF)')] as $field => $label)
                    <label for="amendment-{{ $field }}">{{ $label }}</label>
                    <input class="form-control-file mb-2" type="file" id="amendment-{{ $field }}" name="{{ $field }}" accept="application/pdf" @required($field !== 'validation_report')>
                @endforeach
                <label class="d-block"><input type="checkbox" name="timestamp_checked" value="1"> {{ __('Conferi os carimbos de tempo ICP-Brasil da via digital.') }}</label>
                <label class="d-block"><input type="checkbox" name="reviewed" value="1" required> {{ __('Conferi o pedido, o vínculo com o registro original, o teor e as assinaturas da nova via.') }}</label>
                <button class="btn btn-primary">{{ __('Arquivar no registro original') }}</button>
            </form>
        </div>
    </details>
@endif
