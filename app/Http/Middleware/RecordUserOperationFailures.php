<?php

namespace App\Http\Middleware;

use App\Support\UserOperationFailureRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordUserOperationFailures
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        try {
            app(UserOperationFailureRecorder::class)->response($request, $response);
        } catch (\Throwable) {
            // A captura de erros não deve causar outro erro para a pessoa.
        }

        return $response;
    }
}
