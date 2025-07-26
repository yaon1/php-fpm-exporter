<?php

declare(strict_types=1);

namespace Yaon\FpmExporter;

/** Renders metrics in the Prometheus text format 0.0.4 or, optionally, OpenMetrics 1.0. */
final class TextFormatter
{
    /** @param iterable<Metric> $metrics */
    public function format(iterable $metrics, bool $openMetrics = false): string
    {
        $out = '';
        $declared = [];
        foreach ($metrics as $m) {
            // OpenMetrics names the counter family without the "_total" suffix of its samples.
            $family = $openMetrics && $m->type === 'counter' ? (string) preg_replace('/_total$/', '', $m->name) : $m->name;
            $type = $openMetrics && $m->type === 'untyped' ? 'unknown' : $m->type;
            if (!isset($declared[$family])) {
                $out .= sprintf("# HELP %s %s\n# TYPE %s %s\n", $family, $this->escape($m->help), $family, $type);
                $declared[$family] = true;
            }
            $out .= $m->name . $this->labels($m->labels) . ' ' . $this->value($m->value) . "\n";
        }

        return $openMetrics ? $out . "# EOF\n" : $out;
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
