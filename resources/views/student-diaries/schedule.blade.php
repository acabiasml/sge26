@extends('layouts.app')

@section('title', 'Meu horário')
@section('page-title', 'Meu horário')

@section('page-actions')
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('student-diaries.schedule-pdf', $enrollment) }}" aria-label="Imprimir meu horário" title="Imprimir meu horário">
        <i class="fas fa-file-pdf" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('student-diaries.index') }}" aria-label="Voltar ao meu diário" title="Voltar ao meu diário">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    <section class="card shadow mb-4">
        <div class="card-body">
            <strong>{{ $enrollment->student?->full_name }}</strong>
            <span class="mx-2 text-muted">·</span>{{ $enrollment->schoolClass?->name }}
            <span class="mx-2 text-muted">·</span>{{ $enrollment->schoolClass?->academicYear?->name }}
        </div>
    </section>

    <section class="card shadow" aria-labelledby="student-schedule-title">
        <div class="card-header py-3">
            <h2 id="student-schedule-title" class="h6 m-0 font-weight-bold text-primary">Horário semanal</h2>
        </div>
        <div class="card-body">
            @php($slots = $enrollment->schoolClass->schedules->flatMap->slots->where('type', \App\Models\SchoolClassScheduleSlot::TYPE_CLASS)->sortBy([['weekday', 'asc'], ['starts_at', 'asc']]))
            @if($slots->isEmpty())
                <p class="mb-0">Nenhum horário cadastrado para sua turma.</p>
            @else
                @foreach($weekdays as $weekday => $weekdayLabel)
                    @php($daySlots = $slots->where('weekday', $weekday))
                    @if($daySlots->isNotEmpty())
                        <h3 class="h6 font-weight-bold text-primary mt-3">{{ $weekdayLabel }}</h3>
                        <div class="row">
                            @foreach($daySlots as $slot)
                                @php($teacher = $slot->componentAssignment?->teacher)
                                @php($colors = \App\Support\ScheduleTeacherColor::for($teacher?->id, $teacher?->full_name))
                                <div class="col-lg-4 col-md-6 mb-3">
                                    <div class="sge-teacher-schedule-card" style="border-left-color: {{ $colors['border'] }}; background: {{ $colors['background'] }};">
                                        <span>{{ substr($slot->starts_at, 0, 5) }}-{{ substr($slot->ends_at, 0, 5) }}</span>
                                        <strong>{{ $slot->componentAssignment?->component?->name }}</strong>
                                        <small>{{ $teacher?->full_name ?? 'Docência não definida' }}</small>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            @endif
        </div>
    </section>
@endsection
