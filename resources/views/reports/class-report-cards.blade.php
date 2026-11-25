<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @include('reports.partials.student-report-card-styles')
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
@foreach($reports as $report)
    @include('reports.partials.student-report-card-page', ['report' => $report, 'letterhead' => $report['letterhead'] ?? $letterhead, 'reportDocumentKey' => $loop->index])

    @unless($loop->last)
        <div class="page-break"></div>
    @endunless
@endforeach

@include('reports.partials.document-footer', ['issuedDocument' => $issuedDocument, 'letterhead' => $letterhead ?? data_get($reports, '0.letterhead')])
</body>
</html>
