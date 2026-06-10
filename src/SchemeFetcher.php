<?php

declare(strict_types=1);

namespace Yaon\FpmExporter;

/** Picks HTTP or FastCGI transport by URL scheme, so both can be mixed in one config. */
final readonly class SchemeFetcher implements Fetcher
{
    public function __construct(private Fetcher $http, private Fetcher $fastCgi) {}

    public static function create(float $timeout, bool $verifyTls = true): self
    {
        return new self(new HttpFetcher($timeout, $verifyTls), new FastCgiFetcher($timeout));
    }

    public function fetch(string $url): string
    {
        // parse_url() rejects "unix:///sock;/path", so read the scheme directly.
        $scheme = strtolower(strstr($url, '://', true) ?: '');

        return match ($scheme) {
            'http', 'https' => $this->http->fetch($url),
            'tcp', 'unix' => $this->fastCgi->fetch($url),
            default => throw new \RuntimeException("Unsupported scheme in $url"),
        };
    }
}
