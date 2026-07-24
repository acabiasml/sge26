<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 19px 24px 40px; }
        body { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; color: #111; font-size: 7.9px; line-height: 1.17; }
        @include('reports.partials.letterhead-styles')
        .letterhead { margin-bottom: 7px; padding-bottom: 5px; }
        .letterhead-logo img { max-width: 66px; max-height: 50px; }
        .document-title { font-size: 14px; margin: 5px 0 2px; text-transform: uppercase; }
        .class-line { font-size: 9px; margin: 1px 0 8px; text-align: center; text-transform: uppercase; white-space: nowrap; }
        .student-meta { margin: 0 0 7px; }
        .student-meta p { margin: 0 0 2px; }
        .report-table { border-collapse: collapse; margin: 7px 0; width: 100%; }
        .report-table th, .report-table td { border: .55px solid #111; padding: 1.8px 2.2px; vertical-align: middle; }
        .report-table th { background: #f1ede9; font-size: 6.5px; font-weight: 600; text-align: center; }
        .report-table td { font-size: 6.7px; }
        .formation-cell { font-size: 6.2px; font-weight: 600; text-align: center; text-transform: uppercase; width: 10%; }
        .area-cell { font-size: 6.5px; text-align: center; text-transform: uppercase; width: 15%; }
        .component-cell { width: 22%; }
        .center { text-align: center; }
        .right { text-align: right; }
        .summary { font-size: 8.2px; margin-top: 8px; }
        .summary p { margin: 0 0 4px; }
        .legend { font-size: 6.8px; margin-top: 7px; }
        .legend strong { display: block; }
        .concept-legend { margin-top: 4px; }
        .concept-legend span { display: inline-block; margin-right: 9px; white-space: nowrap; }
        .signatures { border-collapse: collapse; margin-top: 88px; width: 100%; }
        .signatures td { border: 0; font-size: 8px; text-align: center; width: 50%; }
        .signature-line { border-top: .6px solid #111; display: inline-block; min-width: 285px; padding-top: 6px; }
        .page-break { page-break-after: always; }
        .document-footer { position: fixed; bottom: -23px; left: 0; right: 0; border-top: .6px solid #bbb; padding-top: 5px; text-align: center; font-size: 6.7px; color: #333; }
    </style>
</head>
<body>
@foreach($reports as $report)
    @include('reports.partials.student-report-card-page', ['report' => $report])

    @unless($loop->last)
        <div class="page-break"></div>
    @endunless
@endforeach

<div class="document-footer">
    Documento emitido pelo Beabá. Confirme a autenticidade usando o código {{ $issuedDocument->verification_code }}.
    Emitido em {{ $issuedDocument->issued_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s') }} por {{ $issuedDocument->issuedBy?->person?->full_name ?? $issuedDocument->person?->full_name ?? 'usuário identificado' }}.
</div>
</body>
</html>
