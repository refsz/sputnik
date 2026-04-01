<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Attribute;

use PHPUnit\Framework\TestCase;
use Sputnik\Attribute\Option;

final class OptionAttributeTest extends TestCase
{
    public function testConstructWithMinimalArguments(): void
    {
        $option = new Option(name: 'verbose');

        $this->assertSame('verbose', $option->name);
        $this->assertSame('', $option->description);
        $this->assertNull($option->shortcut);
        $this->assertNull($option->default);
        $this->assertFalse($option->required);
        $this->assertNull($option->type);
        $this->assertSame([], $option->choices);
    }

    public function testConstructWithTypeAndChoices(): void
    {
        $option = new Option(
            name: 'env',
            description: 'Environment',
            type: 'string',
            choices: ['dev', 'staging', 'prod'],
        );

        $this->assertSame('string', $option->type);
        $this->assertSame(['dev', 'staging', 'prod'], $option->choices);
    }

    public function testTypeDefaultsToNull(): void
    {
        $option = new Option(name: 'test');
        $this->assertNull($option->type);
    }

    public function testChoicesDefaultsToEmptyArray(): void
    {
        $option = new Option(name: 'test');
        $this->assertSame([], $option->choices);
    }
}
