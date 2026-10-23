############################################
# Base
############################################
# serversideup/php with intl, bcmath, its real-IP shim removed and the shared
# env defaults; see https://github.com/RobertBoes/laravel-php.
FROM ghcr.io/robertboes/laravel-php:8.5.12@sha256:317d199502d79dc34d4401b7f886f00c4ff1f6ab7ae39276802994f01013b840 AS base

############################################
# Composer dependencies
############################################
FROM base AS composer-build

WORKDIR /var/www/html

COPY --chown=www-data:www-data composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/composer-cache,uid=33,gid=33 \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    composer install \
        --no-dev --no-interaction --no-progress \
        --optimize-autoloader --prefer-dist --no-scripts

COPY --chown=www-data:www-data . .
# Discovers the package providers and publishes Filament's assets into
# public/, which are generated rather than committed.
RUN composer dump-autoload --optimize \
    && composer run-script post-autoload-dump

############################################
# Runtime
############################################
FROM base AS deploy

COPY --from=composer-build --chown=www-data:www-data /var/www/html /var/www/html
