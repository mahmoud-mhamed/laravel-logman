<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.1+-8892BF?style=flat-square&logo=php&logoColor=white" alt="PHP 8.1+">
  <img src="https://img.shields.io/badge/Laravel-10.x%20|%2011.x%20|%2012.x-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel">
  <img src="https://img.shields.io/badge/License-MIT-green?style=flat-square" alt="MIT License">
</p>

# Logman

**Error reporting & log management for Laravel** — automatic exception notifications with a powerful built-in log viewer, mute/throttle controls, and an analysis dashboard.

---

## What it does

When an unhandled exception occurs in your Laravel app, Logman captures everything — the stack trace, the request, the authenticated user, recent database queries, queue job details — and sends a rich, formatted notification to your configured channel. All of this happens automatically, with zero code changes.

On top of that, Logman ships with a **full-featured log viewer** you can access from your browser to browse, search, filter, mute, throttle, and review your Laravel log files.

---

## Features

### Notifications
- **Multi-Channel** — send to Slack, Telegram, Discord, Email — or all at once
- **Automatic Exception Reporting** — every unhandled exception, reported with full context
- **Rich Error Details** — stack trace, request info, auth user, DB queries, job context, environment
- **Custom Channels** — register your own notification driver with one line
- **Rate Limiting** — prevents the same exception from flooding your channels (configurable cooldown)
- **Mute System** — temporarily silence specific exceptions
- **Throttle System** — limit how many times an exception is reported per time period

### Log Viewer (`/logman`)
- **Browse & Search** — full-text search with multi-word AND matching + regex mode
- **Level Filtering** — emergency, alert, critical, error, warning, notice, info, debug
- **Date & Time Filtering** — filter by date range and time range
- **Analysis Dashboard** — charts, statistics, per-file breakdowns, today's summary
- **Review System** — mark entries as reviewed / in-progress / won't-fix with notes
- **Mute & Throttle from UI** — mute or throttle any error directly from the log viewer
- **Stack Trace Viewer** — expandable details with tabs (Stack Trace, Context, Raw)
- **In-Detail Search** — search within stack traces with match navigation (prev/next)
- **File Management** — download, clear, delete, batch-delete log files
- **Config Viewer** — view all package settings from the web UI
- **Dark Mode** — full dark/light theme with localStorage persistence
- **Zero Dependencies** — no external CSS/JS frameworks, pure standalone UI
- **Responsive** — works on desktop and mobile

---

## Requirements

- PHP 8.1+
- Laravel 10.x, 11.x, or 12.x

---

## Installation

```bash
composer require mhamed/laravel-logman
```

Laravel auto-discovers the package. No manual registration needed.

---

## Quick Start

### 1. Pick a channel and configure it (see details below)

### 2. Test your setup

```bash
php artisan logman:test
```

### 3. Open the Log Viewer

Navigate to `/logman` in your browser.

---

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=logman-config
```

Publish the views (optional):

```bash
php artisan vendor:publish --tag=logman-views
```

### General Options

| Option | Default | Description |
|---|---|---|
| `enable_production` | `true` | Send reports in production |
| `enable_local` | `false` | Send reports in local environment |
| `auto_report_exceptions` | `true` | Auto-register in exception handler |
| `ignore` | `[]` | Exception classes to skip (uses `instanceof`) |
| `storage_path` | `storage/logman` | Directory for mutes, throttles, rate limits data |

### Channel: Slack

Slack is **enabled by default**.

**Setup:**

1. Go to [https://api.slack.com/apps](https://api.slack.com/apps)
2. Click **Create New App** > **From scratch**
3. Give it a name (e.g. "Exception Bot") and select your workspace
4. In the left sidebar, click **Incoming Webhooks**
5. Toggle **Activate Incoming Webhooks** to **On**
6. Click **Add New Webhook to Workspace**
7. Select the channel and click **Allow**
8. Copy the **Webhook URL**

**Add to `.env`:**

```env
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/T.../B.../xxxx
```

If your app doesn't already define a `slack` logging channel, Logman creates one automatically using `slack_channel_config` in the config file.

---

### Channel: Telegram

**Setup:**

1. Open Telegram and search for **@BotFather**
2. Send `/newbot` and follow the prompts to create a bot
3. Copy the **Bot Token** you receive
4. Add the bot to your group/channel
5. To get the **Chat ID**: send a message in the group, then open `https://api.telegram.org/bot<TOKEN>/getUpdates` and find the `chat.id` value

**Add to `.env`:**

```env
LOGMAN_TELEGRAM_BOT_TOKEN=123456789:ABCdefGHIjklMNOpqrsTUVwxyz
LOGMAN_TELEGRAM_CHAT_ID=-100123456789
```

**Enable in `config/logman.php`:**

```php
'telegram' => [
    'enabled' => true,
    ...
],
```

---

### Channel: Discord

**Setup:**

1. Open your Discord server and go to **Server Settings** > **Integrations**
2. Click **Webhooks** > **New Webhook**
3. Choose a name and the channel to post to
4. Click **Copy Webhook URL**

**Add to `.env`:**

```env
LOGMAN_DISCORD_WEBHOOK=https://discord.com/api/webhooks/1234567890/abcdef...
```

**Enable in `config/logman.php`:**

```php
'discord' => [
    'enabled' => true,
    ...
],
```

---

### Channel: Mail

Sends exception reports via email using Laravel's built-in mail system. Make sure your app's mail config (`config/mail.php`) is working first.

**Add to `.env`:**

```env
LOGMAN_MAIL_TO=alerts@example.com,team@example.com
```

**Enable in `config/logman.php`:**

```php
'mail' => [
    'enabled' => true,
    ...
],
```

Multiple recipients: use comma-separated emails in `LOGMAN_MAIL_TO`.

---

### Per-Channel Auto-Report

Each channel has its own `auto_report_exceptions` flag. A channel can be `enabled` but not auto-report — useful if you only want to send manually via the log viewer's "Send to Channel" button:

```php
'slack'    => ['enabled' => true,  'auto_report_exceptions' => true],   // auto + manual
'mail'     => ['enabled' => true,  'auto_report_exceptions' => false],  // manual only
```

---

### Rate Limiting

| Option | Default | Description |
|---|---|---|
| `rate_limit.enabled` | `true` | Enable rate limiting |
| `rate_limit.cooldown_seconds` | `10` | Seconds before the same exception can be re-reported |

When an error is re-sent after being rate-limited, the notification includes:

> This error was suppressed 5 time(s) since last report (rate limited).

### Log Viewer Options

| Option | Default | Description |
|---|---|---|
| `log_viewer.enabled` | `true` | Enable/disable log viewer routes |
| `log_viewer.route_prefix` | `'logman'` | URL prefix |
| `log_viewer.middleware` | `['web']` | Route middleware |
| `log_viewer.storage_path` | `storage/logs` | Log files directory |
| `log_viewer.pattern` | `'*.log'` | File glob pattern |
| `log_viewer.max_file_size` | `50 MB` | Max file size to display |
| `log_viewer.per_page` | `25` | Entries per page |
| `log_viewer.per_page_options` | `[15,25,50,100]` | Available per-page options |

---

## Pages

| Route | Description |
|---|---|
| `/logman` | Browse, search, and filter log files |
| `/logman/analysis` | Charts, statistics, today's summary |
| `/logman/mutes` | Manage muted exceptions |
| `/logman/throttles` | Manage throttled exceptions |
| `/logman/config` | View all package configuration |

---

## Usage

### Automatic (default)

With `auto_report_exceptions` enabled (default), every unhandled exception is reported automatically. No code changes needed.

### Manual Reporting

```php
use Mhamed\Logman\Facades\Logman;

// Report an exception manually
try {
    // risky operation...
} catch (\Throwable $e) {
    Logman::logException($e);
}

// Send an info message to all enabled channels
Logman::sendInfo('Deployment completed successfully');
```

### Ignoring Exceptions

```php
// config/logman.php
'ignore' => [
    \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
    \Illuminate\Auth\AuthenticationException::class,
    \Illuminate\Database\Eloquent\ModelNotFoundException::class,
],
```

Uses `instanceof` — ignoring a parent class also ignores all its subclasses.

---

## Mute System

Temporarily silence specific exceptions from being reported. Available from the **log viewer UI** or the **Mutes page**.

- **Exception Class** — fully qualified class name or partial match
- **Message Pattern** — optional partial message match
- **Duration** — 1h, 6h, 12h, 1d, 3d, 1w, 1m
- **Reason** — optional note for context
- **Hit Counter** — tracks how many times the mute blocked a notification
- **Extend** — extend active mutes without removing them

---

## Throttle System

Limit how many times an exception is reported per time period.

Example: allow max **5 reports per hour** for a specific exception — after that, further occurrences are silently suppressed until the period resets.

---

## What Gets Reported

Each notification includes:

| Section | Details |
|---|---|
| **Exception** | Class, message, file, line, code, previous exception |
| **Auth** | User name, ID, email, guard |
| **Request** | Method, URL, path, IP, host, route, action, duration, headers, query params, body, files |
| **CLI** | Command (for console exceptions) |
| **Job** | Job name, queue, connection, attempts |
| **Queries** | Last 5 database queries with execution time |
| **Environment** | PHP version, Laravel version, memory peak, hostname, git commit, app URL |
| **Trace** | Stack trace (first 2000 characters) |

---

## Performance

Logman has **zero impact** on normal requests (no exceptions). When an exception occurs:

- **In-memory caching** — JSON data files are read at most once per request
- **Deferred writes** — state changes are batched and written once at request termination
- **Singleton services** — instantiated once per application lifecycle
- **Early returns** — empty checks skip all processing

---

## Security

For production, add authentication middleware and/or an authorize callback:

```php
// config/logman.php
'log_viewer' => [
    'middleware' => ['web', 'auth'],
    'authorize' => fn ($request) => $request->user()?->isAdmin(),
],
```

---

## Custom Channels

Register your own notification channel:

```php
use Mhamed\Logman\Channels\ChannelInterface;

class PagerDutyChannel implements ChannelInterface
{
    public function sendException(array $payload): void { /* ... */ }
    public function sendInfo(string $message, array $context): void { /* ... */ }
}
```

Register it in a service provider:

```php
use Mhamed\Logman\LogmanService;

LogmanService::registerDriver('pagerduty', PagerDutyChannel::class);
```

Then add it to your config:

```php
'channels' => [
    'pagerduty' => [
        'enabled' => true,
        'driver' => \App\Channels\PagerDutyChannel::class,
    ],
],
```

---

## Artisan Commands

| Command | Description |
|---|---|
| `logman:test` | Send a test notification to all enabled channels |
| `logman:mute "ClassName" --duration=1d` | Mute an exception from CLI |
| `logman:list-mutes` | List all active mutes |
| `logman:clear-mutes` | Remove all active mutes |
| `logman:digest` | Send a daily digest summary to all enabled channels |
| `logman:digest --date=2026-04-12` | Digest for a specific date |
| `logman:digest --channel=slack` | Send digest to a specific channel only |

Schedule it in your `routes/console.php` or `app/Console/Kernel.php`:

```php
$schedule->command('logman:digest')->dailyAt('09:00');
```

---

## License

MIT
