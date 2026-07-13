<?php

namespace MahmoudMhamed\Logman\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Builds and writes request-log entries to the dedicated logging channel.
 *
 * Shared by the LogRequests middleware (automatic, global/group logging) and
 * the logman_log_request() helper / Logman::logRequest() facade call
 * (manual, on-demand logging from anywhere in the app).
 */
class RequestLogger
{
    /**
     * Write a single request entry.
     *
     * @param  bool  $respectFilters  When true (the middleware path) the enabled
     *   master switch and the skip filters are honoured. When false (an explicit
     *   manual call) they are bypassed — the developer asked for this entry.
     */
    public static function write(Request $request, ?Response $response = null, bool $respectFilters = true): void
    {
        try {
            $config = config('logman.request_logging', []);

            if ($respectFilters) {
                if (empty($config['enabled'])) {
                    return;
                }

                if (static::shouldSkip($request, $config)) {
                    return;
                }
            }

            $response ??= new Response();

            $channel = $config['channel'] ?? 'logman_requests';
            $level = $config['level'] ?? 'info';

            static::ensureChannel($channel);

            Log::channel($channel)->log(
                $level,
                static::buildMessage($request, $response, $config),
                static::buildContext($request, $response, $config),
            );
        } catch (Throwable $e) {
            // Never let request logging break the application.
        }
    }

    /**
     * Auto-register the request-logging channel if the app hasn't defined it.
     *
     * The service provider only injects the channel when request logging is
     * enabled globally; a manual logman_log_request() call may run while it's
     * disabled, so make sure the channel exists before writing.
     */
    protected static function ensureChannel(string $channelName): void
    {
        if (config("logging.channels.{$channelName}") !== null) {
            return;
        }

        $channelConfig = config('logman.request_channel_config', []);

        if (! empty($channelConfig)) {
            config(["logging.channels.{$channelName}" => $channelConfig]);
        }
    }

    protected static function shouldSkip(Request $request, array $config): bool
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

        // URL allowlist: when either "only" (path patterns) or "only_containing"
        // (URL substrings) is configured, a request must satisfy at least one of
        // them to be logged. Empty allowlists impose no restriction.
        if (static::failsAllowlist($request, $config)) {
            return true;
        }

        return false;
    }

    /**
     * Returns true when an allowlist is configured and the request matches none
     * of its entries (so it should be skipped).
     */
    protected static function failsAllowlist(Request $request, array $config): bool
    {
        $only = array_filter($config['only'] ?? [], fn ($v) => $v !== '' && $v !== null);
        $onlyContaining = array_filter($config['only_containing'] ?? [], fn ($v) => $v !== '' && $v !== null);

        if (empty($only) && empty($onlyContaining)) {
            return false;
        }

        $path = $request->path();

        foreach ($only as $pattern) {
            if (Str::is($pattern, $path) || Str::is(ltrim($pattern, '/'), ltrim($path, '/'))) {
                return false;
            }
        }

        $url = (string) $request->fullUrl();

        foreach ($onlyContaining as $needle) {
            if (Str::contains($url, (string) $needle)) {
                return false;
            }
        }

        return true;
    }

    protected static function buildMessage(Request $request, Response $response, array $config): string
    {
        $message = strtoupper($request->method()).' /'.ltrim($request->path(), '/');

        if (! empty($config['log_response_status'])) {
            $message .= ' → '.$response->getStatusCode();
        }

        $message .= ' ('.static::duration().')';

        return $message;
    }

    protected static function buildContext(Request $request, Response $response, array $config): array
    {
        $max = (int) ($config['max_payload_length'] ?? 8000);

        $context = [
            'method' => strtoupper($request->method()),
            'url' => (string) $request->fullUrl(),
            'path' => (string) $request->path(),
            'ip' => (string) $request->ip(),
            'duration' => static::duration(),
        ];

        if (! empty($config['log_response_status'])) {
            $context['status'] = $response->getStatusCode();
        }

        if ($user = static::currentUser()) {
            $context['user'] = $user;
        }

        if (! empty($config['log_query'])) {
            $context['query'] = static::truncate(Sanitizer::mask($request->query() ?? []), $max);
        }

        if (! empty($config['log_body']) && static::shouldLogBody($request, $config)) {
            $context['body'] = static::truncate(Sanitizer::mask($request->except(static::fileKeys($request))), $max);
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
            $responseBody = static::responseBody($response, $max);
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
    protected static function responseBody(Response $response, int $max)
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
                return static::truncate(Sanitizer::mask($decoded), $max);
            }
        }

        if ($max > 0 && strlen($content) > $max) {
            return substr($content, 0, $max).'… [truncated '.strlen($content).' bytes]';
        }

        return $content;
    }

    protected static function shouldLogBody(Request $request, array $config): bool
    {
        $ignore = array_map('strtoupper', $config['body_ignore_methods'] ?? []);

        return ! in_array(strtoupper($request->method()), $ignore, true);
    }

    /**
     * File input keys are excluded from the logged body (we don't log binaries).
     *
     * @return array<int, string>
     */
    protected static function fileKeys(Request $request): array
    {
        try {
            return array_keys($request->files->all());
        } catch (Throwable $e) {
            return [];
        }
    }

    protected static function currentUser(): ?array
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

    protected static function duration(): string
    {
        return defined('LARAVEL_START')
            ? round((microtime(true) - LARAVEL_START) * 1000).' ms'
            : '-';
    }

    /**
     * Guard against giant payloads: if the JSON-encoded value is larger than
     * $max characters, replace it with a truncation notice.
     */
    protected static function truncate(array $data, int $max): array
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
