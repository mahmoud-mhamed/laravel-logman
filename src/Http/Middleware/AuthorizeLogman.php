<?php

namespace MahmoudMhamed\Logman\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeLogman
{
    public function handle(Request $request, Closure $next): Response
    {
        // Skip password check for login/logout routes
        $routeName = $request->route()?->getName();
        if (in_array($routeName, ['logman.login', 'logman.login.submit'])) {
            return $next($request);
        }

        // Password protection check
        $password = config('logman.viewer.password');
        if ($password !== null && $password !== '') {
            if (!$request->session()->get('logman_authenticated')) {
                return redirect()->route('logman.login');
            }
        }

        // Authorization callback check
        $authorize = config('logman.viewer.authorize');

        if ($authorize === null) {
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
