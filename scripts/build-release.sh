#!/bin/sh
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
DIST_DIR="$ROOT_DIR/dist"
BUILD_DIR="$DIST_DIR/.build"
RELEASE_DIR="$DIST_DIR/universal-markdown-editor"
COMPOSER_IMAGE=${UME_COMPOSER_IMAGE:-composer:2.8.12}
PHP_IMAGE=${UME_PHP_IMAGE:-php:8.1-cli}

cd "$ROOT_DIR"

node -e 'require("node:fs").rmSync(process.argv[1], { recursive: true, force: true })' "$BUILD_DIR"
node -e 'require("node:fs").rmSync(process.argv[1], { recursive: true, force: true })' "$RELEASE_DIR"
mkdir -p "$BUILD_DIR" "$RELEASE_DIR"

cp composer.json composer.lock "$BUILD_DIR/"

echo "Installing production Composer dependencies..."
docker run --rm \
    --user "$(id -u):$(id -g)" \
    --volume "$BUILD_DIR:/app" \
    --workdir /app \
    --env COMPOSER_CACHE_DIR=/tmp/composer-cache \
    --env COMPOSER_HOME=/tmp/composer-home \
    "$COMPOSER_IMAGE" install --no-dev --no-interaction --prefer-dist

echo "Isolating production Composer namespaces..."
docker run --rm \
    --user "$(id -u):$(id -g)" \
    --volume "$ROOT_DIR:/app" \
    --workdir /app \
    "$COMPOSER_IMAGE" \
    php vendor/bin/php-scoper add-prefix \
    "dist/.build/vendor" \
    --output-dir="dist/universal-markdown-editor/vendor" \
    --prefix=UME_Vendor \
    --force

cp composer.json composer.lock "$RELEASE_DIR/"

echo "Regenerating the isolated Composer autoloader..."
docker run --rm \
    --user "$(id -u):$(id -g)" \
    --volume "$ROOT_DIR:/app" \
    --workdir /app/dist/universal-markdown-editor \
    --env COMPOSER_CACHE_DIR=/tmp/composer-cache \
    --env COMPOSER_HOME=/tmp/composer-home \
    "$COMPOSER_IMAGE" dump-autoload --no-dev --classmap-authoritative

node -e 'const fs=require("node:fs"); for (const file of ["composer.json","composer.lock"]) fs.rmSync(process.argv[1] + "/" + file, {force:true})' "$RELEASE_DIR"

echo "Building admin assets..."
npm run build

mkdir -p "$RELEASE_DIR/build"
cp -R build/. "$RELEASE_DIR/build/"
cp universal-markdown-editor.php LICENSE README.md CHANGELOG.md "$RELEASE_DIR/"
cp -R src "$RELEASE_DIR/src"
mkdir -p "$RELEASE_DIR/docs"
cp docs/dependency-licenses.md "$RELEASE_DIR/docs/dependency-licenses.md"

echo "Verifying PHP 8.1 runtime and namespace isolation..."
docker run --rm \
    --volume "$RELEASE_DIR:/plugin:ro" \
    --workdir /plugin \
    "$PHP_IMAGE" \
    php -r '
        require "/plugin/vendor/autoload.php";

        if (!class_exists("UME_Vendor\\League\\CommonMark\\CommonMarkConverter")) {
            fwrite(STDERR, "Scoped CommonMark class was not found.\n");
            exit(1);
        }

        if (class_exists("League\\CommonMark\\CommonMarkConverter")) {
            fwrite(STDERR, "Unscoped CommonMark class remains loadable.\n");
            exit(1);
        }

        $converter = new UME_Vendor\League\CommonMark\CommonMarkConverter();
        $html = trim((string) $converter->convert("# Build verification"));

        if ($html !== "<h1>Build verification</h1>") {
            fwrite(STDERR, "Scoped CommonMark conversion failed: " . $html . "\n");
            exit(1);
        }

        echo "Scoped CommonMark runtime is available on PHP " . PHP_VERSION . ".\n";
    '

echo "Checking release contents..."
for forbidden in \
    .DS_Store \
    .codex \
    node_modules \
    tests \
    composer.json \
    composer.lock \
    vendor/humbug \
    vendor/phpunit
do
    if [ -e "$RELEASE_DIR/$forbidden" ]; then
        echo "Forbidden release path: $forbidden" >&2
        exit 1
    fi
done

docker run --rm \
    --volume "$RELEASE_DIR:/plugin:ro" \
    --workdir /plugin \
    "$PHP_IMAGE" \
    sh -lc '
        set -eu

        find . -type f -name "*.php" -print |
            while IFS= read -r file; do
                php -l "$file" >/dev/null
            done
    '

echo "Release directory ready: dist/universal-markdown-editor"
