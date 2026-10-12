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
