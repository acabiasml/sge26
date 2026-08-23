<?php

namespace App\Support;

use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PdfMetadata
{
    public static function stream(mixed $pdf, string $filename, ?string $title = null): Response
    {
        $title ??= self::titleFromFilename($filename);
        $pdf->render();
        $pdf->addInfo([
            'Title' => $title,
            'Subject' => $title.' emitido pelo Beabá',
            'Author' => 'Beabá',
        ]);

        return $pdf->stream($filename);
    }

    private static function titleFromFilename(string $filename): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $name = preg_replace('/-\d{8}-\d{6}$/', '', $name) ?: $name;
        $name = preg_replace('/^beaba-/', '', $name) ?: $name;

        return Str::headline(str_replace('-', ' ', $name)).' - Beabá';
    }
}
