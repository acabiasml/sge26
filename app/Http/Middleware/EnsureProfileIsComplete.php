<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileIsComplete
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->person?->hasCompletedProfile()) {
            return redirect()->route('profile.edit')
                ->with('status', 'Complete seu cadastro pessoal antes de continuar.');
        }

        return $next($request);
    }
}
