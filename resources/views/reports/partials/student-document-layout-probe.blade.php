<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="utf-8"><style>
    @page { size: A4 portrait; margin: 14px {{ $inline }}px 35px; }
    body { font: 11px 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; }
    @include('reports.partials.letterhead-styles')
    @include('reports.partials.student-document-chrome-styles')
    .document-footer { position: static; }
</style></head>
<body>
    @include('reports.partials.letterhead', ['repeatOnEveryPage' => false, 'showTechnicalRegulation' => false, 'showRenewalNotices' => false])
    @include('reports.partials.document-footer')
</body>
</html>
