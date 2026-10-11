# Bitman

Plataforma para distribuir jogos de navegador vindos de repositórios Git, com
aprovação administrativa em múltiplas etapas, execução isolada e ranking por jogo.

**Documentação:** [docs/](docs/README.md) · **Plano:** [docs/plano.md](docs/plano.md)

Monorepo com backend em Laravel 13 e frontend em React + TypeScript.

```
game-hub/
├── backend/   # API Laravel
└── frontend/  # SPA React (Vite + TypeScript)
```

## Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve        # http://localhost:8000
```

## Frontend

```bash
cd frontend
npm install
npm run dev              # http://localhost:5173
```
