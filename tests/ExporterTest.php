<?php

declare(strict_types=1);

namespace Yaon\FpmExporter\Tests;

use PHPUnit\Framework\TestCase;
use Yaon\FpmExporter\Config;
use Yaon\FpmExporter\Exporter;
use Yaon\FpmExporter\Fetcher;

final class ExporterTest extends TestCase
{
    /** @param array<string, string> $env */
    private function exporter(array $env = ['FPM_STATUS_URLS' => 'www=http://fpm/status']): Exporter
    {
        $fetcher = new class implements Fetcher {
            public function fetch(string $url): string
            {
                return (string) file_get_contents(__DIR__ . '/fixtures/fpm-status.json');
            }
        };

        return new Exporter(Config::fromEnv($env), $fetcher);
    }

    public function testServesMetrics(): void
    {
        $response = $this->exporter()->handle('/metrics');

        self::assertSame(200, $response->status);
        self::assertStringStartsWith('text/plain; version=0.0.4', $response->headers['Content-Type']);
        self::assertStringContainsString("phpfpm_up{pool=\"www\"} 1\n", $response->body);
        self::assertStringContainsString('phpfpm_exporter_scrape_duration_seconds ', $response->body);
    }

    public function testHealthAndIndex(): void
    {
        self::assertSame("ok\n", $this->exporter()->handle('/healthz')->body);
        self::assertStringContainsString('href="/metrics"', $this->exporter()->handle('/')->body);
    }

    public function testExposesBuildInfo(): void
    {
        self::assertMatchesRegularExpression('/^phpfpm_exporter_build_info\{version="[^"]+",php_version="8\.\d+/m', $this->exporter()->handle('/metrics')->body);
    }

    public function testUnknownPathIs404(): void
    {
        self::assertSame(404, $this->exporter()->handle('/favicon.ico')->status);
    }

    public function testBasicAuthProtectsMetrics(): void
    {
        $exporter = $this->exporter(['METRICS_BASIC_AUTH' => 'prom:s3cret']);

        self::assertSame(401, $exporter->handle('/metrics')->status);
        self::assertSame(401, $exporter->handle('/metrics', ['authorization' => 'Basic ' . base64_encode('prom:wrong')])->status);
        self::assertSame(200, $exporter->handle('/metrics', ['authorization' => 'Basic ' . base64_encode('prom:s3cret')])->status);
        self::assertSame(200, $exporter->handle('/healthz')->status);
    }

    public function testNegotiatesOpenMetrics(): void
    {
        $response = $this->exporter()->handle('/metrics', ['accept' => 'application/openmetrics-text;version=1.0.0,text/plain;q=0.5']);

        self::assertStringStartsWith('application/openmetrics-text', $response->headers['Content-Type']);
        self::assertStringEndsWith("# EOF\n", $response->body);
    }
}
