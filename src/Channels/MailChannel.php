<?php

namespace MahmoudMhamed\Logman\Channels;

use Illuminate\Support\Facades\Mail;
use Throwable;

class MailChannel implements ChannelInterface
{
    public function sendException(array $payload): void
    {
        try {
            $to = $this->getRecipients();
            $from = config('logman.channels.mail.from', config('mail.from.address'));

            if (empty($to)) {
                return;
            }

            $subject = "🚨 [{$payload['app']}] Exception in {$payload['env']}: " .
                mb_substr($payload['exception']['class'], 0, 80);

            Mail::raw($this->formatException($payload), function ($msg) use ($to, $from, $subject) {
                $msg->to($to)->subject($subject);
                if ($from) {
                    $msg->from($from, 'Logman');
                }
            });
        } catch (Throwable $e) {
            // Silently fail
        }
    }

    public function sendInfo(string $message, array $context): void
    {
        try {
            $to = $this->getRecipients();
            $from = config('logman.channels.mail.from', config('mail.from.address'));

            if (empty($to)) {
                return;
            }

            $subject = "ℹ️ [{$context['app']}] {$message}";

            $body = "Info: {$message}\n\n" .
                "App: {$context['app']}\n" .
                "Env: {$context['env']}\n" .
                "URL: {$context['url']}\n" .
                "Previous: {$context['previous_url']}\n";

            Mail::raw($body, function ($msg) use ($to, $from, $subject) {
                $msg->to($to)->subject($subject);
                if ($from) {
                    $msg->from($from, 'Logman');
                }
            });
        } catch (Throwable $e) {
            // Silently fail
        }
    }

    protected function formatException(array $p): string
    {
        $e = $p['exception'];
        $lines = [];

        $lines[] = "=== {$p['app']} — Exception in {$p['env']} ===";
        $lines[] = '';

        if ($p['suppressed_count'] > 0) {
            $lines[] = "⚠ Suppressed {$p['suppressed_count']} time(s) since last report";
            $lines[] = '';
        }

        // Exception
        $lines[] = '--- Exception ---';
        $lines[] = "Class:    {$e['class']}";
        $lines[] = "Message:  {$e['message']}";
        $lines[] = "File:     {$e['file']}:{$e['line']}";
        if ($e['code'] !== null) {
            $lines[] = "Code:     {$e['code']}";
        }
        $lines[] = "Previous: {$e['previous']}";
        $lines[] = '';

        // Auth
        if ($p['auth']) {
            $a = $p['auth'];
            $lines[] = '--- Auth ---';
            $lines[] = "Name:  {$a['name']}";
            $lines[] = "ID:    {$a['id']}";
            $lines[] = "Email: {$a['email']}";
            $lines[] = "Guard: {$a['guard']}";
            $lines[] = '';
        }

        // Request / CLI
        if ($p['request']) {
            $r = $p['request'];
            $lines[] = '--- Request ---';
            $lines[] = "Method:   {$r['method']}";
            $lines[] = "URL:      {$r['url']}";
            $lines[] = "Path:     /{$r['path']}";
            $lines[] = "IP:       {$r['ip']}";
            $lines[] = "Host:     {$r['host']}";
            $lines[] = "Route:    {$r['route']}";
            $lines[] = "Action:   {$r['action']}";
            $lines[] = "Duration: {$r['duration']}";
            $lines[] = '';
        } elseif ($p['cli']) {
            $lines[] = '--- CLI ---';
            $lines[] = "Command: {$p['cli']['command']}";
            $lines[] = '';
        }

        // Job
        if ($p['job']) {
            $j = $p['job'];
            $lines[] = '--- Job ---';
            $lines[] = "Name:       {$j['name']}";
            $lines[] = "Queue:      {$j['queue']}";
            $lines[] = "Connection: {$j['connection']}";
            $lines[] = "Attempts:   {$j['attempts']}";
            $lines[] = '';
        }

        // Queries
        if (!empty($p['queries'])) {
            $lines[] = '--- Last Queries (' . count($p['queries']) . ') ---';
            foreach ($p['queries'] as $q) {
                $lines[] = "({$q['time']}ms) {$q['sql']}{$q['bindings_str']}";
            }
            $lines[] = '';
        }

        // Environment
        $env = $p['environment'];
        $lines[] = '--- Environment ---';
        $lines[] = "Env:        {$env['env']}";
        $lines[] = "Time:       {$env['time']}";
        $lines[] = "PHP:        {$env['php']}";
        $lines[] = "Laravel:    {$env['laravel']}";
        $lines[] = "Memory:     {$env['memory']}";
        $lines[] = "Server:     {$env['hostname']}";
        $lines[] = "Git Commit: {$env['git_commit']}";
        if (!empty($env['app_url'])) {
            $lines[] = "App URL:    {$env['app_url']}";
        }
        $lines[] = '';

        // Trace
        $lines[] = '--- Stack Trace ---';
        $lines[] = $e['trace'];

        return implode("\n", $lines);
    }

    protected function getRecipients(): array
    {
        $to = config('logman.channels.mail.to', []);

        if (is_string($to)) {
            $to = array_map('trim', explode(',', $to));
        }

        return array_filter((array) $to);
    }
}
