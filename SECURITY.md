# Безопасность

Об уязвимостях сообщайте через [приватный advisory](https://github.com/yaon1/php-fpm-exporter/security/advisories/new), а не в публичных issues.

Рекомендации:

- Не публикуйте `pm.status_path` и `opcache-status.php` наружу; используйте FastCGI-транспорт или ограничьте доступ во внутреннюю сеть.
- В режиме `/probe` задайте `PROBE_ALLOWED_HOSTS`, иначе экспортер можно использовать для подключения к произвольным адресам.
- Включите `METRICS_BASIC_AUTH`, если порт экспортера доступен вне кластера.
