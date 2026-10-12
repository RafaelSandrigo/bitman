# Imagem PHP do Bitman (app, queue e scheduler).
# Pré-lançamento: o código é montado por bind mount e as dependências de dev
# ficam disponíveis para rodar os testes dentro do container.
FROM php:8.5-fpm-alpine AS app

# O opcache já é embutido no PHP 8.5 (não é instalado como extensão).

# GD (PNG, JPEG e WebP): re-codificação das thumbnails enviadas (A4).
RUN apk add --no-cache icu-libs libpq libzip libpng libjpeg-turbo libwebp \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev postgresql-dev libzip-dev linux-headers \
        libpng-dev libjpeg-turbo-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" intl pdo_pgsql pcntl zip bcmath gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear

# O php-fpm roda como www-data e grava as thumbnails. Um volume nomeado novo herda
# o dono deste diretório da imagem.
RUN mkdir -p /var/bitman/media/thumbnails && chown -R www-data:www-data /var/bitman/media

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/zz-bitman.ini

ENV COMPOSER_HOME=/tmp/composer \
    COMPOSER_NO_INTERACTION=1

WORKDIR /var/www/html
