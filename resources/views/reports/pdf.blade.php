<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #2f241f; font-size: 11px; }
        header { border-bottom: 2px solid #7a3f27; margin-bottom: 16px; padding-bottom: 10px; }
        h1 { font-size: 20px; margin: 0 0 4px; color: #7a3f27; }
        .meta { color: #5f5a55; line-height: 1.5; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #7a3f27; color: #fff; text-align: left; padding: 6px; }
        td { border-bottom: 1px solid #e6ddd8; padding: 5px 6px; vertical-align: top; }
        footer { position: fixed; bottom: 0; left: 0; right: 0; border-top: 1px solid #e6ddd8; padding-top: 8px; font-size: 9px; color: #5f5a55; }
    </style>
</head>
<body>
    <header>
        <h1>{{ $report->title }}</h1>
        <div class="meta">
            Beabá - Sistema de Gestão Escolar<br>
            Emitido em {{ $issuedDocument->issued_at?->format('d/m/Y H:i:s') }}<br>
            Código de verificação: <strong>{{ $issuedDocument->verification_code }}</strong><br>
            Verificação: {{ $verificationUrl }}
        </div>
    </header>

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

    <footer>
        Documento emitido pelo Beabá. Confirme a autenticidade usando o código {{ $issuedDocument->verification_code }}.
    </footer>
</body>
</html>
