@extends('layouts.app')

@section('title', 'Eventos')
@section('page-title', 'Eventos do calendário')

@section('content')
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h2 class="h6 m-0 font-weight-bold text-primary">Novo evento</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('calendar-events.store') }}">
                @csrf
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label for="school_id">Escola</label>
                        <select id="school_id" name="school_id" class="form-control" required>
                            <option value="">Selecione</option>
                            @foreach ($schools as $school)
                                <option value="{{ $school->id }}">{{ $school->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="academic_year_id">Ano letivo</label>
                        <select id="academic_year_id" name="academic_year_id" class="form-control">
                            <option value="">Sem vínculo direto</option>
                            @foreach ($schools as $school)
                                @foreach ($school->academicYears as $year)
                                    <option value="{{ $year->id }}">{{ $school->name }} - {{ $year->name }} {{ $year->reference_year }}</option>
                                @endforeach
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="category">Categoria</label>
                        <input id="category" name="category" class="form-control" value="{{ old('category', 'evento') }}" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-5 form-group">
                        <label for="title">Título</label>
                        <input id="title" name="title" class="form-control" required>
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="starts_at">Início</label>
                        <input id="starts_at" name="starts_at" type="datetime-local" class="form-control" required>
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="ends_at">Fim</label>
                        <input id="ends_at" name="ends_at" type="datetime-local" class="form-control">
                    </div>
                    <div class="col-md-1 form-group d-flex align-items-end">
                        <div class="custom-control custom-checkbox mb-2">
                            <input class="custom-control-input" id="highlight" name="highlight" type="checkbox" value="1">
                            <label class="custom-control-label" for="highlight">Destaque</label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Descrição</label>
                    <textarea id="description" name="description" class="form-control" rows="2"></textarea>
                </div>

                <button class="btn btn-primary" type="submit">Salvar evento</button>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h2 class="h6 m-0 font-weight-bold text-primary">Eventos cadastrados</h2>
        </div>
        <div class="card-body table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Evento</th>
                        <th>Escola</th>
                        <th>Quando</th>
                        <th>Categoria</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($events as $event)
                        <tr>
                            <td>
                                {{ $event->title }}
                                @if ($event->highlight)
                                    <span class="badge badge-warning ml-1">Destaque</span>
                                @endif
                            </td>
                            <td>{{ $event->school?->name }}</td>
                            <td>{{ $event->starts_at?->format('d/m/Y H:i') }}</td>
                            <td>{{ $event->category }}</td>
                            <td>
                                <form method="POST" action="{{ route('calendar-events.destroy', $event) }}" onsubmit="return confirm('Remover este evento?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Remover</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Nenhum evento cadastrado.</td></tr>
                    @endforelse
                </tbody>
            </table>

            {{ $events->links() }}
        </div>
    </div>
@endsection
