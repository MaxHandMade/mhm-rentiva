# V4.20.1 Ecosystem Audit — QA Evidence Document

**Date:** 2026-02-25
**Instruction:** V4.20.1-ECOSYSTEM-AUDIT
**Agent Role:** Implementation & Documentation Agent (Claude)
**Workflow:** audit → optimize → verify (Canonical)

---

## 1. Scope Summary

Full ecosystem X-Ray of MHM Rentiva plugin v4.20.1:
- Shortcode registry inspection (ShortcodeServiceProvider)
- Block registry inspection (BlockRegistry)
- AllowlistRegistry schema inspection
- Elementor widget registry inspection
- Parity comparison matrix validation
- Documentation synchronization

**No code changes introduced. Audit and documentation only.**

---

## 2. Files Inspected (MCP-Verified)

| File | Purpose | Verification |
| :--- | :--- | :--- |
| `src/Admin/Core/ShortcodeServiceProvider.php` | Active shortcode registry | Read directly — 20 shortcodes confirmed |
| `src/Blocks/BlockRegistry.php` | Block registry | Read directly — 19 blocks confirmed |
| `src/Core/Attribute/AllowlistRegistry.php` | Canonical attribute schema | Read directly — 1515 lines, 10 duplicate keys found |
| `src/Admin/Frontend/Widgets/Elementor/ElementorIntegration.php` | Elementor registry | Read directly — 20 widgets confirmed |
| `src/Admin/Frontend/Shortcodes/HomePoc.php` | Home POC experimental SC | Read directly — template-only, no attributes |
| `SHORTCODES.md` | Documentation source of truth | Read directly — 933 lines (pre-update) |
| `PROJECT_MEMORIES.md` | Memory log | Read directly — v4.20.0 foundation freeze confirmed |

---

## 3. Discrepancy List

### CRITICAL
| ID | File | Description | Action |
| :--- | :--- | :--- | :--- |
| C1 | `AllowlistRegistry.php` | 10 duplicate PHP array keys in `ALLOWLIST` constant; runtime aliases differ from documented state | Code fix required (separate task) |
| C2 | `ShortcodeServiceProvider.php` | `rentiva_home_poc` registered with no AllowlistRegistry schema, no Block, no Elementor widget | Intentional (experimental) — document only |

### MAJOR
| ID | Description | Affected Files |
| :--- | :--- | :--- |
| M1 | 4 `enum` attributes without `values` constraint: `status`, `default_payment`, `service_type`, `type` | `AllowlistRegistry.php` |
| M2 | 7 TAG_MAPPING attributes not in canonical ALLOWLIST (`show_technical_specs`, `show_booking_form`, `show_book_button`, `sort_by`, `sort_order`, `show_avatar`, `filter_rating`) | `AllowlistRegistry.php` |

### MINOR
| ID | Description |
| :--- | :--- |
| Mi1 | Redundant twin canonical attributes (`show_booking_btn`/`show_booking_button`, etc.) — acknowledged by design |
| Mi2 | Block-Only/SC-Only attributes in 3-Layer Matrix — expected by architecture |

---

## 4. Updated Documentation Files

| File | Change |
| :--- | :--- |
| `SHORTCODES.md` | Added Section 11 (Elementor Widget Registry), Section 12 (Audit Findings v4.20.1), Contract Change Log entries |
| `PROJECT_MEMORIES.md` | Added `[AUDIT] V4.20.1 Ecosystem X-Ray` entry at top |
| `docs/plans/2026-02-25-v4201-ecosystem-audit.md` | Created: full audit plan, inventory, discrepancy matrix |
| `docs/2026-02-25-V4201-ECOSYSTEM-AUDIT-EVIDENCE.md` | This document — QA evidence |

---

## 5. Required CLI Commands (Reference)

These commands must be executed in the active WordPress environment to produce runtime evidence:

```bash
# 1. Plugin status verification
wp plugin list --fields=name,status,version | grep mhm-rentiva

# 2. Runtime shortcode registry (via filter)
wp eval 'print_r( apply_filters( "mhm_rentiva_registered_shortcodes", [] ) );'

# 3. ShortcodeServiceProvider total count
wp eval 'echo "Total shortcodes: " . MHMRentiva\Admin\Core\ShortcodeServiceProvider::get_total_count() . PHP_EOL;'

# 4. Shortcode groups
wp eval 'print_r( MHMRentiva\Admin\Core\ShortcodeServiceProvider::get_shortcode_groups() );'

# 5. AllowlistRegistry TAG keys
wp eval 'print_r( array_keys( MHMRentiva\Core\Attribute\AllowlistRegistry::get_registry() ) );'

# 6. Registered Gutenberg blocks (mhm-rentiva only)
wp eval '$all = WP_Block_Type_Registry::get_instance()->get_all_registered(); $mhm = array_filter( array_keys($all), fn($k) => str_starts_with($k, "mhm-rentiva") ); print_r(array_values($mhm));'

# 7. Cache flush
wp cache flush
```

**Expected Results:**
- Total shortcodes: 20 (including `rentiva_home_poc`)
- AllowlistRegistry keys: 19 (no `rentiva_home_poc`)
- Registered blocks: 19 `mhm-rentiva/*` blocks

---

## 6. PHPUnit Execution (Reference)

```bash
cd C:\projects\rentiva-dev\plugins\mhm-rentiva
vendor/bin/phpunit --testdox
```

**Baseline (v4.20.0 foundation freeze):** OK — 268 tests, 1379 assertions
**Acceptance:** No new failures. Zero regressions.
**Note:** No PHP code was modified in this audit. PHPUnit results should be identical to v4.20.0 baseline.

---

## 7. Shortcode Regression Notes

No shortcode logic was modified. All findings are documentation-level only.
The only code-level defect (C1 — duplicate ALLOWLIST keys) was flagged but NOT fixed in this task.
Any future fix to C1 must be accompanied by:
1. PHPUnit full suite run confirming no regressions
2. Alias table in SHORTCODES.md Section 8 update
3. Contract Change Log entry

---

## 8. Risk Classification Table

| Finding | Risk Level | Parity Impact | Runtime Impact |
| :--- | :---: | :---: | :--- |
| C1: Duplicate ALLOWLIST keys | CRITICAL | Medium | `ids` type changed, multiple aliases lost |
| C2: `rentiva_home_poc` orphaned | INFO | None | No attributes, template-only |
| M1: Enum without values | MAJOR | Low | No validation against allowed values |
| M2: Unregistered TAG_MAPPING attrs | MAJOR | Low | Untyped pass-through in get_registry() |
| Mi1: Twin canonical attrs | MINOR | None | Acknowledged redundancy |
| Mi2: Block/SC-Only attrs | MINOR | None | By architectural design |

---

## 9. QA Final Decision

**Decision: CONDITIONAL PASS**

**Critical Findings:** 1 active (C1 — requires code fix in separate task), 1 by-design (C2)

**Major Findings:** M1 (4 enum attributes without values), M2 (7 unregistered TAG_MAPPING attributes)

**Minor Findings:** Mi1, Mi2 — acknowledged, no action required

**Required Fixes (for Full PASS):**
1. `AllowlistRegistry.php` — Remove duplicate ALLOWLIST keys, merge aliases into first definitions (C1)
2. `AllowlistRegistry.php` — Add `values` arrays to enum attributes: `status`, `default_payment`, `service_type`, `type` (M1)
3. `AllowlistRegistry.php` — Add `show_technical_specs`, `show_booking_form`, `show_book_button` to ALLOWLIST, or update TAG_MAPPING to use canonical keys (M2)

**Re-test Plan:** After any AllowlistRegistry fixes, run full PHPUnit suite and verify parity matrix.

**Memory Conflict Status:** None detected. Audit-only.

---

*Evidence collected via static file analysis (MCP-backed). CLI runtime evidence pending execution in active WP environment.*
