<?php

declare(strict_types=1);

namespace Yaon\FpmExporter;

/** HTTP front of the exporter, independent of the SAPI so it can be tested. */
final readonly class Exporter
{
    /** @param (\Closure(string): void)|null $log */
    public function __construct(
        private Config $config,
        private Fetcher $fetcher,
        private ?\Closure $log = null,
        private ?FileCache $cache = null,
    ) {}

    /**
     * @param array<string, string> $headers lower-cased request headers
     * @param array<string, string> $query
     */
    public function handle(string $path, array $headers = [], array $query = []): HttpResponse
    {
        if (!in_array($path, ['/', '/healthz'], true) && !$this->authorized($headers)) {
            return new HttpResponse(401, "Unauthorized\n", ['WWW-Authenticate' => 'Basic realm="metrics"']);
        }
        $openMetrics = str_contains($headers['accept'] ?? '', 'application/openmetrics-text');

        return match ($path) {
            '/healthz' => new HttpResponse(200, "ok\n", ['Content-Type' => 'text/plain']),
            '/metrics' => $this->metrics($openMetrics),
            '/probe' => $this->probe($query, $openMetrics),
            '/' => new HttpResponse(200, '<h1>php-fpm-exporter</h1><p><a href="/metrics">Metrics</a></p>', ['Content-Type' => 'text/html']),
            default => new HttpResponse(404, "Not found\n", ['Content-Type' => 'text/plain']),
        };
    }

    /** @param array<string, string> $headers */
    private function authorized(array $headers): bool
    {
        if ($this->config->basicAuth === null) {
            return true;
        }
        $given = $headers['authorization'] ?? '';

        return hash_equals('Basic ' . base64_encode($this->config->basicAuth), $given);
    }

    /**
     * Multi-target mode, like blackbox_exporter: Prometheus passes the FPM address per scrape,
     * so one exporter can watch every pod found by service discovery.
     *
     * @param array<string, string> $query
     */
    private function probe(array $query, bool $openMetrics): HttpResponse
    {
        $target = $query['target'] ?? '';
        try {
            $pool = Config::pools('probe=' . $target);
        } catch (\InvalidArgumentException $e) {
            return new HttpResponse(400, $e->getMessage() . "\n", ['Content-Type' => 'text/plain']);
        }
        if ($this->config->probeAllowedHosts !== [] && !in_array(self::host($target), $this->config->probeAllowedHosts, true)) {
            return new HttpResponse(403, "Target host is not allowed\n", ['Content-Type' => 'text/plain']);
        }
        $metrics = (new FpmCollector($this->fetcher, ['probe' => $pool['probe']], $this->log))->collect();

        return new HttpResponse(200, (new TextFormatter())->format($metrics, $openMetrics), ['Content-Type' => self::contentType($openMetrics)]);
    }

    private static function host(string $url): string
    {
        return preg_match('~^[a-z]+://([^/:;]*)~i', $url, $m) === 1 ? strtolower($m[1]) : '';
    }

    private static function contentType(bool $openMetrics): string
    {
        return $openMetrics ? 'application/openmetrics-text; version=1.0.0; charset=utf-8' : 'text/plain; version=0.0.4; charset=utf-8';
    }

    private function metrics(bool $openMetrics): HttpResponse
    {
        $contentType = self::contentType($openMetrics);
        $render = fn(): string => $this->render($openMetrics);
        $body = $this->cache === null ? $render() : $this->cache->remember(($openMetrics ? 'om:' : 'prom:') . serialize($this->config), $this->config->cacheTtl, $render);

        return new HttpResponse(200, $body, ['Content-Type' => $contentType]);
    }

    private function render(bool $openMetrics): string
    {
        $started = hrtime(true);
        $metrics = [
            ...(new FpmCollector($this->fetcher, $this->config->fpmPools, $this->log))->collect(),
            ...(new OpcacheCollector($this->fetcher, $this->config->opcachePools))->collect(),
        ];
        $metrics[] = new Metric('phpfpm_exporter_build_info', 'gauge', 'Exporter version and PHP runtime, always 1', 1, ['version' => Version::VERSION, 'php_version' => PHP_VERSION]);
        $metrics[] = new Metric('phpfpm_exporter_scrape_duration_seconds', 'gauge', 'Time spent collecting metrics', (hrtime(true) - $started) / 1e9);

        return (new TextFormatter())->format($metrics, $openMetrics);
    }
}
