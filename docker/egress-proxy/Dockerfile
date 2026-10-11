# bitman-egress-proxy: única saída de rede da git-sandbox (ADR 0003, docs/seguranca.md G3).
FROM alpine:3.22

RUN apk add --no-cache tinyproxy

COPY tinyproxy.conf /etc/tinyproxy/tinyproxy.conf
COPY allowed-hosts /etc/tinyproxy/allowed-hosts

USER nobody
EXPOSE 8888
CMD ["tinyproxy", "-d", "-c", "/etc/tinyproxy/tinyproxy.conf"]
