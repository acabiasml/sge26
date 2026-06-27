@extends('layouts.app')

@section('title', 'Meu horário')
@section('page-title', 'Meu horário')

@section('page-actions')
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('teacher-schedules.pdf') }}" aria-label="Imprimir meu horário docente" title="Imprimir meu horário">
        <i class="fas fa-file-pdf" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('teacher-diaries.index') }}" aria-label="Voltar aos diários" title="Voltar aos diários">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    <section class="card shadow" aria-labelledby="teacher-schedule-title">
        <div class="card-header py-3">
            <h2 id="teacher-schedule-title" class="h6 m-0 font-weight-bold text-primary">Horário docente vigente</h2>
        </div>
        <div class="card-body">
            @if($slots->isEmpty())
                <p class="mb-0">Nenhum horário vigente encontrado para sua docência.</p>
            @else
                @foreach($weekdays as $weekday => $weekdayLabel)
                    @php($daySlots = $slots->where('weekday', $weekday))
                    @if($daySlots->isNotEmpty())
                        <h3 class="h6 font-weight-bold text-primary mt-3">{{ $weekdayLabel }}</h3>
                        <div class="row">
                            @foreach($daySlots as $slot)
                                @php($colors = \App\Support\ScheduleTeacherColor::for($slot->componentAssignment?->teacher?->id, $slot->componentAssignment?->teacher?->full_name))
                                <div class="col-lg-4 col-md-6 mb-3">
                                    <a class="sge-teacher-schedule-card" style="border-left-color: {{ $colors['border'] }}; background: {{ $colors['background'] }};" href="{{ route('teacher-diaries.show', [$slot->schedule->schoolClass, $slot->componentAssignment->component]) }}">
                                        <span>{{ substr($slot->starts_at, 0, 5) }}-{{ substr($slot->ends_at, 0, 5) }}</span>
                                        <strong>{{ $slot->componentAssignment?->component?->name }}</strong>
                                        <small>{{ $slot->schedule?->schoolClass?->name }} · {{ $slot->schedule?->schoolClass?->academicYear?->school?->name }}</small>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            @endif
        </div>
    </section>
@endsection
