<?php

declare(strict_types=1);

namespace Yaon\FpmExporter\Tests;

use PHPUnit\Framework\TestCase;
use Yaon\FpmExporter\Config;

final class ConfigTest extends TestCase
{
    public function testParsesLabelledAndBarePools(): void
    {
        self::assertSame(
            ['www' => 'http://a/status', 'default' => 'http://b/status?x=1'],
            Config::pools(' www=http://a/status , http://b/status?x=1,'),
        );
    }

    public function testFromEnvDefaults(): void
    {
        $config = Config::fromEnv([]);

        self::assertSame([], $config->fpmPools);
        self::assertSame(2.0, $config->timeout);
    }

    public function testReadsAuthAndCacheSettings(): void
    {
        $config = Config::fromEnv(['METRICS_BASIC_AUTH' => 'u:p', 'SCRAPE_CACHE_TTL' => '2.5', 'SCRAPE_TIMEOUT' => '1']);

        self::assertSame(['u:p', 2.5, 1.0], [$config->basicAuth, $config->cacheTtl, $config->timeout]);
        self::assertNull(Config::fromEnv(['METRICS_BASIC_AUTH' => ''])->basicAuth);
    }

    public function testRejectsDuplicateLabelsAndBadSchemes(): void
    {
        try {
            Config::pools('a=http://x/status,a=http://y/status');
            self::fail('duplicate accepted');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('used twice', $e->getMessage());
        }

        $this->expectExceptionMessageMatches('/unsupported URL/');
        Config::pools('www=fpm:9000/status');
    }
}
