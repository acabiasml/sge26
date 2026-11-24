@extends('layouts.app')
@section('title', $title)
@section('page-title', $title)
@section('content')
<div class="card sge-panel-card">
    <div class="card-body">
        <p><strong>{{ $context }}</strong></p>
        <p>{{ $notice }}</p>
        @if($preview ?? null)
            <a class="btn btn-outline-primary mb-3" href="{{ $preview }}" target="_blank" rel="noopener">{{ __('Conferir prévia em PDF') }}</a>
        @endif
        <form method="POST" action="{{ $action }}" enctype="multipart/form-data">
            @csrf
            @if(isset($evidenceType))
                @include('credential-registry.book-choice', ['requiresBook' => true])
                @include('credential-registry.evidence-fields')
            @else
                @include('credential-registry.official-options')
            @endif
            @if($confirmMissingCpf ?? false)
                <label class="d-block"><input type="checkbox" name="confirm_missing_student_cpf" value="1" required> {{ __('Confirmo a emissão sem CPF do estudante, após conferir o cadastro.') }}</label>
            @endif
            @if($requireHistory ?? false)
                <div class="form-group">
                    <label for="completion-history">{{ __('Código do histórico oficial assinado do Ensino Médio') }}</label>
                    <input class="form-control" id="completion-history" name="history_code" value="{{ old('history_code') }}" maxlength="60" @required(!$school->usesPhysicalRegistry())>
                    <p class="small text-muted mt-1">{{ __('Use o histórico do estudante emitido e registrado por esta escola.') }} <x-school-document-regulation :articles="__('art. 20')" :page="21" /></p>
                </div>
                @if($school->usesPhysicalRegistry())
                    <details class="mb-3">
                        <summary>{{ __('O histórico assinado está no acervo físico') }}</summary>
                        <div class="form-group mt-2">
                            <label for="paper-history-reference">{{ __('Referência do histórico no acervo em papel') }}</label>
                            <input class="form-control" id="paper-history-reference" name="paper_history_reference" value="{{ old('paper_history_reference') }}" maxlength="250">
                        </div>
                        <div class="form-group">
                            <label for="paper-history-pdf">{{ __('Cópia do histórico assinado (PDF)') }}</label>
                            <input class="form-control-file" id="paper-history-pdf" type="file" name="paper_history_pdf" accept="application/pdf">
                        </div>
                        <label><input type="checkbox" name="paper_history_reviewed" value="1"> {{ __('Conferi o original assinado e a conclusão desta matrícula.') }}</label>
                    </details>
                @endif
            @endif
            @isset($technicalModules)
                @include('technical-credentials.history-fields')
            @endisset
            <button class="btn btn-primary" @disabled(!$school->usesPhysicalRegistry() && $books->isEmpty())>{{ __('Registrar versão oficial') }}</button>
        </form>
    </div>
</div>
@endsection
