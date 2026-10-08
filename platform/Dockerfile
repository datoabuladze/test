# Self-contained PREVIEW image: SQLite, PHP's built-in server, demo data seeded on first start.
# For trying the platform (locally or on a free host), not for production; see docs/DEPLOYMENT.md.
#   docker build -t nebulo-preview . && docker run -p 8080:8080 -e ADMIN_PASSWORD=ChooseAPassword123 nebulo-preview

FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources resources
COPY public public
COPY scripts scripts
RUN npm run build

FROM php:8.3-cli
RUN apt-get update && apt-get install -y --no-install-recommends libicu-dev libzip-dev libpng-dev libjpeg-dev libwebp-dev libfreetype6-dev unzip \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" intl zip gd pcntl opcache \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist
COPY . .
COPY --from=assets /app/public/build public/build
COPY --from=assets /app/public/vendor/ruffle public/vendor/ruffle
RUN composer dump-autoload --optimize --no-dev && rm -f .env public/hot \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/public database \
    && chmod +x docker/start.sh

ENV APP_ENV=production APP_DEBUG=false DB_CONNECTION=sqlite DB_DATABASE=/app/database/preview.sqlite \
    CACHE_STORE=file SESSION_DRIVER=file QUEUE_CONNECTION=sync LOG_CHANNEL=stderr \
    TRUSTED_PROXIES=* BLOCK_INDEXING=true PHP_CLI_SERVER_WORKERS=4 PORT=8080
EXPOSE 8080
CMD ["docker/start.sh"]
