<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 landscape; margin: 148px 18px 76px; }
        body { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; color: #111; font-size: 11px; line-height: 1.16; }
        @include('reports.partials.letterhead-styles')
        .letterhead { margin-bottom: 6px; padding-bottom: 5px; }
        .letterhead-logo img { max-width: 64px; max-height: 48px; }
        .document-title { font-size: 14px; margin: 4px 0 2px; text-transform: uppercase; }
        table { border-collapse: collapse; width: 100%; }
        .context { margin: 5px 0 7px; }
        .context td, .totals td { border: .45px solid #c9bdb5; padding: 3px 4px; }
        .context .label, .totals .label { background: #f2ece7; font-weight: 700; }
        .attendance th, .attendance td { border: .5px solid #333; padding: 3px; vertical-align: middle; }
        .attendance thead { display: table-header-group; }
        .attendance th { background: #ece5df; font-size: 11px; text-transform: uppercase; }
        .attendance tr { page-break-inside: avoid; }
        .attendance .low-attendance td { background: #f9d6d2; }
        .center { text-align: center; }
        .nowrap { white-space: nowrap; }
        .student-name { font-weight: 600; }
        .status-detail { color: #555; display: block; font-size: 11px; }
        .totals { margin-top: 8px; }
        .totals td { text-align: center; }
        .totals .label { text-align: left; }
        .legend { margin: 5px 0 0; }
        .signatures { margin-top: 46px; page-break-inside: avoid; }
        .signatures td { border: 0; text-align: center; width: 50%; }
        .signature-line { border-top: .6px solid #111; display: inline-block; min-width: 300px; padding-top: 5px; }
        .signature-name, .signature-role { display: block; }
        .document-footer { position: fixed; bottom: -22px; left: 0; right: 0; border-top: .6px solid #bbb; padding-top: 4px; text-align: center; font-size: 11px; color: #333; }
    </style>
</head>
<body>
@include('reports.partials.letterhead', [
    'title' => 'Relatório de Frequência',
    'letterhead' => $letterhead,
    'issuedDocument' => $issuedDocument,
    'verificationUrl' => $verificationUrl,
])

<table class="context">
    <tr>
        <td class="label">Escola</td><td>{{ $academicYear->school?->name }}</td>
        <td class="label">Ano letivo</td><td>{{ $academicYear->referenceYearsLabel() }}</td>
    </tr>
    <tr>
        <td class="label">Período selecionado</td><td>{{ $scopeLabel }}</td>
        <td class="label">Intervalo</td><td>{{ $startsAt->format('d/m/Y') }} a {{ $endsAt->format('d/m/Y') }}</td>
    </tr>
</table>

<table class="attendance">
    <thead>
        <tr>
            <th style="width: 3%">Nº</th>
            <th style="width: 19%">Nome completo</th>
            <th style="width: 9%">Turma</th>
            <th style="width: 10%">Situação</th>
            <th style="width: 9%">NIS</th>
            <th style="width: 8%">Turno</th>
            <th style="width: 10%">CPF</th>
            <th style="width: 10%">INEP</th>
            <th style="width: 7%">Frequência</th>
            <th style="width: 6%">Faltas</th>
            <th style="width: 9%">Auxílio federal</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $row)
            @php($enrollment = $row['enrollment'])
            @php($summary = $row['summary'])
            <tr @class(['low-attendance' => $summary['percentage'] !== null && $summary['percentage'] < 85])>
                <td class="center">{{ $loop->iteration }}</td>
                <td class="student-name">{{ $enrollment->student?->full_name ?? '-' }}</td>
                <td>{{ $enrollment->schoolClass?->name ?? '-' }}</td>
                <td>
                    {{ $enrollment->statusLabel() }}
                    @if($enrollment->status === \App\Models\StudentEnrollment::STATUS_TRANSFERRED && $enrollment->transferred_at)
                        <span class="status-detail">em {{ $enrollment->transferred_at->format('d/m/Y') }}</span>
                    @endif
                </td>
                <td class="center nowrap">{{ $enrollment->student?->nis ?: '-' }}</td>
                <td class="center">{{ $enrollment->schoolClass?->shift ?: '-' }}</td>
                <td class="center nowrap">{{ $enrollment->student?->cpf ?: '-' }}</td>
                <td class="center nowrap">{{ $enrollment->student?->student_inep ?: '-' }}</td>
                <td class="center nowrap">{{ $summary['percentage'] !== null ? number_format($summary['percentage'], 1, ',', '.').'%' : '-' }}</td>
                <td class="center">{{ $summary['absent'] }}</td>
                <td class="center">{{ $enrollment->student?->receives_federal_aid ? 'Sim' : 'Não' }}</td>
            </tr>
        @empty
            <tr><td colspan="11" class="center">Nenhuma matrícula encontrada neste ano letivo.</td></tr>
        @endforelse
    </tbody>
</table>

<table class="totals">
    <tr>
        <td class="label">Totais</td>
        <td>Estudantes: <strong>{{ $rows->count() }}</strong></td>
        <td>Abaixo de 85%: <strong>{{ $belowThreshold }}</strong></td>
        <td>Aulas contabilizadas: <strong>{{ $totals['lessons'] }}</strong></td>
        <td>Presenças efetivas: <strong>{{ $totals['effective_attended'] }}</strong></td>
        <td>Faltas: <strong>{{ $totals['absent'] }}</strong></td>
        <td>Frequência geral: <strong>{{ $totals['percentage'] !== null ? number_format($totals['percentage'], 1, ',', '.').'%' : '-' }}</strong></td>
    </tr>
</table>
<p class="legend">Linhas destacadas indicam frequência inferior a 85% no período selecionado. Faltas justificadas permanecem no total de faltas e são consideradas no cálculo da frequência efetiva.</p>

<table class="signatures"><tr>
    @include('reports.partials.signature-staff', ['school' => $academicYear->school, 'signatureType' => 'secretarial', 'signatureDate' => $issuedDocument->issued_at ?? now()])
</tr></table>

@include('reports.partials.document-footer', ['issuedDocument' => $issuedDocument])
</body>
</html>
