#!/usr/bin/env bash

cd "$(dirname "$0")" || exit

if [ $# -lt 4 ]
then
  echo "Usage ./build.sh demosplan-production <IMAGE_NAME> <VERSION> <PROJECT_NAME> <?mode> <?push>"
  echo "  <?mode>: 'dev' for dev only, 'prod' for prod only (default), 'both' for dev and prod"
  echo "  <?push>: If 'push' is specified, images will be pushed to registry"
  exit 2
fi

FOLDER=$1
IMAGE_NAME=$2
VERSION=$3
PROJECT_NAME=$4
MODE=${5:-prod}
PUSH_IMAGES=$6
CONTEXT_DIR=.context
PLATFORM="linux/amd64"

# Determine what to build
case $MODE in
  dev)
    TARGETS="dev"
    VERSIONS="${VERSION}-dev"
    ;;
  both)
    TARGETS="dev prod"
    VERSIONS="${VERSION}-dev ${VERSION}"
    ;;
  *)
    TARGETS="prod"
    VERSIONS="${VERSION}"
    ;;
esac

printf "Building %s with mode=%s (targets: %s)\n" "$FOLDER" "$MODE" "$TARGETS"

# Prepare build context once
if [ -d $CONTEXT_DIR ]; then
    rm -rf $CONTEXT_DIR
fi

mkdir -p $CONTEXT_DIR/{bin,projects}
printf "Preparing build context...\n"
rsync --files-from=rsyncInclude.txt -arz .. $CONTEXT_DIR
rsync --exclude-from=rsyncExcludeProject.txt -az "../projects/$PROJECT_NAME" $CONTEXT_DIR/projects
cp -r "$FOLDER"/* $CONTEXT_DIR
cp -r "$FOLDER"/.dockerignore $CONTEXT_DIR

# Build all requested targets
for target in $TARGETS; do
    if [ "$target" = "dev" ]; then
        tag_version="${VERSION}-dev"
    else
        tag_version="${VERSION}"
    fi

    printf "\n=== Building %s images (version: %s) ===\n" "$target" "$tag_version"

    # Build FPM
    printf "  → Building FPM...\n"
    DOCKER_BUILDKIT=1 docker build \
        --platform $PLATFORM \
        --build-arg PROJECT_NAME=$PROJECT_NAME \
        -t "$IMAGE_NAME:$tag_version" \
        -f $FOLDER/Dockerfile \
        --target "fpm-$target" \
        --secret id=envlocal,src=../.env.local \
        $CONTEXT_DIR

    # Build nginx
    printf "  → Building nginx...\n"
    if [[ $PROJECT_NAME == *"diplan"* ]]; then
        nginx_image="$IMAGE_NAME/nginx"
    else
        nginx_image="$IMAGE_NAME-nginx"
    fi

    DOCKER_BUILDKIT=1 docker build \
        --platform $PLATFORM \
        --build-arg PROJECT_NAME=$PROJECT_NAME \
        -t "$nginx_image:$tag_version" \
        -f $FOLDER/Dockerfile \
        --target "nginx-$target" \
        $CONTEXT_DIR
done

rm -rf $CONTEXT_DIR

# Push if requested
if [[ $PUSH_IMAGES == "push" ]]; then
    printf "\n=== Pushing images ===\n"
    for target in $TARGETS; do
        if [ "$target" = "dev" ]; then
            tag_version="${VERSION}-dev"
        else
            tag_version="${VERSION}"
        fi

        if [[ $PROJECT_NAME == *"diplan"* ]]; then
            nginx_image="$IMAGE_NAME/nginx"
        else
            nginx_image="$IMAGE_NAME-nginx"
        fi

        printf "  → Pushing %s...\n" "$tag_version"
        docker push "$IMAGE_NAME:$tag_version"
        docker push "$nginx_image:$tag_version"
    done
fi

printf "\n✅ Build completed successfully\n"
