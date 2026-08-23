<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 landscape; margin: 150px 18px 72px; }
        body { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; color: #2f241f; font-size: 11px; line-height: 1.18; }
        @include('reports.partials.letterhead-styles')
        .document-title { font-size: 15px; margin-top: 7px; text-transform: uppercase; }
        .schedule-title { margin: 8px 0 4px; color: #6B3D2E; font-size: 12px; text-transform: uppercase; }
        .schedule-subtitle { margin: 0 0 6px; color: #5f5a55; font-size: 11px; }
        .schedule-table { width: 100%; border-collapse: collapse; page-break-inside: avoid; margin-bottom: 12px; }
        .schedule-table th { background: #6B3D2E; color: #fff; border: .55px solid #6B3D2E; padding: 3.5px 3px; text-align: center; }
        .schedule-table td { border: .5px solid #b99686; padding: 3px; vertical-align: top; min-height: 28px; }
        .time-cell { width: 68px; background: #fffaf1; color: #6B3D2E; font-weight: 600; text-align: center; }
        .slot { border-left: 4px solid #9a8f86; background: #f4f1ed; padding: 3px 4px; min-height: 24px; }
        .slot + .slot { margin-top: 3px; }
        .slot strong, .slot span, .slot small { display: block; }
        .slot strong { font-size: 11px; color: #2f241f; }
        .slot span { margin-top: 2px; color: #4f4650; }
        .slot small { margin-top: 2px; color: #6f625b; font-size: 11px; }
        .slot-break { border-left-color: #DB6B30; background: #fff0e7; }
        .empty { color: #9b8c84; text-align: center; }
        .page-break { page-break-after: always; }
        .document-footer { font-size: 11px; }
    </style>
</head>
<body>
    @include('reports.partials.letterhead', [
        'title' => $classes->count() === 1 ? 'Horário da turma' : 'Horários das turmas',
        'letterhead' => $letterhead,
        'issuedDocument' => $issuedDocument,
        'verificationUrl' => $verificationUrl,
    ])

    <p class="schedule-subtitle">
        Ano letivo: {{ $academicYear->referenceYearsLabel() }} ·
        Período: {{ $academicYear->starts_at?->format('d/m/Y') }} a {{ $academicYear->ends_at?->format('d/m/Y') }}
    </p>

    @forelse ($classes as $class)
        @foreach ($class->schedules->sortBy('starts_at') as $schedule)
            @php($slots = $schedule->slots->sortBy([['starts_at', 'asc'], ['weekday', 'asc']])->values())
            @php($timeRanges = $slots->map(fn ($slot) => substr($slot->starts_at, 0, 5).'|'.substr($slot->ends_at, 0, 5))->unique()->sort()->values())

            <section>
                <h2 class="schedule-title">{{ \App\Support\AcademicContextLabel::classWithStages($class->name, $class->courses) }} - {{ $schedule->name }}</h2>
                <p class="schedule-subtitle">
                    {{ $class->courses->pluck('name')->join(' + ') ?: 'Sem matriz vinculada' }} ·
                    Vigência: {{ $schedule->starts_at?->format('d/m/Y') }} até {{ $schedule->ends_at?->format('d/m/Y') ?? 'indeterminado' }}
                </p>

                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th>Horário</th>
                            @foreach ($weekdays as $weekday => $weekdayLabel)
                                <th>{{ $weekdayLabel }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($timeRanges as $range)
                            @php([$startsAt, $endsAt] = explode('|', $range))
                            <tr>
                                <td class="time-cell">{{ $startsAt }}<br>{{ $endsAt }}</td>
                                @foreach ($weekdays as $weekday => $weekdayLabel)
                                    @php($daySlots = $slots->where('weekday', $weekday)->filter(fn ($slot) => substr($slot->starts_at, 0, 5) === $startsAt && substr($slot->ends_at, 0, 5) === $endsAt))
                                    <td>
                                        @forelse ($daySlots as $slot)
                                            @php($teacher = $slot->componentAssignment?->teacher)
                                            @php($colors = \App\Support\ScheduleTeacherColor::for($teacher?->id, $teacher?->full_name))
                                            <div class="slot {{ $slot->type === \App\Models\SchoolClassScheduleSlot::TYPE_BREAK ? 'slot-break' : '' }}" style="border-left-color: {{ $colors['border'] }}; background: {{ $slot->type === \App\Models\SchoolClassScheduleSlot::TYPE_BREAK ? '#fff0e7' : $colors['background'] }};">
                                                <strong>{{ $slot->type === \App\Models\SchoolClassScheduleSlot::TYPE_CLASS ? $slot->componentAssignment?->component?->name : $slot->label }}</strong>
                                                @if ($slot->type === \App\Models\SchoolClassScheduleSlot::TYPE_CLASS)
                                                    <span>{{ $teacher?->full_name ?? 'Docência não definida' }}</span>
                                                    <small>{{ $slot->componentAssignment?->component?->course?->name }}</small>
                                                @else
                                                    <span>Intervalo</span>
                                                @endif
                                            </div>
                                        @empty
                                            <span class="empty">-</span>
                                        @endforelse
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($weekdays) + 1 }}" class="empty">Nenhum bloco cadastrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        @endforeach

        @if (! $loop->last)
            <div class="page-break"></div>
        @endif
    @empty
        <p>Nenhuma turma com horário cadastrado.</p>
    @endforelse

    @include('reports.partials.document-footer', ['issuedDocument' => $issuedDocument])
</body>
</html>
