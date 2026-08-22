<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class SourceEncodingTest extends TestCase
{
    #[DataProvider('sourceFiles')]
    public function test_user_facing_sources_do_not_contain_common_mojibake_sequences(string $path): void
    {
        $contents = file_get_contents($path);

        $this->assertIsString($contents);
        $this->assertDoesNotMatchRegularExpression(
            '/Ã[£§ª©¡º]|Â[·ºª]|â€|ï¿½|�/u',
            $contents,
            $path.' contém texto com codificação corrompida.',
        );
    }

    /** @return iterable<string, array{string}> */
    public static function sourceFiles(): iterable
    {
        $root = dirname(__DIR__, 2);

        foreach (['app', 'resources/views', 'routes'] as $directory) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory));

            foreach ($files as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['php', 'blade.php'], true)) {
                    yield $file->getPathname() => [$file->getPathname()];
                }
            }
        }
    }
}
