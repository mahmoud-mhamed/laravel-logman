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

        $muteService = app(MuteService::class);
        $activeMutes = $muteService->getMutes();
        $activeThrottles = $muteService->getThrottles();

        $logData = ['entries' => null, 'too_large' => false, 'level_counts' => [], 'has_multiple_dates' => false];
        if ($selectedFile) {
            $logData = $this->viewer->getLogEntries(
                $selectedFile, $level, $search, $isRegex,
                $dateFrom, $dateTo, $timeFrom, $timeTo,
                $sortDirection, $page, $perPage, $reviewFilter, $reviewStatus,
                $muteFilter, $activeMutes, $activeThrottles,
            );
        }

        $enabledChannels = collect(config('logman.channels', []))
            ->filter(fn($settings) => !empty($settings['enabled']))
            ->keys()
            ->all();

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
                ['key' => 'channels.slack.auto_report_exceptions', 'label' => 'Auto-Report', 'value' => $channels['slack']['auto_report_exceptions'] ?? true, 'type' => 'bool', 'description' => 'Auto-report exceptions to this channel'],
                ['key' => 'channels.slack.log_channel', 'label' => 'Log Channel', 'value' => $channels['slack']['log_channel'] ?? 'slack', 'type' => 'string', 'description' => 'Laravel logging channel name'],
                ['key' => 'slack_channel_config.url', 'label' => 'Webhook URL', 'value' => !empty($config['slack_channel_config']['url']) ? '***configured***' : 'NOT SET', 'type' => 'status', 'description' => 'Slack incoming webhook URL (LOG_SLACK_WEBHOOK_URL)'],
                ['key' => 'slack_channel_config.username', 'label' => 'Bot Username', 'value' => $config['slack_channel_config']['username'] ?? '-', 'type' => 'string', 'description' => 'Display name for the Slack bot'],
                ['key' => 'slack_channel_config.emoji', 'label' => 'Bot Emoji', 'value' => $config['slack_channel_config']['emoji'] ?? '-', 'type' => 'string', 'description' => 'Emoji icon for the bot'],
            ],
            'Channel: Telegram' => [
                ['key' => 'channels.telegram.enabled', 'label' => 'Enabled', 'value' => $channels['telegram']['enabled'] ?? false, 'type' => 'bool', 'description' => 'Enable Telegram notifications'],
                ['key' => 'channels.telegram.auto_report_exceptions', 'label' => 'Auto-Report', 'value' => $channels['telegram']['auto_report_exceptions'] ?? true, 'type' => 'bool', 'description' => 'Auto-report exceptions to this channel'],
                ['key' => 'channels.telegram.bot_token', 'label' => 'Bot Token', 'value' => !empty($channels['telegram']['bot_token']) ? '***configured***' : 'NOT SET', 'type' => 'status', 'description' => 'Telegram bot token (LOGMAN_TELEGRAM_BOT_TOKEN)'],
                ['key' => 'channels.telegram.chat_id', 'label' => 'Chat ID', 'value' => !empty($channels['telegram']['chat_id']) ? $channels['telegram']['chat_id'] : 'NOT SET', 'type' => !empty($channels['telegram']['chat_id']) ? 'string' : 'status', 'description' => 'Telegram chat/group ID (LOGMAN_TELEGRAM_CHAT_ID)'],
            ],
            'Channel: Discord' => [
                ['key' => 'channels.discord.enabled', 'label' => 'Enabled', 'value' => $channels['discord']['enabled'] ?? false, 'type' => 'bool', 'description' => 'Enable Discord notifications'],
                ['key' => 'channels.discord.auto_report_exceptions', 'label' => 'Auto-Report', 'value' => $channels['discord']['auto_report_exceptions'] ?? true, 'type' => 'bool', 'description' => 'Auto-report exceptions to this channel'],
                ['key' => 'channels.discord.webhook_url', 'label' => 'Webhook URL', 'value' => !empty($channels['discord']['webhook_url']) ? '***configured***' : 'NOT SET', 'type' => 'status', 'description' => 'Discord webhook URL (LOGMAN_DISCORD_WEBHOOK)'],
            ],
            'Channel: Mail' => [
                ['key' => 'channels.mail.enabled', 'label' => 'Enabled', 'value' => $channels['mail']['enabled'] ?? false, 'type' => 'bool', 'description' => 'Enable email notifications'],
                ['key' => 'channels.mail.auto_report_exceptions', 'label' => 'Auto-Report', 'value' => $channels['mail']['auto_report_exceptions'] ?? true, 'type' => 'bool', 'description' => 'Auto-report exceptions to this channel'],
                ['key' => 'channels.mail.to', 'label' => 'Recipients', 'value' => array_filter((array) ($channels['mail']['to'] ?? [])), 'type' => 'list', 'description' => 'Email addresses to receive notifications (LOGMAN_MAIL_TO)'],
                ['key' => 'channels.mail.from', 'label' => 'From', 'value' => $channels['mail']['from'] ?? config('mail.from.address') ?? '-', 'type' => 'string', 'description' => 'Sender address (LOGMAN_MAIL_FROM)'],
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

    // ─── About ─────────────────────────────────────────────

    public function about()
    {
        $channels = config('logman.channels', []);
        $enabledChannels = collect($channels)->filter(fn($ch) => !empty($ch['enabled']))->keys()->all();

        return view('logman::about', [
            'version' => '1.0.0',
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => app()->version(),
            'environment' => app()->environment(),
            'enabledChannels' => $enabledChannels,
            'allChannels' => $channels,
            'config' => config('logman'),
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
