<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 landscape; margin: 150px 22px 72px; }
        body { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; color: #111; font-size: 11px; line-height: 1.18; }
        @include('reports.partials.letterhead-styles')
        .letterhead { margin-bottom: 8px; padding-bottom: 6px; }
        .letterhead-logo img { max-width: 66px; max-height: 50px; }
        .document-title { font-size: 14px; margin: 5px 0 2px; text-transform: uppercase; }
        .context-table, .results-table, .summary-table { border-collapse: collapse; width: 100%; }
        .context-table { margin: 6px 0 8px; }
        .context-table td, .summary-table td { border: .45px solid #d8ccc4; padding: 2.8px 4px; vertical-align: top; }
        .context-table .label, .summary-table .label { background: #f3eee9; font-weight: 600; width: 14%; }
        .summary-table { margin-bottom: 8px; }
        .results-table th, .results-table td { border: .5px solid #111; padding: 2.7px 3.5px; vertical-align: middle; }
        .results-table th { background: #f1ede9; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .results-table td { font-size: 11px; }
        .center { text-align: center; }
        .muted { color: #666; }
        .signatures { border-collapse: collapse; margin-top: 48px; width: 100%; }
        .signatures td { border: 0; font-size: 11px; text-align: center; width: 50%; }
        .signature-line { border-top: .6px solid #111; display: inline-block; min-width: 310px; padding-top: 6px; }
        .document-footer { position: fixed; bottom: -20px; left: 0; right: 0; border-top: .6px solid #bbb; padding-top: 5px; text-align: center; font-size: 11px; color: #333; }
    </style>
</head>
<body>
@php
    $courses = $schoolClass->courses->pluck('name')->join(' + ') ?: '-';
    $counts = $enrollments
        ->groupBy(fn ($enrollment): string => $enrollment->finalResultLabel())
        ->map->count()
        ->sortKeys();
@endphp

@include('reports.partials.letterhead', [
    'title' => 'Ata de resultados finais',
    'letterhead' => $letterhead,
    'issuedDocument' => $issuedDocument,
    'verificationUrl' => $verificationUrl,
])

<table class="context-table">
    <tr>
        <td class="label">Escola</td>
        <td>{{ $academicYear->school?->name }}</td>
        <td class="label">Ano letivo</td>
        <td>{{ $academicYear->referenceYearsLabel() }}</td>
    </tr>
    <tr>
        <td class="label">Turma e etapa</td>
        <td>{{ \App\Support\AcademicContextLabel::classWithStages($schoolClass->name, $schoolClass->courses) }}</td>
        <td class="label">Matrizes</td>
        <td>{{ $courses }}</td>
    </tr>
    <tr>
        <td class="label">Período</td>
        <td>{{ $academicYear->starts_at?->format('d/m/Y') ?? '-' }} a {{ $academicYear->ends_at?->format('d/m/Y') ?? '-' }}</td>
        <td class="label">Critérios</td>
        <td>{{ rtrim(rtrim(number_format((float) $academicYear->passing_points, 2, ',', '.'), '0'), ',') }} pontos · frequência mínima {{ $academicYear->minimum_attendance_percentage }}%</td>
    </tr>
</table>

<table class="summary-table">
    <tr>
        <td class="label">Resumo</td>
        <td>
            @forelse($counts as $label => $total)
                {{ $label }}: {{ $total }}{{ ! $loop->last ? ' · ' : '' }}
            @empty
                Nenhuma matrícula cadastrada.
            @endforelse
        </td>
    </tr>
</table>

<table class="results-table">
    <thead>
        <tr>
            <th style="width: 4%;">Nº</th>
            <th>Estudante</th>
            <th style="width: 17%;">Matrizes</th>
            <th style="width: 11%;">Situação</th>
            <th style="width: 13%;">Resultado final</th>
            <th style="width: 16%;">Registro</th>
            <th style="width: 20%;">Observação</th>
        </tr>
    </thead>
    <tbody>
        @forelse($enrollments as $enrollment)
            <tr>
                <td class="center">{{ $loop->iteration }}</td>
                <td>{{ $enrollment->student?->full_name ?? '-' }}</td>
                <td>{{ $enrollment->courses->pluck('name')->join(' + ') ?: '-' }}</td>
                <td>{{ $enrollment->statusLabel() }}</td>
                <td>{{ $enrollment->finalResultLabel() }}</td>
                <td>
                    @if($enrollment->final_result_calculated_at)
                        {{ $enrollment->final_result_calculated_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}
                        @if($enrollment->finalResultCalculatedBy)
                            <br><span class="muted">{{ $enrollment->finalResultCalculatedBy->full_name }}</span>
                        @endif
                    @else
                        Não calculado
                    @endif
                </td>
                <td>{{ $enrollment->final_result_details['reason'] ?? '-' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="center">Nenhuma matrícula cadastrada.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table class="signatures">
    <tr>
        <td><span class="signature-line">Direção escolar</span></td>
        <td><span class="signature-line">Secretaria escolar</span></td>
    </tr>
</table>

@include('reports.partials.document-footer', ['issuedDocument' => $issuedDocument])
</body>
</html>
