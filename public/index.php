<?php

declare(strict_types=1);

use Yaon\FpmExporter\Config;
use Yaon\FpmExporter\FpmCollector;
use Yaon\FpmExporter\SchemeFetcher;
use Yaon\FpmExporter\Metric;
use Yaon\FpmExporter\OpcacheCollector;
use Yaon\FpmExporter\TextFormatter;

require dirname(__DIR__) . '/vendor/autoload.php';

$path = parse_url(is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);

if ($path === '/healthz') {
    echo "ok\n";

    return;
}

if ($path !== '/metrics') {
    header('Content-Type: text/html');
    echo '<h1>php-fpm-exporter</h1><p><a href="/metrics">Metrics</a></p>';

    return;
}

/** @var array<string, string> $env */
$env = array_filter(getenv(), 'is_string');
$config = Config::fromEnv($env);
$fetcher = SchemeFetcher::create($config->timeout);
$started = hrtime(true);

$metrics = [
    ...(new FpmCollector($fetcher, $config->fpmPools))->collect(),
    ...(new OpcacheCollector($fetcher, $config->opcachePools))->collect(),
];
$metrics[] = new Metric('phpfpm_exporter_scrape_duration_seconds', 'gauge', 'Time spent collecting metrics', (hrtime(true) - $started) / 1e9);

header('Content-Type: text/plain; version=0.0.4; charset=utf-8');
echo (new TextFormatter())->format($metrics);
