@extends('layouts.app')
@section('title', 'Turma do curso livre')
@section('page-title', $course->name)
@section('content')
<form method="POST" action="{{ $offering->exists ? route('free-courses.offerings.update',[$course,$offering]) : route('free-courses.offerings.store',$course) }}">
@csrf @if($offering->exists) @method('PUT') @endif
<div class="card shadow sge-panel-card mb-4"><div class="card-header"><h2 class="h6 mb-0">Turma ou atendimento individual</h2></div><div class="card-body"><div class="form-row">
<div class="form-group col-md-8"><label for="name">Identificação da turma</label><input class="form-control" name="name" id="name" maxlength="255" required value="{{ old('name',$offering->name) }}" placeholder="Ex.: Turma de novembro de 2026"></div>
<div class="form-group col-md-4"><label for="delivery_mode">Modalidade</label><select class="form-control" id="delivery_mode" name="delivery_mode" required>@foreach(\App\Models\FreeCourseOffering::MODES as $value=>$label)<option value="{{ $value }}" @selected(old('delivery_mode',$offering->delivery_mode)===$value)>{{ $label }}</option>@endforeach</select></div>
@foreach(['starts_at'=>'Início','ends_at'=>'Término'] as $field=>$label)<div class="form-group col-md-3"><label for="{{ $field }}">{{ $label }}</label><input class="form-control" id="{{ $field }}" name="{{ $field }}" type="date" value="{{ old($field,$offering->{$field}?->format('Y-m-d')) }}" required></div>@endforeach
<div class="form-group col-md-6"><label for="location">Local de realização</label><input class="form-control" id="location" name="location" maxlength="255" required value="{{ old('location',$offering->location) }}"></div></div></div></div>
<div class="card shadow sge-panel-card mb-4"><div class="card-header"><h2 class="h6 mb-0">Participantes e conclusões</h2></div><div class="card-body">
<p>Para emissão individual, cadastre uma pessoa. Para emissão em lote, cadastre os participantes da turma. Marque a conclusão somente para quem concluiu o curso.</p>
<label for="participant-query">Localizar pessoa cadastrada</label><div class="input-group mb-2"><input class="form-control" id="participant-query" maxlength="100" placeholder="Nome completo ou parte do nome"><div class="input-group-append"><button class="btn btn-outline-primary" type="button" id="find-participant">Buscar</button></div></div><div id="participant-results" class="list-group mb-3" aria-live="polite"></div>
<div id="participant-roster"></div>
@if($classes->isNotEmpty())
<details class="mt-4"><summary>Adicionar estudantes de uma turma escolar</summary><p class="text-muted mt-2">Serão adicionados os estudantes com matrícula no período informado. A conclusão do curso livre continua sendo confirmada individualmente.</p><label for="import-class">Turma escolar</label><div class="input-group"><select class="form-control" id="import-class"><option value="">Selecione</option>@foreach($classes as $class)<option value="{{ $class->id }}">{{ $class->academicYear->referenceYearsLabel() }} · {{ $class->name }}</option>@endforeach</select><div class="input-group-append"><button class="btn btn-outline-primary" type="button" id="import-class-people">Adicionar participantes</button></div></div><p id="import-status" class="mt-2" aria-live="polite"></p></details>
@endif
</div></div>
<button class="btn btn-primary" type="submit">Salvar turma e participantes</button><a class="btn btn-outline-secondary" href="{{ route('free-courses.edit',$course) }}#section-turmas">Voltar ao curso</a>
@if($offering->exists)<a class="btn btn-outline-primary" href="{{ route('free-course-certificates.prepare',$offering) }}">Ir à emissão de certificados</a>@endif
</form>
@php($roster=$offering->exists ? $offering->participants->whereNull('removed_at')->map(fn($p)=>['person_id'=>$p->person_id,'full_name'=>$p->person->full_name,'completed'=>(bool)$p->completed_at])->values()->all() : [])
@php($rosterInput=old('participants',$roster))
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded',()=>{
const roster=document.getElementById('participant-roster'),results=document.getElementById('participant-results'),query=document.getElementById('participant-query'),button=document.getElementById('find-participant');let serial=0;
const names=@json(collect($roster)->pluck('full_name','person_id'));const initial=@json($rosterInput);
function add(p){if(roster.querySelector(`[data-person-id="${Number(p.person_id)}"]`))return;const n=serial++,row=document.createElement('div');row.className='d-flex flex-wrap align-items-center border rounded p-3 mb-2';row.dataset.personId=p.person_id;
const hidden=document.createElement('input');hidden.type='hidden';hidden.name=`participants[${n}][person_id]`;hidden.value=p.person_id;
const name=document.createElement('strong');name.className='mr-auto';name.textContent=p.full_name||names[p.person_id]||`Pessoa ${p.person_id}`;
const label=document.createElement('label');label.className='mb-0 mx-3';const check=document.createElement('input');check.type='checkbox';check.name=`participants[${n}][completed]`;check.value='1';check.checked=!!Number(p.completed);label.append(check,document.createTextNode(' Conclusão confirmada'));
const remove=document.createElement('button');remove.type='button';remove.className='btn btn-sm btn-outline-danger';remove.textContent='Remover da turma';remove.onclick=()=>row.remove();row.append(hidden,name,label,remove);roster.append(row);}
(Array.isArray(initial)?initial:Object.values(initial)).forEach(add);
button.onclick=async()=>{if(query.value.trim().length<2){results.textContent='Digite pelo menos dois caracteres.';return;}button.disabled=true;results.textContent='Buscando...';try{const url=new URL(@json(route('free-courses.people',$course)),location.origin);url.searchParams.set('q',query.value.trim());const response=await fetch(url,{headers:{Accept:'application/json'}});if(!response.ok)throw new Error();const people=await response.json();results.replaceChildren();if(!people.length)results.textContent='Nenhuma pessoa encontrada nesta escola. Confira o cadastro de pessoas.';people.forEach(p=>{const el=document.createElement('button');el.type='button';el.className='list-group-item list-group-item-action';el.textContent=p.full_name+' — adicionar';el.onclick=()=>{add({person_id:p.id,full_name:p.full_name,completed:false});el.remove();};results.append(el);});}catch{results.textContent='Não foi possível buscar. Tente novamente.';}finally{button.disabled=false;}};
query.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();button.click();}});
const importer=document.getElementById('import-class-people');if(importer)importer.onclick=async()=>{const status=document.getElementById('import-status'),classId=document.getElementById('import-class').value,start=document.getElementById('starts_at').value,end=document.getElementById('ends_at').value;if(!classId||!start||!end){status.textContent='Selecione a turma e informe o início e o término do curso.';return;}importer.disabled=true;try{const url=new URL(@json(route('free-courses.class-participants',[$course,'__CLASS__'])).replace('__CLASS__',encodeURIComponent(classId)),location.origin);url.searchParams.set('starts_at',start);url.searchParams.set('ends_at',end);const response=await fetch(url,{headers:{Accept:'application/json'}});if(!response.ok)throw new Error();const people=await response.json();people.forEach(p=>add({person_id:p.id,full_name:p.full_name,completed:false}));status.textContent=people.length?'Participantes adicionados. Confira a lista e as conclusões antes de salvar.':'Nenhuma matrícula encontrada para esse período.';}catch{status.textContent='Não foi possível consultar a turma. Tente novamente.';}finally{importer.disabled=false;}};
});
</script>
@endpush
