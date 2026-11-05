#!/bin/sh
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
PHP_IMAGE=${UME_PHP_IMAGE:-php:8.1-cli}

cd "$ROOT_DIR"

docker run --rm \
    --volume "$ROOT_DIR:/app:ro" \
    --workdir /app \
    "$PHP_IMAGE" \
    sh -lc '
        set -eu

        find . \
            -path "./.git" -prune -o \
            -path "./.codex" -prune -o \
            -path "./build" -prune -o \
            -path "./dist" -prune -o \
            -path "./node_modules" -prune -o \
            -path "./vendor" -prune -o \
            -type f -name "*.php" -print |
            while IFS= read -r file; do
                php -l "$file" >/dev/null
            done
    ' >/dev/null

echo "PHP syntax check passed on $PHP_IMAGE."
