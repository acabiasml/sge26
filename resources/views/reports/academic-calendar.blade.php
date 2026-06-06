<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 18px 20px; }
        body { font-family: DejaVu Sans, sans-serif; color: #2f241f; font-size: 8px; line-height: 1.15; }
        @include('reports.partials.letterhead-styles')
        .letterhead { margin-bottom: 7px; padding-bottom: 6px; }
        .document-title { font-size: 13px; margin-top: 5px; text-transform: uppercase; }
        .document-meta { font-size: 6.8px; }
        .calendar-subtitle { text-align: center; font-size: 8px; margin: -3px 0 6px; }
        table { width: 100%; border-collapse: collapse; }
        .calendar th { background: #6B3D2E; color: #fff; border: 0.5px solid #6B3D2E; padding: 2px 1px; text-align: center; }
        .calendar td { border: 0.5px solid #cdb7ab; padding: 2px 1px; text-align: center; height: 12px; }
        .calendar .month { text-align: left; font-weight: 700; color: #6B3D2E; width: 56px; }
        .calendar .day-number { width: 20px; }
        .calendar .empty { background: #f4eee8; color: #b6a79f; }
        .summary { margin-top: 6px; }
        .summary td { vertical-align: top; }
        .legend { width: 45%; }
        .legend td { padding: 1px 3px; border-bottom: 0.5px solid #eadfd8; }
        .legend .code { width: 22px; font-weight: 700; color: #6B3D2E; }
        .meta { width: 29%; padding-left: 10px; font-size: 7.3px; color: #534741; }
        .signature { width: 26%; text-align: center; padding-left: 12px; font-size: 7.5px; }
        .signature .line { border-top: 0.7px solid #6B3D2E; margin-top: 26px; padding-top: 3px; font-weight: 700; }
        .document-footer { font-size: 6.5px; padding-top: 4px; }
    </style>
</head>
<body>
    @php
        $school = $academicYear->school;
    @endphp

    @include('reports.partials.letterhead', [
        'title' => 'Calendário escolar '.$academicYear->reference_year.' - '.$academicYear->name,
        'letterhead' => $letterhead,
        'issuedDocument' => $issuedDocument,
        'verificationUrl' => $verificationUrl,
    ])

    <div class="calendar-subtitle">
        Período: {{ $academicYear->starts_at?->format('d/m/Y') }} a {{ $academicYear->ends_at?->format('d/m/Y') }}
        · Hora-aula: {{ $academicYear->class_hour_minutes }} minutos
        · Dias letivos: {{ $academicYear->schoolDayCount() }} / mínimo {{ $academicYear->minimum_school_days }}
    </div>

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
                    @foreach ($month['days'] as $code)
                        <td class="{{ $code === '' ? 'empty' : '' }}">{{ $code }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="summary">
        <tr>
            <td class="legend">
                <table>
                    @foreach (array_chunk($legend, 6, true) as $legendChunk)
                        <tr>
                            @foreach ($legendChunk as $code => $label)
                                <td class="code">{{ $code }}</td>
                                <td>{{ $label }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </table>
            </td>
            <td class="meta">
                <strong>Períodos avaliativos</strong><br>
                @forelse ($academicYear->periods->sortBy('position') as $period)
                    {{ $period->name }}: {{ $period->starts_at?->format('d/m/Y') }} a {{ $period->ends_at?->format('d/m/Y') }}<br>
                @empty
                    Nenhum período avaliativo cadastrado.<br>
                @endforelse
                <br>
                Código de verificação: <strong>{{ $issuedDocument->verification_code }}</strong>
            </td>
            <td class="signature">
                {{ $school?->city ?: 'Jarudore' }}{{ $school?->state ? '-'.$school->state : '' }},
                {{ $signatureDate?->format('d/m/Y') }}.
                <div class="line">Direção escolar</div>
            </td>
        </tr>
    </table>

    @include('reports.partials.document-footer', ['issuedDocument' => $issuedDocument])
</body>
</html>
