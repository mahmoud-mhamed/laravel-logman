<?php

namespace Mhamed\Logman;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mhamed\Logman\Services\MuteService;
use Throwable;

class LogmanService
{
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

            $channel = config('logman.log_channel', 'slack');

            // Check if this error was suppressed before
            $suppressedNote = '';
            try {
                $muteService = app(MuteService::class);
                $previousBlocked = $muteService->getPreviousBlockedCount($throwable);
                if ($previousBlocked > 0) {
                    $suppressedNote = PHP_EOL . "⚠️ *This error was suppressed {$previousBlocked} time(s) since last report (rate limited).*" . PHP_EOL;
                }
            } catch (Throwable $e) {
                // ignore
            }

            Log::channel($channel)->error(
                $this->getErrorHeader() .
                $suppressedNote .
                $this->getAuthData() .
                $this->getErrorContent($throwable) .
                $this->getRequestBlock() .
                $this->getJobBlock($throwable) .
                $this->getQueryBlock() .
                $this->getEnvironmentData() .
                $this->getTraceBlock($throwable)
            );
        } catch (Throwable $e) {
            // Silently fail to prevent infinite error loops
        }
    }

    public function slackLogInfo($message): void
    {
        try {
            $app = config('app.name');
            $env = app()->environment();

            $request = request();
            $route = $request->route();
            $currentUrl = (string) $request->fullUrl();
            $previousUrl = (string) ($request->header('referer') ?: $request->header('referrer') ?: '');

            $context =
                "• App: {$app}" . PHP_EOL .
                "• Env: {$env}" . PHP_EOL .
                "• Current: {$currentUrl}" . PHP_EOL .
                "• Previous: " . ($previousUrl !== '' ? $previousUrl : '-');

            $channel = config('logman.log_channel', 'slack');

            Log::channel($channel)->info('ℹ️ ' . $message . PHP_EOL . $context);
        } catch (Throwable $e) {
            // Silently fail to prevent infinite error loops
        }
    }

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

    protected function getErrorHeader(): ?string
    {
        $app = config('app.name');
        $env = app()->environment();
        return '🚨 *' . $app . "* — Exception in `{$env}`" . PHP_EOL . '<!channel>';
    }

    protected function getErrorContent(Throwable $throwable): ?string
    {
        $maxChars = 2000;

        $code = method_exists($throwable, 'getCode') ? $throwable->getCode() : null;
        $message = (string) $throwable->getMessage();
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

        $line = $throwable->getLine();
        $previous = $throwable->getPrevious();
        $prevSummary = $previous ? (get_class($previous) . ': ' . $previous->getMessage()) : 'None';

        $class = get_class($throwable);

        $body = '🐞 Exception' . PHP_EOL .
            "• Class: `{$class}`" . PHP_EOL .
            '• Message:' . PHP_EOL .
            static::fenced("{$message}") . PHP_EOL .
            "• 📄 File: {$file}" . PHP_EOL .
            "• 🔢 Line: {$line}" . PHP_EOL .
            (is_null($code) ? '' : "• Code: {$code}" . PHP_EOL) .
            '• Previous: ' . $prevSummary;

        if (mb_strlen($body) > $maxChars) {
            $body = mb_substr($body, 0, $maxChars) . "\n…(truncated)";
        }

        return static::divider() . $body . PHP_EOL;
    }

    protected function getTraceBlock(Throwable $throwable): ?string
    {
        $traceString = method_exists($throwable, 'getTraceAsString') ? $throwable->getTraceAsString() : '';
        $traceString = mb_substr($traceString, 0, 2000);
        $body = '🧱 Trace' . PHP_EOL . static::fenced($traceString);
        return static::divider() . $body . PHP_EOL;
    }

    protected function getRequestBlock(): ?string
    {
        if (app()->runningInConsole()) {
            return $this->getCliBlock();
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
        $controller = is_string($action) ? $action : '-';
        $routeParams = is_object($route) && method_exists($route, 'parameters') ? $route->parameters() : [];

        $method = strtoupper((string) $request->method());
        $fullUrl = (string) $request->fullUrl();
        $path = (string) $request->path();
        $ip = (string) $request->ip();
        $host = (string) $request->getHost();
        $userAgent = (string) $request->header('User-Agent');
        $referer = (string) ($request->header('referer') ?: $request->header('referrer'));
        $contentType = (string) $request->header('Content-Type');
        $accept = (string) $request->header('Accept');
        $locale = method_exists($request, 'getLocale') ? (string) $request->getLocale() : '';

        $query = $request->query() ?? [];
        $body = $request->request?->all() ?? $request->all();
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

        $headersSubset = [
            'User-Agent' => $userAgent,
            'Referer' => $referer,
            'Content-Type' => $contentType,
            'Accept' => $accept,
            'X-Requested-With' => (string) $request->header('X-Requested-With'),
            'X-Forwarded-For' => (string) $request->header('X-Forwarded-For'),
        ];

        $duration = defined('LARAVEL_START')
            ? round((microtime(true) - LARAVEL_START) * 1000) . ' ms'
            : '-';

        $bodyText =
            '🧭 Request' . PHP_EOL .
            "• Method: {$method}" . PHP_EOL .
            "• URL: {$fullUrl}" . PHP_EOL .
            "• Path: /{$path}" . PHP_EOL .
            "• IP: {$ip}" . PHP_EOL .
            "• Host: {$host}" . PHP_EOL .
            "• Route: {$routeName}" . PHP_EOL .
            "• Action: {$controller}" . PHP_EOL .
            "• Duration: {$duration}" . PHP_EOL .
            (strlen($locale) ? "• Locale: {$locale}" . PHP_EOL : '') .
            (!empty($routeParams) ? '• Route Params:' . PHP_EOL . static::fenced(static::pretty($routeParams)) . PHP_EOL : '') .
            '• Headers:' . PHP_EOL . static::fenced(static::pretty($headersSubset)) . PHP_EOL .
            '🎒 Query Params:' . PHP_EOL . static::fenced(static::pretty($query)) . PHP_EOL .
            '📦 Body:' . PHP_EOL . static::fenced(static::pretty($body)) . PHP_EOL .
            '📁 Files:' . PHP_EOL . static::fenced(static::pretty($files));

        return static::divider() . $bodyText . PHP_EOL;
    }

    protected function getCliBlock(): ?string
    {
        $argv = $_SERVER['argv'] ?? [];
        $command = implode(' ', $argv) ?: '-';

        $body = '⌨️ CLI' . PHP_EOL .
            "• Command: `{$command}`";

        return static::divider() . $body . PHP_EOL;
    }

    protected function getJobBlock(Throwable $throwable): ?string
    {
        try {
            // Laravel wraps job exceptions in Illuminate\Queue\MaxAttemptsExceededException
            // or stores the job on the exception itself
            $job = null;

            if (method_exists($throwable, 'getJob')) {
                $job = $throwable->getJob();
            } elseif (property_exists($throwable, 'job')) {
                $job = $throwable->job;
            }

            if (!$job) {
                return null;
            }

            $jobName = method_exists($job, 'resolveName') ? $job->resolveName() : get_class($job);
            $queue = method_exists($job, 'getQueue') ? ($job->getQueue() ?: '-') : '-';
            $connection = method_exists($job, 'getConnectionName') ? ($job->getConnectionName() ?: '-') : '-';
            $attempts = method_exists($job, 'attempts') ? $job->attempts() : '-';

            $body = '📮 Job / Queue' . PHP_EOL .
                "• Job: `{$jobName}`" . PHP_EOL .
                "• Queue: {$queue}" . PHP_EOL .
                "• Connection: {$connection}" . PHP_EOL .
                "• Attempts: {$attempts}";

            return static::divider() . $body . PHP_EOL;
        } catch (Throwable $e) {
            return null;
        }
    }

    protected function getQueryBlock(): ?string
    {
        try {
            $queryLog = DB::getQueryLog();

            if (empty($queryLog)) {
                return null;
            }

            $lastQueries = array_slice($queryLog, -5);
            $formatted = array_map(function ($q) {
                $time = $q['time'] ?? '?';
                $sql = $q['query'] ?? '';
                $bindings = $q['bindings'] ?? [];
                $bindStr = !empty($bindings) ? ' [' . implode(', ', array_map(fn ($b) => is_null($b) ? 'NULL' : (string) $b, $bindings)) . ']' : '';
                return "({$time}ms) {$sql}{$bindStr}";
            }, $lastQueries);

            $body = '🗄️ Last Queries (' . count($lastQueries) . ')' . PHP_EOL .
                static::fenced(implode("\n", $formatted));

            return static::divider() . $body . PHP_EOL;
        } catch (Throwable $e) {
            return null;
        }
    }

    protected function getAuthData(): ?string
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
                    $user_id = data_get($user, 'id');
                    $user_name = data_get($user, 'name');
                    $user_email = data_get($user, 'email');

                    $body = '👤 Auth' . PHP_EOL .
                        "• Name: {$user_name}" . PHP_EOL .
                        "• Id: {$user_id}" . PHP_EOL .
                        "• Guard: {$guard}" . PHP_EOL .
                        "• Email: {$user_email}";

                    return static::divider() . $body . PHP_EOL;
                }
            } catch (Throwable $e) {
                continue;
            }
        }

        return null;
    }

    protected function getEnvironmentData(): ?string
    {
        $env = app()->environment();
        $now = now()->toDateTimeString();
        $php = PHP_VERSION;
        $appUrl = config('app.url');
        $laravelVersion = app()->version();
        $memory = round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB';
        $hostname = gethostname() ?: '-';

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

        $body = '🧰 Environment' . PHP_EOL .
            "• Env: {$env}" . PHP_EOL .
            "• Time: {$now}" . PHP_EOL .
            "• PHP: {$php}" . PHP_EOL .
            "• Laravel: {$laravelVersion}" . PHP_EOL .
            "• Memory Peak: {$memory}" . PHP_EOL .
            "• Server: {$hostname}" . PHP_EOL .
            "• Git Commit: `{$gitCommit}`" . PHP_EOL .
            (empty($appUrl) ? '' : "• App URL: {$appUrl}");
        return static::divider() . $body . PHP_EOL;
    }

    protected static function divider(): string
    {
        return PHP_EOL . '─────────────' . PHP_EOL;
    }

    protected static function fenced(string $text): string
    {
        $text = (string) $text;
        if ($text === '') {
            $text = '-';
        }
        return "```\n{$text}\n```";
    }

    protected static function pretty($data): string
    {
        if (is_string($data)) {
            return $data;
        }
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return print_r($data, true);
        }
        return mb_strlen($json) > 4000 ? (mb_substr($json, 0, 4000) . "\n…(truncated)") : $json;
    }
}
