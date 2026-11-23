<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class PdfSignatureEvidence
{
    /**
     * Confere a revisão incremental e a integridade das assinaturas CMS.
     * A cadeia ICP-Brasil, os titulares e o carimbo devem ser conferidos no relatório externo.
     */
    public function confirm(string $pdf, int $expected, ?string $original = null): void
    {
        if ($original !== null && ($pdf === $original || ! str_starts_with($pdf, $original))) {
            throw ValidationException::withMessages(['signed_pdf' => 'Assine o PDF original sem regravar ou converter o arquivo.']);
        }
        preg_match_all('/\/ByteRange\s*\[\s*0+\s+(\d{1,10})\s+(\d{1,10})\s+(\d{1,10})\s*\]/', $pdf, $ranges, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $fingerprints = [];
        $finalRevisionSigned = false;
        foreach (array_slice($ranges, 0, 30) as $range) {
            $first = (int) $range[1][0];
            $second = (int) $range[2][0];
            $length = (int) $range[3][0];
            if ($first < strlen($original ?? '') || $second <= $first || $length <= 0 || $second + $length > strlen($pdf)
                || ($range[0][1] >= $first && $range[0][1] < $second)) {
                continue;
            }
            $excluded = substr($pdf, $first, $second - $first);
            if (! preg_match('/^<([a-f0-9\s]+)>$/iD', $excluded, $contents)) {
                continue;
            }
            $hex = preg_replace('/\s+/', '', $contents[1]);
            if (strlen($hex) % 2 !== 0) {
                continue;
            }
            $signature = hex2bin($hex);
            $bytes = substr($pdf, 0, $first).substr($pdf, $second, $length);
            $verified = $this->fingerprints($bytes, $signature);
            if ($verified && trim(substr($pdf, $second + $length), " \t\r\n") === '') {
                $finalRevisionSigned = true;
            }
            foreach ($verified as $fingerprint) {
                $fingerprints[$fingerprint] = true;
            }
        }
        if (! $finalRevisionSigned || count($fingerprints) < $expected) {
            throw ValidationException::withMessages(['signed_pdf' => 'As assinaturas digitais legíveis neste PDF são insuficientes ou não correspondem ao conteúdo assinado.']);
        }
    }

    private function fingerprints(string $bytes, string $signature): array
    {
        $files = [];
        try {
            foreach (['content', 'signature', 'certificates'] as $name) {
                $path = tempnam(sys_get_temp_dir(), 'beaba-pades-');
                throw_unless($path, \RuntimeException::class, 'Não foi possível verificar o PDF.');
                $files[$name] = $path;
            }
            throw_if(file_put_contents($files['content'], $bytes) === false
                || file_put_contents($files['signature'], $signature) === false, \RuntimeException::class, 'Não foi possível verificar o PDF.');
            $valid = openssl_cms_verify($files['content'], OPENSSL_CMS_BINARY | OPENSSL_CMS_DETACHED | OPENSSL_CMS_NOVERIFY,
                $files['certificates'], [], null, null, null, $files['signature'], OPENSSL_ENCODING_DER);
            if (! $valid) {
                return [];
            }
            preg_match_all('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', file_get_contents($files['certificates']), $certificates);

            return array_filter(array_map(fn ($certificate) => openssl_x509_fingerprint($certificate, 'sha256'), $certificates[0]));
        } finally {
            foreach ($files as $path) {
                unlink($path);
            }
        }
    }
}
