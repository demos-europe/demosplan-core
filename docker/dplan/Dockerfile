FROM debian:11 as base

RUN apt update && apt install acl apt-transport-https apt-utils binutils ca-certificates curl gpg less logrotate moreutils openssh-server python3 tree vim-tiny wget zsh -y

RUN echo "deb https://packages.sury.org/php/ bullseye main" | tee /etc/apt/sources.list.d/sury-php.list
RUN curl -fsSL  https://packages.sury.org/php/apt.gpg| gpg --dearmor -o /etc/apt/trusted.gpg.d/sury-keyring.gpg

RUN apt update && apt install composer bind9-host htop jq lnav net-tools php8.1 php8.1-apcu php8.1-bcmath php8.1-bz2 php8.1-cli php8.1-common php8.1-curl php8.1-fpm php8.1-gd php8.1-intl php8.1-mbstring php8.1-mysqli php8.1-soap php8.1-sqlite3 php8.1-xml php8.1-zip silversearcher-ag unzip -y
RUN update-alternatives --set php /usr/bin/php8.1
COPY zzz-dplan.ini /etc/php/8.1/cli/conf.d/zzz-dplan.ini

# Install Nodejs
RUN curl -o nodesource.gpg.key https://deb.nodesource.com/gpgkey/nodesource.gpg.key
RUN apt-key add nodesource.gpg.key
RUN rm nodesource.gpg.key
RUN echo "deb https://deb.nodesource.com/node_16.x bullseye main" | tee /etc/apt/sources.list.d/nodesource.list
RUN echo "deb-src https://deb.nodesource.com/node_16.x bullseye main" | tee -a /etc/apt/sources.list.d/nodesource.list
RUN curl -sL https://deb.nodesource.com/setup_16.x |  bash -
RUN apt update && apt install -y nodejs=16.*

RUN npm i -g npm license-checker npm-check yarn

ARG PROJECT_NAME

USER www-data
WORKDIR /srv/www

#RUN mkdir -p projects/$PROJECT_NAME/app/logs
#RUN mkdir -p projects/$PROJECT_NAME/app/cache/prod
ADD .context/parameters.yml /srv/www/projects/$PROJECT_NAME/app/config/parameters.yml

ADD .context/package.json .
ADD .context/yarn.lock .
RUN yarn install --frozen-lockfile
RUN yarn install --frozen-lockfile

ADD .context/composer.json .
ADD .context/composer.lock .
RUN composer install -o

COPY --chown=www-data:www-data .context .
RUN yarn run dev:$PROJECT_NAME

#FROM php:8.1-fpm as php-fpm

#COPY zzz-dplan.ini "$PHP_INI_DIR/conf.d"
#RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
RUN mkdir /tmp/dplan
RUN chown www-data:www-data /tmp/dplan

USER www-data
WORKDIR /srv/www

#COPY --from=base /srv/www .
