<?php

namespace Mhamed\Logman\Console\Commands;

use Illuminate\Console\Command;
use Mhamed\Logman\Channels\ChannelInterface;
use Mhamed\Logman\Channels\DiscordChannel;
use Mhamed\Logman\Channels\MailChannel;
use Mhamed\Logman\Channels\SlackChannel;
use Mhamed\Logman\Channels\TelegramChannel;
use Mhamed\Logman\LogMan\LogManService;

class LogmanDigestCommand extends Command
{
    protected $signature = 'logman:digest
        {--date= : Date to report (Y-m-d). Defaults to yesterday}
        {--channel= : Send to a specific channel only}';

    protected $description = 'Send a daily digest summary of log entries to all enabled channels';

    protected static array $drivers = [
        'slack' => SlackChannel::class,
        'telegram' => TelegramChannel::class,
        'discord' => DiscordChannel::class,
        'mail' => MailChannel::class,
    ];

    public function handle(LogManService $viewer): int
    {
        $date = $this->option('date') ?: date('Y-m-d', strtotime('yesterday'));
        $channelFilter = $this->option('channel');

        $this->info("Building digest for {$date}...");

        $digest = $this->buildDigest($viewer, $date);

        if ($digest['total'] === 0) {
            $this->info('No log entries found for this date.');
            return self::SUCCESS;
        }

        $channels = $this->getChannels($channelFilter);

        if (empty($channels)) {
            $this->warn('No enabled channels found.');
            return self::FAILURE;
        }

        foreach ($channels as $name => $channel) {
            try {
                $channel->sendInfo($this->formatDigest($digest, $date), [
                    'app' => config('app.name'),
                    'env' => app()->environment(),
                    'url' => config('app.url', '-'),
                    'previous_url' => '-',
                ]);
                $this->info("  Sent to {$name}");
            } catch (\Throwable $e) {
                $this->error("  Failed to send to {$name}: " . $e->getMessage());
            }
        }

        $this->info('Digest complete.');
        return self::SUCCESS;
    }

    protected function buildDigest(LogManService $viewer, string $date): array
    {
        $files = $viewer->getFiles();
        $levelCounts = [];
        $total = 0;
        $topErrors = [];
        $perFile = [];

        foreach ($files as $file) {
            $entries = $this->getEntriesForDate($viewer, $file['name'], $date);
            $fileTotal = 0;

            foreach ($entries as $entry) {
                $level = $entry['level'];
                $levelCounts[$level] = ($levelCounts[$level] ?? 0) + 1;
                $total++;
                $fileTotal++;

                // Track top errors
                if (in_array($level, ['emergency', 'alert', 'critical', 'error'])) {
                    $key = $entry['exception_class'] ?: mb_substr($entry['message'], 0, 80);
                    if (!isset($topErrors[$key])) {
                        $topErrors[$key] = [
                            'key' => $key,
                            'message' => mb_substr($entry['exception_message'] ?? $entry['message'], 0, 100),
                            'count' => 0,
                        ];
                    }
                    $topErrors[$key]['count']++;
                }
            }

            if ($fileTotal > 0) {
                $perFile[$file['name']] = $fileTotal;
            }
        }

        // Sort top errors by count
        usort($topErrors, fn($a, $b) => $b['count'] - $a['count']);
        $topErrors = array_slice($topErrors, 0, 5);

        $errorCount = ($levelCounts['emergency'] ?? 0)
            + ($levelCounts['alert'] ?? 0)
            + ($levelCounts['critical'] ?? 0)
            + ($levelCounts['error'] ?? 0);

        return [
            'total' => $total,
            'error_count' => $errorCount,
            'level_counts' => $levelCounts,
            'top_errors' => $topErrors,
            'per_file' => $perFile,
            'unique_error_types' => count($topErrors),
        ];
    }

    protected function getEntriesForDate(LogManService $viewer, string $filename, string $date): array
    {
        $result = $viewer->getLogEntries(
            $filename,
            level: null,
            dateFrom: $date,
            dateTo: $date,
            perPage: 999999,
        );

        return $result['entries']?->items() ?? [];
    }

    protected function formatDigest(array $d, string $date): string
    {
        $app = config('app.name');
        $lines = [];

        $lines[] = "📊 Daily Digest — {$app} ({$date})";
        $lines[] = '';
        $lines[] = "Total: {$d['total']} entries | Errors & above: {$d['error_count']}";
        $lines[] = '';

        // Level breakdown
        $levelEmojis = [
            'emergency' => '🟣', 'alert' => '🔴', 'critical' => '🔴',
            'error' => '🔴', 'warning' => '🟡', 'notice' => '🔵',
            'info' => '🔵', 'debug' => '🟢',
        ];

        foreach ($d['level_counts'] as $level => $count) {
            $emoji = $levelEmojis[$level] ?? '⚪';
            $lines[] = "  {$emoji} " . ucfirst($level) . ": {$count}";
        }

        // Top errors
        if (!empty($d['top_errors'])) {
            $lines[] = '';
            $lines[] = "🔝 Top Errors:";
            foreach ($d['top_errors'] as $i => $err) {
                $num = $i + 1;
                $lines[] = "  {$num}. {$err['key']} ({$err['count']}x) — {$err['message']}";
            }
        }

        // Per file
        if (!empty($d['per_file'])) {
            $lines[] = '';
            $fileParts = [];
            foreach ($d['per_file'] as $name => $count) {
                $fileParts[] = "{$name} ({$count})";
            }
            $lines[] = "📁 Files: " . implode(', ', $fileParts);
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, ChannelInterface>
     */
    protected function getChannels(?string $filter): array
    {
        $channels = [];
        $configured = config('logman.channels', []);

        foreach ($configured as $name => $settings) {
            if (empty($settings['enabled'])) {
                continue;
            }

            if ($filter && $filter !== $name) {
                continue;
            }

            $driverClass = $settings['driver'] ?? (static::$drivers[$name] ?? null);

            if (is_string($driverClass) && class_exists($driverClass)) {
                $instance = new $driverClass();
                if ($instance instanceof ChannelInterface) {
                    $channels[$name] = $instance;
                }
            }
        }

        return $channels;
    }
}
