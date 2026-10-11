# Protocolo do jogo

Guia para quem desenvolve um jogo para o Bitman: o que o repositório precisa ter,
o que o jogo pode e não pode fazer e como enviar o score.

## Repositório

- Público, via **HTTPS**, hospedado em `github.com`, `gitlab.com` ou `codeberg.org`.
- Na **raiz** do repositório, exatamente estes três arquivos de texto UTF-8:

  | Arquivo | Limite |
  |---|---|
  | `index.html` | 1 MiB |
  | `style.css` | 1 MiB |
  | `game.js` | 1 MiB |

- **Outros arquivos são ignorados** (README, imagens, `package.json`...). Eles não
  bloqueiam a importação, mas não são publicados.
- O repositório **não pode** ter symlinks nem submodules, e os três arquivos não podem
  estar marcados como executáveis.
- No máximo 500 entradas na árvore.
- Nada é compilado ou instalado: **não rodamos** `npm install`, build nem scripts.
  Faça commit do JavaScript final.

## Ambiente de execução

O jogo roda num `iframe` com `sandbox="allow-scripts"`, servido de um domínio
separado e com uma CSP restritiva. Na prática:

| Pode | Não pode |
|---|---|
| Executar `game.js` e aplicar `style.css` | Carregar scripts, CSS, fontes ou imagens externas (CDN incluída) |
| Usar `<canvas>`, DOM, eventos de teclado, mouse e toque | Fazer requisições de rede (`fetch`, XHR, WebSocket) |
| Usar imagens embutidas como `data:` | Usar `localStorage`, `sessionStorage`, IndexedDB ou cookies (lançam erro na origem opaca) |
| Conversar com o hub por `postMessage` | Abrir popups, `alert`/`confirm`, formulários ou navegar a página principal |
| | Usar código inline: `<script>` inline, `onclick=` e `style=` (bloqueados pela CSP) |

No `index.html`, referencie os arquivos de forma relativa:

```html
<!doctype html>
<html>
  <head>
    <meta charset="utf-8">
    <link rel="stylesheet" href="style.css">
  </head>
  <body>
    <canvas id="game"></canvas>
    <script src="game.js"></script>
  </body>
</html>
```

## Comunicação com o hub

O jogo envia mensagens para `window.parent`. Todas seguem o formato:

```ts
{ source: "bitman", version: 1, type: string, ...payload }
```

| Mensagem | Quando | Payload |
|---|---|---|
| `ready` | O jogo carregou (opcional) | — |
| `game-over` | A partida terminou | `score`: inteiro entre 0 e 9.007.199.254.740.991 (`Number.MAX_SAFE_INTEGER`) |

```js
function reportGameOver(score) {
  window.parent.postMessage(
    { source: "bitman", version: 1, type: "game-over", score: Math.floor(score) },
    "*"
  );
}
```

Regras:

- Um score fora da faixa, fracionário ou que não seja número é **rejeitado** (nunca arredondado).
- Apenas **um** `game-over` por partida é aceito. Para jogar de novo, o jogador
  reinicia pelo hub, que cria uma nova sessão.
- Mensagens fora do formato são ignoradas silenciosamente.
- O jogo **nunca** recebe token, cookie ou dado pessoal do jogador.

## Publicação de uma nova versão

1. Faça push das alterações no repositório.
2. No Bitman, em *Meus jogos*, clique em **Solicitar nova versão** (opcionalmente
   informando branch ou tag).
3. Um administrador revisa o commit e cada arquivo. Você acompanha o status, mas
   não participa da aprovação.
4. Depois de publicada, a nova versão substitui a anterior em `/games/{slug}`.
