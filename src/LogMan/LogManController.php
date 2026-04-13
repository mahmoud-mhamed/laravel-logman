<?php

namespace Mhamed\Logman\LogMan;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Response;
use Mhamed\Logman\Channels\ChannelInterface;
use Mhamed\Logman\Services\MuteService;

class LogManController extends Controller
{
    protected LogManService $viewer;

    public function __construct(LogManService $viewer)
    {
        $this->viewer = $viewer;
    }

    public function dashboard()
    {
        $stats = $this->viewer->getDashboardStats();

        return view('logman::logman-dashboard', $stats);
    }

    public function index(Request $request)
    {
        $files = $this->viewer->getFiles();
        $selectedFile = $request->get('file', $files->first()['name'] ?? null);
        $level = $request->get('level', 'all');
        $search = $request->get('search');
        $isRegex = $request->boolean('regex');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $timeFrom = $request->get('time_from');
        $timeTo = $request->get('time_to');
        $sortDirection = $request->get('sort', 'desc');
        $reviewFilter = $request->get('review') ?: null;
        $reviewStatus = $request->get('review_status') ?: null;
        $perPage = (int) $request->get('per_page', config('logman.log_viewer.per_page', 25));
        $allowedOptions = config('logman.log_viewer.per_page_options', [15, 25, 50, 100]);
        if (!in_array($perPage, $allowedOptions)) {
            $perPage = config('logman.log_viewer.per_page', 25);
        }
        $page = (int) $request->get('page', 1);

        $muteFilter = $request->get('mute_filter') ?: null;
        $bookmarkFilter = $request->get('bookmark_filter') ?: null;

        $muteService = app(MuteService::class);
        $activeMutes = $muteService->getMutes();
        $activeThrottles = $muteService->getThrottles();

        $allBookmarks = $this->viewer->getBookmarks();
        $bookmarkedHashList = collect($allBookmarks)->pluck('hash')->all();

        $logData = ['entries' => null, 'too_large' => false, 'level_counts' => [], 'has_multiple_dates' => false];
        if ($selectedFile) {
            $logData = $this->viewer->getLogEntries(
                $selectedFile, $level, $search, $isRegex,
                $dateFrom, $dateTo, $timeFrom, $timeTo,
                $sortDirection, $page, $perPage, $reviewFilter, $reviewStatus,
                $muteFilter, $activeMutes, $activeThrottles,
                $bookmarkFilter, $bookmarkedHashList,
            );
        }

        $enabledChannels = collect(config('logman.channels', []))
            ->filter(fn($settings) => !empty($settings['enabled']))
            ->keys()
            ->all();

        $bookmarkedHashes = collect($allBookmarks)->pluck('id', 'hash')->all();

        return view('logman::logman', [
            'files' => $files,
            'selectedFile' => $selectedFile,
            'entries' => $logData['entries'],
            'tooLarge' => $logData['too_large'],
            'currentLevel' => $level,
            'search' => $search,
            'isRegex' => $isRegex,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'timeFrom' => $timeFrom,
            'timeTo' => $timeTo,
            'sortDirection' => $sortDirection,
            'reviewFilter' => $reviewFilter,
            'muteFilter' => $muteFilter,
            'perPage' => $perPage,
            'levelCounts' => $logData['level_counts'],
            'hasMultipleDates' => $logData['has_multiple_dates'],
            'activeMutes' => $activeMutes,
            'activeThrottles' => $activeThrottles,
            'enabledChannels' => $enabledChannels,
            'bookmarkedHashes' => $bookmarkedHashes,
        ]);
    }

    public function download(Request $request)
    {
        $file = $request->get('file');
        $path = $this->viewer->downloadPath($file);

        if (!$path) {
            return back()->with('error', 'File not found.');
        }

        return Response::download($path);
    }

    public function delete(Request $request)
    {
        $file = $request->get('file');
        $this->viewer->deleteFile($file);

        return redirect()->route('logman.index')->with('success', "Deleted: {$file}");
    }

    public function deleteMultiple(Request $request)
    {
        $files = $request->get('files', []);
        $count = $this->viewer->deleteMultiple($files);

        return redirect()->route('logman.index')->with('success', "Deleted {$count} file(s).");
    }

    public function clear(Request $request)
    {
        $file = $request->get('file');
        $this->viewer->clearFile($file);

        return redirect()->route('logman.index', ['file' => $file])->with('success', "Cleared: {$file}");
    }

    public function clearCache(Request $request)
    {
        $file = $request->get('file');

        if ($file) {
            $this->viewer->clearFileCache($file);
            return back()->with('success', "Cache cleared for: {$file}");
        }

        $this->viewer->clearAllCache();
        return back()->with('success', 'All cache cleared.');
    }

    // ─── Review ────────────────────────────────────────────────

    public function review(Request $request)
    {
        $file = $request->get('file');
        $hash = $request->get('hash');
        $status = $request->get('status', 'reviewed');
        $note = $request->get('note', '');

        if (!$file || !$hash) {
            return back()->with('error', 'Missing parameters.');
        }

        $this->viewer->addReview($file, $hash, $status, $note ?? '');

        return back()->with('success', 'Entry marked as ' . $status . '.');
    }

    public function unreview(Request $request)
    {
        $file = $request->get('file');
        $hash = $request->get('hash');

        if (!$file || !$hash) {
            return back()->with('error', 'Missing parameters.');
        }

        $this->viewer->removeReview($file, $hash);

        return back()->with('success', 'Review removed.');
    }

    // ─── Mute ──────────────────────────────────────────────────

    public function mutes(MuteService $muteService)
    {
        $mutes = $muteService->getMutes();

        return view('logman::mutes', [
            'mutes' => $mutes,
        ]);
    }

    public function mute(Request $request, MuteService $muteService)
    {
        $exceptionClass = $request->get('exception_class');
        $messagePattern = $request->get('message_pattern');
        $duration = $request->get('duration', '1h');
        $reason = $request->get('reason');

        if (!$exceptionClass) {
            return back()->with('error', 'Exception class is required.');
        }

        $muteService->mute($exceptionClass, $messagePattern, $duration, $reason);

        return back()->with('success', "Muted: {$exceptionClass} for {$duration}.");
    }

    public function unmute(Request $request, MuteService $muteService)
    {
        $id = $request->get('id');

        if (!$id) {
            return back()->with('error', 'Mute ID is required.');
        }

        $muteService->unmute($id);

        return back()->with('success', 'Unmuted successfully.');
    }

    public function extendMute(Request $request, MuteService $muteService)
    {
        $id = $request->get('id');
        $duration = $request->get('duration', '1h');

        if (!$id) {
            return back()->with('error', 'Mute ID is required.');
        }

        $muteService->extendMute($id, $duration);

        return back()->with('success', "Mute extended for {$duration}.");
    }

    public function unmuteAll(MuteService $muteService)
    {
        $mutes = $muteService->getMutes();
        foreach ($mutes as $mute) {
            $muteService->unmute($mute['id']);
        }

        return back()->with('success', 'All mutes removed.');
    }

    public function unmuteMultiple(Request $request, MuteService $muteService)
    {
        $ids = $request->get('ids', []);
        $count = 0;

        foreach ($ids as $id) {
            if ($muteService->unmute($id)) {
                $count++;
            }
        }

        return back()->with('success', "Removed {$count} mute(s).");
    }

    // ─── Throttle ──────────────────────────────────────────────

    public function throttles(MuteService $muteService)
    {
        return view('logman::throttles', [
            'throttles' => $muteService->getThrottles(),
        ]);
    }

    public function throttle(Request $request, MuteService $muteService)
    {
        $exceptionClass = $request->get('exception_class');
        $messagePattern = $request->get('message_pattern');
        $maxHits = (int) $request->get('max_hits', 1);
        $period = $request->get('period', '1d');
        $reason = $request->get('reason');

        if (!$exceptionClass) {
            return back()->with('error', 'Exception class is required.');
        }

        $muteService->addThrottle($exceptionClass, $messagePattern, $maxHits, $period, $reason);

        return back()->with('success', "Throttle set: max {$maxHits} per {$period}.");
    }

    public function unthrottle(Request $request, MuteService $muteService)
    {
        $id = $request->get('id');
        if (!$id) {
            return back()->with('error', 'Throttle ID is required.');
        }

        $muteService->removeThrottle($id);
        return back()->with('success', 'Throttle removed.');
    }

    public function unthrottleAll(MuteService $muteService)
    {
        $throttles = $muteService->getThrottles();
        foreach ($throttles as $t) {
            $muteService->removeThrottle($t['id']);
        }
        return back()->with('success', 'All throttles removed.');
    }

    public function unthrottleMultiple(Request $request, MuteService $muteService)
    {
        $ids = $request->get('ids', []);
        $count = 0;
        foreach ($ids as $id) {
            if ($muteService->removeThrottle($id)) {
                $count++;
            }
        }
        return back()->with('success', "Removed {$count} throttle(s).");
    }

    // ─── Config ────────────────────────────────────────────────

    public function config()
    {
        $config = config('logman');

        $channels = $config['channels'] ?? [];

        // Organize into sections
        $sections = [
            'Reporting' => [
                ['key' => 'enable_production', 'label' => 'Enable in Production', 'value' => $config['enable_production'] ?? false, 'type' => 'bool', 'description' => 'Send exception reports when app is in production'],
                ['key' => 'enable_local', 'label' => 'Enable in Local', 'value' => $config['enable_local'] ?? false, 'type' => 'bool', 'description' => 'Send exception reports when app is in local environment'],
                ['key' => 'auto_report_exceptions', 'label' => 'Auto-Report Exceptions', 'value' => $config['auto_report_exceptions'] ?? true, 'type' => 'bool', 'description' => 'Automatically register in exception handler'],
            ],
            'Channel: Slack' => [
                ['key' => 'channels.slack.enabled', 'label' => 'Enabled', 'value' => $channels['slack']['enabled'] ?? false, 'type' => 'bool', 'description' => 'Enable Slack notifications'],
                ['key' => 'channels.slack.auto_report_exceptions', 'label' => 'Auto-Report', 'value' => $channels['slack']['auto_report_exceptions'] ?? true, 'type' => 'bool', 'description' => 'Auto-report exceptions'],
                ['key' => 'channels.slack.min_level', 'label' => 'Min Level', 'value' => $channels['slack']['min_level'] ?? 'debug', 'type' => 'string', 'description' => 'Minimum log level to report'],
                ['key' => 'channels.slack.queue', 'label' => 'Queue', 'value' => $channels['slack']['queue'] ?? false, 'type' => 'bool', 'description' => 'Send via queue (async)'],
                ['key' => 'channels.slack.retries', 'label' => 'Retries', 'value' => $channels['slack']['retries'] ?? 0, 'type' => 'number', 'description' => 'Retry attempts on failure'],
                ['key' => 'channels.slack.throttle', 'label' => 'Throttle (s)', 'value' => $channels['slack']['throttle'] ?? 0, 'type' => 'number', 'description' => 'Per-channel cooldown in seconds'],
                ['key' => 'channels.slack.log_channel', 'label' => 'Log Channel', 'value' => $channels['slack']['log_channel'] ?? 'slack', 'type' => 'string', 'description' => 'Laravel logging channel name'],
                ['key' => 'slack_channel_config.url', 'label' => 'Webhook URL', 'value' => !empty($config['slack_channel_config']['url']) ? '***configured***' : 'NOT SET', 'type' => 'status', 'description' => 'Slack webhook URL'],
            ],
            'Channel: Telegram' => [
                ['key' => 'channels.telegram.enabled', 'label' => 'Enabled', 'value' => $channels['telegram']['enabled'] ?? false, 'type' => 'bool', 'description' => 'Enable Telegram notifications'],
                ['key' => 'channels.telegram.auto_report_exceptions', 'label' => 'Auto-Report', 'value' => $channels['telegram']['auto_report_exceptions'] ?? true, 'type' => 'bool', 'description' => 'Auto-report exceptions'],
                ['key' => 'channels.telegram.min_level', 'label' => 'Min Level', 'value' => $channels['telegram']['min_level'] ?? 'error', 'type' => 'string', 'description' => 'Minimum log level to report'],
                ['key' => 'channels.telegram.queue', 'label' => 'Queue', 'value' => $channels['telegram']['queue'] ?? true, 'type' => 'bool', 'description' => 'Send via queue (async)'],
                ['key' => 'channels.telegram.retries', 'label' => 'Retries', 'value' => $channels['telegram']['retries'] ?? 2, 'type' => 'number', 'description' => 'Retry attempts on failure'],
                ['key' => 'channels.telegram.throttle', 'label' => 'Throttle (s)', 'value' => $channels['telegram']['throttle'] ?? 0, 'type' => 'number', 'description' => 'Per-channel cooldown in seconds'],
                ['key' => 'channels.telegram.bot_token', 'label' => 'Bot Token', 'value' => !empty($channels['telegram']['bot_token']) ? '***configured***' : 'NOT SET', 'type' => 'status', 'description' => 'Telegram bot token'],
                ['key' => 'channels.telegram.chat_id', 'label' => 'Chat ID', 'value' => !empty($channels['telegram']['chat_id']) ? $channels['telegram']['chat_id'] : 'NOT SET', 'type' => !empty($channels['telegram']['chat_id']) ? 'string' : 'status', 'description' => 'Telegram chat/group ID'],
            ],
            'Channel: Discord' => [
                ['key' => 'channels.discord.enabled', 'label' => 'Enabled', 'value' => $channels['discord']['enabled'] ?? false, 'type' => 'bool', 'description' => 'Enable Discord notifications'],
                ['key' => 'channels.discord.auto_report_exceptions', 'label' => 'Auto-Report', 'value' => $channels['discord']['auto_report_exceptions'] ?? true, 'type' => 'bool', 'description' => 'Auto-report exceptions'],
                ['key' => 'channels.discord.min_level', 'label' => 'Min Level', 'value' => $channels['discord']['min_level'] ?? 'error', 'type' => 'string', 'description' => 'Minimum log level to report'],
                ['key' => 'channels.discord.queue', 'label' => 'Queue', 'value' => $channels['discord']['queue'] ?? true, 'type' => 'bool', 'description' => 'Send via queue (async)'],
                ['key' => 'channels.discord.retries', 'label' => 'Retries', 'value' => $channels['discord']['retries'] ?? 2, 'type' => 'number', 'description' => 'Retry attempts on failure'],
                ['key' => 'channels.discord.throttle', 'label' => 'Throttle (s)', 'value' => $channels['discord']['throttle'] ?? 0, 'type' => 'number', 'description' => 'Per-channel cooldown in seconds'],
                ['key' => 'channels.discord.webhook_url', 'label' => 'Webhook URL', 'value' => !empty($channels['discord']['webhook_url']) ? '***configured***' : 'NOT SET', 'type' => 'status', 'description' => 'Discord webhook URL'],
            ],
            'Channel: Mail' => [
                ['key' => 'channels.mail.enabled', 'label' => 'Enabled', 'value' => $channels['mail']['enabled'] ?? false, 'type' => 'bool', 'description' => 'Enable email notifications'],
                ['key' => 'channels.mail.auto_report_exceptions', 'label' => 'Auto-Report', 'value' => $channels['mail']['auto_report_exceptions'] ?? true, 'type' => 'bool', 'description' => 'Auto-report exceptions'],
                ['key' => 'channels.mail.min_level', 'label' => 'Min Level', 'value' => $channels['mail']['min_level'] ?? 'critical', 'type' => 'string', 'description' => 'Minimum log level to report'],
                ['key' => 'channels.mail.queue', 'label' => 'Queue', 'value' => $channels['mail']['queue'] ?? true, 'type' => 'bool', 'description' => 'Send via queue (async)'],
                ['key' => 'channels.mail.retries', 'label' => 'Retries', 'value' => $channels['mail']['retries'] ?? 1, 'type' => 'number', 'description' => 'Retry attempts on failure'],
                ['key' => 'channels.mail.throttle', 'label' => 'Throttle (s)', 'value' => $channels['mail']['throttle'] ?? 60, 'type' => 'number', 'description' => 'Per-channel cooldown in seconds'],
                ['key' => 'channels.mail.to', 'label' => 'Recipients', 'value' => array_filter((array) ($channels['mail']['to'] ?? [])), 'type' => 'list', 'description' => 'Email recipients'],
                ['key' => 'channels.mail.from', 'label' => 'From', 'value' => $channels['mail']['from'] ?? config('mail.from.address') ?? '-', 'type' => 'string', 'description' => 'Sender address'],
            ],
            'Daily Digest' => [
                ['key' => 'daily_digest.enabled', 'label' => 'Enabled', 'value' => $config['daily_digest']['enabled'] ?? false, 'type' => 'bool', 'description' => 'Automatically send a daily digest summary (no manual scheduler setup needed)'],
                ['key' => 'daily_digest.time', 'label' => 'Send Time', 'value' => $config['daily_digest']['time'] ?? '09:00', 'type' => 'string', 'description' => 'Time to send the digest (24h format, server timezone)'],
            ],
            'Rate Limiting' => [
                ['key' => 'rate_limit.enabled', 'label' => 'Enabled', 'value' => $config['rate_limit']['enabled'] ?? true, 'type' => 'bool', 'description' => 'Prevent the same exception from flooding notifications'],
                ['key' => 'rate_limit.cooldown_seconds', 'label' => 'Cooldown (seconds)', 'value' => $config['rate_limit']['cooldown_seconds'] ?? 10, 'type' => 'number', 'description' => 'Seconds before the same exception can be reported again'],
            ],
            'Ignored Exceptions' => [
                ['key' => 'ignore', 'label' => 'Ignored Classes', 'value' => $config['ignore'] ?? [], 'type' => 'list', 'description' => 'Exception classes that will NOT be reported'],
            ],
            'Storage' => [
                ['key' => 'storage_path', 'label' => 'Package Storage', 'value' => $config['storage_path'] ?? '-', 'type' => 'path', 'description' => 'Directory for mutes.json, rate_limits.json, etc.'],
            ],
            'Log Viewer' => [
                ['key' => 'log_viewer.enabled', 'label' => 'Enabled', 'value' => $config['log_viewer']['enabled'] ?? true, 'type' => 'bool', 'description' => 'Enable or disable log viewer routes'],
                ['key' => 'log_viewer.route_prefix', 'label' => 'Route Prefix', 'value' => $config['log_viewer']['route_prefix'] ?? 'logman', 'type' => 'string', 'description' => 'URL prefix for the log viewer'],
                ['key' => 'log_viewer.middleware', 'label' => 'Middleware', 'value' => $config['log_viewer']['middleware'] ?? [], 'type' => 'list', 'description' => 'Middleware applied to log viewer routes'],
                ['key' => 'log_viewer.authorize', 'label' => 'Authorize', 'value' => $config['log_viewer']['authorize'] !== null ? 'Custom callback' : 'None (open)', 'type' => $config['log_viewer']['authorize'] !== null ? 'string' : 'status', 'description' => 'Authorization callback for access control'],
                ['key' => 'log_viewer.storage_path', 'label' => 'Logs Path', 'value' => $config['log_viewer']['storage_path'] ?? '-', 'type' => 'path', 'description' => 'Path to Laravel log files directory'],
                ['key' => 'log_viewer.pattern', 'label' => 'File Pattern', 'value' => $config['log_viewer']['pattern'] ?? '*.log', 'type' => 'string', 'description' => 'Glob pattern for matching log files'],
                ['key' => 'log_viewer.max_file_size', 'label' => 'Max File Size', 'value' => $this->formatFileSize($config['log_viewer']['max_file_size'] ?? 52428800), 'type' => 'string', 'description' => 'Maximum log file size to display in browser'],
                ['key' => 'log_viewer.per_page', 'label' => 'Per Page', 'value' => $config['log_viewer']['per_page'] ?? 25, 'type' => 'number', 'description' => 'Default entries per page'],
            ],
            'Environment' => [
                ['key' => 'app.env', 'label' => 'App Environment', 'value' => app()->environment(), 'type' => 'string', 'description' => 'Current application environment'],
                ['key' => 'app.debug', 'label' => 'Debug Mode', 'value' => config('app.debug'), 'type' => 'bool', 'description' => 'Application debug mode'],
                ['key' => 'php.version', 'label' => 'PHP Version', 'value' => PHP_VERSION, 'type' => 'string', 'description' => 'PHP runtime version'],
                ['key' => 'laravel.version', 'label' => 'Laravel Version', 'value' => app()->version(), 'type' => 'string', 'description' => 'Laravel framework version'],
            ],
        ];

        return view('logman::config', [
            'sections' => $sections,
        ]);
    }

    // ─── Send to Channel ─────────────────────────────────────

    public function sendToChannel(Request $request)
    {
        $file = $request->get('file');
        $hash = $request->get('hash');
        $channelName = $request->get('channel');

        if (!$file || !$hash || !$channelName) {
            return back()->with('error', 'Missing parameters.');
        }

        $channelConfig = config("logman.channels.{$channelName}");
        if (!$channelConfig || empty($channelConfig['enabled'])) {
            return back()->with('error', "Channel '{$channelName}' is not enabled.");
        }

        // Find the log entry by hash
        $entry = $this->viewer->findEntryByHash($file, $hash);
        if (!$entry) {
            return back()->with('error', 'Log entry not found.');
        }

        // Build a payload from the log entry
        $payload = [
            'app' => config('app.name'),
            'env' => $entry['env'],
            'suppressed_count' => 0,
            'exception' => [
                'class' => $entry['exception_class'] ?: 'Unknown',
                'message' => $entry['exception_message'] ?? $entry['message'],
                'file' => $entry['exception_file'] ?? '-',
                'line' => $entry['exception_line'] ?? 0,
                'code' => null,
                'previous' => 'None',
                'trace' => $entry['stack'] ?: '-',
            ],
            'auth' => null,
            'request' => null,
            'cli' => null,
            'job' => null,
            'queries' => [],
            'environment' => [
                'env' => $entry['env'],
                'time' => $entry['date'],
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
                'memory' => '-',
                'hostname' => gethostname() ?: '-',
                'git_commit' => '-',
                'app_url' => config('app.url'),
            ],
        ];

        // Resolve the channel driver
        $drivers = [
            'slack' => \Mhamed\Logman\Channels\SlackChannel::class,
            'telegram' => \Mhamed\Logman\Channels\TelegramChannel::class,
            'discord' => \Mhamed\Logman\Channels\DiscordChannel::class,
            'mail' => \Mhamed\Logman\Channels\MailChannel::class,
        ];

        $driverClass = $channelConfig['driver'] ?? ($drivers[$channelName] ?? null);

        if (!$driverClass || !class_exists($driverClass)) {
            return back()->with('error', "Driver not found for channel '{$channelName}'.");
        }

        $driver = new $driverClass();

        if (!$driver instanceof ChannelInterface) {
            return back()->with('error', "Invalid driver for channel '{$channelName}'.");
        }

        try {
            $driver->sendException($payload);
            return back()->with('success', "Sent to " . ucfirst($channelName) . " successfully.");
        } catch (\Throwable $e) {
            return back()->with('error', "Failed to send to {$channelName}: " . $e->getMessage());
        }
    }

    // ─── Grouped Errors ───────────────────────────────────

    public function grouped(Request $request)
    {
        $files = $this->viewer->getFiles();
        $selectedFile = $request->get('file', $files->first()['name'] ?? null);
        $groups = [];

        if ($selectedFile) {
            $groups = $this->viewer->getGroupedEntries($selectedFile);

            // Resolve the first entry for each group to show details
            foreach ($groups as &$group) {
                $group['full_entry'] = null;
                if (!empty($group['hashes'][0])) {
                    $group['full_entry'] = $this->viewer->findEntryByHash($selectedFile, $group['hashes'][0]);
                }
            }
            unset($group);
        }

        return view('logman::grouped', [
            'files' => $files,
            'selectedFile' => $selectedFile,
            'groups' => $groups,
        ]);
    }

    // ─── Export ────────────────────────────────────────────

    public function export(Request $request)
    {
        $file = $request->get('file');
        $format = $request->get('format', 'json');
        $level = $request->get('level');
        $search = $request->get('search');

        if (!$file) {
            return back()->with('error', 'No file specified.');
        }

        $result = $this->viewer->getLogEntries(
            $file,
            level: $level !== 'all' ? $level : null,
            search: $search,
            perPage: 999999,
        );

        $entries = collect($result['entries']->items())->map(fn($e) => [
            'date' => $e['date'],
            'env' => $e['env'],
            'level' => $e['level'],
            'message' => $e['message'],
            'exception_class' => $e['exception_class'] ?? '',
            'exception_file' => $e['exception_file'] ?? '',
            'exception_line' => $e['exception_line'] ?? '',
            'stack' => $e['stack'],
        ])->all();

        if ($format === 'csv') {
            $csv = $this->buildCsv($entries);
            return response($csv, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="logman-export-' . $file . '.csv"',
            ]);
        }

        return response()->json($entries, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ->header('Content-Disposition', 'attachment; filename="logman-export-' . $file . '.json"');
    }

    protected function buildCsv(array $entries): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Date', 'Env', 'Level', 'Message', 'Exception Class', 'File', 'Line']);
        foreach ($entries as $e) {
            fputcsv($handle, [
                $e['date'], $e['env'], $e['level'], $e['message'],
                $e['exception_class'], $e['exception_file'], $e['exception_line'],
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);
        return $csv;
    }

    // ─── Bookmarks ────────────────────────────────────────

    public function bookmarks(Request $request)
    {
        $bookmarks = $this->viewer->getBookmarks();

        // Resolve full entries for each bookmark
        $resolvedBookmarks = [];
        foreach ($bookmarks as $bm) {
            $fullEntry = $this->viewer->findEntryByHash($bm['file'], $bm['hash']);
            $bm['full_entry'] = $fullEntry;
            $resolvedBookmarks[] = $bm;
        }

        return view('logman::bookmarks', [
            'bookmarks' => $resolvedBookmarks,
        ]);
    }

    public function bookmark(Request $request)
    {
        $file = $request->get('file');
        $hash = $request->get('hash');
        $note = $request->get('note', '');

        if (!$file || !$hash) {
            return back()->with('error', 'Missing parameters.');
        }

        $entry = $this->viewer->findEntryByHash($file, $hash);
        if (!$entry) {
            return back()->with('error', 'Log entry not found.');
        }

        $this->viewer->addBookmark($file, $hash, $entry, $note);

        return back()->with('success', 'Bookmarked.');
    }

    public function unbookmark(Request $request)
    {
        $id = $request->get('id');
        if (!$id) {
            return back()->with('error', 'Missing bookmark ID.');
        }

        $this->viewer->removeBookmark($id);

        return back()->with('success', 'Bookmark removed.');
    }

    // ─── About ─────────────────────────────────────────────

    public function about()
    {
        $channels = config('logman.channels', []);
        $enabledChannels = collect($channels)->filter(fn($ch) => !empty($ch['enabled']))->keys()->all();

        $storagePath = config('logman.storage_path', storage_path('logman'));
        $storageFiles = [
            'mutes.json' => $storagePath . '/mutes.json',
            'throttles.json' => $storagePath . '/throttles.json',
            'rate_limits.json' => $storagePath . '/rate_limits.json',
        ];
        $storageSizes = [];
        foreach ($storageFiles as $name => $path) {
            $storageSizes[$name] = file_exists($path) ? filesize($path) : 0;
        }

        return view('logman::about', [
            'version' => '1.0.0',
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => app()->version(),
            'environment' => app()->environment(),
            'enabledChannels' => $enabledChannels,
            'allChannels' => $channels,
            'config' => config('logman'),
            'storageSizes' => $storageSizes,
        ]);
    }

    protected function formatFileSize(int $bytes): string
    {
        if ($bytes === 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = (int) floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }
}
