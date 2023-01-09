FROM php:8.1-fpm

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions && \
  install-php-extensions apcu bcmath bz2 curl gd intl json mbstring opcache pdo_mysql soap xdebug xml zip

COPY zzz-dplan.ini "$PHP_INI_DIR/conf.d"
COPY xdebug.conf "$PHP_INI_DIR/conf.d/20-xdebug.ini"
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
RUN mkdir /tmp/dplan
RUN chown www-data:www-data /tmp/dplan
WORKDIR /srv/www

USER www-data
