<?php

namespace Mhamed\Logman;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Mhamed\Logman\Channels\ChannelInterface;
use Mhamed\Logman\Channels\DiscordChannel;
use Mhamed\Logman\Channels\MailChannel;
use Mhamed\Logman\Channels\SlackChannel;
use Mhamed\Logman\Channels\TelegramChannel;
use Mhamed\Logman\Services\MuteService;
use Throwable;

class LogmanService
{
    protected static array $drivers = [
        'slack' => SlackChannel::class,
        'telegram' => TelegramChannel::class,
        'discord' => DiscordChannel::class,
        'mail' => MailChannel::class,
    ];

    public function logException(Throwable $throwable): void
    {
        try {
            if (app()->isProduction() && !config('logman.enable_production')) {
                return;
            }
            if (app()->isLocal() && !config('logman.enable_local')) {
                return;
            }
            if ($this->shouldIgnore($throwable)) {
                return;
            }

            $payload = $this->buildPayload($throwable);

            foreach ($this->getEnabledChannels(exceptionsOnly: true) as $channel) {
                $channel->sendException($payload);
            }
        } catch (Throwable $e) {
            // Silently fail to prevent infinite error loops
        }
    }

    public function sendInfo(string $message): void
    {
        try {
            $context = [
                'app' => config('app.name'),
                'env' => app()->environment(),
                'url' => (string) request()->fullUrl(),
                'previous_url' => (string) (request()->header('referer') ?: request()->header('referrer') ?: '-'),
            ];

            foreach ($this->getEnabledChannels() as $channel) {
                $channel->sendInfo($message, $context);
            }
        } catch (Throwable $e) {
            // Silently fail
        }
    }

    /**
     * @deprecated Use sendInfo() instead
     */
    public function slackLogInfo($message): void
    {
        $this->sendInfo($message);
    }

    /**
     * Register a custom channel driver.
     */
    public static function registerDriver(string $name, string $class): void
    {
        static::$drivers[$name] = $class;
    }

    // ─── Channel Resolution ────────────────────────────────────

    /**
     * @return ChannelInterface[]
     */
    protected function getEnabledChannels(bool $exceptionsOnly = false): array
    {
        $channels = [];
        $configured = config('logman.channels', []);

        foreach ($configured as $name => $settings) {
            if (empty($settings['enabled'])) {
                continue;
            }

            if ($exceptionsOnly && empty($settings['auto_report_exceptions'])) {
                continue;
            }

            $driverClass = $settings['driver'] ?? (static::$drivers[$name] ?? null);

            if (is_string($driverClass) && class_exists($driverClass)) {
                $instance = new $driverClass();
                if ($instance instanceof ChannelInterface) {
                    $channels[] = $instance;
                }
            }
        }

        return $channels;
    }

    // ─── Payload Builder ───────────────────────────────────────

    protected function buildPayload(Throwable $throwable): array
    {
        $suppressedCount = 0;
        try {
            $muteService = app(MuteService::class);
            $suppressedCount = $muteService->getPreviousBlockedCount($throwable);
        } catch (Throwable $e) {
            // ignore
        }

        return [
            'app' => config('app.name'),
            'env' => app()->environment(),
            'suppressed_count' => $suppressedCount,
            'exception' => $this->collectException($throwable),
            'auth' => $this->collectAuth(),
            'request' => $this->collectRequest(),
            'cli' => $this->collectCli(),
            'job' => $this->collectJob($throwable),
            'queries' => $this->collectQueries(),
            'environment' => $this->collectEnvironment(),
        ];
    }

    protected function collectException(Throwable $throwable): array
    {
        $file = $throwable->getFile();

        try {
            $base = base_path();
        } catch (Throwable $e) {
            $base = null;
        }
        if (is_string($base) && $base !== '' && strpos($file, $base) === 0) {
            $relative = substr($file, strlen($base));
            $relative = ltrim($relative, DIRECTORY_SEPARATOR . '/\\');
            $file = $relative === '' ? '.' : $relative;
        }

        $previous = $throwable->getPrevious();

        $traceString = method_exists($throwable, 'getTraceAsString') ? $throwable->getTraceAsString() : '';

        return [
            'class' => get_class($throwable),
            'message' => (string) $throwable->getMessage(),
            'file' => $file,
            'line' => $throwable->getLine(),
            'code' => method_exists($throwable, 'getCode') ? $throwable->getCode() : null,
            'previous' => $previous ? (get_class($previous) . ': ' . $previous->getMessage()) : 'None',
            'trace' => mb_substr($traceString, 0, 2000),
        ];
    }

    protected function collectAuth(): ?array
    {
        try {
            $guards = array_keys(config('auth.guards', []));
        } catch (Throwable $e) {
            return null;
        }

        foreach ($guards as $guard) {
            try {
                if (Auth::guard($guard)->check()) {
                    $user = Auth::guard($guard)->user();
                    return [
                        'name' => (string) data_get($user, 'name'),
                        'id' => data_get($user, 'id'),
                        'email' => (string) data_get($user, 'email'),
                        'guard' => $guard,
                    ];
                }
            } catch (Throwable $e) {
                continue;
            }
        }

        return null;
    }

    protected function collectRequest(): ?array
    {
        if (app()->runningInConsole()) {
            return null;
        }

        try {
            $request = request();
        } catch (Throwable $e) {
            return null;
        }

        if (!method_exists($request, 'fullUrl')) {
            return null;
        }

        $route = $request->route();
        $routeName = is_object($route) && method_exists($route, 'getName') ? ($route->getName() ?: '-') : '-';
        $action = is_object($route) && method_exists($route, 'getActionName') ? $route->getActionName() : '-';
        $routeParams = is_object($route) && method_exists($route, 'parameters') ? $route->parameters() : [];

        $files = [];
        if ($request->files) {
            foreach ($request->files->all() as $key => $file) {
                if (is_array($file)) {
                    $files[$key] = array_map(function ($f) {
                        return method_exists($f, 'getClientOriginalName') ? $f->getClientOriginalName() : (string) $f;
                    }, $file);
                } else {
                    $files[$key] = method_exists($file, 'getClientOriginalName') ? $file->getClientOriginalName() : (string) $file;
                }
            }
        }

        return [
            'method' => strtoupper((string) $request->method()),
            'url' => (string) $request->fullUrl(),
            'path' => (string) $request->path(),
            'ip' => (string) $request->ip(),
            'host' => (string) $request->getHost(),
            'route' => $routeName,
            'action' => is_string($action) ? $action : '-',
            'duration' => defined('LARAVEL_START')
                ? round((microtime(true) - LARAVEL_START) * 1000) . ' ms'
                : '-',
            'locale' => method_exists($request, 'getLocale') ? (string) $request->getLocale() : '',
            'route_params' => $routeParams,
            'headers' => [
                'User-Agent' => (string) $request->header('User-Agent'),
                'Referer' => (string) ($request->header('referer') ?: $request->header('referrer')),
                'Content-Type' => (string) $request->header('Content-Type'),
                'Accept' => (string) $request->header('Accept'),
                'X-Requested-With' => (string) $request->header('X-Requested-With'),
                'X-Forwarded-For' => (string) $request->header('X-Forwarded-For'),
            ],
            'query' => $request->query() ?? [],
            'body' => $request->request?->all() ?? $request->all(),
            'files' => $files,
        ];
    }

    protected function collectCli(): ?array
    {
        if (!app()->runningInConsole()) {
            return null;
        }

        $argv = $_SERVER['argv'] ?? [];

        return [
            'command' => implode(' ', $argv) ?: '-',
        ];
    }

    protected function collectJob(Throwable $throwable): ?array
    {
        try {
            $job = null;

            if (method_exists($throwable, 'getJob')) {
                $job = $throwable->getJob();
            } elseif (property_exists($throwable, 'job')) {
                $job = $throwable->job;
            }

            if (!$job) {
                return null;
            }

            return [
                'name' => method_exists($job, 'resolveName') ? $job->resolveName() : get_class($job),
                'queue' => method_exists($job, 'getQueue') ? ($job->getQueue() ?: '-') : '-',
                'connection' => method_exists($job, 'getConnectionName') ? ($job->getConnectionName() ?: '-') : '-',
                'attempts' => method_exists($job, 'attempts') ? $job->attempts() : '-',
            ];
        } catch (Throwable $e) {
            return null;
        }
    }

    protected function collectQueries(): array
    {
        try {
            $queryLog = DB::getQueryLog();

            if (empty($queryLog)) {
                return [];
            }

            return array_map(function ($q) {
                $bindings = $q['bindings'] ?? [];
                $bindStr = !empty($bindings)
                    ? ' [' . implode(', ', array_map(fn($b) => is_null($b) ? 'NULL' : (string) $b, $bindings)) . ']'
                    : '';

                return [
                    'time' => $q['time'] ?? '?',
                    'sql' => $q['query'] ?? '',
                    'bindings' => $bindings,
                    'bindings_str' => $bindStr,
                ];
            }, array_slice($queryLog, -5));
        } catch (Throwable $e) {
            return [];
        }
    }

    protected function collectEnvironment(): array
    {
        $gitCommit = '-';
        try {
            $base = base_path();
            $headFile = $base . '/.git/HEAD';
            if (is_file($headFile)) {
                $head = trim(file_get_contents($headFile));
                if (str_starts_with($head, 'ref: ')) {
                    $refFile = $base . '/.git/' . substr($head, 5);
                    $gitCommit = is_file($refFile) ? substr(trim(file_get_contents($refFile)), 0, 8) : '-';
                } else {
                    $gitCommit = substr($head, 0, 8);
                }
            }
        } catch (Throwable $e) {
            // ignore
        }

        return [
            'env' => app()->environment(),
            'time' => now()->toDateTimeString(),
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'memory' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
            'hostname' => gethostname() ?: '-',
            'git_commit' => $gitCommit,
            'app_url' => config('app.url'),
        ];
    }

    // ─── Filtering ─────────────────────────────────────────────

    protected function shouldIgnore(Throwable $throwable): bool
    {
        $ignoredClasses = config('logman.ignore', []);

        foreach ($ignoredClasses as $class) {
            if ($throwable instanceof $class) {
                return true;
            }
        }

        try {
            $muteService = app(MuteService::class);

            if ($muteService->isMuted($throwable)) {
                return true;
            }

            if ($muteService->isThrottled($throwable)) {
                return true;
            }

            if ($muteService->isRateLimited($throwable)) {
                return true;
            }
        } catch (Throwable $e) {
            // Silently fail
        }

        return false;
    }
}
