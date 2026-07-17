<?php

// Drop this file into a docroot served by the FPM pool you want to observe.
// OPcache state lives inside FPM workers, so the exporter cannot read it directly.
// Prefer scraping it over FastCGI (tcp://fpm:9000/path/to/opcache-status.php) so it
// is never routed through the public web server. As a safety net, requests from
// public IPs are refused unless OPCACHE_STATUS_ALLOW_PUBLIC=1.

declare(strict_types=1);

$remote = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
$private = filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
if (!$private && getenv('OPCACHE_STATUS_ALLOW_PUBLIC') !== '1') {
    http_response_code(403);
    echo "Forbidden\n";

    return;
}

header('Content-Type: application/json');
header('Cache-Control: no-store');

echo json_encode(function_exists('opcache_get_status') ? opcache_get_status(false) : false, JSON_THROW_ON_ERROR);
