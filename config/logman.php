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
    | Notification Channels
    |--------------------------------------------------------------------------
    |
    | Enable one or more channels to receive exception notifications.
    | Each channel can be enabled/disabled independently.
    |
    */
    'channels' => [

        'slack' => [
            'enabled' => true,
            'auto_report_exceptions' => true,
            'log_channel' => 'slack', // Laravel logging channel name
        ],

        'telegram' => [
            'enabled' => false,
            'auto_report_exceptions' => true,
            'bot_token' => env('LOGMAN_TELEGRAM_BOT_TOKEN'),
            'chat_id' => env('LOGMAN_TELEGRAM_CHAT_ID'),
        ],

        'discord' => [
            'enabled' => false,
            'auto_report_exceptions' => true,
            'webhook_url' => env('LOGMAN_DISCORD_WEBHOOK'),
        ],

        'mail' => [
            'enabled' => false,
            'auto_report_exceptions' => true,
            'to' => explode(',', env('LOGMAN_MAIL_TO', '')),  // array of emails
            'from' => env('LOGMAN_MAIL_FROM'),                // null = use default mail.from
        ],

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
    | Log Viewer
    |--------------------------------------------------------------------------
    */
    'log_viewer' => [

        // Enable or disable the log viewer routes
        'enabled' => env('LOG_VIEWER_ENABLED', true),

        // URL prefix (e.g. /logman)
        'route_prefix' => 'logman',

        // Middleware applied to log viewer routes
        // Add 'auth' for production: ['web', 'auth']
        'middleware' => ['web'],

        // Authorization callback — return true to allow access, false to deny.
        // Set to null to allow all authenticated users (when using 'auth' middleware).
        // Example: fn ($request) => $request->user()?->isAdmin()
        'authorize' => null,

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
