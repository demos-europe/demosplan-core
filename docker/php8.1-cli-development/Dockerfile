FROM debian:11

RUN apt update && apt install acl apt-transport-https apt-utils binutils ca-certificates curl gpg less logrotate moreutils openssh-server python3 tree vim-tiny wget zsh -y

RUN echo "deb https://packages.sury.org/php/ bullseye main" | tee /etc/apt/sources.list.d/sury-php.list
RUN curl -fsSL  https://packages.sury.org/php/apt.gpg| gpg --dearmor -o /etc/apt/trusted.gpg.d/sury-keyring.gpg

RUN apt update && apt install composer bind9-host htop jq lnav net-tools npm php8.1 php8.1-apcu php8.1-bcmath php8.1-bz2 php8.1-cli php8.1-common php8.1-curl php8.1-fpm php8.1-gd php8.1-intl php8.1-mbstring php8.1-mysqli php8.1-soap php8.1-sqlite3 php8.1-xdebug php8.1-xml php8.1-zip silversearcher-ag unzip -y
RUN update-alternatives --set php /usr/bin/php8.1
COPY zzz-dplan.ini /etc/php/8.1/cli/conf.d/zzz-dplan.ini

RUN npm i -g npm license-checker npm-check yarn

# Environment
COPY zshenv /etc/zsh/zshenv
RUN chsh -s /bin/zsh

# Initialize ssh
COPY ./sshd_config /etc/ssh/sshd_config
COPY ./files/ssh_host_ed25519_key /etc/ssh/ssh_host_ed25519_key
RUN chmod 600 /etc/ssh/ssh_host_ed25519_key
COPY ./files/ssh_host_ed25519_key /etc/ssh/ssh_host_dsa_key
RUN chmod 600 /etc/ssh/ssh_host_dsa_key
COPY ./files/ssh_host_ed25519_key.pub /etc/ssh/ssh_host_ed25519_key.pub
RUN chmod 644 /etc/ssh/ssh_host_ed25519_key.pub
RUN mkdir -p /etc/ssh/sshd_config.d

ADD ./start.sh /
RUN chmod +x /start.sh

WORKDIR /srv/www
ENTRYPOINT [ "/start.sh" ]
