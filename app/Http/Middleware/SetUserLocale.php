<?php

namespace App\Http\Middleware;

use Closure;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetUserLocale
{
    /** @var list<string> */
    private const SUPPORTED_LOCALES = ['pt_BR', 'it'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->isIssuedDocument($request)
            ? 'pt_BR'
            : ($request->user()?->locale ?? 'pt_BR');

        $locale = in_array($locale, self::SUPPORTED_LOCALES, true) ? $locale : 'pt_BR';
        App::setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }

    private function isIssuedDocument(Request $request): bool
    {
        $routeName = (string) $request->route()?->getName();

        return str_ends_with($routeName, '.pdf')
            || str_ends_with($routeName, '-pdf')
            || str_ends_with($routeName, '.excel')
            || $routeName === 'official-documents.store';
    }
}
