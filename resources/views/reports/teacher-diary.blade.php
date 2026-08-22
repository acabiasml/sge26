<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 landscape; margin: 18px 18px 32px; }
        body { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; color: #1f1713; font-size: 11px; line-height: 1.18; }
        @include('reports.partials.letterhead-styles')
        .letterhead { margin-bottom: 8px; padding-bottom: 6px; }
        .document-title { font-size: 14px; margin-top: 5px; text-transform: uppercase; }
        h2 { color: #6B3D2E; font-size: 12px; margin: 12px 0 5px; border-bottom: 1px solid #6B3D2E; padding-bottom: 3px; }
        h3 { color: #3F6B3D; font-size: 11px; margin: 9px 0 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th { background: #f2e9e3; color: #6B3D2E; font-weight: 600; }
        th, td { border: .5px solid #d8c8bf; padding: 3px 3.4px; vertical-align: top; }
        .meta td { border-color: #e1d5ce; }
        .meta-label { width: 22%; background: #faf4ef; font-weight: 600; color: #6B3D2E; }
        .center { text-align: center; }
        .small { font-size: 11px; color: #6b625e; }
        .student-column, .student-name { width: 1%; min-width: 190px; white-space: nowrap; }
        .date-heading { height: 48px; padding: 0; position: relative; vertical-align: middle; width: 18px; min-width: 18px; }
        .date-heading span { display: block; line-height: 1; position: absolute; left: -15px; top: 16px; text-align: center; transform: rotate(-90deg); transform-origin: center; white-space: nowrap; width: 48px; }
        .attendance-mark { font-weight: 600; white-space: nowrap; }
        .attendance-total { width: 54px; min-width: 54px; }
        .attendance-page-break { page-break-before: always; height: 0; }
        .compact-score-heading { min-width: 32px; }
        .period-break { page-break-after: always; height: 0; }
        .signature-grid { width: 100%; margin-top: 42px; }
        .signature-grid td { border: 0; width: 50%; text-align: center; padding-top: 38px; }
        .signature-line { border-top: .8px solid #6B3D2E; display: inline-block; min-width: 230px; padding-top: 5px; font-weight: 600; }
        .document-footer { position: fixed; bottom: -18px; left: 0; right: 0; font-size: 11px; color: #6b625e; }
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
    @php
        $period = $report['period'];
        $attendanceColumns = $report['attendance']->flatMap(function ($attendance) {
            return collect(range(0, max(1, (int) $attendance->lesson_count) - 1))->map(fn ($lessonIndex) => [
                'record' => $attendance,
                'lesson_index' => $lessonIndex,
            ]);
        })->values();
        $attendanceColumnGroups = $attendanceColumns->isEmpty()
            ? collect([collect()])
            : $attendanceColumns->chunk(24);
    @endphp
    <section class="period-page">
        @include('reports.partials.letterhead', [
            'title' => 'Diário de classe - '.$component->name,
            'letterhead' => $letterhead,
            'issuedDocument' => $issuedDocument,
            'verificationUrl' => $verificationUrl,
        ])

        <table class="meta">
            <tr><td class="meta-label">Ano letivo</td><td>{{ $academicYear->referenceYearsLabel() }}</td><td class="meta-label">Período</td><td>{{ $period->name }}</td></tr>
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

        @foreach($attendanceColumnGroups as $attendanceGroup)
            @if(! $loop->first)
                <div class="attendance-page-break"></div>
            @endif
        <h2>
            Frequência
            @if($attendanceColumnGroups->count() > 1)
                <span class="small">({{ $loop->iteration }}/{{ $attendanceColumnGroups->count() }})</span>
            @endif
        </h2>
        <table>
            <thead>
                <tr>
                    <th class="student-column">Estudante</th>
                    @foreach($attendanceGroup as $attendanceColumn)
                        <th class="center date-heading"><span>{{ $attendanceColumn['record']->class_date->format('d/m') }}</span></th>
                    @endforeach
                    <th class="center attendance-total">Presenças</th>
                    <th class="center attendance-total">Faltas</th>
                </tr>
            </thead>
            <tbody>
                @forelse($enrollments as $enrollment)
                    @php
                        $enrollmentLocked = ! $enrollment->isActive();
                        $present = $report['attendance']->sum(function ($attendance) use ($enrollment) {
                            return (int) ($attendance->entries->firstWhere('student_enrollment_id', $enrollment->id)?->attended_lessons ?? 0);
                        });
                        $absent = $report['attendance']->sum(function ($attendance) use ($enrollment) {
                            $attendedLessons = (int) ($attendance->entries->firstWhere('student_enrollment_id', $enrollment->id)?->attended_lessons ?? 0);

                            return max(0, (int) $attendance->lesson_count - $attendedLessons);
                        });
                    @endphp
                    <tr>
                        <td class="student-name">
                            {{ $enrollment->student?->full_name }}
                            @if($enrollmentLocked)
                                <span class="small">({{ $enrollment->statusLabel() }})</span>
                            @endif
                        </td>
                        @foreach($attendanceGroup as $attendanceColumn)
                            @php($attendance = $attendanceColumn['record'])
                            @php($lessonIndex = $attendanceColumn['lesson_index'])
                            @php($entry = $attendance->entries->firstWhere('student_enrollment_id', $enrollment->id))
                            @php($attended = (int) ($entry?->attended_lessons ?? 0))
                            @php($lessonPresence = $entry?->lesson_presence ?? [])
                            @php($hasExplicitLesson = array_key_exists($lessonIndex, $lessonPresence))
                            @php($lessonWasPresent = $hasExplicitLesson ? (bool) $lessonPresence[$lessonIndex] : ($entry ? $lessonIndex < $attended : null))
                            <td class="center attendance-mark">{{ $lessonWasPresent === null ? '-' : ($lessonWasPresent ? '*' : 'F') }}</td>
                        @endforeach
                        <td class="center attendance-total">{{ $present }}</td>
                        <td class="center attendance-total">{{ $absent }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $attendanceGroup->count() + 3 }}">Nenhum estudante matriculado.</td></tr>
                @endforelse
            </tbody>
        </table>
        @endforeach

        <h2>Notas e médias</h2>
        <table>
            <thead>
                <tr>
                    <th class="student-column">Estudante</th>
                    @foreach($report['assessments'] as $assessment)
                        <th class="center compact-score-heading">{{ $assessment->title }}</th>
                    @endforeach
                    @if($period->recovery_mode === \App\Models\AcademicPeriod::RECOVERY_REPLACE_PERIOD_AVERAGE)
                        <th class="center">Média original</th>
                        <th class="center">Média considerada</th>
                    @else
                        <th class="center">Média</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($enrollments as $enrollment)
                    @php($enrollmentLocked = ! $enrollment->isActive())
                    <tr>
                        <td>
                            {{ $enrollment->student?->full_name }}
                            @if($enrollmentLocked)
                                <span class="small">({{ $enrollment->statusLabel() }})</span>
                            @endif
                        </td>
                        @foreach($report['assessments'] as $assessment)
                            @php($result = $assessment->results->firstWhere('student_enrollment_id', $enrollment->id))
                            <td class="center">{{ $scoreLabel($result?->score, $period->ends_at ?? $period->starts_at) }}</td>
                        @endforeach
                        @php($average = $report['averages'][$enrollment->id] ?? [])
                        @if($period->recovery_mode === \App\Models\AcademicPeriod::RECOVERY_REPLACE_PERIOD_AVERAGE)
                            <td class="center">{{ $scoreLabel($average['regular_value'] ?? null, $period->ends_at ?? $period->starts_at) }}</td>
                            <td class="center">{{ $scoreLabel($average['value'] ?? null, $period->ends_at ?? $period->starts_at) }}</td>
                        @else
                            <td class="center">{{ $scoreLabel($average['value'] ?? null, $period->ends_at ?? $period->starts_at) }}</td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $report['assessments']->count() + ($period->recovery_mode === \App\Models\AcademicPeriod::RECOVERY_REPLACE_PERIOD_AVERAGE ? 3 : 2) }}">Nenhum estudante matriculado.</td></tr>
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
    @unless($loop->last)
        <div class="period-break"></div>
    @endunless
@endforeach

<div class="document-footer">
    Documento emitido pelo Beabá. Confirme a autenticidade usando o código {{ $issuedDocument->verification_code }}. Emitido em {{ $issuedDocument->issued_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s') }} por {{ $issuedDocument->person?->full_name ?? 'usuário identificado' }}.
</div>
</body>
</html>
