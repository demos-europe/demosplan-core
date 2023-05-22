#!/usr/bin/env bash

# re-export basic env vars to keep them around after "boot"
export CURRENT_HOST_USERID
export CURRENT_HOST_USERNAME

has_user=$(id ${CURRENT_HOST_USERNAME})

if [[ $? -ne 0 ]]
then
    # make current user available inside the container
    adduser --home /srv/www \
      --shell /bin/zsh \
      --uid=${CURRENT_HOST_USERID} \
      --disabled-password \
      --gecos "" \
      ${CURRENT_HOST_USERNAME}

    usermod -a -G sudo ${CURRENT_HOST_USERNAME} # make user able to sudo inside the container
fi

# setup listening address for ssh
SSH_LISTEN_ADDRESS=$(host demosplan | cut -d ' ' -f 4)
echo "ListenAddress ${SSH_LISTEN_ADDRESS}" > /etc/ssh/sshd_config.d/docker_listen_address

# provide ssh key to root
mkdir -p /root/.ssh/
chmod 0700 -R /root/.ssh/
cp /.authorized_key /root/.ssh/authorized_keys
chown root:root /root/.ssh/authorized_keys
chmod 0600 /root/.ssh/authorized_keys

# provide ssh key to user
mkdir -p /srv/www/.ssh/
chmod 0700 -R /srv/www/.ssh/
cp /.authorized_key /srv/www/.ssh/authorized_keys
chmod 0600 /srv/www/.ssh/authorized_keys
chown -R ${CURRENT_HOST_USERNAME}:${CURRENT_HOST_USERNAME} /srv/www/.ssh


for project in $(ls -1 /srv/www/projects)
do
    if [[ -d /srv/www/projects/${project}/web ]]
    then
        mkdir -p "/srv/www/projects/${project}/web/uploads/files"
        chmod -R 777 "/srv/www/projects/${project}/web/uploads"
        echo "Updated permissions for ${project}"
    fi
done

# reset access rights for uploads dir
mkdir -p /opt/uploads
chown -R www-data /opt/uploads

# start sshd for debugging access
service ssh start

while [[ 1 -eq 1 ]]
do
    # Basically, this startup script just needs to sleep forever after starting the required services
    # In the future we may want to add some form of a health check / heartbeat to make sure everything
    # is still up to snuff.
    sleep 60
done

