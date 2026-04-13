<?php

namespace MahmoudMhamed\Logman\Facades;

use Illuminate\Support\Facades\Facade;
use MahmoudMhamed\Logman\LogmanService;

/**
 * @method static void logException(\Throwable $throwable)
 * @method static void sendInfo(string $message)
 * @method static void slackLogInfo(string $message)
 * @method static void registerDriver(string $name, string $class)
 *
 * @see \MahmoudMhamed\Logman\LogmanService
 */
class Logman extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LogmanService::class;
    }
}
