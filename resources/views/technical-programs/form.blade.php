@extends('layouts.app')
@section('title', 'Cadastro do curso técnico')
@section('page-title', $program->exists ? $program->name : 'Cadastrar curso técnico')
@section('content')
@php
    $catalogOptions = $catalog->map->only(['id','name','knowledge_area_id'])->values();
    $moduleValues = old('modules',$program->modules?:[]);
    $programTabs = [
        'curso' => ['label'=>'Dados do curso','icon'=>'fa-graduation-cap','fields'=>['school_id','name','graduate_title','knowledge_area_id','offer_modes']],
        'autorizacao' => ['label'=>'Autorização','icon'=>'fa-stamp','fields'=>['authorization_act','official_gazette_reference','regulatory_process','regulatory_opinion','authorization_starts_at','authorization_ends_at']],
        'documentos' => ['label'=>'Documentos','icon'=>'fa-file-alt','fields'=>['legal_basis','registry_resolution','source_project']],
        'modulos' => ['label'=>'Módulos e componentes','icon'=>'fa-book','fields'=>['modules']],
    ];
@endphp
<p class="text-muted">Salve primeiro os dados do curso. Depois vincule os componentes já cadastrados na área curricular correspondente.</p>
<form method="POST" action="{{ $program->exists ? route('technical-programs.update', $program) : route('technical-programs.store') }}">
@csrf @if($program->exists) @method('PUT') @endif
<x-section-tabs :tabs="$programTabs" label="Seções do cadastro do curso técnico" />
<section id="section-curso" data-academic-panel="curso" role="tabpanel">
<div class="card shadow sge-panel-card mb-4"><div class="card-header py-3"><h2 class="h6 m-0 font-weight-bold text-primary">Dados do curso técnico</h2></div><div class="card-body">
<div class="form-row">
    <div class="col-md-6 form-group"><label for="school_id">Escola</label><select id="school_id" name="school_id" class="form-control" required>@foreach($schools as $school)<option value="{{ $school->id }}" @selected(old('school_id',$program->school_id)==$school->id)>{{ $school->name }}</option>@endforeach</select></div>
    <div class="col-md-6 form-group"><label for="knowledge_area_id">Área do cadastro de componentes</label><select id="knowledge_area_id" name="knowledge_area_id" class="form-control"><option value="">Selecione a área antes de vincular componentes</option>@foreach($areas as $area)<option value="{{ $area->id }}" @selected(old('knowledge_area_id',$program->knowledge_area_id)==$area->id)>{{ $area->name }}</option>@endforeach</select></div>
    @foreach(['name'=>'Nome do curso','graduate_title'=>'Título conferido no diploma','technological_axis'=>'Eixo tecnológico'] as $field=>$label)
    <div class="col-md-6 form-group"><label for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" class="form-control" maxlength="255" value="{{ old($field,$program->{$field}) }}" @required(in_array($field,['name','graduate_title']))></div>
    @endforeach
</div>
<fieldset class="form-group"><legend class="h6">Formas de oferta autorizadas</legend><div class="d-flex flex-wrap">
@foreach(\App\Models\TechnicalProgram::OFFER_MODES as $mode=>$label)<div class="custom-control custom-checkbox mr-4 mb-2"><input class="custom-control-input" type="checkbox" id="offer-{{ $mode }}" name="offer_modes[]" value="{{ $mode }}" @checked(in_array($mode,old('offer_modes',$program->offer_modes??[])))><label class="custom-control-label" for="offer-{{ $mode }}">{{ $label }}</label></div>@endforeach
</div></fieldset>
<input type="hidden" name="active" value="0"><div class="custom-control custom-checkbox"><input type="checkbox" id="program-active" class="custom-control-input" name="active" value="1" @checked(old('active',$program->active))><label for="program-active" class="custom-control-label">Curso ativo para novas matrizes</label></div>
</div></div>
@if($program->school)
<div class="card shadow sge-panel-card mb-4"><div class="card-header py-3"><h2 class="h6 m-0 font-weight-bold text-primary">Dados institucionais da escola</h2></div><div class="card-body">
<dl class="row mb-2"><dt class="col-md-3">Escola</dt><dd class="col-md-9">{{ $program->school->name }}</dd><dt class="col-md-3">Endereço</dt><dd class="col-md-9">{{ $program->school->documentAddress() }}</dd><dt class="col-md-3">Mantenedora</dt><dd class="col-md-9">{{ $program->school->maintainer ?: 'A preencher na ficha da escola' }}</dd><dt class="col-md-3">Ato de criação</dt><dd class="col-md-9">{{ $program->school->creation_act ?: 'A preencher na ficha da escola' }}</dd></dl>
<p class="text-muted mb-2">Os dados institucionais dos documentos são obtidos da ficha da escola.</p>
@if(auth()->user()->canManageSchool($program->school->id))<a href="{{ route('schools.edit',$program->school) }}#section-documentos">Abrir ficha da escola</a>@else<a href="{{ route('schools.academic-years.index',$program->school) }}">Abrir escola</a>@endif
</div></div>
@endif
</section>
<section id="section-autorizacao" data-academic-panel="autorizacao" role="tabpanel">
<div class="card shadow sge-panel-card mb-4"><div class="card-header py-3"><h2 class="h6 m-0 font-weight-bold text-primary">Autorização do curso</h2></div><div class="card-body"><p class="text-muted">A autorização cadastrada aqui é usada nos documentos e no subtítulo da área curricular vinculada ao curso.</p><div class="form-row">
@foreach(['authorization_act'=>'Ato de autorização do curso','official_gazette_reference'=>'Publicação da autorização','regulatory_process'=>'Processo','regulatory_opinion'=>'Parecer'] as $field=>$label)<div class="col-md-6 form-group"><label for="{{ $field }}">{{ $label }}</label><textarea id="{{ $field }}" name="{{ $field }}" class="form-control" rows="3" maxlength="2000">{{ old($field,$program->{$field}) }}</textarea></div>@endforeach
@foreach(['authorization_starts_at'=>'Início da autorização','authorization_ends_at'=>'Fim da autorização'] as $field=>$label)<div class="col-md-6 form-group"><label for="{{ $field }}">{{ $label }}</label><input class="form-control" id="{{ $field }}" type="date" name="{{ $field }}" value="{{ old($field,$program->{$field}?->format('Y-m-d')) }}"></div>@endforeach
</div></div></div>
</section>
<section id="section-documentos" data-academic-panel="documentos" role="tabpanel">
<div class="card shadow sge-panel-card mb-4"><div class="card-header py-3"><h2 class="h6 m-0 font-weight-bold text-primary">Dados do curso para documentos</h2></div><div class="card-body">
@foreach(['legal_basis'=>'Fundamento legal','registry_resolution'=>'Norma do registro de certificados e diplomas','source_project'=>'Projeto / documento de referência'] as $field=>$label)<div class="form-group"><label for="{{ $field }}">{{ $label }}</label><textarea id="{{ $field }}" name="{{ $field }}" class="form-control" rows="3" maxlength="2000">{{ old($field,$program->{$field}) }}</textarea></div>@endforeach
</div></div>
</section>
<section id="section-modulos" data-academic-panel="modulos" role="tabpanel">
<div class="card shadow sge-panel-card mb-4"><div class="card-header py-3"><h2 class="h6 m-0 font-weight-bold text-primary">Módulos e componentes vinculados</h2></div><div class="card-body">
@if(!$program->exists)<div class="alert alert-info mb-0">Salve os dados do curso técnico para começar a associar módulos e componentes.</div>
@else
<input type="hidden" name="modules_submitted" value="1">
<p>Selecione componentes já cadastrados na área deste curso e informe a carga em horas-relógio. A qualificação intermediária é opcional no módulo de conclusão do diploma.</p>
@if(auth()->user()->isAdministrator())<a id="open-program-catalog" class="btn btn-outline-primary mb-3" href="{{ route('history-curriculum.index',['table-filters'=>['area_id'=>$program->knowledge_area_id,'education_stage'=>'tecnico']]) }}">Abrir cadastro de componentes</a>@endif
<div class="alert alert-info" id="program-area-notice" @if(old('knowledge_area_id',$program->knowledge_area_id)) hidden @endif>Selecione a área curricular em Dados do curso antes de vincular componentes.</div>
<div id="program-modules"></div><button type="button" id="add-module" class="btn btn-outline-primary">Adicionar módulo</button>
@endif
</div></div>
@if($program->exists)<div class="card shadow sge-panel-card mb-4"><div class="card-header py-3"><h2 class="h6 m-0 font-weight-bold text-primary">Matrizes vinculadas</h2></div><ul class="list-group list-group-flush">@forelse($program->matrices()->with('academicYear')->get() as $matrix)<li class="list-group-item"><a href="{{ route('academic-years.courses.show',[$matrix->academic_year_id,$matrix]) }}">{{ $matrix->name }} · {{ $matrix->academicYear->referenceYearsLabel() }}</a></li>@empty<li class="list-group-item">Nenhuma matriz vinculada.</li>@endforelse</ul></div>@endif
</section>
<div class="d-flex justify-content-between mb-4"><a class="btn btn-outline-secondary" href="{{ route('technical-programs.index',['school_id'=>$program->school_id]) }}">Voltar aos cursos técnicos</a><button class="btn btn-primary" type="submit">{{ $program->exists?'Salvar alterações':'Salvar dados do curso' }}</button></div>
</form>
@endsection
@if($program->exists)
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const holder=document.getElementById('program-modules'),area=document.getElementById('knowledge_area_id');
 const catalog=@json($catalogOptions);let serial=0;
 const options=()=>catalog.filter(item=>String(item.knowledge_area_id)===area.value);
 function input(parent,label,name,value='',type='text'){
  const box=document.createElement('div');box.className=type==='hidden'?'':'form-group';const lab=document.createElement('label'),el=document.createElement('input');
  el.type=type;el.name=name;el.value=value??'';el.id='program-'+name.replace(/[^a-z0-9]/gi,'-');el.className='form-control';el.maxLength=255;lab.htmlFor=el.id;lab.textContent=label;
  if(type==='number'){el.min='0.01';el.step='0.01';el.required=true;}else if(type!=='hidden'&&!name.endsWith('[qualification]'))el.required=true;
  if(type==='hidden'){parent.append(el);return el;}box.append(lab,el);parent.append(box);return el;
 }
 function fillSelect(select,value){select.replaceChildren(new Option('Selecione um componente cadastrado',''),...options().map(item=>new Option(item.name,item.id)));select.value=value??'';}
 function module(data={}){
  const n=serial++,base=`modules[${n}]`,section=document.createElement('details');section.className='card sge-panel-card mb-3';section.open=false;
  const summary=document.createElement('summary');summary.className='sge-panel-header sge-history-section-summary';const title=document.createElement('h3');title.className='h6 mb-0';title.textContent=data.name||'Novo módulo';summary.append(title);section.append(summary);
  const body=document.createElement('div');body.className='card-body';section.append(body);input(body,'',base+'[key]',data.key,'hidden');
  const name=input(body,'Nome do módulo',base+'[name]',data.name);name.addEventListener('input',()=>title.textContent=name.value||'Novo módulo');input(body,'Qualificação intermediária (se houver)',base+'[qualification]',data.qualification);
  const list=document.createElement('div');body.append(list);let next=0;
  function component(c={}){
   const row=document.createElement('div');row.className='form-row align-items-end border-bottom mb-3 pb-3';const key=base+`[components][${next++}]`;input(row,'',key+'[key]',c.key,'hidden');
   const choice=document.createElement('div');choice.className='form-group col-md-8';const label=document.createElement('label'),select=document.createElement('select');select.name=key+'[catalog_component_id]';select.id='catalog-'+n+'-'+next;label.htmlFor=select.id;label.textContent='Componente cadastrado';select.className='form-control';select.required=true;select.dataset.programCatalog='';fillSelect(select,c.catalog_component_id);choice.append(label,select);row.append(choice);
   const hours=document.createElement('div');hours.className='col-md-3';input(hours,'Carga horária (h)',key+'[hours]',c.hours,'number');row.append(hours);
   const remove=document.createElement('button');remove.type='button';remove.className='btn btn-outline-danger btn-sm mb-3';remove.textContent='Desvincular';remove.onclick=()=>row.remove();row.append(remove);list.append(row);
  }
  (data.components??[]).forEach(component);const add=document.createElement('button');add.type='button';add.className='btn btn-outline-primary mr-2';add.textContent='Vincular componente existente';add.onclick=()=>component();const del=document.createElement('button');del.type='button';del.className='btn btn-outline-danger';del.textContent='Remover módulo';del.onclick=()=>section.remove();body.append(add,del);holder.append(section);if(!data.key)section.open=true;
 }
 const data=@json($moduleValues);(Array.isArray(data)?data:Object.values(data)).forEach(module);
 document.getElementById('add-module').onclick=()=>module();area.addEventListener('change',()=>{document.getElementById('program-area-notice').hidden=!!area.value;holder.querySelectorAll('[data-program-catalog]').forEach(select=>fillSelect(select,select.value));});
});
</script>
@endpush
@endif
