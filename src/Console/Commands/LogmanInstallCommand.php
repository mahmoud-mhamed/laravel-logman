<?php

namespace MahmoudMhamed\Logman\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class LogmanInstallCommand extends Command
{
    protected $signature = 'logman:install
        {--force : Overwrite config file if it already exists}
        {--sync : Add missing config keys without overwriting existing values}';

    protected $description = 'Install Logman: publish config, create storage directory, and add env variables';

    public function handle(): int
    {
        $this->info('');
        $this->components->info('Installing Logman...');
        $this->info('');

        if ($this->option('sync')) {
            $this->syncConfig();
        } else {
            $this->publishConfig();
        }

        $this->createStorageDirectory();
        $this->addEnvVariables();
        $this->printSummary();

        return self::SUCCESS;
    }

    protected function publishConfig(): void
    {
        $configPath = config_path('logman.php');

        if (File::exists($configPath) && !$this->option('force')) {
            $this->components->warn('Config file already exists: config/logman.php (use --force to overwrite, or --sync to add missing keys)');
            return;
        }

        $this->call('vendor:publish', [
            '--tag' => 'logman-config',
            '--force' => $this->option('force'),
        ]);

        $this->components->task('Published config file', fn () => true);
    }

    protected function syncConfig(): void
    {
        $configPath = config_path('logman.php');
        $packageSourcePath = __DIR__ . '/../../../config/logman.php';

        if (!File::exists($configPath)) {
            $this->call('vendor:publish', ['--tag' => 'logman-config']);
            $this->components->task('Config file not found — published fresh copy', fn () => true);
            return;
        }

        $packageConfig = require $packageSourcePath;
        $userConfig = require $configPath;

        $missing = $this->findMissingKeys($packageConfig, $userConfig);

        if (empty($missing)) {
            $this->components->info('Config is up to date — no missing keys.');
            return;
        }

        $packageLines = file($packageSourcePath);
        $userContent = File::get($configPath);
        $added = 0;

        foreach ($missing as $dotKey) {
            $snippet = $this->extractSnippetWithComments($packageLines, $dotKey);
            if (!$snippet) {
                continue;
            }

            $segments = explode('.', $dotKey);
            $insertionPoint = $this->findInsertionPoint($userContent, $segments);

            if ($insertionPoint !== null) {
                $userContent = substr($userContent, 0, $insertionPoint)
                    . $snippet . "\n"
                    . substr($userContent, $insertionPoint);
                $added++;
            }
        }

        if ($added === 0) {
            $this->components->warn('Could not auto-insert missing keys. Use --force to republish the full config.');
            return;
        }

        File::put($configPath, $userContent);
        $this->components->task("Synced config — added {$added} missing " . ($added === 1 ? 'key' : 'keys'), fn () => true);
    }

    /**
     * Find missing keys (dot notation) in user config compared to package config.
     */
    protected function findMissingKeys(array $package, array $user, string $prefix = ''): array
    {
        $missing = [];

        foreach ($package as $key => $value) {
            if (is_int($key)) {
                continue;
            }

            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (!array_key_exists($key, $user)) {
                $missing[] = $fullKey;
            } elseif (is_array($value) && is_array($user[$key])) {
                $missing = array_merge($missing, $this->findMissingKeys($value, $user[$key], $fullKey));
            }
        }

        return $missing;
    }

    /**
     * Extract a key's raw snippet from the package source, including preceding comments.
     */
    protected function extractSnippetWithComments(array $lines, string $dotKey): ?string
    {
        $segments = explode('.', $dotKey);
        $targetKey = end($segments);
        $parentKeys = array_slice($segments, 0, -1);

        // Find the target key within the correct parent context
        $searchFrom = 0;
        foreach ($parentKeys as $parentKey) {
            $pattern = "/['\"]" . preg_quote($parentKey, '/') . "['\"]\s*=>/";
            for ($i = $searchFrom; $i < count($lines); $i++) {
                if (preg_match($pattern, $lines[$i])) {
                    $searchFrom = $i + 1;
                    break;
                }
            }
        }

        $keyPattern = "/['\"]" . preg_quote($targetKey, '/') . "['\"]\s*=>/";
        $keyLine = null;

        for ($i = $searchFrom; $i < count($lines); $i++) {
            if (preg_match($keyPattern, $lines[$i])) {
                $keyLine = $i;
                break;
            }
        }

        if ($keyLine === null) {
            return null;
        }

        // Collect preceding comment block
        $commentStart = $keyLine;
        for ($i = $keyLine - 1; $i >= $searchFrom; $i--) {
            $trimmed = trim($lines[$i]);
            if ($trimmed === '' || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '/*') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '|')) {
                $commentStart = $i;
            } else {
                break;
            }
        }

        // Collect the key value (may be multi-line array)
        $endLine = $keyLine;
        if (preg_match('/=>\s*\[/', $lines[$keyLine]) && !preg_match('/\[.*\]/', $lines[$keyLine])) {
            $depth = 0;
            for ($j = $keyLine; $j < count($lines); $j++) {
                $depth += substr_count($lines[$j], '[') - substr_count($lines[$j], ']');
                $endLine = $j;
                if ($depth <= 0) {
                    break;
                }
            }
        }

        // Build the snippet
        $result = '';
        for ($i = $commentStart; $i <= $endLine; $i++) {
            $result .= $lines[$i];
        }

        // Ensure snippet ends with trailing comma and newline
        $result = rtrim($result);
        if (!str_ends_with($result, ',')) {
            $result .= ',';
        }
        $result .= "\n";

        return $result;
    }

    /**
     * Find the byte offset where a missing key should be inserted in the user's config.
     * Inserts before the closing bracket of the parent section.
     */
    protected function findInsertionPoint(string $content, array $segments): ?int
    {
        $parentKeys = array_slice($segments, 0, -1);

        if (empty($parentKeys)) {
            // Top-level key: insert before the final "];" in the file
            $pos = strrpos($content, '];');
            if ($pos === false) {
                return null;
            }
            // Find the start of this line to insert before it
            $lineStart = strrpos(substr($content, 0, $pos), "\n");
            return $lineStart !== false ? $lineStart + 1 : $pos;
        }

        // Find the parent section's opening bracket
        $searchFrom = 0;
        foreach ($parentKeys as $parentKey) {
            $pattern = "/['\"]" . preg_quote($parentKey, '/') . "['\"]\s*=>\s*\[/";
            if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE, $searchFrom)) {
                $searchFrom = $matches[0][1] + strlen($matches[0][0]);
            } else {
                return null;
            }
        }

        // Find the matching closing bracket from the parent's opening
        $depth = 1;
        $len = strlen($content);
        for ($i = $searchFrom; $i < $len; $i++) {
            if ($content[$i] === '[') {
                $depth++;
            } elseif ($content[$i] === ']') {
                $depth--;
                if ($depth === 0) {
                    // Insert before this closing bracket, at the start of its line
                    $lineStart = strrpos(substr($content, 0, $i), "\n");
                    return $lineStart !== false ? $lineStart + 1 : $i;
                }
            }
        }

        return null;
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
            '# Docs: https://github.com/mahmoud-mhamed/laravel-logman',
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
