# Shopware CLI + Deployment Helper Integration Guide

Draft guide for integrating Deployment Helper skill with `shopware-cli ai` command system.

## Overview

Deployment Helper is published as a **Skill** discoverable via `shopware-cli ai info/add/remove`. The skill contains guidance (SKILL.md) published to a repository, and the CLI manages discovery, installation, versioning, and lifecycle.

Key constraints:
- No automatic updates (user controls when/if to upgrade)
- Compatibility checks must be owner-maintained (not CLI-maintained)
- All changes recorded by CLI (tag, revision) for reproducibility

## Architecture

```
shopware-cli (command interface)
  ├─ Manifest (YAML, in shopware-cli repo)
  ├─ Compatibility check script (runs on project before install)
  └─ Skill content (SKILL.md + metadata, published separately)

deployment-helper skill repo (this project)
  ├─ SKILL.md (guidance documentation)
  ├─ skill-manifest.json (metadata: name, description, URL, version)
  ├─ scripts/compatibility-check.sh (project validation)
  └─ GitHub Releases (tagged versions)
```

## Checklist: Essential Elements

### Phase 1: Skill Content & Metadata

- [ ] **SKILL.md updated** (✓ done; see caveman edits)
  - Covers fresh install, migration, PaaS scenarios
  - Troubleshooting decision tree
  - Sources of truth prioritization

- [ ] **Public description (≤75 chars)** needed
  - Current: "Expert guidance for Deployment Helper operations"
  - Too long? Shorten to: "Deployment Helper operations and workflows"

- [ ] **Documentation URL** — where users read full SKILL.md
  - GitHub? `https://github.com/shopware/deployment-helper/blob/main/skills/deployment-helper/skill.md`
  - Shopware docs? `https://docs.shopware.com/...`
  - Decision needed

- [ ] **Compatibility requirements** — explicitly declared
  - PHP 8.2+
  - Shopware 6.5+
  - Deployment Helper (any version? or specific range?)
  - Store access (required for extension management)

### Phase 2: Compatibility Check Script

- [ ] **Create `scripts/compatibility-check.sh`**
  - Input: project root directory
  - Output: JSON or exit code indicating pass/fail
  - Check what?
    - [ ] PHP version (8.2+)
    - [ ] Shopware 6.5+ installed or detectable
    - [ ] Composer available
    - [ ] DATABASE_URL readable
    - [ ] .shopware-project.yml exists or can be created
    - [ ] Deployment Helper already installed? (informational)
  - Return structure: `{"compatible": true, "warnings": [...], "errors": [...]}`

- [ ] **Test locally** before publishing
  - Run against sample projects (fresh, existing, PaaS)
  - Verify error messages are helpful

### Phase 3: Manifest Entry (for shopware-cli repo)

- [ ] **Manifest structure** (YAML, to be added to shopware-cli)
  ```yaml
  skills:
    - id: deployment-helper
      type: skill
      name: Shopware Deployment Helper
      description: Deployment Helper operations and workflows
      documentation_url: https://...
      source:
        type: github
        owner: shopware
        repo: deployment-helper
        path: skills/deployment-helper
      compatibility:
        php_min: "8.2"
        shopware_min: "6.5"
        check_script: scripts/compatibility-check.sh
      maintainer:
        github_team: shopware/deployment-helper-maintainers
      lifecycle: stable  # or beta, deprecated
      tags:
        - deployment
        - ci-cd
        - operations
  ```
  - [ ] Verify against shopware-cli JSON Schema
  - [ ] PR to shopware-cli with this entry

### Phase 4: Publishing & Versioning

- [ ] **GitHub Releases workflow** (manual, no CI automation per requirements)
  - Version: semver (e.g., v1.0.0)
  - Release notes: what changed in SKILL.md or scripts
  - Tag must match release version
  - Include: SKILL.md, skill-manifest.json, scripts/

- [ ] **skill-manifest.json** in skill root
  ```json
  {
    "name": "deployment-helper",
    "version": "1.0.0",
    "description": "Deployment Helper operations and workflows",
    "documentation_url": "https://...",
    "compatibility": {
      "php_min": "8.2",
      "shopware_min": "6.5"
    },
    "maintainer": "shopware/deployment-helper-maintainers"
  }
  ```

- [ ] **Tag naming**: `v1.0.0` (matches semantic versioning)
  - CLI resolves tag → fetch commit SHA for reproducibility
  - Record in user's installation: tag + SHA

### Phase 5: Local Testing

- [ ] **Test `ai add deployment-helper`** workflow (once CLI integration ready)
  - Fresh project: should install successfully
  - Existing project: should recognize and migrate-ready
  - PaaS project (Platform.sh): detect and advise
  - Incompatible project: should fail with clear error

- [ ] **Test `ai info deployment-helper`**
  - Shows description, docs URL, version, compatibility

- [ ] **Test `ai remove deployment-helper`**
  - Removes CLI-managed installation only
  - Doesn't remove hand-written files

## Maintaining Skill as DH Evolves

### When Deployment Helper Changes

1. **New feature in DH** (e.g., new hook type, extension-management field)
   - [ ] Update SKILL.md with new operation/configuration
   - [ ] Add example in "Common Operations" if it's user-facing
   - [ ] Update compatibility requirements if needed
   - [ ] Tag as patch or minor release

2. **Breaking change in DH** (e.g., removed config field, new required env var)
   - [ ] Update SKILL.md troubleshooting section
   - [ ] Add migration guidance in "Common Operations"
   - [ ] Bump minimum DH version in compatibility check
   - [ ] Tag as minor or major release

3. **New hosting platform support** (e.g., AWS Lambda, Fly.io)
   - [ ] Add section in "Hosting Platform Operations"
   - [ ] Update compatibility check if platform-specific requirements apply
   - [ ] Tag as minor release

### When Shopware Version Changes

1. **New Shopware 6.x release**
   - [ ] Verify DH compatibility via source code
   - [ ] Update SKILL.md if lifecycle differs (e.g., new schema migration behavior)
   - [ ] Update compatibility requirement if needed

2. **Shopware 7.0** (future)
   - [ ] Verify DH supports it
   - [ ] Update compatibility range (php_min, shopware_min)
   - [ ] Add guidance for 6.5→7.0 migration if applicable
   - [ ] Tag as major release

### When PHP Version Changes

1. **PHP 8.3 release**
   - [ ] No changes unless DH drops 8.2 support
   - [ ] If 8.2 dropped: update php_min in compatibility check and manifest

## Questions for Integration Developer

### Before Starting

1. **Where will the full SKILL.md live?**
   - GitHub raw URL? Shopware docs site? Both with sync?
   - Decision impacts documentation_url in manifest

2. **Who owns the compatibility check?**
   - Can the DH team maintain it, or CLI team?
   - What if DH changes and check breaks?

3. **Release cadence?**
   - How often will SKILL.md be updated (Shopware docs changes, DH updates)?
   - Who triggers releases (DH team, CLI team)?

4. **Error handling in compatibility check?**
   - Should it suggest fixes, or just report incompatibility?
   - JSON output or plaintext?

5. **Multi-environment support?**
   - Should compatibility check detect Platform.sh, detect staging vs production?
   - Should it warn about data-leak risks?

### During Integration

1. **Manifest in shopware-cli:**
   - Does schema match expectations?
   - Is path to compatibility-check.sh correct?
   - Is maintainer GitHub team created/accessible?

2. **Local testing:**
   - Can CLI resolve and install the skill from GitHub?
   - Does `ai info` display correctly?
   - Does `ai remove` clean up properly?

3. **Release workflow:**
   - Should SKILL.md changes auto-publish, or manual?
   - How to version? (DH version, skill version, or both tracked?)

### Ongoing Maintenance

1. **Monitoring:**
   - How will DH team learn if skill becomes outdated?
   - Monthly review? Issue templates? User feedback channel?

2. **Compatibility drift:**
   - If DH releases major version, how quickly must skill update?
   - Grace period for deprecation?

3. **PaaS updates:**
   - If Platform.sh changes integrations, who updates skill guidance?
   - DH team or platform expert?

## Next Steps

1. **Answer Phase 1 questions** (description, documentation URL)
2. **Draft compatibility-check.sh** with test cases
3. **Create skill-manifest.json** (metadata)
4. **Prepare manifest PR** to shopware-cli (with schema validation)
5. **Local test** before publishing first release
6. **Create GitHub Release v1.0.0** with tagged commit

---

**Owner**: Shopware Deployment Helper team  
**Last updated**: 2026-08-19  
**Related**: [ADR 0001: AI Command](https://github.com/shopware/shopware-cli/blob/main/docs/adr/0001-ai-command.md)
