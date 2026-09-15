@extends('layouts.app')
@section('title', __('Editar recado'))
@section('page-title', __('Editar recado'))
@section('content')
    <a href="{{ route('announcements.index') }}" class="btn btn-outline-primary mb-3">{{ __('Voltar para Recados') }}</a>
    <div class="card shadow mb-4"><div class="card-body">@include('announcements.form')</div></div>
@endsection
