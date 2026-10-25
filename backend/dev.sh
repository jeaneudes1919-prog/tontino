#!/usr/bin/env bash
# API (8000) + file d'attente (paiements Mobile Money simulés) + planificateur
trap 'kill 0' EXIT
php artisan serve --port=8000 &
php artisan queue:listen --tries=1 --timeout=60 &
php artisan schedule:work &
wait
