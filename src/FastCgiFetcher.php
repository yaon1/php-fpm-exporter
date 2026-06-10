<?php

declare(strict_types=1);

namespace Yaon\FpmExporter;

use Yaon\FpmExporter\FastCgi\Client;

/**
 * Talks to FPM directly, no web server needed.
 *
 * URL forms:
 *   tcp://127.0.0.1:9000/status
 *   unix:///run/php/php-fpm.sock;/status
 * The path is used as SCRIPT_NAME and SCRIPT_FILENAME, so for opcache-status.php give its absolute path inside the FPM container.
 */
final readonly class FastCgiFetcher implements Fetcher
{
    /** @param \Closure(string, float): Client|null $clientFactory */
    public function __construct(private float $timeout = 2.0, private ?\Closure $clientFactory = null) {}

    /** @return array{string, string, string} [socket address, script path, query string] */
    public static function parse(string $url): array
    {
        if (preg_match('~^unix://(?<socket>[^;]+);(?<path>/[^?]*)(?:\?(?<query>.*))?$~', $url, $m) === 1) {
            return ['unix://' . $m['socket'], $m['path'], $m['query'] ?? ''];
        }
        if (preg_match('~^tcp://(?<host>[^/]+)(?<path>/[^?]*)(?:\?(?<query>.*))?$~', $url, $m) === 1) {
            return ['tcp://' . $m['host'], $m['path'], $m['query'] ?? ''];
        }

        throw new \InvalidArgumentException("Unsupported FastCGI URL \"$url\"; use tcp://host:port/path or unix:///socket;/path");
    }

    public function fetch(string $url): string
    {
        [$address, $path, $query] = self::parse($url);
        $client = $this->clientFactory !== null ? ($this->clientFactory)($address, $this->timeout) : new Client($address, $this->timeout);

        $response = $client->request([
            'GATEWAY_INTERFACE' => 'FastCGI/1.0',
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => $path,
            'SCRIPT_FILENAME' => $path,
            'REQUEST_URI' => $path . ($query === '' ? '' : "?$query"),
            'QUERY_STRING' => $query,
            'SERVER_SOFTWARE' => 'php-fpm-exporter',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        if ($response->status >= 400) {
            throw new \RuntimeException(sprintf('%s returned %d: %s', $url, $response->status, trim($response->body ?: $response->stderr)));
        }

        return $response->body;
    }
}
