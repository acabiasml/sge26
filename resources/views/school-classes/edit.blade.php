@extends('layouts.app')

@section('title', __('Editar turma'))
@section('page-title', __('Editar turma: ').$class->name)

@section('page-actions')
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('academic-years.classes.show', [$academicYear, $class]) }}" aria-label="{{ __('Voltar à turma') }} {{ $class->name }}" title="{{ __('Voltar à turma') }}">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    @include('school-classes._form')
@endsection
