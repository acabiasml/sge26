<?php

namespace Tests\Support;

final class SignedSchoolPdf
{
    public static function appendSignature(string $original, string $name): string
    {
        $head = "\n<< /Type /Sig /SubFilter /adbe.pkcs7.detached /ByteRange [%09d %09d %09d %09d] /Contents ";
        $tail = " >>\n%%EOF";
        $first = strlen($original) + strlen(sprintf($head, 0, 0, 0, 0));
        $contentsLength = 16002;
        $header = $original.sprintf($head, 0, $first, $first + $contentsLength, strlen($tail));
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        $csr = openssl_csr_new(['commonName' => $name], $key);
        $certificate = openssl_csr_sign($csr, null, $key, 1);
        $content = tempnam(sys_get_temp_dir(), 'beaba-cms-test-');
        $signature = tempnam(sys_get_temp_dir(), 'beaba-cms-test-');
        try {
            file_put_contents($content, $header.$tail);
            if (! openssl_cms_sign($content, $signature, $certificate, $key, [],
                OPENSSL_CMS_BINARY | OPENSSL_CMS_DETACHED, OPENSSL_ENCODING_DER)) {
                throw new \RuntimeException('Falha ao preparar assinatura de teste.');
            }
            $hex = bin2hex(file_get_contents($signature));

            return $header.'<'.str_pad($hex, $contentsLength - 2, '0').'>'.$tail;
        } finally {
            unlink($content);
            unlink($signature);
        }
    }
}
