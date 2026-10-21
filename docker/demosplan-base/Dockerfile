# due to BSI SYS.1.6.A6 we need to use excplicit versions
#
# Do not source PHP from packages.sury.org: its php-fpm has DNS telemetry compiled in
# (telemetry.sury.org:53) with no opt-out.
FROM php:8.2.35-fpm-bookworm AS base

# Extensions that are not already compiled into the base image.
COPY --link --from=mlocati/php-extension-installer:2.11.17 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
        apcu bcmath bz2 calendar exif ffi ftp gd gettext intl mysqli opcache pcntl \
        pdo_mysql shmop soap sockets sysvmsg sysvsem sysvshm xdebug xsl zip \
    && rm -f /usr/local/bin/install-php-extensions \
# The base image ships only php.ini templates; without this display_errors defaults to On.
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
# client/fe/config/projectConfig.js spawns a hardcoded /usr/bin/php and exits 0 when that fails.
    && ln -s /usr/local/bin/php /usr/bin/php

# Tools needed to build and test the application
RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt/lists,sharing=locked \
    apt-get update -y \
    && apt-get --no-install-recommends install acl bind9-host ca-certificates curl \
       default-mysql-client gettext-base lnav npm unzip vim vim-tiny zsh -y \
    && mkdir -p /opt/uploads
COPY --link --from=composer:2.9.8 /usr/bin/composer /usr/bin/composer

# Single conf.d for every SAPI
COPY --link zzz-dplan-cli.ini /usr/local/etc/php/conf.d/zzz-dplan-cli.ini

WORKDIR /srv/www

CMD ["sleep", "infinity"]
