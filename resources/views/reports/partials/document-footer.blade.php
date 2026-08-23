@php
    $issuer = $issuedDocument->issuedBy?->person?->full_name
        ?? $issuedDocument->issuedBy?->name
        ?? $issuedDocument->issuedBy?->email
        ?? 'Sistema';
@endphp

<footer class="document-footer">
    @if($contactLine = collect($letterhead['footer_lines'] ?? [])->filter()->implode(' | '))
        <div class="document-footer-contact">{{ $contactLine }}</div>
    @endif
    <div>
        Documento emitido pelo Beabá. Autenticidade: {{ $issuedDocument->verification_code }}.
        Emitido em {{ $issuedDocument->issued_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s') }}.
    </div>
</footer>
<script type="text/php">
    if (isset($pdf, $fontMetrics)) {
        $font = $fontMetrics->getFont('DejaVu Sans', 'normal');
        $label = {!! json_encode('por '.$issuer.'. Página {PAGE_NUM} de {PAGE_COUNT}', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!};
        $size = 8.25;
        $width = $fontMetrics->getTextWidth($label, $font, $size);
        $pdf->page_text(($pdf->get_width() - $width) / 2, $pdf->get_height() - 13, $label, $font, $size, [0.37, 0.35, 0.33]);
    }
</script>
