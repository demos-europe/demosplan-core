FROM demosplan-base AS fpm

ARG PROJECT_NAME
# ENV vars needs to be set to build the assets
ENV PROJECT_PREFIX=$PROJECT_NAME \
    EXTERNAL_LINK_DIPLAN_COCKPIT=http://temporary.de \
    EXTERNAL_LINK_DIPLAN_PORTAL=http://temporary.de

# install default-mysql-client to be able to dump database
RUN apt update -y && apt --no-install-recommends install default-mysql-client php8.2-fpm -y \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* && \
    apt-get autoremove -y && \
    apt-get clean \
    && rm -rf /var/lib/apt/lists/*
COPY zzz-dplan.ini /etc/php/8.2/fpm/conf.d/zzz-dplan.ini
COPY php-fpm_www.conf /etc/php/8.2/fpm/pool.d/zzz-www.conf

RUN npm i --ignore-scripts -g n && rm -rf /root/.npm
# Install specific node version
RUN n 18.20.1

WORKDIR /srv/www

RUN mkdir -p /opt/uploads && mkdir /opt/config && mkdir /srv/www/vendor &&  \
    chown -R www-data:www-data /opt/uploads && chown -R www-data:www-data /opt/config && chown -R www-data:www-data /srv/www

RUN mkdir -p /srv/www/projects/$PROJECT_NAME/app/config && \
# asset build only needs the parameters.yml without content
    echo "parameters:" > /srv/www/projects/$PROJECT_NAME/app/config/parameters.yml

USER www-data
# Install composer and yarn before copying the project files to make use of the docker cache
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/var/composer composer install -o --prefer-dist --no-scripts --no-progress && rm -rf /srv/www/.cache
#RUN composer install --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress;

COPY package.json yarn.lock ./
RUN yarn install --ignore-scripts --frozen-lockfile && rm -rf /srv/www/.cache

USER root
COPY . /srv/www

# set permissions needed during build
RUN mkdir -p /srv/www/projects/$PROJECT_NAME/web/js && \
    mkdir -p /srv/www/projects/$PROJECT_NAME/web/fonts && \
    mkdir -p /srv/www/projects/$PROJECT_NAME/web/images && \
    mkdir -p /srv/www/projects/$PROJECT_NAME/web/video && \
    mkdir -p /srv/www/projects/$PROJECT_NAME/app/cache && \
    mkdir -p /srv/www/projects/$PROJECT_NAME/app/logs && \
    chown -R www-data /srv/www/projects/$PROJECT_NAME/web && \
    chown -R www-data /srv/www/projects/$PROJECT_NAME/app/cache && \
    chown -R www-data /srv/www/projects/$PROJECT_NAME/app/logs

USER www-data
RUN DEVELOPMENT_CONTAINER=1 ./fe build $PROJECT_NAME && rm -rf /srv/www/node_modules && rm -rf /srv/www/.cache
#RUN ./fe build --prod $PROJECT_NAME

USER root
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
    setfacl -dRm u:www-data:rwx /tmp

RUN touch /var/log/php8.2-fpm.log && chown www-data:www-data /var/log/php8.2-fpm.log

EXPOSE 9000
# run as rootless container
USER www-data
CMD ["/usr/sbin/php-fpm8.2", "-F"]

FROM nginxinc/nginx-unprivileged:latest AS nginx

ARG PROJECT_NAME
ENV PHP_FPM_BETEILIGUNG_SERVICE=beteiligung

COPY nginx.conf.template /etc/nginx/templates/default.conf.template

WORKDIR /srv/www
# only webfolder with frontcontroller and static files is needed for nginx
COPY --from=fpm /srv/www/projects/$PROJECT_NAME/web /srv/www/projects/$PROJECT_NAME/web
