FROM php:8.1-cli

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions && \
        install-php-extensions apcu bcmath bz2 curl gd intl json mbstring opcache pdo_mysql soap xml zip


COPY zzz-dplan.ini "$PHP_INI_DIR/conf.d"
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
RUN mkdir /tmp/dplan
RUN chown www-data:www-data /tmp/dplan

ADD ./start.sh /
RUN chmod +x /start.sh

USER www-data

WORKDIR /srv/www
ENTRYPOINT [ "/start.sh" ]
