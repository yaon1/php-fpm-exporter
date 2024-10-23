<?php

declare(strict_types=1);

namespace Yaon\FpmExporter;

/** Renders metrics in the Prometheus text exposition format 0.0.4. */
final class TextFormatter
{
    /** @param iterable<Metric> $metrics */
    public function format(iterable $metrics): string
    {
        $out = '';
        $declared = [];
        foreach ($metrics as $m) {
            if (!isset($declared[$m->name])) {
                $out .= sprintf("# HELP %s %s\n# TYPE %s %s\n", $m->name, $this->escape($m->help), $m->name, $m->type);
                $declared[$m->name] = true;
            }
            $out .= $m->name . $this->labels($m->labels) . ' ' . $this->value($m->value) . "\n";
        }

        return $out;
    }

    /** @param array<string, string> $labels */
    private function labels(array $labels): string
    {
        if ($labels === []) {
            return '';
        }
        $pairs = [];
        foreach ($labels as $k => $v) {
            $pairs[] = sprintf('%s="%s"', $k, str_replace(['\\', '"', "\n"], ['\\\\', '\\"', '\\n'], $v));
        }

        return '{' . implode(',', $pairs) . '}';
    }

    private function value(float|int $v): string
    {
        return is_int($v) ? (string) $v : (is_nan($v) ? 'NaN' : rtrim(rtrim(sprintf('%.6F', $v), '0'), '.'));
    }

    private function escape(string $help): string
    {
        return str_replace(['\\', "\n"], ['\\\\', '\\n'], $help);
    }
}
