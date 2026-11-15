<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @include('reports.partials.student-report-card-styles')
    </style>
</head>
<body>
@include('reports.partials.student-report-card-page', ['report' => $report])

@include('reports.partials.document-footer', ['issuedDocument' => $issuedDocument])
</body>
</html>
