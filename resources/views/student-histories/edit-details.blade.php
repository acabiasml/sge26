@extends('layouts.app')

@section('title', 'Dados gerais do histórico')
@section('page-title', 'Dados gerais do histórico')

@section('page-actions')
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('people.histories.show', [$person, $history]) }}" aria-label="Voltar ao histórico escolar" title="Voltar ao histórico"><i class="fas fa-arrow-left" aria-hidden="true"></i></a>
@endsection

@section('content')
<div class="row justify-content-center"><div class="col-xl-8"><div class="card shadow sge-panel-card mb-4">
    <div class="sge-panel-header"><div><h2>{{ $history->stage }}</h2><p>Identificação, fundamento legal e emissão do documento.</p></div></div>
    <div class="card-body"><form method="post" action="{{ route('people.histories.details.update', [$person, $history]) }}">@csrf @method('PUT')
        <div class="form-group"><label for="title">Título</label><input id="title" name="title" class="form-control" value="{{ old('title', $history->title) }}" required></div>
        <div class="form-group"><label for="school_id">Escola emissora</label><select id="school_id" name="school_id" class="form-control" required>@foreach($schools as $school)<option value="{{ $school->id }}" @selected((string) old('school_id', $history->school_id) === (string) $school->id)>{{ $school->name }}</option>@endforeach</select></div>
        <div class="form-group"><label for="legal_basis">Fundamento legal</label><textarea id="legal_basis" name="legal_basis" class="form-control" rows="3" required>{{ old('legal_basis', $history->legal_basis) }}</textarea></div>
        <div class="form-group"><label for="notes">Observações gerais</label><textarea id="notes" name="notes" class="form-control" rows="5">{{ old('notes', $history->notes) }}</textarea></div>
        <div class="form-row"><div class="form-group col-md-7"><label for="issued_place">Local de emissão</label><input id="issued_place" name="issued_place" class="form-control" value="{{ old('issued_place', $history->issued_place) }}" required></div><div class="form-group col-md-5"><label for="issued_date">Data</label><input id="issued_date" type="date" name="issued_date" class="form-control" value="{{ old('issued_date', $history->issued_date?->toDateString()) }}" required></div></div>
        <div class="custom-control custom-switch mb-4"><input id="active" name="active" value="1" type="checkbox" class="custom-control-input" @checked(old('active', $history->active))><label class="custom-control-label" for="active">Histórico ativo</label></div>
        <button class="btn btn-primary" type="submit"><i class="fas fa-save mr-1"></i>Salvar dados gerais</button>
    </form></div>
</div></div></div>
@endsection
