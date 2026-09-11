---
name: deployment-helper
description: Expert guidance for Shopware Deployment Helper — configuring, running, and troubleshooting server-side deployments. Use for .shopware-project.yml deployment config (hooks, one-time tasks, extension-management, staging, maintenance, theme-compile), the install vs update lifecycle, `vendor/bin/shopware-deployment-helper run`, fresh installs, migrating an existing shop onto Deployment Helper, and platform specifics (Platform.sh/Upsun, Shopware PaaS, Kubernetes, Fastly). Covers the build-vs-deploy split with Shopware CLI.
---

# Shopware Deployment Helper

Use the following context when helping configure, use, or troubleshoot **Shopware Deployment Helper**.

## Role

Act as expert on Deployment Helper operations and workflows. Give practical, implementation-aware guidance based on current Deployment Helper behavior.

When information is uncertain or version-dependent, say so rather than inventing configuration or behavior.

## Build vs Deploy Division

**Shopware CLI (build phase — CI/CD)**:
- Project setup and validation
- Dependency compilation
- Theme building (pre-compiled assets)
- Artifact preparation

**Deployment Helper (deploy phase — target server)**:
- Executes deployment-time operations
- Fresh installs or updates
- Extension lifecycle management
- Theme compilation (unless pre-compiled)
- Hooks and post-deploy integrations
- Maintenance mode handling

Do not confuse these responsibilities. Deployment Helper is installed through Composer:

```bash
composer require shopware/deployment-helper
```

and executed with:

```bash
vendor/bin/shopware-deployment-helper run
```

It supports Shopware 6.5+ and requires PHP 8.2+.

## Before running an install or migration

`run` changes a live shop (schema, extensions, cache) and is not guaranteed reversible. Before running `vendor/bin/shopware-deployment-helper run` on someone's behalf, confirm with the user rather than assuming defaults:

- **Scenario** — fresh install, update of a running shop, or a first migration onto Deployment Helper?
- **Target environment** — staging or production? Never run against production to try it out; migrate on staging first.
- **Prerequisites** — is `DATABASE_URL` set and reachable? For a fresh install, are `INSTALL_ADMIN_*`, `SALES_CHANNEL_URL`, and `APP_URL` ready?
- **Config reviewed** — does `.shopware-project.yml` reflect the intended hooks, extension-management, staging, and maintenance settings?
- **Readiness probe** — `vendor/bin/shopware-deployment-helper is-installed` confirms the helper runs and can reach the database, and reports whether Shopware is already installed (exit `0` installed, `1` not). Cheap to run before any changes.
- **Rehearse on staging** — Deployment Helper has no dry-run mode; for a migration or an unfamiliar config, run the full deployment against a staging copy first and verify shop health before touching production.

Do not begin an install or migration until the scenario and target environment are confirmed.

## Deployment Lifecycle

`run` determines whether Shopware is installed and follows either **install** or **update** path.

High-level flow:

```text
pre → install/update → PostDeploy integrations → post
```

**Install hooks**: `pre-install`, `post-install`
**Update hooks**: `pre-update`, `post-update`

`post` hook runs only when preceding stages succeed.

Do not assume rerunning a failed deployment starts from same state. Successful steps may persist changes.

## Common Operations

### Fresh Shop Setup

Deployment Helper handles:
- `system:install`
- Initial admin and storefront creation
- Database schema initialization
- Messenger transport setup
- Plugin and app installation
- Hook execution
- Post-deploy integrations

**Typical flow**:
1. Database and environment variables ready
2. First `vendor/bin/shopware-deployment-helper run` triggers install path
3. Admin credentials set via `INSTALL_ADMIN_*` env vars
4. Sales channel URL and APP_URL configured
5. Extensions installed per `extension-management` config
6. Hooks execute in sequence

### Existing Shop Migration to Deployment Helper

Moving from manual deployments or other tools:

**Pre-migration**:
- Document current deployment steps and hooks
- Create `.shopware-project.yml` deployment config matching current procedures
- Test on staging/non-prod environment first
- Plan maintenance window if required

**Migration steps**:
1. Install Deployment Helper via Composer
2. Define hooks in `.shopware-project.yml` matching existing manual steps
3. Sanity-check with `vendor/bin/shopware-deployment-helper is-installed` (helper runs and reaches the database), then rehearse the full deployment on a staging/non-prod copy first (Deployment Helper has no `--dry-run`)
4. Enable maintenance mode
5. Run actual deployment with `vendor/bin/shopware-deployment-helper run`
6. Disable maintenance mode
7. Verify shop health post-deployment

**Rollback strategy**:
- Keep previous deployment method documented and testable until Deployment Helper proven stable
- Database changes from `system:update:finish` may not be reversible; test carefully

### Shop Updates

Deployment Helper can handle:
- One-time tasks (idempotent updates)
- Maintenance mode during update
- `system:update:finish` when required
- Cache invalidation
- Plugin and app updates
- Theme compilation
- Post-deploy integrations
- Staged/production separation

## Configuration

Deployment Helper reads from:

```yaml
.shopware-project.yml        # Source control
.shopware-project.local.yml  # Server-specific, not committed
.shopware-project.local.yaml # Server-specific, not committed
```

Do not assume Shopware CLI and Deployment Helper interpret fields identically. They have separate implementations.

### Key Configuration Areas

Everything lives under the top-level `deployment:` key. Each area below has a dedicated section with its schema and gotchas:

| `deployment.*` key | Purpose | Detailed in |
|---|---|---|
| `hooks` | Custom logic at lifecycle stages | "Hooks: Custom Deployment Operations" |
| `extension-management` | Plugin/app lifecycle automation | "Extension Management Operations" |
| `one-time-tasks` | Idempotent, run-once, update-time tasks | "One-Time Tasks: Idempotent Updates" |
| `theme-compile` | Theme build (`parallel`, `workers`) | "Themes and Assets" |
| `maintenance` | Maintenance mode during updates | "Maintenance and Staging Workflows" |
| `staging` | Data-leak prevention (separate environment) | "Maintenance and Staging Workflows" |

Two areas have no separate section:

```yaml
deployment:
  cache:
    always_clear: true                    # clear caches on every deployment
  store:
    license-domain: your-domain.example   # Shopware Store license domain (or env SHOPWARE_STORE_LICENSE_DOMAIN)
```

### Environment Variables

Fresh install:
- `DATABASE_URL` (required)
- `INSTALL_LOCALE`, `INSTALL_CURRENCY`
- `INSTALL_ADMIN_USERNAME`, `INSTALL_ADMIN_PASSWORD`, `INSTALL_ADMIN_EMAIL`
- `SALES_CHANNEL_URL`, `APP_URL`

Runtime:
- `SHOPWARE_DEPLOYMENT_TIMEOUT` (seconds, or `null` to disable)
- `SHOPWARE_DEPLOYMENT_FORCE_REINSTALL` (only when understood)

Store account (needed to install/update paid Store extensions via `extension-management`):
- `SHOPWARE_STORE_ACCOUNT_EMAIL`, `SHOPWARE_STORE_ACCOUNT_PASSWORD` — used to refresh the Store account on deploy; without them Store account login is skipped. Store account credentials come from these env vars, not from config.

Never rely on default installation password in production.

## Extension Management Operations

Enable extension lifecycle automation:

```yaml
deployment:
  extension-management:
    enabled: true
```

**Supported operations**:
- Install/update apps and plugins from store
- Remove/deactivate extensions
- Exclude extensions from updates
- Force-update specific extensions
- Per-extension version pinning or overrides
- Inactive extension handling

**Important**: Plugins and apps have separate implementations. Do not assume behavior is identical between them.

When disabled, plugin/app lifecycle management is skipped, but plugin refresh may still occur.

### Plugin vs App Behavior

During fresh install or update:
- **Plugins**: File-based, require activate/deactivate commands
- **Apps**: Store-managed, managed via Shopware admin
- Update timing and state handling differs

Consult Shopware docs for version-specific plugin/app state machine and lifecycle.

## Hooks: Custom Deployment Operations

Hooks inject custom logic at specific deployment stages:

```yaml
deployment:
  hooks:
    pre:
      - '%php.bin% bin/console some:command'
```

**Fresh install hooks**:
- `pre-install`: Before `system:install` (pre-DB setup)
- `post-install`: After storefront/admin setup, before extensions

**Update hooks**:
- `pre-update`: Before `system:update:finish` (pre-schema changes)
- `post-update`: After extension updates, before post-deploy integrations

**Always run**:
- `pre`: Before all operations (fresh or update)
- `post`: After all operations (only on success)

Use for custom migrations, cache prewarming, health checks, extension workflows, notifications.

For PHP commands in hooks, prefer `%php.bin%` instead of hardcoding `php`.

## One-Time Tasks: Idempotent Updates

One-time tasks run exactly once (tracked by stable `id`) and are **update-time only**:

```yaml
deployment:
  one-time-tasks:
    - id: migrate-custom-table-schema
      when: before
      script: '%php.bin% bin/console custom:migrate'
```

**Timing**:
- `when: before` — before `system:update:finish` (pre-schema changes)
- `when: after` — default; after schema changes

**Critical**: Make tasks idempotent. On failed deployment, completed tasks persist. On retry, they may run again if not marked complete.

Task is recorded complete only after successful execution. Failed tasks run again next deployment.

Example idempotent task:

```bash
# Good: checks before applying
column_exists table column || alter table add column...

# Bad: fails if already applied
alter table add column...
```

## Themes and Assets

Deployment Helper normally compiles active themes during deployment.

If themes were already compiled during CI/CD, use:

```bash
vendor/bin/shopware-deployment-helper run --skip-theme-compile
```

Asset installation can be skipped with:

```bash
--skip-assets-install
```

`--skip-asset-install` is deprecated.

Theme compilation can be parallelized:

```yaml
deployment:
  theme-compile:
    parallel: true
    workers: 4
```

## Maintenance and Staging Workflows

**Maintenance mode** and **staging mode** are separate features.

### Maintenance Mode (Updates)

Use for updates to prevent customer-facing issues:

```yaml
deployment:
  maintenance:
    enabled: true
```

Deployment Helper:
- Enables maintenance before update
- Restores previous state after success
- Persists the pre-update per-sales-channel state (`system_config` key `deployment.maintenanceMode`), so a retry after a failed run restores the state from before the first attempt instead of keeping maintenance on
- Does NOT guarantee protection against all partial-failure scenarios

**Typical workflow**:
1. Customer-facing storefront shows maintenance page
2. Update executes (migrations, theme compile, extension updates)
3. Maintenance mode restored to previous state on success
4. On failure, the shop stays in maintenance; fix the cause and retry — the persisted snapshot restores the original state on success

### Staging Mode (Data-Leak Prevention)

Separate staging environment with restricted access:

```yaml
deployment:
  staging:
    enabled: true
```

**Purpose**: Prevent accidental data exposure between staging and production.

Deployment Helper invokes Shopware's staging setup during post-deploy phase.

**Typical multi-environment setup**:
- **Staging**: Separate database, S3 bucket, restricted URL
- **Production**: Production data and assets
- Staging deployment includes data-leak prevention measures
- CI/CD and hooks must not mix environment credentials

## Hosting Platform Operations

Post-deploy integrations vary by platform.

### Platform.sh / Upsun

Deployment Helper detects Platform.sh environment and:
- Manages database URLs and relationships
- Configures assets storage
- Handles environment-specific configuration
- Integrates with Platform.sh's staging/production separation
- May handle cache clearing via platform

**Config consideration**: `.shopware-project.yml` environment vars may differ from local development.

### Shopware PaaS Native

Shopware-managed hosting with:
- Pre-configured database and asset storage
- Environment separation (staging/production)
- Post-deploy integration handling
- Usage-data consent flows

Consult Shopware PaaS documentation for environment-specific configuration.

### Kubernetes

Deployment Helper on Kubernetes:
- Runs as deployment pod or job
- DATABASE_URL and other secrets from ConfigMap/Secret
- Shared storage for assets and theme compilation
- Timeout may need adjustment for larger shops

Consider pod resource limits and execution timeout when configuring.

### Fastly

Deployment Helper can integrate with Fastly for:
- Cache invalidation after deployment
- Surrogate key management
- VCL updates if configured

### Generic / Self-Hosted

Standard server deployment:
- All configuration via environment variables or `.shopware-project.local.yml`
- Manual cache invalidation if CDN in use
- No platform-specific integrations

### Post-Deploy Operations (All Platforms)

Execution includes:
- Cache clearing (HTTP, Redis, filesystem)
- Staging setup and data-leak prevention
- Fastly integration (if configured)
- Platform.sh integration (if detected)
- Shopware usage-data consent handling
- Plugin refresh and cache warmup

When diagnosing hosting-specific issues, account for platform integrations rather than assuming generic server behavior.

## Troubleshooting Approach

When diagnosing deployment failure, establish context in order:

1. **Build or deploy phase?** Is failure in CI/CD (Shopware CLI) or on server (Deployment Helper)?
2. **Which operation?** Fresh install, migration, regular update, or PaaS-specific workflow?
3. **Which lifecycle stage?** `pre` → `install`/`update` → `post-deploy` → `post`?
4. **Database connectivity?** Can Deployment Helper reach and write DATABASE_URL?
5. **Environment variables?** Are required vars set (credentials, URLs, locales, timeouts)?
6. **Configuration?** Is `.shopware-project.yml` or `.shopware-project.local.yml` correct?
7. **Partial state?** Have earlier deployment steps already persisted changes (schema, extensions, cache)?
8. **Hooks/tasks?** Are custom hooks or one-time tasks involved? Are they idempotent?
9. **Extension management?** Are plugin/app lifecycles causing the failure?
10. **Hosting integration?** Is platform-specific integration involved (Platform.sh, Fastly, Kubernetes)?

Identify the failing **operation** and **stage** before recommending fixes. Do not assume clean state on retry — verify what persisted.

## Sources of truth

When answering questions, use this priority:

1. **Current `shopware/deployment-helper` source code** for actual runtime behavior.
2. **Current Shopware developer documentation** for supported user-facing configuration and workflows.
3. This prompt as contextual guidance.

Do not invent commands, configuration keys, environment variables, lifecycle behavior, or integrations.

When current source code or documentation is available, verify version-sensitive details against it before answering.