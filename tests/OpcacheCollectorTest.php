<?php

declare(strict_types=1);

namespace Yaon\FpmExporter\Tests;

use PHPUnit\Framework\TestCase;
use Yaon\FpmExporter\Fetcher;
use Yaon\FpmExporter\OpcacheCollector;

final class OpcacheCollectorTest extends TestCase
{
    private function collect(string $body): string
    {
        $fetcher = new class ($body) implements Fetcher {
            public function __construct(private string $body)
            {
            }

            public function fetch(string $url): string
            {
                return $this->body;
            }
        };
        $lines = [];
        foreach ((new OpcacheCollector($fetcher, ['www' => 'http://x']))->collect() as $m) {
            $lines[] = $m->name . json_encode($m->labels) . '=' . $m->value;
        }

        return implode("\n", $lines);
    }

    public function testMapsStatus(): void
    {
        $out = $this->collect((string) json_encode([
            'opcache_enabled' => true,
            'cache_full' => false,
            'memory_usage' => ['used_memory' => 100, 'free_memory' => 50, 'wasted_memory' => 5],
            'opcache_statistics' => ['num_cached_scripts' => 12, 'hits' => 900, 'misses' => 100, 'oom_restarts' => 1],
            'interned_strings_usage' => ['buffer_size' => 8, 'used_memory' => 4],
        ]));

        self::assertStringContainsString('opcache_enabled{"pool":"www"}=1', $out);
        self::assertStringContainsString('opcache_memory_bytes{"pool":"www","kind":"wasted"}=5', $out);
        self::assertStringContainsString('opcache_hits_total{"pool":"www"}=900', $out);
        self::assertStringContainsString('opcache_restarts_total{"pool":"www","reason":"oom"}=1', $out);
    }

    public function testDisabledOpcacheReportsZero(): void
    {
        self::assertSame('opcache_enabled{"pool":"www"}=0', $this->collect('false'));
    }
}
