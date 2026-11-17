@extends('layouts.app')
@section('title', 'Cursos técnicos da escola')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4"><h1 class="h3">Cursos técnicos da escola</h1><a class="btn btn-primary" href="{{ route('technical-programs.create') }}">Cadastrar curso técnico</a></div>
<p>Cadastro de referência para as matrizes, módulos e documentos de cada curso técnico.</p>
<div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Curso</th><th>Escola</th><th>Oferta</th><th>Módulos / horas-relógio</th><th></th></tr></thead><tbody>
@forelse($programs as $program)<tr><td>{{ $program->name }}@if(!$program->active) <small>Inativo</small>@endif</td><td>{{ $program->school->name }}</td><td>{{ $program->offer_forms }}</td><td>{{ count($program->modules) }} / {{ number_format($program->totalHours(), 2, ',', '.') }} h</td><td><a href="{{ route('technical-programs.edit', $program) }}">Abrir cadastro</a></td></tr>@empty<tr><td colspan="5">Nenhum curso técnico cadastrado.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
