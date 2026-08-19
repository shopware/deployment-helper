# Contributing to Deployment Helper

Thanks for your interest in contributing.

## Before opening a pull request

Small fixes can go straight to a PR. Examples:

- typo fixes
- broken links
- small documentation improvements
- obvious bug fixes with a clear test or reproduction

For anything larger, please open an issue first and describe what you want to change before starting implementation. This includes:

- new features, commands, or subcommands
- changes to existing command behavior
- changes to flags, arguments, defaults, or output format
- larger refactors

This helps us confirm the direction, avoid duplicate work, and keep the project maintainable.

A draft PR is welcome if it helps explain the idea, but feature PRs should generally be discussed before they are reviewed or merged.

## Pull requests

When opening a PR, please include:

- what changed
- why it changed
- how it was tested
- any related issue or discussion

Please keep PRs focused. Smaller PRs are easier to review and merge.

## Development

Develop (branch of) from `main` unless you work on a big feature / epic,
where you not want to block bug fix releases with a half finished implementation.
For that use a dedicated feature branch (e.g. `next`) as your target branch.

Before submitting, run the relevant checks locally. Add or update tests for bug fixes and new behavior.

## Reviews

Maintainers may ask for changes, suggest a different direction, or decline a PR if the approach was not discussed beforehand. That is not personal; it is how we keep the project consistent and sustainable.

## Releases

In general releases happen from the default `main` branch with everything included there at the time of release.
If a feature branch (e.g. `next`) is ready for release it needs to be merged back into `main` before the release to be included.
The specific release process is explained in [RELEASING.md](RELEASING.md) and done on demand by the maintainers.
