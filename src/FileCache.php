<?php

declare(strict_types=1);

namespace Yaon\FpmExporter;

/**
 * Tiny shared cache for scrape results. The built-in server runs several worker
 * processes, so state has to live on disk; flock() keeps concurrent scrapes from
 * hitting FPM at the same time.
 */
final readonly class FileCache
{
    /** @param \Closure(): float|null $clock */
    public function __construct(private string $directory, private ?\Closure $clock = null)
    {
    }

    /**
     * @param \Closure(): string $compute
     * @phpstan-impure
     */
    public function remember(string $key, float $ttl, \Closure $compute): string
    {
        if ($ttl <= 0) {
            return $compute();
        }
        $path = $this->directory . '/php-fpm-exporter-' . hash('xxh128', $key) . '.cache';
        $handle = fopen($path, 'c+');
        if ($handle === false) {
            return $compute();
        }

        try {
            flock($handle, LOCK_EX);
            $raw = stream_get_contents($handle);
            $now = $this->clock !== null ? ($this->clock)() : microtime(true);
            if (is_string($raw) && preg_match('/^(\d+(?:\.\d+)?)\n/', $raw, $m) === 1 && $now - (float) $m[1] < $ttl) {
                return substr($raw, strlen($m[0]));
            }

            $value = $compute();
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, sprintf("%.6F\n", $now) . $value);

            return $value;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
