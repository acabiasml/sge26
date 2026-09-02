<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 19px 24px 72px; }
        body { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; color: #111; font-size: 11px; line-height: 1.17; }
        @include('reports.partials.letterhead-styles')
        .letterhead { margin-bottom: 7px; padding-bottom: 5px; }
        .letterhead-logo img { max-width: 66px; max-height: 50px; }
        .document-title { font-size: 14px; margin: 5px 0 2px; text-transform: uppercase; }
        .class-line { font-size: 11px; margin: 1px 0 8px; text-align: center; text-transform: uppercase; white-space: nowrap; }
        .section-title { color: #6f3a29; font-size: 11px; font-weight: 600; margin: 4px 0 2px; text-transform: uppercase; }
        .meta-table { border-collapse: collapse; margin-bottom: 5px; width: 100%; }
        .meta-table td { border: .45px solid #d8ccc4; padding: 1.5px 3px; vertical-align: top; }
        .meta-table .label { background: #f3eee9; font-weight: 600; width: 18%; }
        .report-table { border-collapse: collapse; margin: 5px 0; width: 100%; }
        .report-table th, .report-table td { border: .55px solid #111; padding: 1.8px 2.2px; vertical-align: middle; }
        .report-table th { background: #f1ede9; font-size: 11px; font-weight: 600; text-align: center; }
        .report-table td { font-size: 11px; }
        .technical-regulation { margin: 5px 0; padding: 3px 5px; border: .5px solid #d8c8bf; background: #faf8f6; font-size: 11px; line-height: 1.17; page-break-inside: avoid; }
        .area-cell { font-size: 11px; text-align: center; text-transform: uppercase; width: 22%; word-wrap: break-word; }
        .formation-area-label { display: block; font-size: 11px; font-weight: 600; margin-top: 3px; }
        .component-cell { width: 20%; word-wrap: break-word; }
        .center { text-align: center; }
        .right { text-align: right; }
        .report-summary-table { border-collapse: collapse; font-size: 11px; margin: 5px 0; page-break-inside: avoid; width: 100%; }
        .report-summary-table td { border: .45px solid #d8ccc4; padding: 2px 4px; vertical-align: middle; }
        .report-summary-table .summary-label { background: #f3eee9; font-weight: 600; width: 18%; }
        .legend-table { border-collapse: collapse; font-size: 11px; line-height: 1.22; margin-top: 5px; width: 100%; }
        .legend-table th, .legend-table td { border: .45px solid #d8ccc4; padding: 3px 5px; text-align: left; vertical-align: top; }
        .legend-table th { background: #f3eee9; font-weight: 600; width: 10%; }
        .legend-table td { background: #faf8f6; }
        .legend-table span { display: inline-block; margin-right: 9px; white-space: nowrap; }
        .signatures { border-collapse: collapse; margin-top: 6px; page-break-inside: avoid; width: 100%; }
        .signatures td { border: 0; font-size: 11px; padding-top: 54px; text-align: center; width: 50%; }
        .signature-line { border-top: .6px solid #111; display: inline-block; min-width: 285px; padding-top: 3px; }
        .signature-name { display: block; font-weight: 600; }
        .signature-role { display: block; margin-top: 2px; }
        .document-footer { position: fixed; bottom: -23px; left: 0; right: 0; border-top: .6px solid #bbb; padding-top: 5px; text-align: center; font-size: 11px; color: #333; }
        .document-footer-contact { line-height: 1.08; white-space: normal; overflow-wrap: break-word; }
    </style>
</head>
<body>
@include('reports.partials.student-report-card-page', ['report' => $report])

@include('reports.partials.document-footer', ['issuedDocument' => $issuedDocument])
</body>
</html>
