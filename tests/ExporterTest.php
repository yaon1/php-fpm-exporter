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
}
