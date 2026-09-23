<?php

namespace Tests\Feature;

use App\Support\OfficialDocumentContent;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OfficialDocumentContentTest extends TestCase
{
    public function test_table_structure_images_and_formatting_survive_sanitization(): void
    {
        $image = imagecreatetruecolor(40, 20);
        ob_start(); imagepng($image); $src = 'data:image/png;base64,'.base64_encode(ob_get_clean());
        $input = '<p align="center"><span style="font-family: DejaVu Serif; font-size: 14pt">Título</span></p><table style="width: 100%"><tbody><tr><th colspan="2">Cabeçalho</th></tr><tr><td rowspan="2" style="width: 25%; text-align: right">A</td><td>B</td></tr><tr><td><img src="'.$src.'" alt="Desenho" style="width: 50%; float: right" onerror="alert(1)"></td></tr></tbody></table>';
        $content = app(OfficialDocumentContent::class)->sanitize($input);
        foreach (['colspan="2"', 'rowspan="2"', 'width: 25%', 'width: 50%', 'text-align: center', 'text-align: right', 'font-size: 14pt', 'font-family: DejaVu Serif', 'alt="Desenho"', $src] as $expected) {
            $this->assertStringContainsString($expected, $content);
        }
        $this->assertStringNotContainsString('onerror', $content);
        $this->assertSame($content, app(OfficialDocumentContent::class)->sanitize($content));
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($content)->output();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('/Subtype /Image', $pdf);
    }

    public function test_unsafe_elements_attributes_and_styles_are_removed(): void
    {
        $html = '<script>alert(1)</script><iframe src="http://localhost"></iframe><p onclick="bad()" style="background: url(file:///etc/passwd); position: fixed; text-align: center">Texto</p><table><tr><td colspan="999999" style="width: expression(bad())">Célula</td></tr></table>';
        $content = app(OfficialDocumentContent::class)->sanitize($html);
        foreach (['script', 'alert', 'iframe', 'onclick', 'background', 'position', 'expression', 'colspan'] as $unsafe) {
            $this->assertStringNotContainsString($unsafe, $content);
        }
        $this->assertStringContainsString('text-align: center', $content);
    }

    public function test_external_and_forged_image_sources_are_rejected(): void
    {
        foreach (['https://example.org/picture.png', 'file:///etc/passwd', 'data:image/svg+xml;base64,PHN2Zz4=', 'data:image/png;base64,YmFk'] as $src) {
            try {
                app(OfficialDocumentContent::class)->sanitize('<img src="'.$src.'">');
                $this->fail('Imagem inválida foi aceita.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('content_html', $e->errors());
            }
        }
    }
}
