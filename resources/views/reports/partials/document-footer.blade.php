@php
    $issuer = $issuedDocument->issuedBy?->person?->full_name
        ?? $issuedDocument->issuedBy?->name
        ?? $issuedDocument->issuedBy?->email
        ?? 'Sistema';
@endphp

<footer class="document-footer">
    @foreach(($letterhead['footer_lines'] ?? []) as $line)
        <div>{{ $line }}</div>
    @endforeach
    <div>
        Documento emitido pelo Beabá. Autenticidade: {{ $issuedDocument->verification_code }}.
        Emitido em {{ $issuedDocument->issued_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s') }} por {{ $issuer }}.
    </div>
</footer>
<script type="text/php">
    if (isset($pdf, $fontMetrics)) {
        $font = $fontMetrics->getFont('DejaVu Sans', 'normal');
        $pdf->page_text($pdf->get_width() - 105, $pdf->get_height() - 17, 'Página {PAGE_NUM} de {PAGE_COUNT}', $font, 11, [0.23, 0.20, 0.18]);
    }
</script>
