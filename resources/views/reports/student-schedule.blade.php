<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 18px 20px 28px; }
        body { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; color: #2f241f; font-size: 9px; line-height: 1.2; }
        @include('reports.partials.letterhead-styles')
        .document-title { font-size: 15px; margin-top: 7px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background: #6B3D2E; color: #fff; }
        th, td { border: .5px solid #b99686; padding: 4px; vertical-align: top; }
        .slot { border-left: 4px solid #9a8f86; background: #f4f1ed; padding: 4px; margin-bottom: 3px; }
        .slot strong, .slot span, .slot small { display: block; }
        .slot span { color: #6B3D2E; font-weight: 700; }
        .slot small { color: #5f5a55; font-size: 7.5px; }
        .document-footer { font-size: 7px; }
    </style>
</head>
<body>
    @include('reports.partials.letterhead', [
        'title' => 'Horário do estudante',
        'letterhead' => $letterhead,
        'issuedDocument' => $issuedDocument,
        'verificationUrl' => $verificationUrl,
    ])

    <p>
        <strong>Estudante:</strong> {{ $enrollment->student?->full_name }} ·
        <strong>Turma e etapa:</strong> {{ \App\Support\AcademicContextLabel::classWithStages($enrollment->schoolClass?->name, $enrollment->courses) }} ·
        <strong>Ano letivo:</strong> {{ $academicYear->name }}
    </p>

    @php($slots = $enrollment->schoolClass->schedules->flatMap->slots->where('type', \App\Models\SchoolClassScheduleSlot::TYPE_CLASS)->sortBy([['weekday', 'asc'], ['starts_at', 'asc']]))
    <table>
        <thead>
            <tr>
                @foreach($weekdays as $weekdayLabel)
                    <th>{{ $weekdayLabel }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr>
                @foreach($weekdays as $weekday => $weekdayLabel)
                    <td>
                        @forelse($slots->where('weekday', $weekday) as $slot)
                            @php($teacher = $slot->componentAssignment?->teacher)
                            @php($colors = \App\Support\ScheduleTeacherColor::for($teacher?->id, $teacher?->full_name))
                            <div class="slot" style="border-left-color: {{ $colors['border'] }}; background: {{ $colors['background'] }};">
                                <span>{{ substr($slot->starts_at, 0, 5) }}-{{ substr($slot->ends_at, 0, 5) }}</span>
                                <strong>{{ $slot->componentAssignment?->component?->name }}</strong>
                                <small>{{ $teacher?->full_name ?? 'Docência não definida' }}</small>
                            </div>
                        @empty
                            -
                        @endforelse
                    </td>
                @endforeach
            </tr>
        </tbody>
    </table>

    @include('reports.partials.document-footer', ['issuedDocument' => $issuedDocument])
</body>
</html>
