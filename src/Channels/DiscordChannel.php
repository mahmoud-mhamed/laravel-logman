<?php

namespace Mhamed\Logman\Channels;

use Illuminate\Support\Facades\Http;
use Throwable;

class DiscordChannel implements ChannelInterface
{
    public function sendException(array $payload): void
    {
        try {
            $this->send($this->formatException($payload));
        } catch (Throwable $e) {
            // Silently fail
        }
    }

    public function sendInfo(string $message, array $context): void
    {
        try {
            $embed = [
                'title' => 'ℹ️ ' . $message,
                'color' => 0x3B82F6,
                'fields' => [
                    ['name' => 'App', 'value' => $context['app'], 'inline' => true],
                    ['name' => 'Env', 'value' => $context['env'], 'inline' => true],
                    ['name' => 'URL', 'value' => $context['url'], 'inline' => false],
                ],
                'timestamp' => now()->toIso8601String(),
            ];

            $this->sendWebhook(['embeds' => [$embed]]);
        } catch (Throwable $e) {
            // Silently fail
        }
    }

    protected function send(array $embeds): void
    {
        $this->sendWebhook(['embeds' => $embeds]);
    }

    protected function sendWebhook(array $body): void
    {
        $url = config('logman.channels.discord.webhook_url');

        if (!$url) {
            return;
        }

        Http::post($url, $body);
    }

    protected function formatException(array $p): array
    {
        $e = $p['exception'];
        $embeds = [];

        // Main embed
        $fields = [
            ['name' => 'Class', 'value' => '`' . $e['class'] . '`', 'inline' => false],
            ['name' => 'Message', 'value' => '```' . mb_substr($e['message'], 0, 1000) . '```', 'inline' => false],
            ['name' => 'File', 'value' => '`' . $e['file'] . ':' . $e['line'] . '`', 'inline' => true],
        ];

        if ($e['code'] !== null) {
            $fields[] = ['name' => 'Code', 'value' => '`' . $e['code'] . '`', 'inline' => true];
        }

        // Auth
        if ($p['auth']) {
            $a = $p['auth'];
            $fields[] = ['name' => 'User', 'value' => "{$a['name']} (ID: {$a['id']})\n{$a['email']}", 'inline' => true];
        }

        // Request / CLI
        if ($p['request']) {
            $r = $p['request'];
            $fields[] = ['name' => 'Request', 'value' => "`{$r['method']}` {$r['url']}\nIP: `{$r['ip']}` Duration: {$r['duration']}", 'inline' => false];
        } elseif ($p['cli']) {
            $fields[] = ['name' => 'CLI', 'value' => '`' . $p['cli']['command'] . '`', 'inline' => false];
        }

        // Job
        if ($p['job']) {
            $j = $p['job'];
            $fields[] = ['name' => 'Job', 'value' => "`{$j['name']}` on `{$j['queue']}` (attempt {$j['attempts']})", 'inline' => false];
        }

        // Environment
        $env = $p['environment'];
        $fields[] = ['name' => 'Environment', 'value' => "PHP `{$env['php']}` | Laravel `{$env['laravel']}` | Memory `{$env['memory']}`\nServer: `{$env['hostname']}` | Git: `{$env['git_commit']}`", 'inline' => false];

        $description = '';
        if ($p['suppressed_count'] > 0) {
            $description = "⚠️ Suppressed {$p['suppressed_count']} time(s) since last report";
        }

        $embeds[] = [
            'title' => '🚨 ' . $p['app'] . ' — Exception in ' . $p['env'],
            'description' => $description,
            'color' => 0xDC2626,
            'fields' => $fields,
            'timestamp' => $env['time'],
            'footer' => ['text' => 'Logman'],
        ];

        // Trace embed (separate to avoid field limits)
        if (!empty($e['trace'])) {
            $embeds[] = [
                'title' => '🧱 Stack Trace',
                'description' => '```' . mb_substr($e['trace'], 0, 4000) . '```',
                'color' => 0x6B7280,
            ];
        }

        return $embeds;
    }
}
