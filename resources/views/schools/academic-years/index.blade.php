@extends('layouts.app')

@section('title', 'Anos letivos')
@section('page-title', 'Anos letivos - '.$school->name)

@section('page-actions')
    <a class="btn btn-sm btn-primary shadow-sm sge-icon-action" href="{{ route('schools.academic-years.create', $school) }}" aria-label="Cadastrar novo ano letivo para {{ $school->name }}" title="Novo ano letivo">
        <i class="fas fa-plus" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-primary shadow-sm ml-2 sge-icon-action" href="{{ route('schools.edit', $school) }}" aria-label="Editar escola {{ $school->name }}" title="Editar escola">
        <i class="fas fa-pen" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-secondary shadow-sm ml-2 sge-icon-action" href="{{ route('schools.index') }}" aria-label="Voltar para lista de escolas" title="Voltar">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h2 class="h6 m-0 font-weight-bold text-primary">{{ $school->name }}</h2>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Ano</th>
                        <th>Período</th>
                        <th>Dias letivos</th>
                        <th>Aprovação</th>
                        <th class="text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($school->academicYears as $year)
                        <tr>
                            <td>{{ $year->name }}</td>
                            <td>{{ $year->reference_year }}</td>
                            <td>{{ $year->starts_at?->format('d/m/Y') }} a {{ $year->ends_at?->format('d/m/Y') }}</td>
                            <td>{{ $year->schoolDayCount() }}</td>
                            <td>{{ $year->approved_at?->format('d/m/Y') ?? 'Pendente' }}</td>
                            <td class="text-right">
                                <div class="sge-action-buttons">
                                <a class="btn btn-sm btn-primary sge-icon-action" href="{{ route('academic-years.show', $year) }}" aria-label="Abrir ano letivo {{ $year->name }}" title="Abrir ano letivo">
                                    <i class="fas fa-folder-open" aria-hidden="true"></i>
                                </a>
                                <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('academic-years.calendar-pdf', $year) }}" aria-label="Emitir calendário oficial em PDF de {{ $year->name }}" title="Calendário em PDF">
                                    <i class="fas fa-file-pdf" aria-hidden="true"></i>
                                </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-gray-600">Nenhum ano letivo cadastrado para esta escola.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
