# demosplan-production container

This container is used to deploy the demosplan application. It contains everything
needed to run the application in a production environment. It is based on the
[demosplan-base](../demosplan-base/README.md) container.

On top of the base container, it contains the following components:
- nginx
- php-fpm

## Build
You can build the container with the following command from one folder above this README.md:


```bash
./build.sh demosplan-production <container-registry> <container-tag> projectName
```
