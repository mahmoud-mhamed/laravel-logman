<?php

namespace MahmoudMhamed\Logman\Tests\Unit;

use Illuminate\Http\Request;
use MahmoudMhamed\Logman\Http\Middleware\LogRequests;
use Symfony\Component\HttpFoundation\Response;
use MahmoudMhamed\Logman\Tests\TestCase;

class LogRequestsTest extends TestCase
{
    protected function middleware(): LogRequests
    {
        return new LogRequests();
    }

    protected function invoke(Request $request, Response $response): void
    {
        $mw = $this->middleware();
        // handle() is a pass-through; the write happens in terminate().
        $mw->handle($request, fn ($r) => $response);
        $mw->terminate($request, $response);
    }

    public function test_skips_when_disabled(): void
    {
        config(['logman.request_logging.enabled' => false]);

        $captured = [];
        $this->fakeChannel($captured);

        $this->invoke(Request::create('/users', 'GET'), new Response('ok'));

        $this->assertEmpty($captured, 'No request should be logged when disabled');
    }

    public function test_logs_request_when_enabled(): void
    {
        config([
            'logman.request_logging.enabled' => true,
            'logman.request_logging.channel' => 'logman_requests',
        ]);

        $captured = [];
        $this->fakeChannel($captured);

        $this->invoke(Request::create('/users?page=2', 'GET'), new Response('ok', 200));

        $this->assertCount(1, $captured);
        $this->assertStringContainsString('GET /users', $captured[0]['message']);
        $this->assertStringContainsString('200', $captured[0]['message']);
        $this->assertSame('GET', $captured[0]['context']['method']);
        $this->assertSame(['page' => '2'], $captured[0]['context']['query']);
    }

    public function test_excludes_logman_routes(): void
    {
        config(['logman.request_logging.enabled' => true]);

        $captured = [];
        $this->fakeChannel($captured);

        $this->invoke(Request::create('/logman/analysis', 'GET'), new Response('ok'));

        $this->assertEmpty($captured, 'Logman viewer routes must not be logged');
    }

    public function test_respects_except_patterns(): void
    {
        config([
            'logman.request_logging.enabled' => true,
            'logman.request_logging.except' => ['*.css'],
        ]);

        $captured = [];
        $this->fakeChannel($captured);

        $this->invoke(Request::create('/app.css', 'GET'), new Response('ok'));

        $this->assertEmpty($captured);
    }

    public function test_masks_sensitive_body_fields(): void
    {
        config(['logman.request_logging.enabled' => true]);

        $captured = [];
        $this->fakeChannel($captured);

        $request = Request::create('/login', 'POST', [
            'email' => 'a@b.com',
            'password' => 'super-secret',
        ]);

        $this->invoke($request, new Response('ok'));

        $this->assertCount(1, $captured);
        $this->assertSame('********', $captured[0]['context']['body']['password']);
        $this->assertSame('a@b.com', $captured[0]['context']['body']['email']);
    }

    public function test_logs_response_body_when_enabled(): void
    {
        config([
            'logman.request_logging.enabled' => true,
            'logman.request_logging.log_response_body' => true,
        ]);

        $captured = [];
        $this->fakeChannel($captured);

        $response = new \Illuminate\Http\JsonResponse(['ok' => true, 'token' => 'abc123']);

        $this->invoke(Request::create('/api/ping', 'GET'), $response);

        $this->assertCount(1, $captured);
        $this->assertSame(true, $captured[0]['context']['response_body']['ok']);
        // Sensitive keys are masked in the response body too.
        $this->assertSame('********', $captured[0]['context']['response_body']['token']);
    }

    public function test_response_body_omitted_by_default(): void
    {
        config(['logman.request_logging.enabled' => true]);

        $captured = [];
        $this->fakeChannel($captured);

        $this->invoke(Request::create('/api/ping', 'GET'), new Response('hello'));

        $this->assertCount(1, $captured);
        $this->assertArrayNotHasKey('response_body', $captured[0]['context']);
    }

    /**
     * Swap the logging channel for a spy that records level/message/context.
     */
    protected function fakeChannel(array &$captured): void
    {
        \Illuminate\Support\Facades\Log::shouldReceive('channel')
            ->andReturnUsing(function () use (&$captured) {
                return new class($captured) {
                    public function __construct(public array &$captured) {}

                    public function log($level, $message, $context = []): void
                    {
                        $this->captured[] = compact('level', 'message', 'context');
                    }
                };
            });
    }
}
