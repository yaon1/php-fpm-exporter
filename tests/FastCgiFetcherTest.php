<?php

declare(strict_types=1);

namespace Yaon\FpmExporter\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yaon\FpmExporter\FastCgiFetcher;

final class FastCgiFetcherTest extends TestCase
{
    /** @return iterable<array{string, array{string, string, string}}> */
    public static function urls(): iterable
    {
        yield ['tcp://app:9000/status?json&full', ['tcp://app:9000', '/status', 'json&full']];
        yield ['unix:///run/php/fpm.sock;/status', ['unix:///run/php/fpm.sock', '/status', '']];
        yield ['unix:///s.sock;/var/www/opcache-status.php?x=1', ['unix:///s.sock', '/var/www/opcache-status.php', 'x=1']];
    }

    /** @param array{string, string, string} $expected */
    #[DataProvider('urls')]
    public function testParsesUrls(string $url, array $expected): void
    {
        self::assertSame($expected, FastCgiFetcher::parse($url));
    }

    public function testRejectsHttpUrls(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        FastCgiFetcher::parse('http://nginx/status');
    }
}
