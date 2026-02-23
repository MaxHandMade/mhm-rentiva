#!/usr/bin/env bash
set -euo pipefail

# Finds test files that produce non-zero exit or suspicious output markers.
# Usage:
#   tests/bin/phpunit-bisect.sh [/path/to/php]

PHP_BIN="${1:-php}"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

cd "$ROOT_DIR"

if ! command -v rg >/dev/null 2>&1; then
  echo "rg is required"
  exit 1
fi

echo "Using PHP: $PHP_BIN"

while IFS= read -r test_file; do
  echo "=== $test_file ==="
  set +e
  output="$("$PHP_BIN" ./vendor/bin/phpunit -c phpunit.xml "$test_file" --colors=never 2>&1)"
  status=$?
  set -e

  if [[ $status -ne 0 ]]; then
    echo "FAIL: exit $status"
    echo "$output" | tail -n 80
    continue
  fi

  if echo "$output" | rg -q -- '-1'; then
    echo "WARN: output contains '-1' marker"
    echo "$output" | tail -n 40
  else
    echo "OK"
  fi
done < <(rg --files tests -g '*Test.php' | sort)
