# Imagem PHP do Bitman (docs/implantacao.md).
#   - target "prod" (app, queue e scheduler): o código do commit embutido, sem dependências de dev.
#     Contexto: a raiz de um "git archive" do commit (make release), com CONFIG_DIR=docker/php.
#   - target "dev" (serviço test, make composer/test/stan): o código entra por bind mount.
#     Contexto: docker/php (CONFIG_DIR padrão).
# O builder clássico do host (sem buildx) não aceita --build-context: o caminho dos arquivos de
# configuração muda com o contexto, por isso o ARG (global, redeclarado em cada estágio).
ARG CONFIG_DIR=.

FROM php:8.5.12-fpm-alpine AS base
ARG CONFIG_DIR

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

# O php-fpm e o worker da fila rodam como www-data e gravam thumbnails, quarentena e
# artefatos. Um volume nomeado novo herda o dono destes diretórios da imagem.
RUN mkdir -p /var/bitman/media/thumbnails /var/bitman/quarantine /var/bitman/artifacts/served /var/bitman/artifacts/archive \
    && chown -R www-data:www-data /var/bitman

COPY ${CONFIG_DIR}/php.ini /usr/local/etc/php/conf.d/zz-bitman.ini

ENV COMPOSER_HOME=/tmp/composer \
    COMPOSER_NO_INTERACTION=1

WORKDIR /var/www/html

# Desenvolvimento e testes: composer e as dependências de dev, código por bind mount.
FROM base AS dev
ARG CONFIG_DIR
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
COPY ${CONFIG_DIR}/php.dev.ini /usr/local/etc/php/conf.d/zz-bitman-dev.ini

# Produção: dependências instaladas antes do código (cache das camadas entre deploys).
FROM base AS prod
ARG CONFIG_DIR
COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --prefer-dist --no-progress --no-scripts --no-autoloader

COPY backend/ ./
# O código fica com dono root: o php-fpm e a fila (www-data) processam conteúdo hostil e
# não podem alterar a aplicação. Só storage e bootstrap/cache são graváveis.
RUN composer dump-autoload --no-dev --optimize \
    && php artisan package:discover --ansi \
    && rm -f /usr/local/bin/composer && rm -rf /tmp/composer \
    && chown -R www-data:www-data storage bootstrap/cache

COPY ${CONFIG_DIR}/php.prod.ini /usr/local/etc/php/conf.d/zz-bitman-prod.ini
COPY ${CONFIG_DIR}/entrypoint.prod.sh /usr/local/bin/bitman-entrypoint
# Via sh: o git archive grava o arquivo como 644 e o builder clássico não tem COPY --chmod.
ENTRYPOINT ["sh", "/usr/local/bin/bitman-entrypoint"]
CMD ["php-fpm"]
