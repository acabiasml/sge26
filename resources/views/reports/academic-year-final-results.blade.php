<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 landscape; margin: 18px 22px 34px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111; font-size: 8px; line-height: 1.18; }
        @include('reports.partials.letterhead-styles')
        .letterhead { margin-bottom: 8px; padding-bottom: 6px; }
        .letterhead-logo img { max-width: 66px; max-height: 50px; }
        .document-title { font-size: 14px; margin: 5px 0 2px; text-transform: uppercase; }
        .context-table, .results-table, .summary-table { border-collapse: collapse; width: 100%; }
        .context-table { margin: 6px 0 8px; }
        .context-table td, .summary-table td { border: .45px solid #d8ccc4; padding: 3px 4px; vertical-align: top; }
        .context-table .label, .summary-table .label { background: #f3eee9; font-weight: 700; width: 13%; }
        .summary-table { margin-bottom: 8px; }
        .class-title { background: #6B3D2E; color: #fff; font-weight: 700; margin-top: 9px; padding: 5px 7px; }
        .results-table th, .results-table td { border: .55px solid #111; padding: 3px 4px; vertical-align: middle; }
        .results-table th { background: #f1ede9; font-size: 7px; font-weight: 700; text-transform: uppercase; }
        .results-table td { font-size: 7.2px; }
        .center { text-align: center; }
        .muted { color: #666; }
        .signatures { border-collapse: collapse; margin-top: 44px; width: 100%; }
        .signatures td { border: 0; font-size: 8px; text-align: center; width: 50%; }
        .signature-line { border-top: .6px solid #111; display: inline-block; min-width: 310px; padding-top: 6px; }
        .document-footer { position: fixed; bottom: -20px; left: 0; right: 0; border-top: .6px solid #bbb; padding-top: 5px; text-align: center; font-size: 6.7px; color: #333; }
    </style>
</head>
<body>
@php
    $allCounts = $classes
        ->flatMap(fn ($classSummary) => $classSummary['enrollments'])
        ->groupBy(fn ($enrollment): string => $enrollment->finalResultLabel())
        ->map->count()
        ->sortKeys();
@endphp

@include('reports.partials.letterhead', [
    'title' => 'Resultados finais do ano letivo',
    'letterhead' => $letterhead,
    'issuedDocument' => $issuedDocument,
    'verificationUrl' => $verificationUrl,
])

<table class="context-table">
    <tr>
        <td class="label">Escola</td>
        <td>{{ $academicYear->school?->name }}</td>
        <td class="label">Ano letivo</td>
        <td>{{ $academicYear->name }} · {{ $academicYear->reference_year }}</td>
    </tr>
    <tr>
        <td class="label">Período</td>
        <td>{{ $academicYear->starts_at?->format('d/m/Y') ?? '-' }} a {{ $academicYear->ends_at?->format('d/m/Y') ?? '-' }}</td>
        <td class="label">Critérios</td>
        <td>{{ rtrim(rtrim(number_format((float) $academicYear->passing_points, 2, ',', '.'), '0'), ',') }} pontos · frequência mínima {{ $academicYear->minimum_attendance_percentage }}%</td>
    </tr>
    <tr>
        <td class="label">Fechamento</td>
        <td>{{ $academicYear->closed_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') ?? 'Ano letivo ainda aberto' }}</td>
        <td class="label">Registros</td>
        <td>{{ $classes->count() }} turma(s) · {{ $rowsCount }} matrícula(s)</td>
    </tr>
</table>

<table class="summary-table">
    <tr>
        <td class="label">Resumo geral</td>
        <td>
            @forelse($allCounts as $label => $total)
                {{ $label }}: {{ $total }}{{ ! $loop->last ? ' · ' : '' }}
            @empty
                Nenhum resultado final registrado.
            @endforelse
        </td>
    </tr>
</table>

@forelse($classes as $classSummary)
    @php($class = $classSummary['class'])
    <div class="class-title">
        {{ \App\Support\AcademicContextLabel::classWithStages($class->name, $class->courses) }} · {{ $class->courses->pluck('name')->join(' + ') ?: 'Sem matriz' }}
        @if($classSummary['counts']->isNotEmpty())
            ·
            @foreach($classSummary['counts'] as $label => $total)
                {{ $label }}: {{ $total }}{{ ! $loop->last ? ' · ' : '' }}
            @endforeach
        @endif
    </div>
    <table class="results-table">
        <thead>
            <tr>
                <th style="width: 4%;">Nº</th>
                <th>Estudante</th>
                <th style="width: 18%;">Matrizes</th>
                <th style="width: 12%;">Situação</th>
                <th style="width: 14%;">Resultado final</th>
                <th style="width: 17%;">Registro</th>
                <th style="width: 20%;">Observação</th>
            </tr>
        </thead>
        <tbody>
            @forelse($classSummary['enrollments'] as $enrollment)
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
                <tr><td colspan="7" class="center">Nenhuma matrícula cadastrada.</td></tr>
            @endforelse
        </tbody>
    </table>
@empty
    <p>Nenhuma turma cadastrada.</p>
@endforelse

<table class="signatures">
    <tr>
        <td><span class="signature-line">Direção escolar</span></td>
        <td><span class="signature-line">Secretaria escolar</span></td>
    </tr>
</table>

<div class="document-footer">
    Documento emitido pelo Beabá. Confirme a autenticidade usando o código {{ $issuedDocument->verification_code }}.
    Emitido em {{ $issuedDocument->issued_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s') }} por {{ $issuedDocument->issuedBy?->person?->full_name ?? 'usuário identificado' }}.
</div>
</body>
</html>
