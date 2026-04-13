<?php

namespace MahmoudMhamed\Logman\Channels;

use Illuminate\Support\Facades\Log;
use Throwable;

class SlackChannel implements ChannelInterface
{
    public function sendException(array $payload): void
    {
        try {
            $channel = config('logman.channels.slack.log_channel', 'slack');

            Log::channel($channel)->error($this->formatException($payload));
        } catch (Throwable $e) {
            // Silently fail
        }
    }

    public function sendInfo(string $message, array $context): void
    {
        try {
            $channel = config('logman.channels.slack.log_channel', 'slack');

            $text = 'ℹ️ ' . $message . PHP_EOL .
                "• App: {$context['app']}" . PHP_EOL .
                "• Env: {$context['env']}" . PHP_EOL .
                "• Current: {$context['url']}" . PHP_EOL .
                "• Previous: {$context['previous_url']}";

            Log::channel($channel)->info($text);
        } catch (Throwable $e) {
            // Silently fail
        }
    }

    protected function formatException(array $p): string
    {
        $text = '🚨 *' . $p['app'] . "* — Exception in `{$p['env']}`" . PHP_EOL . '<!channel>';

        if ($p['suppressed_count'] > 0) {
            $text .= PHP_EOL . "⚠️ *This error was suppressed {$p['suppressed_count']} time(s) since last report (rate limited).*" . PHP_EOL;
        }

        // Auth
        if ($p['auth']) {
            $a = $p['auth'];
            $text .= $this->divider() .
                '👤 Auth' . PHP_EOL .
                "• Name: {$a['name']}" . PHP_EOL .
                "• Id: {$a['id']}" . PHP_EOL .
                "• Guard: {$a['guard']}" . PHP_EOL .
                "• Email: {$a['email']}" . PHP_EOL;
        }

        // Exception
        $e = $p['exception'];
        $body = '🐞 Exception' . PHP_EOL .
            "• Class: `{$e['class']}`" . PHP_EOL .
            '• Message:' . PHP_EOL .
            $this->fenced($e['message']) . PHP_EOL .
            "• 📄 File: {$e['file']}" . PHP_EOL .
            "• 🔢 Line: {$e['line']}" . PHP_EOL .
            ($e['code'] !== null ? "• Code: {$e['code']}" . PHP_EOL : '') .
            "• Previous: {$e['previous']}";

        if (mb_strlen($body) > 2000) {
            $body = mb_substr($body, 0, 2000) . "\n…(truncated)";
        }

        $text .= $this->divider() . $body . PHP_EOL;

        // Request / CLI
        if ($p['request']) {
            $r = $p['request'];
            $reqText = '🧭 Request' . PHP_EOL .
                "• Method: {$r['method']}" . PHP_EOL .
                "• URL: {$r['url']}" . PHP_EOL .
                "• Path: /{$r['path']}" . PHP_EOL .
                "• IP: {$r['ip']}" . PHP_EOL .
                "• Host: {$r['host']}" . PHP_EOL .
                "• Route: {$r['route']}" . PHP_EOL .
                "• Action: {$r['action']}" . PHP_EOL .
                "• Duration: {$r['duration']}" . PHP_EOL .
                ($r['locale'] ? "• Locale: {$r['locale']}" . PHP_EOL : '') .
                (!empty($r['route_params']) ? '• Route Params:' . PHP_EOL . $this->fenced($this->pretty($r['route_params'])) . PHP_EOL : '') .
                '• Headers:' . PHP_EOL . $this->fenced($this->pretty($r['headers'])) . PHP_EOL .
                '🎒 Query Params:' . PHP_EOL . $this->fenced($this->pretty($r['query'])) . PHP_EOL .
                '📦 Body:' . PHP_EOL . $this->fenced($this->pretty($r['body'])) . PHP_EOL .
                '📁 Files:' . PHP_EOL . $this->fenced($this->pretty($r['files']));

            $text .= $this->divider() . $reqText . PHP_EOL;
        } elseif ($p['cli']) {
            $text .= $this->divider() . '⌨️ CLI' . PHP_EOL . "• Command: `{$p['cli']['command']}`" . PHP_EOL;
        }

        // Job
        if ($p['job']) {
            $j = $p['job'];
            $text .= $this->divider() .
                '📮 Job / Queue' . PHP_EOL .
                "• Job: `{$j['name']}`" . PHP_EOL .
                "• Queue: {$j['queue']}" . PHP_EOL .
                "• Connection: {$j['connection']}" . PHP_EOL .
                "• Attempts: {$j['attempts']}" . PHP_EOL;
        }

        // Queries
        if (!empty($p['queries'])) {
            $formatted = array_map(fn($q) => "({$q['time']}ms) {$q['sql']}{$q['bindings_str']}", $p['queries']);
            $text .= $this->divider() .
                '🗄️ Last Queries (' . count($p['queries']) . ')' . PHP_EOL .
                $this->fenced(implode("\n", $formatted)) . PHP_EOL;
        }

        // Environment
        $env = $p['environment'];
        $text .= $this->divider() .
            '🧰 Environment' . PHP_EOL .
            "• Env: {$env['env']}" . PHP_EOL .
            "• Time: {$env['time']}" . PHP_EOL .
            "• PHP: {$env['php']}" . PHP_EOL .
            "• Laravel: {$env['laravel']}" . PHP_EOL .
            "• Memory Peak: {$env['memory']}" . PHP_EOL .
            "• Server: {$env['hostname']}" . PHP_EOL .
            "• Git Commit: `{$env['git_commit']}`" . PHP_EOL .
            (!empty($env['app_url']) ? "• App URL: {$env['app_url']}" : '') . PHP_EOL;

        // Trace
        $text .= $this->divider() .
            '🧱 Trace' . PHP_EOL .
            $this->fenced($e['trace']) . PHP_EOL;

        return $text;
    }

    protected function divider(): string
    {
        return PHP_EOL . '─────────────' . PHP_EOL;
    }

    protected function fenced(string $text): string
    {
        return "```\n" . ($text ?: '-') . "\n```";
    }

    protected function pretty($data): string
    {
        if (is_string($data)) return $data;
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) return print_r($data, true);
        return mb_strlen($json) > 4000 ? (mb_substr($json, 0, 4000) . "\n…(truncated)") : $json;
    }
}
