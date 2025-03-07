#!/usr/bin/env bash

cd $(dirname $0)

if [ $# -lt 4 ]
then
  echo "Usage ./build.sh demosplan-production <imagename> <version> <projectname> <?push>"
  exit 2
fi

projectsfolder="projects"

folder=$1
imagename=$2
version=$3
projectname=$4
context=.context

printf "Building %s...\n" $folder

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
DOCKER_BUILDKIT=1 docker build --build-arg PROJECT_NAME=$projectname --secret id=envlocal,src=../.env.local -t $imagename:$version -f $folder/Dockerfile --target fpm $context
DOCKER_BUILDKIT=1 docker build --build-arg PROJECT_NAME=$projectname -t $imagename/nginx:$version -f $folder/Dockerfile --target nginx $context
# / is not always allowed in image names
DOCKER_BUILDKIT=1 docker build --build-arg PROJECT_NAME=$projectname -t $imagename-nginx:$version -f $folder/Dockerfile --target nginx $context

rm -rf $context

if [[ $5 == "push" ]]
then
docker push $imagename:$version
docker push $imagename/nginx:$version
fi
