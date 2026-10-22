#!/usr/bin/env bash
# Lints the two PHP files and runs the checks. No network calls, no files written.
set -euo pipefail

php_bin="${PHP_BIN:-php}"
cd "$(dirname "${BASH_SOURCE[0]}")/../worked-example"

if ! command -v "$php_bin" >/dev/null 2>&1; then
  echo "PHP was not found. Install PHP 8.0 or later with the curl extension, or use the Docker command in the README." >&2
  exit 2
fi
if ! "$php_bin" -r 'exit(PHP_VERSION_ID >= 80000 && PHP_INT_SIZE === 8 ? 0 : 1);'; then
  echo "This example needs 64-bit PHP 8.0 or later; found: $("$php_bin" -r 'echo PHP_VERSION, " (", PHP_INT_SIZE * 8, "-bit)";')." >&2
  exit 2
fi
if ! "$php_bin" -r 'exit(extension_loaded("curl") ? 0 : 1);'; then
  echo "The PHP curl extension is not loaded. Enable ext-curl and run again." >&2
  exit 2
fi

"$php_bin" -l Deadline.php
"$php_bin" -l check.php
"$php_bin" check.php
