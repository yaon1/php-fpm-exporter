<?php

declare(strict_types=1);

namespace Yaon\FpmExporter;

/** HTTP front of the exporter, independent of the SAPI so it can be tested. */
final readonly class Exporter
{
    /** @param (\Closure(string): void)|null $log */
    public function __construct(private Config $config, private Fetcher $fetcher, private ?\Closure $log = null)
    {
    }

    /** @param array<string, string> $headers lower-cased request headers */
    public function handle(string $path, array $headers = []): HttpResponse
    {
        return match ($path) {
            '/healthz' => new HttpResponse(200, "ok\n", ['Content-Type' => 'text/plain']),
            '/metrics' => $this->metrics(),
            '/' => new HttpResponse(200, '<h1>php-fpm-exporter</h1><p><a href="/metrics">Metrics</a></p>', ['Content-Type' => 'text/html']),
            default => new HttpResponse(404, "Not found\n", ['Content-Type' => 'text/plain']),
        };
    }

    private function metrics(): HttpResponse
    {
        $started = hrtime(true);
        $metrics = [
            ...(new FpmCollector($this->fetcher, $this->config->fpmPools, $this->log))->collect(),
            ...(new OpcacheCollector($this->fetcher, $this->config->opcachePools))->collect(),
        ];
        $metrics[] = new Metric('phpfpm_exporter_build_info', 'gauge', 'Exporter version and PHP runtime, always 1', 1, ['version' => Version::VERSION, 'php_version' => PHP_VERSION]);
        $metrics[] = new Metric('phpfpm_exporter_scrape_duration_seconds', 'gauge', 'Time spent collecting metrics', (hrtime(true) - $started) / 1e9);

        return new HttpResponse(200, (new TextFormatter())->format($metrics), ['Content-Type' => 'text/plain; version=0.0.4; charset=utf-8']);
    }
}
