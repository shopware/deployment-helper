# Agent Instructions

## Purpose

This repository contains the Shopware Deployment Helper, a PHP CLI/library used to install and update Shopware projects. It is intended to work independently of the exact Shopware version for Shopware versions newer than 6.5.

Use this file as the default guidance for coding agents working in this repository. Prefer repository conventions and existing patterns over generic PHP or Symfony conventions when they differ.

## Tech stack

* PHP 8.2 or newer.
* Composer for dependency management and project scripts.
* Symfony Console, DependencyInjection, EventDispatcher, Config, Process, Filesystem, Finder, Dotenv, YAML, and HttpClient components.
* PHPUnit 11 for tests.
* PHPStan at level 8 with strict/deprecation/Symfony/PHPUnit extensions.
* PHP-CS-Fixer for formatting and style checks.

The library namespace is `Shopware\Deployment\` and maps to `src/`. Test classes use `Shopware\Deployment\Tests\` and map to `tests/`.

## Repository map

* `bin/shopware-deployment-helper` — executable CLI entry point.
* `src/Application.php` — Symfony Console application bootstrap and service-container setup.
* `src/Command/` — console commands.
* `src/Config/` — deployment-helper configuration handling.
* `src/DependencyInjection/` — dependency-injection related code.
* `src/Event/` — application events.
* `src/Helper/` — focused helper utilities.
* `src/Integration/` — Shopware/environment integration code.
* `src/Resources/` — service configuration and other runtime resources.
* `src/Services/` — application services.
* `src/Struct/` — data/value structures.
* `tests/` — PHPUnit tests, generally mirroring the source-area organization.
* `.github/workflows/php.yml` — unit, static-analysis, and coding-style CI.
* `.github/workflows/integration.yml` — end-to-end Shopware install/update checks.
* `TELEMETRY.md` — telemetry behavior and opt-out documentation.

## Setup

Install development dependencies with:

```bash
composer install
```

Do not update dependencies or regenerate a lock file unless the task specifically requires dependency changes.

## Standard validation

Run the narrowest relevant checks while developing, then run the full local validation set before considering a change complete.

### Unit tests

```bash
composer test
```

For a focused test, invoke PHPUnit directly, for example:

```bash
vendor/bin/phpunit tests/Path/To/Test.php
vendor/bin/phpunit --filter testMethodName
```

Tests are configured to fail on warnings and risky tests and to be strict about unexpected output. Preserve those expectations when adding tests.

### Static analysis

```bash
composer phpstan
```

Use the Composer script rather than calling PHPStan directly for the full check. The script first runs the deployment helper with:

```bash
DEV_MODE=true PROJECT_ROOT=. bin/shopware-deployment-helper -q
```

This generates `var/cache/container.xml`, which PHPStan's Symfony integration consumes.

### Coding style

Check formatting with:

```bash
composer cs-dry
```

Apply automatic fixes with:

```bash
composer cs-fix
```

The configured rules include Symfony/Symfony-risky rules, PHP 8.x migration rules, non-Yoda comparisons, and one-space concatenation.

### Coverage

CI runs PHPUnit with Cobertura coverage:

```bash
composer test-coverage-cobertura
```

HTML coverage is available locally with:

```bash
composer test-coverage-html
```

Do not commit generated coverage or cache artifacts.

## Before finishing a change

For normal PHP source changes, run at least:

```bash
composer test
composer phpstan
composer cs-dry
```

If the change affects installation, upgrades, environment detection, process execution, Shopware integration, Composer behavior, plugin handling, or retry/recovery behavior, also reason through the integration workflow in `.github/workflows/integration.yml`. Run equivalent integration coverage when the environment makes that practical.

CI validates unit tests on PHP 8.2 and 8.3. Avoid code that only works on one of those runtimes.

## Implementation conventions

* Add `declare(strict_types=1);` to PHP files, following existing source conventions.
* Keep code compatible with the minimum supported PHP version, PHP 8.2.
* Prefer typed properties, parameter types, and return types over unnecessary PHPDoc-only typing.
* Follow the existing namespace/directory mapping.
* Keep services small and focused; extend an existing service/helper when that is the established responsibility rather than adding unrelated behavior to a command.
* Use Symfony components already present in the project instead of introducing parallel abstractions without a clear need.
* Prefer dependency injection over service location for new code.
* Match nearby naming, exception, and constructor patterns before inventing a new pattern.
* Avoid speculative refactors in bug fixes. Keep changes scoped to the requested behavior.

## Console commands and DI

The application builds a Symfony DependencyInjection container at runtime. Console commands are autoconfigured from the `#[AsCommand]` attribute, and event listeners are autoconfigured from `#[AsEventListener]`.

When adding a command or listener:

* Use the appropriate Symfony attribute.
* Make dependencies constructor-injectable and ensure they can be autowired by the existing service configuration.
* Keep command classes focused on input/output and orchestration; put reusable behavior in services/helpers.
* Preserve normal Symfony Console exit-code semantics.

The application accepts a global `--project-config` option pointing to a `.shopware-project.yaml` file. Changes around project configuration must preserve this explicit override path.

## Project-root behavior

The helper can run from different installation layouts. The CLI entry point deliberately searches several possible Composer autoload locations, including Shopware plugin/static-plugin layouts.

`Application` determines the Shopware project root by:

1. honoring `PROJECT_ROOT` when set, or
2. walking upward until it finds `bin/console`.

Do not assume the repository itself is always the runtime project root. Be careful with new relative filesystem paths; derive paths from the resolved project root or an injected path when appropriate.

## Tests

* Place tests under `tests/` in the corresponding functional area.
* Name test classes and namespaces consistently with existing tests.
* Add regression coverage for bug fixes whenever the behavior is testable.
* Prefer deterministic unit tests over network, clock, or machine-dependent tests.
* Avoid real external telemetry/network calls in unit tests.
* When code mutates environment variables or globals, restore state so tests remain isolated.
* Do not weaken PHPUnit's warning/risky/output strictness to make a test pass.

## Integration-sensitive changes

The GitHub integration workflow covers three important scenarios:

1. installing a fresh Shopware project with the helper,
2. recovering/retrying an interrupted installation with `SHOPWARE_DEPLOYMENT_FORCE_REINSTALL=1`, and
3. updating a Shopware 6.5 project to a newer version.

Treat these flows as compatibility contracts. Changes to command execution, database setup, plugin lifecycle, update ordering, Composer operations, or retry behavior should be checked against all relevant scenarios.

Integration CI currently runs its Shopware scenarios on PHP 8.3 with MySQL and verifies that the storefront is reachable and that a plugin installation survives the deployment flow.

## Telemetry and privacy

The helper includes anonymous telemetry. `DO_NOT_TRACK=1` disables it, and CI sets that variable to avoid emitting telemetry during integration tests.

When touching telemetry code:

* Do not add passwords, secrets, personal data, URLs containing credentials, raw environment dumps, or other sensitive values to telemetry.
* Keep `TELEMETRY.md` in sync with any event or payload changes.
* Preserve the `DO_NOT_TRACK=1` opt-out behavior.
* Tests must not send telemetry to external services.

## Dependencies

* Reuse existing Composer dependencies where reasonable.
* Do not add a runtime dependency for functionality that can be implemented clearly with PHP or an already-installed Symfony component.
* If a dependency change is required, explain why it belongs in `require` versus `require-dev`.
* Preserve compatibility with both supported Doctrine DBAL major lines and Symfony 6/7 constraints unless the task explicitly changes support policy.

## Documentation

Update documentation when behavior visible to users changes, especially:

* CLI arguments/options or commands,
* configuration behavior,
* environment variables,
* installation/update behavior,
* telemetry events or opt-out behavior.

Keep README changes concise; detailed deployment-helper usage documentation may also live in Shopware's external developer documentation.

## Agent guardrails

* Read the relevant source and neighboring tests before editing.
* Do not commit generated files from `var/`, coverage output, vendor dependencies, or local environment files.
* Do not alter CI, supported PHP versions, dependency constraints, telemetry policy, or public CLI behavior unless required by the task.
* Do not silently change installation/update ordering: deployment steps can have stateful side effects and retry implications.
* Never introduce destructive filesystem or database behavior without explicit safeguards and test coverage.
* Preserve backwards compatibility for existing configuration and environment variables unless a breaking change is explicitly requested.
* Do not claim a change is validated if the corresponding tests/checks were not run; state what was and was not executed.

## Pull-request expectations

Keep pull requests focused. A good change should include:

* the smallest practical implementation,
* tests covering new or corrected behavior,
* documentation when user-facing behavior changes,
* successful `composer test`, `composer phpstan`, and `composer cs-dry` checks for normal PHP changes,
* consideration of the installation/update integration scenarios when the changed area can affect them.
