# Generic Template Kit

This folder contains project-agnostic templates for bootstrapping and governing a Layout Engine based WordPress project.

## Applicability Profiles
- `plugin-only`: Use plugin-centric setup and delivery.
- `theme-only`: Use theme/FSE-centric setup and delivery.
- `hybrid`: Use both plugin and theme paths with shared governance.

## File-to-Profile Matrix

| File | plugin-only | theme-only | hybrid | Notes |
| :--- | :---: | :---: | :---: | :--- |
| `BOOTSTRAP_NEW_PROJECT.md` | Yes | Yes | Yes | Base environment and first verification |
| `CI_CHECKLIST.md` | Yes | Yes | Yes | Common acceptance gates |
| `CLI_RUNBOOK.md` | Yes | Yes | Yes | Canonical command and evidence flow |
| `FOLDER_STRUCTURE.md` | Yes | Yes | Yes | Core vs preset separation |
| `GOVERNANCE_GATES.md` | Yes | Yes | Yes | Hard gates and fail policy |
| `LAYOUT_ENGINE_PRESET_GUIDE.md` | Optional | Yes | Yes | Most useful for UI/layout adaptation |
| `walkthrough.md` | Yes | Yes | Yes | Release/change evidence narrative |

## Files
- `BOOTSTRAP_NEW_PROJECT.md`
- `CI_CHECKLIST.md`
- `CLI_RUNBOOK.md`
- `FOLDER_STRUCTURE.md`
- `GOVERNANCE_GATES.md`
- `LAYOUT_ENGINE_PRESET_GUIDE.md`
- `walkthrough.md`

## Placeholder Variables
These templates use placeholders that must be replaced per project:
- `{{PLUGIN_SLUG}}` -> plugin directory slug (example: `vehicle-layout-kit`)
- `{{CLI_NAMESPACE}}` -> WP-CLI namespace (example: `vehicle-kit`)

Optional placeholders you may add per project:
- `{{PROJECT_NAME}}`
- `{{TEXT_DOMAIN}}`
- `{{MIN_PHP_VERSION}}`
- `{{MIN_WP_VERSION}}`

## Quick Start (Apply Preset)
1. Copy all files in this folder to your project documentation folder.
2. Replace placeholders in all copied files.
3. Review command examples and update paths (`manifest`, `fixtures`, report paths).
4. Validate consistency:
   - CLI namespace is identical across all files.
   - Evidence format is identical across all files.
   - Governance gates and CI checklist match project policy.
5. Run bootstrap commands and capture evidence before first PR.

## Replacement Commands (Examples)
PowerShell:
```powershell
Get-ChildItem . -Recurse -File *.md |
  ForEach-Object {
    (Get-Content $_.FullName -Raw)
      .Replace('{{PLUGIN_SLUG}}', 'your-plugin-slug')
      .Replace('{{CLI_NAMESPACE}}', 'your-cli-namespace') |
      Set-Content $_.FullName -Encoding UTF8
  }
```

Bash:
```bash
find . -type f -name "*.md" -print0 |
  xargs -0 sed -i "s/{{PLUGIN_SLUG}}/your-plugin-slug/g; s/{{CLI_NAMESPACE}}/your-cli-namespace/g"
```

## Recommended Preset File
Create a small preset file in your project root, for example `docs/project-preset.json`:

```json
{
  "project_name": "Your Project",
  "plugin_slug": "your-plugin-slug",
  "cli_namespace": "your-cli-namespace",
  "text_domain": "your-text-domain",
  "min_php_version": "8.1",
  "min_wp_version": "6.0"
}
```

## Governance Rule
Do not change gate intent when adapting templates.
You may adjust command paths and thresholds, but keep evidence requirements and fail policy intact.

## Validation Checklist (1-Minute)
- [ ] No unresolved placeholders remain (`{{...}}`).
- [ ] `{{CLI_NAMESPACE}}` is consistent across all template files.
- [ ] CLI examples run with the target project namespace.
- [ ] CI checklist gates match governance gates.
- [ ] Evidence sections exist in bootstrap, CLI runbook, and walkthrough.
- [ ] Fail policy text is present (`No evidence means fail`).
- [ ] File encoding is UTF-8.
