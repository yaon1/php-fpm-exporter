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
            public function __construct(private string $body) {}

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

    public function testMapsKeyUsage(): void
    {
        $out = $this->collect((string) json_encode(['opcache_enabled' => true, 'opcache_statistics' => ['num_cached_keys' => 300, 'max_cached_keys' => 16229]]));

        self::assertStringContainsString('opcache_cached_keys{"pool":"www"}=300', $out);
        self::assertStringContainsString('opcache_max_cached_keys{"pool":"www"}=16229', $out);
    }

    public function testMapsBlacklistMisses(): void
    {
        $out = $this->collect((string) json_encode(['opcache_enabled' => true, 'opcache_statistics' => ['blacklist_misses' => 3]]));

        self::assertStringContainsString('opcache_blacklist_misses_total{"pool":"www"}=3', $out);
    }

    public function testMapsTimes(): void
    {
        $out = $this->collect((string) json_encode(['opcache_enabled' => true, 'opcache_statistics' => ['start_time' => 1700000000, 'last_restart_time' => 0]]));

        self::assertStringContainsString('opcache_start_time_seconds{"pool":"www"}=1700000000', $out);
        self::assertStringContainsString('opcache_last_restart_time_seconds{"pool":"www"}=0', $out);
    }

    public function testMapsJitWhenPresent(): void
    {
        $out = $this->collect((string) json_encode(['opcache_enabled' => true, 'jit' => ['on' => true, 'buffer_size' => 67108864, 'buffer_free' => 60000000]]));

        self::assertStringContainsString('opcache_jit_enabled{"pool":"www"}=1', $out);
        self::assertStringContainsString('opcache_jit_buffer_free_bytes{"pool":"www"}=60000000', $out);
        self::assertStringNotContainsString('jit', $this->collect((string) json_encode(['opcache_enabled' => true])));
    }

    public function testMapsPreload(): void
    {
        $out = $this->collect((string) json_encode(['opcache_enabled' => true, 'preload_statistics' => ['memory_consumption' => 2048, 'scripts' => ['/app/a.php', '/app/b.php']]]));

        self::assertStringContainsString('opcache_preload_memory_bytes{"pool":"www"}=2048', $out);
        self::assertStringContainsString('opcache_preload_scripts{"pool":"www"}=2', $out);
    }

    public function testComputesHitRatio(): void
    {
        $out = $this->collect((string) json_encode(['opcache_enabled' => true, 'opcache_statistics' => ['hits' => 900, 'misses' => 100]]));

        self::assertStringContainsString('opcache_hit_ratio{"pool":"www"}=0.9', $out);
    }
}
