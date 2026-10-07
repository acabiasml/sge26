@extends('layouts.app')
@section('title', __('Ficha da escola de origem'))
@section('page-title', __('Ficha da escola de origem'))
@section('page-actions')
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('enrollments.report-card.show', $enrollment) }}#convalidation-title" title="{{ __('Voltar ao boletim') }}" aria-label="{{ __('Voltar ao boletim') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i></a>
@endsection
@section('content')
@php
    $indexed = $records->keyBy(fn ($record) => $record->academic_period_id.'_'.$record->curriculum_component_id);
    $latest = $sourceRecords->sortByDesc('updated_at')->first();
    $readOnly = $academicYear->isReadOnly();
@endphp
<div class="card shadow mb-4"><div class="card-body">
    <h2 class="h5">{{ $enrollment->student?->full_name }}</h2>
    <p class="mb-2">{{ $enrollment->schoolClass->name }} · {{ $academicYear->referenceYearsLabel() }}</p>
    <p class="mb-0">{{ __('Transcreva a ficha recebida: informe a escola uma vez e preencha somente os períodos e componentes que constam nela. Os diários dos professores não são alterados.') }}</p>
</div></div>
@if($records->isNotEmpty())
<nav class="mb-3" aria-label="{{ __('Fichas já cadastradas') }}">
    <strong>{{ __('Fichas já cadastradas') }}:</strong>
    @foreach($records->pluck('source_school')->unique() as $source)
        <a class="btn btn-sm btn-outline-primary m-1" href="{{ route('enrollments.origin-sheet', ['enrollment' => $enrollment, 'source_school' => $source ?? '']) }}" @if((string)$source === $sourceSchool) aria-current="page" @endif>{{ $source ?: __('Escola não informada') }}</a>
    @endforeach
    <a class="btn btn-sm btn-outline-secondary m-1" href="{{ route('enrollments.origin-sheet', ['enrollment' => $enrollment, 'new' => 1]) }}">{{ __('Nova ficha') }}</a>
</nav>
@endif
@if($errors->any())
<div class="alert alert-danger" role="alert"><strong>{{ __('Revise os campos indicados. Nenhum resultado desta tentativa foi salvo.') }}</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
@if($readOnly)<div class="alert alert-info">{{ __('Ano letivo fechado: disponível apenas para consulta.') }}</div>@endif
<form method="POST" action="{{ route('enrollments.origin-sheet.store', $enrollment) }}">
    @csrf
    <input type="hidden" name="previous_source_school" value="{{ old('previous_source_school', $sourceSchool) }}">
    <fieldset @disabled($readOnly)>
        <legend class="h5">{{ __('Dados da ficha recebida') }}</legend>
        <div class="card shadow mb-4"><div class="card-body"><div class="form-row">
            <div class="form-group col-md-8"><label for="source-school">{{ __('Escola de origem') }}</label><input id="source-school" name="source_school" class="form-control" required maxlength="255" value="{{ old('source_school', $sourceSchool) }}"></div>
            <div class="form-group col-md-4"><label for="source-date">{{ __('Data do registro') }}</label><input id="source-date" type="date" name="convalidated_at" class="form-control" required value="{{ old('convalidated_at', $latest?->convalidated_at?->toDateString() ?? now('America/Cuiaba')->toDateString()) }}"></div>
            <div class="form-group col-12 mb-0"><label for="source-notes">{{ __('Observações da ficha') }}</label><textarea id="source-notes" name="notes" rows="2" maxlength="5000" class="form-control">{{ old('notes', $latest?->notes) }}</textarea></div>
        </div></div></div>
        <p>{{ __('Média: de 0 a 10. Aulas e faltas: quantidades recebidas da escola de origem. Campos vazios não apagam resultados já salvos.') }}</p>
        @foreach($periods as $period)
        <details class="card shadow mb-3" open>
            <summary class="card-header font-weight-bold">{{ $period->name }}</summary>
            <div class="card-body table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <caption class="sr-only">{{ $period->name }} — {{ __('Resultados da escola de origem') }}</caption>
                    <thead><tr><th scope="col">{{ __('Componente') }}</th><th scope="col">{{ __('Média') }}</th><th scope="col">{{ __('Aulas') }}</th><th scope="col">{{ __('Faltas') }}</th><th scope="col">{{ __('Faltas justificadas') }}</th></tr></thead>
                    <tbody>
                    @foreach($components as $component)
                        @php
                            $key = $period->id.'_'.$component->id;
                            $record = $indexed->get($key);
                            $otherSource = $record && ($newSheet || (string)$record->source_school !== $sourceSchool);
                        @endphp
                        <tr>
                            <th scope="row" style="min-width:180px">{{ $component->name }}<small class="d-block text-muted">{{ $component->course?->name }}</small></th>
                            @if($otherSource)
                                <td colspan="4">{{ __('Resultado cadastrado em outra ficha') }}: <a href="{{ route('enrollments.origin-sheet', ['enrollment' => $enrollment, 'source_school' => $record->source_school ?? '']) }}">{{ $record->source_school ?: __('Escola não informada') }}</a></td>
                            @else
                                @foreach(['score' => __('Média'), 'attendance_lessons' => __('Aulas'), 'attendance_absences' => __('Faltas'), 'attendance_justified_absences' => __('Faltas justificadas')] as $field => $label)
                                    @php($errorKey = 'rows.'.$key.'.'.$field)
                                    <td style="min-width:100px">
                                        <label class="sr-only" for="field-{{ $key }}-{{ $field }}">{{ $period->name }} — {{ $component->name }} — {{ $label }}</label>
                                        <input id="field-{{ $key }}-{{ $field }}" name="rows[{{ $key }}][{{ $field }}]" class="form-control form-control-sm @error($errorKey) is-invalid @enderror" value="{{ old($errorKey, $record?->{$field}) }}"
                                            @if($field === 'score') inputmode="decimal" data-mask="decimal" @else type="number" min="{{ $field === 'attendance_lessons' ? 1 : 0 }}" max="999" @endif
                                            @error($errorKey) aria-invalid="true" aria-describedby="error-{{ $key }}-{{ $field }}" @enderror>
                                        @error($errorKey)<span class="invalid-feedback" id="error-{{ $key }}-{{ $field }}">{{ $message }}</span>@enderror
                                    </td>
                                @endforeach
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </details>
        @endforeach
        <input type="hidden" name="sheet_complete" value="1">
        <button class="btn btn-primary mb-4" type="submit"><i class="fas fa-save mr-1" aria-hidden="true"></i>{{ __('Salvar ficha completa') }}</button>
    </fieldset>
</form>
@endsection
