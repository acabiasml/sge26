<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 18px 22px 34px; }
        body { font-family: DejaVu Sans, sans-serif; color: #2f241f; font-size: 12px; line-height: 1.45; }
        @include('reports.partials.letterhead-styles')
        .certificate-text { font-size: 13px; text-align: justify; margin: 22px 0; }
        .summary { width: 100%; border-collapse: collapse; margin-top: 14px; }
        .summary th { width: 30%; text-align: left; background: #f6f0ea; color: #2f241f; padding: 7px; border: 1px solid #ead8cc; }
        .summary td { padding: 7px; border: 1px solid #ead8cc; }
        .signature { margin-top: 70px; width: 100%; }
        .signature td { border: 0; text-align: center; padding-top: 48px; }
        .line { border-top: 1px solid #6b3d2e; display: inline-block; min-width: 260px; padding-top: 6px; }
    </style>
</head>
<body>
    @php
        $student = $enrollment->student;
        $courses = $enrollment->courses->pluck('name')->join(' + ') ?: '-';
        $stages = \App\Support\AcademicContextLabel::stages($enrollment->courses);
        $stageHeading = \App\Support\AcademicContextLabel::stageHeading($enrollment->courses);
        $classContext = \App\Support\AcademicContextLabel::classWithStages($class->name, $enrollment->courses);
    @endphp

    @include('reports.partials.letterhead', [
        'title' => 'Atestado de transferência',
        'letterhead' => $letterhead,
        'issuedDocument' => $issuedDocument,
    ])

    <p class="certificate-text">
        Atestamos, para fins de transferência escolar, que <strong>{{ $student?->full_name }}</strong>,
        CPF {{ $student?->cpf ?: '-' }}, filho(a) de {{ $student?->mother_name ?: '-' }},
        esteve matriculado(a) nesta instituição no ano letivo <strong>{{ $academicYear->name }}</strong>,
        na turma <strong>{{ $class->name }}</strong>, {{ mb_strtolower($stageHeading) }} <strong>{{ $stages }}</strong>, vinculada à(s) matriz(es)
        <strong>{{ $courses }}</strong>, com matrícula registrada em
        <strong>{{ $enrollment->enrolled_at?->format('d/m/Y') ?? '-' }}</strong>.
    </p>

    <p class="certificate-text">
        A transferência foi registrada em
        <strong>{{ $enrollment->transferred_at?->format('d/m/Y') }}</strong>.
        Os registros acadêmicos e de frequência existentes até a data de saída permanecem preservados no Beabá.
    </p>

    <table class="summary">
        <tr><th>Estudante</th><td>{{ $student?->full_name }}</td></tr>
        <tr><th>Escola de origem</th><td>{{ $academicYear->school?->name }}</td></tr>
        <tr><th>Ano letivo</th><td>{{ $academicYear->name }}</td></tr>
        <tr><th>Turma e etapa</th><td>{{ $classContext }}</td></tr>
        <tr><th>Matriz(es)</th><td>{{ $courses }}</td></tr>
        <tr><th>Data da matrícula</th><td>{{ $enrollment->enrolled_at?->format('d/m/Y') ?? '-' }}</td></tr>
        <tr><th>Data da transferência</th><td>{{ $enrollment->transferred_at?->format('d/m/Y') ?? '-' }}</td></tr>
        <tr><th>Registro feito por</th><td>{{ $enrollment->transferredBy?->full_name ?: '-' }}</td></tr>
        <tr><th>Observações</th><td>{{ $enrollment->notes ?: '-' }}</td></tr>
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
