@extends('layouts.app')

@section('title', $component->name)
@section('page-title', $component->name)

@section('page-actions')
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('student-diaries.index') }}" aria-label="{{ __('Voltar ao meu diário') }}" title="{{ __('Voltar ao meu diário') }}">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
@endsection

@php
    $studentConceptLabel = function ($score, $period) use ($academicYear): string {
        if ($score === null || $score === '') {
            return __('Ainda não lançado');
        }

        $concept = $academicYear->school?->conceptForScore((float) $score, $period?->ends_at ?? $period?->starts_at);

        return $concept?->shortLabel() ?? __('Conceito não definido');
    };
@endphp

@section('content')
    <section class="card shadow mb-4">
        <div class="card-body">
            <strong>{{ $enrollment->schoolClass?->academicYear?->school?->name }}</strong>
            <span class="mx-2 text-muted">·</span>
            {{ $enrollment->schoolClass?->name }}
            <span class="mx-2 text-muted">·</span>
            {{ $academicYear->name }}
        </div>
    </section>

    <nav class="sge-section-nav sge-academic-tabs mb-4" aria-label="{{ __('Áreas do diário do estudante') }}" role="tablist" data-section-tabs>
        <a href="#section-conceitos" class="sge-section-nav-item" data-academic-tab="conceitos" role="tab"><i class="fas fa-star-half-alt"></i><span>{{ __('Conceitos') }}</span><small>{{ __('notas e comportamento') }}</small></a>
        <a href="#section-frequencia" class="sge-section-nav-item" data-academic-tab="frequencia" role="tab"><i class="fas fa-clipboard-check"></i><span>{{ __('Frequência') }}</span><small>{{ $attendance->count() }} {{ __('chamadas') }}</small></a>
        <a href="#section-conteudos" class="sge-section-nav-item" data-academic-tab="conteudos" role="tab"><i class="fas fa-book"></i><span>{{ __('Conteúdos') }}</span><small>{{ $contents->count() }} {{ __('registros') }}</small></a>
    </nav>

    <div class="row">
        <div id="section-conceitos" class="col-12 mb-4" data-academic-panel="conceitos" role="tabpanel">
            <section class="card shadow">
                <div class="card-header py-3">
                    <h2 class="h6 m-0 font-weight-bold text-primary">{{ __('Conceitos lançados') }}</h2>
                </div>
                <div class="card-body">
                    @forelse($periods as $period)
                        <h3 class="h6 mt-2">{{ $period->name }}</h3>
                        <ul class="list-group list-group-flush mb-3">
                            @php($behaviorGrade = $behaviorGrades->get($period->id))
                            <li class="list-group-item px-0 d-flex justify-content-between">
                                <span>{{ __('Comportamento') }}</span>
                                <strong>{{ $studentConceptLabel($behaviorGrade?->score, $period) }}</strong>
                            </li>
                            @forelse($assessments->where('academic_period_id', $period->id) as $assessment)
                                @php($result = $assessment->results->first())
                                <li class="list-group-item px-0 d-flex justify-content-between">
                                    <span>{{ $assessment->title }}</span>
                                    <strong>{{ $studentConceptLabel($result?->score, $period) }}</strong>
                                </li>
                            @empty
                                <li class="list-group-item px-0 text-muted">{{ __('Sem avaliações lançadas.') }}</li>
                            @endforelse
                        </ul>
                    @empty
                        <p class="mb-0">{{ __('Sem períodos cadastrados.') }}</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div id="section-frequencia" class="col-12 mb-4" data-academic-panel="frequencia" role="tabpanel">
            <section class="card shadow">
                <div class="card-header py-3">
                    <h2 class="h6 m-0 font-weight-bold text-primary">{{ __('Frequência lançada') }}</h2>
                </div>
                <div class="card-body">
                    @forelse($attendance as $record)
                        @php($entry = $record->entries->first())
                        <div class="sge-student-diary-entry">
                            <strong>{{ $record->class_date->format('d/m/Y') }}</strong>
                            <span>{{ $entry?->attended_lessons ?? 0 }}/{{ $record->lesson_count }} {{ __('aula(s) com presença') }}</span>
                        </div>
                    @empty
                        <p class="mb-0">{{ __('Nenhuma frequência lançada.') }}</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>

    <section id="section-conteudos" class="card shadow" data-academic-panel="conteudos" role="tabpanel">
        <div class="card-header py-3">
            <h2 class="h6 m-0 font-weight-bold text-primary">{{ __('Conteúdos lançados') }}</h2>
        </div>
        <div class="card-body">
            @forelse($contents as $content)
                <div class="sge-student-diary-entry">
                    <strong>{{ $content->class_date->format('d/m/Y') }}</strong>
                    <span>{{ $content->content }}</span>
                </div>
            @empty
                <p class="mb-0">{{ __('Nenhum conteúdo lançado.') }}</p>
            @endforelse
        </div>
    </section>
@endsection
