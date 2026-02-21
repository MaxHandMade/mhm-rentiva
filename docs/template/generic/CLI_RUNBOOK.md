# CLI RUNBOOK

Applicability: plugin-only, theme-only, hybrid

All layout operations are managed through CLI.

## 1. Canonical Commands

### Dry-Run Validation
```bash
wp {{CLI_NAMESPACE}} layout import path/to/manifest.json --dry-run
```

### Preview Diff
```bash
wp {{CLI_NAMESPACE}} layout import path/to/manifest.json --preview
```

### Atomic Import
```bash
wp {{CLI_NAMESPACE}} layout import path/to/manifest.json --create
```

## 2. Output Expectations

| Condition | Expected Output |
| :--- | :--- |
| Success | `Status: CREATED/UPDATED`, `ID`, `Title`, `Message: OK` |
| Validation Error | `Error: Missing required field ...` |
| Rollback | `Import failed ... Rollback executed. Database state preserved.` |

## 3. Command Evidence Standard (Mandatory)

```text
### [CLI Verification Evidence]
- Command: `wp {{CLI_NAMESPACE}} layout import <manifest.json> --dry-run`
  Exit Code: `<0|non-zero>`
  Summary: `<short output summary>`
- Command: `wp {{CLI_NAMESPACE}} layout import <manifest.json> --preview`
  Exit Code: `<0|non-zero>`
  Summary: `<short output summary>`
- Command: `wp {{CLI_NAMESPACE}} layout import <manifest.json> --create`
  Exit Code: `<0|non-zero>`
  Summary: `<short output summary>`
```

Rule:
- No create/import approval without dry-run and preview evidence.

## 4. Side-Effect-Free Guarantees
- `--dry-run`: no writes to post content/meta.
- `--preview`: in-memory diff only; no state hash update.
- Atomic mode: on exception, snapshot-based rollback restores previous state.


