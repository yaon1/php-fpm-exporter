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
    ) {
    }

    /** @param array<string, string> $headers lower-cased request headers */
    public function handle(string $path, array $headers = []): HttpResponse
    {
        return match ($path) {
            '/healthz' => new HttpResponse(200, "ok\n", ['Content-Type' => 'text/plain']),
            '/metrics' => $this->authorized($headers) ? $this->metrics(str_contains($headers['accept'] ?? '', 'application/openmetrics-text')) : new HttpResponse(401, "Unauthorized\n", ['WWW-Authenticate' => 'Basic realm="metrics"']),
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

    private function metrics(bool $openMetrics): HttpResponse
    {
        $contentType = $openMetrics ? 'application/openmetrics-text; version=1.0.0; charset=utf-8' : 'text/plain; version=0.0.4; charset=utf-8';
        $render = fn (): string => $this->render($openMetrics);
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
