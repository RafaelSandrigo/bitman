# 0004 — Jogos em outra origem, iframe sandbox e postMessage

- **Status:** Aceito
- **Data:** 2026-10-09

## Contexto
O JavaScript dos jogos é potencialmente malicioso e roda no navegador de usuários
autenticados no hub.

## Decisão
- Artefatos publicados servidos por um nginx estático (`bitman-games`) em **outro host**: `bitman-games.sandrigo.com.br`.
  Por decisão do Rafael, ele é um subdomínio de `sandrigo.com.br` e não um domínio registrável próprio (ver *Consequências*).
- O nginx `games` envia `Content-Security-Policy: sandbox allow-scripts` além da CSP de recursos, então o jogo fica
  em origem opaca **mesmo se aberto direto numa aba**. Ele nunca define cookies.
- Carregados em `<iframe sandbox="allow-scripts">` sem `allow-same-origin`, o que dá origem opaca: sem cookies, sem storage e sem acesso ao DOM do hub.
- CSP com `connect-src 'none'`: o jogo não tem rede. O score chega ao hub via `postMessage`, e a SPA valida e envia para a API.

## Consequências
- O jogo não consegue ler a sessão do usuário, chamar a API nem exfiltrar dados.
- Os jogos não podem usar `localStorage`, recursos externos nem multiplayer. Isso está documentado no [protocolo](../protocolo-do-jogo.md).
- Subdomínio no mesmo site (`sandrigo.com.br`): os atributos `SameSite` não separam o hub dos jogos, e cookies
  definidos por outros sistemas com `Domain=.sandrigo.com.br` seriam enviados ao host de jogos. O risco é aceito porque:
  o jogo roda em origem opaca (não lê nem grava cookies), não tem rede (`connect-src 'none'`), o host de jogos é
  estático e ignora cookies, e o hub usa cookies `__Host-` (host-only, `Secure`, `Path=/`). **Regra:** nenhum sistema
  em `*.sandrigo.com.br` deve emitir cookies com `Domain=.sandrigo.com.br`.
- Migrar para um domínio registrável próprio é só configuração (DNS + variável `GAMES_ORIGIN`), sem mudar código.
