@extends('layouts.app')

@section('title', 'Recados')
@section('page-title', 'Recados')

@section('content')
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h2 class="h6 m-0 font-weight-bold text-primary">Novo recado</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('announcements.store') }}">
                @csrf
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label for="school_id">Destino</label>
                        <select id="school_id" name="school_id" class="form-control" @unless(auth()->user()->isAdministrator()) required @endunless>
                            @if (auth()->user()->isAdministrator())
                                <option value="">Global - todas as escolas</option>
                            @else
                                <option value="">Selecione</option>
                            @endif
                            @foreach ($schools as $school)
                                <option value="{{ $school->id }}">{{ $school->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="starts_at">Exibir a partir de</label>
                        <input id="starts_at" name="starts_at" type="datetime-local" class="form-control" value="{{ now()->format('Y-m-d\TH:i') }}" required>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="ends_at">Exibir até</label>
                        <input id="ends_at" name="ends_at" type="datetime-local" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label for="title">Título</label>
                    <input id="title" name="title" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="body">Mensagem</label>
                    <textarea id="body" name="body" class="form-control" rows="4" required></textarea>
                </div>

                <div class="custom-control custom-checkbox mb-2">
                    <input class="custom-control-input" id="highlight" name="highlight" type="checkbox" value="1">
                    <label class="custom-control-label" for="highlight">Destacar na tela inicial</label>
                </div>

                <div class="custom-control custom-checkbox mb-3">
                    <input class="custom-control-input" id="active" name="active" type="checkbox" value="1" checked>
                    <label class="custom-control-label" for="active">Recado ativo</label>
                </div>

                <button class="btn btn-primary" type="submit">Salvar recado</button>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h2 class="h6 m-0 font-weight-bold text-primary">Recados cadastrados</h2>
        </div>
        <div class="card-body table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Recado</th>
                        <th>Destino</th>
                        <th>Exibição</th>
                        <th>Situação</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($announcements as $announcement)
                        <tr>
                            <td>
                                {{ $announcement->title }}
                                @if ($announcement->highlight)
                                    <span class="badge badge-warning ml-1">Destaque</span>
                                @endif
                            </td>
                            <td>{{ $announcement->school?->name ?? 'Global' }}</td>
                            <td>
                                {{ $announcement->starts_at?->format('d/m/Y H:i') }}
                                até
                                {{ $announcement->ends_at?->format('d/m/Y H:i') ?? 'indeterminado' }}
                            </td>
                            <td>{{ $announcement->active ? 'Ativo' : 'Inativo' }}</td>
                            <td>
                                <form method="POST" action="{{ route('announcements.destroy', $announcement) }}" onsubmit="return confirm('Remover este recado?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Remover</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Nenhum recado cadastrado.</td></tr>
                    @endforelse
                </tbody>
            </table>

            {{ $announcements->links() }}
        </div>
    </div>
@endsection
