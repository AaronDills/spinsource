#!/bin/bash
set -e

php artisan down || true

php artisan optimize:clear

# Verify Vite build assets exist (built in .cloud/build.sh)
if [ ! -f "public/build/manifest.json" ]; then
    echo "ERROR: Vite manifest not found! Build phase may have failed."
    echo "Expected: public/build/manifest.json"
    ls -la public/build/ 2>/dev/null || echo "public/build/ directory does not exist"
    exit 1
fi

echo "=== Vite manifest found ==="
cat public/build/manifest.json

php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan up || true
