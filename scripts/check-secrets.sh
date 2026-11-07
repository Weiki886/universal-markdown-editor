#!/bin/sh
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
GITLEAKS_BIN=${GITLEAKS_BIN:-gitleaks}

cd "$ROOT_DIR"

if ! command -v "$GITLEAKS_BIN" >/dev/null 2>&1; then
    echo "Gitleaks is required. Install the pinned version from the CI workflow." >&2
    exit 1
fi

git ls-files --cached --others --exclude-standard -z |
    xargs -0 -r -n 1 "$GITLEAKS_BIN" dir --redact --no-banner --log-level error

echo "Credential scan passed for Git-tracked and non-ignored source files."
