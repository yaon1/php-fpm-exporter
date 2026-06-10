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
        /** Seconds to reuse a scrape; protects FPM when several Prometheus replicas scrape at once */
        public float $cacheTtl = 0.0,
        public bool $verifyTls = true,
        /** @var list<string> hosts /probe may contact; empty allows any */
        public array $probeAllowedHosts = [],
    ) {}

    /** @param array<string, string> $env */
    public static function fromEnv(array $env): self
    {
        return new self(
            self::pools($env['FPM_STATUS_URLS'] ?? ''),
            self::pools($env['OPCACHE_STATUS_URLS'] ?? ''),
            (float) ($env['SCRAPE_TIMEOUT'] ?? 2.0),
            ($env['METRICS_BASIC_AUTH'] ?? '') === '' ? null : $env['METRICS_BASIC_AUTH'],
            (float) ($env['SCRAPE_CACHE_TTL'] ?? 0),
            !filter_var($env['SCRAPE_INSECURE_SKIP_VERIFY'] ?? false, FILTER_VALIDATE_BOOL),
            array_values(array_filter(array_map(static fn(string $h) => strtolower(trim($h)), explode(',', $env['PROBE_ALLOWED_HOSTS'] ?? '')))),
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
            if (isset($pools[$name])) {
                throw new \InvalidArgumentException("Pool label \"$name\" is used twice");
            }
            if (preg_match('~^(https?|tcp|unix)://~i', $url) !== 1) {
                throw new \InvalidArgumentException("Pool \"$name\" has an unsupported URL \"$url\"; use http(s)://, tcp:// or unix://");
            }
            $pools[$name] = $url;
        }

        return $pools;
    }
}
