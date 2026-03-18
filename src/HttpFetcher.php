<?php

declare(strict_types=1);

namespace Yaon\FpmExporter;

final readonly class HttpFetcher implements Fetcher
{
    /** @param bool $verifyTls false allows self-signed certificates on internal status endpoints */
    public function __construct(private float $timeout = 2.0, private bool $verifyTls = true)
    {
    }

    public function fetch(string $url): string
    {
        $context = stream_context_create(['http' => [
            'timeout' => $this->timeout,
            'ignore_errors' => false,
            'header' => 'User-Agent: php-fpm-exporter/' . Version::VERSION . "\r\n",
        ], 'ssl' => ['verify_peer' => $this->verifyTls, 'verify_peer_name' => $this->verifyTls]]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new \RuntimeException(error_get_last()['message'] ?? "Failed to fetch $url");
        }

        return $body;
    }
}
