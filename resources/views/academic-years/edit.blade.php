@extends('layouts.app')

@section('title', 'Editar ano letivo')
@section('page-title', 'Editar ano letivo')

@section('content')
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('academic-years.update', $academicYear) }}">
                @include('academic-years._form')
            </form>
        </div>
    </div>
@endsection
