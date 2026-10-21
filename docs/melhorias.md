# Melhorias identificadas

Ideias e ajustes percebidos durante o desenvolvimento que **não** entram na entrega atual.
Ficam aqui para não se perderem; viram tarefa quando forem priorizadas (aí saem desta lista
e entram no [plano.md](plano.md)).

**Regra:** toda melhoria identificada pelo caminho — por quem desenvolve, por um teste manual
ou por uma revisão — é registrada aqui no mesmo commit em que foi percebida. Descreva o
problema (o que acontece hoje), a proposta e a origem. Itens descartados saem com o motivo
anotado no commit.

| # | Melhoria | Problema hoje | Proposta | Origem |
|---|---|---|---|---|
| M1 | Trocar a ref ao reenviar | O reenvio usa sempre a URL e a ref cadastradas no jogo. Quem publica por tag (`v1.0`) precisa de outra ref para corrigir | Campo opcional de branch/tag no **Enviar novamente**, validado pelo `GitRef` e auditado | Teste manual da Fase 3 (2026-10-12) |
| M2 | Histórico de versões para o dono | "Meus jogos" mostra só a versão mais recente de cada jogo | Página do jogo do dono com todas as versões, estados e motivos | Fase 3 (reenvio) |
| M3 | Notificações por e-mail | O dono só descobre que a versão foi reprovada abrindo "Meus jogos"; o admin só vê um reenvio abrindo Importações | E-mail ao dono quando a versão para (reprovada, rejeitada, falha) e resumo ao admin quando há versão aguardando | Fase 3 (reenvio) |
| M4 | Fixture de redirect real | `http.followRedirects=false` e o código `redirected` são testados, mas sem um redirect HTTP de verdade | Servidor HTTPS falso dentro da allowlist só no harness da sandbox | Fase 3 |
| M5 | Dividir o bundle do frontend | O build avisa que o chunk principal passou de 500 kB | `import()` dinâmico por área (admin, desenvolvedor) nas rotas | Build da Fase 3 |
| M6 | Realce de sintaxe no Portão 2 | O visualizador mostra texto puro, com números de linha | Tokenizar no cliente e renderizar os tokens como elementos React (nunca HTML gerado por biblioteca), mantendo J5 | Fase 4 |
| M7 | Diff textual no Portão 2 | Para arquivo `modified`, o admin vê só que mudou, não o quê | Diff linha a linha contra o arquivo da versão publicada (lido do `archive`, Fase 5) | Fase 4 |
| M8 | Status `rejected` do jogo | `dominio.md` prevê o jogo `rejected` quando a primeira versão é rejeitada, mas o jogo fica `submitted`; o dono vê o estado pela versão | Decidir se o status do jogo é necessário (talvez removê-lo) ou implementar a transição, com volta ao reenviar | Fase 4 |
| M9 | Aprovar todos os arquivos de uma vez | Cada arquivo exige abrir e aprovar separadamente (ADR 0006) | Manter a abertura obrigatória, mas permitir aprovar os já abertos num só passo | Fase 4 |
| M10 | Avisar que o hub foi atualizado | Uma aba aberta antes do deploy continua com o JavaScript antigo até ser recarregada: telas e status novos não aparecem (ex.: "Enviar novamente" e a aba Versões) | Publicar um `version.json` no build e, ao focar a aba ou a cada poucos minutos, mostrar "Nova versão disponível — recarregar" | Teste manual da Fase 4 (2026-10-12) |
| M11 | E2E num navegador de verdade | O aceite da Fase 5 é coberto pela API (`EndToEndPublicationTest`) e por testes de componente do iframe; nenhum teste abre o jogo num navegador real | Playwright num container descartável, contra uma stack de teste, conferindo iframe, CSP e headers | Fase 5 |
| M12 | Reconciliar o que está no ar | Se o re-serve depois de um desbloqueio falhar, o jogo fica fora do ar até alguém notar (o admin vê "não está sendo servida") | Comando agendado que compara versões `published` com `served/` e alerta/corrige (só a partir do archive, conferindo hashes) | Fase 5 |
| M13 | Endpoint de jogos do admin | A aba Jogos usa `GET /admin/submissions?status=…`, nome herdado da fila de submissões | Renomear para `GET /admin/games` mantendo o filtro | Fase 5 |

| M14 | Expirar sessões vencidas no banco | Uma sessão que passou do prazo continua `open` no banco: o envio é recusado, mas o estado não reflete isso (só vira `expired` quando outra partida do mesmo jogo começa) | Comando agendado que marca `expired` as vencidas, o que também permite medir partidas abandonadas | Fase 6 |
| M15 | Heurísticas melhores de anomalia | Só "acima do p99 × 3" e só a partir de 20 scores: os primeiros scores de um jogo nunca são sinalizados, e a duração da partida não é considerada | Pontos por segundo de partida comparados ao padrão do jogo e um teto opcional por jogo definido pelo admin | Fase 6 |
| M16 | Posição fora do top 100 | O ranking mostra só as 100 primeiras posições; quem está abaixo só vê a própria posição logo depois de enviar um score | Mostrar "sua posição: Nº" abaixo da tabela para o jogador logado | Fase 6 |
| M17 | Jogar de novo pelo próprio jogo | Cada partida recarrega o iframe pelo botão **Jogar de novo** do hub; o jogo não consegue oferecer "jogar de novo" na própria tela | Mensagem `play-again` no protocolo (v1, opcional): o hub abre outra sessão e recarrega o iframe, sem nunca passar o token ao jogo | Fase 6 |
| M18 | Botão "Seja Desenvolvedor" | Só o admin promove um usuário a `developer`, e o `player` não tem como pedir: a solicitação acontece fora do sistema | Botão **Seja Desenvolvedor** para o `player` com e-mail confirmado abrir uma solicitação com nome, link do GitHub (validado, só `github.com`) e uma breve apresentação. O admin vê a fila, aprova ou rejeita (com motivo), tudo auditado. Aprovar concede a role `developer` pela Action já existente. Um pedido aberto por usuário e limite de taxa; texto da apresentação tratado como não confiável (renderizado só como texto) | Pedido do Rafael (2026-10-17) |
| M19 | Apresentação do Bitman na home | A página inicial vai direto para os cards dos jogos, sem explicar o que é o Bitman | Bloco antes da lista com um texto curto sobre o que é o Bitman (jogos web em HTML/JS revisados por humanos e rodando isolados, com ranking) e um CTA **Jogar agora** que leva à lista (âncora) ou ao primeiro jogo; o texto também deve convidar quem quer publicar (liga com M18) | Pedido do Rafael (2026-10-17) |
| M20 | Score sinalizado no ranking | `SqlLeaderboard` só exclui `hidden_at IS NULL`: um score acima do limiar de anomalia (`flagged`) entra no ranking e fica em 1º lugar até um admin ocultá-lo | Ocultar automaticamente do ranking os scores sinalizados até a revisão do admin (ou um estado "em revisão" visível só para o admin), mantendo o registro e a auditoria | Análise de fraude do score no console (2026-10-20) |
| M21 | Aviso ao admin de score sinalizado | Um score sinalizado não gera nenhum aviso: o admin só o encontra se abrir o filtro "sinalizados" em Scores | Contador de sinalizados no painel (`/admin`) e notificação por e-mail ao admin quando um score for sinalizado (ligada à M3) | Análise de fraude do score no console (2026-10-20) |
