<?php

declare(strict_types=1);

namespace Sputnik\Config;

use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Nette\Schema\Schema;
use Nette\Schema\ValidationException;
use Sputnik\Config\Exception\ConfigValidationException;

final class ConfigValidator
{
    /**
     * Validate the configuration array.
     *
     * @param array<string, mixed> $config
     *
     * @throws ConfigValidationException
     */
    public function validate(array $config): void
    {
        $processor = new Processor();

        try {
            $processor->process($this->getSchema(), $config);
        } catch (ValidationException $e) {
            throw ConfigValidationException::withErrors(array_values($e->getMessages()));
        }

        $this->validateCrossReferences($config);
    }

    private function getSchema(): Schema
    {
        return Expect::structure([
            'tasks' => Expect::structure([
                'directories' => Expect::listOf('string'),
                'classes' => Expect::listOf(
                    Expect::string()->assert('class_exists', 'class must exist'),
                ),
            ])->castTo('array'),
            'contexts' => Expect::arrayOf(
                Expect::structure([
                    'description' => Expect::string()->nullable(),
                    'variables' => Expect::anyOf(Expect::array(), Expect::null()),
                ])->otherItems()->castTo('array'),
                'string',
            ),
            'variables' => Expect::structure([
                'constants' => Expect::array(),
                'dynamics' => Expect::arrayOf(
                    Expect::structure([
                        'type' => Expect::anyOf('command', 'git', 'script', 'system', 'composite')->nullable(),
                    ])->otherItems()->castTo('array'),
                ),
            ])->otherItems()->castTo('array'),
            'templates' => Expect::arrayOf(
                Expect::structure([
                    'src' => Expect::string()->required(),
                    'dist' => Expect::string()->required(),
                    'overwrite' => Expect::anyOf('always', 'never', 'ask')->nullable(),
                    'contexts' => Expect::listOf('string')->nullable(),
                ])->castTo('array'),
                'string',
            ),
            'environment' => Expect::structure([
                'detection' => Expect::string()->nullable(),
                'executor' => Expect::string()->nullable()->assert(
                    static fn(?string $v): bool => $v === null || str_contains($v, '{command}'),
                    "'environment.executor' must contain the {command} placeholder",
                ),
            ])->castTo('array'),
            'defaults' => Expect::structure([
                'context' => Expect::string()->nullable(),
            ])->castTo('array'),
        ])->otherItems()->castTo('array');
    }

    /**
     * @param array<string, mixed> $config
     *
     * @throws ConfigValidationException
     */
    private function validateCrossReferences(array $config): void
    {
        if (
            isset($config['defaults']['context'])
            && \is_string($config['defaults']['context'])
            && isset($config['contexts'])
            && \is_array($config['contexts'])
            && !isset($config['contexts'][$config['defaults']['context']])
        ) {
            throw ConfigValidationException::withErrors([
                \sprintf("'defaults.context' references undefined context '%s'", $config['defaults']['context']),
            ]);
        }
    }
}
