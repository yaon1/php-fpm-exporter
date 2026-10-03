# Как внести вклад

```bash
composer install
make test analyse alerts
composer cs
```

- Новая метрика — это строка в коллекторе, тест на неё и строка в таблице «Метрики» в README.
- Имена метрик следуют [правилам Prometheus](https://prometheus.io/docs/practices/naming/): базовые единицы (`_seconds`, `_bytes`), `_total` у счётчиков.
- Не добавляйте label с высокой кардинальностью (pid, URI запроса).
- Интеграционные тесты FastCGI запускаются, если в системе есть `php-fpm`; путь можно задать в `PHP_FPM_BINARY`.
- Коммиты в формате [Conventional Commits](https://www.conventionalcommits.org/ru/).
