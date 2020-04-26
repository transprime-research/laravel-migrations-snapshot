#!/bin/sh
command=$1

docker-compose up -d --remove-orphans

if [ "$command" != '--no_rebuild' ]
then
  printf "Rebuilding docker image because of missing --no_rebuild flag\n"
  docker-compose build
fi

docker-compose exec app composer install

if [ "$command" = '--reset_migration' ]
then
    docker-compose exec app php artisan migrate:fresh
else
    docker-compose exec app php artisan migrate
fi
