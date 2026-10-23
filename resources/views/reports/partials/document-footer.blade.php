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
    <table class="document-footer-layout" role="presentation">
        <colgroup>
            <col style="width: 79px" />
            <col />
            <col style="width: 79px" />
        </colgroup>
        <tr>
            <td class="document-footer-qr">
                <img src="{{ $qrCodeDataUri }}" alt="QR Code de validação do documento" />
            </td>
            <td class="document-footer-text">
                @if($contactLine)
                    <div class="document-footer-contact">{{ $contactLine }}.</div>
                @endif
                <div class="document-footer-authentication">
                    @if($phoneLine){{ $phoneLine }} | @endif
                    Documento emitido pelo Beabá. Autenticidade: {{ $issuedDocument->verification_code }}.
                </div>
                <div class="document-footer-issuance">
                    @if($siteLine){{ $siteLine }} | @endif
                    Emitido em {{ $issuedDocument->issued_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s') }}
                    por {{ $issuer }}.
                </div>
            </td>
            <td class="document-footer-balance" aria-hidden="true"></td>
        </tr>
    </table>
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
