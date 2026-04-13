<?php

namespace MahmoudMhamed\Logman\Channels;

use Illuminate\Support\Facades\Http;
use Throwable;

class TelegramChannel implements ChannelInterface
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
            $text = "ℹ️ *{$this->escape($message)}*\n\n" .
                "App: {$this->escape($context['app'])}\n" .
                "Env: `{$context['env']}`\n" .
                "URL: {$this->escape($context['url'])}";

            $this->send($text);
        } catch (Throwable $e) {
            // Silently fail
        }
    }

    protected function send(string $text): void
    {
        $token = config('logman.channels.telegram.bot_token');
        $chatId = config('logman.channels.telegram.chat_id');

        if (!$token || !$chatId) {
            return;
        }

        // Telegram has a 4096 char limit
        $text = mb_substr($text, 0, 4000);

        Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'MarkdownV2',
            'disable_web_page_preview' => true,
        ]);
    }

    protected function formatException(array $p): string
    {
        $e = $p['exception'];

        $text = "🚨 *{$this->escape($p['app'])}* — Exception in `{$p['env']}`\n\n";

        if ($p['suppressed_count'] > 0) {
            $text .= "⚠️ _Suppressed {$p['suppressed_count']} time\\(s\\) since last report_\n\n";
        }

        $text .= "🐞 *Exception*\n" .
            "Class: `{$this->escape($e['class'])}`\n" .
            "Message: `{$this->escape(mb_substr($e['message'], 0, 500))}`\n" .
            "File: `{$this->escape($e['file'])}:{$e['line']}`\n";

        if ($e['code'] !== null) {
            $text .= "Code: `{$e['code']}`\n";
        }

        // Auth
        if ($p['auth']) {
            $a = $p['auth'];
            $text .= "\n👤 *Auth*\n" .
                "User: {$this->escape($a['name'])} \\(ID: {$a['id']}\\)\n" .
                "Email: {$this->escape($a['email'])}\n";
        }

        // Request / CLI
        if ($p['request']) {
            $r = $p['request'];
            $text .= "\n🧭 *Request*\n" .
                "`{$r['method']}` {$this->escape($r['url'])}\n" .
                "IP: `{$r['ip']}` Duration: {$this->escape($r['duration'])}\n";
        } elseif ($p['cli']) {
            $text .= "\n⌨️ *CLI*\n`{$this->escape($p['cli']['command'])}`\n";
        }

        // Job
        if ($p['job']) {
            $j = $p['job'];
            $text .= "\n📮 *Job*\n`{$this->escape($j['name'])}` on `{$this->escape($j['queue'])}`\n";
        }

        // Environment
        $env = $p['environment'];
        $text .= "\n🧰 *Environment*\n" .
            "PHP: `{$env['php']}` Laravel: `{$env['laravel']}`\n" .
            "Server: `{$this->escape($env['hostname'])}` Memory: `{$env['memory']}`\n";

        return $text;
    }

    protected function escape(string $text): string
    {
        return str_replace(
            ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'],
            ['\\_', '\\*', '\\[', '\\]', '\\(', '\\)', '\\~', '\\`', '\\>', '\\#', '\\+', '\\-', '\\=', '\\|', '\\{', '\\}', '\\.', '\\!'],
            $text
        );
    }
}
