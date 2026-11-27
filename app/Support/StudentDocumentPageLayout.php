<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DocumentPdf;
use DOMElement;

final class StudentDocumentPageLayout
{
    private const TOP_INSET = 14;

    private const FOOTER_INSET = 35;

    private const CONTENT_GAP = 9;

    public function render(string $view, string $title, array $data, int $inline = 24): DocumentPdf
    {
        $data['pageChrome'] = $this->measure($title, $data, $inline);

        return Pdf::loadView($view, $data)->setPaper('a4', 'portrait');
    }

    public function measure(string $title, array $data, int $inline = 24): array
    {
        $pdf = Pdf::loadView('reports.partials.student-document-layout-probe', [
            'title' => $title, 'letterhead' => $data['letterhead'], 'issuedDocument' => $data['issuedDocument'],
            'inline' => $inline,
        ])->setPaper('a4', 'portrait');
        $header = $footer = 0;
        $pdf->getDomPDF()->setCallbacks([[
            'event' => 'end_frame',
            'f' => static function ($frame) use (&$header, &$footer): void {
                $node = $frame->get_node();
                if (! $node instanceof DOMElement) {
                    return;
                }
                if ($node->tagName === 'header' && trim($node->getAttribute('class')) === 'letterhead') {
                    $header = $frame->get_border_box()[3];
                }
                if ($node->tagName === 'footer' && $node->getAttribute('class') === 'document-footer') {
                    $footer = $frame->get_border_box()[3];
                }
            },
        ]]);
        $pdf->render();

        // Dompdf devolve pontos. As folhas dos documentos usam 96 pixels por polegada.
        $top = max(116, (int) ceil($header * 96 / 72) + self::TOP_INSET + self::CONTENT_GAP);
        $bottom = max(116, (int) ceil($footer * 96 / 72) + self::FOOTER_INSET + self::CONTENT_GAP);

        return [
            'top' => $top, 'bottom' => $bottom, 'inline' => $inline,
            'header_offset' => self::TOP_INSET - $top, 'footer_offset' => self::FOOTER_INSET - $bottom,
        ];
    }
}
