---
description: Performs a comprehensive read-only analysis of the plugin files focusing on WPCS, security, i18n, and performance issues without modifying any files.
---

# MHM Rentiva - Code Audit Workflow (v2.0)

This workflow defines the mandatory steps for auditing code before any merge or release. The goal is to ensure 100% compliance with WordPress.org standards and zero runtime errors.

## Phase 1: Static Analysis (Pre-Execution)
*Tools: IDE (VS Code), PHP Lint, WPCS*

1.  **Strict Types Check:**
    * Verify that `declare(strict_types=1);` is present at the top of every PHP file.
    * Ensure all function parameters and return types are strictly typed.

2.  **Namespace & Prefix Verification:**
    * Scan for generic class names or functions.
    * **FAIL:** `class BookingForm`
    * **PASS:** `namespace MHMRentiva\Booking; class BookingForm`
    * **FAIL:** `function get_cars()`
    * **PASS:** `function mhm_rentiva_get_cars()`

3.  **Security Scan (Sanitization & Escaping):**
    * **Input:** Check all `$_POST`, `$_GET` usages. Must be wrapped in `sanitize_*` functions.
    * **Output:** Check all `echo` statements. Must be wrapped in `esc_*` functions.
    * **Database:** Verify NO direct variable interpolation in SQL. `$wpdb->prepare()` is mandatory.

## Phase 2: Runtime Analysis (Execution)
*Tools: Query Monitor, Localhost Environment*

1.  **Query Monitor Check:**
    * Load the plugin pages in the browser.
    * Open Query Monitor panel.
    * **PHP Errors:** Must be empty (No Notices, No Warnings, No Deprecated).
    * **Database Queries:** Check for slow queries (>0.05s) or duplicate queries.
    * **Scripts/Styles:** Check for 404 errors or missing dependencies.

2.  **Debug Log Check:**
    * Check `wp-content/debug.log`.
    * The file should be empty or contain no new errors related to MHM Rentiva.

## Phase 3: Compliance Audit (The Gatekeeper)
*Tools: Plugin Check (PCP) Plugin*

1.  **Run Plugin Check:**
    * Navigate to **Tools > Plugin Check**.
    * Select "MHM Rentiva".
    * Run all checks.

2.  **Zero-Tolerance Policy:**
    * **Errors:** 0 allowed. (Must be fixed immediately).
    * **Warnings:** 0 allowed. (Exceptions must be documented).
    * **Notices:** Minimize as much as possible.

## Phase 4: Metadata Synchronization
1.  **Version Consistency:**
    * Compare `Version` in `mhm-rentiva.php`.
    * Compare `Stable tag` in `readme.txt`.
    * Compare `const VERSION` in the main class.
    * **Result:** All three MUST match exactly.

---
**Audit Decision:**
- [ ] **APPROVE:** All phases passed. Ready for optimization.
- [ ] **REJECT:** Any error found in Phase 1, 2, or 3. Return to development.