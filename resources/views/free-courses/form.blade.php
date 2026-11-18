@extends('layouts.app')
@section('title', 'Cadastro de curso livre')
@section('page-title', $course->exists ? $course->name : 'Cadastrar curso livre')
@section('content')
<p class="text-muted">Salve o curso para reutilizar seu conteúdo e sua carga horária em diferentes turmas.</p>
<form method="POST" action="{{ $course->exists ? route('free-courses.update',$course) : route('free-courses.store') }}">
@csrf @if($course->exists) @method('PUT') @endif
@php($tabs=['dados'=>['label'=>'Dados do curso','icon'=>'fa-book','fields'=>['school_id','name','workload_hours','syllabus','active']], 'turmas'=>['label'=>'Turmas e participantes','icon'=>'fa-users']])
<x-section-tabs :tabs="$tabs" label="Cadastro do curso livre" />
<section id="section-dados" data-academic-panel="dados" role="tabpanel">
<div class="card shadow sge-panel-card mb-4"><div class="card-header"><h2 class="h6 mb-0">Dados do curso livre</h2></div><div class="card-body">
<div class="form-row"><div class="form-group col-md-8"><label for="school_id">Escola emissora</label><select id="school_id" name="school_id" class="form-control" required>@foreach($schools as $school)<option value="{{ $school->id }}" @selected(old('school_id',$course->school_id)==$school->id)>{{ $school->name }}</option>@endforeach</select></div>
<div class="form-group col-md-4"><label for="workload_hours">Carga horária (horas-relógio)</label><input class="form-control" id="workload_hours" name="workload_hours" type="number" step="0.01" min="0.01" max="10000" required value="{{ old('workload_hours',$course->workload_hours) }}"></div></div>
<div class="form-group"><label for="name">Nome do curso</label><input class="form-control" id="name" name="name" maxlength="255" required value="{{ old('name',$course->name) }}"></div>
<div class="form-group"><label for="syllabus">Conteúdo programático</label><textarea class="form-control" id="syllabus" name="syllabus" rows="10" maxlength="12000" required aria-describedby="syllabus-help">{{ old('syllabus',$course->syllabus) }}</textarea><small id="syllabus-help" class="form-text text-muted">Informe os temas, um por linha. Este conteúdo será impresso no verso do certificado.</small></div>
<input type="hidden" name="active" value="0"><div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" id="active" name="active" value="1" @checked(old('active',$course->active))><label class="custom-control-label" for="active">Curso ativo</label></div>
</div></div><button class="btn btn-primary" type="submit">Salvar curso</button>
</section>
<section id="section-turmas" data-academic-panel="turmas" role="tabpanel">
<div class="card shadow sge-panel-card mb-4"><div class="card-header"><h2 class="h6 mb-0">Turmas e participantes</h2></div><div class="card-body">
@if(!$course->exists)<p class="mb-0">Salve o curso primeiro. Depois cadastre as turmas e os participantes.</p>@else
<a class="btn btn-primary mb-3" href="{{ route('free-courses.offerings.create',$course) }}">Cadastrar turma ou atendimento individual</a>
<div class="table-responsive"><table class="table"><thead><tr><th>Turma</th><th>Período</th><th>Participantes</th><th>Ações</th></tr></thead><tbody>
@forelse($course->offerings()->withCount(['participants'=>fn($q)=>$q->whereNull('removed_at')])->orderByDesc('ends_at')->get() as $offering)<tr><td>{{ $offering->name }}</td><td>{{ $offering->starts_at->format('d/m/Y') }} a {{ $offering->ends_at->format('d/m/Y') }}</td><td>{{ $offering->participants_count }}</td><td><a href="{{ route('free-courses.offerings.edit',[$course,$offering]) }}">Gerenciar participantes</a> · <a href="{{ route('free-course-certificates.prepare',$offering) }}">Emitir certificados</a></td></tr>@empty<tr><td colspan="4">Nenhuma turma cadastrada.</td></tr>@endforelse
</tbody></table></div>
@endif
</div></div></section>
</form><a class="btn btn-outline-secondary mt-3" href="{{ route('courses.index',['school_id'=>$course->school_id]) }}">Voltar aos cursos</a>
@endsection
