---
title: Deployment Helper Architecture
date: 2026-07-22
area: architecture
tags: [boundaries, orchestration, pipeline, patterns]
---

# Deployment Helper Architecture

## Why Deployment Helper (DH) exists

DH unifies post-upload deploy steps: install fresh shops and upgrade live ones, independent of Shopware version (6.5+). One run handles both paths. Shopware CLI authors and validates `deployment:` config; DH executes it on the server.

## How it's invoked

Server-side Deployer recipe calls `vendor/bin/shopware-deployment-helper run`. CI may invoke that recipe directly or through another action/wrapper. Config-driven execution: CLI prepares config (`.shopware-project.yml`), DH executes it.

## Under the hood

[Symfony Console app](src/Application.php) with its own dependency injection container. Does not boot Shopware; shells out to `bin/console` via [ProcessHelper](src/Helper/ProcessHelper.php#L15) with timeouts.

Entry: [RunCommand](src/Command/RunCommand.php) → `ShopwareState::isInstalled()` → [InstallationManager](src/Services/InstallationManager.php) or [UpgradeManager](src/Services/UpgradeManager.php).

Key services:

- `RunCommand`: entry, branches by installed state
- `InstallationManager` / `UpgradeManager`: orchestrate Shopware console commands in sequence
- `PluginManagementPlanner`: purely plans typed `ConsoleCommand` objects; `PluginHelper` executes via `ProcessHelper`
- `PostDeploy` subscribers: `ClearAlwaysCacheSubscriber`, `FastlyServiceUpdater`, `PlatformSHSubscriber`, `StagingSetupSubscriber`, `UsageDataConsentSubscriber`

## Core workflow

`pre` → install/update → PostDeploy → `post`.

`post` runs only if every preceding stage succeeds. 

A `pre` hook or PostDeploy subscriber can also abort.

**Install (fresh shop):**

1. `pre-install` hook
2. system:install → create admin user (INSTALL_ADMIN_PASSWORD optional; defaults to "shopware") → messenger:setup-transports → storefront setup (if shopware/storefront is installed)
3. plugin:refresh → install/update/deactivate/remove plugins → optional license refresh → install/update/deactivate/remove apps
4. `post-install` hook

**Update (live shop):**

1. `pre-update` hook
2. before one-time tasks
3. Maintenance enabled: snapshots per-storefront state, enables maintenance, clears cache (can fail and leave on—no finally)
4. messenger:setup-transports → system:update:finish (if version changed) → optional sales-channel creation → plugin:refresh → theme:refresh (if storefront installed) → scheduled-task:register → messenger:stop-workers → plugins → optional license refresh → apps
5. theme:compile (parallel if configured; serial fallback; unless `--skip-theme-compile`)
6. after one-time tasks
7. `post-update` hook
8. Maintenance disabled: restores per-storefront prior states, clears cache
9. PostDeploy dispatches conditional Fastly, Platform.sh, staging, cache-clearing, and Shopware-consent subscribers. No explicit listener priorities. If one throws, dispatch stops; later subscribers and `post` hook may not run.
10. `post` hook, only if every earlier stage succeeds.

## Config that matters

- **`deployment.extension-management.enabled`:** enables or disables DH-managed plugin and app lifecycle operations. When `false`, DH skips plugin/app lifecycle actions; plugin:refresh still runs. When `true`, per-extension overrides influence lifecycle behavior, although exact semantics differ between plugins and apps.
- **Hooks:** `pre`, `post`, `pre-install`, `post-install`, `pre-update`, `post-update` - inject shell commands at deploy moments. Untyped escape hatch.
- **One-time tasks:** update-only, stateful run-once scripts keyed by id. Marked done only after success; failures re-run on next deploy. `before`/`after` phasing. Not enforced idempotent - re-running is caller's responsibility.
- **Local config:** `.shopware-project.local.yml` (or `.local.yaml`) layers per-environment overrides. Env vars pass short-lived inputs (locale, admin user, URLs, credentials).
- **Flags:** `--skip-theme-compile`, `--skip-assets-install`. Flag affects plugins (receives `--skip-asset-build`); apps do not receive skip-assets option.
- **Maintenance mode:** update-only, optional. Snapshots per-storefront prior state; enables maintenance and clears cache. On exit, restores prior states and clears cache. Failure after enable can leave modified state (no finally).
- **Staging:** `deployment.staging.enabled` runs `system:setup:staging --no-interaction --force` via PostDeploy subscriber (separate from maintenance).
- **Theme compilation:** `deployment.theme-compile.parallel` enables per-sales-channel parallel compile when supported; `workers` limits concurrency. Falls back to serial `theme:compile --active-only` where unsupported, inapplicable, or not beneficial.

Config parsed by `ConfigFactory` (hand-written interpreter). Ad-hoc parsing/type checks; no shared schema; unknown keys ignored (typos pass silently).

## Operational gotchas

- **DATABASE_URL:** retried at most 10 times, with retries happening one second apart. Missing URL is included in retry loop. Unknown database accepted for install.
- **Admin password:** `INSTALL_ADMIN_PASSWORD` env var optional (defaults to "shopware"); install-only.
- **Maintenance mode:** optional, update-only. No safety guard on prod. Can fail and leave enabled. Fresh installs do not use it.
- **Telemetry:** direct, opt-out via `DO_NOT_TRACK` env. `UsageDataConsentSubscriber` separately configures Shopware's usage-data consent (different from DH telemetry).
- **Fastly integration:** deploys configured VCL snippets and activates service version; does not purge cache or delete removed snippets. Requires `config/fastly` directory, `FASTLY_API_TOKEN`, and `FASTLY_SERVICE_ID` env vars.
- **Asset flag:** `--skip-assets-install` affects `system:install`, `system:update:finish`, and plugin operations (receive `--skip-asset-build`). App operations receive no equivalent option.
- **Version stored immediately:** after `system:update:finish` succeeds, DH stores the new Shopware version. Later deployment failures won't repeat `system:update:finish` on next run.

## Load-bearing invariants

These rules, if broken, turn an addition into a regression.

- **Config-driven execution:** CLI writes `.shopware-project.yml`; DH reads and executes. No call-backs to CLI.
- **PostDeploy integrations stay at edges:** Fastly, Platform.sh, staging, always-clear-cache handling, and Shopware consent are implemented as PostDeploy subscribers. Telemetry and store-license refresh are documented exceptions used directly by command/managers.
- **Rerun safety where provided:** One-time-tasks marked done only after success (failed tasks retry). Fastly performs change detection. Maintenance restores per-storefront prior states. Hooks and partial deployments remain caller's responsibility.

## Current limits

- Neither install nor update flow separates steps for inspection. No generic skip/preview/resume; only success/failure + order.
- Config schema defined once in CLI, re-parsed by hand in DH. Representations drift independently. DH parses fields (maintenance, theme-compile, one-time-task when) not in CLI schema. Deprecated key spellings differ (CLI: `force_updates`; DH: `forceUpdates`; both recognize `force-update`).
- Plugins use typed planner; apps use inline argument arrays. Analogous lifecycle phases, separate implementations with behavioral differences.
