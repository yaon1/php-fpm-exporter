<?php

declare(strict_types=1);

namespace Yaon\FpmExporter;

final readonly class Metric
{
    /** @param array<string, string> $labels */
    public function __construct(
        public string $name,
        public string $type,
        public string $help,
        public float|int $value,
        public array $labels = [],
    ) {
    }
}
