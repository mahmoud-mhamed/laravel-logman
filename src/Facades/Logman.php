<?php

namespace Mhamed\Logman\Facades;

use Illuminate\Support\Facades\Facade;
use Mhamed\Logman\LogmanService;

/**
 * @method static void logException(\Throwable $throwable)
 * @method static void slackLogInfo(string $message)
 *
 * @see \Mhamed\Logman\LogmanService
 */
class Logman extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LogmanService::class;
    }
}
