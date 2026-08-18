# Secret Masking Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Every value a declared secret resolves to is replaced with `***` in everything Sputnik prints, without changing the data a task receives.

**Architecture:** Secrets are declared in a `variables.secrets` config section and resolved lazily by `VariableResolver`. Each resolved value is handed to a shared `SecretRegistry`. A `SecretRedactor` built on that registry replaces known values in text, and a `RedactingOutput` decorator around the console output applies it to every channel Sputnik writes to.

**Tech Stack:** PHP 8.2+, Symfony Console 7, Nette DI (compiled container), PHPUnit 11, PHPStan (max level), php-cs-fixer, Rector.

**Spec:** `docs/superpowers/specs/2026-08-18-secret-masking-design.md`

## Global Constraints

- PHP `^8.2` — no syntax newer than 8.2. `new` in parameter defaults is allowed (8.1+), `readonly` promoted properties are allowed (8.1+); readonly properties may **not** be reassigned in `__clone()` (8.3+ only).
- `declare(strict_types=1);` in every PHP file, immediately after the opening tag.
- PER Coding Style 3.0. Run `vendor/bin/php-cs-fixer fix` before each commit; CI runs `--dry-run --diff`.
- PHPStan must stay at zero errors: `vendor/bin/phpstan analyse --no-progress`. Never add a baseline entry or an inline ignore.
- Rector must stay clean: `vendor/bin/rector --dry-run`.
- Commit subjects follow `[TYPE] message` with TYPE from `BC BUILD CHORE CI DOCS FEAT FIX PERF REFACTOR STYLE TEST`. A local hook rejects anything else. Use `git commit -F -` with a heredoc; `-m "$(printf ...)"` is rejected by the hook because it inspects the command text.
- Comments only where the code cannot express the *why*. No DocBlock that merely repeats the signature.
- The placeholder is exactly `***`. The word-boundary threshold is exactly 8 characters.
- Tests live under `tests/Unit/...`, `tests/Integration/...`, `tests/E2E/...` mirroring `src/`. Test doubles belong in `tests/Support/Doubles/`.

---

### Task 1: SecretRegistry

Holds which names are secret and which values have been resolved, and collects the diagnostics other code prints.

**Files:**

- Create: `src/Secret/SecretRegistry.php`
- Test: `tests/Unit/Secret/SecretRegistryTest.php`

**Interfaces:**

- Consumes: `Sputnik\Template\VerbatimValueFormatter` (existing) — `format(mixed $value): string`, turns booleans into `true`/`false` and arrays into JSON, exactly as the renderer inserts them.
- Produces:
    - `declareSecrets(array $names): void` — `list<string>`
    - `isSecret(string $name): bool`
    - `remember(string $name, mixed $value): void`
    - `values(): array` — `list<string>`, unique, longest first
    - `takeDiagnostics(): array` — `list<string>`, drains the collected messages

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Secret/SecretRegistryTest.php`:

```php
<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Secret;

use PHPUnit\Framework\TestCase;
use Sputnik\Secret\SecretRegistry;

final class SecretRegistryTest extends TestCase
{
    private SecretRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new SecretRegistry();
        $this->registry->declareSecrets(['apiToken', 'dbPassword']);
    }

    public function testDeclaredNamesAreRecognised(): void
    {
        $this->assertTrue($this->registry->isSecret('apiToken'));
        $this->assertFalse($this->registry->isSecret('dbHost'));
    }

    public function testRememberedValueIsReturned(): void
    {
        $this->registry->remember('apiToken', 'ghp_abcdefghij');

        $this->assertSame(['ghp_abcdefghij'], $this->registry->values());
    }

    public function testValuesAreOrderedLongestFirst(): void
    {
        $this->registry->remember('apiToken', 'ghp_abcdefghij');
        $this->registry->remember('dbPassword', 'ghp_abc');

        $this->assertSame(['ghp_abcdefghij', 'ghp_abc'], $this->registry->values());
    }

    public function testBooleanValueIsRememberedAsRenderedText(): void
    {
        $this->registry->declareSecrets(['xdebugEnabled']);
        $this->registry->remember('xdebugEnabled', false);

        $this->assertSame(['false'], $this->registry->values());
    }

    public function testNullValueIsNotRememberedAndReportsDiagnostic(): void
    {
        $this->registry->remember('apiToken', null);

        $this->assertSame([], $this->registry->values());
        $this->assertSame(["secret 'apiToken' could not be resolved"], $this->registry->takeDiagnostics());
    }

    public function testEmptyValueIsNotRememberedAndReportsDiagnostic(): void
    {
        $this->registry->remember('apiToken', '');

        $this->assertSame([], $this->registry->values());
        $this->assertSame(
            ["secret 'apiToken' resolves to an empty value and cannot be masked"],
            $this->registry->takeDiagnostics(),
        );
    }

    public function testShortValueIsRememberedAndReportsDiagnostic(): void
    {
        $this->registry->remember('apiToken', 'abc');

        $this->assertSame(['abc'], $this->registry->values());
        $this->assertSame(
            ["secret 'apiToken' has a short value; unrelated output may be masked too"],
            $this->registry->takeDiagnostics(),
        );
    }

    public function testDiagnosticsAreReportedOncePerName(): void
    {
        $this->registry->remember('apiToken', 'abc');
        $this->registry->remember('apiToken', 'abc');

        $this->assertCount(1, $this->registry->takeDiagnostics());
    }

    public function testTakeDiagnosticsDrains(): void
    {
        $this->registry->remember('apiToken', 'abc');
        $this->registry->takeDiagnostics();

        $this->assertSame([], $this->registry->takeDiagnostics());
    }

    public function testLongValueReportsNoDiagnostic(): void
    {
        $this->registry->remember('apiToken', 'ghp_abcdefghij');

        $this->assertSame([], $this->registry->takeDiagnostics());
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Secret/SecretRegistryTest.php`
Expected: FAIL — `Class "Sputnik\Secret\SecretRegistry" not found`.

- [ ] **Step 3: Write the implementation**

Create `src/Secret/SecretRegistry.php`:

```php
<?php

declare(strict_types=1);

namespace Sputnik\Secret;

use Sputnik\Template\VerbatimValueFormatter;

final class SecretRegistry
{
    private const SHORT_VALUE_LENGTH = 8;

    /**
     * @var array<string, true>
     */
    private array $names = [];

    /**
     * @var array<string, string>
     */
    private array $values = [];

    /**
     * @var array<string, string>
     */
    private array $diagnostics = [];

    private readonly VerbatimValueFormatter $formatter;

    public function __construct()
    {
        $this->formatter = new VerbatimValueFormatter();
    }

    /**
     * @param list<string> $names
     */
    public function declareSecrets(array $names): void
    {
        foreach ($names as $name) {
            $this->names[$name] = true;
        }
    }

    public function isSecret(string $name): bool
    {
        return isset($this->names[$name]);
    }

    public function remember(string $name, mixed $value): void
    {
        if ($value === null) {
            $this->addDiagnostic($name, \sprintf("secret '%s' could not be resolved", $name));

            return;
        }

        $text = $this->formatter->format($value);

        if ($text === '') {
            $this->addDiagnostic(
                $name,
                \sprintf("secret '%s' resolves to an empty value and cannot be masked", $name),
            );

            return;
        }

        $this->values[$name] = $text;

        if (\strlen($text) < self::SHORT_VALUE_LENGTH) {
            $this->addDiagnostic(
                $name,
                \sprintf("secret '%s' has a short value; unrelated output may be masked too", $name),
            );
        }
    }

    /**
     * @return list<string>
     */
    public function values(): array
    {
        $values = array_values(array_unique($this->values));

        usort($values, static fn (string $a, string $b): int => \strlen($b) <=> \strlen($a));

        return $values;
    }

    /**
     * @return list<string>
     */
    public function takeDiagnostics(): array
    {
        $messages = array_values($this->diagnostics);
        $this->diagnostics = [];

        return $messages;
    }

    private function addDiagnostic(string $name, string $message): void
    {
        $this->diagnostics[$name] = $message;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Secret/SecretRegistryTest.php`
Expected: PASS, 10 tests.

- [ ] **Step 5: Check style and static analysis**

Run: `vendor/bin/php-cs-fixer fix && vendor/bin/phpstan analyse --no-progress`
Expected: fixer reports 0 or fixes formatting only; PHPStan `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add src/Secret/SecretRegistry.php tests/Unit/Secret/SecretRegistryTest.php
git commit -F - <<'MSG'
[FEAT] add the secret registry

Holds the declared secret names and the values they resolve to, formatted the
same way the template renderer inserts them, plus the diagnostics for values
that cannot be masked or are short enough to over-match.
MSG
```

---

### Task 2: SecretRedactor

Replaces known secret values in text.

**Files:**

- Create: `src/Secret/SecretRedactor.php`
- Test: `tests/Unit/Secret/SecretRedactorTest.php`

**Interfaces:**

- Consumes: `SecretRegistry::values(): list<string>` from Task 1.
- Produces: `SecretRedactor::__construct(SecretRegistry $registry)` and `redact(string $text): string`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Secret/SecretRedactorTest.php`:

```php
<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Secret;

use PHPUnit\Framework\TestCase;
use Sputnik\Secret\SecretRedactor;
use Sputnik\Secret\SecretRegistry;

final class SecretRedactorTest extends TestCase
{
    private SecretRegistry $registry;

    private SecretRedactor $redactor;

    protected function setUp(): void
    {
        $this->registry = new SecretRegistry();
        $this->registry->declareSecrets(['apiToken', 'pin', 'quoted']);
        $this->redactor = new SecretRedactor($this->registry);
    }

    public function testTextWithoutSecretsIsUnchanged(): void
    {
        $this->registry->remember('apiToken', 'ghp_abcdefghij');

        $this->assertSame('nothing to see', $this->redactor->redact('nothing to see'));
    }

    public function testLongValueIsReplacedAnywhere(): void
    {
        $this->registry->remember('apiToken', 'ghp_abcdefghij');

        $this->assertSame(
            'Authorization: Bearer ***',
            $this->redactor->redact('Authorization: Bearer ghp_abcdefghij'),
        );
    }

    public function testLongValueIsReplacedInsideAnotherWord(): void
    {
        $this->registry->remember('apiToken', 'ghp_abcdefghij');

        $this->assertSame(
            'url=https://x/***?v=1',
            $this->redactor->redact('url=https://x/ghp_abcdefghij?v=1'),
        );
    }

    public function testShortValueIsReplacedOnlyAtWordBoundaries(): void
    {
        $this->registry->remember('pin', 'abc');

        $this->assertSame(
            'value *** stays abcdef and my-abc',
            $this->redactor->redact('value abc stays abcdef and my-abc'),
        );
    }

    public function testQuotedShellFormIsReplaced(): void
    {
        $this->registry->remember('quoted', "pa'ss word");

        $this->assertSame(
            'run --pw=***',
            $this->redactor->redact('run --pw=' . escapeshellarg("pa'ss word")),
        );
    }

    public function testLongerValueIsReplacedFirst(): void
    {
        $this->registry->declareSecrets(['outer', 'inner']);
        $this->registry->remember('outer', 'abcdefghij');
        $this->registry->remember('inner', 'abcdefgh');

        $this->assertSame('***', $this->redactor->redact('abcdefghij'));
    }

    public function testValuesWithRegexMetaCharactersAreLiteral(): void
    {
        $this->registry->declareSecrets(['weird']);
        $this->registry->remember('weird', 'a.c');

        $this->assertSame('*** but abc stays', $this->redactor->redact('a.c but abc stays'));
    }

    public function testBooleanValueIsReplacedAtWordBoundaries(): void
    {
        $this->registry->declareSecrets(['flag']);
        $this->registry->remember('flag', false);

        $this->assertSame('xdebug: ***', $this->redactor->redact('xdebug: false'));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Secret/SecretRedactorTest.php`
Expected: FAIL — `Class "Sputnik\Secret\SecretRedactor" not found`.

- [ ] **Step 3: Write the implementation**

Create `src/Secret/SecretRedactor.php`:

```php
<?php

declare(strict_types=1);

namespace Sputnik\Secret;

final class SecretRedactor
{
    private const PLACEHOLDER = '***';

    private const WORD_BOUNDARY_LENGTH = 8;

    public function __construct(
        private readonly SecretRegistry $registry,
    ) {
    }

    public function redact(string $text): string
    {
        foreach ($this->registry->values() as $value) {
            foreach ($this->variantsOf($value) as $variant) {
                $text = $this->replace($text, $variant);
            }
        }

        return $text;
    }

    /**
     * The shell-escaped form only differs when the value contains a quote, in
     * which case the raw value no longer occurs in the escaped string.
     *
     * @return list<string>
     */
    private function variantsOf(string $value): array
    {
        $escaped = escapeshellarg($value);

        if (!str_contains($escaped, $value)) {
            return [$escaped, $value];
        }

        return [$value];
    }

    private function replace(string $text, string $value): string
    {
        if (\strlen($value) >= self::WORD_BOUNDARY_LENGTH) {
            return str_replace($value, self::PLACEHOLDER, $text);
        }

        $pattern = '/(?<![\w-])' . preg_quote($value, '/') . '(?![\w-])/';

        return preg_replace($pattern, self::PLACEHOLDER, $text) ?? $text;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Secret/SecretRedactorTest.php`
Expected: PASS, 8 tests.

Note on `testQuotedShellFormIsReplaced`: `escapeshellarg("pa'ss word")` is `'pa'\''ss word'`, which does not contain the raw value, so both variants are replaced and the escaped one is tried first.

- [ ] **Step 5: Check style and static analysis**

Run: `vendor/bin/php-cs-fixer fix && vendor/bin/phpstan analyse --no-progress`
Expected: PHPStan `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add src/Secret/SecretRedactor.php tests/Unit/Secret/SecretRedactorTest.php
git commit -F - <<'MSG'
[FEAT] add the secret redactor

Replaces every known secret value with *** , longest value first so a secret
containing another leaves no fragment. Values of eight characters or more are
replaced anywhere, shorter ones only at word boundaries so that unrelated
output survives.
MSG
```

---

### Task 3: Output decorators

Apply redaction to every console channel.

**Files:**

- Create: `src/Secret/RedactingOutput.php`
- Create: `src/Secret/RedactingConsoleOutput.php`
- Test: `tests/Unit/Secret/RedactingOutputTest.php`

**Interfaces:**

- Consumes: `SecretRedactor::redact(string): string` from Task 2.
- Produces:
    - `RedactingOutput::__construct(OutputInterface $inner, SecretRedactor $redactor)`, implements `Symfony\Component\Console\Output\OutputInterface`
    - `RedactingConsoleOutput::__construct(ConsoleOutputInterface $inner, SecretRedactor $redactor)`, extends `RedactingOutput`, implements `ConsoleOutputInterface`

Redaction has to sit at this layer rather than in an `OutputFormatter`, because `ShellExecutor::streamOutput()` writes with `OutputInterface::OUTPUT_RAW`, which bypasses formatting.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Secret/RedactingOutputTest.php`:

```php
<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Secret;

use PHPUnit\Framework\TestCase;
use Sputnik\Secret\RedactingConsoleOutput;
use Sputnik\Secret\RedactingOutput;
use Sputnik\Secret\SecretRedactor;
use Sputnik\Secret\SecretRegistry;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class RedactingOutputTest extends TestCase
{
    private BufferedOutput $inner;

    private RedactingOutput $output;

    protected function setUp(): void
    {
        $registry = new SecretRegistry();
        $registry->declareSecrets(['apiToken']);
        $registry->remember('apiToken', 'ghp_abcdefghij');

        $this->inner = new BufferedOutput();
        $this->output = new RedactingOutput($this->inner, new SecretRedactor($registry));
    }

    public function testWritelnIsRedacted(): void
    {
        $this->output->writeln('token ghp_abcdefghij');

        $this->assertSame("token ***\n", $this->inner->fetch());
    }

    public function testWriteIsRedacted(): void
    {
        $this->output->write('token ghp_abcdefghij');

        $this->assertSame('token ***', $this->inner->fetch());
    }

    public function testArrayOfMessagesIsRedacted(): void
    {
        $this->output->writeln(['a ghp_abcdefghij', 'b']);

        $this->assertSame("a ***\nb\n", $this->inner->fetch());
    }

    public function testRawOutputIsRedacted(): void
    {
        $this->output->write('ghp_abcdefghij', false, OutputInterface::OUTPUT_RAW);

        $this->assertSame('***', $this->inner->fetch());
    }

    public function testStyleTagsSurvive(): void
    {
        $this->output->writeln('<info>ok</info>');

        $this->assertSame("ok\n", $this->inner->fetch());
    }

    public function testVerbosityIsDelegated(): void
    {
        $this->output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);

        $this->assertSame(OutputInterface::VERBOSITY_DEBUG, $this->inner->getVerbosity());
        $this->assertSame(OutputInterface::VERBOSITY_DEBUG, $this->output->getVerbosity());
        $this->assertTrue($this->output->isDebug());
    }

    public function testFormatterIsDelegated(): void
    {
        $this->assertSame($this->inner->getFormatter(), $this->output->getFormatter());
    }

    public function testConsoleVariantRedactsErrorOutput(): void
    {
        $registry = new SecretRegistry();
        $registry->declareSecrets(['apiToken']);
        $registry->remember('apiToken', 'ghp_abcdefghij');

        $console = new ConsoleOutput();
        $decorated = new RedactingConsoleOutput($console, new SecretRedactor($registry));

        $this->assertInstanceOf(RedactingOutput::class, $decorated->getErrorOutput());
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Secret/RedactingOutputTest.php`
Expected: FAIL — `Class "Sputnik\Secret\RedactingOutput" not found`.

- [ ] **Step 3: Write the implementations**

Create `src/Secret/RedactingOutput.php`:

```php
<?php

declare(strict_types=1);

namespace Sputnik\Secret;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RedactingOutput implements OutputInterface
{
    public function __construct(
        private readonly OutputInterface $inner,
        private readonly SecretRedactor $redactor,
    ) {
    }

    public function write(string|iterable $messages, bool $newline = false, int $options = 0): void
    {
        $this->inner->write($this->redactMessages($messages), $newline, $options);
    }

    public function writeln(string|iterable $messages, int $options = 0): void
    {
        $this->inner->writeln($this->redactMessages($messages), $options);
    }

    public function setVerbosity(int $level): void
    {
        $this->inner->setVerbosity($level);
    }

    public function getVerbosity(): int
    {
        return $this->inner->getVerbosity();
    }

    public function isQuiet(): bool
    {
        return $this->inner->isQuiet();
    }

    public function isVerbose(): bool
    {
        return $this->inner->isVerbose();
    }

    public function isVeryVerbose(): bool
    {
        return $this->inner->isVeryVerbose();
    }

    public function isDebug(): bool
    {
        return $this->inner->isDebug();
    }

    public function setDecorated(bool $decorated): void
    {
        $this->inner->setDecorated($decorated);
    }

    public function isDecorated(): bool
    {
        return $this->inner->isDecorated();
    }

    public function setFormatter(OutputFormatterInterface $formatter): void
    {
        $this->inner->setFormatter($formatter);
    }

    public function getFormatter(): OutputFormatterInterface
    {
        return $this->inner->getFormatter();
    }

    protected function redactor(): SecretRedactor
    {
        return $this->redactor;
    }

    /**
     * @param string|iterable<string> $messages
     *
     * @return string|list<string>
     */
    private function redactMessages(string|iterable $messages): string|array
    {
        if (\is_string($messages)) {
            return $this->redactor->redact($messages);
        }

        $redacted = [];
        foreach ($messages as $message) {
            $redacted[] = $this->redactor->redact($message);
        }

        return $redacted;
    }
}
```

Create `src/Secret/RedactingConsoleOutput.php`:

```php
<?php

declare(strict_types=1);

namespace Sputnik\Secret;

use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class RedactingConsoleOutput extends RedactingOutput implements ConsoleOutputInterface
{
    public function __construct(
        private readonly ConsoleOutputInterface $console,
        SecretRedactor $redactor,
    ) {
        parent::__construct($console, $redactor);
    }

    public function getErrorOutput(): OutputInterface
    {
        return new RedactingOutput($this->console->getErrorOutput(), $this->redactor());
    }

    public function setErrorOutput(OutputInterface $error): void
    {
        $this->console->setErrorOutput($error);
    }

    public function section(): ConsoleSectionOutput
    {
        return $this->console->section();
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Secret/RedactingOutputTest.php`
Expected: PASS, 8 tests.

- [ ] **Step 5: Check style and static analysis**

Run: `vendor/bin/php-cs-fixer fix && vendor/bin/phpstan analyse --no-progress`
Expected: PHPStan `[OK] No errors`. If PHPStan complains that `RedactingOutput` is not final, leave it non-final — `RedactingConsoleOutput` extends it deliberately.

- [ ] **Step 6: Commit**

```bash
git add src/Secret/RedactingOutput.php src/Secret/RedactingConsoleOutput.php tests/Unit/Secret/RedactingOutputTest.php
git commit -F - <<'MSG'
[FEAT] add the redacting output decorators

One decorator around OutputInterface covers every channel Sputnik writes to,
including the raw writes the shell executor uses for streamed process output,
which bypass the formatter. The console variant keeps getErrorOutput() so
Symfony still renders errors on stderr.
MSG
```

---

### Task 4: Config section and the env variable type

Make `variables.secrets` readable and teach the dynamic resolver to read environment variables.

**Files:**

- Modify: `src/Config/Configuration.php` — add `getSecrets()` next to `getDynamics()`
- Modify: `src/Variable/DynamicVariableResolver.php` — add the `env` arm to the `match` in `resolve()`
- Test: `tests/Unit/Config/ConfigurationTest.php` (existing file — add cases)
- Test: `tests/Unit/Variable/DynamicVariableResolverTest.php` (existing file — add cases)

**Interfaces:**

- Produces:
    - `Configuration::getSecrets(): array<string, mixed>` — the raw `variables.secrets` map; each value is either a definition array or a scalar literal
    - `DynamicVariableResolver::resolve(['type' => 'env', 'name' => 'DB_PASSWORD'])` returns the environment value or `null`

- [ ] **Step 1: Write the failing tests**

Append to `tests/Unit/Config/ConfigurationTest.php`:

```php
    public function testGetSecretsReturnsTheSection(): void
    {
        $config = new Configuration([
            'variables' => [
                'secrets' => [
                    'apiToken' => ['type' => 'command', 'command' => 'pass show x'],
                    'legacyKey' => 'literal',
                ],
            ],
        ]);

        $this->assertSame(
            [
                'apiToken' => ['type' => 'command', 'command' => 'pass show x'],
                'legacyKey' => 'literal',
            ],
            $config->getSecrets(),
        );
    }

    public function testGetSecretsDefaultsToEmpty(): void
    {
        $this->assertSame([], (new Configuration([]))->getSecrets());
    }
```

Append to `tests/Unit/Variable/DynamicVariableResolverTest.php`:

```php
    public function testEnvTypeReadsTheEnvironmentVariable(): void
    {
        putenv('SPUTNIK_TEST_SECRET=from-env');

        try {
            $value = (new DynamicVariableResolver())->resolve(['type' => 'env', 'name' => 'SPUTNIK_TEST_SECRET']);
        } finally {
            putenv('SPUTNIK_TEST_SECRET');
        }

        $this->assertSame('from-env', $value);
    }

    public function testEnvTypeReturnsNullWhenUnset(): void
    {
        $value = (new DynamicVariableResolver())->resolve(['type' => 'env', 'name' => 'SPUTNIK_TEST_MISSING']);

        $this->assertNull($value);
    }

    public function testEnvTypeReturnsNullWithoutName(): void
    {
        $this->assertNull((new DynamicVariableResolver())->resolve(['type' => 'env']));
    }
```

`ConfigurationTest` builds `new Configuration([...])` per test; `DynamicVariableResolverTest` holds `private DynamicVariableResolver $resolver` built in `setUp()`, so use `$this->resolver->resolve(...)` there instead of constructing inline.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/Config/ConfigurationTest.php tests/Unit/Variable/DynamicVariableResolverTest.php`
Expected: FAIL — `Call to undefined method Sputnik\Config\Configuration::getSecrets()` and `null` returned for the `env` type.

- [ ] **Step 3: Write the implementations**

In `src/Config/Configuration.php`, directly after `getDynamics()` (which reads
`$this->get('variables.dynamics', [])` — `get()` resolves dot notation):

```php
    /**
     * @return array<string, mixed>
     */
    public function getSecrets(): array
    {
        return $this->get('variables.secrets', []);
    }
```

In `src/Variable/DynamicVariableResolver.php`, add the arm and the method:

```php
            'env' => $this->resolveEnv($definition),
```

```php
    /**
     * @param array<string, mixed> $definition
     */
    private function resolveEnv(array $definition): ?string
    {
        $name = $definition['name'] ?? null;

        if (!\is_string($name)) {
            return null;
        }

        $value = getenv($name);

        return $value === false ? null : $value;
    }
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/Config tests/Unit/Variable`
Expected: PASS, no failures.

- [ ] **Step 5: Check style and static analysis**

Run: `vendor/bin/php-cs-fixer fix && vendor/bin/phpstan analyse --no-progress`
Expected: PHPStan `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add src/Config/Configuration.php src/Variable/DynamicVariableResolver.php tests/Unit/Config tests/Unit/Variable
git commit -F - <<'MSG'
[FEAT] read the secrets config section and resolve env values

getSecrets() exposes variables.secrets, and the dynamic resolver gains a type
env that reads an environment variable, which is the form a secret should
normally take.
MSG
```

---

### Task 5: Lazy secret resolution in VariableResolver

**Files:**

- Modify: `src/Variable/VariableResolver.php` — constructor, `initialize()`, `resolve()`, `has()`, two new private methods
- Test: `tests/Unit/Variable/VariableResolverSecretsTest.php` (new file, so the existing resolver test stays focused)

**Interfaces:**

- Consumes: `SecretRegistry::declareSecrets()`, `isSecret()`, `remember()` from Task 1; `Configuration::getSecrets()` and the `env` type from Task 4.
- Produces:
    - `VariableResolver::__construct(Configuration $config, ?string $contextName = null, ?string $workingDir = null, SecretRegistry $secrets = new SecretRegistry())`
    - `has()` returns `true` for a declared secret without resolving it
    - `resolve()` resolves a declared secret on first access and remembers the value
    - `InvalidConfigException` on a name declared in two sources and on an unsupported secret type

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Variable/VariableResolverSecretsTest.php`:

```php
<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Variable;

use PHPUnit\Framework\TestCase;
use Sputnik\Config\Configuration;
use Sputnik\Exception\InvalidConfigException;
use Sputnik\Secret\SecretRegistry;
use Sputnik\Variable\VariableResolver;

final class VariableResolverSecretsTest extends TestCase
{
    public function testSecretIsResolvedOnAccess(): void
    {
        $resolver = $this->resolver([
            'apiToken' => ['type' => 'command', 'command' => 'echo ghp_abcdefghij'],
        ]);

        $this->assertSame('ghp_abcdefghij', $resolver->resolve('apiToken'));
    }

    public function testLiteralSecretIsResolved(): void
    {
        $resolver = $this->resolver(['legacyKey' => 'literal-value']);

        $this->assertSame('literal-value', $resolver->resolve('legacyKey'));
    }

    public function testSecretIsNotResolvedUntilAccessed(): void
    {
        $marker = sys_get_temp_dir() . '/sputnik_secret_marker_' . uniqid();
        $resolver = $this->resolver([
            'apiToken' => ['type' => 'command', 'command' => 'touch ' . escapeshellarg($marker) . ' && echo value'],
        ]);

        $resolver->resolve('other', 'fallback');
        $this->assertFileDoesNotExist($marker);

        $resolver->resolve('apiToken');
        $this->assertFileExists($marker);

        unlink($marker);
    }

    public function testHasDoesNotResolve(): void
    {
        $marker = sys_get_temp_dir() . '/sputnik_secret_marker_' . uniqid();
        $resolver = $this->resolver([
            'apiToken' => ['type' => 'command', 'command' => 'touch ' . escapeshellarg($marker) . ' && echo value'],
        ]);

        $this->assertTrue($resolver->has('apiToken'));
        $this->assertFileDoesNotExist($marker);
    }

    public function testResolvedValueIsRememberedByTheRegistry(): void
    {
        $registry = new SecretRegistry();
        $resolver = $this->resolver(
            ['apiToken' => ['type' => 'command', 'command' => 'echo ghp_abcdefghij']],
            $registry,
        );

        $resolver->resolve('apiToken');

        $this->assertSame(['ghp_abcdefghij'], $registry->values());
    }

    public function testRuntimeOverrideOfASecretStaysClassifiedAndRemembered(): void
    {
        $registry = new SecretRegistry();
        $resolver = $this->resolver(
            ['apiToken' => ['type' => 'command', 'command' => 'echo from-command']],
            $registry,
        )->withOverrides(['apiToken' => 'from-override']);

        $this->assertSame('from-override', $resolver->resolve('apiToken'));
        $this->assertSame(['from-override'], $registry->values());
    }

    public function testUnresolvableSecretIsNullAndReportsDiagnostic(): void
    {
        $registry = new SecretRegistry();
        $resolver = $this->resolver(
            ['apiToken' => ['type' => 'command', 'command' => 'exit 1']],
            $registry,
        );

        $this->assertNull($resolver->resolve('apiToken'));
        $this->assertSame(["secret 'apiToken' could not be resolved"], $registry->takeDiagnostics());
    }

    public function testNameInTwoSourcesIsAConfigurationError(): void
    {
        $config = new Configuration([
            'variables' => [
                'constants' => ['apiToken' => 'plain'],
                'secrets' => ['apiToken' => 'secret'],
            ],
        ]);

        $resolver = new VariableResolver($config, null, sys_get_temp_dir(), new SecretRegistry());

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('apiToken');

        $resolver->resolve('apiToken');
    }

    public function testNameInAContextConstantIsAConfigurationError(): void
    {
        $config = new Configuration([
            'contexts' => [
                'dev' => ['variables' => ['constants' => ['apiToken' => 'plain']]],
            ],
            'variables' => [
                'secrets' => ['apiToken' => 'secret'],
            ],
        ]);

        $resolver = new VariableResolver($config, 'dev', sys_get_temp_dir(), new SecretRegistry());

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('apiToken');

        $resolver->resolve('apiToken');
    }

    public function testUnsupportedSecretTypeIsAConfigurationError(): void
    {
        $resolver = $this->resolver(['branch' => ['type' => 'git', 'property' => 'branch']]);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('git');

        $resolver->resolve('branch');
    }

    /**
     * @param array<string, mixed> $secrets
     */
    private function resolver(array $secrets, ?SecretRegistry $registry = null): VariableResolver
    {
        $config = new Configuration(['variables' => ['secrets' => $secrets]]);

        return new VariableResolver($config, null, sys_get_temp_dir(), $registry ?? new SecretRegistry());
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Variable/VariableResolverSecretsTest.php`
Expected: FAIL — the constructor takes no fourth argument, and `resolve('apiToken')` returns `null`.

- [ ] **Step 3: Write the implementation**

In `src/Variable/VariableResolver.php`:

```php
    public function __construct(
        private readonly Configuration $config,
        private ?string $contextName = null,
        ?string $workingDir = null,
        private readonly SecretRegistry $secrets = new SecretRegistry(),
    ) {
        $this->dynamicResolver = new DynamicVariableResolver($workingDir);
    }
```

At the end of `initialize()`, before `$this->initialized = true;`:

```php
        $this->declareSecrets();
```

Add the three private methods:

```php
    private function declareSecrets(): void
    {
        $definitions = $this->config->getSecrets();

        if ($definitions === []) {
            return;
        }

        $declaredElsewhere = array_merge(
            $this->config->getConstants(),
            $this->config->getDynamics(),
            $this->contextConstants(),
        );

        foreach (array_keys($definitions) as $name) {
            if (\array_key_exists($name, $declaredElsewhere)) {
                throw new InvalidConfigException(\sprintf(
                    "Variable '%s' is declared as a secret and as a constant or dynamic variable",
                    $name,
                ));
            }

            $this->assertSupportedSecretType($name, $definitions[$name]);
        }

        $this->secrets->declareSecrets(array_keys($definitions));

        // A runtime override of a secret never goes through resolveSecret(), so
        // its value has to reach the registry here or it would print unmasked.
        foreach (array_keys($definitions) as $name) {
            if (\array_key_exists($name, $this->resolved)) {
                $this->secrets->remember($name, $this->resolved[$name]);
            }
        }
    }

    /**
     * Context constants are merged into the resolved set before secrets are
     * declared, so a colliding name there would silently declassify a secret.
     *
     * @return array<string, mixed>
     */
    private function contextConstants(): array
    {
        if ($this->contextName === null) {
            return [];
        }

        $context = $this->config->getContext($this->contextName);

        return $context['variables']['constants'] ?? [];
    }

    private function assertSupportedSecretType(string $name, mixed $definition): void
    {
        if (!\is_array($definition)) {
            return;
        }

        $type = $definition['type'] ?? 'command';

        if (!\in_array($type, ['command', 'script', 'env'], true)) {
            throw new InvalidConfigException(\sprintf(
                "Secret '%s' uses unsupported type '%s'; use command, script or env",
                $name,
                \is_string($type) ? $type : get_debug_type($type),
            ));
        }
    }

    private function resolveSecret(string $name): void
    {
        $definition = $this->config->getSecrets()[$name];

        $value = \is_array($definition)
            ? $this->dynamicResolver->resolve($definition)
            : $definition;

        $this->resolved[$name] = $value;
        $this->secrets->remember($name, $value);
    }
```

Change `resolve()` and `has()`:

```php
    public function resolve(string $name, mixed $default = null): mixed
    {
        $this->initialize();

        $root = explode('.', $name)[0];
        if ($this->secrets->isSecret($root) && !\array_key_exists($root, $this->resolved)) {
            $this->resolveSecret($root);
        }

        return $this->getNestedValue($this->resolved, $name, $default);
    }

    public function has(string $name): bool
    {
        $this->initialize();

        if ($this->secrets->isSecret(explode('.', $name)[0])) {
            return true;
        }

        return $this->hasNestedValue($this->resolved, $name);
    }
```

Add `use Sputnik\Exception\InvalidConfigException;` and `use Sputnik\Secret\SecretRegistry;` to the imports.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/Variable`
Expected: PASS, including the pre-existing `VariableResolverTest`.

- [ ] **Step 5: Run the whole suite**

Run: `vendor/bin/phpunit`
Expected: no failures. `has()` now answers `true` for declared secrets, so watch for template tests that assert a missing variable — none should be affected because no fixture declares secrets.

- [ ] **Step 6: Check style and static analysis**

Run: `vendor/bin/php-cs-fixer fix && vendor/bin/phpstan analyse --no-progress && vendor/bin/rector --dry-run`
Expected: all clean.

- [ ] **Step 7: Commit**

```bash
git add src/Variable/VariableResolver.php tests/Unit/Variable/VariableResolverSecretsTest.php
git commit -F - <<'MSG'
[FEAT] resolve declared secrets lazily

A secret resolves on first access of its own name instead of together with all
dynamic variables, so a pass or op lookup does not prompt for every task that
merely reads a variable. Resolved values reach the registry, including values
that arrive through a runtime override, and a name declared twice or with an
unsupported type is a configuration error.
MSG
```

---

### Task 6: Wiring

Register the services, wrap the output, and surface the diagnostics.

**Files:**

- Modify: `src/DependencyInjection/SputnikExtension.php` — register `secretRegistry` and `secretRedactor`; pass the registry to `variableResolver` and `taskRunner`
- Modify: `src/Kernel.php` — add `getSecretRedactor(): SecretRedactor`
- Modify: `bin/sputnik` — wrap the output after the kernel is built
- Modify: `src/Task/TaskRunner.php` — constructor parameter and diagnostics logging
- Test: `tests/Integration/Secret/SecretMaskingTest.php` (new)
- Test: `tests/E2E/SputnikBinaryTest.php` (existing — add one case)

**Interfaces:**

- Consumes: everything from Tasks 1–5.
- Produces: `Kernel::getSecretRedactor(): SecretRedactor`; `TaskRunner::__construct(..., SecretRegistry $secrets = new SecretRegistry())` as the last parameter.

- [ ] **Step 1: Write the failing integration test**

Create `tests/Integration/Secret/SecretMaskingTest.php`:

```php
<?php

declare(strict_types=1);

namespace Sputnik\Tests\Integration\Secret;

use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use Sputnik\Attribute\Task;
use Sputnik\Config\Configuration;
use Sputnik\Console\SputnikOutput;
use Sputnik\Secret\RedactingOutput;
use Sputnik\Secret\SecretRedactor;
use Sputnik\Secret\SecretRegistry;
use Sputnik\Task\TaskContext;
use Sputnik\Task\TaskDiscovery;
use Sputnik\Task\TaskInterface;
use Sputnik\Task\TaskMetadata;
use Sputnik\Task\TaskResult;
use Sputnik\Task\TaskRunner;
use Sputnik\Template\TemplateEngine;
use Sputnik\Tests\Support\TestCase;
use Sputnik\Variable\VariableResolver;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class SecretMaskingTest extends TestCase
{
    private const SECRET = 'ghp_abcdefghij';

    public function testCommandEchoAndStreamedOutputAreMasked(): void
    {
        $buffer = new BufferedOutput();
        $registry = new SecretRegistry();
        $output = new RedactingOutput($buffer, new SecretRedactor($registry));

        $task = new class implements TaskInterface {
            public function __invoke(TaskContext $context): TaskResult
            {
                $context->shell('echo {{! apiToken }}');

                return TaskResult::success();
            }
        };

        $this->run($task, $registry, $output);

        $printed = $buffer->fetch();
        $this->assertStringNotContainsString(self::SECRET, $printed);
        $this->assertStringContainsString('***', $printed);
    }

    public function testFailureMessageIsMasked(): void
    {
        $buffer = new BufferedOutput();
        $registry = new SecretRegistry();
        $output = new RedactingOutput($buffer, new SecretRedactor($registry));

        $task = new class implements TaskInterface {
            public function __invoke(TaskContext $context): TaskResult
            {
                $context->shell('false {{! apiToken }}')->assertSuccess();

                return TaskResult::success();
            }
        };

        $result = $this->run($task, $registry, $output);

        $this->assertFalse($result->isSuccessful());
        $this->assertStringNotContainsString(self::SECRET, (string) $result->message);
        $this->assertStringNotContainsString(self::SECRET, $buffer->fetch());
    }

    public function testExecutionResultKeepsTheRawValue(): void
    {
        $buffer = new BufferedOutput();
        $registry = new SecretRegistry();
        $output = new RedactingOutput($buffer, new SecretRedactor($registry));
        $seen = null;

        $task = new class($seen) implements TaskInterface {
            public function __construct(private mixed &$seen)
            {
            }

            public function __invoke(TaskContext $context): TaskResult
            {
                $this->seen = trim($context->shell('echo {{! apiToken }}')->getOutput());

                return TaskResult::success();
            }
        };

        $this->run($task, $registry, $output);

        $this->assertSame("'" . self::SECRET . "'", $seen);
    }

    public function testRenderedStringKeepsTheRealValue(): void
    {
        $buffer = new BufferedOutput();
        $registry = new SecretRegistry();
        $output = new RedactingOutput($buffer, new SecretRedactor($registry));
        $rendered = null;

        $task = new class($rendered) implements TaskInterface {
            public function __construct(private mixed &$rendered)
            {
            }

            public function __invoke(TaskContext $context): TaskResult
            {
                $this->rendered = $context->render('TOKEN={{! apiToken }}');

                return TaskResult::success();
            }
        };

        $this->run($task, $registry, $output);

        $this->assertSame('TOKEN=' . self::SECRET, $rendered);
    }

    private function run(TaskInterface $task, SecretRegistry $registry, RedactingOutput $output): TaskResult
    {
        $config = new Configuration([
            'variables' => ['secrets' => ['apiToken' => self::SECRET]],
        ]);

        $metadata = new TaskMetadata($task::class, new Task(name: 'test:secret'));

        $discovery = $this->createMock(TaskDiscovery::class);
        $discovery->method('getTask')->willReturn($metadata);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturn($task);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(static fn (object $event): object => $event);

        $templateEngine = $this->createMock(TemplateEngine::class);
        $templateEngine->method('getTemplatesForContext')->willReturn([]);
        $templateEngine->method('rendererFor')->willReturnCallback(
            static fn (VariableResolver $variables): \Sputnik\Template\TemplateRenderer => new \Sputnik\Template\TemplateRenderer(
                new \Sputnik\Template\TemplateParser(),
                $variables,
            ),
        );

        $runner = new TaskRunner(
            discovery: $discovery,
            variableResolver: new VariableResolver($config, null, sys_get_temp_dir(), $registry),
            container: $container,
            logger: new NullLogger(),
            templateEngine: $templateEngine,
            eventDispatcher: $dispatcher,
            workingDir: sys_get_temp_dir(),
            contextName: 'test',
            secrets: $registry,
        );

        return $runner->run('test:secret', output: $output, sputnikOutput: new SputnikOutput($output, '0.0.0', 'test', 'test'));
    }
}
```

`tests/bootstrap.php` enables `DG\BypassFinals`, so mocking the final `TemplateEngine` works — that is how `TaskRunnerTest` already does it.

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Integration/Secret/SecretMaskingTest.php`
Expected: FAIL — `TaskRunner::__construct()` has no `secrets` parameter.

- [ ] **Step 3: Wire the services**

In `src/Task/TaskRunner.php`, add the parameter as the last one:

```php
        private readonly ?EnvironmentDetector $environmentDetector = null,
        private readonly SecretRegistry $secrets = new SecretRegistry(),
```

In `run()` there are two exit paths: `return $result->withDuration($duration);` at the end of the `try`, and `return TaskResult::failure($throwable->getMessage())->withDuration($duration);` in the `catch (\Throwable $throwable)` block. Add one private method and call it once on each path, so the diagnostics are logged exactly once per run:

```php
    private function reportSecretDiagnostics(LoggerInterface $logger): void
    {
        foreach ($this->secrets->takeDiagnostics() as $message) {
            $logger->warning($message);
        }
    }
```

Call `$this->reportSecretDiagnostics($logger);` immediately before `return $result->withDuration($duration);` and before `return TaskResult::failure(...)` in the `catch` block.

In `src/DependencyInjection/SputnikExtension.php`, before the `variableResolver` definition:

```php
        $builder->addDefinition($this->prefix('secretRegistry'))
            ->setFactory(SecretRegistry::class)
            ->setAutowired(true);

        $builder->addDefinition($this->prefix('secretRedactor'))
            ->setFactory(SecretRedactor::class, [
                'registry' => $this->prefix('@secretRegistry'),
            ])
            ->setAutowired(true);
```

Add `'secrets' => $this->prefix('@secretRegistry'),` to both the `variableResolver` and the `taskRunner` factory argument arrays, and import both classes.

In `src/Kernel.php`, next to `getTemplateEngine()`:

```php
    public function getSecretRedactor(): SecretRedactor
    {
        return $this->container->getByType(SecretRedactor::class);
    }
```

In `bin/sputnik`, inside the existing `try`, right after the kernel is created:

```php
    $kernel = new Kernel(workingDir: $workingDir, contextName: $contextOverride);
    $output = new Sputnik\Secret\RedactingConsoleOutput($output, $kernel->getSecretRedactor());
    $app = $kernel->createApplication();
```

The reassignment matters: the `catch` blocks below print through `$output`, so they redact once the kernel exists. Errors thrown by `new Kernel(...)` itself cannot be redacted — no secret is known yet.

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit tests/Integration/Secret/SecretMaskingTest.php`
Expected: PASS, 4 tests.

- [ ] **Step 5: Add the end-to-end test**

Append to `tests/E2E/SputnikBinaryTest.php`, following the file's own `scaffoldConfig()` and `writeTask()` helpers:

```php
    public function testSecretIsMaskedInOutput(): void
    {
        $this->scaffoldConfig(<<<'NEON'
            tasks:
                directories:
                    - sputnik

            variables:
                secrets:
                    apiToken: ghp_abcdefghij
            NEON);

        $this->writeTask('leaky', <<<'PHP'
            #[Task(name: 'leaky', description: 'Prints a secret')]
            final class LeakyTask implements TaskInterface
            {
                public function __invoke(TaskContext $ctx): TaskResult
                {
                    $ctx->shell('echo {{! apiToken }}');

                    return TaskResult::success();
                }
            }
            PHP);

        $result = $this->sputnik(['leaky'], $this->tempDir);

        $this->assertSame(0, $result->getExitCode());
        $this->assertStringNotContainsString('ghp_abcdefghij', $result->getOutput());
        $this->assertStringNotContainsString('ghp_abcdefghij', $result->getErrorOutput());
        $this->assertStringContainsString('***', $result->getOutput());
    }
```

`writeTask($name, $body)` writes `$body` as the whole class into `sputnik/<Ucfirst>Task.php` and prepends the `use` statements itself; the class name must be `ucfirst($name) . 'Task'`. `scaffoldConfig()` writes `.sputnik.dist.neon`.

- [ ] **Step 6: Run the whole suite and the checks**

Run: `vendor/bin/phpunit && vendor/bin/php-cs-fixer fix && vendor/bin/phpstan analyse --no-progress && vendor/bin/rector --dry-run`
Expected: all green. If the compiled container is stale, delete `.sputnik/cache` in the E2E temp project — the helper creates a fresh directory per test, so this should not occur.

- [ ] **Step 7: Commit**

```bash
git add src bin tests
git commit -F - <<'MSG'
[FEAT] mask secret values in everything Sputnik prints

The registry and redactor become container services, bin/sputnik wraps the
console output once the kernel exists, and the task runner reports the
registry's diagnostics as warnings. Command echo, streamed process output and
failure messages are redacted; ExecutionResult keeps the raw value.
MSG
```

---

### Task 7: Documentation

**Files:**

- Modify: `docs/variables.md` — a `Secrets` section after `Dynamic Variables`
- Modify: `docs/configuration.md` — the `variables.secrets` key in the reference
- Modify: `docs/tasks.md` — a note in the Shell Execution section

**Interfaces:**

- Consumes: the behaviour built in Tasks 1–6. No code.

- [ ] **Step 1: Write the variables documentation**

Insert into `docs/variables.md` after the `Dynamic Variables` section, matching the file's existing heading depth and the mkdocs admonition style already used there:

```markdown
## Secrets

Variables that must never appear in output are declared under `secrets`:

```neon
variables:
    secrets:
        apiToken:
            type: command
            command: "pass show project/api"

        dbPassword:
            type: env
            name: DB_PASSWORD
```

Declaring a variable here does two things: it defines how the value is obtained,
and it marks the value as sensitive. Everything Sputnik prints — the echoed
command, streamed command output, failure messages, log lines and
`$ctx->writeln()` — has the value replaced with `***`.

Supported types are `command`, `script` and `env`. A plain scalar is a literal
value; it works, but a secret does not belong in a committed config file. `git`
and `system` are rejected here.

Secrets resolve lazily, on first access of that name, so a `pass` or `op` lookup
does not prompt for tasks that never use the secret.

`-D apiToken=xyz` overrides the value and keeps the masking.

A name declared both here and under `constants` or `dynamics` is a configuration
error.

!!! warning "What masking does not cover"
    `ExecutionResult` keeps raw output and the raw command, so a task can still
    parse them — and can still leak them with `echo` or `file_put_contents()`,
    which bypass Sputnik's output. Rendered template files contain the real
    value by design, and `ps aux` shows the real command line: pass a secret via
    `$ctx->shell($cmd, ['env' => [...]])` if that matters.

    A secret shorter than eight characters is masked at word boundaries, so
    unrelated output may be masked too. Sputnik warns about this once per
    secret, visible with `-v`.
```

- [ ] **Step 2: Write the configuration reference entry**

In `docs/configuration.md`, the `## variables` section opens with "Two
sub-sections: `constants` and `dynamics`." Change that line to:

```markdown
Three sub-sections: `constants`, `dynamics` and `secrets`.
```

and add a `### secrets` subsection after the existing `### dynamics` one, in the
same shape as its siblings:

```markdown
### secrets

Sensitive variables. Resolved on first use and replaced with `***` in everything
Sputnik prints. Types `command`, `script` and `env`; a plain scalar is a literal.

```neon
variables:
    secrets:
        apiToken:
            type: command
            command: "pass show project/api"
```

See [Secrets](../../variables.md#secrets) for the exact guarantee and its limits.
```

The context paragraph above already states that a context can override
`variables.constants` but not `dynamics`. Extend that parenthesis to
`(not `dynamics` or `secrets`)`.

- [ ] **Step 3: Add the note in the task docs**

In `docs/tasks.md`, in the `Shell Execution` section after the existing paragraph about the template syntax, add:

```markdown
Values of variables declared under `variables.secrets` are replaced with `***`
in the echoed command and in the command's output. See
[Secrets](../../variables.md#secrets) for what that does and does not cover.
```

- [ ] **Step 4: Verify the docs build**

Run: `mkdocs build --strict --site-dir /tmp/sputnik-docs-check`
Expected: exit code 0. If mkdocs is not installed, skip — CI runs it.

- [ ] **Step 5: Commit**

```bash
git add docs
git commit -F - <<'MSG'
[DOCS] document secrets and what masking covers

Explains the variables.secrets section, lazy resolution and the exact promise,
including the cases masking cannot reach.
MSG
```

---

## Definition of done

- `vendor/bin/phpunit` green, including the new E2E case.
- `vendor/bin/phpstan analyse --no-progress` reports `[OK] No errors`, with no new baseline entry and no inline ignore.
- `vendor/bin/php-cs-fixer fix --dry-run --diff` and `vendor/bin/rector --dry-run` clean.
- `mkdocs build --strict` exits 0.
- A task that interpolates a declared secret prints `***` and never the value, on success and on failure, both through the test harness and through `bin/sputnik`.
- `ExecutionResult::getOutput()` still returns the raw value.
