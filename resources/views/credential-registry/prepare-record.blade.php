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
                    <input class="form-control" id="completion-history" name="history_code" value="{{ old('history_code') }}" maxlength="60" required>
                    <p class="small text-muted mt-1">{{ __('Use o histórico do estudante emitido e registrado por esta escola.') }} <x-school-document-regulation :articles="__('art. 20')" :page="21" /></p>
                </div>
            @endif
            @isset($technicalModules)
                @include('technical-credentials.history-fields')
            @endisset
            <button class="btn btn-primary" @disabled($books->isEmpty())>{{ __('Registrar versão oficial') }}</button>
        </form>
    </div>
</div>
@endsection
