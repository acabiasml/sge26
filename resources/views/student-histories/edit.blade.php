@extends('layouts.app')

@section('title', $history->is_unified ? __('Séries cursadas em outras escolas') : __('Matriz curricular do histórico'))
@section('page-title', $history->is_unified ? __('Séries cursadas em outras escolas') : __('Matriz curricular do histórico'))

@section('page-actions')
    <a target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('people.histories.pdf', [$person, $history]) }}" aria-label="{{ __('Emitir histórico em PDF') }}" title="{{ __('Histórico em PDF') }}">
        <i class="fas fa-file-pdf" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('people.histories.show', [$person, $history]) }}" aria-label="{{ __('Voltar ao histórico') }}" title="{{ __('Voltar ao histórico') }}">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    @if($history->is_unified)
        <div class="alert alert-info">
            <strong>{{ __('Os dados das nossas escolas são automáticos e não podem ser alterados aqui.') }}</strong>
            {{ __('Esta tela cadastra somente séries cursadas em outras instituições. Matrículas, notas, frequência, faltas, resultados e cargas horárias internas vêm diretamente do sistema.') }}
        </div>
    @endif
    <form method="POST" action="{{ route('people.histories.update', [$person, $history]) }}">
        @csrf
        @method('PUT')
        @include('student-histories._form', ['curriculumOnly' => true, 'manualOnly' => $history->is_unified])
    </form>
    @if($history->years->contains(fn ($year) => $year->source !== 'system'))
        <section class="card shadow sge-panel-card mb-4" aria-labelledby="manual-years-title">
            <div class="sge-panel-header"><div>
                <h2 id="manual-years-title">{{ __('Remover um ano cadastrado') }}</h2>
                <p>{{ __('A remoção elimina este ano e suas notas transcritas. Os demais anos são preservados.') }}</p>
            </div></div>
            <div class="card-body"><div class="table-responsive"><table class="table table-sm table-bordered" data-sge-client-pagination="10">
                <thead><tr><th>{{ __('Ano civil') }}</th><th>{{ __('Série/Fase') }}</th><th>{{ __('CH total') }}</th><th>{{ __('Ações') }}</th></tr></thead>
                <tbody>@foreach($history->years->filter(fn ($year) => $year->source !== 'system') as $year)
                    <tr><td>{{ $year->year }}</td><td>{{ $year->grade_phase }}</td>
                        <td>{{ number_format((float) $year->workload_hours, 2, ',', '.') }} h</td>
                        <td><form method="post" action="{{ route('people.histories.years.destroy', [$person, $history, $year]) }}" data-confirm-message="{{ __('Remover este ano e suas notas transcritas?') }}">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="fas fa-trash mr-1" aria-hidden="true"></i>{{ __('Remover ano') }}</button>
                        </form></td>
                    </tr>
                @endforeach</tbody>
            </table></div></div>
        </section>
    @endif
    @if($history->is_unified && $history->years->contains('source', 'system'))
        <section class="card shadow sge-panel-card mb-4" aria-labelledby="system-years-title">
            <div class="sge-panel-header">
                <div>
                    <h2 id="system-years-title">{{ __('Séries registradas no sistema') }}</h2>
                    <p>{{ __('Dados calculados automaticamente. A edição deve ser feita na matrícula e nos diários correspondentes.') }}</p>
                </div>
            </div>
            <div class="card-body">
                @foreach($history->years->where('source', 'system')->sortBy('position') as $year)
                    <h3 class="h5">{{ $year->grade_phase }} · {{ $year->year }} · {{ $year->school_name }}</h3>
                    <p>{{ __('Resultado:') }} {{ $year->final_result ?: '-' }} · {{ __('Frequência:') }} {{ $year->attendance_label ?: '-' }}</p>
                    @if($year->transcript_mode === 'detailed')
                        <div class="table-responsive mb-3">
                            <table class="table table-bordered table-sm" data-sge-client-pagination="10">
                                <thead><tr><th scope="col">{{ __('Componente') }}</th><th scope="col">{{ __('Nota calculada') }}</th><th scope="col">{{ __('Faltas') }}</th></tr></thead>
                                <tbody>
                                    @forelse($year->records as $record)
                                        <tr><td>{{ $record->component?->name }}</td><td>{{ $record->score_label ?: '-' }}</td><td>{{ $record->absences ?? '-' }}</td></tr>
                                    @empty
                                        <tr><td colspan="3">{{ __('Nenhum resultado calculado para esta série.') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endforeach
            </div>
        </section>
    @endif
@endsection
