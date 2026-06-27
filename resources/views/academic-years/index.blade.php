@extends('layouts.app')

@section('title', 'Anos letivos')
@section('page-title', 'Anos letivos')

@section('page-actions')
    <a class="btn btn-sm btn-primary shadow-sm sge-icon-action" href="{{ route('academic-years.create') }}" aria-label="Cadastrar novo ano letivo" title="Novo ano letivo">
        <i class="fas fa-plus" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    <div class="card shadow mb-4">
        <div class="card-body table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Escola</th>
                        <th>Ano</th>
                        <th>Período</th>
                        <th>Dias letivos</th>
                        <th>Situação</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($years as $year)
                        <tr>
                            <td>{{ $year->name }}</td>
                            <td>{{ $year->school?->name }}</td>
                            <td>{{ $year->reference_year }}</td>
                            <td>{{ $year->starts_at?->format('d/m/Y') }} a {{ $year->ends_at?->format('d/m/Y') }}</td>
                            <td>{{ $year->schoolDayCount() }} / mínimo {{ $year->minimum_school_days }}</td>
                            <td>{{ $year->active ? 'Ativo' : 'Inativo' }}</td>
                            <td>
                                <div class="sge-action-buttons">
                                <a class="btn btn-sm btn-primary sge-icon-action" href="{{ route('academic-years.show', $year) }}" aria-label="Abrir ano letivo {{ $year->name }}" title="Abrir ano letivo">
                                    <i class="fas fa-folder-open" aria-hidden="true"></i>
                                </a>
                                <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('academic-years.edit', $year) }}" aria-label="Editar ano letivo {{ $year->name }}" title="Editar ano letivo">
                                    <i class="fas fa-pen" aria-hidden="true"></i>
                                </a>
                                <form class="d-inline" method="POST" action="{{ route('academic-years.destroy', $year) }}" onsubmit="return confirm('Excluir este ano letivo? Os dias do calendário gerados para ele também serão apagados.')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger sge-icon-action" type="submit" aria-label="Excluir ano letivo {{ $year->name }}" title="Excluir ano letivo">
                                        <i class="fas fa-trash-alt" aria-hidden="true"></i>
                                    </button>
                                </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">Nenhum ano letivo cadastrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            {{ $years->links() }}
        </div>
    </div>
@endsection
