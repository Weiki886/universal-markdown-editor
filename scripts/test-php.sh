#!/bin/sh
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
PHP_IMAGE=${UME_PHP_IMAGE:-php:8.1-cli}

cd "$ROOT_DIR"

if [ ! -f vendor/autoload.php ]; then
    "$ROOT_DIR/bin/composer" install --no-interaction --prefer-dist
fi

sh scripts/check-php-syntax.sh

docker run --rm \
    --volume "$ROOT_DIR:/app" \
    --workdir /app \
    "$PHP_IMAGE" php vendor/bin/phpunit --configuration phpunit.xml.dist
