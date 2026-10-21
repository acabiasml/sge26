@extends('layouts.app')

@section('title', __('Definir proprietário do documento'))
@section('page-title', __('Definir proprietário do documento'))

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-6">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h2 class="h6 m-0 font-weight-bold text-primary">{{ __('Tornar documento privado') }}</h2>
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong>{{ $document->title }}</strong></p>
                    <p class="text-muted mb-4">{{ $document->school?->name }}</p>
                    <p>{{ __('Escolha o usuário que poderá consultar e excluir este documento privado. A autoria da emissão será preservada.') }}</p>

                    <form method="POST" action="{{ route('official-documents.visibility', $document) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="visibility" value="private">

                        <div class="form-group">
                            <label for="private_owner_user_id">{{ __('Proprietário do documento') }}</label>
                            <select id="private_owner_user_id" name="private_owner_user_id" class="form-control @error('private_owner_user_id') is-invalid @enderror" required>
                                <option value="">{{ __('Selecione um usuário') }}</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" @selected((string) old('private_owner_user_id') === (string) $user->id)>
                                        {{ $user->person?->full_name ?: $user->name }} — {{ $user->email }}
                                    </option>
                                @endforeach
                            </select>
                            @error('private_owner_user_id') <div class="invalid-feedback">{{ __($message) }}</div> @enderror
                        </div>

                        <button type="submit" class="btn btn-primary">{{ __('Confirmar como privado') }}</button>
                        <a href="{{ route('official-documents.create') }}" class="btn btn-outline-secondary">{{ __('Cancelar') }}</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
