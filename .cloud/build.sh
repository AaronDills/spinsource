#!/bin/bash
set -e

echo "=== Installing Node dependencies ==="
npm ci

echo "=== Building frontend assets ==="
npm run build

echo "=== Verifying build output ==="
ls -la public/build/
cat public/build/manifest.json

echo "=== Build phase complete ==="
