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

    /**
     * A variable must resolve unless the template says what to use instead. A
     * silently empty substitution is how `rm -rf {{ deployPath }}/` became
     * `rm -rf /`: the typo produced an empty argument and the command reported
     * success. Ask for emptiness explicitly with `{{ name | "" }}`.
     *
     * The `{{! name }}` marker stays accepted, and is now redundant.
     */
    public function isRequired(): bool
    {
        return $this->isVariable() && !$this->hasDefault();
    }

    public function hasDefault(): bool
    {
        return $this->default !== null;
    }
}
