@if(auth()->user()?->canManageSchool($school->id))
<div class="card shadow mb-4" id="school-technical-programs">
    <div class="card-header py-3"><h2 class="h6 m-0 font-weight-bold text-primary"><i class="fas fa-graduation-cap mr-2" aria-hidden="true"></i>{{ __('navigation.technical_programs') }}</h2></div>
    <div class="card-body">
        <p>Gerencie os cursos técnicos desta escola: autorizações, modalidades, módulos, qualificações intermediárias, componentes e cargas horárias.</p>
        <div class="mb-3">
            <a class="btn btn-primary mr-2 mb-2" href="{{ route('technical-programs.index', ['school_id'=>$school->id]) }}">Gerenciar cursos técnicos</a>
            <a class="btn btn-outline-primary mb-2" href="{{ route('technical-programs.create', ['school_id'=>$school->id]) }}">Cadastrar curso técnico</a>
        </div>
        @php($schoolPrograms=$school->technicalPrograms()->orderBy('name')->get())
        @if($schoolPrograms->isNotEmpty())
        <ul class="list-group list-group-flush">
            @foreach($schoolPrograms as $program)
            <li class="list-group-item px-0"><a href="{{ route('technical-programs.edit',$program) }}">{{ $program->name }}</a>@if(!$program->active) <span class="badge badge-secondary">Inativo</span>@endif<br><span class="text-muted">{{ $program->offer_forms }} · {{ count($program->modules) }} módulos · {{ number_format($program->totalHours(),2,',','.') }} h</span></li>
            @endforeach
        </ul>
        @else<p class="text-muted mb-0">Nenhum curso técnico cadastrado nesta escola.</p>@endif
    </div>
</div>
@endif
