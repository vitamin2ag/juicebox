---
name: package-compatibility
description: "Use this skill when reviewing Juicebox code, dependencies or CI against the supported PHP and Statamic versions, composer constraints, or the prefer-lowest and prefer-stable CI lanes."
---

# Package Compatibility

## Primary Goal

Keep code, dependencies and workflows within the supported range: PHP `^8.5` and Statamic `^6` (see `composer.json`).

## Workflow

1. Read `composer.json` first for the PHP and Statamic constraints.
2. Check changed code against Statamic 6 and Laravel APIs that exist in the lowest supported versions, not only the locally installed ones.
3. CI (`.github/workflows/tests.yml`) runs every gate on `prefer-lowest` and `prefer-stable`; when changing dependencies, make sure both still resolve and pass.
4. Validate locally with the smallest command (`composer test`), then rely on CI for the lowest-version lane.

## Anti-Patterns

- Assuming the locally installed versions represent the supported range.
- Raising a constraint to dodge a failing `prefer-lowest` lane instead of fixing the code, unless the old range is truly unsupported.
- Removing the `prefer-lowest` lane because it is slower or noisier.
