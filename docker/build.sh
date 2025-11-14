#!/usr/bin/env bash

cd "$(dirname "$0")" || exit

if [ $# -lt 4 ]
then
  echo "Usage ./build.sh demosplan-production <IMAGE_NAME> <VERSION> <PROJECT_NAME> <?push> <?dev>"
  echo "  <?push>: If 'push' is specified, images will be pushed to registry"
  echo "  <?dev>: If 'dev' is specified, container will be built in dev mode, otherwise prod mode"
  exit 2
fi

FOLDER=$1
IMAGE_NAME=$2
VERSION=$3
PROJECT_NAME=$4
CONTEXT_DIR=.context
BUILD_MODE="prod"
PLATFORM="linux/amd64"

# Check if dev environment is requested
if [[ $6 == "dev" ]]
then
  BUILD_MODE="dev"
  VERSION="${VERSION}-dev"
fi

printf "Building %s in %s mode...\n" $FOLDER $BUILD_MODE

function docker_build() {
    # extract named arguments
    image=$1
    target=$2

    # remove those to append remaining arguments as extra to docker build command
    shift 2

    DOCKER_BUILDKIT=1 docker build \
        --platform $PLATFORM \
        --build-arg PROJECT_NAME=$PROJECT_NAME \
        --build-arg BUILD_MODE=$BUILD_MODE \
        -t $image:$VERSION \
        -f $FOLDER/Dockerfile \
        --target "$target" \
        "$@" \
        $CONTEXT_DIR
}

if [ -d $CONTEXT_DIR ]
then
rm -rf $CONTEXT_DIR
fi

mkdir -p $CONTEXT_DIR/{bin,projects}

rsync --files-from=rsyncInclude.txt -arz .. $CONTEXT_DIR
rsync --exclude-from=rsyncExcludeProject.txt -az "../projects/$PROJECT_NAME" $CONTEXT_DIR/projects
cp -r "$FOLDER"/* $CONTEXT_DIR
cp -r "$FOLDER"/.dockerignore $CONTEXT_DIR

docker_build "$IMAGE_NAME" fpm --secret id=envlocal,src=../.env.local
docker_build "$IMAGE_NAME/nginx" nginx
docker_build "$IMAGE_NAME-nginx" nginx

rm -rf $CONTEXT_DIR

if [[ $5 == "push" ]]
then
    docker push "$IMAGE_NAME:$VERSION"
    if [[ $PROJECT_NAME == *"diplan"* ]]
    then
        docker push "$IMAGE_NAME/nginx:$VERSION"
    else
        docker push $IMAGE_NAME-nginx:$VERSION
    fi
fi

printf "Build of %s in %s mode completed.\n" $FOLDER $BUILD_MODE
