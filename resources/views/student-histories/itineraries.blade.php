@extends('layouts.app')
@section('title', __('Itinerários do estudante'))
@section('page-title', __('Itinerários do estudante'))
@section('page-actions')
<a data-navigation-return class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('student-histories.student', $person) }}" aria-label="{{ __('Voltar aos históricos') }}" title="{{ __('Voltar aos históricos') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i></a>
@endsection
@section('content')
<p class="h5">{{ $person->full_name }}</p>
<p class="text-muted">{{ __('O curso técnico selecionado integra o histórico do Médio e deixa de ter emissão separada para este estudante. A matrícula técnica e seus registros são preservados.') }}</p>
@foreach($enrollments as $enrollment)
<section class="card shadow mb-3"><div class="card-body">
<h2 class="h5">{{ $enrollment->schoolClass->name }} · {{ $enrollment->schoolClass->academicYear->name }}</h2>
<p class="text-muted">{{ $enrollment->schoolClass->academicYear->school->name }} · {{ $enrollment->statusLabel() }}</p>
<form method="post" action="{{ route('people.itineraries.update', [$person, $enrollment]) }}">@csrf @method('PUT')
<div class="row align-items-end"><div class="col-md-9 form-group mb-md-0">
<label for="itinerary-{{ $enrollment->id }}">{{ __('Curso técnico usado como itinerário') }}</label>
<select class="form-control" id="itinerary-{{ $enrollment->id }}" name="technical_itinerary_course_id">
<option value="">{{ __('Itinerário da própria matrícula, sem curso técnico separado') }}</option>
@foreach($options[$enrollment->id] as $course)
<option value="{{ $course->id }}" @selected($enrollment->technical_itinerary_course_id === $course->id)>{{ $course->name }} · {{ $course->academicYear->name }}</option>
@endforeach
</select>
</div><div class="col-md-3"><button type="submit" class="btn btn-primary">{{ __('Salvar itinerário') }}</button></div></div>
</form>
@if($options[$enrollment->id]->isEmpty())<p class="small text-muted mt-3 mb-0">{{ __('Para vincular um técnico, inclua sua oferta na matriz do Médio e registre a matrícula técnica deste estudante.') }}</p>@endif
@foreach($enrollment->courses->where('stage', 'medio') as $matrix)
<a class="d-inline-block mt-3" href="{{ route('academic-years.courses.show', [$matrix->academic_year_id, $matrix]) }}">{{ __('Ver matriz') }}: {{ $matrix->name }}</a>
@endforeach
</div></section>
@endforeach
@endsection
