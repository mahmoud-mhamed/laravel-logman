<?php

namespace Mhamed\Logman\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Mhamed\Logman\Channels\ChannelInterface;
use Throwable;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public function __construct(
        protected string $driverClass,
        protected string $type,
        protected array $data,
        int $retries = 0,
    ) {
        $this->tries = max(1, $retries + 1);
    }

    public function handle(): void
    {
        $driver = new $this->driverClass();

        if (!$driver instanceof ChannelInterface) {
            return;
        }

        if ($this->type === 'exception') {
            $driver->sendException($this->data);
        } elseif ($this->type === 'info') {
            $driver->sendInfo($this->data['message'], $this->data['context']);
        }
    }

    public function failed(?Throwable $exception): void
    {
        // Silently fail — don't let notification failures cascade
    }
}
