@extends('layouts.app')

@section('title', 'Erro')
@section('page-title', 'Erro ao carregar a página')

@section('content')
    <div class="alert alert-danger">
        <h3 class="h5">{{ $message }}</h3>
        @if (isset($exception))
            <pre style="white-space: pre-wrap; word-break: break-word;">{{ $exception->getMessage() }}

{{ $exception->getTraceAsString() }}</pre>
        @endif
    </div>
@endsection
