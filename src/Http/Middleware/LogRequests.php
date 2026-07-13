<?php

namespace MahmoudMhamed\Logman\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use MahmoudMhamed\Logman\Support\RequestLogger;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs every incoming HTTP request to a dedicated log channel.
 *
 * Registered globally (only when logman.request_logging.enabled is true) by the
 * service provider. The actual write happens in terminate() so it runs after
 * the response has been sent to the client and never adds latency.
 *
 * The building/filtering logic lives in {@see RequestLogger} so it can be
 * shared with the logman_log_request() helper and the Logman::logRequest()
 * facade call.
 */
class LogRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        RequestLogger::write($request, $response);
    }
}
