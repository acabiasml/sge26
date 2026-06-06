<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #2f241f; font-size: 11px; }
        @include('reports.partials.letterhead-styles')
        table { width: 100%; border-collapse: collapse; }
        th { background: #6B3D2E; color: #fff; text-align: left; padding: 6px; }
        td { border-bottom: 1px solid #e6ddd8; padding: 5px 6px; vertical-align: top; }
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
