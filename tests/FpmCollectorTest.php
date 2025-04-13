<?php

declare(strict_types=1);

namespace Yaon\FpmExporter\Tests;

use PHPUnit\Framework\TestCase;
use Yaon\FpmExporter\Fetcher;
use Yaon\FpmExporter\FpmCollector;
use Yaon\FpmExporter\Metric;

final class FpmCollectorTest extends TestCase
{
    /** @param array<string, string> $responses */
    private function fetcher(array $responses): Fetcher
    {
        return new class ($responses) implements Fetcher {
            /** @var list<string> */
            public array $requested = [];

            /** @param array<string, string> $responses */
            public function __construct(private array $responses)
            {
            }

            public function fetch(string $url): string
            {
                $this->requested[] = $url;

                return $this->responses[$url] ?? throw new \RuntimeException('down');
            }
        };
    }

    /**
     * @param iterable<Metric> $metrics
     * @return array<string, float|int>
     */
    private function index(iterable $metrics): array
    {
        $out = [];
        foreach ($metrics as $m) {
            $out[$m->name . json_encode($m->labels)] = $m->value;
        }

        return $out;
    }

    public function testMapsPoolStatus(): void
    {
        $json = (string) file_get_contents(__DIR__ . '/fixtures/fpm-status.json');
        $metrics = $this->index((new FpmCollector($this->fetcher(['http://fpm/status?json&full' => $json]), ['www' => 'http://fpm/status']))->collect());

        self::assertSame(1, $metrics['phpfpm_up{"pool":"www"}']);
        self::assertSame(1234, $metrics['phpfpm_accepted_connections_total{"pool":"www"}']);
        self::assertSame(2, $metrics['phpfpm_listen_queue{"pool":"www"}']);
        self::assertSame(2, $metrics['phpfpm_process_state{"pool":"www","state":"idle"}']);
        self::assertSame(1, $metrics['phpfpm_process_state{"pool":"www","state":"running"}']);
        self::assertSame(1.5, $metrics['phpfpm_process_max_request_duration_seconds{"pool":"www"}']);
        self::assertSame(4194304, $metrics['phpfpm_process_max_request_memory_bytes{"pool":"www"}']);
    }

    public function testUnreachablePoolReportsDown(): void
    {
        $metrics = $this->index((new FpmCollector($this->fetcher([]), ['api' => 'http://api/status?x=1']))->collect());

        self::assertSame(['phpfpm_up{"pool":"api"}' => 0], $metrics);
    }

    public function testExposesProcessManagerInfo(): void
    {
        $json = (string) file_get_contents(__DIR__ . "/fixtures/fpm-status.json");
        $metrics = $this->index((new FpmCollector($this->fetcher(["http://fpm/status?json&full" => $json]), ["www" => "http://fpm/status"]))->collect());

        self::assertSame(1, $metrics["phpfpm_info{\"pool\":\"www\",\"process_manager\":\"dynamic\"}"]);
    }
}
