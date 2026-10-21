<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class NativeBrowserDialogTest extends TestCase
{
    public function test_application_views_and_scripts_do_not_open_native_browser_dialogs(): void
    {
        $root = dirname(__DIR__, 2);
        $issues = [];

        foreach (['resources/views' => '.blade.php', 'public/template/js' => '.js'] as $directory => $extension) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory));

            foreach ($files as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getFilename(), $extension)
                    || str_ends_with($file->getFilename(), '.min.js')) {
                    continue;
                }

                if (preg_match('/\b(?:window\.)?(?:alert|confirm|prompt)\s*\(/', file_get_contents($file->getPathname()))) {
                    $issues[] = $file->getPathname();
                }
            }
        }

        $this->assertSame([], $issues, 'Diálogos nativos encontrados em: '.implode(', ', $issues));
    }
}
