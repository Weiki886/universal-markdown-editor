#!/bin/sh
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
PLUGIN_SLUG=$(basename "$ROOT_DIR")
WP_ENV="$ROOT_DIR/node_modules/.bin/wp-env"

cd "$ROOT_DIR"

if [ ! -x "$WP_ENV" ]; then
    echo "wp-env is missing. Run npm ci first." >&2
    exit 1
fi

npm run build
"$WP_ENV" start

if "$WP_ENV" run cli wp plugin is-active "$PLUGIN_SLUG" >/dev/null 2>&1; then
    "$WP_ENV" run cli wp plugin deactivate "$PLUGIN_SLUG" --quiet
fi

activation_output=$("$WP_ENV" run cli wp plugin activate "$PLUGIN_SLUG" 2>&1)
printf '%s\n' "$activation_output"

if printf '%s\n' "$activation_output" | grep -Eq '(Warning|Fatal error|Parse error):'; then
    echo "Plugin activation emitted a PHP error." >&2
    exit 1
fi

if ! smoke_output=$(
    "$WP_ENV" run cli wp eval-file \
        "wp-content/plugins/$PLUGIN_SLUG/tests/integration/plugin-smoke.php" 2>&1
); then
    printf '%s\n' "$smoke_output"
    echo "The WordPress integration smoke test failed." >&2
    exit 1
fi

printf '%s\n' "$smoke_output"

if printf '%s\n' "$smoke_output" | grep -Eq '(Warning|Fatal error|Parse error):'; then
    echo "The integration smoke test emitted a PHP error." >&2
    exit 1
fi

if ! markdown_core_output=$(
    "$WP_ENV" run cli wp eval-file \
        "wp-content/plugins/$PLUGIN_SLUG/tests/integration/markdown-core.php" 2>&1
); then
    printf '%s\n' "$markdown_core_output"
    echo "The Markdown core integration test failed." >&2
    exit 1
fi

printf '%s\n' "$markdown_core_output"

if ! markdown_preview_output=$(
    "$WP_ENV" run cli wp eval-file \
        "wp-content/plugins/$PLUGIN_SLUG/tests/integration/markdown-preview.php" 2>&1
); then
    printf '%s\n' "$markdown_preview_output"
    echo "The Markdown preview integration test failed." >&2
    exit 1
fi

printf '%s\n' "$markdown_preview_output"

if ! markdown_storage_output=$(
    "$WP_ENV" run cli wp eval-file \
        "wp-content/plugins/$PLUGIN_SLUG/tests/integration/markdown-storage.php" 2>&1
); then
    printf '%s\n' "$markdown_storage_output"
    echo "The Markdown storage integration test failed." >&2
    exit 1
fi

printf '%s\n' "$markdown_storage_output"

if printf '%s\n' "$markdown_preview_output" | grep -Eq '(Warning|Fatal error|Parse error):'; then
    echo "The Markdown preview integration test emitted a PHP error." >&2
    exit 1
fi

if printf '%s\n' "$markdown_core_output" | grep -Eq '(Warning|Fatal error|Parse error):'; then
    echo "The Markdown core integration test emitted a PHP error." >&2
    exit 1
fi

if printf '%s\n' "$markdown_storage_output" | grep -Eq '(Warning|Fatal error|Parse error):'; then
    echo "The Markdown storage integration test emitted a PHP error." >&2
    exit 1
fi
