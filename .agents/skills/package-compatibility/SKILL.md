---
name: package-compatibility
description: "Use this skill when reviewing Juicebox code, dependencies or CI against the supported PHP and Statamic versions, or composer constraints."
---

# Package Compatibility

## Primary Goal

Keep code, dependencies and workflows within the supported range: PHP `^8.5` and Statamic `^6` (see `composer.json`).

## Workflow

1. Read `composer.json` first for the PHP and Statamic constraints.
2. Check changed code against Statamic 6 and Laravel APIs that exist in the lowest version the `composer.json` constraint allows, not only the locally installed one.
3. CI (`.github/workflows/tests.yml`) runs every gate against the newest allowed versions only. When raising or lowering a constraint, say why in the PR.
4. Validate locally with `composer test`.

## Anti-Patterns

- Assuming the locally installed versions represent the supported range.
- Declaring a lower bound (for example `statamic/cms`) that nothing tests.
- Using an API newer than the lowest version the constraint allows without raising the constraint.
