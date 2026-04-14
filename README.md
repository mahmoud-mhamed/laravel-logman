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
- **Queue Support** — send notifications asynchronously via Laravel queues
- **Retry Logic** — configurable retry attempts per channel on failure
- **Log Level per Channel** — each channel can filter by minimum severity (e.g. Mail = critical only)
- **Per-Channel Throttle** — independent cooldown per channel per exception
- **Rate Limiting** — prevents the same exception from flooding your channels (global cooldown)
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
- **Grouped Errors** — deduplicated view of recurring errors with counts
- **Export** — download filtered logs as CSV or JSON
- **Bookmarks** — save log entries for later reference
- **Config Viewer** — view all package settings from the web UI
- **About Page** — quick overview of features, channels, commands
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
composer require mahmoud-mhamed/laravel-logman
```

Laravel auto-discovers the package. Then run the install command:

```bash
php artisan logman:install
```

This will:
- Publish the config file (`config/logman.php`)
- Create the storage directory (`storage/logman`) with `.gitignore`
- Add all required env variables to `.env` and `.env.example`

> Use `--force` to overwrite an existing config file with the latest version.

#### Updating config after a package update

When Logman adds new config options in a new version, you can add them automatically:

```bash
php artisan logman:install --sync
```

This will compare your `config/logman.php` with the package default and **insert any missing keys** — with their default values, comments, and original formatting preserved. Your existing values stay untouched.

| Flag | Description |
|---|---|
| `--force` | Overwrite the entire config file with the latest package default |
| `--sync` | Add missing config keys with their defaults (preserves your existing values and formatting) |

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

To overwrite an existing config with the latest defaults:

```bash
php artisan vendor:publish --tag=logman-config --force
```

To add missing config keys after a package update (preserves your existing values):

```bash
php artisan logman:install --sync
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

### Per-Channel Options

Every channel supports these options:

| Option | Default | Description |
|---|---|---|
| `enabled` | varies | Enable/disable the channel |
| `auto_report_exceptions` | `true` | Auto-report exceptions (false = manual send only) |
| `daily_digest` | `true` | Include this channel in the daily digest |
| `min_level` | `'debug'` | Minimum log level (see levels below) |
| `queue` | `false` | Send notifications asynchronously via Laravel queues |
| `retries` | `0` | Number of retry attempts on failure |
| `throttle` | `0` | Per-channel cooldown in seconds (0 = no per-channel throttle) |

**Available log levels** (from lowest to highest severity):

```
debug → info → notice → warning → error → critical → alert → emergency
```

Setting `min_level` to `error` means only `error`, `critical`, `alert`, and `emergency` will be reported to that channel. Setting it to `debug` reports everything.

Example — Slack gets everything sync, Mail only gets critical errors async:

```php
'slack' => ['enabled' => true, 'min_level' => 'debug', 'queue' => false, 'throttle' => 0],
'mail'  => ['enabled' => true, 'min_level' => 'critical', 'queue' => true, 'retries' => 1, 'throttle' => 60],
```

---

### Rate Limiting & Throttling

Logman uses **two levels** of protection to prevent notification flooding:

#### Level 1: Global Rate Limit

Runs **first**, before any channel is contacted. If the same exception (same class + file + line) fires again within the cooldown window, it is blocked for **all channels** at once. This is the fast, cheap check that prevents unnecessary work.

| Option | Default | Description |
|---|---|---|
| `rate_limit.enabled` | `true` | Enable global rate limiting |
| `rate_limit.cooldown_seconds` | `10` | Seconds before the same exception can be re-reported to any channel |

When an error is re-sent after being rate-limited, the notification includes:

> This error was suppressed 5 time(s) since last report (rate limited).

#### Level 2: Per-Channel Throttle

Runs **second**, independently for each channel. This lets you set different cooldowns per channel — for example, send to Slack every second but limit emails to once per minute.

| Channel | Default | Description |
|---|---|---|
| `channels.slack.throttle` | `1` | Slack cooldown in seconds |
| `channels.telegram.throttle` | `10` | Telegram cooldown in seconds |
| `channels.discord.throttle` | `10` | Discord cooldown in seconds |
| `channels.mail.throttle` | `60` | Mail cooldown in seconds |

#### How They Work Together

```
Exception occurs
  → Global Rate Limit (blocked? → stop for ALL channels)
  → Per-Channel Throttle (blocked? → skip THIS channel only)
  → Send notification
```

Both checks are lightweight (file read / cache lookup) and **much faster** than the actual HTTP request or email send they prevent. Keeping both levels gives you coarse global protection plus fine-grained per-channel control.

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
| `/logman/analysis` | Charts, statistics, today vs yesterday comparison |
| `/logman/mutes` | Manage muted exceptions |
| `/logman/throttles` | Manage throttled exceptions |
| `/logman/grouped` | Deduplicated view of recurring errors |
| `/logman/bookmarks` | Saved log entries |
| `/logman/config` | View all package configuration |
| `/logman/about` | Package info, features, commands |

---

## Usage

### Automatic (default)

With `auto_report_exceptions` enabled (default), every unhandled exception is reported automatically. No code changes needed.

### Manual Reporting

```php
use MahmoudMhamed\Logman\Facades\Logman;

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
use MahmoudMhamed\Logman\Channels\ChannelInterface;

class PagerDutyChannel implements ChannelInterface
{
    public function sendException(array $payload): void { /* ... */ }
    public function sendInfo(string $message, array $context): void { /* ... */ }
}
```

Register it in a service provider:

```php
use MahmoudMhamed\Logman\LogmanService;

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
| `logman:install` | Publish config, create storage directory, add env variables |
| `logman:install --force` | Overwrite existing config with the package default |
| `logman:install --sync` | Add missing config keys while preserving existing values and formatting |
| `logman:test` | Send a test notification to all enabled channels |
| `logman:mute "ClassName" --duration=1d` | Mute an exception from CLI |
| `logman:list-mutes` | List all active mutes |
| `logman:clear-mutes` | Remove all active mutes |
| `logman:digest` | Send a daily digest summary to all enabled channels |
| `logman:digest --date=2026-04-12` | Digest for a specific date |
| `logman:digest --channel=slack` | Send digest to a specific channel only |

---

## Daily Digest

Logman can send a daily summary of all log activity to your enabled channels (Slack, Telegram, Discord, Mail). The digest includes:

- Total entries and error count
- Breakdown by log level (emergency, error, warning, info, etc.)
- Top 5 most frequent errors
- Per-file entry counts

### Automatic Setup (Recommended)

Enable the daily digest in your `config/logman.php` — no manual scheduler setup needed:

```php
'daily_digest' => [
    'enabled' => true,
    'time'    => '09:00',  // 24h format, server timezone
],
```

Logman will automatically register the scheduled command for you.

### Per-Channel Control

Each channel has its own `daily_digest` flag. A channel can receive real-time exception reports but skip the digest, or vice versa:

```php
'slack'    => ['enabled' => true,  'daily_digest' => true],   // gets the digest
'telegram' => ['enabled' => true,  'daily_digest' => false],  // no digest
'mail'     => ['enabled' => true,  'daily_digest' => true],   // gets the digest
```

### Manual Setup (Alternative)

If you prefer manual control, you can skip the config flag and schedule it yourself in `routes/console.php` or `app/Console/Kernel.php`:

```php
// Laravel 11+
Schedule::command('logman:digest')->dailyAt('09:00');

// Laravel 10 and below (in Kernel.php)
$schedule->command('logman:digest')->dailyAt('09:00');
```

### Options

```bash
# Send digest for yesterday (default)
php artisan logman:digest

# Send digest for a specific date
php artisan logman:digest --date=2026-04-12

# Send to a specific channel only (bypasses per-channel daily_digest flag)
php artisan logman:digest --channel=slack
```

---

## Testing

```bash
composer install
vendor/bin/phpunit
```

Tests cover:
- MuteService (mute, unmute, throttle, rate limiting)
- LogmanService (exception reporting, custom drivers, ignored exceptions)
- All routes (index, dashboard, mutes, throttles, grouped, bookmarks, config, about)

---

## License

MIT
