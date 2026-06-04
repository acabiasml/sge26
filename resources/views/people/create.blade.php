@extends('layouts.app')

@section('title', 'Nova Pessoa')
@section('page-title', 'Nova Pessoa')

@section('content')
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('people.store') }}">
                @csrf
                @include('people._form', ['person' => new \App\Models\Person(['active' => true])])

                <hr>
                <h2 class="h5 text-gray-900">Vínculo inicial</h2>

                @if (! $requiresInitialRole)
                    <p class="text-gray-600">Opcional para Administração. A Gestão sempre deve cadastrar pessoas já vinculadas a uma escola.</p>
                @endif

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="initial_role">Papel</label>
                        <select id="initial_role" name="initial_role" class="form-control @error('initial_role') is-invalid @enderror" data-role-select @required($requiresInitialRole)>
                            <option value="">Selecione</option>
                            @foreach ($roles as $value => $label)
                                <option value="{{ $value }}" @selected(old('initial_role') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('initial_role') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group col-md-4">
                        <label for="initial_school_id">Escola</label>
                        <select id="initial_school_id" name="initial_school_id" class="form-control @error('initial_school_id') is-invalid @enderror" @required($requiresInitialRole)>
                            <option value="">Global / sem escola</option>
                            @foreach ($schools as $school)
                                <option value="{{ $school->id }}" @selected((int) old('initial_school_id') === $school->id)>{{ $school->name }}</option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">Administração é sempre global, sem escola vinculada.</small>
                        @error('initial_school_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group col-md-4">
                        <label for="initial_position">Área da gestão</label>
                        <select id="initial_position" name="initial_position" class="form-control @error('initial_position') is-invalid @enderror" data-manager-position>
                            <option value="">Selecione quando o papel for Gestão</option>
                            @foreach ($positions as $value => $label)
                                <option value="{{ $value }}" @selected(old('initial_position') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('initial_position') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="initial_started_at">Início</label>
                        <input id="initial_started_at" name="initial_started_at" type="date" class="form-control @error('initial_started_at') is-invalid @enderror" value="{{ old('initial_started_at') }}">
                        <small class="form-text text-muted">Obrigatório para vínculos de escola. Administração recebe a data atual.</small>
                        @error('initial_started_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group col-md-6">
                        <label for="initial_ended_at">Fim</label>
                        <input id="initial_ended_at" name="initial_ended_at" type="date" class="form-control @error('initial_ended_at') is-invalid @enderror" value="{{ old('initial_ended_at') }}">
                        @error('initial_ended_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <button class="btn btn-primary" type="submit">Salvar pessoa</button>
                <a class="btn btn-secondary" href="{{ route('people.index') }}">Cancelar</a>
            </form>
        </div>
    </div>
@endsection
