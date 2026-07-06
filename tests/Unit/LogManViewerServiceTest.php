<?php

namespace MahmoudMhamed\Logman\Tests\Unit;

use MahmoudMhamed\Logman\LogMan\LogManService;
use MahmoudMhamed\Logman\Tests\TestCase;

class LogManViewerServiceTest extends TestCase
{
    /**
     * Call the protected finalizeEntry method via reflection.
     */
    protected function callFinalizeEntry(array $entry): array
    {
        $service = app(LogManService::class);
        $ref = new \ReflectionMethod($service, 'finalizeEntry');
        $ref->invokeArgs($service, [&$entry]);

        return $entry;
    }

    public function test_extracts_class_from_stack_trace_start_of_line(): void
    {
        $entry = [
            'date' => '2026-04-14 10:00:00',
            'level' => 'error',
            'message' => 'SQLSTATE[42S02]: Base table not found',
            'stack' => "Illuminate\\Database\\QueryException: SQLSTATE[42S02]\n#0 /app/foo.php(10): bar()",
        ];

        $entry = $this->callFinalizeEntry($entry);

        $this->assertEquals('Illuminate\\Database\\QueryException', $entry['exception_class']);
    }

    public function test_extracts_class_from_laravel_serialized_format(): void
    {
        $entry = [
            'date' => '2026-04-14 11:41:52',
            'level' => 'error',
            'message' => 'Division by zero {"userId":37,"exception":"[object] (DivisionByZeroError(code: 0): Division by zero at /home/forge/app/Actions/HomeAction.php:323)',
            'stack' => "[stacktrace]\n#0 /home/forge/app/vendor/laravel/framework/src/Illuminate/Collections/Traits/EnumeratesValues.php(275): App\\Actions\\HomeAction->{closure}()",
        ];

        $entry = $this->callFinalizeEntry($entry);

        $this->assertEquals('DivisionByZeroError', $entry['exception_class']);
    }

    public function test_extracts_namespaced_class_from_serialized_format(): void
    {
        $entry = [
            'date' => '2026-04-14 10:00:00',
            'level' => 'error',
            'message' => 'Something failed {"exception":"[object] (App\\Exceptions\\CustomException(code: 0): Something failed at /app/foo.php:10)"}',
            'stack' => "[stacktrace]\n#0 /app/bar.php(5): baz()",
        ];

        $entry = $this->callFinalizeEntry($entry);

        $this->assertEquals('App\\Exceptions\\CustomException', $entry['exception_class']);
    }

    public function test_fallback_extracts_from_message_start(): void
    {
        $entry = [
            'date' => '2026-04-14 10:00:00',
            'level' => 'error',
            'message' => 'RuntimeException: something went wrong',
            'stack' => '',
        ];

        $entry = $this->callFinalizeEntry($entry);

        $this->assertEquals('RuntimeException', $entry['exception_class']);
    }

    public function test_exception_class_empty_when_no_match(): void
    {
        $entry = [
            'date' => '2026-04-14 10:00:00',
            'level' => 'warning',
            'message' => 'some generic warning message',
            'stack' => '',
        ];

        $entry = $this->callFinalizeEntry($entry);

        $this->assertEquals('', $entry['exception_class']);
    }

    public function test_extracts_json_blocks_from_message(): void
    {
        $entry = [
            'date' => '2026-04-14 10:00:00',
            'level' => 'error',
            'message' => 'Payment API error {"id":42,"status":"failed","meta":{"retries":3}}',
            'stack' => '',
        ];

        $entry = $this->callFinalizeEntry($entry);

        $this->assertNotEmpty($entry['json_blocks']);
        $decoded = json_decode($entry['json_blocks'][0], true);
        $this->assertSame(42, $decoded['id']);
        $this->assertSame('failed', $decoded['status']);
        $this->assertStringContainsString("\n", $entry['json_blocks'][0], 'JSON block should be pretty printed');
    }

    public function test_no_json_blocks_when_message_has_no_json(): void
    {
        $entry = [
            'date' => '2026-04-14 10:00:00',
            'level' => 'info',
            'message' => 'User logged in successfully',
            'stack' => '',
        ];

        $entry = $this->callFinalizeEntry($entry);

        $this->assertSame([], $entry['json_blocks']);
    }

    public function test_unique_entries_collapses_duplicates_with_count(): void
    {
        $service = app(LogManService::class);
        $ref = new \ReflectionMethod($service, 'uniqueEntries');
        $ref->setAccessible(true);

        $entries = [
            ['date' => '2026-04-14 10:00:00', 'level' => 'error', 'message' => 'Boom'],
            ['date' => '2026-04-14 10:05:00', 'level' => 'error', 'message' => 'Boom'],
            ['date' => '2026-04-14 10:06:00', 'level' => 'error', 'message' => 'Different'],
            ['date' => '2026-04-14 10:10:00', 'level' => 'error', 'message' => 'Boom'],
        ];

        $result = $ref->invoke($service, $entries);

        $this->assertCount(2, $result);

        // The "Boom" entry keeps the most recent occurrence and counts 3.
        $boom = collect($result)->firstWhere('message', 'Boom');
        $this->assertSame(3, $boom['occurrence_count']);
        $this->assertSame('2026-04-14 10:10:00', $boom['date']);
        $this->assertSame('2026-04-14 10:00:00', $boom['first_seen']);

        $different = collect($result)->firstWhere('message', 'Different');
        $this->assertSame(1, $different['occurrence_count']);
    }
}
