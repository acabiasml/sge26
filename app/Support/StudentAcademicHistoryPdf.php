<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DocumentPdf;
use DOMElement;

final class StudentAcademicHistoryPdf
{
    public function make(array $data): DocumentPdf
    {
        if ($data['history']->education_stage === 'tecnico') {
            return $this->document($data, null);
        }
        $groups = null;
        $sections = $this->measure($data, $groups);
        $filledBoundaries = [];
        while ($sections !== []) {
            $groups = array_column($sections, 'components');
            $merged = [];
            $previous = null;
            foreach ($sections as $section) {
                if ($previous && $previous['page'] === $section['page']
                    && $previous['formation'] === $section['formation'] && $section['formation'] !== '') {
                    array_push($merged[array_key_last($merged)], ...$section['components']);
                } else {
                    $merged[] = $section['components'];
                }
                $previous = $section;
            }
            if (count($merged) < count($sections)) {
                $groups = $merged;
                $sections = $this->measure($data, $groups);

                continue;
            }

            $boundary = null;
            foreach ($sections as $index => $section) {
                if ($index === 0) continue;
                $previous = $sections[$index - 1];
                $key = $previous['page'].':'.end($previous['components']);
                if ($previous['page'] < $section['page'] && ! isset($filledBoundaries[$key])) {
                    $boundary = $index;
                    break;
                }
            }
            if ($boundary === null) break;

            $previous = $sections[$boundary - 1];
            $next = $sections[$boundary];
            $low = 0;
            $high = count($next['components']);
            $bestGroups = $groups;
            $bestSections = $sections;
            // Busca quantas linhas realmente cabem, sem reduzir fonte ou invadir
            // o rodapé. Cada tentativa usa a paginação efetiva do Dompdf.
            while ($low < $high) {
                $count = intdiv($low + $high + 1, 2);
                $prefix = array_slice($next['components'], 0, $count);
                $candidate = $groups;
                $candidate[$boundary] = array_slice($next['components'], $count);
                if ($previous['formation'] === $next['formation'] && $next['formation'] !== '') {
                    array_push($candidate[$boundary - 1], ...$prefix);
                } else {
                    array_splice($candidate, $boundary, 0, [$prefix]);
                }
                $candidate = array_values(array_filter($candidate));
                $measured = $this->measure($data, $candidate);
                $destination = collect($measured)->first(fn ($section) => in_array(end($prefix), $section['components'], true));
                if ($destination && $destination['page'] === $previous['page']
                    && $destination['bottom'] <= $destination['footer_top'] - 6) {
                    $low = $count;
                    $bestGroups = $candidate;
                    $bestSections = $measured;
                } else {
                    $high = $count - 1;
                }
            }
            $last = $low > 0 ? $next['components'][$low - 1] : end($previous['components']);
            if ($low < count($next['components'])) {
                $filledBoundaries[$previous['page'].':'.$last] = true;
            }
            $groups = $bestGroups;
            $sections = $bestSections;
        }

        return $this->document($data, $groups);
    }

    private function document(array $data, ?array $groups): DocumentPdf
    {
        return Pdf::loadView('reports.student-academic-history', array_merge($data, ['historyMatrixComponentGroups' => $groups]))
            ->setPaper('a4', 'portrait');
    }

    private function measure(array $data, ?array $groups): array
    {
        $sections = [];
        $footers = [];
        $pdf = $this->document($data, $groups);
        $pdf->getDomPDF()->setCallbacks([[
            'event' => 'end_frame',
            'f' => function ($frame, $canvas) use (&$sections, &$footers): void {
                $node = $frame->get_node();
                if ($node instanceof DOMElement && $node->hasAttribute('data-history-section')) {
                    $box = $frame->get_border_box();
                    $sections[(int) $node->getAttribute('data-history-section')] = [
                        'page' => $canvas->get_page_number(),
                        'bottom' => $box['y'] + $box['h'],
                        'formation' => $node->getAttribute('data-history-formation'),
                        'components' => json_decode($node->getAttribute('data-history-components'), true, flags: JSON_THROW_ON_ERROR),
                    ];
                }
                if ($node instanceof DOMElement && $node->getAttribute('class') === 'document-footer') {
                    $footers[$canvas->get_page_number()] = $frame->get_border_box()['y'];
                }
            },
        ]]);
        $pdf->render();
        foreach ($sections as &$section) {
            $section['footer_top'] = $footers[$section['page']];
        }
        unset($section);
        ksort($sections);

        return array_values($sections);
    }
}
