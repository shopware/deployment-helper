# Skill scripts

## `compatibility-check.sh`

Owner-maintained compatibility check for the `deployment-helper` skill. It is
**not** part of the skill's runtime guidance — Shopware CLI runs it against a
project **before** installing the skill (`ai add deployment-helper`), per
[shopware/shopware-cli#1337](https://github.com/shopware/shopware-cli/issues/1337).

- **Usage:** `compatibility-check.sh [project-root]` (defaults to `.`)
- **Output (stdout):** JSON `{"compatible":bool,"errors":[],"warnings":[],"info":[]}`
- **Exit code:** `0` compatible, `1` incompatible
- **Read-only:** never modifies the project.

### What it checks

| Requirement | Blocking? |
|---|---|
| PHP ≥ 8.2 | yes |
| `shopware/core` (or `shopware/platform`) ≥ 6.5 — from `composer.lock`, else the `composer.json` constraint | yes |
| Composer available, `DATABASE_URL` set, Deployment Helper already installed | no (informational) |

Config-file discovery (`.shopware-project.yml` vs the `.config/` location) is left
to shopware-cli / Deployment Helper — it changes over time
([shopware-cli#1387](https://github.com/shopware/shopware-cli/issues/1387)), so the
check does not duplicate that lookup.

### Policy

A requirement that cannot be positively confirmed is treated as **incompatible**
("unknown counts as incompatible"): if no `shopware/core >= 6.5` can be resolved,
the check fails rather than warning.

### Maintenance

Keep the minimum versions here in sync with what Deployment Helper actually
supports. When Deployment Helper raises its PHP or Shopware floor, update the
thresholds in `compatibility-check.sh`.
