<?php

namespace Mhamed\Logman\Tests\Unit;

use Mhamed\Logman\Services\MuteService;
use Mhamed\Logman\Tests\TestCase;

class MuteServiceTest extends TestCase
{
    protected MuteService $muteService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->muteService = app(MuteService::class);
    }

    public function test_mute_creates_entry(): void
    {
        $entry = $this->muteService->mute('App\\Exceptions\\TestException', null, '1h', 'Test reason');

        $this->assertNotEmpty($entry['id']);
        $this->assertEquals('App\\Exceptions\\TestException', $entry['exception_class']);
        $this->assertEquals('Test reason', $entry['reason']);
        $this->assertEquals(0, $entry['hit_count']);
    }

    public function test_unmute_removes_entry(): void
    {
        $entry = $this->muteService->mute('App\\Exceptions\\TestException', null, '1h');
        $this->assertTrue($this->muteService->unmute($entry['id']));
        $this->assertEmpty($this->muteService->getMutes());
    }

    public function test_unmute_returns_false_for_unknown_id(): void
    {
        $this->assertFalse($this->muteService->unmute('nonexistent-id'));
    }

    public function test_is_muted_matches_exact_class(): void
    {
        $this->muteService->mute('RuntimeException', null, '1h');
        $this->assertTrue($this->muteService->isMuted(new \RuntimeException('test')));
    }

    public function test_is_muted_with_message_pattern(): void
    {
        $this->muteService->mute('RuntimeException', 'connection refused', '1h');
        $this->assertTrue($this->muteService->isMuted(new \RuntimeException('connection refused to host')));
        $this->assertFalse($this->muteService->isMuted(new \RuntimeException('timeout error')));
    }

    public function test_throttle_allows_within_limit(): void
    {
        $this->muteService->addThrottle('RuntimeException', null, 3, '1h');

        // First 3 should not be throttled
        $this->assertFalse($this->muteService->isThrottled(new \RuntimeException('test')));
        $this->assertFalse($this->muteService->isThrottled(new \RuntimeException('test')));
        $this->assertFalse($this->muteService->isThrottled(new \RuntimeException('test')));

        // 4th should be throttled
        $this->assertTrue($this->muteService->isThrottled(new \RuntimeException('test')));
    }

    public function test_rate_limit_blocks_within_cooldown(): void
    {
        config(['logman.rate_limit.enabled' => true, 'logman.rate_limit.cooldown_seconds' => 60]);

        $exception = new \RuntimeException('test');

        $this->assertFalse($this->muteService->isRateLimited($exception));
        $this->assertTrue($this->muteService->isRateLimited($exception));
    }

    public function test_get_mutes_returns_empty_initially(): void
    {
        $this->assertEmpty($this->muteService->getMutes());
    }

    public function test_get_throttles_returns_empty_initially(): void
    {
        $this->assertEmpty($this->muteService->getThrottles());
    }
}
