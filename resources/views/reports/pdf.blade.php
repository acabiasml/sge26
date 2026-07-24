<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 landscape; margin: 18px 22px 34px; }
        body { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; color: #2f241f; font-size: 10px; line-height: 1.28; }
        @include('reports.partials.letterhead-styles')
        table { width: 100%; border-collapse: collapse; }
        th { background: #6B3D2E; color: #fff; text-align: left; padding: 5px 6px; }
        td { border-bottom: .6px solid #e6ddd8; padding: 4.5px 6px; vertical-align: top; }
    </style>
</head>
<body>
    @include('reports.partials.letterhead', [
        'title' => $report->title,
        'letterhead' => $letterhead,
        'issuedDocument' => $issuedDocument,
        'verificationUrl' => $verificationUrl,
    ])

    <table>
        <thead>
            <tr>
                @foreach ($report->headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($report->rows as $row)
                <tr>
                    @foreach ($row as $value)
                        <td>{{ $value ?: '-' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($report->headings) }}">Nenhum registro encontrado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @include('reports.partials.document-footer', ['issuedDocument' => $issuedDocument])
</body>
</html>
