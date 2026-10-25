# Tontino

Plateforme de gestion d'une coopérative d'épargne et de tontines : membres, tontines rotatives, cotisations, portefeuille, paiements Mobile Money simulés, prêts, rapports PDF/CSV et journal d'audit.

## Structure

- `backend/` : API REST Laravel 11, Sanctum, SQLite, dompdf.
- `frontend/` : interface React 18, Vite 5, Tailwind CSS 3, Framer Motion, Recharts.

## Lancement

### Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
bash dev.sh
```

API : `http://localhost:8000/api/v1`

### Frontend

```bash
cd frontend
npm install
npm run dev
```

Interface : `http://localhost:5173`

## Comptes de démonstration

Mot de passe : `Tontino2026!`

- `admin@tontino.dev`
- `tresor@tontino.dev`
- `membre@tontino.dev`

## Tests

```bash
cd backend
php artisan test
```
