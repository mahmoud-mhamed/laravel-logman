<?php

namespace Mhamed\Logman\Tests;

use Mhamed\Logman\LogmanServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [LogmanServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Logman' => \Mhamed\Logman\Facades\Logman::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['env'] = 'local';
        $app['config']->set('logman.enable_local', true);
        $app['config']->set('logman.storage_path', sys_get_temp_dir() . '/logman-test-' . uniqid());
        $app['config']->set('logman.channels.slack.enabled', false);
    }
}
