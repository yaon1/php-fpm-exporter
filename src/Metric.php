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
        if (preg_match('/^[a-zA-Z_:][a-zA-Z0-9_:]*$/', $name) !== 1) {
            throw new \InvalidArgumentException("Invalid metric name \"$name\"");
        }
        if (!in_array($type, ['gauge', 'counter', 'untyped'], true)) {
            throw new \InvalidArgumentException("Unsupported metric type \"$type\"");
        }
        foreach (array_keys($labels) as $label) {
            if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $label) !== 1 || str_starts_with($label, '__')) {
                throw new \InvalidArgumentException("Invalid label name \"$label\"");
            }
        }
    }
}
