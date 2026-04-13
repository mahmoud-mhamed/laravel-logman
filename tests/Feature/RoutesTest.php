<?php

namespace MahmoudMhamed\Logman\Tests\Feature;

use MahmoudMhamed\Logman\Tests\TestCase;

class RoutesTest extends TestCase
{
    public function test_index_page_loads(): void
    {
        $response = $this->get(route('logman.index'));
        $response->assertStatus(200);
    }

    public function test_dashboard_page_loads(): void
    {
        $response = $this->get(route('logman.dashboard'));
        $response->assertStatus(200);
    }

    public function test_mutes_page_loads(): void
    {
        $response = $this->get(route('logman.mutes'));
        $response->assertStatus(200);
    }

    public function test_throttles_page_loads(): void
    {
        $response = $this->get(route('logman.throttles'));
        $response->assertStatus(200);
    }

    public function test_grouped_page_loads(): void
    {
        $response = $this->get(route('logman.grouped'));
        $response->assertStatus(200);
    }

    public function test_bookmarks_page_loads(): void
    {
        $response = $this->get(route('logman.bookmarks'));
        $response->assertStatus(200);
    }

    public function test_config_page_loads(): void
    {
        $response = $this->get(route('logman.config'));
        $response->assertStatus(200);
    }

    public function test_about_page_loads(): void
    {
        $response = $this->get(route('logman.about'));
        $response->assertStatus(200);
    }
}
