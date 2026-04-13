<?php

namespace MahmoudMhamed\Logman\Console\Commands;

use Illuminate\Console\Command;
use MahmoudMhamed\Logman\Services\MuteService;

class LogmanListMutesCommand extends Command
{
    protected $signature = 'logman:list-mutes';
    protected $description = 'List all active mutes';

    public function handle(MuteService $muteService): int
    {
        $mutes = $muteService->getMutes();

        if (empty($mutes)) {
            $this->info('No active mutes.');
            return self::SUCCESS;
        }

        $rows = array_map(fn($m) => [
            $m['id'],
            $m['exception_class'],
            $m['message_pattern'] ?: '-',
            $m['hit_count'] ?? 0,
            \Carbon\Carbon::parse($m['muted_until'])->diffForHumans(),
            $m['reason'] ?: '-',
        ], $mutes);

        $this->table(
            ['ID', 'Exception', 'Pattern', 'Hits', 'Expires', 'Reason'],
            $rows
        );

        return self::SUCCESS;
    }
}
