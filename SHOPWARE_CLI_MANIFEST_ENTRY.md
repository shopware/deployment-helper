# Shopware CLI Manifest Entry Template

This is a **template** for the manifest entry that will be added to the `shopware-cli` repository.

## Location

File: `https://github.com/shopware/shopware-cli/blob/main/docs/adr/0001-ai-command.md` (or wherever the manifest lives — TBD)

Format: YAML (check shopware-cli for exact location and schema)

## Entry Template

```yaml
- id: deployment-helper
  type: skill
  name: Shopware Deployment Helper
  description: Deployment Helper operations and workflows
  documentation_url: https://github.com/shopware/deployment-helper/blob/main/skills/deployment-helper/skill.md
  source:
    type: github
    owner: shopware
    repo: deployment-helper
    path: skills/deployment-helper
    # CLI will fetch from releases, not main branch
  compatibility_check:
    script: scripts/compatibility-check.sh
    owner_maintained: true
  compatibility:
    php_min: "8.2"
    shopware_min: "6.5"
    deployment_helper: "*"
  maintainer:
    github_team: shopware/deployment-helper-maintainers
    contact: l.apple@shopware.com
  lifecycle: beta
  tags:
    - deployment
    - ci-cd
    - operations
    - server
```

## Fields Explained

### id
Stable identifier. Used in commands like `ai add deployment-helper`.

### type
Always `skill` for this use case.

### name
Display name (not constrained to 75 chars — that's the description).

### description
**Max 75 characters.** Shown in `ai list` and `ai info`.

Current: `"Deployment Helper operations and workflows"` (44 chars ✓)

### documentation_url
URL to full SKILL.md. Users go here for detailed guidance.

Options:
- GitHub blob URL (updates with repo)
- Shopware docs site (stable, requires sync)
- Both (documented in INTEGRATION_GUIDE.md maintainer section)

### source.type
Always `github` for this project.

### source.owner, source.repo, source.path
- `owner`: shopware
- `repo`: deployment-helper
- `path`: skills/deployment-helper (where SKILL.md and skill-manifest.json live)

CLI will:
1. Resolve latest GitHub release of `shopware/deployment-helper`
2. Fetch files from `skills/deployment-helper` directory in that release
3. Record tag + commit SHA for reproducibility

### compatibility_check.script
Path within released skill: `scripts/compatibility-check.sh`

CLI runs this **before** installation to validate project.

### compatibility_check.owner_maintained
`true` — means DH team maintains this script and keeps it compatible.

If `false`, would mean CLI team maintains it (not the case here).

### compatibility fields
Minimum requirements. Used by CLI for early filtering and by maintainers for decisions.

- `php_min`: "8.2" (minimum PHP version)
- `shopware_min`: "6.5" (minimum Shopware version)
- `deployment_helper`: "*" (any DH version is compatible; update if breaking changes occur)

### maintainer.github_team
GitHub team that owns maintenance: `shopware/deployment-helper-maintainers`

This team:
- Reviews and approves skill updates
- Maintains compatibility check script
- Responds to issues/compatibility questions

**Action required**: Create this team in Shopware GitHub org (if not already exists).

### maintainer.contact
Fallback contact email (informational).

### lifecycle
- `beta`: Feature-complete but not widely tested
- `stable`: Proven production-ready
- `deprecated`: Scheduled for removal

Start as `beta`, move to `stable` after real-world usage.

### tags
Help users discover the skill. Examples: `deployment`, `ci-cd`, `operations`, `server`.

## PR Process

1. **Create the entry** (use template above)
2. **Validate against schema** (shopware-cli CI will check)
3. **Submit PR** to shopware-cli with this manifest entry
4. **CI validates**:
   - JSON Schema compliance
   - GitHub URLs resolve
   - Team exists
5. **Merge** once approved

## Testing Before PR

Before submitting to shopware-cli, ensure:

1. **skill-manifest.json exists** in `skills/deployment-helper/skill-manifest.json`
2. **SKILL.md exists** at `skills/deployment-helper/skill.md`
3. **scripts/compatibility-check.sh exists** and is executable
4. **GitHub release exists** with matching tag (e.g., v1.0.0)
5. **Release contains all three files** in the correct structure
6. **documentation_url is valid** and points to live content

## Post-Merge Actions

After manifest PR is merged to shopware-cli:

1. **Wait for CLI release** (includes updated manifest)
2. **Test locally** with released CLI:
   ```bash
   shopware-cli ai info deployment-helper
   shopware-cli ai add deployment-helper
   ```
3. **Verify** installation works on test projects
4. **Update INTEGRATION_GUIDE.md** with any learnings

## Updating Later

If you need to change the manifest entry (e.g., update `php_min`, change maintainer team):

1. Update entry in shopware-cli repo
2. Submit PR with justification
3. Wait for CLI release with updated manifest
4. Update INTEGRATION_GUIDE.md "Maintaining Skill" section with notes

---

**Related**: [INTEGRATION_GUIDE.md](INTEGRATION_GUIDE.md)  
**Manifest Validation**: Check shopware-cli repository for JSON Schema definition
