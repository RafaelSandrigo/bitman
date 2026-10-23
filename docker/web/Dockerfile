# bitman-web (docs/implantacao.md): a SPA compilada do commit e a configuração do nginx.
# Contexto: a raiz de um "git archive" do commit (make release).
FROM node:22-alpine AS build
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY frontend/ ./
# A origem dos jogos entra no bundle (frame-src e validação da URL do iframe).
ARG VITE_GAMES_ORIGIN
# M10: o commit do build vai para o bundle e para o version.json (aviso de nova versão).
ARG VITE_BITMAN_BUILD=""
RUN test -n "$VITE_GAMES_ORIGIN" && npm run build

FROM nginx:1.29-alpine
COPY docker/web/templates/ /etc/nginx/templates/
COPY --from=build /app/dist/ /srv/spa/
