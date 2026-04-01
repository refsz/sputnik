<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Sputnik\Support\PhpFileParser;

final class PhpFileParserTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/PhpFileParserTest_' . uniqid('', true);
        mkdir($this->tempDir, 0o700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tempDir . '/*.php') as $file) {
            unlink($file);
        }
        rmdir($this->tempDir);
    }

    private function writeFile(string $name, string $content): string
    {
        $path = $this->tempDir . '/' . $name;
        file_put_contents($path, $content);

        return $path;
    }

    public function testExtractsSimpleClassName(): void
    {
        $path = $this->writeFile('Simple.php', '<?php class MyClass {}');

        $this->assertSame('MyClass', PhpFileParser::extractClassName($path));
    }

    public function testExtractsFullyQualifiedClassNameWithNamespace(): void
    {
        $path = $this->writeFile('Namespaced.php', <<<'PHP'
            <?php
            namespace App\Services;
            class UserService {}
            PHP);

        $this->assertSame('App\Services\UserService', PhpFileParser::extractClassName($path));
    }

    public function testReturnsNullWhenNoClassDeclared(): void
    {
        $path = $this->writeFile('NoClass.php', '<?php function helper() {}');

        $this->assertNull(PhpFileParser::extractClassName($path));
    }

    public function testHandlesEnumDeclaration(): void
    {
        $path = $this->writeFile('Status.php', <<<'PHP'
            <?php
            namespace App\Enums;
            enum Status { case Active; case Inactive; }
            PHP);

        $this->assertSame('App\Enums\Status', PhpFileParser::extractClassName($path));
    }

    public function testIgnoresDoubleColonClassConstant(): void
    {
        $path = $this->writeFile('WithClassConstant.php', <<<'PHP'
            <?php
            namespace App;
            class Foo
            {
                public function bar(): string
                {
                    return self::class;
                }
            }
            PHP);

        $this->assertSame('App\Foo', PhpFileParser::extractClassName($path));
    }

    public function testIgnoresDoubleColonClassConstantWithoutRealClass(): void
    {
        $path = $this->writeFile('OnlyClassConstant.php', <<<'PHP'
            <?php
            $x = SomeClass::class;
            PHP);

        $this->assertNull(PhpFileParser::extractClassName($path));
    }

    public function testReturnsNullForNonExistentFile(): void
    {
        // Suppress the PHP warning from file_get_contents() on a missing path;
        // the method must return null rather than propagate it.
        $result = @PhpFileParser::extractClassName('/no/such/file.php');

        $this->assertNull($result);
    }

    public function testReturnsNullForEmptyFile(): void
    {
        $path = $this->writeFile('Empty.php', '');

        $this->assertNull(PhpFileParser::extractClassName($path));
    }

    public function testClassWithoutNamespaceReturnsUnqualifiedName(): void
    {
        $path = $this->writeFile('GlobalClass.php', '<?php class GlobalHelper {}');

        $this->assertSame('GlobalHelper', PhpFileParser::extractClassName($path));
    }
}
