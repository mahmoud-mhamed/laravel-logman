<?php

namespace Mhamed\Logman\Tests\Unit;

use Mhamed\Logman\Channels\ChannelInterface;
use Mhamed\Logman\LogmanService;
use Mhamed\Logman\Tests\TestCase;

class LogmanServiceTest extends TestCase
{
    public function test_log_exception_does_not_throw(): void
    {
        $service = app(LogmanService::class);
        $service->logException(new \RuntimeException('Test exception'));

        $this->assertTrue(true); // No exception thrown
    }

    public function test_send_info_does_not_throw(): void
    {
        $service = app(LogmanService::class);
        $service->sendInfo('Test info message');

        $this->assertTrue(true);
    }

    public function test_register_custom_driver(): void
    {
        LogmanService::registerDriver('custom', FakeChannel::class);

        config(['logman.channels.custom' => [
            'enabled' => true,
            'auto_report_exceptions' => true,
            'min_level' => 'debug',
            'queue' => false,
            'retries' => 0,
            'throttle' => 0,
            'driver' => FakeChannel::class,
        ]]);

        $service = app(LogmanService::class);
        $service->logException(new \RuntimeException('Test'));

        // afterResponse() closures don't fire during tests — flush them manually
        app()->terminate();

        $this->assertTrue(FakeChannel::$sent);

        FakeChannel::$sent = false;
    }

    public function test_ignored_exception_is_not_reported(): void
    {
        config(['logman.ignore' => [\InvalidArgumentException::class]]);

        FakeChannel::$sent = false;
        config(['logman.channels.fake' => [
            'enabled' => true,
            'auto_report_exceptions' => true,
            'min_level' => 'debug',
            'queue' => false,
            'retries' => 0,
            'throttle' => 0,
            'driver' => FakeChannel::class,
        ]]);

        $service = app(LogmanService::class);
        $service->logException(new \InvalidArgumentException('Should be ignored'));

        $this->assertFalse(FakeChannel::$sent);
    }
}

class FakeChannel implements ChannelInterface
{
    public static bool $sent = false;

    public function sendException(array $payload): void
    {
        static::$sent = true;
    }

    public function sendInfo(string $message, array $context): void
    {
        static::$sent = true;
    }
}
