@extends('layouts.app')

@section('title', 'Novo ano letivo')
@section('page-title', 'Novo ano letivo')

@section('content')
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('academic-years.store') }}">
                @include('academic-years._form')
            </form>
        </div>
    </div>
@endsection
