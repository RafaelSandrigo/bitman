# Bitman

Plataforma para distribuir jogos de navegador vindos de repositórios Git, com
aprovação administrativa em múltiplas etapas, execução isolada e ranking por jogo.

**Documentação:** [docs/](docs/README.md) · **Plano:** [docs/plano.md](docs/plano.md)

> ⚠️ Este repositório roda no servidor de produção, compartilhado com outros projetos.
> Leia [docs/convencoes.md](docs/convencoes.md#operação-segura-no-servidor-de-produção) antes de executar qualquer comando.

```
bitman/
├── backend/    # API Laravel 13 (PHP 8.5)
├── frontend/   # SPA React + TypeScript (Vite)
├── docker/     # imagens e configurações (nginx, php, git-sandbox, egress-proxy, postgres)
├── tests/infra # testes de infraestrutura (origem de jogos, isolamento da sandbox)
└── docs/       # documentação e ADRs
```

## Primeira instalação

```bash
cp .env.example .env      # preencha os segredos (APP_KEY, senhas, SANDBOX_TOKEN)
make init                 # volumes e dependências de desenvolvimento (dentro dos containers)
make release              # gera as imagens do commit atual e sobe a stack (docs/implantacao.md)
make migrate              # migrations + roles/permissões
```

Primeiro admin: crie a conta no hub, confirme o e-mail (enviado pelo Resend; com `MAIL_MAILER=log`,
o link aparece em `make logs s=queue`) e rode:

```bash
make artisan c="user:grant-role seu@email admin"
```

## Dia a dia

```bash
make help     # todos os comandos
make check    # lint + análise estática + todos os testes (o mesmo que o CI)
make release  # coloca o commit atual no ar (o deploy automático faz o mesmo com origin/main)
make logs s=app
```

Hub: https://bitman.sandrigo.com.br ·
Jogos: https://bitman-games.sandrigo.com.br
