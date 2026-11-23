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
            @include('credential-registry.official-options')
            @if(\App\Support\OfficialDocumentCompliance::studentHasNoCpf($person))
                <label class="d-block"><input type="checkbox" name="confirm_missing_student_cpf" value="1"> {{ __('Confirmo a emissão sem CPF do estudante, conforme a conferência cadastral.') }}</label>
            @endif
            <button class="btn btn-primary" @disabled(!$completeness['complete'] || $books->isEmpty())>{{ __('Registrar histórico oficial') }}<span class="sr-only"> {{ __('(abre em nova aba)') }}</span></button>
        </form>
    </div>
</div>
@if($recent->isNotEmpty())
    <section class="card mt-4">
        <div class="card-header"><h2 class="h6 mb-0">{{ __('Históricos emitidos') }}</h2></div>
        <ul class="list-group list-group-flush">
            @foreach($recent as $document)
                <li class="list-group-item"><a href="{{ $document->registration ? route('credential-registry.entry', $document->registration) : route('document-signatures.show', $document) }}">{{ $document->verification_code }}</a> · {{ $document->issued_at->timezone('America/Cuiaba')->format('d/m/Y H:i') }}</li>
            @endforeach
        </ul>
        <div class="card-body">{{ $recent->links() }}</div>
    </section>
@endif
@endsection
