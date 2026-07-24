<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 {{ $officialDocument->orientation }}; margin: 20px 24px 38px; }
        body { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; color: #2f241f; font-size: 11.5px; line-height: {{ number_format((float) $officialDocument->line_spacing, 2, '.', '') }}; }
        @include('reports.partials.letterhead-styles')
        .official-content, .official-content * { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif !important; }
        .official-content { margin-top: 18px; }
        .official-content h2 { font-size: 16px; color: #44693D; margin: 14px 0 8px; }
        .official-content h3 { font-size: 14px; color: #44693D; margin: 12px 0 7px; }
        .official-content h4 { font-size: 13px; color: #44693D; margin: 10px 0 6px; }
        .official-content p, .official-content div { margin: 0 0 9px; }
        .official-content img { max-width: 100%; height: auto; }
        .official-content ul, .official-content ol { margin: 0 0 10px 20px; padding: 0; }
        .official-content li { margin-bottom: 4px; }
        .official-content blockquote { border-left: 3px solid #e6ddd8; margin: 10px 0; padding: 4px 0 4px 10px; color: #5f5a55; }
        .official-content table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .official-content th, .official-content td { border: 1px solid #e6ddd8; padding: 6px; vertical-align: top; }
        .official-content th { background: #f6f0ea; }
    </style>
</head>
<body>
    @include('reports.partials.letterhead', [
        'title' => $officialDocument->title,
        'letterhead' => $letterhead,
        'issuedDocument' => $issuedDocument,
        'verificationUrl' => $verificationUrl,
    ])

    <main class="official-content">
        {!! $officialDocument->content_html !!}
    </main>

    @include('reports.partials.document-footer', ['issuedDocument' => $issuedDocument])
</body>
</html>
