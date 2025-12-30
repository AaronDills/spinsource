# =============================================================================
# Multi-stage Dockerfile for Laravel Application
# Supports both development and production environments
# =============================================================================

# -----------------------------------------------------------------------------
# Stage 1: Base PHP image with extensions
# -----------------------------------------------------------------------------
FROM php:8.4-fpm-alpine AS base

# Install system dependencies
RUN apk add --no-cache \
    curl \
    git \
    zip \
    unzip \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    mysql-client \
    supervisor \
    nginx

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache

# Install Redis extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Copy PHP configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# Set working directory
WORKDIR /var/www/html

# -----------------------------------------------------------------------------
# Stage 2: Composer dependencies
# -----------------------------------------------------------------------------
FROM composer:2 AS composer

WORKDIR /app

COPY composer.json composer.lock ./

# Install dependencies (no dev for production)
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# -----------------------------------------------------------------------------
# Stage 3: Node.js for frontend assets
# -----------------------------------------------------------------------------
FROM node:20-alpine AS node

WORKDIR /app

COPY package.json package-lock.json* ./

RUN npm ci

# Copy all files needed for Vite/Tailwind build
COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
COPY tailwind.config.js* ./
COPY postcss.config.js* ./

RUN npm run build

# -----------------------------------------------------------------------------
# Stage 4: Development image
# -----------------------------------------------------------------------------
FROM base AS development

# Install development dependencies
RUN apk add --no-cache \
    nodejs \
    npm

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy application code
COPY . .

# Install all dependencies including dev
RUN composer install \
    --no-interaction \
    --no-progress \
    --prefer-dist

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# Create supervisor log directory
RUN mkdir -p /var/log/supervisor

# Copy development configurations
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisor/supervisord.dev.conf /etc/supervisord.conf

EXPOSE 80 5173

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]

# -----------------------------------------------------------------------------
# Stage 5: Production image
# -----------------------------------------------------------------------------
FROM base AS production

# Copy Composer dependencies from composer stage
COPY --from=composer /app/vendor ./vendor

# Copy built assets from node stage
COPY --from=node /app/public/build ./public/build

# Copy application code
COPY --chown=www-data:www-data . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# Optimize Laravel for production
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# Create supervisor log directory
RUN mkdir -p /var/log/supervisor

# Copy production configurations
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisor/supervisord.conf /etc/supervisord.conf

# Health check
HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
