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
    default-mysql-client gettext-base lnav npm php8.2 php8.2-apcu php8.2-bcmath php8.2-bz2 php8.2-cli  \
    php8.2-common php8.2-curl php-excimer php8.2-fpm php8.2-gd php8.2-intl php8.2-mbstring php8.2-mysqli php8.2-soap  \
    php8.2-sqlite3 php8.2-xml php8.2-zip unzip vim-tiny -y \
    && mkdir -p /opt/uploads
COPY zzz-dplan-cli.ini /etc/php/8.2/cli/conf.d/zzz-dplan-cli.ini

WORKDIR /srv/www

CMD ["sleep", "infinity"]
