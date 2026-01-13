# due to BSI SYS.1.6.A6 we need to use excplicit versions
FROM debian:bookworm-20251020-slim AS base

# Install all dependencies needed for both build and fpm stages
RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt/lists,sharing=locked \
    apt-get update -y && apt-get install --no-install-recommends  lsb-release ca-certificates curl gpg -y \
    && curl -sSo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb \
    && dpkg -i /tmp/debsuryorg-archive-keyring.deb \
    && rm -f /tmp/debsuryorg-archive-keyring.deb \
    && echo "deb https://packages.sury.org/php/ bookworm main" > /etc/apt/sources.list.d/deb.sury.org.list \
    && apt-get update -y \
    && apt-get --no-install-recommends install acl bind9-host ca-certificates composer curl  \
    default-mysql-client gettext-base lnav npm php8.3 php8.3-apcu php8.3-bcmath php8.3-bz2 php8.3-cli  \
    php8.3-common php8.3-curl php-excimer php8.3-fpm php8.3-gd php8.3-intl php8.3-mbstring php8.3-mysqli php8.3-soap  \
    php8.3-sqlite3 php8.3-xml php8.3-zip unzip vim-tiny -y \
    && update-alternatives --set php /usr/bin/php8.3 \
    && mkdir -p /opt/uploads
COPY zzz-dplan-cli.ini /etc/php/8.3/cli/conf.d/zzz-dplan-cli.ini

WORKDIR /srv/www

CMD ["sleep", "infinity"]
