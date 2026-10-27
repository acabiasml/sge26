<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 19px 24px 96px; }
        body { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; color: #111; font-size: 11px; line-height: 1.06; }
        @include('reports.partials.letterhead-styles')
        .letterhead { margin-bottom: 7px; padding-bottom: 5px; }
        .letterhead-logo img { max-width: 66px; max-height: 50px; }
        .document-title { font-size: 14px; margin: 5px 0 0; }
        .class-line { font-size: 11px; margin: 1px 0 8px; text-align: center; white-space: normal; }
        .section-title { color: #6f3a29; font-size: 11px; font-weight: 600; margin: 4px 0 2px; }
        .meta-table { border-collapse: collapse; margin-bottom: 5px; width: 100%; }
        .meta-table td { border: .45px solid #d8ccc4; padding: 1.5px 3px; vertical-align: top; }
        .meta-table .label { background: #f3eee9; font-weight: 600; }
        .student-meta { margin: 0 0 7px; }
        .student-meta p { margin: 0 0 2px; }
        .report-table { border-collapse: collapse; margin: 7px 0; width: 100%; }
        .report-table th, .report-table td { border: .55px solid #111; padding: 1px 2.2px; vertical-align: middle; }
        .report-table th { background: #f1ede9; font-size: 11px; font-weight: 600; text-align: center; }
        .report-table td { font-size: 11px; }
        .formation-cell { font-size: 11px; font-weight: 600; text-align: center; width: 10%; }
        .area-cell { font-size: 11px; text-align: center; width: 15%; }
        .component-cell { width: 22%; }
        .center { text-align: center; }
        .right { text-align: right; }
        .summary { font-size: 11px; margin-top: 8px; }
        .summary p { margin: 0 0 4px; }
        .legend { font-size: 11px; margin-top: 7px; }
        .legend strong { display: block; }
        .concept-legend { margin-top: 4px; }
        .concept-legend span { display: inline-block; margin-right: 9px; white-space: normal; }
        .signatures { border-collapse: collapse; margin-top: 18px; page-break-inside: avoid; width: 100%; }
        .signatures.spacious { margin-top: 60px; }
        .signatures td { border: 0; font-size: 11px; text-align: center; width: 50%; }
        .signature-line { border-top: .6px solid #111; display: inline-block; min-width: 285px; padding-top: 6px; }
        .signature-name { display: block; font-weight: 600; }
        .signature-role { display: block; margin-top: 2px; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
@foreach($reports as $report)
    @include('reports.partials.student-report-card-page', ['report' => $report])

    @unless($loop->last)
        <div class="page-break"></div>
    @endunless
@endforeach

@include('reports.partials.document-footer', ['issuedDocument' => $issuedDocument])
</body>
</html>
