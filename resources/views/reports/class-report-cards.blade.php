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
    @include('reports.partials.student-report-card-page', ['report' => $report, 'reportDocumentKey' => $loop->index])

    @unless($loop->last)
        <div class="page-break"></div>
    @endunless
@endforeach

@include('reports.partials.document-footer', ['issuedDocument' => $issuedDocument])
</body>
</html>
