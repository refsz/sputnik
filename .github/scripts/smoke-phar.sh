#!/usr/bin/env bash
#
# Exercise a built PHAR the way a user does: scaffold a project, run the
# example, then run a task that uses the two features a unit test cannot cover
# from inside the archive - argv execution and secret masking.
#
# Usage: .github/scripts/smoke-phar.sh build/sputnik.phar

set -euo pipefail

phar=${1:-build/sputnik.phar}

if [ ! -f "$phar" ]; then
    echo "No PHAR at $phar" >&2
    exit 1
fi

phar=$(cd "$(dirname "$phar")" && pwd)/$(basename "$phar")
dir=$(mktemp -d)
trap 'rm -rf "$dir"' EXIT

run() {
    php "$phar" --working-dir="$dir" "$@"
}

echo "== version is resolved"
run --version
run --version | grep -qv '@package_version@'

echo "== the scaffold validates and runs"
run init
run list >/dev/null
run example | grep -q 'Hello, World!'

echo "== a config the validator rejects fails loudly"
cp "$dir/.sputnik.dist.neon" "$dir/.sputnik.dist.neon.bak"
printf '\nenvironment:\n    executor: "ddev exec {command}"\n' >>"$dir/.sputnik.dist.neon"
if run list >/dev/null 2>&1; then
    echo "The pre-0.2 executor string was accepted" >&2
    exit 1
fi
mv "$dir/.sputnik.dist.neon.bak" "$dir/.sputnik.dist.neon"

echo "== argv survives and secrets are masked"
cat >"$dir/.sputnik.dist.neon" <<'NEON'
tasks:
    directories:
        - sputnik

variables:
    secrets:
        smoke_token: sputnik-smoke-secret
NEON

cat >"$dir/sputnik/SmokeTask.php" <<'PHP'
<?php

declare(strict_types=1);

use Sputnik\Attribute\Task;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskInterface;
use Sputnik\Task\TaskResult;

#[Task(name: 'smoke', description: 'Smoke test')]
final class SmokeTask implements TaskInterface
{
    public function __invoke(TaskContext $ctx): TaskResult
    {
        $ctx->exec(['printf', '%s', 'literal ; not a separator']);
        $ctx->exec(['printf', '%s', 'token {{ smoke_token }}']);
        $ctx->writeln('secret in a message: ' . $ctx->get('smoke_token'));

        return TaskResult::success('smoked');
    }
}
PHP

output=$(run smoke)
echo "$output"

expect() {
    if ! echo "$output" | grep -q "$1"; then
        echo "FAIL: $2" >&2
        exit 1
    fi
}

# A shell would have split at the semicolon and lost the second half.
expect 'literal ; not a separator' 'the argument was split, so argv went through a shell'

# The value is masked on the echoed command line, in the program output, and in
# a message the task writes itself.
expect 'token \*\*\*' 'the secret was not masked'

if echo "$output" | grep -q 'sputnik-smoke-secret'; then
    echo 'FAIL: the secret reached the terminal unmasked' >&2
    exit 1
fi

echo "== ok"
