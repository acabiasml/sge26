@extends('layouts.app')
@section('title', __('Emissão de histórico'))
@section('page-title', __('Emissão de histórico'))
@section('content')
<p><strong>{{ $person->full_name }}</strong> · {{ $history->stage }} · {{ $history->school?->name }}</p>
<div class="card sge-panel-card mb-4">
    <div class="card-body">
        <h2 class="h5">{{ __('Conferir prévia') }}</h2>
        <p>{{ __('Gere quantas prévias precisar para conferir os dados do estudante, os lançamentos e os signatários. A prévia é identificada como temporária e reservada à conferência.') }} <x-school-document-regulation :articles="__('art. 20')" :page="21" /></p>
        <a class="btn btn-outline-primary" href="{{ $history->freshPdfUrl() }}" target="_blank" rel="noopener">{{ __('Gerar prévia temporária') }}</a>
        <a class="btn btn-outline-secondary" href="{{ route('people.histories.edit', [$person, $history]) }}">{{ __('Corrigir histórico') }}</a>
        <a class="btn btn-outline-secondary" href="{{ route('people.edit', $person) }}">{{ __('Conferir ficha da pessoa') }}</a>
    </div>
</div>
@if(!$completeness['complete'])
    <div class="alert alert-warning" role="status">{{ $completeness['message'] }}</div>
@endif
<div class="card sge-panel-card">
    <div class="card-body">
        <h2 class="h5">{{ __('Emitir histórico') }}</h2>
        <form method="POST" action="{{ route('people.histories.register-official', [$person, $history]) }}" target="_blank" rel="noopener">
            @csrf
            <input type="hidden" name="expected_version" value="{{ $version }}">
            <div class="form-group">
                <label for="history-signature-mode">{{ __('Forma de assinatura') }}</label>
                <select class="form-control" id="history-signature-mode" name="signature_mode" aria-describedby="history-signature-help">
                    <option value="physical">{{ __('Física · imprimir o PDF') }}</option>
                    <option value="digital">{{ __('Digital · preparar o PDF para assinatura externa') }}</option>
                </select>
                <p id="history-signature-help" class="small text-muted mt-1 mb-0">
                    {{ __('Na via física, imprima, carimbe e colha as assinaturas da direção e da secretaria em tinta azul ou preta. Na digital, colha as assinaturas ICP-Brasil e arquive o PDF validado em Assinaturas digitais.') }}
                    <x-school-document-regulation :articles="__('arts. 7º e 39')" />
                </p>
            </div>
            @if(\App\Support\OfficialDocumentCompliance::studentHasNoCpf($person))
                <label class="d-block"><input type="checkbox" name="confirm_missing_student_cpf" value="1"> {{ __('Confirmo a emissão sem CPF do estudante, conforme a conferência cadastral.') }}</label>
            @endif
            <p class="small text-muted">
                {{ __('O histórico oficial tem registro próprio. Na entrega, arquive no acervo da escola o protocolo de recebimento assinado, em papel ou digital.') }}
                <x-school-document-regulation :articles="__('art. 38')" :page="23" />
            </p>
            <button class="btn btn-primary" @disabled(!$completeness['complete'])>{{ __('Emitir histórico oficial') }}<span class="sr-only"> {{ __('(abre em nova aba)') }}</span></button>
        </form>
    </div>
</div>
@if($recent->isNotEmpty())
    <section class="card mt-4">
        <div class="card-header"><h2 class="h6 mb-0">{{ __('Históricos preparados para assinatura digital') }}</h2></div>
        <ul class="list-group list-group-flush">
            @foreach($recent as $document)
                <li class="list-group-item"><a href="{{ route('document-signatures.show', $document) }}">{{ $document->verification_code }}</a> · {{ $document->issued_at->timezone('America/Cuiaba')->format('d/m/Y H:i') }}</li>
            @endforeach
        </ul>
        <div class="card-body">{{ $recent->links() }}</div>
    </section>
@endif
@endsection
