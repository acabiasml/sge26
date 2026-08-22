<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ReportFontSizeTest extends TestCase
{
    #[DataProvider('reportTemplates')]
    public function test_report_templates_do_not_define_fonts_smaller_than_eleven_pixels(string $path): void
    {
        $contents = file_get_contents($path);

        $this->assertIsString($contents);

        preg_match_all('/font-size\s*:\s*([0-9]+(?:\.[0-9]+)?)px/i', $contents, $matches);

        foreach ($matches[1] as $fontSize) {
            $this->assertGreaterThanOrEqual(
                11,
                (float) $fontSize,
                basename($path).' define fonte de '.$fontSize.'px.',
            );
        }
    }

    /** @return iterable<string, array{string}> */
    public static function reportTemplates(): iterable
    {
        $directory = dirname(__DIR__, 2).'/resources/views/reports';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($files as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                yield $file->getPathname() => [$file->getPathname()];
            }
        }
    }
}
