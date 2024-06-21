# due to BSI SYS.1.6.A6 we need to use excplicit versions
FROM debian:testing-20240408-slim AS fpm

RUN apt update -y && apt install acl bind9-host composer gettext-base npm php8.2 php8.2-apcu php8.2-bcmath php8.2-bz2 php8.2-cli php8.2-common php8.2-curl php8.2-fpm php8.2-gd php8.2-intl php8.2-mbstring php8.2-mysqli php8.2-soap php8.2-sqlite3 php8.2-xdebug php8.2-xml php8.2-zip unzip vim zsh -y \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* && \
    apt-get autoremove -y && \
    apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN npm i -g yarn && rm -rf /root/.npm
RUN mkdir -p /opt/uploads
COPY zzz-dplan-cli.ini /etc/php/8.2/cli/conf.d/zzz-dplan-cli.ini

ARG PROJECT_NAME
# ENV vars needs to be set to build the assets
ENV PROJECT_PREFIX=$PROJECT_NAME

COPY zzz-dplan.ini /etc/php/8.2/fpm/conf.d/zzz-dplan.ini
COPY php-fpm_www.conf /etc/php/8.2/fpm/pool.d/zzz-www.conf

WORKDIR /srv/www

RUN mkdir -p /opt/uploads && mkdir /opt/config && mkdir /srv/www/vendor &&  \
    chown -R www-data:www-data /opt/uploads && chown -R www-data:www-data /opt/config && chown -R www-data:www-data /srv/www

#COPY parameters.yml /opt/config/parameters.yml
#
#RUN mkdir -p /srv/www/projects/$PROJECT_NAME/app/config && \
## asset build only needs the parameters.yml without content
#    echo "parameters:" > /srv/www/projects/$PROJECT_NAME/app/config/parameters.yml

USER www-data
# Install composer and yarn before copying the project files to make use of the docker cache
#COPY composer.json composer.lock ./
RUN #--mount=type=cache,target=/var/composer composer install -o --prefer-dist --no-scripts --no-progress && rm -rf /srv/www/.cache
#RUN composer install --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress;

#COPY package.json yarn.lock ./
#RUN yarn install --frozen-lockfile && rm -rf /srv/www/.cache

USER root
COPY --chown=www-data:www-data . /srv/www

#USER www-data
RUN #DEVELOPMENT_CONTAINER=1 ./fe build $PROJECT_NAME && rm -rf /srv/www/node_modules && rm -rf /srv/www/.cache
#RUN ./fe build --prod $PROJECT_NAME

USER root
RUN mkdir -p "/srv/www/projects/$PROJECT_NAME/web/uploads/files" && \
    chmod -R 777 "/srv/www/projects/$PROJECT_NAME/web/uploads" && \
    mkdir -p /opt/uploads && \
    chown -R www-data /opt/uploads && \
    ## set facls for /tmp (trick the system) still needed?
    setfacl -dRm u:www-data:rwx /tmp

RUN touch /var/log/php8.2-fpm.log && chown www-data:www-data /var/log/php8.2-fpm.log

EXPOSE 9000
# run as rootless container
USER www-data
CMD ["/usr/sbin/php-fpm8.2", "-F"]

FROM nginxinc/nginx-unprivileged:1.27.0-alpine AS nginx

ARG PROJECT_NAME
ENV PHP_FPM_BETEILIGUNG_SERVICE=development

COPY nginx.conf.template /etc/nginx/templates/default.conf.template

WORKDIR /srv/www
# only webfolder with frontcontroller and static files is needed for nginx
COPY --from=fpm --chown=nginx:nginx /srv/www/projects/$PROJECT_NAME/web /srv/www/projects/$PROJECT_NAME/web


