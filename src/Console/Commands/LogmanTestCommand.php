<?php

namespace MahmoudMhamed\Logman\Console\Commands;

use Illuminate\Console\Command;
use MahmoudMhamed\Logman\LogmanService;

class LogmanTestCommand extends Command
{
    protected $signature = 'logman:test';
    protected $description = 'Send a test notification to verify your Logman configuration';

    public function handle(LogmanService $service): int
    {
        $this->info('Sending test notification...');

        try {
            $service->sendInfo('Logman test notification — your configuration is working!');
            $this->info('Test notification sent successfully.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to send: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
