# demosplan-base container

This container is used as a base for the demosplan containers. It contains everything
needed to test the demosplan application in the CI. No extra components like
nginx or php-fpm are included, nor does it contain any files from the application.

## Build
You can build the container with the following command:
```bash
DOCKER_BUILDKIT=1 docker build -t demosdeutschland/demosplan-base .
```
