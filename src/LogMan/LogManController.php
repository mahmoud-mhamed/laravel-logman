<?php

namespace Mhamed\Logman\LogMan;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Response;
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

        // Organize into sections
        $sections = [
            'Reporting' => [
                ['key' => 'enable_production', 'label' => 'Enable in Production', 'value' => $config['enable_production'] ?? false, 'type' => 'bool', 'description' => 'Send exception reports when app is in production'],
                ['key' => 'enable_local', 'label' => 'Enable in Local', 'value' => $config['enable_local'] ?? false, 'type' => 'bool', 'description' => 'Send exception reports when app is in local environment'],
                ['key' => 'auto_report_exceptions', 'label' => 'Auto-Report Exceptions', 'value' => $config['auto_report_exceptions'] ?? true, 'type' => 'bool', 'description' => 'Automatically register in exception handler'],
                ['key' => 'log_channel', 'label' => 'Log Channel', 'value' => $config['log_channel'] ?? 'slack', 'type' => 'string', 'description' => 'Logging channel used to send messages'],
            ],
            'Slack Channel' => [
                ['key' => 'slack_channel_config.driver', 'label' => 'Driver', 'value' => $config['slack_channel_config']['driver'] ?? '-', 'type' => 'string', 'description' => 'Logging driver'],
                ['key' => 'slack_channel_config.url', 'label' => 'Webhook URL', 'value' => !empty($config['slack_channel_config']['url']) ? '***configured***' : 'NOT SET', 'type' => 'status', 'description' => 'Slack incoming webhook URL (LOG_SLACK_WEBHOOK_URL)'],
                ['key' => 'slack_channel_config.username', 'label' => 'Bot Username', 'value' => $config['slack_channel_config']['username'] ?? '-', 'type' => 'string', 'description' => 'Display name for the Slack bot'],
                ['key' => 'slack_channel_config.emoji', 'label' => 'Bot Emoji', 'value' => $config['slack_channel_config']['emoji'] ?? '-', 'type' => 'string', 'description' => 'Emoji icon for the bot'],
                ['key' => 'slack_channel_config.level', 'label' => 'Minimum Level', 'value' => $config['slack_channel_config']['level'] ?? 'error', 'type' => 'string', 'description' => 'Minimum log level to send'],
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
                ['key' => 'log_viewer.route_prefix', 'label' => 'Route Prefix', 'value' => $config['log_viewer']['route_prefix'] ?? 'log-viewer', 'type' => 'string', 'description' => 'URL prefix for the log viewer'],
                ['key' => 'log_viewer.middleware', 'label' => 'Middleware', 'value' => $config['log_viewer']['middleware'] ?? [], 'type' => 'list', 'description' => 'Middleware applied to log viewer routes'],
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

    protected function formatFileSize(int $bytes): string
    {
        if ($bytes === 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = (int) floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }
}
