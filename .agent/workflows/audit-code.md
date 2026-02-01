---
description: Performs a comprehensive read-only analysis of the plugin files focusing on WPCS, security, i18n, and performance issues without modifying any files.
---

# MHM Rentiva - Code Audit Workflow (v3.0)

This workflow defines the mandatory steps for auditing code before any merge or release. It is now fully integrated with the **MHM Skills Hub** to ensure autonomous excellence.

## 🤖 Automated Skill Integration
- **Security Guard Enforcement:** Automatically verify `nonces`, `current_user_can('manage_options')`, and `wp_unslash` / `sanitize_text_field` standards during Phase 1.
- **Performance Auditor Check:** Scan for SQL queries using `numberposts => -1` or missing `wp_cache` / `transient` logic during Phase 2.
- **Memory Keeper Logging:** Every audit session must start by reading and end by updating `PROJECT_MEMORIES.md`.

## Phase 1: Static Analysis (Pre-Execution)
*Primary Skill: mhm-security-guard*

1.  **Strict Types Check:**
    * Verify `declare(strict_types=1);` is at the top of every PHP file.
    * Ensure all function parameters and return types are strictly typed.

2.  **Namespace & Prefix Verification:**
    * **PASS:** `namespace MHMRentiva\Booking; class BookingForm`
    * **PASS:** `function mhm_rentiva_get_cars()`

3.  **Security Scan (Sanitization & Escaping):**
    * **Input:** Every `$_POST`/`$_GET` must be wrapped in `sanitize_*` and `wp_unslash`.
    * **Output:** Every `echo` must be wrapped in `esc_*`.
    * **Database:** `$wpdb->prepare()` is mandatory. Direct interpolation is a Critical Fail.

## Phase 2: Runtime Analysis (Execution)
*Primary Skill: mhm-performance-auditor*

1.  **Query Monitor & Efficiency Check:**
    * **Slow Queries:** No query should exceed 0.05s.
    * **Redundancy:** Eliminate "Duplicate Queries" using Object Cache or Transients.
    * **Asset Optimization:** Verify scripts are enqueued conditionally (Only where needed).

2.  **Debug Log Check:**
    * Verify `wp-content/debug.log` is clean of any MHM Rentiva notices or warnings.

## Phase 3: Compliance Audit (The Gatekeeper)
*Tools: Plugin Check (PCP) Plugin*

1.  **Zero-Tolerance Policy:**
    * **Errors:** 0 allowed.
    * **Warnings:** 0 allowed (Must be refactored or documented as Technical Debt).

## Phase 4: Metadata & Memory Sync
*Primary Skill: mhm-memory-keeper*

1.  **Version Consistency:**
    * Sync versions in `mhm-rentiva.php`, `readme.txt`, and `const VERSION`.

2.  **Final Memory Update:**
    * Record any newly identified **Technical Debts** (e.g., Performance Warnings) in the TECHNICAL NOTES of `PROJECT_MEMORIES.md`.
    * Move the verified audit task to the ARCHIVE.

---
**Audit Decision:**
- [ ] **APPROVE:** All phases passed. `PROJECT_MEMORIES.md` updated.
- [ ] **REJECT:** Return to development. Log the reason in `PROJECT_MEMORIES.md`.