# php-fpm-exporter

**Prometheus-экспортер для PHP-FPM и OPcache с готовым дашбордом Grafana.** Помогает ответить на вопросы, которые обычно всплывают уже во время инцидента: хватает ли воркеров (`max_children reached`), растёт ли очередь запросов, какой запрос висит дольше всех, не переполнен ли OPcache и не перезапускается ли он по OOM.

- **Ходит в FPM напрямую по FastCGI** (`tcp://` или `unix://`): nginx и открытый наружу status-путь не нужны.
- Несколько пулов в одном экспортере, у каждого свой label `pool`.
- Воркеры по состояниям, самый долгий текущий запрос, пиковая память запроса.
- OPcache: память, hit/miss, перезапуски по причинам, interned strings.
- Нет зависимостей во время выполнения, образ ~20 МБ, `amd64` и `arm64`.

## Пример: весь стек за одну команду

```bash
cd demo && docker compose up -d
```

| URL | Что там |
|---|---|
| http://localhost:8088 | Демо-приложение на PHP |
| http://localhost:9253/metrics | Метрики экспортера |
| http://localhost:9090 | Prometheus |
| http://localhost:3000/d/php-fpm-exporter | Дашборд Grafana |

Подключение к своему проекту:

```bash
docker run -p 9253:9253 \
  -e FPM_STATUS_URLS="www=http://nginx/status,api=http://nginx/api-status" \
  -e OPCACHE_STATUS_URLS="www=http://nginx/opcache-status.php" \
  ghcr.io/yaon1/php-fpm-exporter
```

Примеры алертов:

```yaml
- alert: PhpFpmMaxChildrenReached
  expr: increase(phpfpm_max_children_reached_total[10m]) > 0
- alert: OpcacheFull
  expr: opcache_cache_full == 1
```

## Настройка

1. Включите страницу статуса в конфиге пула: `pm.status_path = /status`.
2. *(Для OPcache)* положите [`agent/opcache-status.php`](agent/opcache-status.php) в docroot пула. OPcache живёт внутри воркеров FPM, поэтому читать его нужно оттуда.
3. Укажите адреса в одном из форматов:

| Транспорт | Пример URL |
|---|---|
| FastCGI по TCP | `tcp://app:9000/status` |
| FastCGI по unix-сокету | `unix:///run/php/php-fpm.sock;/status` |
| HTTP через веб-сервер | `http://nginx/status` (см. [`demo/nginx.conf`](demo/nginx.conf)) |

Для OPcache по FastCGI путь — абсолютный путь к файлу внутри контейнера FPM: `tcp://app:9000/var/www/html/opcache-status.php`.

| Переменная | По умолчанию | |
|---|---|---|
| `FPM_STATUS_URLS` | — | Пары `label=url` через запятую; адрес без метки получает `default` |
| `OPCACHE_STATUS_URLS` | — | Тот же формат, адреса `opcache-status.php` |
| `SCRAPE_TIMEOUT` | `2` | Таймаут одного запроса, секунды |
| `SCRAPE_CACHE_TTL` | `0` | Сколько секунд переиспользовать результат опроса; защищает FPM, когда его опрашивают несколько реплик Prometheus |
| `METRICS_BASIC_AUTH` | — | `user:password`, после этого `/metrics` и `/probe` требуют Basic Auth |
| `SCRAPE_INSECURE_SKIP_VERIFY` | `false` | Не проверять TLS-сертификат для `https://` адресов |
| `PROBE_ALLOWED_HOSTS` | — | Список хостов через запятую, которые разрешено опрашивать через `/probe` |

## Режим /probe: один экспортер на весь кластер

Как у blackbox_exporter: адрес FPM приходит в параметре запроса, поэтому один экспортер может опрашивать все поды, найденные через service discovery, без сайдкаров.

```bash
curl 'http://exporter:9253/probe?target=tcp://10.0.3.17:9000/status'
```

Готовый `scrape_config` для Kubernetes лежит в [`deploy/kubernetes/probe-scrape-config.yaml`](deploy/kubernetes/probe-scrape-config.yaml). Ограничьте круг целей переменной `PROBE_ALLOWED_HOSTS`.

## Метрики

| Метрика | Тип | Labels |
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

## Разработка

```bash
composer install && composer test && composer analyse
```

## Лицензия

MIT
