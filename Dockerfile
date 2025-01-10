FROM composer:2 AS deps
WORKDIR /app
COPY composer.json ./
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader --no-scripts

FROM php:8.4-cli-alpine
WORKDIR /app
COPY --from=deps /app/vendor vendor
COPY src src
COPY public public
ENV PHP_CLI_SERVER_WORKERS=4
EXPOSE 9253
USER nobody
HEALTHCHECK --interval=30s --timeout=3s CMD wget -qO- http://127.0.0.1:9253/healthz || exit 1
CMD ["php", "-S", "0.0.0.0:9253", "-t", "public"]
