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
    <div class="document-footer-authentication">
        Documento emitido pelo Beabá. Autenticidade: {{ $issuedDocument->verification_code }}.
        Emitido em {{ $issuedDocument->issued_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s') }}.
    </div>
    <div class="document-footer-issuer">por {{ $issuer }}.</div>
</footer>
<script type="text/php">
    if (isset($pdf, $fontMetrics)) {
        $font = $fontMetrics->getFont('DejaVu Sans', 'normal');
        $issuerText = {!! json_encode('por '.$issuer.'.', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!};
        $label = 'Página {PAGE_NUM} de {PAGE_COUNT}';
        $size = 8.25;
        $issuerWidth = $fontMetrics->getTextWidth($issuerText, $font, $size);
        $labelWidth = $fontMetrics->getTextWidth($label, $font, $size);
        $gap = 4;
        $x = ($pdf->get_width() - $issuerWidth - $gap - $labelWidth) / 2 + $issuerWidth + $gap;
        $pdf->page_text($x, $pdf->get_height() - 13, $label, $font, $size, [0.37, 0.35, 0.33]);
    }
</script>
