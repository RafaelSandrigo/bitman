# bitman-git-sandbox (ADR 0003, docs/seguranca.md G1–G8).
FROM php:8.5-cli-alpine

RUN apk add --no-cache git \
    && addgroup -S -g 10001 sandbox \
    && adduser -S -D -H -u 10001 -G sandbox sandbox

COPY server.php /srv/sandbox/server.php
COPY src/ /srv/sandbox/src/

USER sandbox
WORKDIR /tmp
EXPOSE 8080

# Servidor embutido do PHP: atende uma requisição por vez (intencional, G7).
CMD ["php", "-S", "0.0.0.0:8080", "/srv/sandbox/server.php"]
