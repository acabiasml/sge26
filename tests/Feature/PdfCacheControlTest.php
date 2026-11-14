<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PdfCacheControlTest extends TestCase
{
    public function test_pdf_responses_disable_browser_and_proxy_caching_without_changing_the_document(): void
    {
        Route::middleware('web')->get('/test-pdf-cache', fn () => response('%PDF-conteudo', 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="ficha.pdf"',
            'Cache-Control' => 'public, max-age=3600',
            'ETag' => '"antigo"',
            'Last-Modified' => 'Wed, 01 Jan 2025 00:00:00 GMT',
        ]));

        $response = $this->get('/test-pdf-cache')->assertOk()->assertContent('%PDF-conteudo')
            ->assertHeader('Content-Disposition', 'inline; filename="ficha.pdf"')
            ->assertHeader('Pragma', 'no-cache')->assertHeader('Expires', '0')
            ->assertHeader('CDN-Cache-Control', 'no-store')->assertHeaderMissing('ETag')->assertHeaderMissing('Last-Modified');
        foreach (['private', 'no-store', 'no-cache', 'max-age=0', 'must-revalidate'] as $directive) {
            $this->assertStringContainsString($directive, $response->headers->get('Cache-Control'));
        }
    }

    public function test_regular_responses_keep_their_existing_cache_policy(): void
    {
        Route::middleware('web')->get('/test-regular-cache', fn () => response('pagina', 200, ['Cache-Control' => 'public, max-age=600', 'ETag' => '"atual"']));
        $this->get('/test-regular-cache')->assertOk()->assertHeader('Cache-Control', 'max-age=600, public')
            ->assertHeader('ETag', '"atual"')->assertHeaderMissing('CDN-Cache-Control');
    }
}
