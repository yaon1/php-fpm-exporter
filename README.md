# php-fpm-exporter

Prometheus exporter for **PHP-FPM** pools and **OPcache**, with a ready-made Grafana dashboard.

- Multiple pools per exporter, each with its own `pool` label
- Worker state breakdown, longest running request, peak request memory
- OPcache memory, hit/miss counters, restarts by reason, interned strings
- Zero runtime dependencies, ~20 MB image, `linux/amd64` + `linux/arm64`

## Quick start

```bash
cd demo && docker compose up -d
```

| URL | What |
|---|---|
| http://localhost:8088 | Demo PHP app |
| http://localhost:9253/metrics | Exporter |
| http://localhost:9090 | Prometheus |
| http://localhost:3000/d/php-fpm-exporter | Grafana dashboard |

## Setup

1. Enable the status page in your pool config: `pm.status_path = /status`
2. *(Optional, for OPcache)* copy [`agent/opcache-status.php`](agent/opcache-status.php) into a docroot served by the pool. OPcache lives inside FPM workers, so it has to be read from there.
3. Expose both through your web server to the exporter only (see [`demo/nginx.conf`](demo/nginx.conf)).
4. Run the exporter:

```bash
docker run -p 9253:9253 \
  -e FPM_STATUS_URLS="www=http://nginx/status,api=http://nginx/api-status" \
  -e OPCACHE_STATUS_URLS="www=http://nginx/opcache-status.php" \
  ghcr.io/yaon1/php-fpm-exporter
```

## Configuration

| Variable | Default | Description |
|---|---|---|
| `FPM_STATUS_URLS` | — | Comma-separated `label=url` pairs; a bare URL is labelled `default` |
| `OPCACHE_STATUS_URLS` | — | Same format, pointing at `opcache-status.php` |
| `SCRAPE_TIMEOUT` | `2` | Per-request timeout in seconds |

## Metrics

| Metric | Type | Labels |
|---|---|---|
| `phpfpm_up` | gauge | pool |
| `phpfpm_{idle,active,total,max_active}_processes` | gauge | pool |
| `phpfpm_listen_queue`, `phpfpm_max_listen_queue`, `phpfpm_listen_queue_length` | gauge | pool |
| `phpfpm_accepted_connections_total`, `phpfpm_max_children_reached_total`, `phpfpm_slow_requests_total` | counter | pool |
| `phpfpm_process_state` | gauge | pool, state |
| `phpfpm_process_max_request_duration_seconds`, `phpfpm_process_max_request_memory_bytes` | gauge | pool |
| `opcache_enabled`, `opcache_cache_full`, `opcache_cached_scripts` | gauge | pool |
| `opcache_memory_bytes` | gauge | pool, kind (used/free/wasted) |
| `opcache_hits_total`, `opcache_misses_total` | counter | pool |
| `opcache_restarts_total` | counter | pool, reason (oom/hash/manual) |
| `opcache_interned_strings_{used,buffer}_bytes` | gauge | pool |

### Example alerts

```yaml
- alert: PhpFpmMaxChildrenReached
  expr: increase(phpfpm_max_children_reached_total[10m]) > 0
- alert: OpcacheFull
  expr: opcache_cache_full == 1
```

## Development

```bash
composer install && composer test && composer analyse
```

## License

MIT
