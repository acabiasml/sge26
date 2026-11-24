@extends('layouts.app')
@section('title', __('Assinaturas digitais'))
@section('page-title', __('Assinaturas digitais'))
@section('content')
<p>{{ __('Abra um documento para baixar o original, arquivar o PDF assinado e guardar o relatório de validação. Certificados e diplomas com registro digital seguem pelo Livro Digital.') }}</p>
<livewire:screen-listing screen="document-signatures" />
@endsection
