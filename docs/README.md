# Documentação do Bitman

Plataforma para distribuir jogos de navegador feitos por amigos. Os jogos vêm de
repositórios Git, passam por aprovação administrativa em múltiplas etapas e rodam
isolados no navegador. Cada jogo tem o seu próprio ranking.

## O que ler e quando

| Documento | Para quê |
|---|---|
| [contexto-do-produto.md](contexto-do-produto.md) | Requisitos originais do produto (fonte da verdade do "o quê") |
| [plano.md](plano.md) | Roadmap em fases, entregas e critérios de pronto |
| [melhorias.md](melhorias.md) | Melhorias identificadas pelo caminho, ainda sem fase (backlog) |
| [arquitetura.md](arquitetura.md) | Containers, módulos, camadas e regras de dependência |
| [implantacao.md](implantacao.md) | Como a produção é atualizada: `make release`, deploy-hub, ativação e como reverter |
| [dominio.md](dominio.md) | Linguagem ubíqua, agregados, máquinas de estado e invariantes |
| [seguranca.md](seguranca.md) | Modelo de ameaças e mitigação de cada uma |
| [protocolo-do-jogo.md](protocolo-do-jogo.md) | Contrato para quem desenvolve jogos (arquivos, limites, envio de score) |
| [convencoes.md](convencoes.md) | Como trabalhamos: TDD, padrões de código, commits, definição de pronto |
| [adr/](adr/) | Decisões arquiteturais e seus porquês |

## Regras desta documentação

- **Documente decisões e regras, não código.** O que o código e os testes já dizem
  com clareza não se repete aqui.
- **Uma informação, um lugar.** Os outros documentos apontam para ele com um link.
- **A documentação muda junto com o código, no mesmo PR.** Um documento desatualizado é bug.
- **Decisão difícil de reverter vira ADR** (veja [adr/README.md](adr/README.md)).
