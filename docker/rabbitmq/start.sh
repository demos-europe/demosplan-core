#!/usr/bin/env bash

envsubst < /etc/rabbitmq/definitions-raw.json > /etc/rabbitmq/definitions.json

rabbitmq-server
