<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 18px 22px 34px; }
        body { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; color: #2f241f; font-size: 12px; line-height: 1.45; }
        @include('reports.partials.letterhead-styles')
        .certificate-text { font-size: 13px; text-align: justify; margin: 22px 0; }
        .summary { width: 100%; border-collapse: collapse; margin-top: 14px; }
        .summary th { width: 30%; text-align: left; background: #f6f0ea; color: #2f241f; padding: 7px; border: 1px solid #ead8cc; }
        .summary td { padding: 7px; border: 1px solid #ead8cc; }
        .matrix-summary { width: 100%; border-collapse: collapse; margin-top: 18px; font-size: 10px; }
        .matrix-summary caption { text-align: left; color: #6b3d2e; font-weight: 700; font-size: 12px; margin-bottom: 6px; }
        .matrix-summary th { background: #6b3d2e; color: #fff; padding: 6px 4px; border: 1px solid #6b3d2e; text-align: center; }
        .matrix-summary td { padding: 6px 4px; border: 1px solid #ead8cc; text-align: center; }
        .matrix-summary td:first-child, .matrix-summary td:nth-child(2) { text-align: left; }
        .signature { margin-top: 64px; width: 100%; }
        .signature td { border: 0; text-align: center; padding-top: 44px; }
        .line { border-top: 1px solid #6b3d2e; display: inline-block; min-width: 260px; padding-top: 6px; }
        .muted { color: #6d5f5a; }
    </style>
</head>
<body>
    @php
        $student = $enrollment->student;
        $courses = $enrollment->courses->pluck('name')->join(' + ') ?: '-';
        $stages = \App\Support\AcademicContextLabel::stages($enrollment->courses);
        $stageHeading = \App\Support\AcademicContextLabel::stageHeading($enrollment->courses);
        $classContext = \App\Support\AcademicContextLabel::classWithStages($class->name, $enrollment->courses);
        $percentage = $attendance['percentage'] !== null ? number_format((float) $attendance['percentage'], 1, ',', '.').'%' : null;
    @endphp

    @include('reports.partials.letterhead', [
        'title' => 'Atestado de frequência',
        'letterhead' => $letterhead,
        'issuedDocument' => $issuedDocument,
    ])

    <p class="certificate-text">
        Atestamos, para os devidos fins, que <strong>{{ $student?->full_name }}</strong>,
        CPF {{ $student?->cpf ?: '-' }}, filho(a) de {{ $student?->mother_name ?: '-' }},
        {{ $enrollment->isActive() ? 'encontra-se matriculado(a)' : 'esteve matriculado(a)' }}
        nesta instituição no ano letivo <strong>{{ $academicYear->name }}</strong>,
        na turma <strong>{{ $class->name }}</strong>, {{ mb_strtolower($stageHeading) }} <strong>{{ $stages }}</strong>, vinculada à(s) matriz(es)
        <strong>{{ $courses }}</strong>, com matrícula registrada em
        <strong>{{ $enrollment->enrolled_at?->format('d/m/Y') ?? '-' }}</strong>.
    </p>

    <p class="certificate-text">
        No recorte <strong>{{ $scope['label'] }}</strong>, de
        <strong>{{ $scope['starts_at']->format('d/m/Y') }}</strong> a
        <strong>{{ $scope['ends_at']->format('d/m/Y') }}</strong>, conforme os registros disponíveis no Beabá,
        @if ($percentage)
            constam {{ $attendance['lessons'] }} aula(s) lançada(s),
            {{ $attendance['effective_attended'] }} presença(s) consideradas para fins escolares,
            {{ $attendance['absent'] }} falta(s), sendo {{ $attendance['justified'] }} justificada(s),
            correspondendo a <strong>{{ $percentage }}</strong> de frequência.
        @else
            ainda não há lançamentos de frequência suficientes para cálculo percentual.
        @endif
    </p>

    <table class="summary">
        <tr><th>Estudante</th><td>{{ $student?->full_name }}</td></tr>
        <tr><th>Escola</th><td>{{ $academicYear->school?->name }}</td></tr>
        <tr><th>Ano letivo</th><td>{{ $academicYear->name }}</td></tr>
        <tr><th>Turma e etapa</th><td>{{ $classContext }}</td></tr>
        <tr><th>Matriz(es)</th><td>{{ $courses }}</td></tr>
        <tr><th>Recorte da frequência</th><td>{{ $scope['label'] }} · {{ $scope['starts_at']->format('d/m/Y') }} a {{ $scope['ends_at']->format('d/m/Y') }}</td></tr>
        <tr><th>Situação da matrícula</th><td>{{ $enrollment->statusLabel() }}</td></tr>
        <tr><th>Aulas lançadas</th><td>{{ $attendance['lessons'] }}</td></tr>
        <tr><th>Presenças consideradas</th><td>{{ $attendance['effective_attended'] }}</td></tr>
        <tr><th>Faltas</th><td>{{ $attendance['absent'] }} <span class="muted">({{ $attendance['justified'] }} justificada(s))</span></td></tr>
        <tr><th>Frequência</th><td>{{ $percentage ?? '-' }}</td></tr>
    </table>

    <table class="matrix-summary">
        <caption>Frequência por matriz e etapa</caption>
        <thead>
            <tr>
                <th>Matriz</th>
                <th>Etapa</th>
                <th>Aulas</th>
                <th>Presenças consideradas</th>
                <th>Faltas</th>
                <th>Justificadas</th>
                <th>Frequência</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($matrixAttendance as $matrix)
                @php
                    $matrixPercentage = $matrix['attendance']['percentage'] !== null
                        ? number_format((float) $matrix['attendance']['percentage'], 1, ',', '.').'%' : '-';
                @endphp
                <tr>
                    <td>{{ $matrix['course']->name }}</td>
                    <td>{{ $matrix['stage'] }}</td>
                    <td>{{ $matrix['attendance']['lessons'] }}</td>
                    <td>{{ $matrix['attendance']['effective_attended'] }}</td>
                    <td>{{ $matrix['attendance']['absent'] }}</td>
                    <td>{{ $matrix['attendance']['justified'] }}</td>
                    <td>{{ $matrixPercentage }}</td>
                </tr>
            @empty
                <tr><td colspan="7">Nenhuma matriz vinculada à matrícula.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="signature">
        <tr>
            <td><span class="line">Direção escolar</span></td>
            <td><span class="line">Secretaria escolar</span></td>
        </tr>
    </table>

    @include('reports.partials.document-footer', ['issuedDocument' => $issuedDocument])
</body>
</html>
