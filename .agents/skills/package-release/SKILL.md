---
name: package-release
description: "Use this skill when preparing a Juicebox release: checking release readiness, PR labels, version choice, GitHub release notes, tags, CHANGELOG.md, or changing the release workflows. Never tag, push or publish without explicit approval."
---

# Package Release

## Primary Goal

Prepare a safe release and tell the user exactly what to approve. Never tag, push, create a GitHub release, or create labels unless the user explicitly approves that specific action.

## How a release works

1. PRs merged into `main` carry a label that decides their section in the release notes (`.github/release.yml`): `breaking`, `enhancement`, `bug`, `documentation`, `dependencies`, `maintenance`; `skip-changelog` hides a PR.
2. A GitHub release named `vX.Y.Z` is published; this creates the tag. Notes are generated from the labels.
3. `.github/workflows/update-changelog.yml` fires on the published release, prepends the notes to `CHANGELOG.md` under `## [Unreleased]` and commits to `main`. Do not edit released sections of `CHANGELOG.md` by hand and keep the `## [Unreleased]` heading (a convention test enforces it).

## Workflow

1. Confirm the working tree is clean and `main` is up to date; list what is unreleased with `git log --oneline $(git describe --tags --abbrev=0)..HEAD` and check that each merged PR has a label.
2. Run `composer test`, then `composer test:coverage` and `composer test:mutate` (see the `package-testing` skill for loading a coverage driver). All must be green; CI runs the same gates.
3. Propose the version: SemVer with a `v` prefix. While below 1.0.0, a breaking change bumps the minor and anything else bumps the patch. Existing tags `0.1.0` and `0.2.0` have no prefix; use `v` from the next release on.
4. Draft the command for approval, for example `gh release create vX.Y.Z --target main --generate-notes --title vX.Y.Z`, and show the user the generated notes first (`--draft` is fine).
5. After the user publishes or approves, remind them to pull `main` to get the `Update CHANGELOG` commit.

## Anti-Patterns

- Tagging, pushing, publishing or creating labels without explicit approval.
- Recommending a release while any gate is red or a lane was skipped.
- Editing released sections of `CHANGELOG.md` or removing `## [Unreleased]`.
- Changing release workflows without checking `.github/workflows/tests.yml`.
