# QA Evidence: M2 Test Harness Stabilization

**Date:** 2026-02-14
**Version:** v1.3.5 (M2)
**Status:** In Progress

## 5.1 Non-Isolated Mode Smoke
*   **Command:** `vendor/bin/phpunit`
*   **Expectation:** PASS, No DB Drop, Standard Prefix.
*   **Result:** **PASS** (Confirmed via console output).
*   **Note:** "Installing..." message confirms test suite bootstrap.

## 5.2 Isolated Mode Smoke
*   **Command:** `$env:WP_TESTS_ISOLATE_DB="true"; vendor/bin/phpunit`
*   **Expectation:** PASS, Dynamic Prefix (`wptests_{hash}_`), Cleanup at end.
*   **Result:** **PASS**.
*   **Cleanup Verification:** `SHOW TABLES LIKE 'wptests_%_%'` returned **EMPTY**.
    *   *Conclusion:* `MHM_Test_Listener` successfully identified and dropped isolated tables.

## 5.3 Determinism Gate (5 runs)
*   **Result:** **PASS** (5/5 Successful Runs).
*   **Stability:** High. No flaky failures observed. Standard prefix usage confirmed stable.

## 5.4 Safety Guard Review
*   Ref: `tests/bootstrap.php` -> `mhm_verify_safe_db_context()`
*   **Logic Verified:** The guard requires at least ONE of the following to proceed:
    1.  DB Name contains 'test'.
    2.  Table Prefix starts with 'wptests_'.
    3.  Environment Type is 'test' or 'local'.
## 6. Scope Proof (M2 Closure)
*   **Command:** `git status -s`
*   **Changed Files:**
    *   `docs/internal/M2_*.md` (Documentation)
    *   `docs/internal/QA_M2_EVIDENCE.md` (Evidence)
    *   `tests/bootstrap.php` (Refactored Harness)
    *   `tests/MHM_Test_Listener.php` (New Listener)
    *   `tests/bin/install-wp-tests.ps1` (New Helper)
    *   `phpunit.xml.dist` (Improved Config)
    *   `task.md` (Checklist)
*   **Verdict:** **CLEAN SCOPE**. No changes to `src/` or core plugin logic.
