@extends('layouts.app')
@section('title', __('Atestado de transferência'))
@section('page-title', __('Atestado de transferência'))

@section('content')
    <div class="card sge-panel-card">
        <div class="card-body">
            <p><strong>{{ $enrollment->student->full_name }}</strong></p>
            <form method="GET" action="{{ route('enrollments.transfer-certificate.pdf', $enrollment) }}">
                <label class="d-block mb-3"><input type="checkbox" name="confirm_missing_student_cpf" value="1" required> {{ __('Confirmo a emissão sem CPF do estudante, após conferir o cadastro.') }}</label>
                <button class="btn btn-primary" type="submit">{{ __('Emitir atestado') }}</button>
            </form>
        </div>
    </div>
@endsection
