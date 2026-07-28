# 2. Opt-in OpenSearch indexing after installation

- Status: Proposed
- Date: 2026-07-28

## Context

A fresh Shopware installation can be configured to use OpenSearch through
`OPENSEARCH_URL` for storefront search and `ADMIN_OPENSEARCH_URL` for
administration search. Shopware requires indexes with its own mappings and index
settings before those integrations can serve requests.

Deployment Helper previously left index creation to deployment-specific hooks. In
SaaS, those hooks run `es:index` and `es:admin:index` after installation. Without
equivalent handling, a fresh installation with OpenSearch configured can fail with
missing-index errors.

This must not turn every deployment that happens to provide an OpenSearch URL into
a behavior change. It also must not reindex on ordinary updates: complete
reindexing is expensive for large catalogs. Shopware's update process owns mapping
updates and creates fresh indexes automatically when a mapping change is
incompatible.

`SHOPWARE_ES_THROW_EXCEPTION` controls Shopware's error and fallback behavior. Its
value is an operational choice for the person running the deployment, not a
Deployment Helper decision.

## Decision

Deployment Helper will offer OpenSearch indexing as an explicit opt-in through a
dedicated deployment configuration option. The option is disabled by default.

When the option is enabled, Deployment Helper will run Shopware's index commands
as part of a fresh installation after Shopware has completed its installation and
setup steps:

- Run `es:index` when `OPENSEARCH_URL` is set.
- Run `es:admin:index` when `ADMIN_OPENSEARCH_URL` is set.
- Run both commands when both variables are set.
- Run neither command when its corresponding URL is absent.

Deployment Helper will not proactively run either index command during ordinary
updates. Mapping updates and any reindexing required by incompatible mappings stay
within Shopware's update process.

The index commands are deployment steps. A command failure fails the deployment
and is visible to its operator. Deployment Helper does not read, set, default, or
modify `SHOPWARE_ES_THROW_EXCEPTION`; the deployment operator chooses its value
and the resulting Shopware fallback policy.

The implementation must preserve safe retries by relying on Shopware's index
commands rather than creating or modifying OpenSearch indexes directly.

## Consequences

### Positive

- Operators can deliberately enable OpenSearch index creation for fresh installs
  without maintaining post-install hooks for it.
- Indexes are created by Shopware, which supplies the required mappings and
  settings.
- Existing deployments remain unchanged unless they opt in.
- Ordinary updates avoid an expensive full reindex.
- Indexing failures are actionable deployment failures rather than hidden behavior.

### Negative / trade-offs

- Operators must learn and set an additional Deployment Helper configuration
  option.
- An unavailable or misconfigured OpenSearch service can fail a fresh installation
  when the feature is enabled.
- The error-versus-fallback policy remains outside Deployment Helper and may differ
  between operators because they control `SHOPWARE_ES_THROW_EXCEPTION`.
- This decision does not define Shopware's mapping-update behavior or
  SaaS/PaaS-specific deployment procedures.
