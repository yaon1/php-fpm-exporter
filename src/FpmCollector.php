<?php

declare(strict_types=1);

namespace Yaon\FpmExporter;

/** Scrapes the FPM status page (`pm.status_path`, queried with `?json&full`). */
final readonly class FpmCollector
{
    private const GAUGES = [
        'listen queue' => ['listen_queue', 'Requests waiting for a free process'],
        'max listen queue' => ['max_listen_queue', 'Max listen queue length since start'],
        'listen queue len' => ['listen_queue_length', 'Size of the socket listen queue'],
        'idle processes' => ['idle_processes', 'Idle worker processes'],
        'active processes' => ['active_processes', 'Active worker processes'],
        'total processes' => ['total_processes', 'Total worker processes'],
        'max active processes' => ['max_active_processes', 'Max simultaneously active processes since start'],
        'start since' => ['uptime_seconds', 'Seconds since the pool started'],
    ];

    private const COUNTERS = [
        'accepted conn' => ['accepted_connections_total', 'Accepted connections'],
        'max children reached' => ['max_children_reached_total', 'Times pm.max_children was reached'],
        'slow requests' => ['slow_requests_total', 'Requests exceeding request_slowlog_timeout'],
    ];

    /**
     * @param array<string, string> $pools pool label => status URL
     * @param (\Closure(string): void)|null $log receives scrape errors, e.g. for stderr
     */
    public function __construct(private Fetcher $fetcher, private array $pools, private ?\Closure $log = null)
    {
    }

    /** @return \Generator<Metric> */
    public function collect(): \Generator
    {
        foreach ($this->pools as $name => $url) {
            $started = hrtime(true);
            try {
                $status = Json::object($this->fetcher->fetch($url . (str_contains($url, '?') ? '&' : '?') . 'json&full'));
            } catch (\Throwable $e) {
                $this->log !== null && ($this->log)(sprintf('pool %s: %s', $name, $e->getMessage()));
                yield new Metric('phpfpm_up', 'gauge', 'Whether the FPM status page could be scraped', 0, ['pool' => $name]);
                continue;
            }

            $labels = ['pool' => $name];
            yield new Metric('phpfpm_up', 'gauge', 'Whether the FPM status page could be scraped', 1, $labels);
            yield new Metric('phpfpm_scrape_duration_seconds', 'gauge', 'Time taken to fetch the pool status', (hrtime(true) - $started) / 1e9, $labels);
            yield new Metric('phpfpm_start_time_seconds', 'gauge', 'Unix time when the pool started', Json::number($status, 'start time'), $labels);
            $manager = is_string($status['process manager'] ?? null) ? $status['process manager'] : 'unknown';
            yield new Metric('phpfpm_info', 'gauge', 'Pool information, always 1', 1, $labels + ['process_manager' => $manager]);
            foreach (self::GAUGES as $key => [$metric, $help]) {
                yield new Metric("phpfpm_$metric", 'gauge', $help, Json::number($status, $key), $labels);
            }
            foreach (self::COUNTERS as $key => [$metric, $help]) {
                yield new Metric("phpfpm_$metric", 'counter', $help, Json::number($status, $key), $labels);
            }
            yield from $this->processes(Json::nested($status, 'processes'), $labels);
        }
    }

    /**
     * @param array<mixed> $processes
     * @param array<string, string> $labels
     * @return \Generator<Metric>
     */
    private function processes(array $processes, array $labels): \Generator
    {
        $states = [];
        $maxDuration = 0;
        $maxMemory = 0;
        $maxCpu = 0;
        foreach ($processes as $p) {
            if (!is_array($p)) {
                continue;
            }
            $state = is_string($p['state'] ?? null) ? strtolower($p['state']) : 'unknown';
            $states[$state] = ($states[$state] ?? 0) + 1;
            $maxDuration = max($maxDuration, Json::number($p, 'request duration'));
            $maxMemory = max($maxMemory, Json::number($p, 'last request memory'));
            $maxCpu = max($maxCpu, Json::number($p, 'last request cpu'));
        }

        foreach ($states as $state => $count) {
            yield new Metric('phpfpm_process_state', 'gauge', 'Worker processes by state', $count, $labels + ['state' => (string) $state]);
        }
        yield new Metric('phpfpm_process_max_request_duration_seconds', 'gauge', 'Longest current/last request duration across workers', $maxDuration / 1_000_000, $labels);
        yield new Metric('phpfpm_process_max_last_request_cpu_ratio', 'gauge', 'Highest CPU usage of the last request across workers (1 = one core)', $maxCpu / 100, $labels);
        yield new Metric('phpfpm_process_max_request_memory_bytes', 'gauge', 'Peak memory of the last request across workers', $maxMemory, $labels);
    }
}
