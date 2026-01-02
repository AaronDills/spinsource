#!/bin/bash
set -e

php artisan down || true

php artisan optimize:clear

npm ci
npm run build

php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan up || true
