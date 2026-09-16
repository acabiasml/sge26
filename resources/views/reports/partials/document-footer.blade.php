@php
    $issuer = $issuedDocument->issuedBy?->person?->full_name
        ?? $issuedDocument->issuedBy?->name
        ?? $issuedDocument->issuedBy?->email
        ?? 'Sistema';
    $verificationUrl = route('documents.verify', $issuedDocument->verification_code);
    $qrCodeDataUri = (new \chillerlan\QRCode\QRCode)->render($verificationUrl);

    $footerSegments = collect($letterhead['footer_lines'] ?? [])
        ->filter()
        ->flatMap(fn ($line) => explode('|', $line))
        ->map(fn ($line) => trim($line))
        ->filter()
        ->values();
    $siteLine = $footerSegments->first(fn ($line): bool => str_starts_with(mb_strtolower($line, 'UTF-8'), 'site:'));
    $contactSegments = $footerSegments->reject(fn ($line): bool => $line === $siteLine)->values();
    $phoneLine = $contactSegments->first(fn ($line): bool => str_starts_with(mb_strtolower($line, 'UTF-8'), 'tel.:'));
    $contactLine = $contactSegments->reject(fn ($line): bool => $line === $phoneLine)->implode(' | ');
@endphp

<footer class="document-footer">
    <div class="document-footer-qr" aria-label="Código de autenticação do documento">
        <img src="{{ $qrCodeDataUri }}" alt="QR Code de validação do documento" />
    </div>
    <div class="document-footer-text">
        @if($contactLine)
            <div class="document-footer-contact">{{ $contactLine }}</div>
        @endif
        <div class="document-footer-authentication">
            @if($phoneLine){{ $phoneLine }} | @endif
            @if($siteLine){{ $siteLine }} | @endif
            Documento emitido pelo Beabá. Autenticidade: {{ $issuedDocument->verification_code }}.
        </div>
        <div class="document-footer-issuance">
            Emitido em {{ $issuedDocument->issued_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s') }}.
            <span class="document-footer-issuer">por {{ $issuer }}.</span>
        </div>
    </div>
</footer>
<script type="text/php">
    if (isset($pdf, $fontMetrics)) {
        $font = $fontMetrics->getFont('DejaVu Sans', 'normal');
        $label = 'Página {PAGE_NUM} de {PAGE_COUNT}';
        $size = 7.6;
        $pageTextWidth = $fontMetrics->getTextWidth(str_replace('{PAGE_NUM}', '99', str_replace('{PAGE_COUNT}', '99', $label)), $font, $size);
        $x = $pdf->get_width() - 18 - $pageTextWidth;
        $y = $pdf->get_height() - 32;
        $pdf->page_text($x, $y, $label, $font, $size, [0.37, 0.35, 0.33]);
    }
</script>
