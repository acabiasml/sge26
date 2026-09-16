@extends('layouts.app')
@section('title', __('Editar recado'))
@section('page-title', __('Editar recado'))
@section('page-actions')
    <a href="{{ route('announcements.index') }}" class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" aria-label="{{ __('Voltar para Recados') }}" title="{{ __('Voltar para Recados') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i></a>
@endsection
@section('content')
    <div class="card shadow mb-4"><div class="card-body">@include('announcements.form')</div></div>
@endsection
