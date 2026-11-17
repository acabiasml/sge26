@extends('layouts.app')
@section('title', 'Cadastro do curso técnico')
@section('content')
<h1 class="h3 mb-3">{{ $program->exists ? $program->name : 'Cadastrar curso técnico' }}</h1>
<p>Este cadastro reúne os dados usados pelas matrizes e pela emissão de diplomas e certificados. Cada escola pode cadastrar mais de um curso.</p>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ $program->exists ? route('technical-programs.update', $program) : route('technical-programs.store') }}">
@csrf @if($program->exists) @method('PUT') @endif
<div class="card mb-4"><div class="card-header">Identificação, oferta e autorização</div><div class="card-body"><div class="row">
<div class="col-md-6 form-group"><label for="school_id">Escola</label><select id="school_id" name="school_id" class="form-control" required>@foreach($schools as $school)<option value="{{ $school->id }}" @selected(old('school_id', $program->school_id)==$school->id)>{{ $school->name }}</option>@endforeach</select></div>
@foreach(['name'=>'Nome do curso','graduate_title'=>'Título conferido no diploma','technological_axis'=>'Eixo tecnológico','education_mode'=>'Modalidade (presencial, a distância...)','offer_forms'=>'Formas de oferta (concomitante, subsequente...)','maintainer'=>'Mantenedora','state_heading'=>'Estado no cabeçalho','education_system'=>'Sistema de ensino','creation_act'=>'Ato legal de criação','accreditation_act'=>'Credenciamento da instituição','authorization_act'=>'Autorização do curso','official_gazette_reference'=>'Publicação da autorização','regulatory_process'=>'Processo','regulatory_opinion'=>'Parecer','registry_resolution'=>'Norma do registro','front_address'=>'Endereço na frente do documento','back_address'=>'Endereço e contato no verso','legal_basis'=>'Fundamento legal dos documentos','source_project'=>'Projeto / documento de referência'] as $field=>$label)
<div class="col-md-6 form-group"><label for="{{ $field }}">{{ $label }}</label><textarea rows="2" id="{{ $field }}" name="{{ $field }}" maxlength="{{ in_array($field,['name','graduate_title','technological_axis','education_mode','offer_forms','maintainer','state_heading','education_system'])?255:2000 }}" class="form-control" @if(in_array($field,['name','graduate_title'])) required @endif>{{ old($field, $program->{$field}) }}</textarea></div>
@endforeach
@foreach(['authorization_starts_at'=>'Início da autorização','authorization_ends_at'=>'Fim da autorização'] as $field=>$label)<div class="col-md-6 form-group"><label for="{{ $field }}">{{ $label }}</label><input class="form-control" id="{{ $field }}" type="date" name="{{ $field }}" value="{{ old($field, $program->{$field}?->format('Y-m-d')) }}"></div>@endforeach
</div><input type="hidden" name="active" value="0"><label><input type="checkbox" name="active" value="1" @checked(old('active', $program->active))> Curso ativo para novas matrizes</label></div></div>
<div class="card mb-4"><div class="card-header">Módulos, saídas intermediárias e componentes</div><div class="card-body">
<p>Informe a carga em horas-relógio. Deixe a qualificação intermediária vazia no módulo que conclui o diploma. Alterações de nome e carga serão refletidas nos componentes vinculados às matrizes.</p>
<div id="program-modules"></div><button type="button" id="add-module" class="btn btn-outline-primary">Adicionar módulo</button>
</div></div>
<div class="d-flex justify-content-between"><a class="btn btn-outline-secondary" href="{{ route('technical-programs.index',['school_id'=>$program->school_id]) }}">Voltar aos cursos técnicos</a><button class="btn btn-primary">Salvar cadastro do curso</button></div>
</form>
@if($program->exists)<div class="card mt-4"><div class="card-header">Matrizes vinculadas</div><ul class="list-group list-group-flush">@forelse($program->matrices()->with('academicYear')->get() as $matrix)<li class="list-group-item"><a href="{{ route('academic-years.courses.show', [$matrix->academic_year_id,$matrix]) }}">{{ $matrix->name }} · {{ $matrix->academicYear->referenceYearsLabel() }}</a></li>@empty<li class="list-group-item">Nenhuma matriz vinculada.</li>@endforelse</ul></div>@endif
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const holder=document.getElementById('program-modules'); let serial=0;
 function input(parent,label,name,value='',type='text'){
  const box=document.createElement('div');box.className='form-group';const lab=document.createElement('label');const el=document.createElement('input');el.type=type;el.maxLength=255;el.name=name;el.value=value??'';el.id='program-'+name.replace(/[^a-z0-9]/gi,'-');lab.htmlFor=el.id;lab.textContent=label;el.className='form-control';if(type==='number'){el.min='0.01';el.step='0.01';el.required=true;}else if(type!=='hidden'&&!name.endsWith('[qualification]'))el.required=true;if(type==='hidden'){parent.append(el);return el;}box.append(lab,el);parent.append(box);return el;
 }
 function module(data={}){
  const n=serial++,base=`modules[${n}]`,section=document.createElement('section');section.className='border rounded p-3 mb-3';
  const title=document.createElement('h2');title.className='h5';title.textContent='Módulo';section.append(title);
  input(section,'',base+'[key]',data.key,'hidden');input(section,'Nome do módulo',base+'[name]',data.name);input(section,'Qualificação intermediária (se houver)',base+'[qualification]',data.qualification);
  const list=document.createElement('div');section.append(list);let next=0;
  function component(c={}){const row=document.createElement('div');row.className='border-bottom py-2 mb-2';const key=base+`[components][${next++}]`;input(row,'',key+'[key]',c.key,'hidden');input(row,'Componente',key+'[name]',c.name);input(row,'Carga horária (h)',key+'[hours]',c.hours,'number');const del=document.createElement('button');del.type='button';del.className='btn btn-sm btn-outline-danger';del.textContent='Remover componente';del.onclick=()=>row.remove();row.append(del);list.append(row);}
  (data.components??[{}]).forEach(component);const add=document.createElement('button');add.type='button';add.className='btn btn-sm btn-outline-primary mr-2';add.textContent='Adicionar componente';add.onclick=()=>component();const del=document.createElement('button');del.type='button';del.className='btn btn-sm btn-outline-danger';del.textContent='Remover módulo';del.onclick=()=>section.remove();section.append(add,del);holder.append(section);
 }
 const data=@json(old('modules',$program->modules?:[]));(Array.isArray(data)?data:Object.values(data)).forEach(module);if(!holder.children.length)module();document.getElementById('add-module').onclick=()=>module();
});
</script>
@endpush
