FROM composer:2 AS deps
WORKDIR /app
COPY composer.json ./
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader --no-scripts

FROM php:8.4-cli-alpine
LABEL org.opencontainers.image.title="php-fpm-exporter" \
      org.opencontainers.image.description="Prometheus exporter for PHP-FPM and OPcache" \
      org.opencontainers.image.source="https://github.com/yaon1/php-fpm-exporter" \
      org.opencontainers.image.licenses="MIT"
WORKDIR /app
COPY --from=deps /app/vendor vendor
COPY src src
COPY public public
COPY bin bin
ENV PHP_CLI_SERVER_WORKERS=4
EXPOSE 9253
USER nobody
HEALTHCHECK --interval=30s --timeout=3s CMD wget -qO- http://127.0.0.1:9253/healthz || exit 1
CMD ["php", "-S", "0.0.0.0:9253", "-t", "public"]
