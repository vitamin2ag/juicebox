# Contribution Guide

Thank you for considering contributing to Juicebox! Please review the following guidelines before submitting a pull request.

For significant changes, please open an issue first so we can discuss the approach.

## Process

1. Fork the project
2. Create a new branch
3. Code, test, commit, and push
4. Open a pull request detailing your changes

Every change, including new features, goes through a pull request that passes CI; please don't push to `main` directly.

## Guidelines

- Ensure the coding style passes by running `composer lint`.
- Send a coherent commit history, making sure each commit in your pull request is meaningful.
- You may need to [rebase](https://git-scm.com/book/en/v2/Git-Branching-Rebasing) to avoid merge conflicts.
- Maintainers label merged pull requests (`enhancement`, `bug`, ...); the labels group the release notes.
- Please remember that we follow [SemVer](http://semver.org/).

## Setup

Clone your fork, then install the dev dependencies:

```bash
composer install
```

## Lint

Lint your code:

```bash
composer lint
```

## Tests

Run all tests:

```bash
composer test            # analyse, lint, refactor, type coverage, tests
composer test:coverage   # code coverage, 100% required (needs a coverage driver)
composer test:mutate     # mutation score, 100% required (needs a coverage driver)
```
