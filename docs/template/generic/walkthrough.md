# Walkthrough Template

Applicability: plugin-only, theme-only, hybrid

## Walkthrough: vX.Y.Z - Safe Rollback Engine

> Replace vX.Y.Z with the actual release version before finalizing this document.

This document explains technical scope, behavior model, and verification outcomes for the Safe Rollback Engine release.

---

## 1. Objective
- Enable safe and atomic rollback of layout versions.
- Preserve deterministic hash guarantees.
- Enforce governance gates during rollback.
- Preserve render performance target (`Delta Queries <= 0`).

## 2. Architectural Changes
### 2.1 New Components
- `LayoutRollbackService`
- CLI command: `layout rollback`
- Previous-state meta keys

### 2.2 Modified Components
- `AtomicImporter`
- CLI command registration

### 2.3 Determinism Guarantee
- Re-verify previous manifest hash via normalization.
- Fail rollback on hash mismatch.
- No non-deterministic restore transforms.

## 3. Rollback State Machine
- STATE A: Snapshot current
- STATE B: Load previous
- STATE C: Validate and gate
- STATE D: Apply and flip
- STATE E: Failure recovery
- STATE F: Success

Atomicity:
- Any failure restores snapshot from STATE A.
- Partial write is not allowed.

## 4. Governance Enforcement
Rollback must enforce:
- Blueprint validation
- ContractAllowlist checks
- Token mapping validation
- Forbidden pattern scan
- Adapter validation

## 5. Performance Impact
- Rollback is write-time work.
- Frontend render target remains `Delta Queries <= 0`.
- No unexpected asset handle changes.

## 6. Test Coverage
List added tests and provide full suite result:
```text
OK (N tests, N assertions)
```

## 7. CLI Usage Examples
```bash
wp {{CLI_NAMESPACE}} layout rollback 123 --dry-run
wp {{CLI_NAMESPACE}} layout rollback 123
```

## 8. Version Flip Semantics
After rollback:
- current <- old previous
- previous <- old current

## 9. Failure Scenarios
Rollback fails when:
- previous manifest missing
- hash mismatch
- any governance gate fails

Failure action:
- snapshot restore
- database state preserved

## 10. Minimum Evidence Pack
- File diff summary (added/modified)
- PHPCS evidence
- PHPUnit evidence
- Rollback-specific test output
- CLI verification output
- Delta query measurement
- Asset snapshot diff
- Governance scan result

## Evidence Rule
No evidence section means release is blocked.


