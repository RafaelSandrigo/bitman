# Bitman — Contexto do Produto

> Documento original de requisitos. Ele define **o quê** e **por quê**. O **como**
> está em [arquitetura.md](arquitetura.md), [dominio.md](dominio.md) e nos [ADRs](adr/).
> Alterações de requisito devem ser feitas aqui primeiro.

O projeto consiste em uma plataforma web para hospedagem e distribuição de jogos de navegador desenvolvidos por amigos. A ideia é criar um pequeno "hub de jogos", onde usuários possam acessar os jogos disponíveis, jogar diretamente pelo navegador e acompanhar rankings de pontuação de cada jogo.

A aplicação principal será desenvolvida utilizando Laravel no backend e React com TypeScript no frontend (ver [ADR 0001](adr/0001-laravel-13.md) sobre a versão do Laravel). O Laravel será responsável pela API, autenticação, usuários, gerenciamento dos jogos, repositórios, versões, aprovação, sessões de jogo e rankings. O React será responsável pela interface pública e pelo painel administrativo.

## Conceito dos jogos

Os jogos serão desenvolvidos externamente e disponibilizados através de repositórios Git. Em vez de realizar upload manual dos arquivos para a plataforma, o usuário responsável pelo jogo deverá apenas submeter o jogo, informando dados como nome, descrição, thumbnail, autor e a URL do repositório Git.

O usuário responsável pelo jogo não aprovará execuções Git, repositórios, commits, arquivos ou versões. Todas as etapas de análise, validação, aprovação, rejeição e publicação deverão ser realizadas pelo administrador.

O repositório será utilizado como fonte para importar uma versão do jogo, mas o fato de um repositório ser válido ou estar acessível não significa que seu conteúdo seja confiável.

O projeto deverá trabalhar com uma política de zero trust também para os repositórios Git.

Inicialmente, cada jogo deverá possuir obrigatoriamente estes três arquivos principais:

- `index.html`
- `style.css`
- `game.js`

A plataforma deverá validar a estrutura do repositório e garantir que os arquivos necessários existam de acordo com as regras do projeto.

Arquivos adicionais não deverão ser automaticamente considerados parte do jogo publicado. Caso futuramente seja necessário permitir imagens, fontes, áudio ou outros recursos, isso deverá ser definido explicitamente pelas regras do sistema.

## Repositório Git como fonte

Cada jogo deverá estar associado a um repositório Git (ex.: `https://github.com/usuario/meu-jogo`).

O usuário responsável pelo jogo informa esse repositório na plataforma como parte da submissão. A partir disso, o sistema cria uma solicitação para análise administrativa. O sistema realiza uma operação controlada de obtenção do código, mas nenhuma execução Git ou aprovação será realizada pelo usuário dono do jogo.

O processo não deve simplesmente executar um `git clone` arbitrário dentro do ambiente principal da aplicação. O repositório deve ser tratado como uma fonte externa não confiável.

O sistema deverá utilizar um ambiente controlado para operações como: `git clone`; `git fetch`; `git pull`; checkout de branches; checkout de commits; leitura do histórico; identificação do commit que será importado.

As operações Git devem ocorrer de maneira isolada e controlada, sem permitir que comandos ou parâmetros fornecidos pelo usuário sejam executados arbitrariamente no servidor.

A execução, aprovação ou rejeição dessas operações deverá ser controlada exclusivamente pelo administrador. O usuário responsável pelo jogo apenas fornece o link do Git durante a submissão.

A plataforma deve trabalhar preferencialmente com commits específicos, permitindo identificar exatamente qual estado do repositório foi analisado e aprovado pelo administrador.

## Dupla validação

Uma característica fundamental do projeto será possuir uma validação redundante em duas etapas.

A primeira etapa valida o repositório e o estado Git que será importado, mas a aprovação dessa etapa deverá ser realizada exclusivamente pelo administrador.

A segunda etapa valida individualmente os arquivos que serão disponibilizados, e a aprovação desses arquivos também deverá ser realizada exclusivamente pelo administrador.

O usuário dono do jogo não participa dessas aprovações. Sua única responsabilidade dentro desse fluxo é submeter o jogo com suas informações básicas e o link do repositório Git.

```
Usuário submete o jogo com nome, descrição e link do Git
        ↓
Validação do repositório
        ↓
Clone/fetch controlado
        ↓
Identificação do commit
        ↓
Validação da estrutura
        ↓
Administrador aprova ou rejeita a execução/importação Git
        ↓
Arquivos encontrados no commit
        ↓
Administrador analisa cada arquivo
        ↓
Administrador aprova ou rejeita individualmente os arquivos
        ↓
Nova versão publicada
```

Portanto, aprovar o repositório ou o commit não significa aprovar automaticamente os arquivos. O administrador deverá confirmar individualmente os arquivos que fazem parte da versão publicada. Essa redundância é intencional e deve ser tratada como requisito de segurança.

## Zero Trust

Todos os dados provenientes do Git devem ser considerados não confiáveis: URL do repositório; branch; tags; commits; nomes de arquivos; conteúdo dos arquivos; histórico; submodules; configurações do projeto; qualquer script ou arquivo adicional presente no repositório.

O sistema nunca deve executar o código do jogo durante o processo de importação. Em especial, o sistema não deve executar `npm install`, `npm run build`, `composer install`, scripts shell, Makefiles, executáveis ou qualquer outro comando originado do projeto importado, a menos que exista futuramente uma arquitetura específica e isolada para isso.

Para o MVP, o sistema deve apenas baixar, ler, validar e armazenar os arquivos permitidos.

## Restrições do repositório

O sistema deverá validar o conteúdo do repositório antes de permitir sua aprovação pelo administrador: repositório acessível; URL válida; protocolo permitido; existência do repositório; possibilidade de obter o commit; branch ou referência válida; existência dos arquivos obrigatórios; quantidade de arquivos permitidos; tamanho dos arquivos; caminhos dos arquivos; ausência de path traversal; ausência de arquivos proibidos; ausência de links simbólicos perigosos; ausência de submodules não autorizados.

O sistema também deve registrar exatamente qual commit foi utilizado na análise. A versão publicada deve estar vinculada a esse commit específico.

```
Repository: github.com/usuario/pacman
Branch:     main
Commit:     a84c7f91...
Status:     Pendente de aprovação do administrador
```

## Aprovação do Git

O administrador deverá possuir uma interface onde possa analisar e aprovar ou rejeitar a importação do repositório e a execução controlada das operações Git.

O usuário dono do jogo não poderá aprovar o commit, autorizar a execução Git, alterar o status da importação ou liberar o repositório para a próxima etapa.

```
Pac-Man
Repository: github.com/usuario/pacman
Branch:     main
Commit:     a84c7f91...

Arquivos encontrados:
✓ index.html
✓ style.css
✓ game.js
✗ README.md
✗ package.json

Status: Pendente de aprovação do administrador
[ Aprovar commit ]  [ Rejeitar ]
```

A aprovação nessa etapa significa apenas: *"O administrador confirmou que este repositório/commit é a fonte que deseja analisar."* Ela não significa que os arquivos foram aprovados para publicação.

## Aprovação individual dos arquivos

Depois da aprovação do repositório/commit pelo administrador, o sistema deverá apresentar os arquivos individualmente, cada um com visualização de conteúdo, status e ações de aprovar/rejeitar. A análise, aprovação ou rejeição de cada arquivo deverá ser feita exclusivamente pelo administrador.

Cada arquivo deverá possuir seu próprio estado de aprovação. A publicação somente poderá ocorrer quando todos os arquivos necessários para a execução do jogo estiverem individualmente aprovados pelo administrador.

## Integridade dos arquivos

A aprovação deve estar associada não apenas ao nome do arquivo, mas ao conteúdo exato que foi analisado. O sistema deverá calcular um hash SHA-256 do conteúdo do arquivo.

```
game.js
commit: a84c7f91
hash:   8f7d...abc
status: aprovado pelo administrador
```

Se o conteúdo do arquivo mudar, mesmo que o nome continue sendo `game.js`, a aprovação anterior não deverá continuar válida.

> **A aprovação pertence ao conteúdo exato analisado, e não simplesmente ao nome do arquivo.**

## Versionamento

Cada versão publicada deverá estar associada a um commit específico do repositório e às aprovações realizadas pelo administrador. Uma versão já publicada nunca deverá ser sobrescrita.

Caso o desenvolvedor faça alterações no Git, a plataforma deverá importar o novo estado como uma nova versão candidata, que deverá passar novamente pelas duas etapas de aprovação, ambas realizadas pelo administrador.

## Detecção de alterações

Quando uma nova versão for importada, o sistema deverá comparar os arquivos com a versão atualmente publicada (igual / alterado), dando ao administrador uma visão clara do que mudou. A lógica de aprovação continua vinculada ao conteúdo/hash exato.

## Estrutura interna de armazenamento

O Git é a fonte de origem, mas a aplicação não deve depender do repositório online para executar a versão publicada. Depois de aprovada, a plataforma deverá possuir uma cópia imutável dos arquivos aprovados (`games/{game_id}/versions/{n}/...`). O repositório Git não deve ser utilizado diretamente como ambiente de execução do jogo.

## Jogos como conteúdo não confiável

Os arquivos HTML, CSS e principalmente JavaScript devem ser tratados como código potencialmente malicioso. Os jogos não devem ser executados pelo servidor nem ter acesso à aplicação Laravel, ao filesystem, ao banco de dados ou a qualquer recurso interno.

Os jogos deverão ser servidos como arquivos estáticos através de um ambiente ou domínio separado e executados no navegador dentro de um contexto isolado (`iframe` com `sandbox` e políticas de segurança apropriadas).

## Execução dos jogos

A execução ocorre por meio de uma URI identificadora `/games/{identificadorDoJogo}` (slug ou outro identificador único, não necessariamente o nome). A página do jogo carrega a versão publicada dentro de um iframe isolado.

Somente jogos que concluíram o fluxo de aprovação e estão publicados podem ser executados. Versões candidatas, pendentes, rejeitadas ou bloqueadas não.

Políticas a considerar: CSP; sandbox do iframe; separação de domínio/origem; ausência de cookies da aplicação; ausência de acesso a recursos internos; restrição de requisições externas. No MVP, quanto menos permissões os jogos tiverem, melhor.

## Fluxo completo de uma nova versão

```
Desenvolvedor altera o jogo → Push para o Git
        ↓
Usuário dono submete o jogo (nome, descrição, URL do repositório)
        ↓
Administrador autoriza ou rejeita a execução/importação Git
        ↓
Fetch/clone controlado → identifica branch/commit
        ↓
Valida estrutura e arquivos permitidos → cria versão candidata
        ↓
Administrador aprova ou rejeita o repositório/commit
        ↓
Administrador analisa e aprova individualmente cada arquivo
        ↓
Sistema verifica hashes
        ↓
Todos os arquivos obrigatórios aprovados? SIM
        ↓
Versão publicada em /games/{identificadorDoJogo}
```

Caso qualquer etapa seja rejeitada, a versão não deverá ser disponibilizada.

## Informações de um jogo

Nome; slug/identificador da URI; descrição; thumbnail (usada na listagem pública); autor; URL do repositório; branch/referência; commit publicado; status; versão publicada; datas de criação e atualização.

## Página inicial

Listagem pública dos jogos publicados com thumbnail, nome, descrição resumida e acesso a `/games/{identificadorDoJogo}`. Somente jogos aprovados e publicados aparecem.

## Usuários

Usuários autenticados podem jogar. Administradores acessam o painel de moderação. O dono do jogo submete, mas não aprova nada. A autorização deve ser baseada em permissões/roles, e não apenas na existência de uma rota administrativa.

## Sistema de ranking

Ranking separado por jogo. Scores associados a usuário, jogo, versão, sessão da partida, pontuação e data/hora. Deve ser possível consultar o melhor resultado de cada jogador e o histórico de partidas.

## Comunicação entre jogo e plataforma

```
Usuário acessa /games/{id} → Hub cria sessão → jogo carregado → usuário joga
→ partida termina → score enviado → Laravel valida sessão → score registrado → ranking atualizado
```

O score enviado pelo navegador não é absolutamente confiável. Mecanismos: sessão de jogo; autenticação; autorização; rate limiting; prevenção de replay; limites e validações de score; logs; detecção de anomalias. No MVP não é necessário garantir matematicamente o score, mas a limitação deve estar explícita na arquitetura.

## API

Recursos para autenticação, listagem e detalhes de jogos, ranking, sessão de jogo, envio de score, histórico do usuário, administração, submissão de jogos e importação de repositórios. Ações de aprovação, rejeição, publicação e controle Git apenas para administradores.

## Painel administrativo

Visualizar jogos/submissões/repositórios/execuções Git/commits/versões/arquivos pendentes; jogos publicados, rejeitados e bloqueados; informações do autor; histórico de versões, commits e aprovações. Para cada importação, o commit e os arquivos encontrados; cada arquivo visualizável e aprovável individualmente. Registro de auditoria das ações administrativas.

## Segurança das operações Git

Não montar comandos com strings do usuário. Evitar: command injection; path traversal; URLs maliciosas; acesso a recursos internos via URL; protocolos não permitidos; submodules perigosos; hooks executáveis; scripts automáticos; repositórios excessivamente grandes; consumo excessivo de CPU, memória ou armazenamento. Execução em ambiente controlado com limites de recursos. O conteúdo é tratado como dados até a aprovação.

## Auditoria

O sistema deve responder: quem submeteu o jogo; quem informou a URL; qual URL; qual commit; quando foi o fetch; quem autorizou/rejeitou a execução Git; quem aprovou o commit; quais arquivos foram encontrados; qual arquivo foi aprovado, por quem, com qual hash e quando; qual versão foi publicada; quem rejeitou o quê; qual versão está publicada; qual URI foi usada. Aprovações e rejeições registram administrador, data, motivo (quando aplicável) e estado analisado.

## Princípios gerais

1. Zero trust para repositórios e arquivos
2. Isolamento entre jogos e aplicação
3. Importação controlada através de Git
4. Aprovação administrativa da execução Git e do commit antes da análise dos arquivos
5. Aprovação administrativa individual de cada arquivo
6. Integridade através de hash do conteúdo aprovado
7. Versionamento baseado em commits
8. Nenhuma versão publicada deve ser sobrescrita
9. Execução dos jogos somente em ambiente isolado
10. Disponibilização através de uma URI identificadora
11. Simplicidade no MVP
12. Arquitetura preparada para evolução
13. Auditoria completa das ações administrativas
14. Responsabilidade do usuário limitada à submissão
15. Controle administrativo de todas as aprovações, rejeições e publicações

A plataforma é um **sistema de distribuição de conteúdo potencialmente não confiável**, e não um CRUD de jogos. O Git é fonte de distribuição e versionamento, mas não é automaticamente confiável.

```
REPOSITÓRIO / COMMIT → APROVAÇÃO DO ADMIN
ARQUIVOS INDIVIDUAIS → APROVAÇÃO DO ADMIN
VALIDAÇÃO DE HASH → PUBLICAÇÃO PELO ADMIN → /games/{identificadorDoJogo}
```
