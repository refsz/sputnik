<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Task;

use PHPUnit\Framework\TestCase;
use Sputnik\Attribute\Argument;
use Sputnik\Attribute\Option;
use Sputnik\Attribute\Task;
use Sputnik\Task\InvalidOptionException;
use Sputnik\Task\OptionCoercer;
use Sputnik\Task\TaskMetadata;

final class OptionCoercerTest extends TestCase
{
    private OptionCoercer $coercer;

    protected function setUp(): void
    {
        $this->coercer = new OptionCoercer();
    }

    public function testResolveOptionsWithDefaults(): void
    {
        $metadata = $this->metadataWithOptions(
            new Option(name: 'verbose', default: false),
            new Option(name: 'level', default: 1),
        );

        $result = $this->coercer->resolveOptions($metadata, []);

        $this->assertSame(['verbose' => false, 'level' => 1], $result);
    }

    public function testResolveOptionsProvidedOverridesDefault(): void
    {
        $metadata = $this->metadataWithOptions(
            new Option(name: 'level', default: 1),
        );

        $result = $this->coercer->resolveOptions($metadata, ['level' => 5]);

        $this->assertSame(['level' => 5], $result);
    }

    public function testCoerceInt(): void
    {
        $metadata = $this->metadataWithOptions(
            new Option(name: 'count', type: 'int'),
        );

        $result = $this->coercer->resolveOptions($metadata, ['count' => '42']);

        $this->assertSame(42, $result['count']);
    }

    public function testCoerceInvalidIntThrows(): void
    {
        $metadata = $this->metadataWithOptions(
            new Option(name: 'count', type: 'int'),
        );

        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessageMatches('/count/');

        $this->coercer->resolveOptions($metadata, ['count' => 'abc']);
    }

    public function testCoerceBoolFromString(): void
    {
        $metadata = $this->metadataWithOptions(
            new Option(name: 'debug', type: 'bool'),
        );

        $result = $this->coercer->resolveOptions($metadata, ['debug' => 'true']);

        $this->assertTrue($result['debug']);
    }

    public function testCoerceBoolPassthrough(): void
    {
        $metadata = $this->metadataWithOptions(
            new Option(name: 'debug', type: 'bool'),
        );

        $result = $this->coercer->resolveOptions($metadata, ['debug' => true]);

        $this->assertTrue($result['debug']);
    }

    public function testChoicesValidationPasses(): void
    {
        $metadata = $this->metadataWithOptions(
            new Option(name: 'env', choices: ['dev', 'staging', 'prod']),
        );

        $result = $this->coercer->resolveOptions($metadata, ['env' => 'staging']);

        $this->assertSame('staging', $result['env']);
    }

    public function testChoicesValidationFails(): void
    {
        $metadata = $this->metadataWithOptions(
            new Option(name: 'env', choices: ['dev', 'staging', 'prod']),
        );

        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessageMatches('/must be one of/');

        $this->coercer->resolveOptions($metadata, ['env' => 'invalid']);
    }

    public function testNullValueSkipsCoercionAndValidation(): void
    {
        $metadata = $this->metadataWithOptions(
            new Option(name: 'env', type: 'string', choices: ['dev', 'prod']),
        );

        $result = $this->coercer->resolveOptions($metadata, ['env' => null]);

        $this->assertNull($result['env']);
    }

    public function testCoercionBeforeChoiceValidation(): void
    {
        $metadata = $this->metadataWithOptions(
            new Option(name: 'level', type: 'int', choices: [1, 2, 3]),
        );

        // '2' coerced to int 2, which is in choices [1, 2, 3]
        $result = $this->coercer->resolveOptions($metadata, ['level' => '2']);

        $this->assertSame(2, $result['level']);
    }

    public function testNullTypeMeansNoCoercion(): void
    {
        $metadata = $this->metadataWithOptions(
            new Option(name: 'raw'),
        );

        $result = $this->coercer->resolveOptions($metadata, ['raw' => '42']);

        $this->assertSame('42', $result['raw']);
    }

    public function testExtraOptionsPassedThrough(): void
    {
        $metadata = $this->metadataWithOptions(
            new Option(name: 'known', default: 'x'),
        );

        $result = $this->coercer->resolveOptions($metadata, ['known' => 'y', 'extra' => 'z']);

        $this->assertSame('y', $result['known']);
        $this->assertSame('z', $result['extra']);
    }

    public function testResolveArguments(): void
    {
        $metadata = $this->metadataWithArguments(
            new Argument(name: 'path', default: '/tmp'),
            new Argument(name: 'name'),
        );

        $result = $this->coercer->resolveArguments($metadata, []);

        $this->assertSame(['path' => '/tmp', 'name' => null], $result);
    }

    public function testResolveArgumentsProvidedOverridesDefault(): void
    {
        $metadata = $this->metadataWithArguments(
            new Argument(name: 'path', default: '/tmp'),
        );

        $result = $this->coercer->resolveArguments($metadata, ['path' => '/var/log']);

        $this->assertSame('/var/log', $result['path']);
    }

    public function testCoerceFloatFromString(): void
    {
        $metadata = $this->metadataWithOptions(
            new Option(name: 'ratio', type: 'float'),
        );

        $result = $this->coercer->resolveOptions($metadata, ['ratio' => '3.14']);

        $this->assertSame(3.14, $result['ratio']);
    }

    public function testCoerceFloatFromInt(): void
    {
        $metadata = $this->metadataWithOptions(
            new Option(name: 'ratio', type: 'float'),
        );

        $result = $this->coercer->resolveOptions($metadata, ['ratio' => 2]);

        $this->assertSame(2.0, $result['ratio']);
    }

    public function testCoerceInvalidFloatThrows(): void
    {
        $metadata = $this->metadataWithOptions(
            new Option(name: 'ratio', type: 'float'),
        );

        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessageMatches('/ratio/');

        $this->coercer->resolveOptions($metadata, ['ratio' => 'notanumber']);
    }

    public function testCoerceBoolFromStringZero(): void
    {
        $metadata = $this->metadataWithOptions(
            new Option(name: 'flag', type: 'bool'),
        );

        $result = $this->coercer->resolveOptions($metadata, ['flag' => '0']);

        $this->assertFalse($result['flag']);
    }

    public function testCoerceBoolFromStringOne(): void
    {
        $metadata = $this->metadataWithOptions(
            new Option(name: 'flag', type: 'bool'),
        );

        $result = $this->coercer->resolveOptions($metadata, ['flag' => '1']);

        $this->assertTrue($result['flag']);
    }

    public function testCoerceBoolFromNonStringNonBool(): void
    {
        $metadata = $this->metadataWithOptions(
            new Option(name: 'flag', type: 'bool'),
        );

        // int 0 → (bool) 0 → false
        $result = $this->coercer->resolveOptions($metadata, ['flag' => 0]);

        $this->assertFalse($result['flag']);
    }

    public function testCoerceArrayFromJsonString(): void
    {
        $metadata = $this->metadataWithOptions(
            new Option(name: 'tags', type: 'array'),
        );

        $result = $this->coercer->resolveOptions($metadata, ['tags' => '["a","b","c"]']);

        $this->assertSame(['a', 'b', 'c'], $result['tags']);
    }

    public function testCoerceArrayPassthroughWhenAlreadyArray(): void
    {
        $metadata = $this->metadataWithOptions(
            new Option(name: 'tags', type: 'array'),
        );

        $result = $this->coercer->resolveOptions($metadata, ['tags' => ['x', 'y']]);

        $this->assertSame(['x', 'y'], $result['tags']);
    }

    public function testCoerceArrayInvalidJsonThrows(): void
    {
        $metadata = $this->metadataWithOptions(
            new Option(name: 'tags', type: 'array'),
        );

        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessageMatches('/tags/');

        $this->coercer->resolveOptions($metadata, ['tags' => 'not-json']);
    }

    public function testCoerceStringFromNonString(): void
    {
        $metadata = $this->metadataWithOptions(
            new Option(name: 'label', type: 'string'),
        );

        $result = $this->coercer->resolveOptions($metadata, ['label' => 42]);

        $this->assertSame('42', $result['label']);
    }

    public function testCoerceUnknownTypePassesValueThrough(): void
    {
        $metadata = $this->metadataWithOptions(
            new Option(name: 'data', type: 'custom'),
        );

        $result = $this->coercer->resolveOptions($metadata, ['data' => 'unchanged']);

        $this->assertSame('unchanged', $result['data']);
    }

    private function metadataWithOptions(Option ...$options): TaskMetadata
    {
        return new TaskMetadata('FakeTask', new Task(name: 'test:task'), $options);
    }

    private function metadataWithArguments(Argument ...$arguments): TaskMetadata
    {
        return new TaskMetadata('FakeTask', new Task(name: 'test:task'), [], $arguments);
    }
}
