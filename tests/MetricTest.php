<?php

declare(strict_types=1);

namespace Yaon\FpmExporter\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yaon\FpmExporter\Metric;

final class MetricTest extends TestCase
{
    /** @return iterable<array{string, string, array<string, string>}> */
    public static function invalid(): iterable
    {
        yield 'name with dash' => ['php-fpm', 'gauge', []];
        yield 'name starting with digit' => ['1up', 'gauge', []];
        yield 'unknown type' => ['up', 'histogram', []];
        yield 'reserved label' => ['up', 'gauge', ['__name__' => 'x']];
        yield 'label with dot' => ['up', 'gauge', ['pool.name' => 'x']];
    }

    /** @param array<string, string> $labels */
    #[DataProvider('invalid')]
    public function testRejectsInvalidDefinitions(string $name, string $type, array $labels): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Metric($name, $type, 'help', 1, $labels);
    }

    public function testAcceptsValidMetric(): void
    {
        self::assertSame('phpfpm:up', (new Metric('phpfpm:up', 'gauge', 'h', 1, ['pool' => 'www']))->name);
    }
}
