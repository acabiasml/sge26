@extends('layouts.app')

@section('title', __('Editar histórico'))
@section('page-title', __('Editar histórico'))

@section('page-actions')
    <a target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('people.histories.pdf', [$person, $history]) }}" aria-label="{{ __('Emitir histórico em PDF') }}" title="{{ __('Histórico em PDF') }}">
        <i class="fas fa-file-pdf" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('people.histories.show', [$person, $history]) }}" aria-label="{{ __('Voltar ao histórico') }}" title="{{ __('Voltar ao histórico') }}">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    <div class="sge-history-editor-context mb-4">
        <div><span class="sge-page-kicker">{{ $history->stage ?: __('Histórico escolar') }}</span><h2>{{ $person->full_name }}</h2></div>
        <p>{{ __('Confira os anos cursados e abra as notas quando precisar.') }}</p>
    </div>
    @if($history->is_unified)
        <p class="text-muted mb-4"><i class="fas fa-lock mr-1" aria-hidden="true"></i>{{ __('Edite aqui os anos de outras escolas. Os anos das nossas escolas são calculados pelo sistema e aparecem para consulta ao final.') }}</p>
    @endif
    <form method="POST" action="{{ route('people.histories.update', [$person, $history]) }}">
        @csrf
        @method('PUT')
        @include('student-histories._form', ['curriculumOnly' => true, 'manualOnly' => $history->is_unified])
    </form>
    @if($history->years->contains(fn ($year) => $year->source !== 'system'))
        <details class="card shadow sge-panel-card mb-4" aria-labelledby="manual-years-title">
            <summary class="sge-panel-header sge-history-section-summary"><div>
                <h2 id="manual-years-title">{{ __('Remover um ano cadastrado') }}</h2>
                <p>{{ __('A remoção elimina este ano e suas notas transcritas. Os demais anos são preservados.') }}</p>
            </div><i class="fas fa-chevron-down sge-history-year-chevron" aria-hidden="true"></i></summary>
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
        </details>
    @endif
    @if($history->is_unified && $history->years->contains('source', 'system'))
        <details class="card shadow sge-panel-card mb-4" aria-labelledby="system-years-title">
            <summary class="sge-panel-header sge-history-section-summary">
                <div>
                    <h2 id="system-years-title">{{ __('Séries registradas no sistema') }}</h2>
                    <p>{{ __('Dados calculados automaticamente. A edição deve ser feita na matrícula e nos diários correspondentes.') }}</p>
                </div>
                <i class="fas fa-chevron-down sge-history-year-chevron" aria-hidden="true"></i>
            </summary>
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
        </details>
    @endif
@endsection
