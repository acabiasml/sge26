<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 landscape; margin: 16px 18px 30px; }
        body { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; color: #2f241f; font-size: 11px; line-height: 1.18; }
        @include('reports.partials.letterhead-styles')
        .document-title { font-size: 14px; margin-top: 6px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #6B3D2E; color: #fff; }
        th, td { border: .45px solid #b99686; padding: 2.8px 3px; vertical-align: middle; }
        .meta { margin-bottom: 8px; }
        .meta td { border-color: #e1d5ce; }
        .meta-label { width: 14%; background: #faf4ef; font-weight: 600; color: #6B3D2E; }
        .name { width: 24%; font-weight: 600; }
        .day { width: 22px; text-align: center; font-size: 11px; }
        .lesson-count { display: block; font-size: 11px; font-weight: 400; color: #f8e6d8; }
        .signature { margin-top: 36px; text-align: center; }
        .signature-line { display: inline-block; min-width: 260px; border-top: .8px solid #6B3D2E; padding-top: 5px; font-weight: 600; }
        .document-footer { font-size: 11px; }
    </style>
</head>
<body>
    @include('reports.partials.letterhead', [
        'title' => 'Lista de chamada',
        'letterhead' => $letterhead,
        'issuedDocument' => $issuedDocument,
        'verificationUrl' => $verificationUrl,
    ])

    <table class="meta">
        <tr>
            <td class="meta-label">Ano letivo</td><td>{{ $academicYear->referenceYearsLabel() }}</td>
            <td class="meta-label">Mês</td><td>{{ $month->translatedFormat('F/Y') }}</td>
        </tr>
        <tr>
            <td class="meta-label">Turma e etapa</td><td>{{ \App\Support\AcademicContextLabel::classWithStages($schoolClass->name, collect([$course])) }}</td>
            <td class="meta-label">Matriz</td><td>{{ $course->name }} · {{ $course->stageLabel() }}</td>
        </tr>
        <tr>
            <td class="meta-label">Componente</td><td>{{ $component->name }}</td>
            <td class="meta-label">Docência</td><td>{{ $assignment->teacher?->full_name ?? 'Não definida' }}</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th class="name">Estudante</th>
                @foreach($days as $day)
                    <th class="day">
                        {{ $day->date->format('d') }}
                        @if($day->scheduled_lessons)
                            <span class="lesson-count">{{ $day->scheduled_lessons }}a</span>
                        @endif
                    </th>
                @endforeach
                <th>Observações</th>
            </tr>
        </thead>
        <tbody>
            @forelse($enrollments as $enrollment)
                <tr>
                    <td class="name">{{ $enrollment->student?->full_name }}</td>
                    @foreach($days as $day)
                        <td>&nbsp;</td>
                    @endforeach
                    <td>&nbsp;</td>
                </tr>
            @empty
                <tr><td colspan="{{ $days->count() + 2 }}">Nenhum estudante matriculado.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="signature">
        <span class="signature-line">{{ $assignment->teacher?->full_name ?? 'Docência' }}</span>
    </div>

    @include('reports.partials.document-footer', ['issuedDocument' => $issuedDocument])
</body>
</html>
