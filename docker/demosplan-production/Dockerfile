# due to BSI SYS.1.6.A6 we need to use excplicit versions
FROM demosdeutschland/demosplan-base:latest AS build

ARG PROJECT_NAME
ARG BUILD_MODE=prod
# ENV vars needs to be set to build the assets
ENV PROJECT_PREFIX=$PROJECT_NAME \
    EXTERNAL_LINK_DIPLAN_COCKPIT=http://temporary.de \
    EXTERNAL_LINK_DIPLAN_PORTAL=http://temporary.de \
    BUILD_MODE=$BUILD_MODE \
    TZ=Europe/Berlin

# Setup timezone
RUN ln -snf /usr/share/zoneinfo/"$TZ" /etc/localtime && echo "$TZ"> /etc/timezone
COPY zzz-dplan.ini /etc/php/8.2/fpm/conf.d/zzz-dplan.ini
COPY zzz-dplan-cli.ini /etc/php/8.2/cli/conf.d/zzz-dplan-cli.ini
COPY php-fpm_www.conf /etc/php/8.2/fpm/pool.d/zzz-www.conf

RUN npm install --ignore-scripts -g corepack && \
    corepack enable && yarn set version 4.2.2 && \
    npm i --ignore-scripts -g n && \
    rm -rf /root/.npm && \
# Install specific node version
    n 20.18.1

WORKDIR /srv/www

RUN mkdir -p /opt/uploads && \
    mkdir /opt/config && \
    mkdir /srv/www/vendor && \
    mkdir -p /srv/www/client/js/generated && \
    chown -R www-data:www-data /opt/uploads && \
    chown -R www-data:www-data /srv/www/client/js/generated && \
    chown -R www-data:www-data /opt/config && \
    chown -R www-data:www-data /srv/www && \
    mkdir -p /srv/www/projects/$PROJECT_NAME/app/config && \
# asset build only needs the parameters.yml without content
    echo "parameters:" > /srv/www/projects/$PROJECT_NAME/app/config/parameters.yml

USER www-data
WORKDIR /srv/www
# Install composer and yarn before copying the project files to make use of the docker cache
COPY composer.json composer.lock package.json yarn.lock .yarnrc.yml ./
# do not use --no-scripts, as we do not need all the aws stuff
RUN --mount=type=cache,target=/var/composer --mount=type=cache,target=/var/www,uid=33,gid=33 \
    if [ "$BUILD_MODE" = "dev" ]; then \
        composer install -o --prefer-dist --no-progress; \
    else \
        composer install -o --prefer-dist --no-dev --no-progress; \
    fi && \
    yarn install --immutable && \
    yarn cache clean && \
    rm -rf /srv/www/.cache/*

USER root
COPY . /srv/www

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
# define doctrine in memory database for the asset build
    mv /srv/www/config/packages/doctrine.yaml /tmp/doctrine.yaml
COPY doctrine_inmemory.yaml /srv/www/config/packages/doctrine.yaml
COPY parameters_build.yml /srv/www/projects/$PROJECT_NAME/app/config/parameters.yml

USER www-data
# mount .env.local as a secret to ensure that addons can be installed while keeping the layer clean.
# during addon install the cache is deleted and the command "fails" because it does not find the cache
# the command is executed with "|| true" to prevent the build from failing
RUN --mount=type=secret,id=envlocal,target=/srv/www/.env.local,uid=33 \
    --mount=type=cache,target=/var/www,uid=33,gid=33 \
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
COPY projects/$PROJECT_NAME/app/config/parameters_docker.yml /opt/config/parameters.yml

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
    touch /var/log/php8.2-fpm.log && chown www-data:www-data /var/log/php8.2-fpm.log

FROM demosdeutschland/demosplan-base:latest AS fpm

ARG PROJECT_NAME
ENV PROJECT_PREFIX=$PROJECT_NAME \
    EXTERNAL_LINK_DIPLAN_COCKPIT=http://temporary.de \
    EXTERNAL_LINK_DIPLAN_PORTAL=http://temporary.de \
    TZ=Europe/Berlin

# Install additional packages needed for FPM
RUN mkdir -p /opt/config && \
    chown -R www-data:www-data /opt/config

COPY zzz-dplan.ini /etc/php/8.2/fpm/conf.d/zzz-dplan.ini
COPY zzz-dplan-cli.ini /etc/php/8.2/cli/conf.d/zzz-dplan-cli.ini
COPY php-fpm_www.conf /etc/php/8.2/fpm/pool.d/zzz-www.conf
COPY php-fpm.conf /etc/php/8.2/fpm/php-fpm.conf

WORKDIR /srv/www

COPY --from=build /srv/www /srv/www
COPY --from=build /opt/config/parameters.yml /opt/config/parameters.yml
# delte the cache and log files
RUN rm -rf /srv/www/var/cache/* && \
    rm -rf /srv/www/var/log/* && \
    rm -rf /srv/www/projects/$PROJECT_NAME/{files,fonts,images,js,video,img,pdf} && \
    # link the custom squid root ca to the system that is mounted as a secret \
    # use a dummy file during link creation to prevent the error if the secret is not mounted \
    touch /srv/www/var/certs/ca.crt && \
    ln -s /srv/www/var/certs/ca.crt /usr/local/share/ca-certificates/ca.crt

# run as rootless container
USER www-data
CMD ["/usr/sbin/php-fpm8.2", "-F"]

FROM nginxinc/nginx-unprivileged:alpine3.22-slim AS nginx

ARG PROJECT_NAME
ARG BUILD_MODE=prod
ENV PHP_FPM_BETEILIGUNG_SERVICE=beteiligung \
    BUILD_MODE=$BUILD_MODE

# Copy nginx config templates to context
COPY nginx.conf.template /tmp/nginx.conf.template
COPY nginx.conf.template-dev /tmp/nginx.conf.template-dev

# Use appropriate template based on build mode
RUN mkdir -p /etc/nginx/templates && \
    if [ "$BUILD_MODE" = "dev" ] && [ -f /tmp/nginx.conf.template-dev ]; then \
      cp /tmp/nginx.conf.template-dev /etc/nginx/templates/default.conf.template; \
    else \
      cp /tmp/nginx.conf.template /etc/nginx/templates/default.conf.template; \
    fi

WORKDIR /srv/www
# only webfolder with frontcontroller and static files is needed for nginx
COPY --from=build /srv/www/projects/$PROJECT_NAME/web /srv/www/projects/$PROJECT_NAME/web

# Ensure text/csv is defined in mime.types
RUN sed -i '/^}/i\    text/csv csv;' /etc/nginx/mime.types
