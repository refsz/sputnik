# Contexts

## Overview

Contexts represent different environments (dev, prod, staging) with variable overrides. One context is active at a time.

## Configuration

```neon
contexts:
    dev:
        description: Local development
        variables:
            constants:
                appEnv: dev
                debug: true

    prod:
        description: Production settings
        variables:
            constants:
                appEnv: prod
                debug: false

defaults:
    context: dev
```

## Switching Contexts

```bash
sputnik context:switch prod
# or shorthand:
sputnik switch prod
sputnik use prod
```

## What Happens on Switch

1. Context is persisted to `.sputnik/state.json`
2. `ContextSwitchedEvent` is dispatched
3. Built-in listener `SwitchContextOnServices` (priority 100) updates VariableResolver and TemplateEngine
4. Built-in listener `RegenerateTemplatesOnContextSwitch` (priority 0) re-renders all templates
5. Custom listeners run (use negative priority to run after templates)

## Listing Contexts

```bash
sputnik context:list
# or:
sputnik contexts
```

Shows all contexts with descriptions. Current context marked with `*`.

## Variable Overrides

Context constants override global constants:

```neon
variables:
    constants:
        appEnv: dev       # default

contexts:
    prod:
        variables:
            constants:
                appEnv: prod  # overrides when prod is active
```

## State Persistence

Active context is stored in `.sputnik/state.json`. This file is auto-created and should be gitignored (it's inside `.sputnik/` directory).

## Built-in Variable

The current context name is available as the built-in variable `context`:

```
ENVIRONMENT={{ context }}
```
