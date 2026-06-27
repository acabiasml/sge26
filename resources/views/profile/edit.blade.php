@extends('layouts.app')

@section('title', 'Meu Cadastro')
@section('page-title', 'Meu Cadastro')

@section('content')
    <div class="card shadow mb-4">
        <div class="card-body">
            <p class="text-gray-700">Antes de continuar, confirme seus dados pessoais.</p>

            <form method="POST" action="{{ route('profile.update') }}">
                @csrf
                @method('PUT')

                @include('people._form', [
                    'person' => $person,
                    'lockInstitutionalEmail' => $lockInstitutionalEmail ?? true,
                    'lockOwnIdentity' => $lockOwnIdentity ?? true,
                    'showActiveControl' => false,
                ])

                <button class="btn btn-primary" type="submit">Salvar e continuar</button>
            </form>
        </div>
    </div>
@endsection
