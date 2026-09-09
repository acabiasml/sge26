@extends('layouts.app')

@section('title', __('Justificativas de ausência'))
@section('page-title', __('Justificativas de ausência'))

@section('content')
    <section class="card shadow mb-4" aria-labelledby="justification-school-title">
        <div class="card-header py-3"><h2 id="justification-school-title" class="h6 m-0 font-weight-bold text-primary">{{ __('Escola') }}</h2></div>
        <div class="card-body">
            <form method="GET" action="{{ route('attendance-justifications.index') }}" class="row align-items-end">
                <div class="col-md-6 form-group mb-md-0">
                    <label for="justification_school">{{ __('Unidade escolar') }}</label>
                    <select id="justification_school" name="school" class="form-control" onchange="this.form.submit()">
                        @foreach ($schools as $availableSchool)
                            <option value="{{ $availableSchool->id }}" @selected($school?->id === $availableSchool->id)>{{ $availableSchool->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6"><noscript><button class="btn btn-outline-primary" type="submit">{{ __('Aplicar') }}</button></noscript></div>
            </form>
        </div>
    </section>

    @if ($school)
        <nav class="sge-section-nav sge-academic-tabs mb-4" aria-label="{{ __('Áreas das justificativas') }}" role="tablist" data-section-tabs data-default-tab="{{ $errors->any() ? 'nova' : 'registradas' }}">
            <a href="#section-nova" class="sge-section-nav-item" data-academic-tab="nova" role="tab"><i class="fas fa-notes-medical"></i><span>{{ __('Nova justificativa') }}</span><small>{{ __('registrar período') }}</small></a>
            <a href="#section-registradas" class="sge-section-nav-item" data-academic-tab="registradas" role="tab"><i class="fas fa-list"></i><span>{{ __('Registradas') }}</span><small>{{ $justifications->count() }} {{ __('justificativas') }}</small></a>
        </nav>
        <div class="row">
            <div id="section-nova" class="col-12 mb-4" data-academic-panel="nova" role="tabpanel">
                <section class="card shadow" aria-labelledby="new-justification-title">
                    <div class="card-header py-3"><h2 id="new-justification-title" class="h6 m-0 font-weight-bold text-primary">{{ __('Nova justificativa') }}</h2></div>
                    <div class="card-body">
                        <p class="small text-muted">{{ __('A justificativa é vinculada à matrícula e vale em todos os componentes curriculares do estudante nessa turma.') }}</p>
                        <form method="POST" action="{{ route('attendance-justifications.store') }}">
                            @csrf
                            <div class="form-group">
                                <label for="justification_enrollment">{{ __('Estudante e turma') }}</label>
                                <select id="justification_enrollment" name="student_enrollment_id" class="form-control @error('student_enrollment_id') is-invalid @enderror" required>
                                    <option value="">{{ __('Selecione') }}</option>
                                    @foreach ($enrollments as $enrollment)
                                        <option value="{{ $enrollment->id }}" @selected((int) old('student_enrollment_id') === $enrollment->id)>{{ $enrollment->student?->full_name }} · {{ $enrollment->schoolClass?->name }} · {{ $enrollment->schoolClass?->academicYear?->name }}</option>
                                    @endforeach
                                </select>
                                @error('student_enrollment_id')<div class="invalid-feedback">{{ __($message) }}</div>@enderror
                            </div>
                            <div class="row">
                                <div class="col-md-6 form-group"><label for="justification_starts_at">{{ __('Início') }}</label><input id="justification_starts_at" name="starts_at" type="date" class="form-control @error('starts_at') is-invalid @enderror" value="{{ old('starts_at') }}" required>@error('starts_at')<div class="invalid-feedback">{{ __($message) }}</div>@enderror</div>
                                <div class="col-md-6 form-group"><label for="justification_ends_at">{{ __('Fim') }}</label><input id="justification_ends_at" name="ends_at" type="date" class="form-control @error('ends_at') is-invalid @enderror" value="{{ old('ends_at') }}" required>@error('ends_at')<div class="invalid-feedback">{{ __($message) }}</div>@enderror</div>
                            </div>
                            <div class="form-group"><label for="justification_reason">{{ __('Motivo') }}</label><textarea id="justification_reason" name="reason" class="form-control @error('reason') is-invalid @enderror" rows="3" required>{{ old('reason') }}</textarea>@error('reason')<div class="invalid-feedback">{{ __($message) }}</div>@enderror</div>
                            <button class="btn btn-primary" type="submit"><i class="fas fa-notes-medical mr-1" aria-hidden="true"></i>{{ __('Registrar justificativa') }}</button>
                        </form>
                    </div>
                </section>
            </div>
            <div id="section-registradas" class="col-12 mb-4" data-academic-panel="registradas" role="tabpanel">
                <section class="card shadow" aria-labelledby="justifications-list-title">
                    <div class="card-header py-3"><h2 id="justifications-list-title" class="h6 m-0 font-weight-bold text-primary">{{ __('Justificativas registradas') }}</h2></div>
                    <div class="card-body table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>{{ __('Estudante') }}</th><th>{{ __('Período') }}</th><th>{{ __('Motivo') }}</th><th>{{ __('Registrada por') }}</th><th class="text-right">{{ __('Ações') }}</th></tr></thead>
                            <tbody>
                                @forelse ($justifications as $justification)
                                    <tr>
                                        <td>{{ $justification->enrollment?->student?->full_name }}<span class="d-block small text-muted">{{ $justification->enrollment?->schoolClass?->name }}</span></td>
                                        <td>{{ $justification->starts_at?->format('d/m/Y') }} {{ __('a') }} {{ $justification->ends_at?->format('d/m/Y') }}</td>
                                        <td>{{ $justification->reason }}</td>
                                        <td>{{ $justification->grantedBy?->full_name ?? __('Sistema') }}</td>
                                        <td class="text-right"><form class="d-inline" method="POST" action="{{ route('attendance-justifications.destroy', $justification) }}" onsubmit="return confirm(@js(__('Remover esta justificativa?')))">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger sge-icon-action" type="submit" aria-label="{{ __('Remover justificativa de') }} {{ $justification->enrollment?->student?->full_name }}" title="{{ __('Remover justificativa') }}"><i class="fas fa-trash-alt" aria-hidden="true"></i></button></form></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5">{{ __('Nenhuma justificativa registrada para esta escola.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    @else
        <div class="alert alert-info">{{ __('Nenhuma escola disponível para a sua gestão.') }}</div>
    @endif
@endsection
