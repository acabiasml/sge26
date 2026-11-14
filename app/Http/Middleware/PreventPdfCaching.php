<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;
use Symfony\Component\HttpFoundation\Response;

class PreventPdfCaching
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $issuance = $request->routeIs('document-issuance.issue');

        if ($issuance && $response instanceof RedirectResponse) {
            $target = Uri::of($response->getTargetUrl());
            if (preg_match('~(?:[/-]pdf|/recibo-voluntario)/?$~', $target->path())) {
                $response->setTargetUrl((string) $target->withQuery(['emissao' => (string) Str::uuid()]));
            }
        }

        if ($issuance || str_starts_with(strtolower($response->headers->get('Content-Type', '')), 'application/pdf')) {
            $response->headers->set('Cache-Control', 'private, no-store, no-cache, max-age=0, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
            $response->headers->set('CDN-Cache-Control', 'no-store');
            $response->headers->remove('ETag');
            $response->headers->remove('Last-Modified');
        }

        return $response;
    }
}
