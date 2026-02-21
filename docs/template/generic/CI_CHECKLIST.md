# CI CHECKLIST

Applicability: plugin-only, theme-only, hybrid

Run these acceptance gates before every PR and release.

## 1. Minimum Success Criteria
- [ ] `composer install` completed successfully.
- [ ] `phpcs` passed with zero errors.
- [ ] `phpunit` passed with `0 failures`, `0 errors`, `0 risky`, `0 warnings`.
- [ ] `wp {{CLI_NAMESPACE}} layout import <manifest.json> --dry-run` passed.
- [ ] `wp {{CLI_NAMESPACE}} layout import <manifest.json> --preview` output is expected.
- [ ] Delta queries are within gate (`<= 0`).
- [ ] No Tailwind usage found in scan.

## 2. Evidence Format for PR Approval

```text
### [Verification Evidence]
- PHPUnit: `OK (N tests, N assertions)`
- Dry-Run: `<summary>`
- Preview: `<summary>`
- Performance: `Delta Queries = <value>`
- Tailwind Scan: `<zero findings|findings present>`
```

## 3. Policy
No evidence means fail.
Any failed gate blocks merge to the protected branch.

## 4. Contract Docs Sync Gate

If any shortcode/block parameter contract changes, all checks below are mandatory:

- [ ] `SHORTCODES.md` updated (canonical params + block inspector params).
- [ ] Alias and deprecation tables updated.
- [ ] Validation rules and tag-level alias matrix updated.
- [ ] Contract Change Log entry added.
- [ ] Block editor settings and frontend output parity verified.

Rule:
- If any row is missing, the change is not considered complete.

## 5. Elementor Smoke Gate

If Elementor widget behavior is affected, all checks below are mandatory:

- [ ] A widget smoke checklist was executed for critical widgets.
- [ ] Editor sidebar controls are visible and functional.
- [ ] Frontend output reflects changed widget controls (parity check).
- [ ] PASS/FAIL evidence and issue notes were added to PR verification output.

Rule:
- No Elementor smoke evidence means the task is incomplete for widget-related changes.


