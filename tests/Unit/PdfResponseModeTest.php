<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class PdfResponseModeTest extends TestCase
{
    public function test_pdf_controllers_open_documents_inline(): void
    {
        $directory = dirname(__DIR__, 2).'/app/Http/Controllers';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        $pdfControllers = 0;

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            if (! is_string($contents) || ! str_contains($contents, 'Pdf::loadView')) {
                continue;
            }

            $pdfControllers++;
            $this->assertStringNotContainsString('->download(', $contents, $file->getFilename().' força download do PDF.');
            $this->assertStringContainsString('->stream(', $contents, $file->getFilename().' não abre o PDF no navegador.');
        }

        $this->assertGreaterThan(0, $pdfControllers);
    }
}
