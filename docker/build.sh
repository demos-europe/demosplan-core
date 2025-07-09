#!/usr/bin/env bash

cd $(dirname $0)

if [ $# -lt 4 ]
then
  echo "Usage ./build.sh demosplan-production <imagename> <version> <projectname> <?push> <?dev>"
  echo "  <?push>: If 'push' is specified, images will be pushed to registry"
  echo "  <?dev>: If 'dev' is specified, container will be built in dev mode, otherwise prod mode"
  exit 2
fi

projectsfolder="projects"

folder=$1
imagename=$2
version=$3
projectname=$4
context=.context
build_mode="prod"

# Check if dev environment is requested
if [[ $6 == "dev" ]]
then
  build_mode="dev"
  version="${version}-dev"
fi

printf "Building %s in %s mode...\n" $folder $build_mode

if [ -d $context ]
then
rm -rf $context
fi

mkdir -p $context/{bin,projects}

rsync --files-from=rsyncInclude.txt -arz .. $context
rsync -az ../bin/$projectname $context/bin/$projectname
rsync --exclude-from=rsyncExcludeProject.txt -az ../projects/$projectname $context/projects
cp -r $folder/* $context
cp -r $folder/.dockerignore $context
# use --progress=plain to see all build output
DOCKER_BUILDKIT=1 docker build --build-arg PROJECT_NAME=$projectname --build-arg BUILD_MODE=$build_mode --secret id=envlocal,src=../.env.local -t $imagename:$version -f $folder/Dockerfile --target fpm $context
DOCKER_BUILDKIT=1 docker build --build-arg PROJECT_NAME=$projectname --build-arg BUILD_MODE=$build_mode -t $imagename/nginx:$version -f $folder/Dockerfile --target nginx $context
# / is not always allowed in image names
DOCKER_BUILDKIT=1 docker build --build-arg PROJECT_NAME=$projectname --build-arg BUILD_MODE=$build_mode -t $imagename-nginx:$version -f $folder/Dockerfile --target nginx $context

rm -rf $context

if [[ $5 == "push" ]]
then
docker push $imagename:$version
docker push $imagename/nginx:$version
fi
