<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DocumentPdf;
use DOMElement;

final class StudentAcademicHistoryPdf
{
    public function make(array $data): DocumentPdf
    {
        $groups = null;
        do {
            $sections = [];
            $pdf = Pdf::loadView('reports.student-academic-history', array_merge($data, ['historyMatrixSectionGroups' => $groups]))
                ->setPaper('a4', 'portrait');
            $pdf->getDomPDF()->setCallbacks([[
                'event' => 'end_frame',
                'f' => function ($frame, $canvas) use (&$sections): void {
                    $node = $frame->get_node();
                    if ($node instanceof DOMElement && $node->hasAttribute('data-history-section')) {
                        $sections[(int) $node->getAttribute('data-history-section')] = [
                            'page' => $canvas->get_page_number(),
                            'formation' => $node->getAttribute('data-history-formation'),
                        ];
                    }
                },
            ]]);
            $pdf->render();

            $merged = [];
            $previous = null;
            ksort($sections);
            foreach ($sections as $index => $section) {
                $originalSections = $groups[$index] ?? [$index];
                if ($previous === $section && $section['formation'] !== '') {
                    array_push($merged[array_key_last($merged)], ...$originalSections);
                } else {
                    $merged[] = $originalSections;
                }
                $previous = $section;
            }
            if (count($merged) === count($sections)) {
                $pdf->getDomPDF()->setCallbacks([]);

                return $pdf;
            }
            // Só combina blocos já medidos na mesma página. A redução no número
            // de blocos garante o término, inclusive quando libera mais espaço.
            $groups = $merged;
            unset($pdf);
        } while (true);
    }
}
