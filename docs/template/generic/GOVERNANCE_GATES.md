# GOVERNANCE GATES

Applicability: plugin-only, theme-only, hybrid

This document defines non-negotiable engineering gates.

## 1. No Tailwind Usage Enforcement
Tailwind runtime and Tailwind utility classes are forbidden.
- Check: block handles containing `tailwind`.
- Scan: search for `@tailwind` directives and Tailwind utility patterns.

## 2. Contract Allowlist Drift Gate
`CompositionBuilder` may render only components declared in `ContractAllowlist`.
Undeclared components must fail import and trigger rollback.

## 3. Asset Snapshot Diff Gate
Verify enqueue handle snapshots before release.
New global CSS/JS requires senior approval.
Conditional loading is mandatory.

## 4. Delta Query Gate
Render path must not add query overhead (`Delta Queries <= 0`).
Manifest-backed data should avoid extra runtime queries.

## 5. Fail Conditions
Reject PR when any of the following is true:
- Tailwind usage detected.
- Additional SQL queries introduced during render.
- Allowlist bypass via direct template calls.

## 6. Gate Evidence Matrix

| Gate | Verification Command / Method | Pass Criteria | Required Evidence |
| :--- | :--- | :--- | :--- |
| No Tailwind Usage | Static scan and asset handle review | Zero findings | Scan summary and reviewed files |
| Contract Drift | Dry-run and preview with allowlist validation | No undeclared component | CLI output summary and validation note |
| Asset Snapshot Diff | Before/after handle snapshot compare | No unexpected global assets | Snapshot diff report |
| Delta Query Gate | Query monitor or internal performance test | Delta Queries <= 0 | Baseline/after/delta output |
| Atomic Safety | Import failure simulation | Database state preserved | Failure output and rollback confirmation |

Missing evidence for any gate means automatic fail.


