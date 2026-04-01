<?php

declare(strict_types=1);

namespace Sputnik\Template;

use Sputnik\Exception\InvalidConfigException;

final class TemplateConfig
{
    /**
     * @param string             $name      Template identifier
     * @param string             $src       Source template file path
     * @param string             $dist      Destination file path
     * @param string             $overwrite Overwrite behavior: 'always', 'never', 'ask'
     * @param array<string>|null $contexts  Limit to specific contexts (null = all)
     */
    public function __construct(
        public readonly string $name,
        public readonly string $src,
        public readonly string $dist,
        public readonly string $overwrite = 'always',
        public readonly ?array $contexts = null,
    ) {
    }

    /**
     * Create from NEON configuration array.
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(string $name, array $config): self
    {
        return new self(
            name: $name,
            src: $config['src'] ?? throw new InvalidConfigException(\sprintf("Template '%s' missing 'src'", $name)),
            dist: $config['dist'] ?? throw new InvalidConfigException(\sprintf("Template '%s' missing 'dist'", $name)),
            overwrite: $config['overwrite'] ?? 'always',
            contexts: $config['contexts'] ?? null,
        );
    }

    /**
     * Check if this template should be rendered for the given context.
     */
    public function isForContext(string $contextName): bool
    {
        if ($this->contexts === null) {
            return true;
        }

        return \in_array($contextName, $this->contexts, true);
    }
}
