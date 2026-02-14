# M2 — Test Harness Inventory

**Date:** 2026-02-14
**Context:** M2 Test Harness Stabilization
**Status:** DRAFT

## 1. Test Runner & Configuration

*   **Command:** `vendor/bin/phpunit` (Standard Composer binary)
*   **Configuration File:** `phpunit.xml` (Overrides `phpunit.xml.dist`)
*   **Bootstrap File:** `tests/bootstrap.php` (Defined in XML)
*   **Test Suite:** "MHM Rentiva Test Suite" -> `./tests/` (Suffix: `Test.php`)

## 2. WordPress Test Library Source (Discovery Flow)

The `tests/bootstrap.php` script determines the location of the WordPress Test Library in the following order:

1.  **Environment Variable:** `WP_TESTS_DIR` (if set).
2.  **Windows Local Development (XAMPP):** Checks for `C:/xampp/htdocs/wordpress-develop/tests/phpunit`.
    *   *Condition:* File exists `.../includes/functions.php`.
    *   **Current State:** Verified active on User Environment.
3.  **System Temp Fallback:** `/tmp/wordpress-tests-lib` (Standard WP-CLI location).

## 3. Database Configuration Source

*   **Primary Source:** `wp-tests-config.php` located in the **Test Library Directory** (e.g., `C:/xampp/htdocs/wordpress-develop/tests/phpunit/wp-tests-config.php`).
*   **Mechanism:** `bootstrap.php` locates this file (checking parent directories if needed) and loads it.
*   **Override Capability:**
    *   The script has logic to **rewrite** the config in-memory/temp file if `WP_TESTS_ISOLATE_DB` env var is set or if running in CI.
    *   It regex-replaces the `ABSPATH` and `$table_prefix` in the loaded config content.

## 4. Database Isolation & Table Prefix

### 4.1 Discovery Mode (`--list-tests`)
*   **Behavior:** Isolation logic is **SKIPPED**.
*   **Prefix:** Uses the prefix defined in the physical `wp-tests-config.php` (likely `wptests_`).

### 4.2 Execution Mode (Standard Run)
*   **Logic:** `should_isolate_db` is `false` by default on local dev (unless `WP_TESTS_ISOLATE_DB=1`).
*   **Current Local Values:**
    *   `should_isolate_db`: `false` (presumably, unless set in shell).
    *   **Prefix:** `wptests_` (from `wp-tests-config.php`).
    *   **Risk:** If `wp-tests-config.php` points to the *main* dev database (unlikely for `wptests_` prefix, but possible), it could flush data.

### 4.3 Isolation Logic (When Active)
*   Scanning `tests/bootstrap.php`:
    *   Generates a random run ID (SHA256 hash).
    *   Prefix becomes: `wptests_{hash}_`.
    *   Creates a generic temp config file: `mhm-rentiva-wp-tests-config-{md5}.php`.
    *   Updates `WP_TESTS_CONFIG_FILE_PATH` env var to point to this temp file.

## 5. Plugin Loading Flow

1.  **Bootstrap:** `tests/bootstrap.php` runs.
2.  **WP Loader:** Requires `includes/functions.php` from Test Lib.
3.  **Action Hook:** Adds `_manually_load_plugin` to `muplugins_loaded`.
4.  **Core Load:** Requires `includes/bootstrap.php` from Test Lib (starts WP).
5.  **Plugin Load:** `_manually_load_plugin` requires `dirname(dirname(__FILE__)) . '/mhm-rentiva.php'`.

## 6. Recommendations for M2

1.  **Explicit Isolation:** We should ensure `WP_TESTS_ISOLATE_DB` can be reliably toggled or enforced via a local config file (e.g. `phpunit.xml` env var) to prevent accidental wipes.
2.  **Config Clarity:** The fallback logic in `bootstrap.php` is robust but complex. Simplifying specifically for the known Windows environment might aid debugging.
3.  **Table Cleanup:** The current logic creates random prefixes but does it ensure *dropping* those tables after the run? Standard WP `includes/bootstrap.php` usually handles usage, but valid "tearDown" is critical for `wptests_{hash}_` to not fill the DB.
