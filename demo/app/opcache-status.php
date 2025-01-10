<?php

// Drop this file into a docroot served by the FPM pool you want to observe and
// restrict access to it (e.g. `allow 127.0.0.1; deny all;` in nginx).
// OPcache state lives inside FPM workers, so the CLI exporter cannot read it directly.

declare(strict_types=1);

header('Content-Type: application/json');
header('Cache-Control: no-store');

echo json_encode(function_exists('opcache_get_status') ? opcache_get_status(false) : false, JSON_THROW_ON_ERROR);
