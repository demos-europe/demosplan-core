# due to BSI SYS.1.6.A6 we need to use excplicit versions
FROM demosdeutschland/demosplan-base:latest AS base

ARG PROJECT_NAME
# ENV vars needs to be set to build the assets
ENV PROJECT_PREFIX="$PROJECT_NAME" \
    EXTERNAL_LINK_DIPLAN_COCKPIT=http://temporary.de \
    EXTERNAL_LINK_DIPLAN_PORTAL=http://temporary.de \
    TZ=Europe/Berlin

# Setup timezone
RUN ln -snf /usr/share/zoneinfo/"$TZ" /etc/localtime && echo "$TZ"> /etc/timezone
COPY zzz-dplan.ini /etc/php/8.2/fpm/conf.d/zzz-dplan.ini
COPY zzz-dplan-cli.ini /etc/php/8.2/cli/conf.d/zzz-dplan-cli.ini
COPY php-fpm_www.conf /etc/php/8.2/fpm/pool.d/zzz-www.conf

RUN --mount=type=cache,target=/root/.npm,sharing=locked \
    --mount=type=cache,target=/var/www/.cache,sharing=locked \
    npm install --ignore-scripts -g n && \
# Install specific node version first
    n 20.18.1 && \
    npm install --ignore-scripts -g corepack && \
    corepack enable && yarn set version 4.2.2

WORKDIR /srv/www

RUN mkdir -p /opt/uploads && \
    mkdir /opt/config && \
    mkdir /srv/www/vendor && \
    mkdir -p /srv/www/client/js/generated && \
    mkdir -p /srv/www/tmp && \
    chown -R www-data:www-data /opt/uploads && \
    chown -R www-data:www-data /srv/www/client/js/generated && \
    chown -R www-data:www-data /opt/config && \
    chown -R www-data:www-data /srv/www && \
    mkdir -p /srv/www/projects/"$PROJECT_NAME"/app/config && \
# asset build only needs the parameters.yml without content
    echo "parameters:" > /srv/www/projects/"$PROJECT_NAME"/app/config/parameters.yml

# =============================================================================
# Stage: Dependencies - Only composer splits on BUILD_MODE, yarn is shared
# =============================================================================
FROM base AS dependencies-dev

USER www-data
WORKDIR /srv/www
COPY composer.json composer.lock package.json yarn.lock .yarnrc.yml ./

RUN --mount=type=cache,target=/var/www/.yarn,uid=33,gid=33,sharing=locked \
    --mount=type=cache,target=/var/www/.cache,uid=33,gid=33,sharing=locked \
    --mount=type=cache,target=/var/www/.composer/cache,uid=33,gid=33,sharing=locked \
    yarn install --immutable && \
    yarn cache clean && \
    rm -rf /srv/www/.cache/* && \
    composer install -o --prefer-dist --no-progress

FROM base AS dependencies-prod

USER www-data
WORKDIR /srv/www
COPY composer.json composer.lock package.json yarn.lock .yarnrc.yml ./

RUN --mount=type=cache,target=/var/www/.yarn,uid=33,gid=33,sharing=locked \
    --mount=type=cache,target=/var/www/.cache,uid=33,gid=33,sharing=locked \
    --mount=type=cache,target=/var/www/.composer/cache,uid=33,gid=33,sharing=locked \
    yarn install --immutable && \
    yarn cache clean && \
    rm -rf /srv/www/.cache/* && \
    composer install -o --prefer-dist --no-dev --no-progress

# =============================================================================
# Stage: Build - Select dependency stage based on target
# =============================================================================
FROM dependencies-dev AS build-dev

ARG PROJECT_NAME
ENV PROJECT_PREFIX="$PROJECT_NAME"

USER root
COPY . /srv/www

# set permissions needed during build
RUN mkdir -p /srv/www/projects/"$PROJECT_NAME"/web/js && \
    mkdir -p /srv/www/projects/"$PROJECT_NAME"/web/fonts && \
    mkdir -p /srv/www/projects/"$PROJECT_NAME"/web/images && \
    mkdir -p /srv/www/projects/"$PROJECT_NAME"/web/video && \
    mkdir -p /srv/www/var/cache && \
    mkdir -p /srv/www/var/log && \
    mkdir -p /srv/www/var/certs && \
    mkdir -p /srv/www/client/css/generated && \
    chown -R www-data /srv/www/projects/"$PROJECT_NAME"/web && \
    chown -R www-data /srv/www/var/cache && \
    chown -R www-data /srv/www/var/log && \
    chown -R www-data /srv/www/var/certs && \
    chown -R www-data /srv/www/client/css/generated && \
# define doctrine in memory database for the asset build
    mv /srv/www/config/packages/doctrine.yaml /tmp/doctrine.yaml
COPY doctrine_inmemory.yaml /srv/www/config/packages/doctrine.yaml
COPY parameters_build.yml /srv/www/projects/"$PROJECT_NAME"/app/config/parameters.yml

USER www-data
RUN --mount=type=secret,id=envlocal,target=/srv/www/.env.local,uid=33 \
    --mount=type=cache,target=/var/www/.composer/cache,uid=33,gid=33,sharing=locked \
    --mount=type=cache,target=/var/www/.yarn,uid=33,gid=33,sharing=locked \
    --mount=type=cache,target=/var/www/.cache,uid=33,gid=33,sharing=locked \
    ACTIVE_PROJECT="$PROJECT_NAME" bin/console dplan:addon:autoinstall -e dev --no-debug || true && \
    ./fe build "$PROJECT_NAME" && \
    rm -rf /srv/www/node_modules && \
    rm -rf /srv/www/addons/cache/*/node_modules && \
    rm -rf /srv/www/addonZips && \
    rm -rf /srv/www/.cache

USER root
RUN mv /tmp/doctrine.yaml /srv/www/config/packages/doctrine.yaml
COPY projects/"$PROJECT_NAME"/app/config/parameters_docker.yml /opt/config/parameters.yml

RUN rm /srv/www/projects/"$PROJECT_NAME"/app/config/parameters.yml && \
    ln -s /opt/config/parameters.yml /srv/www/projects/"$PROJECT_NAME"/app/config/parameters.yml && \
    touch /opt/config/.env.local && \
    ln -s /opt/config/.env.local /srv/www/.env.local && \
    mkdir -p /srv/www/projects/"$PROJECT_NAME"/web/uploads/files && \
    chmod -R 775 /srv/www/projects/"$PROJECT_NAME"/web/uploads && \
    mkdir -p /opt/uploads && \
    chown -R root:root /srv/www/projects/"$PROJECT_NAME"/web && \
    chown -R www-data /opt/uploads && \
    chown -R www-data /srv/www/projects/"$PROJECT_NAME"/web/uploads && \
    touch /var/log/php8.2-fpm.log && chown www-data:www-data /var/log/php8.2-fpm.log

FROM dependencies-prod AS build-prod

ARG PROJECT_NAME
ENV PROJECT_PREFIX="$PROJECT_NAME"

USER root
COPY . /srv/www

# Remove app_dev.php in production mode
RUN rm -f /srv/www/projects/"$PROJECT_NAME"/web/app_dev.php

# set permissions needed during build
RUN mkdir -p /srv/www/projects/"$PROJECT_NAME"/web/js && \
    mkdir -p /srv/www/projects/"$PROJECT_NAME"/web/fonts && \
    mkdir -p /srv/www/projects/"$PROJECT_NAME"/web/images && \
    mkdir -p /srv/www/projects/"$PROJECT_NAME"/web/video && \
    mkdir -p /srv/www/var/cache && \
    mkdir -p /srv/www/var/log && \
    mkdir -p /srv/www/var/certs && \
    mkdir -p /srv/www/client/css/generated && \
    chown -R www-data /srv/www/projects/"$PROJECT_NAME"/web && \
    chown -R www-data /srv/www/var/cache && \
    chown -R www-data /srv/www/var/log && \
    chown -R www-data /srv/www/var/certs && \
    chown -R www-data /srv/www/client/css/generated && \
# define doctrine in memory database for the asset build
    mv /srv/www/config/packages/doctrine.yaml /tmp/doctrine.yaml
COPY doctrine_inmemory.yaml /srv/www/config/packages/doctrine.yaml
COPY parameters_build.yml /srv/www/projects/"$PROJECT_NAME"/app/config/parameters.yml

USER www-data
RUN --mount=type=secret,id=envlocal,target=/srv/www/.env.local,uid=33 \
    --mount=type=cache,target=/var/www/.composer/cache,uid=33,gid=33,sharing=locked \
    --mount=type=cache,target=/var/www/.yarn,uid=33,gid=33,sharing=locked \
    --mount=type=cache,target=/var/www/.cache,uid=33,gid=33,sharing=locked \
    ACTIVE_PROJECT="$PROJECT_NAME" bin/console dplan:addon:autoinstall -e prod --no-debug || true && \
    ./fe build "$PROJECT_NAME" --prod && \
    rm -rf /srv/www/node_modules && \
    rm -rf /srv/www/addons/cache/*/node_modules && \
    rm -rf /srv/www/addonZips && \
    rm -rf /srv/www/.cache

USER root
RUN mv /tmp/doctrine.yaml /srv/www/config/packages/doctrine.yaml
COPY projects/"$PROJECT_NAME"/app/config/parameters_docker.yml /opt/config/parameters.yml

RUN rm /srv/www/projects/"$PROJECT_NAME"/app/config/parameters.yml && \
    ln -s /opt/config/parameters.yml /srv/www/projects/"$PROJECT_NAME"/app/config/parameters.yml && \
    touch /opt/config/.env.local && \
    ln -s /opt/config/.env.local /srv/www/.env.local && \
    mkdir -p /srv/www/projects/"$PROJECT_NAME"/web/uploads/files && \
    chmod -R 775 /srv/www/projects/"$PROJECT_NAME"/web/uploads && \
    mkdir -p /opt/uploads && \
    chown -R root:root /srv/www/projects/"$PROJECT_NAME"/web && \
    chown -R www-data /opt/uploads && \
    chown -R www-data /srv/www/projects/"$PROJECT_NAME"/web/uploads && \
    touch /var/log/php8.2-fpm.log && chown www-data:www-data /var/log/php8.2-fpm.log

# =============================================================================
# Stage: FPM Runtime
# =============================================================================
FROM demosdeutschland/demosplan-base:latest AS fpm-base

ARG PROJECT_NAME
ENV PROJECT_PREFIX="$PROJECT_NAME" \
    EXTERNAL_LINK_DIPLAN_COCKPIT=http://temporary.de \
    EXTERNAL_LINK_DIPLAN_PORTAL=http://temporary.de \
    TZ=Europe/Berlin

RUN mkdir -p /opt/config && \
    chown -R www-data:www-data /opt/config

COPY zzz-dplan.ini /etc/php/8.2/fpm/conf.d/zzz-dplan.ini
COPY zzz-dplan-cli.ini /etc/php/8.2/cli/conf.d/zzz-dplan-cli.ini
COPY php-fpm_www.conf /etc/php/8.2/fpm/pool.d/zzz-www.conf
COPY php-fpm.conf /etc/php/8.2/fpm/php-fpm.conf

WORKDIR /srv/www

FROM fpm-base AS fpm-dev

ARG PROJECT_NAME

COPY --from=build-dev /srv/www /srv/www
COPY --from=build-dev /opt/config/parameters.yml /opt/config/parameters.yml

RUN rm -rf /srv/www/var/cache/* && \
    rm -rf /srv/www/var/log/* && \
    rm -rf /srv/www/projects/"$PROJECT_NAME"/{files,fonts,images,js,video,img,pdf} && \
    mkdir -p /srv/www/tmp && \
    chown -R www-data:www-data /srv/www/tmp && \
    touch /srv/www/var/certs/ca.crt && \
    ln -s /srv/www/var/certs/ca.crt /usr/local/share/ca-certificates/ca.crt && \
    rm /etc/php/8.2/fpm/pool.d/www.conf

USER www-data
CMD ["/usr/sbin/php-fpm8.2", "-F"]

FROM fpm-base AS fpm-prod

ARG PROJECT_NAME

COPY --from=build-prod /srv/www /srv/www
COPY --from=build-prod /opt/config/parameters.yml /opt/config/parameters.yml

RUN rm -rf /srv/www/var/cache/* && \
    rm -rf /srv/www/var/log/* && \
    rm -rf /srv/www/projects/"$PROJECT_NAME"/{files,fonts,images,js,video,img,pdf} && \
    mkdir -p /srv/www/tmp && \
    chown -R www-data:www-data /srv/www/tmp && \
    touch /srv/www/var/certs/ca.crt && \
    ln -s /srv/www/var/certs/ca.crt /usr/local/share/ca-certificates/ca.crt && \
    rm /etc/php/8.2/fpm/pool.d/www.conf

USER www-data
CMD ["/usr/sbin/php-fpm8.2", "-F"]

# =============================================================================
# Stage: Nginx Runtime
# =============================================================================
FROM nginxinc/nginx-unprivileged:alpine3.22-slim AS nginx-dev

ARG PROJECT_NAME
ENV PHP_FPM_BETEILIGUNG_SERVICE=beteiligung

COPY nginx.conf.template-dev /etc/nginx/templates/default.conf.template

WORKDIR /srv/www
COPY --from=build-dev /srv/www/projects/"$PROJECT_NAME"/web /srv/www/projects/"$PROJECT_NAME"/web

RUN sed -i '/^}/i\    text/csv csv;' /etc/nginx/mime.types

FROM nginxinc/nginx-unprivileged:alpine3.22-slim AS nginx-prod

ARG PROJECT_NAME
ENV PHP_FPM_BETEILIGUNG_SERVICE=beteiligung

COPY nginx.conf.template /etc/nginx/templates/default.conf.template

WORKDIR /srv/www
COPY --from=build-prod /srv/www/projects/"$PROJECT_NAME"/web /srv/www/projects/"$PROJECT_NAME"/web

RUN sed -i '/^}/i\    text/csv csv;' /etc/nginx/mime.types

# =============================================================================
# Legacy aliases
# =============================================================================
FROM fpm-prod AS fpm
FROM nginx-prod AS nginx
