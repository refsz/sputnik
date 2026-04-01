<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Console;

use PHPUnit\Framework\TestCase;
use Sputnik\Console\ShellCallCounter;

final class ShellCallCounterTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    public function testCountsShellCalls(): void
    {
        $file = $this->createTempFile(<<<'PHP'
            <?php
            class MyTask {
                public function __invoke($ctx) {
                    $ctx->shell('echo hello');
                    $ctx->shellRaw('docker exec bash');
                    return TaskResult::success();
                }
            }
            PHP);

        $this->assertSame(2, ShellCallCounter::count($file));
    }

    public function testCountsSingleShellCall(): void
    {
        $file = $this->createTempFile(<<<'PHP'
            <?php
            class MyTask {
                public function __invoke($ctx) {
                    $ctx->shell('echo hello');
                    return TaskResult::success();
                }
            }
            PHP);

        $this->assertSame(1, ShellCallCounter::count($file));
    }

    public function testReturnsZeroForNoShellCalls(): void
    {
        $file = $this->createTempFile(<<<'PHP'
            <?php
            class MyTask {
                public function __invoke($ctx) {
                    return TaskResult::success();
                }
            }
            PHP);

        $this->assertSame(0, ShellCallCounter::count($file));
    }

    public function testCountsRunTaskCalls(): void
    {
        $file = $this->createTempFile(<<<'PHP'
            <?php
            class MyTask {
                public function __invoke($ctx) {
                    $ctx->shell('echo 1');
                    $ctx->runTask('other:task');
                    return TaskResult::success();
                }
            }
            PHP);

        // runTask is not a shell call — should only count shell/shellRaw
        $this->assertSame(1, ShellCallCounter::count($file));
    }

    public function testReturnsZeroForNonExistentFile(): void
    {
        $this->assertSame(0, ShellCallCounter::count('/nonexistent/file.php'));
    }

    public function testCountsMultipleShellCallsInSameMethod(): void
    {
        $file = $this->createTempFile(<<<'PHP'
            <?php
            class MyTask {
                public function __invoke($ctx) {
                    $ctx->shell('echo 1');
                    $ctx->shell('echo 2');
                    $ctx->shell('echo 3');
                    $ctx->shellRaw('raw cmd');
                    return TaskResult::success();
                }
            }
            PHP);

        $this->assertSame(4, ShellCallCounter::count($file));
    }

    public function testCountsNullsafeOperatorShellCalls(): void
    {
        $file = $this->createTempFile(<<<'PHP'
            <?php
            class MyTask {
                public function __invoke($ctx) {
                    $ctx?->shell('echo hello');
                    $ctx?->shellRaw('ls');
                    return TaskResult::success();
                }
            }
            PHP);

        $this->assertSame(2, ShellCallCounter::count($file));
    }

    public function testDoesNotCountShellMethodOnUnrelatedObject(): void
    {
        $file = $this->createTempFile(<<<'PHP'
            <?php
            class MyTask {
                public function __invoke($ctx) {
                    // shell() called as standalone function, not as method
                    $result = shell_exec('ls');
                    return TaskResult::success();
                }
            }
            PHP);

        // shell_exec is not ->shell( so should not be counted
        $this->assertSame(0, ShellCallCounter::count($file));
    }

    public function testDoesNotCountMethodCallsWithNonParenToken(): void
    {
        // ->shell followed by something that is not '(' — e.g. ->shell['key'] or property access
        $file = $this->createTempFile(<<<'PHP'
            <?php
            class MyTask {
                public function __invoke($ctx) {
                    // ->shell not followed by ( — edge case
                    $x = $ctx->shellProp;
                    return TaskResult::success();
                }
            }
            PHP);

        $this->assertSame(0, ShellCallCounter::count($file));
    }

    public function testCountsShellCallWithWhitespaceBetweenArrowAndMethod(): void
    {
        // PHP tokenizer includes whitespace tokens between -> and method name
        $file = $this->createTempFile('<?php $ctx->shell(\'cmd\');');

        $this->assertSame(1, ShellCallCounter::count($file));
    }

    public function testHandlesFileWithOnlyWhitespaceAfterOperator(): void
    {
        // Edge case: object operator at end of tokens (skipWhitespace returns null)
        $file = $this->createTempFile('<?php $x->');

        // Should not throw, should return 0
        $this->assertSame(0, ShellCallCounter::count($file));
    }

    public function testCountsShellCallNotFollowedByParenIsNotCounted(): void
    {
        // ->shell followed by a string token (weird but syntactically invalid — test robustness)
        $file = $this->createTempFile(<<<'PHP'
            <?php
            class MyTask {
                public $shell = 'not a call';
                public function __invoke($ctx) {
                    return TaskResult::success();
                }
            }
            PHP);

        $this->assertSame(0, ShellCallCounter::count($file));
    }

    private function createTempFile(string $content): string
    {
        $file = tempnam(sys_get_temp_dir(), 'sputnik_test_');
        file_put_contents($file, $content);
        $this->tempFiles[] = $file;

        return $file;
    }
}
