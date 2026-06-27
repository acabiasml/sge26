@extends('layouts.app')

@section('title', 'Nova matriz')
@section('page-title', 'Nova matriz')

@section('page-actions')
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('academic-years.show', $academicYear) }}" aria-label="Voltar ao ano letivo {{ $academicYear->name }}" title="Voltar ao ano letivo">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    @include('academic-courses._form')
@endsection
