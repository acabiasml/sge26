@extends('layouts.app')

@section('title', $component->name)
@section('page-title', $component->name)
@section('page-actions')
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('student-diaries.index') }}" aria-label="Voltar ao meu diário" title="Voltar ao meu diário"><i class="fas fa-arrow-left" aria-hidden="true"></i></a>
@endsection

@section('content')
    <section class="card shadow mb-4"><div class="card-body"><strong>{{ $enrollment->schoolClass?->academicYear?->school?->name }}</strong><span class="mx-2 text-muted">·</span>{{ $enrollment->schoolClass?->name }}<span class="mx-2 text-muted">·</span>{{ $academicYear->name }}</div></section>
    <div class="row">
        <div class="col-lg-6 mb-4"><section class="card shadow h-100"><div class="card-header py-3"><h2 class="h6 m-0 font-weight-bold text-primary">Notas lançadas</h2></div><div class="card-body">@forelse($periods as $period)<h3 class="h6 mt-2">{{ $period->name }}</h3><ul class="list-group list-group-flush mb-3">@forelse($assessments->where('academic_period_id', $period->id) as $assessment)@php($result = $assessment->results->first())<li class="list-group-item px-0 d-flex justify-content-between"><span>{{ $assessment->title }}</span><strong>{{ $result?->score ?? 'Ainda não lançada' }}</strong></li>@empty<li class="list-group-item px-0 text-muted">Sem avaliações lançadas.</li>@endforelse</ul>@empty<p class="mb-0">Sem períodos cadastrados.</p>@endforelse</div></section></div>
        <div class="col-lg-6 mb-4"><section class="card shadow h-100"><div class="card-header py-3"><h2 class="h6 m-0 font-weight-bold text-primary">Frequência lançada</h2></div><div class="card-body">@forelse($attendance as $record)@php($entry=$record->entries->first())<div class="sge-student-diary-entry"><strong>{{ $record->class_date->format('d/m/Y') }}</strong><span>{{ $entry?->attended_lessons ?? 0 }}/{{ $record->lesson_count }} aula(s) com presença</span></div>@empty<p class="mb-0">Nenhuma frequência lançada.</p>@endforelse</div></section></div>
    </div>
    <section class="card shadow"><div class="card-header py-3"><h2 class="h6 m-0 font-weight-bold text-primary">Conteúdos lançados</h2></div><div class="card-body">@forelse($contents as $content)<div class="sge-student-diary-entry"><strong>{{ $content->class_date->format('d/m/Y') }}</strong><span>{{ $content->content }}</span></div>@empty<p class="mb-0">Nenhum conteúdo lançado.</p>@endforelse</div></section>
@endsection
