@extends('layouts.app')

@section('title', 'Novo ano letivo')
@section('page-title', 'Novo ano letivo - '.$school->name)

@section('content')
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('schools.academic-years.store', $school) }}">
                @include('academic-years._form', ['school' => $school])
            </form>
        </div>
    </div>
@endsection
