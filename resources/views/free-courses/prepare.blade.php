@extends('layouts.app')
@section('title', 'Certificado de curso livre')
@section('page-title', 'Emitir certificado de curso livre')
@section('content')
<div class="card shadow sge-panel-card mb-4"><div class="card-header"><h2 class="h6 mb-0">{{ $offering->freeCourse->name }}</h2></div><div class="card-body"><strong>{{ $offering->freeCourse->school->name }}</strong><p>{{ $offering->name }} · {{ $offering->starts_at->format('d/m/Y') }} a {{ $offering->ends_at->format('d/m/Y') }} · {{ number_format($offering->freeCourse->workload_hours,2,',','.') }} horas-relógio</p><details><summary>Conferir conteúdo programático</summary><p class="mt-2" style="white-space:pre-wrap">{{ $offering->freeCourse->syllabus }}</p></details><a class="sge-inline-link" href="{{ route('free-courses.offerings.edit',[$offering->freeCourse,$offering]) }}">Revisar turma e conclusões</a> · <a class="sge-inline-link" href="{{ route('free-courses.edit',$offering->freeCourse) }}">Revisar curso</a></div></div>
@if(!$logosReady)<div class="alert alert-warning">Confira os logos do Centro Técnico e da escola emissora na ficha da escola antes da emissão oficial.</div>@endif
<form method="POST" action="{{ route('free-course-certificates.store',$offering) }}">
@csrf<input type="hidden" name="expected_version" value="{{ $version }}">
<div class="card shadow sge-panel-card mb-4"><div class="card-header"><h2 class="h6 mb-0">Selecione os concluintes</h2></div><div class="card-body">
<p>{{ __('Confira as prévias e selecione até 50 concluintes. Cada certificado recebe um código de autenticidade e fica arquivado na ficha da pessoa.') }}</p>
<button type="button" class="btn btn-sm btn-outline-primary mb-3" id="select-graduates">Marcar / desmarcar todos os concluintes</button>
<div class="table-responsive"><x-livewire-table>
<table class="table"><thead><tr><th>Emitir</th><th>Participante</th><th>Situação</th><th>Conferência</th></tr></thead><tbody>
@forelse($offering->participants->whereNull('removed_at')->sortBy('person.full_name') as $participant)
@php($eligible=$certificates->eligible($participant))
@php($identityMissing=$certificates->missingIdentity($participant->person))
<tr><td><input type="checkbox" name="participant_ids[]" value="{{ $participant->id }}" @disabled(!$eligible || $identityMissing) @checked(in_array($participant->id,old('participant_ids',[]))) aria-label="Emitir para {{ $participant->person->full_name }}"></td><td>{{ $participant->person->full_name }}</td><td>{{ $eligible?'Conclusão confirmada':'Aguardando término ou confirmação de conclusão' }}@if($identityMissing)<br><span class="text-danger">Falta preencher: {{ implode(', ',$identityMissing) }}.</span> <a href="{{ route('people.edit',$participant->person) }}">Abrir ficha da pessoa</a>@endif</td><td><a target="_blank" rel="noopener" href="{{ route('free-course-certificates.preview',[$offering,$participant]) }}">Ver prévia sem registro</a></td></tr>
@empty<tr><td colspan="4">Cadastre os participantes na turma.</td></tr>@endforelse
</tbody></table>
</x-livewire-table></div></div></div>
<details class="mb-4" @if($errors->hasAny(['registry_book_id', 'registry_book', 'registry_number', 'registry_folio', 'registered_by'])) open @endif>
    <summary>{{ __('Registro em livro (opcional)') }}</summary>
    <div class="mt-2">
        @if($offering->freeCourse->school->usesPhysicalRegistry())
            <p>{{ __('Para informar uma referência em livro físico, selecione um participante por emissão.') }}</p>
        @endif
        @include('credential-registry.book-choice', ['school' => $offering->freeCourse->school, 'hasRegistrationDate' => true, 'optionalBook' => true, 'citeDocumentRegulation' => false])
    </div>
</details>
<div class="card shadow sge-panel-card mb-4"><div class="card-header"><h2 class="h6 mb-0">Emissão e assinaturas</h2></div><div class="card-body"><div class="form-row">
@foreach(['issued_place'=>'Local da emissão','director_name'=>'Nome completo da direção','instructor_name'=>'Nome completo do outro signatário','instructor_role'=>'Cargo do outro signatário'] as $field=>$label)
<div class="form-group col-md-6" ><label for="{{ $field }}">{{ $label }}</label><input class="form-control" id="{{ $field }}" name="{{ $field }}" maxlength="{{ $field==='instructor_role'?80:150 }}" required value="{{ old($field,$field==='issued_place'?$offering->freeCourse->school->city:($field==='instructor_role'?'Responsável pelo curso':'')) }}"></div>
@endforeach
<div class="form-group col-md-6"><label for="issued_date">Data da emissão</label><input class="form-control" id="issued_date" name="issued_date" type="date" required max="{{ now('America/Cuiaba')->toDateString() }}" value="{{ old('issued_date',now('America/Cuiaba')->toDateString()) }}"></div>
</div></div></div>
<button class="btn btn-primary" @disabled(!$logosReady)>Emitir e arquivar certificados oficiais</button><a class="btn btn-outline-secondary" href="{{ route('document-issuance.index') }}">Voltar à central</a>
</form>
@if($recent->isNotEmpty())<div class="card mt-4"><div class="card-header">Certificados já emitidos nesta turma</div><ul class="list-group list-group-flush">@foreach($recent as $document)<li class="list-group-item">{{ $document->payload['participant_name'] }} · {{ $document->issued_at->timezone('America/Cuiaba')->format('d/m/Y H:i') }} · <a href="{{ route('credential-archive.pdf',$document) }}" target="_blank" rel="noopener">Abrir PDF original</a> @include('partials.credential-delete',['document'=>$document])</li>@endforeach</ul></div>@endif
@endsection
@push('scripts')
<script>document.getElementById('select-graduates').onclick=()=>{const boxes=[...document.querySelectorAll('input[name="participant_ids[]"]:not(:disabled)')],mark=boxes.some(el=>!el.checked);boxes.forEach(el=>el.checked=mark);};</script>
@endpush
