############################################
# Base
############################################
FROM serversideup/php:8.5-fpm-nginx AS base

USER root
RUN install-php-extensions intl bcmath
# serversideup rewrites REMOTE_ADDR from CF-Connecting-IP. Behind a proxy
# (Caddy, a load balancer) that hides the proxy hop from Laravel, which then
# ignores X-Forwarded-Proto and generates http URLs. Emptied rather than
# deleted so an include by name cannot break the config; TRUSTED_PROXIES
# does this job in the application instead.
RUN echo '# real_ip shim removed - TRUSTED_PROXIES handles proxies' \
    > /etc/nginx/server-opts.d/remoteip.conf
USER www-data

############################################
# Composer dependencies
############################################
FROM base AS composer-build

WORKDIR /var/www/html

# Temporary, until robertboes/patchbay is on Packagist: composer.json takes
# the package from ../patchbay, so every build is given it as a named context
#
#   docker build --build-context patchbay=../patchbay .
#
# Its source only; its own vendor directory is not needed here.
COPY --from=patchbay --chown=www-data:www-data composer.json /var/www/patchbay/composer.json
COPY --from=patchbay --chown=www-data:www-data src /var/www/patchbay/src
COPY --from=patchbay --chown=www-data:www-data config /var/www/patchbay/config
COPY --from=patchbay --chown=www-data:www-data database /var/www/patchbay/database
COPY --from=patchbay --chown=www-data:www-data resources /var/www/patchbay/resources

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
# The path repository installs the package as a symlink into this directory.
COPY --from=composer-build --chown=www-data:www-data /var/www/patchbay /var/www/patchbay

ENV PHP_OPCACHE_ENABLE=1 \
    SSL_MODE=off \
    HEALTHCHECK_PATH=/up
