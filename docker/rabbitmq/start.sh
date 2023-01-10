#!/usr/bin/env bash

rabbitmqctl change_password hase "$RABBITMQ_USER_PASSWORD"

rabbitmq-server
