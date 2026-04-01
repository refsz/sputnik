<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Console;

use PHPUnit\Framework\TestCase;
use Sputnik\Console\RuntimeVariableParser;

final class RuntimeVariableParserTest extends TestCase
{
    public function testEmptyArrayReturnsEmpty(): void
    {
        $result = RuntimeVariableParser::parse([]);

        $this->assertSame([], $result);
    }

    public function testStringValue(): void
    {
        $result = RuntimeVariableParser::parse(['ENV=production']);

        $this->assertSame(['ENV' => 'production'], $result);
    }

    public function testIntegerCoercion(): void
    {
        $result = RuntimeVariableParser::parse(['WORKERS=4']);

        $this->assertSame(['WORKERS' => 4], $result);
        $this->assertIsInt($result['WORKERS']);
    }

    public function testFloatCoercion(): void
    {
        $result = RuntimeVariableParser::parse(['RATIO=1.5']);

        $this->assertSame(['RATIO' => 1.5], $result);
        $this->assertIsFloat($result['RATIO']);
    }

    public function testBooleanTrueCoercion(): void
    {
        $result = RuntimeVariableParser::parse(['DEBUG=true']);

        $this->assertTrue($result['DEBUG']);
        $this->assertIsBool($result['DEBUG']);
    }

    public function testBooleanFalseCoercion(): void
    {
        $result = RuntimeVariableParser::parse(['DEBUG=false']);

        $this->assertFalse($result['DEBUG']);
        $this->assertIsBool($result['DEBUG']);
    }

    public function testBooleanCaseInsensitive(): void
    {
        $result = RuntimeVariableParser::parse(['A=True', 'B=FALSE']);

        $this->assertTrue($result['A']);
        $this->assertFalse($result['B']);
    }

    public function testNullCoercion(): void
    {
        $result = RuntimeVariableParser::parse(['VALUE=null']);

        $this->assertNull($result['VALUE']);
        $this->assertArrayHasKey('VALUE', $result);
    }

    public function testArrayCoercionFromJsonArray(): void
    {
        $result = RuntimeVariableParser::parse(['TAGS=["a","b","c"]']);

        $this->assertSame(['TAGS' => ['a', 'b', 'c']], $result);
        $this->assertIsArray($result['TAGS']);
    }

    public function testObjectCoercionFromJsonObject(): void
    {
        $result = RuntimeVariableParser::parse(['CONFIG={"key":"val"}']);

        $this->assertSame(['CONFIG' => ['key' => 'val']], $result);
        $this->assertIsArray($result['CONFIG']);
    }

    public function testInvalidJsonFallsBackToString(): void
    {
        $result = RuntimeVariableParser::parse(['DATA=[not json]']);

        $this->assertSame(['DATA' => '[not json]'], $result);
        $this->assertIsString($result['DATA']);
    }

    public function testMissingEqualsSignTreatedAsBooleanTrue(): void
    {
        $result = RuntimeVariableParser::parse(['VERBOSE']);

        $this->assertTrue($result['VERBOSE']);
        $this->assertIsBool($result['VERBOSE']);
    }

    public function testEmptyValue(): void
    {
        $result = RuntimeVariableParser::parse(['KEY=']);

        $this->assertSame(['KEY' => ''], $result);
    }

    public function testNameIsTrimmed(): void
    {
        $result = RuntimeVariableParser::parse([' KEY =value']);

        $this->assertArrayHasKey('KEY', $result);
        $this->assertSame('value', $result['KEY']);
    }

    public function testMultipleDefinesAreAllParsed(): void
    {
        $result = RuntimeVariableParser::parse([
            'HOST=localhost',
            'PORT=3306',
            'VERBOSE',
        ]);

        $this->assertSame('localhost', $result['HOST']);
        $this->assertSame(3306, $result['PORT']);
        $this->assertTrue($result['VERBOSE']);
    }
}
