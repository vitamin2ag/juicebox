---
name: package-release
description: "Use this skill when preparing a Juicebox release: checking release readiness, PR labels, version choice, GitHub release notes, tags, CHANGELOG.md, or changing the release workflows. Tag, push and publish only after explicit approval."
---

# Package Release

## Primary Goal

Prepare a safe release and tell the user exactly what to approve. Tag, push, create a GitHub release or create labels only after the user explicitly approves that specific action.

## How a release works

1. PRs merged into `main` carry a label that decides their section in the release notes (`.github/release.yml`): `breaking`, `enhancement`, `bug`, `documentation`, `dependencies`, `maintenance`; `skip-changelog` hides a PR.
2. A GitHub release named `vX.Y.Z` is published; this creates the tag. Notes are generated from the labels.
3. `.github/workflows/update-changelog.yml` fires when a release is published (not for drafts or pre-releases). It uses the release **title** as the version heading, so the title is exactly `vX.Y.Z`. It prepends the notes to `CHANGELOG.md` under `## [Unreleased]` and commits to `main`. Leave released sections of `CHANGELOG.md` as they are and keep the `## [Unreleased]` heading (a convention test enforces it).

## Workflow

1. Confirm the working tree is clean and you are on an up-to-date `main`. List what is unreleased with `git log --oneline $(git describe --tags --abbrev=0)..HEAD`, then check that every merged PR since the last tag has a label: `gh pr list --state merged --base main --search "merged:>=$(git log -1 --format=%cs $(git describe --tags --abbrev=0))" --json number,title,labels`. Unlabelled PRs land in "Other Changes"; propose labels and change them only after approval.
2. Run `composer test`, then `composer test:coverage` and `composer test:mutate` (the `package-testing` skill explains how to load a coverage driver). Confirm CI is green for the release commit: `gh run list --branch main --limit 1`. All must be green.
3. Propose the version: SemVer with a `v` prefix. While below 1.0.0, a breaking change bumps the minor and anything else bumps the patch.
4. Show the user the version, the labelled changes and the generated notes (`gh release create vX.Y.Z --target main --generate-notes --title vX.Y.Z --draft` creates a draft to review). Ask for explicit approval to publish.
5. After explicit approval, run the approved `gh release create` command (or publish the draft with `gh release edit vX.Y.Z --draft=false`). Create tags only through that command.
6. Verify the `Update Changelog` workflow: `gh run list --workflow update-changelog.yml --limit 1`. Then tell the user to pull `main` for the `Update CHANGELOG` commit.

## If the changelog workflow fails

The workflow pushes straight to `main`. When that push is rejected (for example after branch protection was tightened), report the failure and the error, and leave `CHANGELOG.md` on `main` untouched. The user decides the fix: give the workflow a token of an admin (a fine-grained token with contents: write, stored as a repo secret, passed to the checkout step) or change the workflow to open a PR.

## Rules

- Tag, push, publish and create labels only after explicit approval of that action.
- Recommend a release only when every gate is green and no lane was skipped.
- Keep released sections of `CHANGELOG.md` and the `## [Unreleased]` heading as they are.
- Check `.github/workflows/tests.yml` when changing release workflows.
