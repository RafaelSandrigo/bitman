# Snake: jogo de exemplo do Bitman

Um Snake mínimo que segue o [protocolo do jogo](../../docs/protocolo-do-jogo.md):
só `index.html`, `style.css` e `game.js` na raiz, sem nada externo, sem rede e sem storage.

- Ao carregar, envia `ready`; ao morrer, envia **um** `game-over` com o score (10 pontos por fruta).
- Uma partida por carregamento. Para jogar de novo, o jogador usa **Jogar de novo** no Bitman,
  que abre uma nova sessão.

Para publicar: este diretório vira a raiz de um repositório público (ver
[protocolo-do-jogo.md](../../docs/protocolo-do-jogo.md#jogo-de-exemplo)).
