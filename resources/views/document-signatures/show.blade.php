@extends('layouts.app')
@section('title', __('Preparar assinatura digital'))
@section('page-title', __('Preparar assinatura digital'))
@section('content')
<p><strong>{{ $document->payload['title'] ?? \App\Support\DocumentVerificationPresenter::typeLabel($document->type) }}</strong> · {{ $document->verification_code }}</p>
<div class="card sge-panel-card mb-4">
    <div class="card-body">
        <ol class="pl-3">
            <li>{{ __('Baixe o original. Se houver mais de um signatário, encaminhe a versão já assinada ao próximo para preservar as assinaturas.') }}</li>
            <li>{{ __('Confira as assinaturas ICP-Brasil no VALIDAR do ITI e baixe o relatório.') }}</li>
            <li>{{ __('Anexe o PDF assinado e o relatório abaixo.') }}</li>
        </ol>
        @if($document->type === 'student-academic-history')
            <p class="small text-muted">{{ __('Entregue a versão com todas as assinaturas conferidas e guarde o protocolo de recebimento assinado no acervo escolar.') }} <x-school-document-regulation :articles="__('arts. 7º e 38')" /></p>
        @endif
        @if(!empty($document->payload['signers']))<p>{{ __('Signatários previstos') }} · {{ implode(' · ', $document->payload['signers']) }}</p>@endif
        <a class="btn btn-primary" href="{{ route('document-signatures.original', $document) }}">{{ __('Baixar PDF para assinar') }}</a>
        <a class="btn btn-outline-primary" href="https://validar.iti.gov.br/" target="_blank" rel="noopener">{{ __('VALIDAR do ITI') }}</a>
        <a class="btn btn-outline-secondary" href="{{ route('document-signatures.index') }}">{{ __('Voltar às assinaturas') }}</a>
    </div>
</div>
<div class="card sge-panel-card mb-4">
    <div class="card-body">
        <h2 class="h5">{{ __('Arquivar versão assinada') }}</h2>
        <form method="POST" action="{{ route('document-signatures.upload', $document) }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group"><label for="signature-pdf">{{ __('PDF assinado') }}</label><input class="form-control-file" id="signature-pdf" name="signed_pdf" type="file" accept="application/pdf" required></div>
            <div class="form-group"><label for="signature-report">{{ __('Relatório da validação externa (PDF)') }}</label><input class="form-control-file" id="signature-report" name="validation_report" type="file" accept="application/pdf" required></div>
            @if($requiredSigners)
                <fieldset class="form-group">
                    <legend class="h6">{{ __('Signatários conferidos nesta versão') }}</legend>
                    @foreach($requiredSigners as $name)
                        <label class="d-block"><input type="checkbox" name="signers[]" value="{{ $name }}" @checked(in_array($name, old('signers', [])))> {{ $name }}</label>
                    @endforeach
                </fieldset>
            @else
            <div class="form-group"><label for="signature-signers">{{ __('Quem assinou esta versão?') }}</label><input class="form-control" id="signature-signers" name="signers" maxlength="500" value="{{ old('signers') }}" required></div>
            <div class="form-group"><label for="signature-complete">{{ __('Situação da assinatura') }}</label><select class="form-control" id="signature-complete" name="complete"><option value="0">{{ __('Parcial · faltam assinaturas') }}</option><option value="1">{{ __('Completa · todos os responsáveis assinaram') }}</option></select></div>
            @endif
            <div class="form-group"><label><input type="checkbox" name="reviewed" value="1" required> {{ __('Conferi o documento, o código e os signatários com o relatório do validador externo.') }}</label></div>
            <button class="btn btn-primary">{{ __('Arquivar versão') }}</button>
        </form>
    </div>
</div>
@if($versions->isNotEmpty())
    <section class="card sge-panel-card">
        <div class="card-header"><h2 class="h6 mb-0">{{ __('Versões arquivadas') }}</h2></div>
        <ul class="list-group list-group-flush">@foreach($versions as $version)
            <li class="list-group-item"><strong>{{ $version->complete ? __('Completa') : __('Parcial') }}</strong> · {{ $version->signers }} · {{ $version->created_at->timezone('America/Cuiaba')->format('d/m/Y H:i') }}
                <div><a href="{{ route('document-signatures.download', [$version, 'assinado']) }}">{{ __('PDF assinado') }}</a> · <a href="{{ route('document-signatures.download', [$version, 'validacao']) }}">{{ __('Relatório de validação') }}</a></div>
            </li>
        @endforeach</ul>
        <div class="card-body">{{ $versions->links() }}</div>
    </section>
@endif
@endsection
