#!/usr/bin/env bash

set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

while IFS= read -r -d '' php_file; do
	php -l "$php_file"
done < <(
	find "$project_root" -type f -name '*.php' \
		-not -path '*/.git/*' \
		-not -path '*/build/*' \
		-not -path '*/worktrees/*' \
		-print0
)

php "$project_root/tests/run.php"
