<?php

declare(strict_types=1);

namespace Sputnik\Template;

enum TokenType: string
{
    case Text = 'text';
    case Variable = 'variable';
    case RequiredVariable = 'required_variable';
}

final class Token
{
    public function __construct(
        public readonly TokenType $type,
        public readonly string $value,
        public readonly ?string $default = null,
        public readonly int $line = 1,
        public readonly int $column = 1,
    ) {
    }

    public function isVariable(): bool
    {
        return $this->type === TokenType::Variable || $this->type === TokenType::RequiredVariable;
    }

    public function isRequired(): bool
    {
        return $this->type === TokenType::RequiredVariable;
    }

    public function hasDefault(): bool
    {
        return $this->default !== null;
    }
}
