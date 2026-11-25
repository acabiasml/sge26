@if(auth()->user()?->canManageSchool($school->id))
<div class="mb-4" id="school-technical-programs">
        <div class="mb-3">
            <a class="btn btn-primary mr-2 mb-2" href="{{ route('courses.index', ['school_id'=>$school->id]) }}">Gerenciar cursos</a>
            <a class="btn btn-outline-primary mb-2" href="{{ route('technical-programs.create', ['school_id'=>$school->id]) }}">Cadastrar curso técnico</a>
            <a class="btn btn-outline-primary mb-2" href="{{ route('free-courses.create', ['school_id'=>$school->id]) }}">Cadastrar curso livre</a>
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
@endif
