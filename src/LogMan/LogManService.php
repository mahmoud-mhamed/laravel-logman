<?php

namespace Mhamed\Logman\LogMan;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use SplFileInfo;

class LogManService
{
    protected string $storagePath;
    protected string $pattern;
    protected int $maxFileSize;
    protected int $perPage;
    protected string $cachePrefix;

    public function __construct()
    {
        $config = config('logman.viewer');
        $this->storagePath = $config['storage_path'] ?? storage_path('logs');
        $this->pattern = $config['pattern'] ?? '*.log';
        $this->maxFileSize = $config['max_file_size'] ?? 50 * 1024 * 1024;
        $this->perPage = $config['per_page'] ?? 25;
        $this->cachePrefix = 'logman:';
    }

    // ─── File Operations ────────────────────────────────────────

    public function getFiles(): Collection
    {
        if (!File::isDirectory($this->storagePath)) {
            return collect();
        }

        return collect(File::glob($this->storagePath . '/' . $this->pattern))
            ->map(fn(string $path) => new SplFileInfo($path))
            ->sortByDesc(fn(SplFileInfo $file) => $file->getMTime())
            ->values()
            ->map(fn(SplFileInfo $file) => [
                'name' => $file->getFilename(),
                'path' => $file->getRealPath(),
                'size' => $file->getSize(),
                'size_formatted' => $this->formatBytes($file->getSize()),
                'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                'modified_human' => $this->timeAgo($file->getMTime()),
            ]);
    }

    public function deleteFile(string $filename): bool
    {
        $path = $this->safePath($filename);
        if ($path && File::exists($path)) {
            $this->clearFileCache($filename);
            return File::delete($path);
        }
        return false;
    }

    public function deleteMultiple(array $filenames): int
    {
        $count = 0;
        foreach ($filenames as $filename) {
            if ($this->deleteFile($filename)) {
                $count++;
            }
        }
        return $count;
    }

    public function clearFile(string $filename): bool
    {
        $path = $this->safePath($filename);
        if ($path && File::exists($path)) {
            $this->clearFileCache($filename);
            return File::put($path, '') !== false;
        }
        return false;
    }

    public function downloadPath(string $filename): ?string
    {
        $path = $this->safePath($filename);
        return ($path && File::exists($path)) ? $path : null;
    }

    // ─── Log Parsing ────────────────────────────────────────────

    public function getLogEntries(
        string  $filename,
        ?string $level = null,
        ?string $search = null,
        bool    $isRegex = false,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $timeFrom = null,
        ?string $timeTo = null,
        string  $sortDirection = 'desc',
        int     $page = 1,
        ?int    $perPage = null,
        ?string $reviewFilter = null,
        ?string $reviewStatus = null,
        ?string $muteFilter = null,
        array   $activeMutes = [],
        array   $activeThrottles = [],
        ?string $bookmarkFilter = null,
        array   $bookmarkedHashes = [],
    ): array
    {
        $perPage = $perPage ?? $this->perPage;
        $path = $this->safePath($filename);

        if (!$path || !File::exists($path)) {
            return $this->emptyResult($perPage, $page);
        }

        if (File::size($path) > $this->maxFileSize) {
            return ['entries' => new LengthAwarePaginator([], 0, $perPage), 'too_large' => true, 'level_counts' => [], 'has_multiple_dates' => false];
        }

        $entries = $this->getCachedEntries($filename, $path);

        // Apply review data from separate storage
        $this->applyReviews($entries, $filename);

        // Detect if file has multiple dates
        $hasMultipleDates = $this->hasMultipleDates($entries);

        // Level counts before filtering
        $levelCounts = $this->countLevels($entries);

        // Apply filters
        if ($level && $level !== 'all') {
            $entries = array_filter($entries, fn($e) => $e['level'] === strtolower($level));
        }

        if ($search) {
            $entries = $this->applySearch($entries, $search, $isRegex);
        }

        if ($dateFrom) {
            $entries = array_filter($entries, fn($e) => substr($e['date'], 0, 10) >= $dateFrom);
        }

        if ($dateTo) {
            $entries = array_filter($entries, fn($e) => substr($e['date'], 0, 10) <= $dateTo);
        }

        if ($timeFrom) {
            $entries = array_filter($entries, fn($e) => substr($e['date'], 11, 8) >= $timeFrom);
        }

        if ($timeTo) {
            $entries = array_filter($entries, fn($e) => substr($e['date'], 11, 8) <= $timeTo);
        }

        if ($reviewFilter === 'reviewed') {
            $entries = array_filter($entries, fn($e) => !empty($e['reviewed']));
            if ($reviewStatus) {
                $entries = array_filter($entries, fn($e) => ($e['review_status'] ?? 'reviewed') === $reviewStatus);
            }
        } elseif ($reviewFilter === 'unreviewed') {
            $entries = array_filter($entries, fn($e) => empty($e['reviewed']));
        }

        // Mark muted entries
        if (!empty($activeMutes)) {
            foreach ($entries as &$entry) {
                $muteInfo = $this->findEntryMute($entry, $activeMutes);
                $entry['is_muted'] = $muteInfo !== null;
                $entry['mute_info'] = $muteInfo;
            }
            unset($entry);
        }

        // Mark throttled entries
        if (!empty($activeThrottles)) {
            foreach ($entries as &$entry) {
                $throttleInfo = $this->findEntryThrottle($entry, $activeThrottles);
                $entry['is_throttled'] = $throttleInfo !== null;
                $entry['throttle_info'] = $throttleInfo;
            }
            unset($entry);
        }

        if ($muteFilter === 'muted') {
            $entries = array_filter($entries, fn($e) => !empty($e['is_muted']));
        } elseif ($muteFilter === 'unmuted') {
            $entries = array_filter($entries, fn($e) => empty($e['is_muted']));
        }

        // Bookmark filter
        if ($bookmarkFilter === 'bookmarked' && !empty($bookmarkedHashes)) {
            $bmSet = array_flip($bookmarkedHashes);
            $entries = array_filter($entries, fn($e) => isset($bmSet[$e['hash']]));
        } elseif ($bookmarkFilter === 'not_bookmarked') {
            $bmSet = array_flip($bookmarkedHashes);
            $entries = array_filter($entries, fn($e) => !isset($bmSet[$e['hash']]));
        }

        $entries = array_values($entries);

        // Paginate — entries are stored oldest-first (chronological).
        // For desc (newest first), slice from the end to avoid reversing the full array.
        $total = count($entries);
        if ($sortDirection === 'desc') {
            $offset = max(0, $total - ($page * $perPage));
            $length = min($perPage, $total - (($page - 1) * $perPage));
            $items = array_reverse(array_slice($entries, $offset, $length));
        } else {
            $offset = ($page - 1) * $perPage;
            $items = array_slice($entries, $offset, $perPage);
        }

        $paginator = new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);

        return [
            'entries' => $paginator,
            'too_large' => false,
            'level_counts' => $levelCounts,
            'has_multiple_dates' => $hasMultipleDates,
        ];
    }

    protected function hasMultipleDates(array $entries): bool
    {
        if (count($entries) < 2) return false;

        $dates = [];
        foreach ($entries as $entry) {
            $date = substr($entry['date'], 0, 10);
            $dates[$date] = true;
            if (count($dates) > 1) return true;
        }

        return false;
    }

    protected function getCachedEntries(string $filename, string $path): array
    {
        $mtime = File::lastModified($path);
        $cacheKey = $this->cachePrefix . md5($filename . $mtime);

        return Cache::remember($cacheKey, 300, function () use ($path) {
            return $this->parseLogFile($path);
        });
    }

    public function clearFileCache(string $filename): void
    {
        $path = $this->safePath($filename);
        if ($path && File::exists($path)) {
            $mtime = File::lastModified($path);
            Cache::forget($this->cachePrefix . md5($filename . $mtime));
        }
    }

    public function clearAllCache(): void
    {
        foreach ($this->getFiles() as $file) {
            $this->clearFileCache($file['name']);
        }
    }

    /**
     * Stream-parse a log file line-by-line instead of loading the entire file into memory.
     */
    protected function parseLogFile(string $path): array
    {
        $pattern = '/\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}\.?\d*[\+\-]?\d{0,4})\]\s+(\w+)\.(\w+):\s*(.*)/';
        $entries = [];
        $currentEntry = null;

        $handle = fopen($path, 'r');
        if (!$handle) {
            return [];
        }

        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\n\r");

            // Skip legacy #REVIEWED lines embedded in log files (reviews now stored separately)
            if (str_starts_with($line, '#REVIEWED ')) {
                continue;
            }

            if (preg_match($pattern, $line, $matches)) {
                if ($currentEntry !== null) {
                    $this->finalizeEntry($currentEntry);
                    $entries[] = $currentEntry;
                }

                $currentEntry = [
                    'date' => $matches[1],
                    'env' => $matches[2],
                    'level' => strtolower($matches[3]),
                    'message' => $matches[4],
                    'stack' => '',
                    'context' => '',
                    'level_class' => $this->getLevelClass($matches[3]),
                    'reviewed' => false,
                    'review_status' => null,
                    'review_note' => '',
                    'review_by' => '',
                    'review_at' => '',
                ];
            } elseif ($currentEntry !== null) {
                $currentEntry['stack'] .= $line . "\n";
            }
        }

        fclose($handle);

        if ($currentEntry !== null) {
            $this->finalizeEntry($currentEntry);
            $entries[] = $currentEntry;
        }

        // Return in chronological order (oldest first).
        // Sorting/reversing is handled at pagination time to avoid copying the full array.
        return $entries;
    }

    protected function finalizeEntry(array &$entry): void
    {
        $entry['hash'] = md5($entry['date'] . '|' . $entry['level'] . '|' . $entry['message']);
        $entry['stack'] = trim($entry['stack']);

        // Extract JSON context from stack trace
        if ($entry['stack'] && preg_match('/^(\{[\s\S]*?\})\s*$/m', $entry['stack'], $jsonMatch)) {
            $decoded = json_decode($jsonMatch[1], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $entry['context'] = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        // Extract exception file/line from message
        if (preg_match('/^(.+?)\s+in\s+(.+?):(\d+)$/', $entry['message'], $m)) {
            $entry['exception_message'] = $m[1];
            $entry['exception_file'] = $m[2];
            $entry['exception_line'] = (int) $m[3];
        }

        // Extract exception class from stack trace (e.g. [stacktrace] or first line with namespace\Class)
        $entry['exception_class'] = '';
        if ($entry['stack']) {
            // Look for patterns like "Namespace\ClassName:" or common exception class patterns
            if (preg_match('/^([A-Z][a-zA-Z0-9_\\\\]+(?:Exception|Error|Fault))/m', $entry['stack'], $cm)) {
                $entry['exception_class'] = $cm[1];
            } elseif (preg_match('/^([A-Z][a-zA-Z0-9_\\\\]+(?:\\\\[A-Z][a-zA-Z0-9_]+)+)/m', $entry['message'], $cm)) {
                $entry['exception_class'] = $cm[1];
            }
        }
        // Fallback: try extracting from message itself
        if (empty($entry['exception_class']) && preg_match('/^([A-Z][a-zA-Z0-9_\\\\]*(?:Exception|Error))/', $entry['message'], $cm)) {
            $entry['exception_class'] = $cm[1];
        }
    }

    protected function applySearch(array $entries, string $search, bool $isRegex): array
    {
        if ($isRegex) {
            // Use \x01 as delimiter to prevent delimiter injection
            $pattern = "\x01" . $search . "\x01iu";
            $regexValid = @preg_match($pattern, '') !== false;
            if ($regexValid) {
                $oldLimit = ini_get('pcre.backtrack_limit');
                ini_set('pcre.backtrack_limit', 10000);

                $filtered = array_filter($entries, function ($entry) use ($pattern) {
                    $text = $entry['message'] . ' ' . $entry['stack'];
                    return @preg_match($pattern, $text) === 1;
                });

                ini_set('pcre.backtrack_limit', $oldLimit);
                return $filtered;
            }
        }

        $search = mb_strtolower($search);
        $terms = array_filter(explode(' ', $search));

        return array_filter($entries, function ($entry) use ($terms) {
            $text = mb_strtolower($entry['message'] . ' ' . $entry['stack'] . ' ' . $entry['env']);
            foreach ($terms as $term) {
                if (!str_contains($text, $term)) {
                    return false;
                }
            }
            return true;
        });
    }

    // ─── Dashboard / Statistics ─────────────────────────────────

    public function getDashboardStats(): array
    {
        $files = $this->getFiles();
        $globalCounts = [];
        $perFileCounts = [];
        $totalEntries = 0;
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('yesterday'));
        $todayCounts = [];
        $todayTotal = 0;
        $yesterdayCounts = [];
        $yesterdayTotal = 0;

        // Single loop: collect global stats + today/yesterday in one pass
        foreach ($files as $file) {
            $path = $this->safePath($file['name']);
            if (!$path || !File::exists($path) || File::size($path) > $this->maxFileSize) {
                continue;
            }

            $entries = $this->getCachedEntries($file['name'], $path);
            $counts = $this->countLevels($entries);

            $perFileCounts[$file['name']] = [
                'counts' => $counts,
                'total' => array_sum($counts),
                'size' => $file['size_formatted'],
                'modified' => $file['modified'],
            ];

            foreach ($counts as $level => $count) {
                $globalCounts[$level] = ($globalCounts[$level] ?? 0) + $count;
            }

            $totalEntries += array_sum($counts);

            // Today + yesterday stats in the same loop
            foreach ($entries as $entry) {
                $entryDate = substr($entry['date'], 0, 10);
                $l = $entry['level'];

                if ($entryDate === $today) {
                    $todayCounts[$l] = ($todayCounts[$l] ?? 0) + 1;
                    $todayTotal++;
                } elseif ($entryDate === $yesterday) {
                    $yesterdayCounts[$l] = ($yesterdayCounts[$l] ?? 0) + 1;
                    $yesterdayTotal++;
                }
            }
        }

        // Comparison
        $comparison = [];
        if ($yesterdayTotal > 0) {
            $comparison['total'] = $this->calcChange($todayTotal, $yesterdayTotal);

            $todayErrors = ($todayCounts['error'] ?? 0) + ($todayCounts['critical'] ?? 0) + ($todayCounts['alert'] ?? 0) + ($todayCounts['emergency'] ?? 0);
            $yesterdayErrors = ($yesterdayCounts['error'] ?? 0) + ($yesterdayCounts['critical'] ?? 0) + ($yesterdayCounts['alert'] ?? 0) + ($yesterdayCounts['emergency'] ?? 0);
            $comparison['errors'] = $this->calcChange($todayErrors, $yesterdayErrors);
        }

        return [
            'files' => $files,
            'file_count' => $files->count(),
            'total_entries' => $totalEntries,
            'global_counts' => $globalCounts,
            'per_file_counts' => $perFileCounts,
            'percentages' => $totalEntries > 0
                ? array_map(fn($c) => round(($c / $totalEntries) * 100, 1), $globalCounts)
                : [],
            'chart_data' => $this->buildChartData($globalCounts),
            'today_counts' => $todayCounts,
            'today_total' => $todayTotal,
            'yesterday_counts' => $yesterdayCounts,
            'yesterday_total' => $yesterdayTotal,
            'comparison' => $comparison,
        ];
    }

    protected function buildChartData(array $counts): array
    {
        $colors = [
            'emergency' => '#7f1d1d',
            'alert' => '#991b1b',
            'critical' => '#dc2626',
            'error' => '#e11d48',
            'warning' => '#f59e0b',
            'notice' => '#06b6d4',
            'info' => '#3b82f6',
            'debug' => '#22c55e',
        ];

        $labels = [];
        $data = [];
        $bgColors = [];

        foreach ($counts as $level => $count) {
            if ($count > 0) {
                $labels[] = ucfirst($level);
                $data[] = $count;
                $bgColors[] = $colors[$level] ?? '#94a3b8';
            }
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'colors' => $bgColors,
        ];
    }

    protected function calcChange(int $current, int $previous): array
    {
        if ($previous === 0) {
            return ['pct' => $current > 0 ? 100 : 0, 'direction' => $current > 0 ? 'up' : 'same'];
        }
        $pct = round((($current - $previous) / $previous) * 100);
        $direction = $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'same');
        return ['pct' => abs($pct), 'direction' => $direction, 'previous' => $previous];
    }

    // ─── Helpers ────────────────────────────────────────────────

    protected function countLevels(array $entries): array
    {
        $counts = [];
        foreach ($entries as $entry) {
            $l = $entry['level'];
            $counts[$l] = ($counts[$l] ?? 0) + 1;
        }
        return $counts;
    }

    protected function getLevelClass(string $level): string
    {
        return match (strtolower($level)) {
            'emergency', 'alert', 'critical' => 'danger',
            'error' => 'error',
            'warning' => 'warning',
            'notice', 'info' => 'info',
            'debug' => 'debug',
            default => 'debug',
        };
    }

    protected function safePath(string $filename): ?string
    {
        $filename = basename($filename);
        $path = $this->storagePath . '/' . $filename;

        // Prevent directory traversal
        $realPath = realpath($path);
        $realStorage = realpath($this->storagePath);

        if ($realPath && $realStorage && str_starts_with($realPath, $realStorage)) {
            return $realPath;
        }

        // File might not exist yet (for checking before create)
        if (!$realPath && $realStorage) {
            return $realStorage . '/' . $filename;
        }

        return null;
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes === 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = (int) floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }

    protected function timeAgo(int $timestamp): string
    {
        $diff = time() - $timestamp;

        if ($diff < 60) return 'just now';
        if ($diff < 3600) return (int) ($diff / 60) . 'm ago';
        if ($diff < 86400) return (int) ($diff / 3600) . 'h ago';
        if ($diff < 604800) return (int) ($diff / 86400) . 'd ago';

        return date('M j', $timestamp);
    }

    // ─── Review Operations ────────────────────────────────────
    // Reviews are stored in a separate JSON file instead of modifying log files.
    // This avoids rewriting multi-MB log files for each review action.

    protected function getReviewsPath(): string
    {
        return config('logman.storage_path', storage_path('logman')) . '/reviews.json';
    }

    protected function loadReviews(): array
    {
        $path = $this->getReviewsPath();
        if (!File::exists($path)) {
            return [];
        }
        return json_decode(File::get($path), true) ?: [];
    }

    protected function saveReviews(array $reviews): bool
    {
        $path = $this->getReviewsPath();
        $dir = dirname($path);
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $json = json_encode($reviews, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $tmpPath = $path . '.tmp.' . getmypid();
        if (file_put_contents($tmpPath, $json, LOCK_EX) !== false) {
            return rename($tmpPath, $path);
        }
        @unlink($tmpPath);
        return false;
    }

    /**
     * Apply review data from the separate reviews file onto parsed entries.
     */
    protected function applyReviews(array &$entries, string $filename): void
    {
        $reviews = $this->loadReviews();
        if (empty($reviews)) {
            return;
        }

        foreach ($entries as &$entry) {
            $key = $filename . ':' . $entry['hash'];
            if (isset($reviews[$key])) {
                $r = $reviews[$key];
                $entry['reviewed'] = true;
                $entry['review_status'] = $r['status'] ?? 'reviewed';
                $entry['review_note'] = $r['note'] ?? '';
                $entry['review_by'] = $r['by'] ?? '';
                $entry['review_at'] = $r['at'] ?? '';
            }
        }
        unset($entry);
    }

    public function addReview(string $filename, string $hash, string $status = 'reviewed', string $note = '', ?string $by = null): bool
    {
        $reviews = $this->loadReviews();
        $key = $filename . ':' . $hash;

        $reviews[$key] = [
            'status' => $status,
            'note' => $note,
            'by' => $by ?? '',
            'at' => now()->toDateTimeString(),
        ];

        $this->clearFileCache($filename);
        return $this->saveReviews($reviews);
    }

    public function removeReview(string $filename, string $hash): bool
    {
        $reviews = $this->loadReviews();
        $key = $filename . ':' . $hash;

        if (!isset($reviews[$key])) {
            return false;
        }

        unset($reviews[$key]);
        $this->clearFileCache($filename);
        return $this->saveReviews($reviews);
    }

    // ─── Grouping ──────────────────────────────────────────────

    public function getGroupedEntries(string $filename): array
    {
        $path = $this->safePath($filename);
        if (!$path || !File::exists($path) || File::size($path) > $this->maxFileSize) {
            return [];
        }

        $entries = $this->getCachedEntries($filename, $path);
        $groups = [];

        foreach ($entries as $entry) {
            // Group by: exception class + file + line (from message)
            $groupKey = $entry['level'] . '|' . ($entry['exception_message'] ?? $entry['message']);
            if (isset($entry['exception_file'], $entry['exception_line'])) {
                $groupKey .= '|' . $entry['exception_file'] . ':' . $entry['exception_line'];
            }

            $key = md5($groupKey);

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'message' => $entry['exception_message'] ?? $entry['message'],
                    'level' => $entry['level'],
                    'level_class' => $entry['level_class'],
                    'file' => $entry['exception_file'] ?? null,
                    'line' => $entry['exception_line'] ?? null,
                    'count' => 0,
                    'first_seen' => $entry['date'],
                    'last_seen' => $entry['date'],
                    'hashes' => [],
                ];
            }

            $groups[$key]['count']++;
            $groups[$key]['last_seen'] = max($groups[$key]['last_seen'], $entry['date']);
            $groups[$key]['first_seen'] = min($groups[$key]['first_seen'], $entry['date']);
            if (count($groups[$key]['hashes']) < 5) {
                $groups[$key]['hashes'][] = $entry['hash'];
            }
        }

        // Sort by count descending
        usort($groups, fn($a, $b) => $b['count'] - $a['count']);

        return $groups;
    }

    // ─── Helpers ────────────────────────────────────────────────

    protected function findEntryMute(array $entry, array $mutes): ?array
    {
        $entryClass = $entry['exception_class'] ?? '';
        $entryMessage = $entry['exception_message'] ?? $entry['message'];

        foreach ($mutes as $mute) {
            $classMatch = $mute['exception_class'] === $entryClass
                || $mute['exception_class'] === $entryMessage
                || str_contains($entryMessage, $mute['exception_class']);

            $patternMatch = empty($mute['message_pattern'])
                || str_contains($entryMessage, $mute['message_pattern']);

            if ($classMatch && $patternMatch) {
                return $mute;
            }
        }

        return null;
    }

    protected function findEntryThrottle(array $entry, array $throttles): ?array
    {
        $entryClass = $entry['exception_class'] ?? '';
        $entryMessage = $entry['exception_message'] ?? $entry['message'];

        foreach ($throttles as $throttle) {
            $classMatch = $throttle['exception_class'] === $entryClass
                || $throttle['exception_class'] === $entryMessage
                || str_contains($entryMessage, $throttle['exception_class']);

            $patternMatch = empty($throttle['message_pattern'])
                || str_contains($entryMessage, $throttle['message_pattern']);

            if ($classMatch && $patternMatch) {
                return $throttle;
            }
        }

        return null;
    }

    public function findEntryByHash(string $filename, string $hash): ?array
    {
        $path = $this->safePath($filename);
        if (!$path || !File::exists($path) || File::size($path) > $this->maxFileSize) {
            return null;
        }

        $entries = $this->getCachedEntries($filename, $path);

        foreach ($entries as $entry) {
            if ($entry['hash'] === $hash) {
                return $entry;
            }
        }

        return null;
    }

    // ─── Bookmarks ──────────────────────────────────────────

    protected ?array $cachedBookmarks = null;

    protected function getBookmarksPath(): string
    {
        return config('logman.storage_path', storage_path('logman')) . '/bookmarks.json';
    }

    public function getBookmarks(): array
    {
        if ($this->cachedBookmarks !== null) {
            return $this->cachedBookmarks;
        }

        $path = $this->getBookmarksPath();
        if (!File::exists($path)) {
            $this->cachedBookmarks = [];
            return [];
        }

        $this->cachedBookmarks = json_decode(File::get($path), true) ?: [];
        return $this->cachedBookmarks;
    }

    protected int $maxBookmarks = 100;

    public function addBookmark(string $file, string $hash, array $entry, string $note = ''): void
    {
        $bookmarks = $this->getBookmarks();

        // Prevent duplicate
        foreach ($bookmarks as $bm) {
            if ($bm['hash'] === $hash && $bm['file'] === $file) {
                return;
            }
        }

        $bookmarks[] = [
            'id' => md5($file . $hash . microtime()),
            'file' => $file,
            'hash' => $hash,
            'level' => $entry['level'],
            'message' => mb_substr($entry['message'], 0, 200),
            'exception_class' => $entry['exception_class'] ?? '',
            'date' => $entry['date'],
            'note' => $note,
            'bookmarked_at' => now()->toDateTimeString(),
        ];

        // Enforce max limit — remove oldest
        if (count($bookmarks) > $this->maxBookmarks) {
            $bookmarks = array_slice($bookmarks, -$this->maxBookmarks);
        }

        $this->saveBookmarks($bookmarks);
    }

    public function removeBookmark(string $id): void
    {
        $bookmarks = $this->getBookmarks();
        $bookmarks = array_values(array_filter($bookmarks, fn($b) => $b['id'] !== $id));
        $this->saveBookmarks($bookmarks);
    }

    public function clearAllBookmarks(): void
    {
        $this->saveBookmarks([]);
    }

    protected function saveBookmarks(array $bookmarks): void
    {
        $storagePath = dirname($this->getBookmarksPath());
        if (!File::isDirectory($storagePath)) {
            File::makeDirectory($storagePath, 0755, true);
        }

        File::put($this->getBookmarksPath(), json_encode($bookmarks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->cachedBookmarks = $bookmarks;
    }

    protected function emptyResult(int $perPage, int $page): array
    {
        return [
            'entries' => new LengthAwarePaginator([], 0, $perPage, $page),
            'too_large' => false,
            'level_counts' => [],
            'has_multiple_dates' => false,
        ];
    }
}
