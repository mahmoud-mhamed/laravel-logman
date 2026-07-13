<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable in Production
    |--------------------------------------------------------------------------
    */
    'enable_production' => true,

    /*
    |--------------------------------------------------------------------------
    | Enable in Local
    |--------------------------------------------------------------------------
    */
    'enable_local' => false,

    /*
    |--------------------------------------------------------------------------
    | Auto-Report Exceptions
    |--------------------------------------------------------------------------
    */
    'auto_report_exceptions' => true,

    /*
    |--------------------------------------------------------------------------
    | Ignored Exceptions
    |--------------------------------------------------------------------------
    */
    'ignore' => [
        // \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        // \Illuminate\Auth\AuthenticationException::class,
        // \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        // \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Daily Digest
    |--------------------------------------------------------------------------
    |
    | When enabled, Logman will automatically schedule a daily digest summary
    | and send it to all enabled channels. No manual scheduler setup needed.
    |
    */
    'daily_digest' => [
        'enabled' => false,
        'time' => '09:00',  // Time to send (24h format, server timezone)
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Channels
    |--------------------------------------------------------------------------
    |
    | Enable one or more channels to receive exception notifications.
    | Each channel can be enabled/disabled independently.
    |
    */
    'channels' => [

        'slack' => [
            'enabled' => false,
            'auto_report_exceptions' => true,
            'daily_digest' => true,         // Include in daily digest
            // Levels: debug, info, notice, warning, error, critical, alert, emergency
            'min_level' => 'debug',         // Minimum log level to report (debug = all)
            'queue' => false,               // Send via queue (async)
            'retries' => 0,                 // Retry attempts on failure
            'throttle' => 1,               // Per-channel cooldown in seconds (0 = use global)
            'log_channel' => 'slack',       // Laravel logging channel name
        ],

        'telegram' => [
            'enabled' => false,
            'auto_report_exceptions' => true,
            'daily_digest' => true,
            // Levels: debug, info, notice, warning, error, critical, alert, emergency
            'min_level' => 'error',
            'queue' => true,
            'retries' => 2,
            'throttle' => 10,
            'bot_token' => env('LOGMAN_TELEGRAM_BOT_TOKEN'),
            'chat_id' => env('LOGMAN_TELEGRAM_CHAT_ID'),
        ],

        'discord' => [
            'enabled' => false,
            'auto_report_exceptions' => true,
            'daily_digest' => true,
            // Levels: debug, info, notice, warning, error, critical, alert, emergency
            'min_level' => 'error',
            'queue' => true,
            'retries' => 2,
            'throttle' => 10,
            'webhook_url' => env('LOGMAN_DISCORD_WEBHOOK'),
        ],

        'mail' => [
            'enabled' => false,
            'auto_report_exceptions' => true,
            'daily_digest' => true,
            // Levels: debug, info, notice, warning, error, critical, alert, emergency
            'min_level' => 'error',
            'queue' => true,
            'retries' => 1,
            'throttle' => 60,              // 60s cooldown per exception for email
            'to' => explode(',', env('LOGMAN_MAIL_TO', '')),
            'from' => env('LOGMAN_MAIL_FROM'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Request Logging
    |--------------------------------------------------------------------------
    |
    | When enabled, Logman logs every incoming HTTP request to a dedicated
    | log channel. Disabled by default. The generated log file lives in the
    | viewer's storage path, so it shows up automatically in the Log Viewer.
    |
    */
    'request_logging' => [

        // Master switch — disabled by default.
        'enabled' => env('LOGMAN_REQUEST_LOGGING', false),

        // Dedicated logging channel used for request entries.
        // Auto-created (see request_channel_config) if not already defined.
        'channel' => 'logman_requests',

        // Log level used for each request entry.
        'level' => 'info',

        // What to capture in each entry (sanitized via hidden_fields).
        'log_query' => true,      // Query string parameters
        'log_body' => true,       // Request body (POST/PUT/PATCH ...)
        'log_headers' => true,    // A curated set of request headers
        'log_response_status' => true, // HTTP status code of the response
        'log_response_body' => false,  // Response body (off by default — can be large/sensitive)

        // Where to attach the request-logging middleware.
        //   'global'          → every request (default)
        //   'web' / 'api'     → only that route middleware group
        //   ['web', 'api']    → multiple groups
        'middleware' => 'global',

        // Only log these HTTP methods. Empty = log all methods.
        // Read from env as a comma-separated list, e.g. LOGMAN_REQUEST_METHODS="POST,PUT,DELETE".
        'methods' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('LOGMAN_REQUEST_METHODS', ''))
        ))),

        // Allowlist by PATH — only log requests whose path matches one of these
        // patterns (supports * wildcards, e.g. "api/*", "admin/users").
        // Empty = no path restriction. Read from env as a comma-separated list:
        //   LOGMAN_REQUEST_ONLY="api/orders/*,checkout"
        'only' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('LOGMAN_REQUEST_ONLY', ''))
        ))),

        // Allowlist by SUBSTRING — only log requests whose full URL contains one
        // of these strings. Empty = no restriction. Read from env:
        //   LOGMAN_REQUEST_ONLY_CONTAINING="checkout,?debug=1"
        // When both "only" and "only_containing" are set, a request is logged if
        // it satisfies EITHER list.
        'only_containing' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('LOGMAN_REQUEST_ONLY_CONTAINING', ''))
        ))),

        // Do not log the request body for these methods (usually none have one).
        'body_ignore_methods' => ['GET', 'HEAD', 'OPTIONS'],

        // Skip requests whose path matches any of these patterns (supports *).
        // Logman's own viewer routes are always excluded automatically.
        'except' => [
            'telescope*',
            'horizon*',
            '_debugbar*',
            'livewire*',
            'sanctum*',
            '*.js', '*.css', '*.ico', '*.map',
            '*.png', '*.jpg', '*.jpeg', '*.gif', '*.svg', '*.webp',
            '*.woff', '*.woff2', '*.ttf', '*.eot',
        ],

        // Max characters kept for the body/headers payload (guards huge uploads).
        'max_payload_length' => 8000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Channel Config (auto-injected into logging.channels)
    |--------------------------------------------------------------------------
    |
    | If the request_logging.channel above isn't already defined in your
    | app's logging config, Logman creates it automatically using this.
    |
    */
    'request_channel_config' => [
        'driver' => 'daily',
        'path' => storage_path('logs/logman-requests.log'),
        'level' => 'debug',
        'days' => 14,
    ],

    /*
    |--------------------------------------------------------------------------
    | Slack Channel Config (auto-injected into logging.channels)
    |--------------------------------------------------------------------------
    |
    | If your app doesn't already define the logging channel above,
    | Logman will create it automatically using this config.
    |
    */
    'slack_channel_config' => [
        'driver' => 'slack',
        'url' => env('LOG_SLACK_WEBHOOK_URL'),
        'username' => 'Exception Bot',
        'emoji' => ':boom:',
        'level' => 'error',
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Path
    |--------------------------------------------------------------------------
    |
    | Directory for package data files (mutes.json, rate_limits.json, etc.)
    | This folder is auto-created on boot with a .gitignore inside it.
    |
    */
    'storage_path' => storage_path('logman'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Prevent the same exception from being sent more than once
    | within the given cooldown period (in seconds).
    |
    */
    'rate_limit' => [
        'enabled' => true,
        'cooldown_seconds' => 10, // 10 seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Hidden Fields
    |--------------------------------------------------------------------------
    |
    | These field names will be masked with '********' in notification payloads
    | (request body, query params, DB bindings) to prevent leaking secrets.
    |
    */
    'hidden_fields' => [
        'password', 'password_confirmation', 'token', 'secret',
        'credit_card', 'card_number', 'cvv', 'ssn',
        'authorization', 'api_key', 'api_secret', 'access_token',
        'refresh_token', 'private_key',
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Viewer
    |--------------------------------------------------------------------------
    */
    'viewer' => [

        // Enable or disable the log viewer routes
        'enabled' => env('LOGMAN_VIEWER_ENABLED', true),

        // Simple password protection for the log viewer.
        // Set a password to require authentication before accessing Logman.
        // Leave null to disable password protection.
        'password' => env('LOGMAN_PASSWORD', null),

        // URL prefix (e.g. /logman)
        'route_prefix' => 'logman',

        // "Open in IDE" links for source locations (e.g. the manual request-log
        // "called_from" reference). Set LOGMAN_EDITOR to one of:
        //   phpstorm, idea, vscode, vscode-insiders, vscodium, sublime, atom,
        //   nova, macvim, emacs, textmate, netbeans
        // Leave null to render locations as plain text (no clickable link).
        'editor' => env('LOGMAN_EDITOR'),

        // Optional remote→local path mapping so server logs open in your local
        // IDE. Example: ['/var/www/app' => '/Users/me/Code/app'].
        'editor_path_map' => [],

        // Middleware applied to log viewer routes
        // Add 'auth' for production: ['web', 'auth']
        'middleware' => ['web'],

        // Authorization callback — return true to allow access, false to deny.
        // Set to null to allow access only in local environment.
        // To allow all access, use: true or fn () => true
        // Example: fn ($request) => $request->user()?->isAdmin()
        'authorize' => true,

        // Path to the log files directory
        'storage_path' => storage_path('logs'),

        // Glob pattern for matching log files
        'pattern' => '*.log',

        // Maximum file size to display (in bytes). Default: 50 MB
        'max_file_size' => 50 * 1024 * 1024,

        // Entries per page
        'per_page' => 25,

        // Available per-page options
        'per_page_options' => [15, 25, 50, 100],
    ],

];
