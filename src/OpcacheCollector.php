<?php

declare(strict_types=1);

namespace Yaon\FpmExporter;

/** Scrapes the JSON emitted by agent/opcache-status.php. */
final readonly class OpcacheCollector
{
    /** @param array<string, string> $pools pool label => agent URL */
    public function __construct(private Fetcher $fetcher, private array $pools) {}

    /** @return \Generator<Metric> */
    public function collect(): \Generator
    {
        foreach ($this->pools as $name => $url) {
            $labels = ['pool' => $name];
            try {
                $raw = json_decode($this->fetcher->fetch($url), true, 64, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                $raw = false;
            }

            if (!is_array($raw)) {
                yield new Metric('opcache_enabled', 'gauge', 'Whether OPcache is enabled', 0, $labels);
                continue;
            }

            $memory = Json::nested($raw, 'memory_usage');
            $stats = Json::nested($raw, 'opcache_statistics');
            $strings = Json::nested($raw, 'interned_strings_usage');

            yield new Metric('opcache_enabled', 'gauge', 'Whether OPcache is enabled', Json::number($raw, 'opcache_enabled'), $labels);
            yield new Metric('opcache_cache_full', 'gauge', 'Whether the OPcache memory is exhausted', Json::number($raw, 'cache_full'), $labels);
            foreach (['used_memory' => 'used', 'free_memory' => 'free', 'wasted_memory' => 'wasted'] as $key => $kind) {
                yield new Metric('opcache_memory_bytes', 'gauge', 'OPcache shared memory', Json::number($memory, $key), $labels + ['kind' => $kind]);
            }
            yield new Metric('opcache_cached_scripts', 'gauge', 'Scripts in the cache', Json::number($stats, 'num_cached_scripts'), $labels);
            yield new Metric('opcache_hits_total', 'counter', 'Cache hits', Json::number($stats, 'hits'), $labels);
            $lookups = Json::number($stats, 'hits') + Json::number($stats, 'misses');
            yield new Metric('opcache_hit_ratio', 'gauge', 'Lifetime cache hit ratio (0-1)', $lookups === 0 ? 0 : Json::number($stats, 'hits') / $lookups, $labels);
            yield new Metric('opcache_misses_total', 'counter', 'Cache misses', Json::number($stats, 'misses'), $labels);
            foreach (['oom_restarts' => 'oom', 'hash_restarts' => 'hash', 'manual_restarts' => 'manual'] as $key => $reason) {
                yield new Metric('opcache_restarts_total', 'counter', 'Cache restarts', Json::number($stats, $key), $labels + ['reason' => $reason]);
            }
            yield new Metric('opcache_cached_keys', 'gauge', 'Keys in the OPcache hash table', Json::number($stats, 'num_cached_keys'), $labels);
            yield new Metric('opcache_max_cached_keys', 'gauge', 'Capacity of the OPcache hash table (opcache.max_accelerated_files)', Json::number($stats, 'max_cached_keys'), $labels);
            yield new Metric('opcache_blacklist_misses_total', 'counter', 'Requests for blacklisted scripts', Json::number($stats, 'blacklist_misses'), $labels);
            yield new Metric('opcache_start_time_seconds', 'gauge', 'Unix time when OPcache started', Json::number($stats, 'start_time'), $labels);
            yield new Metric('opcache_last_restart_time_seconds', 'gauge', 'Unix time of the last OPcache restart, 0 if never', Json::number($stats, 'last_restart_time'), $labels);
            yield new Metric('opcache_interned_strings_used_bytes', 'gauge', 'Interned strings buffer used', Json::number($strings, 'used_memory'), $labels);
            yield new Metric('opcache_interned_strings_buffer_bytes', 'gauge', 'Interned strings buffer size', Json::number($strings, 'buffer_size'), $labels);

            $preload = Json::nested($raw, 'preload_statistics');
            if ($preload !== []) {
                yield new Metric('opcache_preload_memory_bytes', 'gauge', 'Memory used by preloaded scripts', Json::number($preload, 'memory_consumption'), $labels);
                yield new Metric('opcache_preload_scripts', 'gauge', 'Number of preloaded scripts', is_array($preload['scripts'] ?? null) ? count($preload['scripts']) : 0, $labels);
            }

            $jit = Json::nested($raw, 'jit');
            if ($jit !== []) {
                yield new Metric('opcache_jit_enabled', 'gauge', 'Whether the JIT is active', Json::number($jit, 'on'), $labels);
                yield new Metric('opcache_jit_buffer_bytes', 'gauge', 'JIT buffer size', Json::number($jit, 'buffer_size'), $labels);
                yield new Metric('opcache_jit_buffer_free_bytes', 'gauge', 'Free space in the JIT buffer', Json::number($jit, 'buffer_free'), $labels);
            }
        }
    }
}
