#!/usr/bin/env bash

cd "$(dirname "$0")" || exit

function usage() {
  echo "Usage: ./build.sh [-p] [-d] [-v] <IMAGE_NAME> <VERSION> <PROJECT_NAME>"
  echo "  -p: If specified, images will be pushed to registry"
  echo "  -d: If specified, container will be built in dev mode, otherwise prod mode"
  echo "  -v: If specified, output logging for builds will be verbose"
  return 0
}

# Parse arguments using getopts
PUSH=""
DEV=""
VERBOSE=""
while getopts "pdv" opt; do
  case $opt in
    p)
      PUSH="push"
      ;;
    d)
      DEV="dev"
      ;;
    v)
      VERBOSE="--progress=plain"
      ;;
    \?)
      echo "Invalid option: -$OPTARG" >&2
      usage
      exit 2
      ;;
    *)
      echo "Unexpected option: -$opt" >&2
      usage
      exit 2
      ;;
  esac
done
shift $((OPTIND -1))

if [[ $# -lt 3 ]]; then
  usage
  exit 2
fi

FOLDER=demosplan-production
IMAGE_NAME=$1
VERSION=$2
PROJECT_NAME=$3
CONTEXT_DIR=.context
BUILD_MODE="prod"
PLATFORM="linux/amd64"

# Resolve git commit SHAs for image labels
COMMIT_SHA_CORE=$(git -C .. rev-parse HEAD 2>/dev/null || echo "")
COMMIT_SHA_PROJECT=$(git -C "../projects/$PROJECT_NAME" rev-parse HEAD 2>/dev/null || echo "")

# Check if dev environment is requested
if [[ $DEV == "dev" ]]; then
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
        $VERBOSE \
        --platform $PLATFORM \
        --build-arg PROJECT_NAME=$PROJECT_NAME \
        --build-arg BUILD_MODE=$BUILD_MODE \
        --build-arg COMMIT_SHA_CORE=$COMMIT_SHA_CORE \
        --build-arg COMMIT_SHA_PROJECT=$COMMIT_SHA_PROJECT \
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

if [[ $PUSH == "push" ]]
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
