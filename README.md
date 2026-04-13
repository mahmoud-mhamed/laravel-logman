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
- **Automatic Exception Reporting** — every unhandled exception, reported with full context
- **Rich Error Details** — stack trace, request info, auth user, DB queries, job context, environment
- **Rate Limiting** — prevents the same exception from flooding your channel (configurable cooldown)
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

### 1. Set up your Slack Webhook

1. Go to [Slack Apps](https://api.slack.com/apps) > **Create New App** > **From scratch**
2. Navigate to **Incoming Webhooks** > toggle **On**
3. Click **Add New Webhook to Workspace** > select your channel > **Allow**
4. Copy the Webhook URL

### 2. Add to `.env`

```env
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/T.../B.../xxxx
```

**Done.** Every unhandled exception will now be reported to Slack automatically.

### 3. Open the Log Viewer

Navigate to `/logman` in your browser to access the log viewer.

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
| `log_channel` | `'slack'` | Laravel logging channel to use |
| `ignore` | `[]` | Exception classes to skip (uses `instanceof`) |
| `storage_path` | `storage/logman` | Directory for mutes, throttles, rate limits data |

### Rate Limiting

| Option | Default | Description |
|---|---|---|
| `rate_limit.enabled` | `true` | Enable rate limiting |
| `rate_limit.cooldown_seconds` | `10` | Seconds before the same exception can be re-reported |

When an error is re-sent after being rate-limited, the notification includes:

> This error was suppressed 5 time(s) since last report (rate limited).

### Slack Channel Config

If your app doesn't already define a `slack` logging channel, Logman creates one automatically:

```php
'slack_channel_config' => [
    'driver'   => 'slack',
    'url'      => env('LOG_SLACK_WEBHOOK_URL'),
    'username' => 'Exception Bot',
    'emoji'    => ':boom:',
    'level'    => 'error',
],
```

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

// Send an info message
Logman::slackLogInfo('Deployment completed successfully');
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

For production, add authentication middleware to protect the log viewer:

```php
// config/logman.php
'log_viewer' => [
    'middleware' => ['web', 'auth'],
],
```

---

## Roadmap

- [ ] Multi-channel notifications (Telegram, Discord, Email)
- [ ] Custom notification channels

---

## License

MIT
