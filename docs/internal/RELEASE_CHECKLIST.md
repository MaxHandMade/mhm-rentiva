# v4.10.0 Release Checklist

## Release Summary
- **Version**: 4.10.0
- **Tag**: `v4.10.0`
- **Branch**: `release/4.10.0`
- **Date**: 2026-02-18
- **Theme**: Block ↔ Shortcode Parity Stabilization

---

## Preconditions

| Checkpoint | Status |
| :--- | :--- |
| Parity Matrix Final Audit PASS | ✅ |
| 19/19 Light Audit PASS | ✅ |
| PR-01 → PR-05 merged | ✅ |
| Working tree clean | ✅ |
| No open regression issues | ✅ |

## M4 Hard Gates (Final Run)

| Gate | Result |
| :--- | :--- |
| PHPCS | ✅ 0 errors |
| Plugin Check | ✅ PASS (last verified pre-bump) |
| PHPUnit | ✅ 216 tests, 0 failures |

## Version Bump

| File | Before | After |
| :--- | :--- | :--- |
| `mhm-rentiva.php` (header) | 4.9.9 | 4.10.0 |
| `mhm-rentiva.php` (constant) | 4.9.9 | 4.10.0 |
| `readme.txt` (Stable tag) | 4.9.9 | 4.10.0 |
| `changelog.json` | — | v4.10.0 entry added |
| `changelog-tr.json` | — | v4.10.0 entry added |

## Tag

```
git tag -a v4.10.0 -m "v4.10.0 — Block/Shortcode Parity Stabilization"
```

## Parity Coverage

| Scope | PRs | Evidence |
| :--- | :--- | :--- |
| vehicles-list | PR-01 | QA_V410_PR01_VEHICLES_LIST_EVIDENCE.md |
| vehicles-grid | PR-02 | QA_V410_PR02_VEHICLES_GRID_EVIDENCE.md |
| booking-form | PR-03B | QA_V410_PR03B_BOOKING_FORM_EVIDENCE.md |
| testimonials | PR-04 | Implicit (global alias) |
| vehicle-comparison | PR-05 | QA_V410_PR05_VEHICLE_COMPARISON_EVIDENCE.md |
| transfer-search | PR-TS | QA_V410_TRANSFER_SEARCH_EVIDENCE.md |
| Remaining 13 | Light Audit | QA_V410_PARITY_LIGHT_AUDIT_19OF19.md |

## Governance

- [x] Parity cleanup completed
- [x] 19/19 verified
- [x] M4 gates PASS
- [x] Tag created

## Freeze Lock

- Main branch: **Hotfix only** — No new features until v4.11.0
- New features → `v4.11.0` branch

---

**Decision**: ✅ **RELEASE APPROVED**
