<?php

declare(strict_types=1);

namespace Sputnik\Variable;

use Sputnik\Config\Configuration;
use Sputnik\Exception\InvalidConfigException;
use Sputnik\Secret\SecretRegistry;

final class VariableResolver implements VariableResolverInterface
{
    /**
     * The key each secret type requires - a definition missing it is not a
     * valid definition of that type, it is an arbitrary map (e.g. a nested
     * map of unrelated credentials) that happens to have no 'type' key and
     * would otherwise default to 'command' and silently resolve to null.
     */
    private const REQUIRED_KEY_BY_TYPE = [
        'command' => 'command',
        'script' => 'script',
        'env' => 'name',
    ];

    /**
     * @var array<string, mixed>
     */
    private array $resolved = [];

    /**
     * @var array<string, mixed>
     */
    private array $runtimeOverrides = [];

    private bool $initialized = false;

    private readonly DynamicVariableResolver $dynamicResolver;

    public function __construct(
        private readonly Configuration $config,
        private ?string $contextName = null,
        ?string $workingDir = null,
        private readonly SecretRegistry $secrets = new SecretRegistry(),
    ) {
        $this->dynamicResolver = new DynamicVariableResolver($workingDir);
    }

    /**
     * Switch to a different context. Resets resolved variables.
     */
    public function switchContext(string $contextName): void
    {
        $this->contextName = $contextName;
        $this->initialized = false;
        $this->resolved = [];
    }

    /**
     * Create a new resolver with runtime variable overrides.
     * Runtime variables have highest priority and override all other sources.
     *
     * @param array<string, mixed> $overrides
     */
    public function withOverrides(array $overrides): self
    {
        $clone = clone $this;
        $clone->runtimeOverrides = array_merge($this->runtimeOverrides, $overrides);
        $clone->initialized = false;
        $clone->resolved = [];

        return $clone;
    }

    public function resolve(string $name, mixed $default = null): mixed
    {
        $this->initialize();

        $root = explode('.', $name)[0];
        if ($this->secrets->isSecret($root) && !\array_key_exists($root, $this->resolved)) {
            $this->resolveSecret($root);
        }

        // A stored null - a failed secret as much as any other variable that
        // resolves to null - counts as absent, matching the null-as-absent
        // rule TemplateRenderer already applies. The cache entry itself is
        // untouched, so a failed secret is not re-resolved on the next access.
        return $this->getNestedValue($this->resolved, $name, $default) ?? $default;
    }

    public function has(string $name): bool
    {
        $this->initialize();

        $root = explode('.', $name)[0];
        if ($this->secrets->isSecret($root)) {
            if ($root === $name) {
                return true;
            }

            // A nested path under a secret root is an explicit reference to
            // the secret's value, so resolving here (unlike the plain-name
            // case above) is legitimate and keeps has() honest with resolve().
            if (!\array_key_exists($root, $this->resolved)) {
                $this->resolveSecret($root);
            }
        }

        return $this->hasNestedValue($this->resolved, $name);
    }

    public function all(): array
    {
        $this->initialize();

        return $this->flatten($this->resolved);
    }

    /**
     * Get all variables as a nested array.
     *
     * @return array<string, mixed>
     */
    public function allNested(): array
    {
        $this->initialize();

        return $this->resolved;
    }

    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        // Start with constants
        $this->resolved = $this->config->getConstants();

        // Add context as a built-in variable
        if ($this->contextName !== null) {
            $this->resolved['context'] = $this->contextName;
        }

        // Apply context overrides if a context is set
        if ($this->contextName !== null) {
            $context = $this->config->getContext($this->contextName);
            if ($context !== null && isset($context['variables']['constants'])) {
                $this->resolved = $this->mergeDeep(
                    $this->resolved,
                    $context['variables']['constants'],
                );
            }
        }

        // Resolve dynamic variables
        $dynamics = $this->config->getDynamics();
        foreach ($dynamics as $name => $definition) {
            $this->resolved[$name] = $this->dynamicResolver->resolve($definition);
        }

        // Apply runtime overrides (highest priority)
        if ($this->runtimeOverrides !== []) {
            $this->resolved = $this->mergeDeep($this->resolved, $this->runtimeOverrides);
        }

        $this->declareSecrets();

        $this->initialized = true;
    }

    private function declareSecrets(): void
    {
        $this->assertNoContextLevelSecrets();

        $definitions = $this->config->getSecrets();

        if ($definitions === []) {
            return;
        }

        if (\array_key_exists('context', $definitions)) {
            throw new InvalidConfigException(
                "Variable 'context' is a built-in variable and cannot be declared as a secret",
            );
        }

        $declaredElsewhere = array_merge(
            $this->config->getConstants(),
            $this->config->getDynamics(),
            $this->contextConstants(),
        );

        foreach (array_keys($definitions) as $name) {
            if (\array_key_exists($name, $declaredElsewhere)) {
                throw new InvalidConfigException(\sprintf(
                    "Variable '%s' is declared as a secret and as a constant or dynamic variable",
                    $name,
                ));
            }

            // Lookup splits a variable name on the first dot to find a nested
            // path, so a secret literally named with a dot could never be
            // resolved or masked once declared - reject it up front instead.
            if (str_contains($name, '.')) {
                throw new InvalidConfigException(\sprintf(
                    "Secret name '%s' is not valid: secret names are flat and cannot contain '.'",
                    $name,
                ));
            }

            $this->assertSupportedSecretType($name, $definitions[$name]);
        }

        $this->secrets->declareSecrets(array_keys($definitions));

        // A runtime override of a secret never goes through resolveSecret(), so
        // its value has to reach the registry here or it would print unmasked.
        foreach (array_keys($definitions) as $name) {
            if (\array_key_exists($name, $this->resolved)) {
                $this->secrets->remember($name, $this->resolved[$name]);
            }
        }
    }

    /**
     * Secrets are not context-overridable, matching dynamics. A
     * `contexts.*.variables.secrets` block is silently ignored by resolve()
     * otherwise - the name never matches a declared secret - so it must be
     * rejected explicitly rather than left to resolve to nothing.
     */
    private function assertNoContextLevelSecrets(): void
    {
        foreach ($this->config->getContexts() as $contextName => $context) {
            $variables = $context['variables'] ?? null;

            // array_key_exists, not isset: an empty `secrets:` key is null in NEON
            // and must be rejected just as loudly as a populated one.
            if (\is_array($variables) && \array_key_exists('secrets', $variables)) {
                throw new InvalidConfigException(\sprintf(
                    "Context '%s' declares a 'variables.secrets' block, but secrets are not context-overridable",
                    $contextName,
                ));
            }
        }
    }

    /**
     * Context constants are merged into the resolved set before secrets are
     * declared, so a colliding name there would silently declassify a secret.
     *
     * @return array<string, mixed>
     */
    private function contextConstants(): array
    {
        if ($this->contextName === null) {
            return [];
        }

        $context = $this->config->getContext($this->contextName);

        return $context['variables']['constants'] ?? [];
    }

    private function assertSupportedSecretType(string $name, mixed $definition): void
    {
        if (!\is_array($definition)) {
            return;
        }

        $type = $definition['type'] ?? 'command';

        if (!\in_array($type, ['command', 'script', 'env'], true)) {
            throw new InvalidConfigException(\sprintf(
                "Secret '%s' uses unsupported type '%s'; use command, script or env",
                $name,
                \is_string($type) ? $type : get_debug_type($type),
            ));
        }

        $requiredKey = self::REQUIRED_KEY_BY_TYPE[$type];

        if (!\array_key_exists($requiredKey, $definition)) {
            throw new InvalidConfigException(\sprintf(
                "Secret '%s' is missing its '%s' key; a nested map is not supported under 'secrets'",
                $name,
                $requiredKey,
            ));
        }
    }

    private function resolveSecret(string $name): void
    {
        $definition = $this->config->getSecrets()[$name];

        $value = \is_array($definition)
            ? $this->dynamicResolver->resolve($definition)
            : $definition;

        $this->resolved[$name] = $value;
        $this->secrets->remember($name, $value);
    }

    /**
     * @param array<string, mixed> $array
     */
    private function getNestedValue(array $array, string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $array;

        foreach ($keys as $segment) {
            if (!\is_array($value) || !\array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $array
     */
    private function hasNestedValue(array $array, string $key): bool
    {
        $keys = explode('.', $key);
        $value = $array;

        foreach ($keys as $segment) {
            if (!\is_array($value) || !\array_key_exists($segment, $value)) {
                return false;
            }

            $value = $value[$segment];
        }

        return true;
    }

    /**
     * @param array<string, mixed> $array
     *
     * @return array<string, mixed>
     */
    private function flatten(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix === '' ? $key : $prefix . '.' . $key;

            if (\is_array($value) && $value !== [] && array_keys($value) !== range(0, \count($value) - 1)) {
                // Associative array - recurse
                $result = array_merge($result, $this->flatten($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function mergeDeep(array $base, array $override): array
    {
        $result = $base;

        foreach ($override as $key => $value) {
            if (\is_array($value) && isset($result[$key]) && \is_array($result[$key])) {
                $result[$key] = $this->mergeDeep($result[$key], $value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
