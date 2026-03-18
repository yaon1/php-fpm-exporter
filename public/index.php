<?php

declare(strict_types=1);

use Yaon\FpmExporter\Config;
use Yaon\FpmExporter\Exporter;
use Yaon\FpmExporter\FileCache;
use Yaon\FpmExporter\SchemeFetcher;

require dirname(__DIR__) . '/vendor/autoload.php';

/** @var array<string, string> $env */
$env = array_filter(getenv(), 'is_string');
try {
    $config = Config::fromEnv($env);
} catch (InvalidArgumentException $e) {
    http_response_code(500);
    file_put_contents('php://stderr', 'Configuration error: ' . $e->getMessage() . "\n");
    echo 'Configuration error: ', $e->getMessage(), "\n";

    return;
}
$log = static function (string $message): void {
    file_put_contents('php://stderr', sprintf("[%s] %s\n", date(DATE_ATOM), $message));
};

$headers = [];
foreach ($_SERVER as $key => $value) {
    if (is_string($key) && is_string($value) && str_starts_with($key, 'HTTP_')) {
        $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
    }
}
$path = (string) parse_url(is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);

(new Exporter($config, SchemeFetcher::create($config->timeout, $config->verifyTls), $log, new FileCache(sys_get_temp_dir())))->handle($path, $headers)->send();
