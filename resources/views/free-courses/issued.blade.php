@extends('layouts.app')
@section('title', 'Certificados arquivados')
@section('page-title', 'Certificados emitidos')
@section('content')
<p>{{ __('Os originais estão na ficha de cada pessoa. Para os certificados registrados no Livro Digital, use Abrir registro para arquivar assinaturas e o protocolo de entrega.') }}</p>
<div class="mb-3"><a class="btn btn-primary" href="{{ route('credential-archive.zip',$batch) }}">Baixar todos os PDFs em ZIP</a> <a class="btn btn-outline-secondary" href="{{ route('document-issuance.index') }}">Voltar à central</a></div>
<livewire:screen-listing screen="issued-certificates" :context="['batch' => $batch]" />
@endsection
