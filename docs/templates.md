# Templates

## Overview

Sputnik renders template files by replacing variables. Templates are rendered once before the first task runs, and re-rendered on context switch.

## Configuration

```neon
templates:
    env:
        src: .env.dist
        dist: .env
        overwrite: always

    compose:
        src: dev-ops/docker/compose.override.yml.dist
        dist: compose.override.yml
        overwrite: never
        contexts: [dev]
```

- `src` (required) — source template path (relative to project root)
- `dist` (required) — output file path
- `overwrite` — `always` (default), `never`, `ask`
- `contexts` — restrict to specific contexts, null = all

## Syntax

**Variable substitution:**
```
DB_HOST={{ dbHost }}
DB_PORT={{ dbPort }}
```

**With default value:**
```
DEBUG={{ debug | "false" }}
LOG_LEVEL={{ logLevel | 'info' }}
```

**Required variable (no default, fails if missing):**
```
API_KEY={{! apiKey }}
```

**Escape literal braces:**
```
PATTERN=\{\{ not_a_variable \}\}
```
Renders as: `PATTERN={{ not_a_variable }}`

## Overwrite Modes

- `always` — overwrite existing files without asking (default)
- `never` — skip if file exists
- `ask` — prompt user for confirmation (interactive mode only)

## Context Filtering

Templates with a `contexts` array are only rendered when the current context matches:

```neon
templates:
    prodConfig:
        src: config.prod.dist
        dist: config.php
        contexts: [prod]
```

## Rendering Order

1. On first task execution — all templates for current context rendered
2. On context switch — all templates re-rendered for new context
