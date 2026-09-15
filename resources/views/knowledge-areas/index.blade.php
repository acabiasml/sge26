@extends('layouts.app')
@section('title', __('Áreas e formações'))
@section('page-title', __('Áreas e formações'))
@section('content')
    <p>{{ __('A formação da área é utilizada por todos os componentes vinculados a ela, em todas as matrizes. Alterações valem para os próximos relatórios gerados.') }}</p>
    <section class="card mb-4">
        <div class="card-header">{{ __('Nova área') }}</div>
        <form class="card-body" method="POST" action="{{ route('knowledge-areas.store') }}">
            @csrf
            <label for="new-area-name">{{ __('Nome') }}</label><input id="new-area-name" name="name" class="form-control mb-2" required maxlength="255">
            <label for="new-area-formation">{{ __('Formação') }}</label><select id="new-area-formation" name="formation" class="form-control mb-3" required><option value="">{{ __('Selecione') }}</option>@foreach($formations as $formation)<option value="{{ $formation }}">{{ __($formation) }}</option>@endforeach</select>
            <button class="btn btn-primary">{{ __('Cadastrar') }}</button>
        </form>
    </section>
    @foreach($areas as $area)
        <form class="card card-body mb-3" method="POST" action="{{ route('knowledge-areas.update', $area) }}">
            @csrf @method('PUT')
            <div class="row align-items-end">
                <div class="col-md-5 form-group"><label for="area-name-{{ $area->id }}">{{ __('Área') }}</label><input id="area-name-{{ $area->id }}" name="name" class="form-control" value="{{ $area->name }}" required maxlength="255"></div>
                <div class="col-md-5 form-group"><label for="area-formation-{{ $area->id }}">{{ __('Formação') }}</label><select id="area-formation-{{ $area->id }}" name="formation" class="form-control" required><option value="">{{ __('Formação não definida') }}</option>@foreach($formations as $formation)<option value="{{ $formation }}" @selected($area->formation === $formation)>{{ __($formation) }}</option>@endforeach</select></div>
                <div class="col-md-2 form-group"><button class="btn btn-primary">{{ __('Salvar') }}</button></div>
            </div>
        </form>
    @endforeach
@endsection
