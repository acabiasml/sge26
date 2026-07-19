<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 18px 22px 34px; }
        body { font-family: DejaVu Sans, sans-serif; color: #2f241f; font-size: 12px; line-height: 1.45; }
        @include('reports.partials.letterhead-styles')
        .declaration-text { font-size: 13px; text-align: justify; margin: 20px 0; }
        .summary { width: 100%; border-collapse: collapse; margin-top: 22px; }
        .summary th { width: 30%; text-align: left; background: #f6f0ea; color: #2f241f; padding: 7px; border: 1px solid #ead8cc; }
        .summary td { padding: 7px; border: 1px solid #ead8cc; }
        .place-date { margin-top: 42px; text-align: right; }
        .signature { margin-top: 80px; width: 100%; }
        .signature td { border: 0; text-align: center; padding-top: 50px; }
        .line { border-top: 1px solid #6b3d2e; display: inline-block; min-width: 280px; padding-top: 6px; }
    </style>
</head>
<body>
    @php
        $student = $enrollment->student;
        $courses = $enrollment->courses->pluck('name')->join(' + ') ?: '-';
        $stageNames = $enrollment->courses
            ->map(fn ($course) => $course->stageLabel())
            ->filter()
            ->unique()
            ->values();
        $stages = $stageNames->join(' / ') ?: '-';
        $stageLabel = $stageNames->count() > 1 ? 'Etapas' : 'Etapa';
        $location = collect([$academicYear->school?->city, $academicYear->school?->state])->filter()->join('-') ?: 'Local';
    @endphp

    @include('reports.partials.letterhead', [
        'title' => $title,
        'letterhead' => $letterhead,
        'issuedDocument' => $issuedDocument,
    ])

    <p class="declaration-text">
        {{ $statement }}
    </p>

    <p class="declaration-text">
        O vínculo refere-se ao(à) estudante <strong>{{ $student?->full_name }}</strong>,
        CPF {{ $student?->cpf ?: '-' }}, filho(a) de {{ $student?->mother_name ?: '-' }},
        na turma <strong>{{ $class->name }}</strong>, {{ mb_strtolower($stageLabel) }}
        <strong>{{ $stages }}</strong>, ano letivo <strong>{{ $academicYear->name }}</strong>,
        matriz(es) <strong>{{ $courses }}</strong>, com matrícula registrada em
        <strong>{{ $enrollment->enrolled_at?->format('d/m/Y') ?? '-' }}</strong>.
        A situação atual da matrícula é <strong>{{ $enrollment->statusLabel() }}</strong>.
    </p>

    <table class="summary">
        <tr><th>Estudante</th><td>{{ $student?->full_name }}</td></tr>
        <tr><th>Data de nascimento</th><td>{{ $student?->birth_date?->format('d/m/Y') ?? '-' }}</td></tr>
        <tr><th>CPF</th><td>{{ $student?->cpf ?: '-' }}</td></tr>
        <tr><th>Escola</th><td>{{ $academicYear->school?->name }}</td></tr>
        <tr><th>{{ $stageLabel }}</th><td>{{ $stages }}</td></tr>
        <tr><th>Ano letivo</th><td>{{ $academicYear->name }}</td></tr>
        <tr><th>Turma</th><td>{{ $class->name }}</td></tr>
        <tr><th>Matriz(es)</th><td>{{ $courses }}</td></tr>
        <tr><th>Situação</th><td>{{ $enrollment->statusLabel() }}</td></tr>
        @if ($enrollment->final_result_status)
            <tr><th>Resultado final</th><td>{{ $enrollment->finalResultLabel() }}</td></tr>
        @endif
    </table>

    <p class="place-date">{{ $location }}, {{ now()->timezone('America/Sao_Paulo')->format('d/m/Y') }}.</p>

    <table class="signature">
        <tr>
            <td><span class="line">Direção escolar</span></td>
            <td><span class="line">Secretaria escolar</span></td>
        </tr>
    </table>

    @include('reports.partials.document-footer', ['issuedDocument' => $issuedDocument])
</body>
</html>
