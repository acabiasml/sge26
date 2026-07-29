<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 landscape; margin: 18px 18px 32px; }
        body { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; color: #1f1713; font-size: 7.6px; line-height: 1.18; }
        @include('reports.partials.letterhead-styles')
        .letterhead { margin-bottom: 8px; padding-bottom: 6px; }
        .document-title { font-size: 14px; margin-top: 5px; text-transform: uppercase; }
        h2 { color: #6B3D2E; font-size: 12px; margin: 12px 0 5px; border-bottom: 1px solid #6B3D2E; padding-bottom: 3px; }
        h3 { color: #3F6B3D; font-size: 10px; margin: 9px 0 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th { background: #f2e9e3; color: #6B3D2E; font-weight: 600; }
        th, td { border: .5px solid #d8c8bf; padding: 3px 3.4px; vertical-align: top; }
        .meta td { border-color: #e1d5ce; }
        .meta-label { width: 22%; background: #faf4ef; font-weight: 600; color: #6B3D2E; }
        .center { text-align: center; }
        .small { font-size: 7.5px; color: #6b625e; }
        .student-column { min-width: 128px; }
        .date-heading { height: 74px; padding: 0; position: relative; vertical-align: middle; width: 24px; min-width: 24px; overflow: hidden; }
        .date-heading span { display: block; line-height: 1.05; position: absolute; left: -24px; top: 30px; text-align: center; transform: rotate(-90deg); transform-origin: center; white-space: nowrap; width: 74px; }
        .compact-score-heading { min-width: 32px; }
        .period-page { page-break-after: always; }
        .period-page:last-child { page-break-after: auto; }
        .signature-grid { width: 100%; margin-top: 42px; }
        .signature-grid td { border: 0; width: 50%; text-align: center; padding-top: 38px; }
        .signature-line { border-top: .8px solid #6B3D2E; display: inline-block; min-width: 230px; padding-top: 5px; font-weight: 600; }
        .document-footer { position: fixed; bottom: -18px; left: 0; right: 0; font-size: 6.5px; color: #6b625e; }
    </style>
</head>
<body>
@php
    $scoreLabel = function ($score, $date = null) use ($academicYear, $scoreView): string {
        if ($score === null || $score === '') {
            return '-';
        }

        if ($scoreView === 'conceitos') {
            $concept = $academicYear->school?->conceptForScore((float) $score, $date);

            if ($concept) {
                return $concept->shortLabel();
            }
        }

        return number_format((float) $score, 1, ',', '.');
    };
@endphp
@foreach($periodReports as $report)
    @php($period = $report['period'])
    <section class="period-page">
        @include('reports.partials.letterhead', [
            'title' => 'Diário de classe - '.$component->name,
            'letterhead' => $letterhead,
            'issuedDocument' => $issuedDocument,
            'verificationUrl' => $verificationUrl,
        ])

        <table class="meta">
            <tr><td class="meta-label">Ano letivo</td><td>{{ $academicYear->name }}</td><td class="meta-label">Período</td><td>{{ $period->name }}</td></tr>
            <tr><td class="meta-label">Turma e etapa</td><td>{{ \App\Support\AcademicContextLabel::classWithStages($schoolClass->name, collect([$course])) }}</td><td class="meta-label">Matriz</td><td>{{ $course->name }} · {{ $course->stageLabel() }}</td></tr>
            <tr><td class="meta-label">Componente</td><td>{{ $component->name }}</td><td class="meta-label">Área</td><td>{{ $component->area?->name ?? 'Não definida' }}</td></tr>
            <tr><td class="meta-label">Docência</td><td>{{ $assignment->teacher?->full_name ?? 'Não definida' }}</td><td class="meta-label">Situação</td><td>{{ $report['confirmation']?->confirmed ? 'Confirmado' : 'Em lançamento' }}</td></tr>
            <tr><td class="meta-label">Critérios de aprovação</td><td colspan="3">{{ number_format((float) $academicYear->passing_points, 1, ',', '.') }} pontos · {{ $academicYear->minimum_attendance_percentage }}% de frequência mínima</td></tr>
        </table>

        <h2>Conteúdos lançados</h2>
        <table>
            <thead><tr><th style="width: 16%;">Data</th><th>Conteúdo</th></tr></thead>
            <tbody>
                @forelse($report['contents'] as $content)
                    <tr><td>{{ $content->class_date->format('d/m/Y') }}</td><td>{{ $content->content }}</td></tr>
                @empty
                    <tr><td colspan="2">Nenhum conteúdo lançado.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>Frequência</h2>
        <table>
            <thead>
                <tr>
                    <th class="student-column">Estudante</th>
                    @foreach($report['attendance'] as $attendance)
                        <th class="center date-heading"><span>{{ $attendance->class_date->format('d/m') }} · {{ $attendance->lesson_count }} aula(s)</span></th>
                    @endforeach
                    <th class="center">Presenças</th>
                    <th class="center">Faltas</th>
                </tr>
            </thead>
            <tbody>
                @forelse($enrollments as $enrollment)
                    @php($present = 0)
                    @php($absent = 0)
                    <tr>
                        <td>{{ $enrollment->student?->full_name }}</td>
                        @foreach($report['attendance'] as $attendance)
                            @php($entry = $attendance->entries->firstWhere('student_enrollment_id', $enrollment->id))
                            @php($attended = (int) ($entry?->attended_lessons ?? 0))
                            @php($lessons = (int) $attendance->lesson_count)
                            @php($present += $attended)
                            @php($absent += max(0, $lessons - $attended))
                            <td class="center">{{ $attended }}/{{ $lessons }}</td>
                        @endforeach
                        <td class="center">{{ $present }}</td>
                        <td class="center">{{ $absent }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $report['attendance']->count() + 3 }}">Nenhum estudante matriculado.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>Notas e médias</h2>
        <table>
            <thead>
                <tr>
                    <th class="student-column">Estudante</th>
                    @foreach($report['assessments'] as $assessment)
                        <th class="center compact-score-heading">{{ $assessment->title }}</th>
                    @endforeach
                    <th class="center">Média</th>
                </tr>
            </thead>
            <tbody>
                @forelse($enrollments as $enrollment)
                    <tr>
                        <td>{{ $enrollment->student?->full_name }}</td>
                        @foreach($report['assessments'] as $assessment)
                            @php($result = $assessment->results->firstWhere('student_enrollment_id', $enrollment->id))
                            <td class="center">{{ $scoreLabel($result?->score, $period->ends_at ?? $period->starts_at) }}</td>
                        @endforeach
                        <td class="center">{{ $scoreLabel($report['averages'][$enrollment->id]['value'] ?? null, $period->ends_at ?? $period->starts_at) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $report['assessments']->count() + 2 }}">Nenhum estudante matriculado.</td></tr>
                @endforelse
            </tbody>
        </table>

        <table class="signature-grid">
            <tr>
                <td><span class="signature-line">{{ $assignment->teacher?->full_name ?? 'Docência' }}</span><br><span class="small">Docência</span></td>
                <td><span class="signature-line">Gestão escolar</span><br><span class="small">Direção / Coordenação / Secretaria</span></td>
            </tr>
        </table>
    </section>
@endforeach

<div class="document-footer">
    Documento emitido pelo Beabá. Confirme a autenticidade usando o código {{ $issuedDocument->verification_code }}. Emitido em {{ $issuedDocument->issued_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s') }} por {{ $issuedDocument->person?->full_name ?? 'usuário identificado' }}.
</div>
</body>
</html>
