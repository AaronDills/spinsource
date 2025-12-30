#!/bin/bash

npm ci
npm run build
php artisan migrate --force
