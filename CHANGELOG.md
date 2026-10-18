# Changelog

Формат: [Keep a Changelog](https://keepachangelog.com/ru/1.1.0/), версии по [SemVer](https://semver.org/lang/ru/).

## [0.2.0] — 2026-10-19

### Добавлено
- Опрос FPM напрямую по FastCGI (`tcp://`, `unix://`), без веб-сервера.
- Режим `/probe` для одного экспортера на весь кластер, с allow-list хостов.
- CLI `bin/php-fpm-exporter` и вывод в textfile для node_exporter.
- Метрики: `phpfpm_info`, время старта, длительность опроса, средний возраст запросов, пиковый CPU; для OPcache — hit ratio, хеш-таблица, blacklist, JIT, preload.
- Basic Auth, кэш опросов, отключаемая проверка TLS, формат OpenMetrics.
- Правила алертов с тестами promtool, манифесты Kubernetes, unit-файлы systemd.

### Изменено
- Неизвестные пути отдают 404.
- Ошибки конфигурации и опроса пишутся в stderr.

### Безопасность
- Агент OPcache отклоняет запросы с публичных IP.

## [0.1.0]

- Первый релиз: метрики пулов FPM и OPcache по HTTP, дашборд Grafana, Docker-образ.
