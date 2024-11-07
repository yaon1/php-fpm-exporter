<?php

declare(strict_types=1);

namespace Yaon\FpmExporter;

interface Fetcher
{
    /** @throws \RuntimeException when the URL cannot be fetched */
    public function fetch(string $url): string;
}
