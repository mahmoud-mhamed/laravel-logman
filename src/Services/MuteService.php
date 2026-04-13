<?php

namespace Mhamed\Logman\Services;

use Illuminate\Support\Facades\File;
use Throwable;

class MuteService
{
    protected string $storagePath;
    protected string $mutesFile;
    protected string $rateLimitsFile;
    protected string $throttlesFile;

    // In-memory cache - each file loaded once per request
    protected ?array $cachedMutes = null;
    protected ?array $cachedRateLimits = null;
    protected ?array $cachedThrottles = null;

    // Track dirty state for deferred writes
    protected bool $mutesDirty = false;
    protected bool $rateLimitsDirty = false;
    protected bool $throttlesDirty = false;
    protected bool $shutdownRegistered = false;

    /** Maximum JSON file size to read (5 MB). Files exceeding this are reset. */
    protected const MAX_JSON_FILE_SIZE = 5 * 1024 * 1024;

    public function __construct()
    {
        $this->storagePath = config('logman.storage_path', storage_path('logman'));
        $this->mutesFile = $this->storagePath . '/mutes.json';
        $this->rateLimitsFile = $this->storagePath . '/rate_limits.json';
        $this->throttlesFile = $this->storagePath . '/throttles.json';
    }

    // ─── Safe File Read ──────────────────────────────────────

    /**
     * Read and decode a JSON file safely.
     * Returns empty array if file is missing, too large, or corrupted.
     * Resets oversized files to prevent future blocking.
     */
    protected function safeReadJson(string $path): array
    {
        if (!File::exists($path)) {
            return [];
        }

        $size = filesize($path);
        if ($size > self::MAX_JSON_FILE_SIZE) {
            // File too large — reset it to prevent blocking requests
            $this->writeFile($path, []);
            return [];
        }

        $data = json_decode(File::get($path), true);

        return is_array($data) ? $data : [];
    }

    // ─── Deferred Write ───────────────────────────────────────

    protected function registerShutdown(): void
    {
        if ($this->shutdownRegistered) {
            return;
        }

        $this->shutdownRegistered = true;

        // Use Laravel's terminating callback if available, otherwise register_shutdown_function
        try {
            app()->terminating(function () {
                $this->flushAll();
            });
        } catch (Throwable $e) {
            register_shutdown_function(function () {
                $this->flushAll();
            });
        }
    }

    public function flushAll(): void
    {
        if ($this->mutesDirty && $this->cachedMutes !== null) {
            $this->mergeAndWrite($this->mutesFile, $this->cachedMutes, fn($item) => $item['id'], true);
            $this->mutesDirty = false;
        }

        if ($this->rateLimitsDirty && $this->cachedRateLimits !== null) {
            $this->mergeAndWriteAssoc($this->rateLimitsFile, $this->cachedRateLimits);
            $this->rateLimitsDirty = false;
        }

        if ($this->throttlesDirty && $this->cachedThrottles !== null) {
            $this->mergeAndWrite($this->throttlesFile, $this->cachedThrottles, fn($item) => $item['id'], true);
            $this->throttlesDirty = false;
        }
    }

    /**
     * Merge indexed arrays (mutes, throttles) with disk state before writing.
     * Lock → read latest from disk → merge (local wins by key) → atomic write.
     */
    protected function mergeAndWrite(string $path, array $localData, callable $keyFn, bool $pretty = false): void
    {
        $this->ensureStorageExists();

        $handle = fopen($path, 'c+');
        if (!$handle || !flock($handle, LOCK_EX)) {
            if ($handle) fclose($handle);
            $this->writeFile($path, $localData, $pretty);
            return;
        }

        $diskContent = stream_get_contents($handle);
        $diskData = json_decode($diskContent, true) ?: [];

        // Merge: local entries win by key
        $merged = [];
        $seen = [];
        foreach ($localData as $item) {
            $key = $keyFn($item);
            $merged[] = $item;
            $seen[$key] = true;
        }
        foreach ($diskData as $item) {
            $key = $keyFn($item);
            if (!isset($seen[$key])) {
                $merged[] = $item;
            }
        }

        $flags = $pretty
            ? JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            : 0;
        $json = json_encode(array_values($merged), $flags);

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, $json);
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    /**
     * Merge associative arrays (rate limits) with disk state before writing.
     */
    protected function mergeAndWriteAssoc(string $path, array $localData): void
    {
        $this->ensureStorageExists();

        $handle = fopen($path, 'c+');
        if (!$handle || !flock($handle, LOCK_EX)) {
            if ($handle) fclose($handle);
            $this->writeFile($path, $localData);
            return;
        }

        $diskContent = stream_get_contents($handle);
        $diskData = json_decode($diskContent, true) ?: [];

        // Merge: local keys win
        $merged = array_merge($diskData, $localData);

        $json = json_encode($merged);

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, $json);
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    protected function writeFile(string $path, array $data, bool $pretty = false): void
    {
        $this->ensureStorageExists();
        $flags = $pretty
            ? JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            : 0;
        $json = json_encode($data, $flags);

        // Atomic write: temp file + rename (safe against crash between truncate and write)
        $tmpPath = $path . '.tmp.' . getmypid();
        if (file_put_contents($tmpPath, $json, LOCK_EX) !== false) {
            rename($tmpPath, $path);
        } else {
            @unlink($tmpPath);
        }
    }

    // ─── Mute Operations ───────────────────────────────────────

    public function getMutes(): array
    {
        if ($this->cachedMutes !== null) {
            return $this->cachedMutes;
        }

        $mutes = $this->safeReadJson($this->mutesFile);

        // Clean expired mutes
        $now = now()->toIso8601String();
        $active = array_filter($mutes, fn($m) => $m['muted_until'] > $now);

        if (count($active) !== count($mutes)) {
            $this->cachedMutes = array_values($active);
            $this->mutesDirty = true;
            $this->registerShutdown();
        } else {
            $this->cachedMutes = array_values($mutes);
        }

        return $this->cachedMutes;
    }

    public function mute(string $exceptionClass, ?string $messagePattern, string $duration, ?string $reason = null): array
    {
        $mutes = $this->getMutes();

        $mutedUntil = $this->resolveDuration($duration);

        $entry = [
            'id' => md5($exceptionClass . ($messagePattern ?? '') . microtime()),
            'exception_class' => $exceptionClass,
            'message_pattern' => $messagePattern,
            'muted_until' => $mutedUntil->toIso8601String(),
            'muted_until_human' => $mutedUntil->diffForHumans(),
            'reason' => $reason,
            'hit_count' => 0,
            'created_at' => now()->toIso8601String(),
        ];

        $mutes[] = $entry;
        $this->cachedMutes = $mutes;
        $this->saveMutesNow($mutes); // Immediate write for user actions

        return $entry;
    }

    public function unmute(string $id): bool
    {
        $mutes = $this->getMutes();
        $filtered = array_filter($mutes, fn($m) => $m['id'] !== $id);

        if (count($filtered) === count($mutes)) {
            return false;
        }

        $this->cachedMutes = array_values($filtered);
        $this->saveMutesNow($this->cachedMutes);
        return true;
    }

    public function extendMute(string $id, string $duration): bool
    {
        $mutes = $this->getMutes();

        foreach ($mutes as &$mute) {
            if ($mute['id'] === $id) {
                $mute['muted_until'] = $this->resolveDuration($duration)->toIso8601String();
                $this->cachedMutes = $mutes;
                $this->saveMutesNow($mutes);
                return true;
            }
        }

        return false;
    }

    public function isMuted(Throwable $throwable): bool
    {
        $mutes = $this->getMutes();
        if (empty($mutes)) {
            return false;
        }

        $class = get_class($throwable);
        $message = $throwable->getMessage();

        foreach ($mutes as &$mute) {
            $classMatch = $this->classMatches($class, $mute['exception_class'])
                || $mute['exception_class'] === $message
                || str_contains($message, $mute['exception_class'])
                || str_contains($mute['exception_class'], $message)
                || str_contains($mute['exception_class'], $class);
            $patternMatch = empty($mute['message_pattern'])
                || str_contains($message, $mute['message_pattern']);

            if ($classMatch && $patternMatch) {
                $mute['hit_count'] = ($mute['hit_count'] ?? 0) + 1;
                $this->cachedMutes = $mutes;
                $this->mutesDirty = true;
                $this->registerShutdown();
                return true;
            }
        }

        return false;
    }

    // ─── Throttle Operations ──────────────────────────────────

    public function getThrottles(): array
    {
        if ($this->cachedThrottles !== null) {
            return $this->cachedThrottles;
        }

        $throttles = $this->safeReadJson($this->throttlesFile);

        // Clean expired throttles (period ended and not reset)
        $now = now();
        $active = array_filter($throttles, function ($t) use ($now) {
            if (empty($t['period_start']) || empty($t['period_seconds'])) {
                return true;
            }
            $periodEnd = \Carbon\Carbon::parse($t['period_start'])->addSeconds($t['period_seconds']);
            // Keep if still within period, or if it was reset recently (within 24h of last period end)
            return $now->lt($periodEnd) || $now->diffInHours($periodEnd) < 24;
        });

        if (count($active) !== count($throttles)) {
            $this->cachedThrottles = array_values($active);
            $this->throttlesDirty = true;
            $this->registerShutdown();
        } else {
            $this->cachedThrottles = array_values($throttles);
        }

        return $this->cachedThrottles;
    }

    public function addThrottle(string $exceptionClass, ?string $messagePattern, int $maxHits, string $period, ?string $reason = null): array
    {
        $throttles = $this->getThrottles();

        $entry = [
            'id' => md5($exceptionClass . ($messagePattern ?? '') . microtime()),
            'exception_class' => $exceptionClass,
            'message_pattern' => $messagePattern,
            'max_hits' => $maxHits,
            'period' => $period,
            'period_seconds' => $this->periodToSeconds($period),
            'current_hits' => 0,
            'period_start' => now()->toIso8601String(),
            'reason' => $reason,
            'created_at' => now()->toIso8601String(),
        ];

        $throttles[] = $entry;
        $this->cachedThrottles = $throttles;
        $this->saveThrottlesNow($throttles);

        return $entry;
    }

    public function removeThrottle(string $id): bool
    {
        $throttles = $this->getThrottles();
        $filtered = array_filter($throttles, fn($t) => $t['id'] !== $id);

        if (count($filtered) === count($throttles)) {
            return false;
        }

        $this->cachedThrottles = array_values($filtered);
        $this->saveThrottlesNow($this->cachedThrottles);
        return true;
    }

    public function isThrottled(Throwable $throwable): bool
    {
        $throttles = $this->getThrottles();
        if (empty($throttles)) {
            return false;
        }

        $class = get_class($throwable);
        $message = $throwable->getMessage();
        $now = now();

        foreach ($throttles as &$throttle) {
            $classMatch = $this->classMatches($class, $throttle['exception_class'])
                || $throttle['exception_class'] === $message
                || str_contains($message, $throttle['exception_class'])
                || str_contains($throttle['exception_class'], $message)
                || str_contains($throttle['exception_class'], $class);
            $patternMatch = empty($throttle['message_pattern'])
                || str_contains($message, $throttle['message_pattern']);

            if (!$classMatch || !$patternMatch) {
                continue;
            }

            $periodStart = \Carbon\Carbon::parse($throttle['period_start']);
            $periodEnd = $periodStart->copy()->addSeconds($throttle['period_seconds']);

            // Period expired - reset
            if ($now->greaterThanOrEqualTo($periodEnd)) {
                $throttle['current_hits'] = 1;
                $throttle['period_start'] = $now->toIso8601String();
                $this->cachedThrottles = $throttles;
                $this->throttlesDirty = true;
                $this->registerShutdown();
                return false;
            }

            // Within period - check limit
            $throttle['current_hits'] = ($throttle['current_hits'] ?? 0) + 1;
            $this->cachedThrottles = $throttles;
            $this->throttlesDirty = true;
            $this->registerShutdown();

            return $throttle['current_hits'] > $throttle['max_hits'];
        }

        return false;
    }

    protected function periodToSeconds(string $period): int
    {
        return match ($period) {
            '1h' => 3600,
            '6h' => 21600,
            '12h' => 43200,
            '1d' => 86400,
            '3d' => 259200,
            '1w' => 604800,
            '10d' => 864000,
            '1m' => 2592000,
            default => (int) $period ?: 86400,
        };
    }

    // ─── Rate Limiting ─────────────────────────────────────────

    public function isRateLimited(Throwable $throwable): bool
    {
        if (!config('logman.rate_limit.enabled', true)) {
            return false;
        }

        $cooldown = config('logman.rate_limit.cooldown_seconds', 10);
        $key = $this->getRateLimitKey($throwable);
        $limits = $this->getRateLimits();
        $now = time();

        if (isset($limits[$key]) && ($now - $limits[$key]['last_sent']) < $cooldown) {
            $limits[$key]['blocked_count'] = ($limits[$key]['blocked_count'] ?? 0) + 1;
            $this->cachedRateLimits = $limits;
            $this->rateLimitsDirty = true;
            $this->registerShutdown();
            return true;
        }

        // Store blocked count so it can be included in next message
        $previousBlocked = $limits[$key]['blocked_count'] ?? 0;
        $limits[$key] = [
            'last_sent' => $now,
            'blocked_count' => 0,
            'previous_blocked' => $previousBlocked,
        ];
        $this->cachedRateLimits = $limits;
        $this->rateLimitsDirty = true;
        $this->registerShutdown();

        return false;
    }

    public function getPreviousBlockedCount(Throwable $throwable): int
    {
        // Uses cached data - no extra file read
        $key = $this->getRateLimitKey($throwable);
        $limits = $this->getRateLimits();

        return $limits[$key]['previous_blocked'] ?? 0;
    }

    protected function getRateLimitKey(Throwable $throwable): string
    {
        return md5(
            get_class($throwable) . '|'
            . $throwable->getFile() . '|'
            . $throwable->getLine() . '|'
            . substr($throwable->getMessage(), 0, 100)
        );
    }

    protected function getRateLimits(): array
    {
        if ($this->cachedRateLimits !== null) {
            return $this->cachedRateLimits;
        }

        $limits = $this->safeReadJson($this->rateLimitsFile);

        // Clean old entries (older than 1 hour)
        $cutoff = time() - 3600;
        $cleaned = array_filter($limits, fn($l) => ($l['last_sent'] ?? 0) > $cutoff);

        // Cap at 1000 entries to prevent unbounded growth
        $maxEntries = 1000;
        if (count($cleaned) > $maxEntries) {
            uasort($cleaned, fn($a, $b) => ($b['last_sent'] ?? 0) - ($a['last_sent'] ?? 0));
            $cleaned = array_slice($cleaned, 0, $maxEntries, true);
        }

        if (count($cleaned) !== count($limits)) {
            $this->cachedRateLimits = $cleaned;
            $this->rateLimitsDirty = true;
            $this->registerShutdown();
        } else {
            $this->cachedRateLimits = $limits;
        }

        return $this->cachedRateLimits;
    }

    // ─── Immediate File Operations (for user actions) ─────────

    protected function saveMutesNow(array $mutes): void
    {
        $this->writeFile($this->mutesFile, $mutes, true);
        $this->cachedMutes = $mutes;
        $this->mutesDirty = false;
    }

    protected function saveThrottlesNow(array $throttles): void
    {
        $this->writeFile($this->throttlesFile, $throttles, true);
        $this->cachedThrottles = $throttles;
        $this->throttlesDirty = false;
    }

    // ─── Class Matching ──────────────────────────────────────

    /**
     * Match an actual exception class against a mute/throttle class pattern.
     *
     * Supports:
     *   - Exact FQN match: "App\Exceptions\CustomException"
     *   - Short class name: "CustomException" matches "App\Exceptions\CustomException"
     *   - Namespace prefix: "App\Exceptions\" matches anything under that namespace
     *   - Wildcard: "App\*Exception" matches "App\CustomException"
     */
    protected function classMatches(string $actualClass, string $muteClass): bool
    {
        // Exact match
        if ($actualClass === $muteClass) {
            return true;
        }

        // Short name match: "RuntimeException" matches "App\Exceptions\RuntimeException"
        $shortActual = class_basename($actualClass);
        if ($muteClass === $shortActual) {
            return true;
        }

        // Namespace prefix: "App\Exceptions\" matches everything under that namespace
        if (str_ends_with($muteClass, '\\') && str_starts_with($actualClass, $muteClass)) {
            return true;
        }

        // Wildcard: "App\*Exception" matches "App\CustomException"
        if (str_contains($muteClass, '*')) {
            $pattern = '/^' . str_replace('\\*', '.*', preg_quote($muteClass, '/')) . '$/';
            return (bool) preg_match($pattern, $actualClass);
        }

        return false;
    }

    // ─── Helpers ──────────────────────────────────────────────

    protected function ensureStorageExists(): void
    {
        if (!File::isDirectory($this->storagePath)) {
            File::makeDirectory($this->storagePath, 0755, true);
            File::put($this->storagePath . '/.gitignore', "*\n!.gitignore\n");
        }
    }

    protected function resolveDuration(string $duration): \Illuminate\Support\Carbon
    {
        return match ($duration) {
            '1h' => now()->addHour(),
            '6h' => now()->addHours(6),
            '12h' => now()->addHours(12),
            '1d' => now()->addDay(),
            '3d' => now()->addDays(3),
            '1w' => now()->addWeek(),
            '1m' => now()->addMonth(),
            default => now()->addHours((int) $duration ?: 1),
        };
    }
}
