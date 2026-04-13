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
        $config = config('logman.log_viewer');
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

        $entries = array_values($entries);

        // Sort
        if ($sortDirection === 'asc') {
            $entries = array_reverse($entries);
        }

        // Paginate
        $total = count($entries);
        $offset = ($page - 1) * $perPage;
        $items = array_slice($entries, $offset, $perPage);

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
            $content = File::get($path);
            return $this->parseLogContent($content);
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

    protected function parseLogContent(string $content): array
    {
        $pattern = '/\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}\.?\d*[\+\-]?\d{0,4})\]\s+(\w+)\.(\w+):\s*(.*)/';
        $lines = explode("\n", $content);
        $entries = [];
        $currentEntry = null;

        foreach ($lines as $line) {
            // Parse #REVIEWED lines
            if (str_starts_with($line, '#REVIEWED ')) {
                if ($currentEntry !== null) {
                    $json = substr($line, 10);
                    $reviewData = json_decode($json, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $currentEntry['reviewed'] = true;
                        $currentEntry['review_status'] = $reviewData['status'] ?? 'reviewed';
                        $currentEntry['review_note'] = $reviewData['note'] ?? '';
                        $currentEntry['review_by'] = $reviewData['by'] ?? '';
                        $currentEntry['review_at'] = $reviewData['at'] ?? '';
                    }
                }
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

        if ($currentEntry !== null) {
            $this->finalizeEntry($currentEntry);
            $entries[] = $currentEntry;
        }

        // Newest first by default
        return array_reverse($entries);
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
            $regexValid = @preg_match('/' . $search . '/i', '') !== false;
            if ($regexValid) {
                $oldLimit = ini_get('pcre.backtrack_limit');
                ini_set('pcre.backtrack_limit', 10000);

                $filtered = array_filter($entries, function ($entry) use ($search) {
                    $text = $entry['message'] . ' ' . $entry['stack'];
                    return @preg_match('/' . $search . '/i', $text) === 1;
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
        }

        // Today's & yesterday's stats
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('yesterday'));
        $todayCounts = [];
        $todayTotal = 0;
        $yesterdayCounts = [];
        $yesterdayTotal = 0;

        foreach ($files as $file) {
            $path = $this->safePath($file['name']);
            if (!$path || !File::exists($path) || File::size($path) > $this->maxFileSize) {
                continue;
            }

            $entries = $this->getCachedEntries($file['name'], $path);
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

    public function addReview(string $filename, string $hash, string $status = 'reviewed', string $note = '', ?string $by = null): bool
    {
        $path = $this->safePath($filename);
        if (!$path || !File::exists($path)) {
            return false;
        }

        $content = File::get($path);
        $lines = explode("\n", $content);
        $pattern = '/\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}\.?\d*[\+\-]?\d{0,4})\]\s+(\w+)\.(\w+):\s*(.*)/';
        $result = [];
        $currentEntryStart = null;
        $currentHash = null;

        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];

            if (preg_match($pattern, $line, $matches)) {
                $currentHash = md5($matches[1] . '|' . strtolower($matches[3]) . '|' . $matches[4]);
                $currentEntryStart = count($result);
            }

            // Skip existing #REVIEWED for this entry if we're updating it
            if (str_starts_with($line, '#REVIEWED ') && $currentHash === $hash) {
                continue;
            }

            $result[] = $line;

            // If this is the entry we want to review, find the end and insert
            if ($currentHash === $hash) {
                // Check if next line is a new entry or end of file
                $nextLine = $lines[$i + 1] ?? null;
                $isEndOfEntry = $nextLine === null
                    || preg_match($pattern, $nextLine)
                    || str_starts_with($nextLine, '#REVIEWED ');

                // If the current line is the log entry line itself, we need to check further
                // We insert after all stack trace lines
                if ($isEndOfEntry || ($i + 1 >= count($lines))) {
                    $reviewData = json_encode([
                        'status' => $status,
                        'note' => $note,
                        'by' => $by ?? '',
                        'at' => now()->toDateTimeString(),
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $result[] = '#REVIEWED ' . $reviewData;
                    $currentHash = null; // Prevent double insertion
                }
            }
        }

        $this->clearFileCache($filename);
        return File::put($path, implode("\n", $result)) !== false;
    }

    public function removeReview(string $filename, string $hash): bool
    {
        $path = $this->safePath($filename);
        if (!$path || !File::exists($path)) {
            return false;
        }

        $content = File::get($path);
        $lines = explode("\n", $content);
        $pattern = '/\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}\.?\d*[\+\-]?\d{0,4})\]\s+(\w+)\.(\w+):\s*(.*)/';
        $result = [];
        $currentHash = null;

        foreach ($lines as $line) {
            if (preg_match($pattern, $line, $matches)) {
                $currentHash = md5($matches[1] . '|' . strtolower($matches[3]) . '|' . $matches[4]);
            }

            if (str_starts_with($line, '#REVIEWED ') && $currentHash === $hash) {
                continue;
            }

            $result[] = $line;
        }

        $this->clearFileCache($filename);
        return File::put($path, implode("\n", $result)) !== false;
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
