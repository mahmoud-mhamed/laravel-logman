<?php

namespace Mhamed\Logman\Console\Commands;

use Illuminate\Console\Command;
use Mhamed\Logman\Services\MuteService;

class LogmanClearMutesCommand extends Command
{
    protected $signature = 'logman:clear-mutes';
    protected $description = 'Remove all active mutes';

    public function handle(MuteService $muteService): int
    {
        $mutes = $muteService->getMutes();

        if (empty($mutes)) {
            $this->info('No active mutes to clear.');
            return self::SUCCESS;
        }

        $count = count($mutes);

        if (!$this->confirm("Remove all {$count} active mute(s)?")) {
            $this->line('Cancelled.');
            return self::SUCCESS;
        }

        foreach ($mutes as $mute) {
            $muteService->unmute($mute['id']);
        }

        $this->info("Cleared {$count} mute(s).");
        return self::SUCCESS;
    }
}
