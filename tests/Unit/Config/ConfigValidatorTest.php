<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use Sputnik\Config\ConfigValidator;
use Sputnik\Config\Exception\ConfigValidationException;

final class ConfigValidatorTest extends TestCase
{
    private ConfigValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ConfigValidator();
    }

    public function testEmptyConfigIsValid(): void
    {
        $this->validator->validate([]);
        $this->assertTrue(true);
    }

    public function testValidMinimalConfig(): void
    {
        $config = [
            'tasks' => [
                'directories' => ['tasks'],
            ],
        ];

        $this->validator->validate($config);
        $this->assertTrue(true);
    }

    public function testValidFullConfig(): void
    {
        $config = [
            'tasks' => [
                'directories' => ['tasks', 'src/Tasks'],
            ],
            'contexts' => [
                'local' => [
                    'description' => 'Local development',
                    'variables' => [
                        'constants' => ['debug' => true],
                    ],
                ],
                'production' => [
                    'description' => 'Production',
                ],
            ],
            'variables' => [
                'constants' => ['app_name' => 'MyApp'],
                'dynamics' => [
                    'git_branch' => ['type' => 'git', 'property' => 'branch'],
                ],
            ],
            'templates' => [
                'env' => [
                    'src' => 'templates/.env.dist',
                    'dist' => '.env',
                    'overwrite' => 'ask',
                ],
            ],
            'defaults' => [
                'context' => 'local',
            ],
        ];

        $this->validator->validate($config);
        $this->assertTrue(true);
    }

    public function testTasksMustBeArray(): void
    {
        $this->expectException(ConfigValidationException::class);
        $this->expectExceptionMessage('tasks');

        $this->validator->validate(['tasks' => 'invalid']);
    }

    public function testTasksDirectoriesMustBeArray(): void
    {
        $this->expectException(ConfigValidationException::class);
        $this->expectExceptionMessage('directories');

        $this->validator->validate([
            'tasks' => ['directories' => 'tasks'],
        ]);
    }

    public function testTasksDirectoriesEntriesMustBeStrings(): void
    {
        $this->expectException(ConfigValidationException::class);
        $this->expectExceptionMessage('directories');

        $this->validator->validate([
            'tasks' => ['directories' => ['tasks', 123]],
        ]);
    }

    public function testContextsMustBeArray(): void
    {
        $this->expectException(ConfigValidationException::class);
        $this->expectExceptionMessage('contexts');

        $this->validator->validate(['contexts' => 'invalid']);
    }

    public function testContextMustBeArray(): void
    {
        $this->expectException(ConfigValidationException::class);
        $this->expectExceptionMessage('contexts');

        $this->validator->validate([
            'contexts' => ['local' => 'invalid'],
        ]);
    }

    public function testContextDescriptionMustBeString(): void
    {
        $this->expectException(ConfigValidationException::class);
        $this->expectExceptionMessage('description');

        $this->validator->validate([
            'contexts' => [
                'local' => ['description' => 123],
            ],
        ]);
    }

    public function testVariablesMustBeArray(): void
    {
        $this->expectException(ConfigValidationException::class);
        $this->expectExceptionMessage('variables');

        $this->validator->validate(['variables' => 'invalid']);
    }

    public function testVariablesConstantsMustBeArray(): void
    {
        $this->expectException(ConfigValidationException::class);
        $this->expectExceptionMessage('constants');

        $this->validator->validate([
            'variables' => ['constants' => 'invalid'],
        ]);
    }

    public function testVariablesDynamicsMustBeArray(): void
    {
        $this->expectException(ConfigValidationException::class);
        $this->expectExceptionMessage('dynamics');

        $this->validator->validate([
            'variables' => ['dynamics' => 'invalid'],
        ]);
    }

    public function testDynamicVariableTypeMustBeValid(): void
    {
        $this->expectException(ConfigValidationException::class);
        $this->expectExceptionMessage('type');

        $this->validator->validate([
            'variables' => [
                'dynamics' => [
                    'test' => ['type' => 'invalid'],
                ],
            ],
        ]);
    }

    public function testTemplatesMustBeArray(): void
    {
        $this->expectException(ConfigValidationException::class);
        $this->expectExceptionMessage('templates');

        $this->validator->validate(['templates' => 'invalid']);
    }

    public function testTemplateMustBeArray(): void
    {
        $this->expectException(ConfigValidationException::class);
        $this->expectExceptionMessage('templates');

        $this->validator->validate([
            'templates' => ['env' => 'invalid'],
        ]);
    }

    public function testTemplateRequiresSrc(): void
    {
        $this->expectException(ConfigValidationException::class);
        $this->expectExceptionMessage('src');

        $this->validator->validate([
            'templates' => [
                'env' => ['dist' => '.env'],
            ],
        ]);
    }

    public function testTemplateRequiresDist(): void
    {
        $this->expectException(ConfigValidationException::class);
        $this->expectExceptionMessage('dist');

        $this->validator->validate([
            'templates' => [
                'env' => ['src' => 'templates/.env.dist'],
            ],
        ]);
    }

    public function testTemplateOverwriteMustBeValid(): void
    {
        $this->expectException(ConfigValidationException::class);
        $this->expectExceptionMessage('overwrite');

        $this->validator->validate([
            'templates' => [
                'env' => [
                    'src' => 'templates/.env.dist',
                    'dist' => '.env',
                    'overwrite' => 'invalid',
                ],
            ],
        ]);
    }

    public function testDefaultsMustBeArray(): void
    {
        $this->expectException(ConfigValidationException::class);
        $this->expectExceptionMessage('defaults');

        $this->validator->validate(['defaults' => 'invalid']);
    }

    public function testDefaultsContextMustBeString(): void
    {
        $this->expectException(ConfigValidationException::class);
        $this->expectExceptionMessage('context');

        $this->validator->validate([
            'defaults' => ['context' => 123],
        ]);
    }

    public function testDefaultsContextMustExist(): void
    {
        $this->expectException(ConfigValidationException::class);
        $this->expectExceptionMessage("references undefined context 'production'");

        $this->validator->validate([
            'contexts' => [
                'local' => ['description' => 'Local'],
            ],
            'defaults' => [
                'context' => 'production',
            ],
        ]);
    }

    public function testValidTemplateConfigPasses(): void
    {
        $this->validator->validate([
            'templates' => [
                'env' => [
                    'src' => 'templates/.env.dist',
                    'dist' => '.env',
                    'overwrite' => 'always',
                    'contexts' => ['local', 'ci'],
                ],
            ],
        ]);
        $this->assertTrue(true);
    }

    public function testTemplateMissingSrcAndDistThrows(): void
    {
        $this->expectException(ConfigValidationException::class);

        $this->validator->validate([
            'templates' => ['env' => []],
        ]);
    }

    public function testValidEnvironmentConfigPasses(): void
    {
        $this->validator->validate([
            'environment' => [
                'detection' => 'auto',
                'executor' => 'docker exec app {command}',
            ],
        ]);
        $this->assertTrue(true);
    }

    public function testInvalidEnvironmentDetectionTypeThrows(): void
    {
        $this->expectException(ConfigValidationException::class);
        $this->expectExceptionMessage('detection');

        $this->validator->validate([
            'environment' => ['detection' => 123],
        ]);
    }

    public function testValidDefaultsPass(): void
    {
        $this->validator->validate([
            'contexts' => [
                'local' => ['description' => 'Local'],
            ],
            'defaults' => [
                'context' => 'local',
            ],
        ]);
        $this->assertTrue(true);
    }

    public function testUnknownDefaultContextThrows(): void
    {
        $this->expectException(ConfigValidationException::class);
        $this->expectExceptionMessage("references undefined context 'staging'");

        $this->validator->validate([
            'contexts' => [
                'local' => ['description' => 'Local'],
            ],
            'defaults' => [
                'context' => 'staging',
            ],
        ]);
    }

    public function testContextVariablesMustBeArray(): void
    {
        $this->expectException(ConfigValidationException::class);
        $this->expectExceptionMessage('variables');

        $this->validator->validate([
            'contexts' => [
                'local' => ['variables' => 'invalid'],
            ],
        ]);
    }

    public function testContextNameMustBeString(): void
    {
        $this->expectException(ConfigValidationException::class);

        $this->validator->validate([
            'contexts' => [
                ['description' => 'unnamed context'],
            ],
        ]);
    }

    public function testDynamicVariableDefinitionMustBeArray(): void
    {
        $this->expectException(ConfigValidationException::class);
        $this->expectExceptionMessage('dynamics');

        $this->validator->validate([
            'variables' => [
                'dynamics' => ['test' => 'not-an-array'],
            ],
        ]);
    }

    public function testTemplateSrcMustBeString(): void
    {
        $this->expectException(ConfigValidationException::class);
        $this->expectExceptionMessage('src');

        $this->validator->validate([
            'templates' => [
                'env' => ['src' => 123, 'dist' => '.env'],
            ],
        ]);
    }

    public function testTemplateDistMustBeString(): void
    {
        $this->expectException(ConfigValidationException::class);
        $this->expectExceptionMessage('dist');

        $this->validator->validate([
            'templates' => [
                'env' => ['src' => 'src/.env.dist', 'dist' => 456],
            ],
        ]);
    }

    public function testTemplateContextsMustBeArray(): void
    {
        $this->expectException(ConfigValidationException::class);
        $this->expectExceptionMessage('contexts');

        $this->validator->validate([
            'templates' => [
                'env' => [
                    'src' => 'src/.env.dist',
                    'dist' => '.env',
                    'contexts' => 'local',
                ],
            ],
        ]);
    }

    public function testTemplateNameMustBeString(): void
    {
        $this->expectException(ConfigValidationException::class);

        $this->validator->validate([
            'templates' => [
                ['src' => 'src/test.dist', 'dist' => 'test'],
            ],
        ]);
    }

    public function testEnvironmentMustBeArray(): void
    {
        $this->expectException(ConfigValidationException::class);
        $this->expectExceptionMessage('environment');

        $this->validator->validate(['environment' => 'invalid']);
    }

    public function testEnvironmentExecutorMustBeString(): void
    {
        $this->expectException(ConfigValidationException::class);
        $this->expectExceptionMessage('executor');

        $this->validator->validate([
            'environment' => ['executor' => 123],
        ]);
    }

    public function testEnvironmentExecutorMustContainCommandPlaceholder(): void
    {
        $this->expectException(ConfigValidationException::class);
        $this->expectExceptionMessage('{command}');

        $this->validator->validate([
            'environment' => ['executor' => 'docker exec app'],
        ]);
    }

    public function testMultipleErrorsAreCollected(): void
    {
        $config = [
            'tasks' => 'invalid',
            'contexts' => 'invalid',
            'variables' => 'invalid',
        ];

        try {
            $this->validator->validate($config);
            $this->fail('Expected ConfigValidationException');
        } catch (ConfigValidationException $e) {
            $errors = $e->getErrors();
            $this->assertGreaterThanOrEqual(3, \count($errors));
        }
    }
}
