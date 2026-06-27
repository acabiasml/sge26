@extends('layouts.app')

@section('title', 'Meu diário')
@section('page-title', 'Meu diário')

@section('content')
    <section class="card shadow" aria-labelledby="student-diary-title">
        <div class="card-header py-3">
            <h2 id="student-diary-title" class="h6 m-0 font-weight-bold text-primary">Lançamentos acadêmicos</h2>
        </div>
        <div class="card-body">
            @forelse($enrollments as $enrollment)
                <article class="mb-4">
                    <h3 class="h6 font-weight-bold">{{ $enrollment->schoolClass?->academicYear?->school?->name }} · {{ $enrollment->schoolClass?->name }}</h3>
                    <div class="d-flex align-items-center justify-content-between flex-wrap">
                        <p class="small text-muted mb-2">{{ $enrollment->schoolClass?->academicYear?->name }}</p>
                        <div class="btn-group btn-group-sm mb-2">
                            <a class="btn btn-outline-primary" href="{{ route('student-diaries.schedule', $enrollment) }}">
                                <i class="fas fa-clock mr-1" aria-hidden="true"></i>Meu horário
                            </a>
                            <a class="btn btn-outline-primary" href="{{ route('student-diaries.schedule-pdf', $enrollment) }}">
                                <i class="fas fa-file-pdf mr-1" aria-hidden="true"></i>PDF
                            </a>
                        </div>
                    </div>
                    <div class="row">
                        @foreach($enrollment->courses as $course)
                            @foreach($course->components->sortBy('name') as $component)
                                <div class="col-md-6 mb-2">
                                    <a class="sge-student-diary-link" href="{{ route('student-diaries.show', [$enrollment, $component]) }}">
                                        <i class="fas fa-book-open" aria-hidden="true"></i>
                                        <span>
                                            <strong>{{ $component->name }}</strong>
                                            <small>{{ $course->name }} · {{ $component->area?->name ?? 'Área não definida' }}</small>
                                        </span>
                                        <i class="fas fa-chevron-right" aria-hidden="true"></i>
                                    </a>
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </article>
            @empty
                <p class="mb-0">Nenhuma matrícula encontrada.</p>
            @endforelse
        </div>
    </section>
@endsection
