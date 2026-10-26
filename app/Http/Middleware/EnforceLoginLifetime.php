<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnforceLoginLifetime
{
    public const SESSION_KEY = 'login_started_at';

    private const MAX_SECONDS = 4 * 60 * 60;

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $next($request);
        }

        $guard = Auth::guard('web');
        $startedAt = $request->session()->get(self::SESSION_KEY);
        $now = now()->timestamp;

        if ($guard->viaRemember()
            || ($startedAt !== null && (! is_numeric($startedAt)
                || (int) $startedAt > $now
                || $now - (int) $startedAt >= self::MAX_SECONDS))) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sua sessão expirou. Entre novamente.'], 401);
            }

            return redirect()->route('login')->with('status', 'Sua sessão expirou. Entre novamente.');
        }

        // Sessões anteriores à implantação recebem um marco inicial na primeira requisição.
        if ($startedAt === null) {
            $request->session()->put(self::SESSION_KEY, $now);
        }

        return $next($request);
    }
}
