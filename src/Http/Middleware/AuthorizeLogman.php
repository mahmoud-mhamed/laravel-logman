<?php

namespace Mhamed\Logman\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeLogman
{
    public function handle(Request $request, Closure $next): Response
    {
        $authorize = config('logman.log_viewer.authorize');

        if ($authorize === null) {
            // No callback configured — block access outside local env
            if (!app()->isLocal()) {
                abort(403);
            }
            return $next($request);
        }

        if (is_callable($authorize) && !$authorize($request)) {
            abort(403);
        }

        return $next($request);
    }
}
