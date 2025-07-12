<?php

declare(strict_types=1);

namespace Yaon\FpmExporter;

final readonly class Config
{
    /**
     * @param array<string, string> $fpmPools
     * @param array<string, string> $opcachePools
     */
    public function __construct(
        public array $fpmPools,
        public array $opcachePools,
        public float $timeout,
        /** "user:password" required for /metrics, null disables auth */
        public ?string $basicAuth = null,
    ) {
    }

    /** @param array<string, string> $env */
    public static function fromEnv(array $env): self
    {
        return new self(
            self::pools($env['FPM_STATUS_URLS'] ?? ''),
            self::pools($env['OPCACHE_STATUS_URLS'] ?? ''),
            (float) ($env['SCRAPE_TIMEOUT'] ?? 2.0),
            ($env['METRICS_BASIC_AUTH'] ?? '') === '' ? null : $env['METRICS_BASIC_AUTH'],
        );
    }

    /**
     * Parses "www=http://a/status,api=http://b/status"; a bare URL gets the label "default".
     *
     * @return array<string, string>
     */
    public static function pools(string $spec): array
    {
        $pools = [];
        foreach (array_filter(array_map('trim', explode(',', $spec))) as $entry) {
            [$name, $url] = preg_match('~^([\w.-]+)=(.+)$~', $entry, $m) === 1 ? [$m[1], $m[2]] : ['default', $entry];
            $pools[$name] = $url;
        }

        return $pools;
    }
}
