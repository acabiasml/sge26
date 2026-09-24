<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;

class OfficialDocumentPdf
{
    public function make(array $data): \Barryvdh\DomPDF\PDF
    {
        // Measure only the actual letterhead, including wrapped title/school lines.
        // Dompdf frame dimensions are points; CSS page margins here use pixels.
        $probe = Pdf::loadView('official-documents.pdf', array_merge($data, ['measureLetterhead' => true]))
            ->setPaper('a4', $data['officialDocument']->orientation);
        $height = 0;
        $probe->getDomPDF()->setCallbacks([[
            'event' => 'end_frame',
            'f' => function ($frame) use (&$height): void {
                $node = $frame->get_node();
                if ($node instanceof \DOMElement && $node->tagName === 'header' && str_contains($node->getAttribute('class'), 'letterhead')) {
                    $height = max($height, $frame->get_border_box()['h']);
                }
            },
        ]]);
        $probe->render();
        $margin = max(150, (int) ceil($height * 96 / 72) + 16 + 20);

        return Pdf::loadView('official-documents.pdf', array_merge($data, ['contentTopMargin' => $margin]))
            ->setPaper('a4', $data['officialDocument']->orientation);
    }
}
