# Imagem PHP do Bitman (app, queue e scheduler).
# Pré-lançamento: o código é montado por bind mount e as dependências de dev
# ficam disponíveis para rodar os testes dentro do container.
FROM php:8.5-fpm-alpine AS app

# O opcache já é embutido no PHP 8.5 (não é instalado como extensão).

RUN apk add --no-cache icu-libs libpq libzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev postgresql-dev libzip-dev linux-headers \
    && docker-php-ext-install -j"$(nproc)" intl pdo_pgsql pcntl zip bcmath \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/zz-bitman.ini

ENV COMPOSER_HOME=/tmp/composer \
    COMPOSER_NO_INTERACTION=1

WORKDIR /var/www/html
