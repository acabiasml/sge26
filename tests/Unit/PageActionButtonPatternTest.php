<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class PageActionButtonPatternTest extends TestCase
{
    public function test_page_header_actions_follow_the_compact_accessible_pattern(): void
    {
        $viewsPath = dirname(__DIR__, 2).'/resources/views';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsPath));
        $issues = [];

        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if (! preg_match("/@section\\('page-actions'\\)(.*?)@endsection/s", $contents, $sectionMatch)) {
                continue;
            }

            preg_match_all('/<(a|button)\\b[^>]*class="([^"]*\\bbtn\\b[^"]*)"[^>]*>.*?<\\/\\1>/s', $sectionMatch[1], $buttons);

            foreach ($buttons[0] as $index => $markup) {
                $classes = $buttons[2][$index];
                $isScoreViewToggle = str_contains($classes, 'btn-{{');

                if (! $isScoreViewToggle && ! str_contains($classes, 'sge-icon-action')) {
                    $issues[] = $file->getPathname().': ação de cabeçalho sem sge-icon-action';
                    continue;
                }

                if (str_contains($classes, 'sge-icon-action')) {
                    if (! str_contains($markup, 'aria-label=')) {
                        $issues[] = $file->getPathname().': ação compacta sem aria-label';
                    }
                    if (! str_contains($markup, 'title=')) {
                        $issues[] = $file->getPathname().': ação compacta sem title';
                    }
                }

                if (preg_match('/\\bm[rlxy]-[1-5]\\b/', $classes)) {
                    $issues[] = $file->getPathname().': espaçamento manual em ação de cabeçalho';
                }
            }
        }

        $this->assertSame([], $issues, implode(PHP_EOL, $issues));
    }
}
