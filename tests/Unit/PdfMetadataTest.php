<?php

namespace Tests\Unit;

use App\Support\PdfMetadata;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class PdfMetadataTest extends TestCase
{
    public function test_it_adds_a_descriptive_title_before_streaming(): void
    {
        $pdf = new class
        {
            public array $info = [];
            public bool $rendered = false;

            public function render(): void
            {
                $this->rendered = true;
            }

            public function addInfo(array $info): void
            {
                $this->info = $info;
            }

            public function stream(string $filename): Response
            {
                return new Response($filename);
            }
        };

        $response = PdfMetadata::stream($pdf, 'beaba-ficha-individual-335-20260823-154500.pdf');

        $this->assertTrue($pdf->rendered);
        $this->assertSame('Ficha Individual 335 - Beabá', $pdf->info['Title']);
        $this->assertSame('Beabá', $pdf->info['Author']);
        $this->assertSame('beaba-ficha-individual-335-20260823-154500.pdf', $response->getContent());
    }
}
