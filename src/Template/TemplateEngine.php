<?php

declare(strict_types=1);

namespace Sputnik\Template;

use Sputnik\Config\Configuration;
use Sputnik\Exception\InvalidConfigException;
use Sputnik\Exception\RuntimeException as SputnikRuntimeException;
use Sputnik\Exception\SputnikException;
use Sputnik\Variable\VariableResolverInterface;

final class TemplateEngine
{
    private readonly TemplateParser $parser;

    private readonly TemplateRenderer $renderer;

    /**
     * @var array<string, TemplateConfig>
     */
    private array $templates = [];

    private bool $templatesLoaded = false;

    /**
     * @var (callable(string): bool)|null
     */
    private $confirmOverwrite;

    public function __construct(
        private readonly Configuration $config,
        private readonly VariableResolverInterface $variables,
        private readonly string $workingDir,
        private string $contextName = 'local',
    ) {
        $this->parser = new TemplateParser();
        $this->renderer = new TemplateRenderer($this->parser, $this->variables);
    }

    /**
     * Switch to a different context for template filtering.
     */
    public function switchContext(string $contextName): void
    {
        $this->contextName = $contextName;
    }

    /**
     * Set a callback for interactive overwrite confirmation.
     *
     * @param callable(string $path): bool $callback Returns true to overwrite
     */
    public function setConfirmOverwrite(callable $callback): void
    {
        $this->confirmOverwrite = $callback;
    }

    /**
     * Render a template string.
     */
    public function render(string $template): string
    {
        return $this->renderer->render($template);
    }

    /**
     * Render a template file by name.
     *
     * @return array{content: string, path: string}
     */
    public function renderTemplate(string $name): array
    {
        $this->loadTemplates();

        if (!isset($this->templates[$name])) {
            throw new InvalidConfigException('Template not found: ' . $name);
        }

        $template = $this->templates[$name];
        $srcPath = $this->resolvePath($template->src);

        if (!file_exists($srcPath)) {
            throw new InvalidConfigException('Template source file not found: ' . $srcPath);
        }

        $content = file_get_contents($srcPath);
        if ($content === false) {
            throw new SputnikRuntimeException('Could not read template file: ' . $srcPath);
        }

        $rendered = $this->renderer->render($content, $srcPath);
        $distPath = $this->resolvePath($template->dist);

        return [
            'content' => $rendered,
            'path' => $distPath,
        ];
    }

    /**
     * Render and write a template file.
     *
     * @param callable(string $path): bool|null $confirmOverwrite Called when overwrite=ask and file exists. Return true to overwrite.
     *
     * @return array{written: bool, path: string, skipped: bool, reason?: string}
     */
    public function writeTemplate(string $name, bool $force = false, ?callable $confirmOverwrite = null): array
    {
        $result = $this->renderTemplate($name);
        $template = $this->templates[$name];
        $distPath = $result['path'];

        // Check if file exists
        if (file_exists($distPath) && !$force) {
            if ($template->overwrite === 'never') {
                return [
                    'written' => false,
                    'path' => $distPath,
                    'skipped' => true,
                    'reason' => 'File exists and overwrite=never',
                ];
            }

            if ($template->overwrite === 'ask') {
                $callback = $confirmOverwrite ?? $this->confirmOverwrite;
                if ($callback !== null) {
                    if (!$callback($distPath)) {
                        return [
                            'written' => false,
                            'path' => $distPath,
                            'skipped' => true,
                            'reason' => 'User chose not to overwrite',
                        ];
                    }
                } else {
                    return [
                        'written' => false,
                        'path' => $distPath,
                        'skipped' => true,
                        'reason' => 'File exists and overwrite=ask (no interactive prompt available)',
                    ];
                }
            }
        }

        // Ensure directory exists
        $dir = \dirname($distPath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new SputnikRuntimeException('Could not create directory: ' . $dir);
        }

        // Write file
        $bytesWritten = file_put_contents($distPath, $result['content']);
        if ($bytesWritten === false) {
            throw new SputnikRuntimeException('Could not write file: ' . $distPath);
        }

        return [
            'written' => true,
            'path' => $distPath,
            'skipped' => false,
        ];
    }

    /**
     * Render all templates for the current context.
     *
     * @param callable(string $path): bool|null $confirmOverwrite called when overwrite=ask and file exists
     *
     * @return array<string, array{written: bool, path: string, skipped: bool, reason?: string, error?: string}>
     */
    public function renderAll(bool $force = false, ?callable $confirmOverwrite = null): array
    {
        $this->loadTemplates();
        $results = [];

        foreach ($this->templates as $name => $template) {
            if (!$template->isForContext($this->contextName)) {
                $results[$name] = [
                    'written' => false,
                    'path' => $this->resolvePath($template->dist),
                    'skipped' => true,
                    'reason' => 'Not for context: ' . $this->contextName,
                ];
                continue;
            }

            try {
                $results[$name] = $this->writeTemplate($name, $force, $confirmOverwrite);
            } catch (SputnikException $e) {
                $results[$name] = [
                    'written' => false,
                    'path' => $this->resolvePath($template->dist),
                    'skipped' => true,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Get all configured templates.
     *
     * @return array<string, TemplateConfig>
     */
    public function getTemplates(): array
    {
        $this->loadTemplates();

        return $this->templates;
    }

    /**
     * Get templates for the current context.
     *
     * @return array<string, TemplateConfig>
     */
    public function getTemplatesForContext(): array
    {
        $this->loadTemplates();

        return array_filter(
            $this->templates,
            fn (TemplateConfig $t): bool => $t->isForContext($this->contextName),
        );
    }

    /**
     * Check if a template can be rendered (all required variables available).
     */
    public function canRenderTemplate(string $name): bool
    {
        $this->loadTemplates();

        if (!isset($this->templates[$name])) {
            return false;
        }

        $template = $this->templates[$name];
        $srcPath = $this->resolvePath($template->src);

        if (!file_exists($srcPath)) {
            return false;
        }

        $content = file_get_contents($srcPath);
        if ($content === false) {
            return false;
        }

        return $this->renderer->canRender($content);
    }

    /**
     * Get missing variables for a template.
     *
     * @return list<string>
     */
    public function getMissingVariables(string $name): array
    {
        $this->loadTemplates();

        if (!isset($this->templates[$name])) {
            return [];
        }

        $template = $this->templates[$name];
        $srcPath = $this->resolvePath($template->src);

        if (!file_exists($srcPath)) {
            return [];
        }

        $content = file_get_contents($srcPath);
        if ($content === false) {
            return [];
        }

        return $this->renderer->getMissingVariables($content);
    }

    /**
     * Get a template config by name.
     */
    public function getTemplate(string $name): ?TemplateConfig
    {
        $this->loadTemplates();

        return $this->templates[$name] ?? null;
    }

    private function loadTemplates(): void
    {
        if ($this->templatesLoaded) {
            return;
        }

        $templateConfigs = $this->config->getTemplates();

        foreach ($templateConfigs as $name => $config) {
            $this->templates[$name] = TemplateConfig::fromArray($name, $config);
        }

        $this->templatesLoaded = true;
    }

    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return $this->workingDir . '/' . $path;
    }
}
