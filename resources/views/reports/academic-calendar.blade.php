<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 16px 18px; }
        body { font-family: DejaVu Sans, sans-serif; color: #2f241f; font-size: 8px; line-height: 1.15; }
        @include('reports.partials.letterhead-styles')
        .letterhead { margin-bottom: 6px; padding-bottom: 5px; }
        .document-title { font-size: 12.5px; margin-top: 4px; text-transform: uppercase; }
        .document-meta { font-size: 6.8px; }
        .calendar-subtitle { text-align: center; font-size: 8px; margin: -2px 0 6px; color: #534741; }
        table { width: 100%; border-collapse: collapse; }
        .calendar-wrap { border: 1px solid #6B3D2E; padding: 2px; }
        .calendar th { background: #6B3D2E; color: #fff; border: .5px solid #6B3D2E; padding: 2px 1px; text-align: center; }
        .calendar td { border: .5px solid #b99686; padding: 1px; text-align: center; height: 11px; font-weight: 700; }
        .calendar .month { text-align: left; font-weight: 700; color: #6B3D2E; width: 68px; background: #fffaf1; padding-left: 4px; }
        .calendar .day-number { width: 18px; }
        .calendar .school-day { background: #7FB069; color: #11270b; }
        .calendar .weekend { background: #ffffff; color: #1f1b18; }
        .calendar .holiday { background: #D94B43; color: #ffffff; }
        .calendar .recess { background: #7B4A3A; color: #ffffff; }
        .calendar .training { background: #3D8DB8; color: #ffffff; }
        .calendar .period-start { background: #F1C64E; color: #2f241f; }
        .calendar .period-end { background: #DB6B30; color: #ffffff; }
        .calendar .no-class { background: #9257B5; color: #ffffff; }
        .calendar .other { background: #e6dccf; color: #2f241f; }
        .calendar .empty { background: #f4eee8; color: #b6a79f; }
        .calendar td.period-color-1, .period-color-1 { background: #7FB069; color: #11270b; }
        .calendar td.period-color-2, .period-color-2 { background: #79B8CE; color: #14333f; }
        .calendar td.period-color-3, .period-color-3 { background: #F1C64E; color: #2f241f; }
        .calendar td.period-color-4, .period-color-4 { background: #B8A2E3; color: #24183e; }
        .calendar td.period-color-5, .period-color-5 { background: #E79B63; color: #2f160a; }
        .calendar td.period-color-6, .period-color-6 { background: #80C8BC; color: #10342f; }
        .calendar td.period-color-7, .period-color-7 { background: #E2A0B8; color: #3a1825; }
        .calendar td.period-color-8, .period-color-8 { background: #BFA88F; color: #2f241f; }
        .summary { margin-top: 6px; }
        .summary td { vertical-align: top; }
        .panel { border: .7px solid #d8c8bf; padding: 4px; }
        .panel-title { color: #6B3D2E; font-weight: 700; text-transform: uppercase; font-size: 7.2px; margin-bottom: 2px; }
        .legend { width: 34%; }
        .legend-table td { padding: 1px 2px; border-bottom: .4px solid #eadfd8; }
        .legend .code { width: 18px; font-weight: 700; color: #6B3D2E; text-align: center; }
        .special-dates { width: 30%; padding-left: 6px; }
        .special-dates td { padding: 1px 2px; border-bottom: .4px solid #eadfd8; }
        .special-dates .date { width: 42px; font-weight: 700; color: #6B3D2E; }
        .meta { width: 18%; padding-left: 6px; font-size: 7.1px; color: #534741; }
        .meta table td { padding: 1px 2px; border-bottom: .4px solid #eadfd8; }
        .signature { width: 18%; text-align: center; padding-left: 8px; font-size: 7.2px; }
        .signature .line { border-top: .7px solid #6B3D2E; margin-top: 48px; padding-top: 4px; font-weight: 700; }
        .signature .role { display: block; font-weight: 400; margin-top: 2px; }
        .document-footer { font-size: 6.2px; padding-top: 3px; }
    </style>
</head>
<body>
    @php($school = $academicYear->school)

    @include('reports.partials.letterhead', [
        'title' => 'Calendário escolar '.$calendarYearLabel.' - '.$academicYear->name,
        'letterhead' => $letterhead,
        'issuedDocument' => $issuedDocument,
        'verificationUrl' => $verificationUrl,
    ])

    <div class="calendar-subtitle">
        Período: {{ $academicYear->starts_at?->format('d/m/Y') }} a {{ $academicYear->ends_at?->format('d/m/Y') }}
        · Dias letivos: {{ $academicYear->schoolDayCount() }}
        · Aprovação: {{ number_format((float) $academicYear->passing_points, 1, ',', '.') }} pontos e {{ $academicYear->minimum_attendance_percentage }}% de frequência
    </div>

    <div class="calendar-wrap">
        <table class="calendar">
            <thead>
                <tr>
                    <th class="month">Mês</th>
                    @for ($day = 1; $day <= 31; $day++)
                        <th class="day-number">{{ $day }}</th>
                    @endfor
                </tr>
            </thead>
            <tbody>
                @foreach ($calendar as $month)
                    <tr>
                        <td class="month">{{ $month['name'] }}</td>
                        @foreach ($month['days'] as $cell)
                            <td class="{{ $cell['class'] }}">{{ $cell['code'] }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <table class="summary">
        <tr>
            <td class="legend">
                <div class="panel">
                    <div class="panel-title">Legenda</div>
                    <table class="legend-table">
                        @foreach (array_chunk($legend, 6, true) as $legendChunk)
                            <tr>
                                @foreach ($legendChunk as $code => $label)
                                    <td class="code">{{ $code }}</td>
                                    <td>{{ $label }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </table>
                </div>
            </td>
            <td class="special-dates">
                <div class="panel">
                    <div class="panel-title">Datas especiais do calendário</div>
                    <table>
                        @forelse ($specialDates as $specialDate)
                            <tr>
                                <td class="date">{{ $specialDate['date'] }}</td>
                                <td>{{ $specialDate['description'] }}</td>
                            </tr>
                        @empty
                            <tr><td>Nenhuma data especial cadastrada.</td></tr>
                        @endforelse
                    </table>
                </div>
            </td>
            <td class="meta">
                <div class="panel">
                    <div class="panel-title">Períodos</div>
                    <table>
                        @forelse ($periodSummary as $period)
                            <tr>
                                <td class="{{ $period['color_class'] }}"><strong>{{ $period['name'] }}</strong></td>
                                <td>{{ $period['starts_at'] }} a {{ $period['ends_at'] }}</td>
                                <td>{{ $period['school_days'] }} dias</td>
                            </tr>
                        @empty
                            <tr><td>Nenhum período cadastrado.</td></tr>
                        @endforelse
                    </table>
                </div>
            </td>
            <td class="signature">
                {{ $school?->city ?: 'Jarudore' }}{{ $school?->state ? '-'.$school->state : '' }},
                {{ $signatureDate?->format('d/m/Y') }}.
                <div class="line">
                    {{ $directorName ?: 'Direção escolar' }}
                    @if ($directorName)
                        <span class="role">Direção escolar</span>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    @include('reports.partials.document-footer', ['issuedDocument' => $issuedDocument])
</body>
</html>
