<?php

declare(strict_types=1);

namespace Yaon\FpmExporter\Tests;

use PHPUnit\Framework\TestCase;
use Yaon\FpmExporter\Fetcher;
use Yaon\FpmExporter\SchemeFetcher;

final class SchemeFetcherTest extends TestCase
{
    public function testDispatchesByScheme(): void
    {
        $fake = static fn (string $name): Fetcher => new class ($name) implements Fetcher {
            public function __construct(private string $name)
            {
            }

            public function fetch(string $url): string
            {
                return $this->name;
            }
        };
        $fetcher = new SchemeFetcher($fake('http'), $fake('fcgi'));

        self::assertSame('http', $fetcher->fetch('https://nginx/status'));
        self::assertSame('fcgi', $fetcher->fetch('tcp://app:9000/status'));
        self::assertSame('fcgi', $fetcher->fetch('unix:///run/fpm.sock;/status'));
        $this->expectException(\RuntimeException::class);
        $fetcher->fetch('ftp://x');
    }
}
