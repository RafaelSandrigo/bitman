# Game Hub

Monorepo com backend em Laravel e frontend em React.

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
