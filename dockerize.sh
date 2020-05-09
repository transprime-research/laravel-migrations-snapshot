#!/bin/sh
command=$1

docker-compose up -d --remove-orphans

if [ "$command" != '--no_rebuild' ]
then
  printf "Rebuilding docker image because of missing --no_rebuild flag\n"
  docker-compose build
fi

docker-compose exec snapshot composer install
