<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;

final class StudentSocioeconomicPdf
{
    public function make(array $data): \Barryvdh\DomPDF\PDF
    {
        $probe = Pdf::loadView('reports.records.socioeconomic', $data)->setPaper('a4', 'portrait');
        $height = 0;
        $probe->getDomPDF()->setCallbacks([['event' => 'end_frame', 'f' => function ($frame) use (&$height): void {
            $node = $frame->get_node();
            if ($node instanceof \DOMElement && $node->tagName === 'header') $height = max($height, $frame->get_border_box()['h']);
        }]]);
        $probe->render();
        // As medidas do renderizador são em pontos; as margens da view são em pixels.
        $margin = max(180, (int) ceil($height * 96 / 72) + 22);

        return Pdf::loadView('reports.records.socioeconomic', array_merge($data, ['contentTopMargin' => $margin]))->setPaper('a4', 'portrait');
    }
}
