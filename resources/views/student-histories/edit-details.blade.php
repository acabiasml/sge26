@extends('layouts.app')

@section('title', __('Dados gerais do histórico'))
@section('page-title', __('Dados gerais do histórico'))

@section('page-actions')
    <a data-navigation-return class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('people.histories.show', [$person, $history]) }}" aria-label="{{ __('Voltar ao histórico escolar') }}" title="{{ __('Voltar ao histórico') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i></a>
@endsection

@section('content')
<div class="row justify-content-center"><div class="col-xl-8"><div class="card shadow sge-panel-card mb-4">
    <div class="sge-panel-header"><div><h2>{{ $history->stage }}</h2><p>{{ __('Identificação, fundamento legal e emissão do documento.') }}</p></div></div>
    <div class="card-body"><form method="post" action="{{ route('people.histories.details.update', [$person, $history]) }}">@csrf @method('PUT')
        <div class="form-group"><label for="title">{{ __('Título') }}</label><input id="title" name="title" class="form-control" value="{{ old('title', $history->title) }}" required></div>
        <div class="form-group"><label for="school_id">{{ __('Escola emissora') }}</label><select id="school_id" name="school_id" class="form-control" required>@foreach($schools as $school)<option value="{{ $school->id }}" @selected((string) old('school_id', $history->school_id) === (string) $school->id)>{{ $school->name }}</option>@endforeach</select></div>
        <div class="form-group"><label for="legal_basis">{{ __('Fundamento legal') }}</label><textarea id="legal_basis" name="legal_basis" class="form-control" rows="3">{{ old('legal_basis', $history->legal_basis) }}</textarea></div>
        <div class="form-group"><label for="notes">{{ __('Observações gerais') }}</label><textarea id="notes" name="notes" class="form-control" rows="5">{{ old('notes', $history->notes) }}</textarea></div>
        <div class="form-row"><div class="form-group col-md-7"><label for="issued_place">{{ __('Local de emissão') }}</label><input id="issued_place" name="issued_place" class="form-control" value="{{ old('issued_place', $history->issued_place) }}"></div><div class="form-group col-md-5"><label for="issued_date">{{ __('Data') }}</label><input id="issued_date" type="date" name="issued_date" class="form-control" value="{{ old('issued_date', $history->issued_date?->toDateString()) }}"></div></div>
        @if($history->education_stage === \App\Models\AcademicCourse::STAGE_HIGH_SCHOOL)
            <fieldset class="border rounded p-3 mb-3">
                <legend class="h6 w-auto">{{ __('Ingresso e conclusão do Ensino Médio') }}</legend>
                <p class="small text-muted">{{ __('Use a matrícula inicial da etapa ou o documento de origem conferido pela secretaria, inclusive em caso de transferência. Informe o semestre quando se aplicar e preencha a data de conclusão somente após conferi-la.') }}</p>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="course_entered_on">{{ __('Data de ingresso') }}</label>
                        <input id="course_entered_on" name="course_entered_on" type="date" class="form-control" value="{{ old('course_entered_on', $history->course_entered_on?->toDateString()) }}">
                    </div>
                    <div class="form-group col-md-8">
                        <label for="course_entry_method">{{ __('Forma de ingresso') }}</label>
                        <input id="course_entry_method" name="course_entry_method" class="form-control" maxlength="120" value="{{ old('course_entry_method', $history->course_entry_method) }}">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="course_entry_year">{{ __('Ano de ingresso') }}</label>
                        <input id="course_entry_year" name="course_entry_year" type="number" min="1900" max="{{ now('America/Cuiaba')->year }}" class="form-control" value="{{ old('course_entry_year', $history->course_entry_year) }}">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="course_entry_semester">{{ __('Semestre de ingresso') }}</label>
                        <select id="course_entry_semester" name="course_entry_semester" class="form-control">
                            <option value="">{{ __('Não se aplica') }}</option>
                            <option value="1" @selected((int) old('course_entry_semester', $history->course_entry_semester) === 1)>{{ __('1º semestre') }}</option>
                            <option value="2" @selected((int) old('course_entry_semester', $history->course_entry_semester) === 2)>{{ __('2º semestre') }}</option>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="course_completed_on">{{ __('Data de conclusão') }}</label>
                        <input id="course_completed_on" name="course_completed_on" type="date" class="form-control" value="{{ old('course_completed_on', $history->course_completed_on?->toDateString()) }}">
                    </div>
                </div>
                <label for="course_complementary_activities">{{ __('Atividades complementares e respectivas cargas horárias, quando houver') }}</label>
                <textarea id="course_complementary_activities" name="course_complementary_activities" class="form-control" maxlength="2000" rows="2">{{ old('course_complementary_activities', $history->course_complementary_activities) }}</textarea>
            </fieldset>
        @endif
        <div class="custom-control custom-switch mb-4"><input id="active" name="active" value="1" type="checkbox" class="custom-control-input" @checked(old('active', $history->active))><label class="custom-control-label" for="active">{{ __('Histórico ativo') }}</label></div>
        <button class="btn btn-primary" type="submit"><i class="fas fa-save mr-1"></i>{{ __('Salvar dados gerais') }}</button>
    </form></div>
</div></div></div>
@endsection
