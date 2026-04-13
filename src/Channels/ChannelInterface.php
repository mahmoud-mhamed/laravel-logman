<?php

namespace Mhamed\Logman\Channels;

interface ChannelInterface
{
    /**
     * Send an exception notification.
     */
    public function sendException(array $payload): void;

    /**
     * Send an info notification.
     */
    public function sendInfo(string $message, array $context): void;
}
