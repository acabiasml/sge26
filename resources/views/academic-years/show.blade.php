@extends('layouts.app')

@section('title', $academicYear->name)
@section('page-title', $academicYear->name)

@section('page-actions')
    <a class="btn btn-sm btn-outline-primary shadow-sm" href="{{ route('academic-years.calendar-pdf', $academicYear) }}">
        <i class="fas fa-file-pdf fa-sm"></i> Calendário oficial
    </a>
    <a class="btn btn-sm btn-outline-primary shadow-sm" href="{{ route('academic-years.edit', $academicYear) }}">Editar</a>
    <form class="d-inline" method="POST" action="{{ route('academic-years.destroy', $academicYear) }}" onsubmit="return confirm('Excluir este ano letivo? Os dias do calendário gerados para ele também serão apagados.')">
        @csrf
        @method('DELETE')
        <button class="btn btn-sm btn-outline-danger shadow-sm" type="submit">Excluir</button>
    </form>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h2 class="h6 m-0 font-weight-bold text-primary">Resumo</h2>
                </div>
                <div class="card-body">
                    <dl class="mb-0">
                        <dt>Escola</dt>
                        <dd>{{ $academicYear->school?->name }}</dd>
                        <dt>Ano de referência</dt>
                        <dd>{{ $academicYear->reference_year }}</dd>
                        <dt>Período</dt>
                        <dd>{{ $academicYear->starts_at?->format('d/m/Y') }} a {{ $academicYear->ends_at?->format('d/m/Y') }}</dd>
                        <dt>Aprovação</dt>
                        <dd>{{ $academicYear->approved_at?->format('d/m/Y') ?? '-' }}</dd>
                        <dt>Hora-aula</dt>
                        <dd>{{ $academicYear->class_hour_minutes }} minutos</dd>
                        <dt>Dias letivos</dt>
                        <dd>
                            @php($schoolDays = $academicYear->schoolDayCount())
                            <span class="badge badge-{{ $schoolDays >= $academicYear->minimum_school_days ? 'success' : 'warning' }}">
                                {{ $schoolDays }} / mínimo {{ $academicYear->minimum_school_days }}
                            </span>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-8 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h2 class="h6 m-0 font-weight-bold text-primary">Novo período avaliativo</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('academic-years.periods.store', $academicYear) }}">
                        @csrf
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label for="period_name">Nome</label>
                                <input id="period_name" name="name" class="form-control @error('name') is-invalid @enderror" placeholder="1º Bimestre" required>
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-2 form-group">
                                <label for="position">Ordem</label>
                                <input id="position" name="position" type="number" min="1" class="form-control" value="{{ $academicYear->periods->count() + 1 }}" required>
                            </div>
                            <div class="col-md-3 form-group">
                                <label for="period_starts_at">Início</label>
                                <input id="period_starts_at" name="starts_at" type="date" class="form-control @error('starts_at') is-invalid @enderror" required>
                                @error('starts_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3 form-group">
                                <label for="period_ends_at">Fim</label>
                                <input id="period_ends_at" name="ends_at" type="date" class="form-control @error('ends_at') is-invalid @enderror" required>
                                @error('ends_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="period_notes">Observações</label>
                            <input id="period_notes" name="notes" class="form-control">
                        </div>
                        <button class="btn btn-primary" type="submit">Adicionar período</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-5 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h2 class="h6 m-0 font-weight-bold text-primary">Períodos cadastrados</h2>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-sm">
                        <tbody>
                            @forelse ($academicYear->periods->sortBy('position') as $period)
                                <tr>
                                    <td>
                                        <strong>{{ $period->name }}</strong>
                                        <div class="small text-gray-600">{{ $period->starts_at?->format('d/m/Y') }} a {{ $period->ends_at?->format('d/m/Y') }}</div>
                                    </td>
                                    <td class="text-right">
                                        <form method="POST" action="{{ route('academic-years.periods.destroy', [$academicYear, $period]) }}" onsubmit="return confirm('Remover este período?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Remover</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td>Nenhum período cadastrado.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-7 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h2 class="h6 m-0 font-weight-bold text-primary">Registrar dia do calendário</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('academic-years.days.store', $academicYear) }}">
                        @csrf
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label for="date">Data</label>
                                <input id="date" name="date" type="date" class="form-control @error('date') is-invalid @enderror" required>
                                @error('date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="type">Tipo</label>
                                <select id="type" name="type" class="form-control @error('type') is-invalid @enderror" required>
                                    @foreach (\App\Models\CalendarDay::TYPE_LABELS as $value => $label)
                                        <option value="{{ $value }}">{{ \App\Models\CalendarDay::labelWithPrintCode($value) }}</option>
                                    @endforeach
                                </select>
                                @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4 form-group d-flex align-items-end">
                                <div class="custom-control custom-checkbox mb-2">
                                    <input class="custom-control-input" id="counts_as_school_day" name="counts_as_school_day" type="checkbox" value="1">
                                    <label class="custom-control-label" for="counts_as_school_day">Conta como letivo</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="title">Título</label>
                            <input id="title" name="title" class="form-control" placeholder="Feriado municipal, conselho de classe...">
                        </div>
                        <div class="form-group">
                            <label for="description">Observações</label>
                            <textarea id="description" name="description" class="form-control" rows="2"></textarea>
                        </div>
                        <button class="btn btn-primary" type="submit">Salvar dia</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h2 class="h6 m-0 font-weight-bold text-primary">Dias cadastrados</h2>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th>Conta como letivo</th>
                        <th>Título</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($academicYear->days->sortBy('date') as $day)
                        <tr>
                            <td>{{ $day->date?->format('d/m/Y') }}</td>
                            <td>{{ $day->label() }}</td>
                            <td>{{ $day->counts_as_school_day ? 'Sim' : 'Não' }}</td>
                            <td>{{ $day->title ?: '-' }}</td>
                            <td>
                                <form method="POST" action="{{ route('academic-years.days.destroy', [$academicYear, $day]) }}" onsubmit="return confirm('Remover este dia do calendário?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Remover</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Nenhum dia cadastrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
