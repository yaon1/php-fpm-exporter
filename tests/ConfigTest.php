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
}
