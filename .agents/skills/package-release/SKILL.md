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
3. `.github/workflows/update-changelog.yml` fires when a release is published (not for drafts or pre-releases), uses the release **title** as the version heading, so it must be exactly `vX.Y.Z`; it prepends the notes to `CHANGELOG.md` under `## [Unreleased]` and commits to `main`. Do not edit released sections of `CHANGELOG.md` by hand and keep the `## [Unreleased]` heading (a convention test enforces it).

## Workflow

1. Confirm the working tree is clean and you are on an up-to-date `main`. List what is unreleased with `git log --oneline $(git describe --tags --abbrev=0)..HEAD`, then check that every merged PR since the last tag has a label: `gh pr list --state merged --base main --search "merged:>=$(git log -1 --format=%cs $(git describe --tags --abbrev=0))" --json number,title,labels`. Unlabelled PRs land in "Other Changes"; propose labels, but do not create or change them without approval.
2. Run `composer test`, then `composer test:coverage` and `composer test:mutate` (see the `package-testing` skill for loading a coverage driver; run mutation serially). Also confirm CI is green for the release commit: `gh run list --branch main --limit 1`. All must be green.
3. Propose the version: SemVer with a `v` prefix. While below 1.0.0, a breaking change bumps the minor and anything else bumps the patch. Existing tags `0.1.0` and `0.2.0` have no prefix; use `v` from the next release on.
4. Show the user the version, the labelled changes and the generated notes (`gh release create vX.Y.Z --target main --generate-notes --title vX.Y.Z --draft` creates a draft to review). Ask for explicit approval to publish.
5. Once the user explicitly approves, you may run the approved `gh release create` command (or publish the draft with `gh release edit vX.Y.Z --draft=false`). Do not run it before that approval, and do not push or tag any other way.
6. Verify the `Update Changelog` workflow: `gh run list --workflow update-changelog.yml --limit 1`. Then tell the user to pull `main` for the `Update CHANGELOG` commit.

## If the changelog workflow fails

The workflow pushes straight to `main`. If that push is rejected (for example after branch protection was tightened), do not edit `CHANGELOG.md` by hand on `main`. Report the failure and the error. The fix is a decision for the user: give the workflow a token of an admin (a fine-grained token with contents: write, stored as a repo secret, passed to the checkout step) or change the workflow to open a PR.

## Anti-Patterns

- Tagging, pushing, publishing or creating labels without explicit approval.
- Recommending a release while any gate is red or a lane was skipped.
- Editing released sections of `CHANGELOG.md` or removing `## [Unreleased]`.
- Changing release workflows without checking `.github/workflows/tests.yml`.
