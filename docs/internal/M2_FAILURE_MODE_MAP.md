# M2 — Failure Mode Map

**Context:** MHM Rentiva Test Harness Stabilization
**Focus:** Identifying risks associated with moving to an isolated/randomized DB prefix strategy.

## 1. Helper/Bootstrap Failures

| Failure Mode | Symptom (Belirti) | Root Cause (Neden) | Solution (Çözüm) |
| :--- | :--- | :--- | :--- |
| **Discovery Mode Drift** | DB clutter with empty tables (`wptests_abc123_*`). | usage of `--list-tests` triggers `bootstrap.php` which generates a random prefix, but since tests don't run, no `tearDown` or cleanup happens. | 1. Detect `--list-tests` argument.<br>2. Use a **FIXED** prefix (e.g., `wptests_discovery_`) for discovery mode.<br>3. Skip DB table creation entirely if possible in discovery. |
| **Windows Pathing Hell** | `Could not find .../includes/functions.php`. | Hardcoded `/` vs `\` mismatches or `sys_get_temp_dir()` returning inconsistent slashes on Windows. | Normalize all paths using `str_replace('\\', '/', ...)` in `bootstrap.php`. Use `install-wp-tests.ps1` helper. |
| **Config Write Permission** | `bootstrap.php` crash / fallback failure. | Script tries to write temp config file in a restricted dir or file is locked. | Ensure Temp dir is writable. Use hash-based filenames to avoid lock contention. |

## 2. Database State Failures

| Failure Mode | Symptom (Belirti) | Root Cause (Neden) | Solution (Çözüm) |
| :--- | :--- | :--- | :--- |
| **Duplicate Table** | `SQL Error: Table 'wptests_xyz_mhm_rentiva_bookings' already exists`. | Previous test run utilized the same prefix (hash collision or reuse) and failed to drop custom tables on exit. | 1. Implement `MHM_Test_Listener` to `DROP TABLES` matching prefix on `endTestSuite`.<br>2. Ensure `bootstrap.php` drops tables *before* installing if reusing prefix. |
| **Option Drift** | Tests failing intermittently due to "unexpected settings state". | Options (`mhm_rentiva_*`) set during a test are not rolled back because transaction wrapping (`WP_UnitTestCase`) doesn't cover external DB commits or explicit `commit` calls. | 1. Rely strictly on `WP_UnitTestCase` transaction rollback.<br>2. Explicitly `delete_option()` in `tearDown()` for critical settings. |
| **Transient Leakage** | Caching tests return stale data across runs. | Transients (`_transient_mhm_*`) persist in `wp_options` and are ignored by standard rollback if logic uses `wp_cache_set` (object cache) but fallback hits DB. | Flush transients in `tearDown()`. Enforce `array` driver for object cache during tests. |

## 3. WordPress Core State Failures

| Failure Mode | Symptom (Belirti) | Root Cause (Neden) | Solution (Çözüm) |
| :--- | :--- | :--- | :--- |
| **Rewrite Rule Corruption** | Test returns 404 for CPT endpoints. | `flush_rewrite_rules()` updates `wp_options`. If a test crashes mid-run, the rewrite array might be partial/corrupt for next run. | Re-run `flush_rewrite_rules()` in `setUp()` of Route/CPT tests. |
| **Cron Schedule Drift** | Unexpected cron events firing/missing. | Core Cron array stored in options. | Mock Cron system or clear cron array in `setUp()`. |
| **User/Role Pollution** | "User already exists" or capability errors. | Creating users without `self::factory()->user->create()` (which handles cleanup). | **Strict Rule:** ALWAYS use `self::factory()` generators. |

## 4. Mitigation Strategy (M2 Plan)

1.  **Isolation:** Randomize Prefix (`wptests_{hash}_`) per run to prevent collision.
2.  **Cleanup (Listener):** Add a PHPUnit Listener to aggressively DROP all tables with the run's prefix at shutdown.
3.  **Discovery Check:** Short-circuit logic in `bootstrap.php` when `--list-tests` is present to avoid creating "ghost" prefixes.
