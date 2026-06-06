@extends('layouts.app')

@section('title', 'Anos letivos')
@section('page-title', 'Anos letivos')

@section('page-actions')
    <a class="btn btn-sm btn-primary shadow-sm" href="{{ route('academic-years.create') }}">
        <i class="fas fa-plus fa-sm text-white-50"></i> Novo ano letivo
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
                                <a class="btn btn-sm btn-primary" href="{{ route('academic-years.show', $year) }}">Abrir</a>
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('academic-years.edit', $year) }}">Editar</a>
                                <form class="d-inline" method="POST" action="{{ route('academic-years.destroy', $year) }}" onsubmit="return confirm('Excluir este ano letivo? Os dias do calendário gerados para ele também serão apagados.')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Excluir</button>
                                </form>
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
