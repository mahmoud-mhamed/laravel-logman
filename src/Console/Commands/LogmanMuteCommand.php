<?php

namespace MahmoudMhamed\Logman\Console\Commands;

use Illuminate\Console\Command;
use MahmoudMhamed\Logman\Services\MuteService;

class LogmanMuteCommand extends Command
{
    protected $signature = 'logman:mute
        {exception : The exception class name or pattern to mute}
        {--duration=1d : Duration (1h, 6h, 12h, 1d, 3d, 1w, 1m)}
        {--pattern= : Optional message pattern to match}
        {--reason= : Optional reason for muting}';

    protected $description = 'Mute a specific exception from being reported';

    public function handle(MuteService $muteService): int
    {
        $exception = $this->argument('exception');
        $duration = $this->option('duration');
        $pattern = $this->option('pattern');
        $reason = $this->option('reason');

        $entry = $muteService->mute($exception, $pattern, $duration, $reason);

        $this->info("Muted: {$exception}");
        $this->line("  Duration: {$duration}");
        $this->line("  Expires:  {$entry['muted_until']}");
        if ($pattern) {
            $this->line("  Pattern:  {$pattern}");
        }
        if ($reason) {
            $this->line("  Reason:   {$reason}");
        }

        return self::SUCCESS;
    }
}
