@extends('layouts.app')

@section('title', __('Editar matriz'))
@section('page-title', __('Editar matriz: ').$course->name)

@section('page-actions')
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('academic-years.courses.show', [$academicYear, $course]) }}" aria-label="{{ __('Voltar à matriz') }} {{ $course->name }}" title="{{ __('Voltar à matriz') }}">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    @include('academic-courses._form')
@endsection
