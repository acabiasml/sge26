@extends('layouts.app')

@section('title', 'Históricos escolares')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <div class="sge-page-kicker">Vida escolar</div>
        <h1 class="h3 mb-0 text-gray-800">Históricos escolares</h1>
        <p class="mb-0 text-muted">Históricos separados por Ensino Fundamental e Ensino Médio.</p>
    </div>
</div>
<div class="card shadow mb-4"><div class="card-body">
    <form method="get" class="form-row align-items-end mb-4">
        <div class="form-group {{ $isAdministrator ? 'col-md-5' : 'col-md-8' }} mb-md-0"><label for="q">Estudante</label><input id="q" name="q" class="form-control" value="{{ request('q') }}" placeholder="Digite o nome do estudante"></div>
        @if($isAdministrator)
            <div class="form-group col-md-4 mb-md-0"><label for="school">Escola</label><select id="school" name="school" class="form-control"><option value="">Todas as escolas</option>@foreach($schools as $school)<option value="{{ $school->id }}" @selected($selectedSchoolId === $school->id)>{{ $school->name }}</option>@endforeach</select></div>
        @endif
        <div class="form-group {{ $isAdministrator ? 'col-md-3' : 'col-md-4' }} mb-0"><button class="btn btn-primary" type="submit"><i class="fas fa-search mr-1" aria-hidden="true"></i>Filtrar</button> <a class="btn btn-outline-secondary" href="{{ route('student-histories.index') }}">Limpar</a></div>
    </form>
    <div class="table-responsive"><table class="table table-hover">
        <thead><tr><th>Estudante</th><th>Registros internos</th><th>Progresso</th><th class="text-right">Ações</th></tr></thead>
        <tbody>
        @forelse($students as $student)
            <tr>
                <td><strong>{{ $student->full_name }}</strong><br><span class="small text-muted">INEP {{ $student->student_inep ?: 'não informado' }}</span></td>
                <td>{{ $student->student_enrollments_count }} matrícula(s)</td>
                <td>{{ $student->academicHistories->sum('years_count') }} ano(s) cadastrados</td>
                <td class="text-right text-nowrap">
                    <a class="btn btn-sm btn-primary" href="{{ route('student-histories.student', $student) }}">Gerenciar históricos</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="text-center text-muted py-4">Nenhum estudante encontrado.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="d-flex justify-content-center mt-3">{{ $students->links('pagination::bootstrap-4') }}</div>
</div></div>
@endsection
