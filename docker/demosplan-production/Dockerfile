# due to BSI SYS.1.6.A6 we need to use excplicit versions
FROM debian:testing-20241111-slim AS fpm

ARG PROJECT_NAME
# ENV vars needs to be set to build the assets
ENV PROJECT_PREFIX=$PROJECT_NAME \
    EXTERNAL_LINK_DIPLAN_COCKPIT=http://temporary.de \
    EXTERNAL_LINK_DIPLAN_PORTAL=http://temporary.de

ENV TZ=Europe/Berlin
RUN ln -snf /usr/share/zoneinfo/"$TZ" /etc/localtime && echo "$TZ"> /etc/timezone

# install default-mysql-client to be able to dump database
RUN apt update -y && \
    apt --no-install-recommends install acl bind9-host composer curl gettext-base default-mysql-client npm php8.2 php8.2-apcu php8.2-bcmath php8.2-bz2 php8.2-cli php8.2-common php8.2-curl php8.2-fpm php8.2-gd php8.2-intl php8.2-mbstring php8.2-mysqli php8.2-soap php8.2-sqlite3 php8.2-xdebug php8.2-xml php8.2-zip unzip vim zsh -y && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* && \
    apt-get autoremove -y && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*
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
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/var/composer composer install -o --prefer-dist --no-dev --no-scripts --no-progress

COPY package.json yarn.lock .yarnrc.yml ./

RUN --mount=type=cache,target=/var/www,uid=33,gid=33 yarn install --immutable

USER root
COPY . /srv/www

# set permissions needed during build
RUN mkdir -p /srv/www/projects/$PROJECT_NAME/web/js && \
    mkdir -p /srv/www/projects/$PROJECT_NAME/web/fonts && \
    mkdir -p /srv/www/projects/$PROJECT_NAME/web/images && \
    mkdir -p /srv/www/projects/$PROJECT_NAME/web/video && \
    mkdir -p /srv/www/var/cache && \
    mkdir -p /srv/www/var/log && \
    chown -R www-data /srv/www/projects/$PROJECT_NAME/web && \
    chown -R www-data /srv/www/var/cache && \
    chown -R www-data /srv/www/var/log && \
# define doctrine in memory database for the asset build
    mv /srv/www/config/packages/doctrine.yaml /tmp/doctrine.yaml
COPY doctrine_inmemory.yaml /srv/www/config/packages/doctrine.yaml
COPY parameters_build.yml /srv/www/projects/$PROJECT_NAME/app/config/parameters.yml

USER www-data
RUN ./fe build $PROJECT_NAME --prod && \
    rm -rf /srv/www/node_modules && \
    rm -rf /srv/www/.cache

USER root
RUN mv /tmp/doctrine.yaml /srv/www/config/packages/doctrine.yaml
COPY projects/$PROJECT_NAME/app/config/parameters_docker.yml /opt/config/parameters.yml

RUN rm /srv/www/projects/$PROJECT_NAME/app/config/parameters.yml && \
    ln -s /opt/config/parameters.yml /srv/www/projects/$PROJECT_NAME/app/config/parameters.yml && \
    mkdir -p /srv/www/projects/$PROJECT_NAME/web/uploads/files && \
    chmod -R 775 /srv/www/projects/$PROJECT_NAME/web/uploads && \
    mkdir -p /opt/uploads && \
    chown -R root:root /srv/www/projects/$PROJECT_NAME/web && \
    chown -R www-data /opt/uploads && \
    chown -R www-data /srv/www/projects/$PROJECT_NAME/web/uploads && \
    ## set facls for /tmp (trick the system) still needed?
    setfacl -dRm u:www-data:rwx /tmp && \
    touch /var/log/php8.2-fpm.log && chown www-data:www-data /var/log/php8.2-fpm.log

EXPOSE 9000
# run as rootless container
USER www-data
CMD ["/usr/sbin/php-fpm8.2", "-F"]

FROM nginxinc/nginx-unprivileged:alpine3.19-slim AS nginx

ARG PROJECT_NAME
ENV PHP_FPM_BETEILIGUNG_SERVICE=beteiligung

COPY nginx.conf.template /etc/nginx/templates/default.conf.template

WORKDIR /srv/www
# only webfolder with frontcontroller and static files is needed for nginx
COPY --from=fpm /srv/www/projects/$PROJECT_NAME/web /srv/www/projects/$PROJECT_NAME/web
