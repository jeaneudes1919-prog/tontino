# Tontino : frontend (React)

Interface de la plateforme de tontines et de coopérative d'épargne : tableau de bord, tontines avec tirage au sort animé, cotisations et paiement Mobile Money simulé, portefeuille avec grand livre, prêts avec simulateur d'échéancier, garants et décisions, annonces, réunions et présences, rapports PDF/CSV, journal d'audit.

**Stack** : React 18, Vite 5, Tailwind CSS 3, Framer Motion, Recharts, React Router, Axios.

## Lancement
Le backend (`05a-tontino-backend`) doit tourner sur `http://localhost:8000` (`bash dev.sh` dans `backend/`).

```bash
npm install
npm run dev     # http://localhost:5173 (les appels /api sont redirigés vers le port 8000)
npm run build
```

Comptes de démonstration (mot de passe `Tontino2026!`) : `admin@tontino.dev`, `tresor@tontino.dev`, `membre@tontino.dev`.

## Parcours à essayer
1. Connectez-vous en administratrice, ouvrez la tontine « Fêtes de fin d'année » et lancez le **tirage au sort**, puis activez-la.
2. En membre, payez une cotisation par **Mobile Money** (un numéro finissant par 00 est refusé) ou avec le portefeuille.
3. Faites une **demande de prêt** avec le simulateur, puis traitez-la en trésorier (le trésorier ne peut pas approuver son propre prêt).
