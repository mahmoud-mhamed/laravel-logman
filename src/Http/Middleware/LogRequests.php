<?php

namespace MahmoudMhamed\Logman\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use MahmoudMhamed\Logman\Support\Sanitizer;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Logs every incoming HTTP request to a dedicated log channel.
 *
 * Registered globally (only when logman.request_logging.enabled is true) by the
 * service provider. The actual write happens in terminate() so it runs after
 * the response has been sent to the client and never adds latency.
 */
class LogRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            $config = config('logman.request_logging', []);

            if (empty($config['enabled'])) {
                return;
            }

            if ($this->shouldSkip($request, $config)) {
                return;
            }

            $channel = $config['channel'] ?? 'logman_requests';
            $level = $config['level'] ?? 'info';

            Log::channel($channel)->log(
                $level,
                $this->buildMessage($request, $response, $config),
                $this->buildContext($request, $response, $config),
            );
        } catch (Throwable $e) {
            // Never let request logging break the application.
        }
    }

    protected function shouldSkip(Request $request, array $config): bool
    {
        $methods = array_map('strtoupper', $config['methods'] ?? []);
        if (! empty($methods) && ! in_array(strtoupper($request->method()), $methods, true)) {
            return true;
        }

        $path = $request->path();

        // Always skip Logman's own viewer routes to avoid feedback noise.
        $prefix = trim((string) config('logman.viewer.route_prefix', 'logman'), '/');
        if ($prefix !== '' && ($path === $prefix || Str::startsWith($path, $prefix.'/'))) {
            return true;
        }

        foreach ($config['except'] ?? [] as $pattern) {
            if (Str::is($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    protected function buildMessage(Request $request, Response $response, array $config): string
    {
        $message = strtoupper($request->method()).' /'.ltrim($request->path(), '/');

        if (! empty($config['log_response_status'])) {
            $message .= ' → '.$response->getStatusCode();
        }

        $message .= ' ('.$this->duration().')';

        return $message;
    }

    protected function buildContext(Request $request, Response $response, array $config): array
    {
        $max = (int) ($config['max_payload_length'] ?? 8000);

        $context = [
            'method' => strtoupper($request->method()),
            'url' => (string) $request->fullUrl(),
            'path' => (string) $request->path(),
            'ip' => (string) $request->ip(),
            'duration' => $this->duration(),
        ];

        if (! empty($config['log_response_status'])) {
            $context['status'] = $response->getStatusCode();
        }

        if ($user = $this->currentUser()) {
            $context['user'] = $user;
        }

        if (! empty($config['log_query'])) {
            $context['query'] = $this->truncate(Sanitizer::mask($request->query() ?? []), $max);
        }

        if (! empty($config['log_body']) && $this->shouldLogBody($request, $config)) {
            $context['body'] = $this->truncate(Sanitizer::mask($request->except($this->fileKeys($request))), $max);
        }

        if (! empty($config['log_headers'])) {
            $context['headers'] = [
                'User-Agent' => (string) $request->header('User-Agent'),
                'Referer' => (string) ($request->header('referer') ?: $request->header('referrer')),
                'Content-Type' => (string) $request->header('Content-Type'),
                'Accept' => (string) $request->header('Accept'),
                'X-Requested-With' => (string) $request->header('X-Requested-With'),
                'X-Forwarded-For' => (string) $request->header('X-Forwarded-For'),
            ];
        }

        if (! empty($config['log_response_body'])) {
            $responseBody = $this->responseBody($response, $max);
            if ($responseBody !== null) {
                $context['response_body'] = $responseBody;
            }
        }

        return $context;
    }

    /**
     * Extract a loggable representation of the response body.
     *
     * Returns a masked array for JSON responses, a (truncated) string for other
     * text bodies, or null when the body isn't safely readable (streamed /
     * binary / file downloads).
     *
     * @return array<string, mixed>|string|null
     */
    protected function responseBody(Response $response, int $max)
    {
        // Streamed/binary/file responses return false from getContent() — the
        // is_string() guard below rejects them (nothing safe to read).
        $content = $response->getContent();
        if (! is_string($content) || $content === '') {
            return null;
        }

        $contentType = (string) $response->headers->get('Content-Type');

        if (str_contains($contentType, 'json')) {
            $decoded = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $this->truncate(Sanitizer::mask($decoded), $max);
            }
        }

        if ($max > 0 && strlen($content) > $max) {
            return substr($content, 0, $max).'… [truncated '.strlen($content).' bytes]';
        }

        return $content;
    }

    protected function shouldLogBody(Request $request, array $config): bool
    {
        $ignore = array_map('strtoupper', $config['body_ignore_methods'] ?? []);

        return ! in_array(strtoupper($request->method()), $ignore, true);
    }

    /**
     * File input keys are excluded from the logged body (we don't log binaries).
     *
     * @return array<int, string>
     */
    protected function fileKeys(Request $request): array
    {
        try {
            return array_keys($request->files->all());
        } catch (Throwable $e) {
            return [];
        }
    }

    protected function currentUser(): ?array
    {
        try {
            foreach (array_keys(config('auth.guards', [])) as $guard) {
                if (Auth::guard($guard)->check()) {
                    $user = Auth::guard($guard)->user();

                    return [
                        'id' => data_get($user, 'id'),
                        'email' => (string) data_get($user, 'email'),
                        'guard' => $guard,
                    ];
                }
            }
        } catch (Throwable $e) {
            // ignore
        }

        return null;
    }

    protected function duration(): string
    {
        return defined('LARAVEL_START')
            ? round((microtime(true) - LARAVEL_START) * 1000).' ms'
            : '-';
    }

    /**
     * Guard against giant payloads: if the JSON-encoded value is larger than
     * $max characters, replace it with a truncation notice.
     */
    protected function truncate(array $data, int $max): array
    {
        if ($max <= 0) {
            return $data;
        }

        $encoded = json_encode($data);
        if ($encoded !== false && strlen($encoded) > $max) {
            return ['_truncated' => 'payload exceeded '.$max.' characters ('.strlen($encoded).' bytes)'];
        }

        return $data;
    }
}
