<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\UserLastAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordUserLastAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $action = (string) $request->route()?->getActionName();
        $isApplicationRequest = str_starts_with($action, 'App\\Http\\Controllers\\')
            || $request->routeIs('livewire.update', 'app.livewire.update');

        if ($isApplicationRequest && ! $request->isMethod('HEAD') && ! $request->isMethod('OPTIONS')
            && ($user = $request->user()) instanceof User) {
            app(UserLastAccess::class)->record($user);
        }

        return $next($request);
    }
}
