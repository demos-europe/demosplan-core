# due to BSI SYS.1.6.A6 we need to use excplicit versions
FROM debian:testing-20240722-slim AS base

RUN apt update -y && apt --no-install-recommends install acl bind9-host composer curl gettext-base npm php8.2 php8.2-apcu php8.2-bcmath php8.2-bz2 php8.2-cli php8.2-common php8.2-curl php8.2-gd php8.2-intl php8.2-mbstring php8.2-mysqli php8.2-soap php8.2-sqlite3 php8.2-xdebug php8.2-xml php8.2-zip unzip vim zsh -y \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* && \
    apt-get autoremove -y && \
    apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN npm i --ignore-scripts -g yarn && rm -rf /root/.npm
RUN mkdir -p /opt/uploads
COPY zzz-dplan-cli.ini /etc/php/8.2/cli/conf.d/zzz-dplan-cli.ini

FROM base

WORKDIR /srv/www

CMD ["sleep", "infinity"]
