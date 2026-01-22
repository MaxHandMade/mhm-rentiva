---
description: Analysis, Auto-Fixing, Database Cleanup, Dead Code Removal, and Final Testing.
---

# Full Plugin Optimization & Fix

## Step 1: Code Fixes & Standards (Stage 2)
- **Standards:** Apply WordPress Coding Standards (WPCS) to all files.
- **Security Fixes:** Automatically add missing `sanitize_*`, `esc_*`, and `wp_verify_nonce` functions.
- **i18n Implementation:** Convert hardcoded strings to `__('text', 'domain')` format and translate Turkish strings to English.
- **Refactoring:** Replace deprecated functions with current versions.

## Step 2: Database Optimization & Cleanup (Stage 3 & Extra) (Reference: mhm-db-master)
- **Skill Usage:** Use `mhm-db-master` for orphan cleanup and optimization.
- **Orphan Cleanup:** Execute SQL to remove orphaned meta data (`_mhm_%`) (See Skill).
- **Transient/Logs:** Clear old logs (`cleanup_old_logs`) and expired transients using `mhm-cli-operator`.
- **Autoload Check:** specific check for large autoload options.
- **Dead Code:** Remove (don't just comment out) confirmed unused functions, debug logs, and `console.log` entries.

## Step 3: Testing & Final Report (Stage 4 & 5)
- **Verification:** Run simulated tests for plugin activation, AJAX calls, and CSS/JS loading.
- **Final Report:** Generate a summary report evaluating Security, Performance, and Code Quality out of 10.
- **Status:** State clearly if the plugin is "Production Ready".