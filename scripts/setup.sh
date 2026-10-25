#!/usr/bin/env bash
set -euo pipefail
metribay_core="$(cd "$(dirname "$0")/.." && pwd)"
cd "$metribay_core/standalone"
mkdir -p bootstrap/cache storage/framework/{cache/data,sessions,views} storage/logs
composer install --no-interaction --prefer-dist
php ../scripts/initialize-host.php
php artisan migrate --force
cd "$metribay_core"
if [ "${1:-}" = "--build-assets" ]; then npm ci; npm run build; fi
if [ ! -f dist/app.js ] || [ ! -f dist/app.css ]; then
    printf '%s\n' 'Missing packaged assets. Run scripts/setup.sh --build-assets with Node24 installed.' >&2
    exit 1
fi
php standalone/artisan vendor:publish --tag=metribay-assets --force
printf '%s\n' 'Run: cd standalone && php artisan metribay:install'
