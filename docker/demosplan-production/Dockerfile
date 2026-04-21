# due to BSI SYS.1.6.A6 we need to use excplicit versions
FROM debian:bookworm-20260406-slim AS build

# Install all dependencies needed for both build and fpm stages
RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt/lists,sharing=locked \
    apt-get update -y \
    && apt-get install --no-install-recommends  lsb-release ca-certificates curl gpg -y \
    && curl -sSo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb \
    && dpkg -i /tmp/debsuryorg-archive-keyring.deb \
    && rm -f /tmp/debsuryorg-archive-keyring.deb \
    && echo "deb https://packages.sury.org/php/ bookworm main" > /etc/apt/sources.list.d/deb.sury.org.list \
    && apt-get update -y \
    && apt-get --no-install-recommends install acl bind9-host ca-certificates composer curl  \
    gettext-base npm php8.3 php8.3-apcu php8.3-bcmath php8.3-bz2 php8.3-cli  \
    php8.3-common php8.3-curl php8.3-excimer php8.3-fpm php8.3-gd php8.3-intl php8.3-mbstring php8.3-mysqli php8.3-soap  \
    php8.3-sqlite3 php8.3-xml php8.3-zip unzip -y \
    && update-alternatives --set php /usr/bin/php8.3 \
    && mkdir -p /opt/uploads
COPY --link zzz-dplan-cli.ini /etc/php/8.3/cli/conf.d/zzz-dplan-cli.ini

ARG PROJECT_NAME
ARG BUILD_MODE=prod
ARG COMMIT_SHA_CORE=""
ARG COMMIT_SHA_PROJECT=""
# ENV vars needs to be set to build the assets
ENV PROJECT_PREFIX=$PROJECT_NAME \
    EXTERNAL_LINK_DIPLAN_COCKPIT=http://temporary.de \
    EXTERNAL_LINK_DIPLAN_PORTAL=http://temporary.de \
    BUILD_MODE=$BUILD_MODE \
    TZ=Europe/Berlin

# Setup timezone
RUN ln -snf /usr/share/zoneinfo/"$TZ" /etc/localtime && echo "$TZ"> /etc/timezone
COPY --link zzz-dplan.ini /etc/php/8.3/fpm/conf.d/zzz-dplan.ini
COPY --link zzz-dplan-cli.ini /etc/php/8.3/cli/conf.d/zzz-dplan-cli.ini
COPY --link php-fpm_www.conf /etc/php/8.3/fpm/pool.d/zzz-www.conf

RUN --mount=type=cache,target=/root/.npm,sharing=locked \
    --mount=type=cache,target=/var/www/.cache,sharing=locked \
    npm install --ignore-scripts -g n && \
# Install specific node version first \
    n 24.11.1 && \
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
    mkdir -p /srv/www/projects/$PROJECT_NAME/app/config && \
# asset build only needs the parameters.yml without content \
    echo "parameters:" > /srv/www/projects/$PROJECT_NAME/app/config/parameters.yml

USER www-data
WORKDIR /srv/www
# Install composer and yarn before copying the project files to make use of the docker cache
COPY --link composer.json composer.lock package.json yarn.lock .yarnrc.yml ./
# do not use --no-scripts, as we do not need all the aws stuff
RUN --mount=type=cache,target=/var/www/.composer/cache,uid=33,gid=33,sharing=locked \
    --mount=type=cache,target=/var/www/.yarn,uid=33,gid=33,sharing=locked \
    --mount=type=cache,target=/var/www/.cache,uid=33,gid=33,sharing=locked \
    if [ "$BUILD_MODE" = "dev" ]; then \
        composer install -o --prefer-dist --no-progress; \
    else \
        composer install -o --prefer-dist --no-dev --no-progress; \
    fi && \
    yarn install --immutable && \
    yarn cache clean && \
    rm -rf /srv/www/.cache/*

USER root
COPY --link . /srv/www

# Remove app_dev.php in production mode
RUN if [ "$BUILD_MODE" = "prod" ]; then \
        rm -f /srv/www/projects/$PROJECT_NAME/web/app_dev.php; \
    fi

# set permissions needed during build
RUN mkdir -p /srv/www/projects/$PROJECT_NAME/web/js && \
    mkdir -p /srv/www/projects/$PROJECT_NAME/web/fonts && \
    mkdir -p /srv/www/projects/$PROJECT_NAME/web/images && \
    mkdir -p /srv/www/projects/$PROJECT_NAME/web/video && \
    mkdir -p /srv/www/var/cache && \
    mkdir -p /srv/www/var/log && \
    mkdir -p /srv/www/var/certs && \
    mkdir -p /srv/www/client/css/generated && \
    chown -R www-data /srv/www/projects/$PROJECT_NAME/web && \
    chown -R www-data /srv/www/var/cache && \
    chown -R www-data /srv/www/var/log && \
    chown -R www-data /srv/www/var/certs && \
    chown -R www-data /srv/www/client/css/generated && \
# define doctrine in memory database for the asset build \
    mv /srv/www/config/packages/doctrine.yaml /tmp/doctrine.yaml
COPY --link doctrine_inmemory.yaml /srv/www/config/packages/doctrine.yaml
COPY --link parameters_build.yml /srv/www/projects/$PROJECT_NAME/app/config/parameters.yml

USER www-data
# mount .env.local as a secret to ensure that addons can be installed while keeping the layer clean.
# during addon install the cache is deleted and the command "fails" because it does not find the cache
# the command is executed with "|| true" to prevent the build from failing
RUN --mount=type=secret,id=envlocal,target=/srv/www/.env.local,uid=33 \
    --mount=type=cache,target=/var/www/.composer/cache,uid=33,gid=33,sharing=locked \
    --mount=type=cache,target=/var/www/.yarn,uid=33,gid=33,sharing=locked \
    --mount=type=cache,target=/var/www/.cache,uid=33,gid=33,sharing=locked \
    ACTIVE_PROJECT=$PROJECT_NAME bin/console dplan:addon:autoinstall -e ${BUILD_MODE} --no-debug || true && \
    if [ "$BUILD_MODE" = "dev" ]; then \
        ./fe build $PROJECT_NAME; \
    else \
        ./fe build $PROJECT_NAME --prod; \
    fi && \
    rm -rf /srv/www/node_modules && \
    rm -rf /srv/www/addons/cache/*/node_modules && \
    rm -rf /srv/www/addonZips && \
    rm -rf /srv/www/.cache

USER root
RUN mv /tmp/doctrine.yaml /srv/www/config/packages/doctrine.yaml
COPY --link projects/$PROJECT_NAME/app/config/parameters_docker.yml /opt/config/parameters.yml

RUN rm /srv/www/projects/$PROJECT_NAME/app/config/parameters.yml && \
    ln -s /opt/config/parameters.yml /srv/www/projects/$PROJECT_NAME/app/config/parameters.yml && \
    touch /opt/config/.env.local && \
    ln -s /opt/config/.env.local /srv/www/.env.local && \
    mkdir -p /srv/www/projects/$PROJECT_NAME/web/uploads/files && \
    chmod -R 775 /srv/www/projects/$PROJECT_NAME/web/uploads && \
    mkdir -p /opt/uploads && \
    chown -R root:root /srv/www/projects/$PROJECT_NAME/web && \
    chown -R www-data /opt/uploads && \
    chown -R www-data /srv/www/projects/$PROJECT_NAME/web/uploads && \
    touch /var/log/php8.3-fpm.log && chown www-data:www-data /var/log/php8.3-fpm.log

# Runtime stage - starts fresh WITHOUT build tools (Node.js, npm, composer)
FROM debian:bookworm-20260406-slim AS fpm

ARG PROJECT_NAME

LABEL org.opencontainers.image.source="https://github.com/demos-europe/demosplan-core"

ENV PROJECT_PREFIX=$PROJECT_NAME \
    EXTERNAL_LINK_DIPLAN_COCKPIT=http://temporary.de \
    EXTERNAL_LINK_DIPLAN_PORTAL=http://temporary.de \
    TZ=Europe/Berlin

# Install only runtime dependencies (no npm, no composer, no build tools)
RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt/lists,sharing=locked \
    apt-get update -y \
    && apt-get install --no-install-recommends lsb-release ca-certificates curl gpg -y \
    && curl -sSo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb \
    && dpkg -i /tmp/debsuryorg-archive-keyring.deb \
    && rm -f /tmp/debsuryorg-archive-keyring.deb \
    && echo "deb https://packages.sury.org/php/ bookworm main" > /etc/apt/sources.list.d/deb.sury.org.list \
    && apt-get update -y \
    && apt-get --no-install-recommends install acl bind9-host ca-certificates curl \
       default-mysql-client gettext-base libfcgi-bin lnav php8.3 php8.3-apcu php8.3-bcmath php8.3-bz2 php8.3-cli \
       php8.3-common php8.3-curl php8.3-excimer php8.3-fpm php8.3-gd php8.3-intl php8.3-mbstring \
       php8.3-mysqli php8.3-soap php8.3-sqlite3 php8.3-xml php8.3-zip unzip vim-tiny -y \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
    && update-alternatives --set php /usr/bin/php8.3 \
    && mkdir -p /opt/uploads \
    && ln -snf /usr/share/zoneinfo/"$TZ" /etc/localtime && echo "$TZ"> /etc/timezone

# Copy PHP configuration. --link lets these layers stay independent from each other so
# touching one ini file doesn't invalidate the rest.
COPY --link zzz-dplan.ini /etc/php/8.3/fpm/conf.d/zzz-dplan.ini
COPY --link zzz-dplan-cli.ini /etc/php/8.3/cli/conf.d/zzz-dplan-cli.ini
COPY --link php-fpm_www.conf /etc/php/8.3/fpm/pool.d/zzz-www.conf
COPY --link php-fpm.conf /etc/php/8.3/fpm/php-fpm.conf

WORKDIR /srv/www

# Copy only built artifacts from build stage (no build tools, no node_modules history)
COPY --link --from=build /srv/www /srv/www
COPY --link --from=build /opt/config/parameters.yml /opt/config/parameters.yml

# Setup directories and permissions
RUN rm -rf /srv/www/var/cache/* && \
    rm -rf /srv/www/var/log/* && \
    rm -rf /srv/www/projects/$PROJECT_NAME/{files,fonts,images,js,video,img,pdf} && \
    mkdir -p /opt/config && \
    chown -R www-data:www-data /opt/config && \
    # ensure tmp directory exists and is writable for Symfony XML schema validation \
    mkdir -p /srv/www/tmp && \
    chown -R www-data:www-data /srv/www/tmp && \
    chown -R www-data:www-data /srv/www/var && \
    # link the custom squid root ca to the system that is mounted as a secret \
    # use a dummy file during link creation to prevent the error if the secret is not mounted \
    touch /srv/www/var/certs/ca.crt && \
    ln -s /srv/www/var/certs/ca.crt /usr/local/share/ca-certificates/ca.crt && \
    # avoid conflict with the default www.conf \
    rm -f /etc/php/8.3/fpm/pool.d/www.conf && \
    # create php-fpm log file \
    touch /var/log/php8.3-fpm.log && chown www-data:www-data /var/log/php8.3-fpm.log

# Strip setuid/setgid bits — defense-in-depth against privilege escalation from a compromised
# runtime (matches dunglas/symfony-docker + BSI SYS.1.6 hardening guidance).
RUN find / -perm /6000 -type f -exec chmod a-s {} + 2>/dev/null || true

# Commit SHA labels at end to avoid busting layer cache for apt-get
ARG COMMIT_SHA_CORE=""
ARG COMMIT_SHA_PROJECT=""
LABEL de.demos.core.revision=$COMMIT_SHA_CORE
LABEL de.demos.project.revision=$COMMIT_SHA_PROJECT

# run as rootless container
USER www-data
CMD ["/usr/sbin/php-fpm8.3", "-F"]

FROM nginxinc/nginx-unprivileged:alpine3.23-slim AS nginx

ARG PROJECT_NAME
ARG BUILD_MODE=prod

LABEL org.opencontainers.image.source="https://github.com/demos-europe/demosplan-core"

ENV PHP_FPM_BETEILIGUNG_SERVICE=beteiligung \
    BUILD_MODE=$BUILD_MODE

# Copy nginx config templates to context
COPY --link nginx.conf.template /tmp/nginx.conf.template
COPY --link nginx.conf.template-dev /tmp/nginx.conf.template-dev

# Use appropriate template based on build mode
RUN mkdir -p /etc/nginx/templates && \
    if [ "$BUILD_MODE" = "dev" ] && [ -f /tmp/nginx.conf.template-dev ]; then \
      cp /tmp/nginx.conf.template-dev /etc/nginx/templates/default.conf.template; \
    else \
      cp /tmp/nginx.conf.template /etc/nginx/templates/default.conf.template; \
    fi

WORKDIR /srv/www
# only webfolder with frontcontroller and static files is needed for nginx
COPY --link --from=build /srv/www/projects/$PROJECT_NAME/web /srv/www/projects/$PROJECT_NAME/web

# Ensure text/csv is defined in mime.types
RUN sed -i '/^}/i\    text/csv csv;' /etc/nginx/mime.types

# Commit SHA labels at end to avoid busting layer cache
ARG COMMIT_SHA_CORE=""
ARG COMMIT_SHA_PROJECT=""
LABEL de.demos.core.revision=$COMMIT_SHA_CORE
LABEL de.demos.project.revision=$COMMIT_SHA_PROJECT
