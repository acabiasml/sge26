<?php

use App\Http\Middleware\EnsureProfileIsComplete;
use App\Http\Middleware\PreventPdfCaching;
use App\Http\Middleware\RecordUserLastAccess;
use App\Http\Middleware\RecordUserOperationFailures;
use App\Http\Middleware\RejectRememberedLogin;
use App\Http\Middleware\SetUserLocale;
use App\Support\UserOperationFailureRecorder;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            PreventPdfCaching::class,
            RejectRememberedLogin::class,
            SetUserLocale::class,
            RecordUserLastAccess::class,
            RecordUserOperationFailures::class,
        ]);
        $middleware->alias([
            'profile.complete' => EnsureProfileIsComplete::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function ($response, Throwable $exception, Request $request) {
            try {
                app(UserOperationFailureRecorder::class)->exception($request, $response, $exception);
            } catch (Throwable) {
                // Preserva a resposta original se o diagnóstico estiver indisponível.
            }

            return $response;
        });
    })->create();
