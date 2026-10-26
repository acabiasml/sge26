<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class RejectRememberedLogin
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! Auth::guard('web')->viaRemember()) {
            return $next($request);
        }

        $guard = Auth::guard('web');
        $guard->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Entre novamente para continuar.'], 401);
        }

        return redirect()->route('login')->with('status', 'Entre novamente para continuar.');
    }
}
