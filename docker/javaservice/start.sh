#!/usr/bin/env bash

java -jar -Xmx$JAVASERVICE_MEMORY -Dfile.encoding=UTF-8 -Dlogpath=/var/logs/demosplan /opt/demosplan/demosplan.jar --spring.config.location=classpath:application.properties,/opt/demosplan/override.properties
