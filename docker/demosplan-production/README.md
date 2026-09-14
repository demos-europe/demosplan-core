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

## Addon installation

By default the build installs addons defined in the project's `addons.yaml` via
`dplan:addon:autoinstall`, which pulls each addon from GitHub (requires `GITHUB_TOKEN`
via the `envlocal` build secret).

### Installing addons from local zips

To install pre-built addon zips instead of pulling from GitHub, place the zips in an
`addonZipsDeploy/` folder in the build context. The build script copies this folder into
the image, and the Dockerfile detects it at build time:

- If `/srv/www/addonZipsDeploy` exists and contains `*.zip` files, each zip is installed
  via `dplan:addon:install <zip>` (frontend build deferred to the single core
  `./fe build` step afterward).
- Otherwise, `dplan:addon:autoinstall` runs as the fallback.

The folder is owned by `www-data` so the build can read the zips and remove the folder
during cleanup, and it is deleted from the final image together with `addonZips`.
