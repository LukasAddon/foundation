#!/usr/bin/env bash

set -eu

php_version=8.1

if ! type docker > /dev/null; then
    echo "Docker is required to run this command."
    exit 1
fi

if [ ! -d "vendor" ]; then
    echo "# No vendor dir detected, installing dependencies first then"

    docker run \
        -it \
        --rm \
        -w /mnt/tmp \
        -v `pwd`:/mnt/tmp \
        -e COMPOSER_MEMORY_LIMIT=-1 \
        modera/php:${php_version} "composer install"
fi

docker run \
    -it \
    --rm \
    -w /mnt/tmp \
    -v `pwd`:/mnt/tmp \
    modera/php:${php_version} "composer pre-commit"

exit_code=$?

exit $exit_code
