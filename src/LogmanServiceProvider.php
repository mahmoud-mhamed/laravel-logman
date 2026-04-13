<?php

namespace MahmoudMhamed\Logman;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use MahmoudMhamed\Logman\Console\Commands\LogmanClearMutesCommand;
use MahmoudMhamed\Logman\Console\Commands\LogmanDigestCommand;
use MahmoudMhamed\Logman\Console\Commands\LogmanInstallCommand;
use MahmoudMhamed\Logman\Console\Commands\LogmanListMutesCommand;
use MahmoudMhamed\Logman\Console\Commands\LogmanMuteCommand;
use MahmoudMhamed\Logman\Console\Commands\LogmanTestCommand;
use MahmoudMhamed\Logman\Http\Middleware\AuthorizeLogman;
use MahmoudMhamed\Logman\Services\MuteService;
use Throwable;

class LogmanServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        require_once __DIR__ . '/helpers.php';

        $this->mergeConfigFrom(__DIR__ . '/../config/logman.php', 'logman');

        $this->app->singleton(LogmanService::class, function () {
            return new LogmanService();
        });

        $this->app->singleton(MuteService::class, function () {
            return new MuteService();
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/logman.php' => config_path('logman.php'),
        ], 'logman-config');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/logman'),
        ], 'logman-views');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'logman');

        if (config('logman.viewer.enabled', true)) {
            $this->registerRoutes();
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                LogmanInstallCommand::class,
                LogmanTestCommand::class,
                LogmanMuteCommand::class,
                LogmanListMutesCommand::class,
                LogmanClearMutesCommand::class,
                LogmanDigestCommand::class,
            ]);
        }

        $this->ensureStorageDirectory();
        $this->injectSlackChannelIfMissing();

        if (config('logman.auto_report_exceptions')) {
            $this->registerExceptionReporting();
        }

        if (config('logman.daily_digest.enabled')) {
            $this->registerDailyDigest();
        }
    }

    protected function registerRoutes(): void
    {
        $middleware = config('logman.viewer.middleware', ['web']);

        $middleware[] = AuthorizeLogman::class;

        $this->app['router']->middlewareGroup('logman', $middleware);

        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
    }

    protected function ensureStorageDirectory(): void
    {
        $path = config('logman.storage_path', storage_path('logman'));

        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true);
            File::put($path . '/.gitignore', "*\n!.gitignore\n");
        }

        // Ensure JSON data files exist so read/write operations don't fail
        foreach (['mutes.json', 'throttles.json', 'rate_limits.json'] as $file) {
            $filePath = $path . '/' . $file;
            if (!File::exists($filePath)) {
                File::put($filePath, '[]');
            }
        }
    }

    protected function injectSlackChannelIfMissing(): void
    {
        $channelName = config('logman.channels.slack.log_channel', 'slack');

        if (config("logging.channels.{$channelName}") === null) {
            $channelConfig = config('logman.slack_channel_config', []);
            config(["logging.channels.{$channelName}" => $channelConfig]);
        }
    }

    protected function registerDailyDigest(): void
    {
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);
            $time = config('logman.daily_digest.time', '09:00');
            $schedule->command('logman:digest')->dailyAt($time);
        });
    }

    protected function registerExceptionReporting(): void
    {
        try {
            $handler = $this->app->make(ExceptionHandler::class);

            if (method_exists($handler, 'reportable')) {
                $handler->reportable(function (Throwable $e) {
                    $this->app->make(LogmanService::class)->logException($e);
                });
            }
        } catch (Throwable $e) {
            // Silently fail — the handler may not be available yet
        }
    }
}
