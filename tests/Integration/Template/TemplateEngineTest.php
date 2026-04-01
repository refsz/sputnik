<?php

declare(strict_types=1);

namespace Sputnik\Tests\Integration\Template;

use Sputnik\Config\Configuration;
use Sputnik\Exception\InvalidConfigException;
use Sputnik\Template\TemplateEngine;
use Sputnik\Tests\Support\Doubles\InMemoryVariableResolver;
use Sputnik\Tests\Support\TestCase;

final class TemplateEngineTest extends TestCase
{
    private string $tempDir;
    private InMemoryVariableResolver $variables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = $this->createTempDir();
        $this->variables = new InMemoryVariableResolver();
    }

    protected function tearDown(): void
    {
        $this->removeTempDir($this->tempDir);
        parent::tearDown();
    }

    public function testRenderAllWritesFiles(): void
    {
        mkdir($this->tempDir . '/src', 0755, true);
        file_put_contents($this->tempDir . '/src/test.template', 'Hello {{ name }}');
        $this->variables->set('name', 'World');

        $engine = $this->createEngine([
            'test' => ['src' => 'src/test.template', 'dist' => 'dist/test.txt', 'overwrite' => 'always'],
        ]);
        $results = $engine->renderAll(force: true);

        $this->assertTrue($results['test']['written']);
        $this->assertFileExists($this->tempDir . '/dist/test.txt');
        $this->assertSame('Hello World', file_get_contents($this->tempDir . '/dist/test.txt'));
    }

    public function testContextFilteringSkipsWrongContext(): void
    {
        mkdir($this->tempDir . '/src', 0755, true);
        file_put_contents($this->tempDir . '/src/prod.template', 'prod only');

        $engine = $this->createEngine([
            'prod' => ['src' => 'src/prod.template', 'dist' => 'dist/prod.txt', 'contexts' => ['prod']],
        ], context: 'local');
        $results = $engine->renderAll();

        $this->assertFalse($results['prod']['written']);
    }

    public function testOverwriteNeverSkipsExistingFile(): void
    {
        mkdir($this->tempDir . '/src', 0755, true);
        mkdir($this->tempDir . '/dist', 0755, true);
        file_put_contents($this->tempDir . '/src/test.template', 'new');
        file_put_contents($this->tempDir . '/dist/test.txt', 'old');

        $engine = $this->createEngine([
            'test' => ['src' => 'src/test.template', 'dist' => 'dist/test.txt', 'overwrite' => 'never'],
        ]);
        $results = $engine->renderAll();

        $this->assertFalse($results['test']['written']);
        $this->assertSame('old', file_get_contents($this->tempDir . '/dist/test.txt'));
    }

    public function testMissingSrcFileThrows(): void
    {
        $engine = $this->createEngine([
            'missing' => ['src' => 'nonexistent.template', 'dist' => 'out.txt'],
        ]);

        $this->expectException(\RuntimeException::class);
        $engine->renderTemplate('missing');
    }

    public function testGetMissingVariablesReturnsList(): void
    {
        mkdir($this->tempDir . '/src', 0755, true);
        file_put_contents($this->tempDir . '/src/test.template', '{{! required_var }}');

        $engine = $this->createEngine([
            'test' => ['src' => 'src/test.template', 'dist' => 'dist/test.txt'],
        ]);

        $this->assertContains('required_var', $engine->getMissingVariables('test'));
    }

    public function testCanRenderTemplateReturnsFalseWhenMissing(): void
    {
        mkdir($this->tempDir . '/src', 0755, true);
        file_put_contents($this->tempDir . '/src/test.template', '{{! missing }}');

        $engine = $this->createEngine([
            'test' => ['src' => 'src/test.template', 'dist' => 'dist/test.txt'],
        ]);

        $this->assertFalse($engine->canRenderTemplate('test'));
    }

    public function testRenderTemplateUnknownNameThrows(): void
    {
        $engine = $this->createEngine([]);

        $this->expectException(InvalidConfigException::class);
        $engine->renderTemplate('nonexistent');
    }

    public function testCanRenderTemplateReturnsTrueWhenAllVariablesPresent(): void
    {
        mkdir($this->tempDir . '/src', 0755, true);
        file_put_contents($this->tempDir . '/src/test.template', 'Hello {{ name }}');
        $this->variables->set('name', 'World');

        $engine = $this->createEngine([
            'test' => ['src' => 'src/test.template', 'dist' => 'dist/test.txt'],
        ]);

        $this->assertTrue($engine->canRenderTemplate('test'));
    }

    public function testCanRenderTemplateReturnsFalseWhenTemplateNotFound(): void
    {
        $engine = $this->createEngine([]);

        $this->assertFalse($engine->canRenderTemplate('nonexistent'));
    }

    public function testCanRenderTemplateReturnsFalseWhenSrcFileMissing(): void
    {
        $engine = $this->createEngine([
            'test' => ['src' => 'src/nonexistent.template', 'dist' => 'dist/test.txt'],
        ]);

        $this->assertFalse($engine->canRenderTemplate('test'));
    }

    public function testGetMissingVariablesReturnsMissingNames(): void
    {
        mkdir($this->tempDir . '/src', 0755, true);
        file_put_contents($this->tempDir . '/src/test.template', '{{! foo }} and {{! bar }}');

        $engine = $this->createEngine([
            'test' => ['src' => 'src/test.template', 'dist' => 'dist/test.txt'],
        ]);

        $missing = $engine->getMissingVariables('test');
        $this->assertContains('foo', $missing);
        $this->assertContains('bar', $missing);
    }

    public function testGetMissingVariablesReturnsEmptyWhenTemplateNotFound(): void
    {
        $engine = $this->createEngine([]);

        $this->assertSame([], $engine->getMissingVariables('nonexistent'));
    }

    public function testWriteTemplateSkipsWithOverwriteNeverWhenFileExists(): void
    {
        mkdir($this->tempDir . '/src', 0755, true);
        mkdir($this->tempDir . '/dist', 0755, true);
        file_put_contents($this->tempDir . '/src/test.template', 'new content');
        file_put_contents($this->tempDir . '/dist/test.txt', 'old content');

        $engine = $this->createEngine([
            'test' => ['src' => 'src/test.template', 'dist' => 'dist/test.txt', 'overwrite' => 'never'],
        ]);
        $result = $engine->writeTemplate('test');

        $this->assertFalse($result['written']);
        $this->assertTrue($result['skipped']);
        $this->assertStringContainsString('never', $result['reason']);
        $this->assertSame('old content', file_get_contents($this->tempDir . '/dist/test.txt'));
    }

    public function testWriteTemplateSkipsWithOverwriteAskWhenCallbackReturnsFalse(): void
    {
        mkdir($this->tempDir . '/src', 0755, true);
        mkdir($this->tempDir . '/dist', 0755, true);
        file_put_contents($this->tempDir . '/src/test.template', 'new content');
        file_put_contents($this->tempDir . '/dist/test.txt', 'old content');

        $engine = $this->createEngine([
            'test' => ['src' => 'src/test.template', 'dist' => 'dist/test.txt', 'overwrite' => 'ask'],
        ]);
        $result = $engine->writeTemplate('test', confirmOverwrite: fn (string $path): bool => false);

        $this->assertFalse($result['written']);
        $this->assertTrue($result['skipped']);
        $this->assertSame('old content', file_get_contents($this->tempDir . '/dist/test.txt'));
    }

    public function testWriteTemplateSkipsWithOverwriteAskWhenNoCallbackAvailable(): void
    {
        mkdir($this->tempDir . '/src', 0755, true);
        mkdir($this->tempDir . '/dist', 0755, true);
        file_put_contents($this->tempDir . '/src/test.template', 'new content');
        file_put_contents($this->tempDir . '/dist/test.txt', 'old content');

        $engine = $this->createEngine([
            'test' => ['src' => 'src/test.template', 'dist' => 'dist/test.txt', 'overwrite' => 'ask'],
        ]);
        $result = $engine->writeTemplate('test');

        $this->assertFalse($result['written']);
        $this->assertTrue($result['skipped']);
        $this->assertStringContainsString('no interactive', $result['reason']);
    }

    public function testWriteTemplateCreatesDirectoryIfMissing(): void
    {
        mkdir($this->tempDir . '/src', 0755, true);
        file_put_contents($this->tempDir . '/src/test.template', 'Hello');

        $engine = $this->createEngine([
            'test' => ['src' => 'src/test.template', 'dist' => 'deeply/nested/dir/test.txt'],
        ]);
        $result = $engine->writeTemplate('test');

        $this->assertTrue($result['written']);
        $this->assertFileExists($this->tempDir . '/deeply/nested/dir/test.txt');
    }

    public function testRenderAllSkipsTemplateNotForContext(): void
    {
        mkdir($this->tempDir . '/src', 0755, true);
        file_put_contents($this->tempDir . '/src/local.template', 'local only');
        file_put_contents($this->tempDir . '/src/prod.template', 'prod only');

        $engine = $this->createEngine([
            'local-tpl' => ['src' => 'src/local.template', 'dist' => 'dist/local.txt', 'contexts' => ['local']],
            'prod-tpl'  => ['src' => 'src/prod.template', 'dist' => 'dist/prod.txt', 'contexts' => ['prod']],
        ], context: 'local');
        $results = $engine->renderAll(force: true);

        $this->assertTrue($results['local-tpl']['written']);
        $this->assertFalse($results['prod-tpl']['written']);
        $this->assertTrue($results['prod-tpl']['skipped']);
        $this->assertStringContainsString('local', $results['prod-tpl']['reason']);
    }

    public function testSetConfirmOverwriteSetsGlobalCallback(): void
    {
        mkdir($this->tempDir . '/src', 0755, true);
        mkdir($this->tempDir . '/dist', 0755, true);
        file_put_contents($this->tempDir . '/src/test.template', 'new content');
        file_put_contents($this->tempDir . '/dist/test.txt', 'old content');

        $engine = $this->createEngine([
            'test' => ['src' => 'src/test.template', 'dist' => 'dist/test.txt', 'overwrite' => 'ask'],
        ]);
        $engine->setConfirmOverwrite(fn (string $path): bool => false);
        $result = $engine->writeTemplate('test');

        $this->assertFalse($result['written']);
        $this->assertTrue($result['skipped']);
        $this->assertSame('User chose not to overwrite', $result['reason']);
    }

    public function testSwitchContextChangesContextForFiltering(): void
    {
        mkdir($this->tempDir . '/src', 0755, true);
        file_put_contents($this->tempDir . '/src/prod.template', 'prod only');

        $engine = $this->createEngine([
            'prod-tpl' => ['src' => 'src/prod.template', 'dist' => 'dist/prod.txt', 'contexts' => ['prod']],
        ], context: 'local');

        $resultsLocal = $engine->renderAll();
        $this->assertFalse($resultsLocal['prod-tpl']['written']);

        $engine->switchContext('prod');
        $resultsProd = $engine->renderAll(force: true);
        $this->assertTrue($resultsProd['prod-tpl']['written']);
    }

    public function testRenderDelegatesStringRendering(): void
    {
        $this->variables->set('name', 'World');
        $engine = $this->createEngine([]);

        $result = $engine->render('Hello {{ name }}');

        $this->assertSame('Hello World', $result);
    }

    public function testGetTemplateReturnsConfigForExistingTemplate(): void
    {
        $engine = $this->createEngine([
            'env' => ['src' => 'src/env.dist', 'dist' => '.env'],
        ]);

        $config = $engine->getTemplate('env');

        $this->assertNotNull($config);
        $this->assertSame('env', $config->name);
    }

    public function testGetTemplateReturnsNullForNonExistingTemplate(): void
    {
        $engine = $this->createEngine([]);

        $this->assertNull($engine->getTemplate('nonexistent'));
    }

    public function testRenderAllWithForceTrueOverwritesExistingFile(): void
    {
        mkdir($this->tempDir . '/src', 0755, true);
        mkdir($this->tempDir . '/dist', 0755, true);
        file_put_contents($this->tempDir . '/src/test.template', 'New {{ name }}');
        file_put_contents($this->tempDir . '/dist/test.txt', 'old content');
        $this->variables->set('name', 'Content');

        $engine = $this->createEngine([
            'test' => ['src' => 'src/test.template', 'dist' => 'dist/test.txt', 'overwrite' => 'never'],
        ]);

        // Without force: skipped
        $results = $engine->renderAll(force: false);
        $this->assertFalse($results['test']['written']);

        // With force: written despite overwrite=never
        $results = $engine->renderAll(force: true);
        $this->assertTrue($results['test']['written']);
        $this->assertSame('New Content', file_get_contents($this->tempDir . '/dist/test.txt'));
    }

    public function testRenderAllCatchesSputnikExceptionAsError(): void
    {
        // Template with missing source file (triggers InvalidConfigException in writeTemplate)
        $engine = $this->createEngine([
            'broken' => ['src' => 'nonexistent.template', 'dist' => 'dist/broken.txt'],
        ]);

        $results = $engine->renderAll(force: true);

        $this->assertFalse($results['broken']['written']);
        $this->assertArrayHasKey('error', $results['broken']);
    }

    public function testGetTemplatesReturnsAllConfigured(): void
    {
        $engine = $this->createEngine([
            'env' => ['src' => 'src/env.dist', 'dist' => '.env'],
            'nginx' => ['src' => 'src/nginx.conf.dist', 'dist' => 'nginx.conf'],
        ]);

        $templates = $engine->getTemplates();

        $this->assertCount(2, $templates);
        $this->assertArrayHasKey('env', $templates);
        $this->assertArrayHasKey('nginx', $templates);
    }

    private function createEngine(array $templates, string $context = 'local'): TemplateEngine
    {
        $config = new Configuration(['templates' => $templates]);

        return new TemplateEngine($config, $this->variables, $this->tempDir, $context);
    }
}
