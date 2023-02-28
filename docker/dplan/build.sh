#!/usr/bin/env bash

cd $(dirname $0)

if [ $# -lt 1 ]
then
  echo "You need to specify a project name"
  exit 2
fi

#image=$1
repository=$1
version=$2
projectname=$3
context=.context

if [ ! -d "projects/$projectname" ]
then
  echo "Project name does not exist"
  exit 2
fi

projectsfolder="projects"

printf "Building %s...\n" $image

if [ -d $context ]
then
rm -rf $context
fi

mkdir -p $context/{bin,projects}

rsync --files-from=rsyncInclude.txt -arz ../.. $context
printf "Stage %s..\n" 1
rsync -az ../../bin/$projectname $context/bin/$projectname
printf "Stage %s..\n" 4
cp -r $projectsfolder/$projectname/* $context
printf "Stage %s..\n" 5
rsync --exclude-from=rsyncExcludeProject.txt -az ../../projects/$projectname $context/projects
printf "Stage %s..\n" 6
#if hash tree
#then
#tree $context
#else
#ls -l $context
#fi

#docker build --build-arg PROJECT_NAME=$projectname -t $repository/$image:$version $context

#rm -rf $context

if [[ $5 == "push" ]]
then
docker push $repository/$image:$version
fi

