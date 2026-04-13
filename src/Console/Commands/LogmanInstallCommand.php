<?php

namespace Mhamed\Logman\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class LogmanInstallCommand extends Command
{
    protected $signature = 'logman:install
        {--force : Overwrite config file if it already exists}';

    protected $description = 'Install Logman: publish config, create storage directory, and add env variables';

    public function handle(): int
    {
        $this->info('');
        $this->components->info('Installing Logman...');
        $this->info('');

        $this->publishConfig();
        $this->createStorageDirectory();
        $this->addEnvVariables();
        $this->printSummary();

        return self::SUCCESS;
    }

    protected function publishConfig(): void
    {
        $configPath = config_path('logman.php');

        if (File::exists($configPath) && !$this->option('force')) {
            $this->components->warn('Config file already exists: config/logman.php (use --force to overwrite)');
            return;
        }

        $this->call('vendor:publish', [
            '--tag' => 'logman-config',
            '--force' => $this->option('force'),
        ]);

        $this->components->task('Published config file', fn () => true);
    }

    protected function createStorageDirectory(): void
    {
        $path = config('logman.storage_path', storage_path('logman'));

        if (File::isDirectory($path)) {
            $this->components->task('Storage directory already exists', fn () => true);
            return;
        }

        File::makeDirectory($path, 0755, true);
        File::put($path . '/.gitignore', "*\n!.gitignore\n");

        $this->components->task('Created storage directory: ' . str_replace(base_path() . '/', '', $path), fn () => true);
    }

    protected function addEnvVariables(): void
    {
        $envPath = base_path('.env');
        $examplePath = base_path('.env.example');

        $variables = [
            '',
            '# Logman — Notification Channels',
            '# Docs: https://github.com/mhamed/laravel-logman',
            'LOG_SLACK_WEBHOOK_URL=',
            'LOGMAN_TELEGRAM_BOT_TOKEN=',
            'LOGMAN_TELEGRAM_CHAT_ID=',
            'LOGMAN_DISCORD_WEBHOOK=',
            'LOGMAN_MAIL_TO=',
            'LOGMAN_MAIL_FROM=',
        ];

        $block = implode("\n", $variables) . "\n";

        // Add to .env.example
        if (File::exists($examplePath)) {
            $content = File::get($examplePath);
            if (!str_contains($content, 'LOG_SLACK_WEBHOOK_URL')) {
                File::append($examplePath, "\n" . $block);
                $this->components->task('Added env variables to .env.example', fn () => true);
            } else {
                $this->components->task('Env variables already in .env.example', fn () => true);
            }
        } else {
            $this->components->warn('.env.example not found — skipped');
        }

        // Add to .env
        if (File::exists($envPath)) {
            $content = File::get($envPath);
            if (!str_contains($content, 'LOG_SLACK_WEBHOOK_URL')) {
                File::append($envPath, "\n" . $block);
                $this->components->task('Added env variables to .env', fn () => true);
            } else {
                $this->components->task('Env variables already in .env', fn () => true);
            }
        } else {
            $this->components->warn('.env not found — skipped');
        }
    }

    protected function printSummary(): void
    {
        $this->info('');
        $this->components->info('Logman installed successfully!');
        $this->info('');
        $this->line('  <fg=gray>Next steps:</>');
        $this->line('  <fg=gray>1.</> Fill in your channel credentials in <fg=yellow>.env</>');
        $this->line('  <fg=gray>2.</> Enable channels in <fg=yellow>config/logman.php</>');
        $this->line('  <fg=gray>3.</> Visit <fg=yellow>/' . config('logman.viewer.route_prefix', 'logman') . '</> to open the log viewer');
        $this->line('  <fg=gray>4.</> Run <fg=yellow>php artisan logman:test</> to send a test notification');
        $this->info('');
    }
}
