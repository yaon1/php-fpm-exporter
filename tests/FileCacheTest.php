<?php

declare(strict_types=1);

namespace Yaon\FpmExporter\Tests;

use PHPUnit\Framework\TestCase;
use Yaon\FpmExporter\FileCache;

final class FileCacheTest extends TestCase
{
    public function testReusesValueWithinTtl(): void
    {
        $now = 1000.0;
        $cache = new FileCache(sys_get_temp_dir(), function () use (&$now): float {
            return $now;
        });
        $key = uniqid('k', true);
        $calls = 0;
        $compute = function () use (&$calls): string {
            return 'v' . ++$calls;
        };

        self::assertSame('v1', $cache->remember($key, 5, $compute));
        $now += 4;
        self::assertSame('v1', $cache->remember($key, 5, $compute));
        $now += 2;
        self::assertSame('v2', $cache->remember($key, 5, $compute));
    }

    public function testZeroTtlDisablesCaching(): void
    {
        $cache = new FileCache(sys_get_temp_dir());
        $calls = 0;

        $cache->remember('k', 0, function () use (&$calls): string {
            return (string) ++$calls;
        });
        $cache->remember('k', 0, function () use (&$calls): string {
            return (string) ++$calls;
        });

        self::assertSame(2, $calls);
    }
}
