---
trigger: always_on
---

# 🧠 WordPress Plugin Development and Audit Rules

These rules must be applied by the Agent at **all times** during plugin development, analysis, fixing, and testing processes.

## 1. General Coding Principles
- **Language:** Code language, variable names, and comments must be **English**.
- **i18n (Internationalization):** All user-facing text must be wrapped in `__()`, `_e()`, or `esc_html__()` functions for translation support.
- **Dynamic Structure:** Hardcoded URLs, file paths, and text are strictly **prohibited**. All paths and URLs must be generated dynamically (e.g., using `plugin_dir_url()`).
- **Modularity:** The main plugin file should only serve as a "loader". Classes and functions must be organized effectively. **(See `mhm-architect` for file map standards)**.

## 2. Security Standards (Critical)
**Reference Skill:** `mhm-security-guard`
- **Input Security (Sanitization):** All user inputs must be sanitized according to their type (`sanitize_text_field`, `sanitize_email`, `absint`, etc.).
- **Output Security (Escaping):** Every piece of data output to the screen must be escaped (`esc_html`, `esc_attr`, `esc_url`, `wp_kses`).
- **Validation:** `wp_verify_nonce` is mandatory for all Form and AJAX operations. Critical operations must verify permissions using `current_user_can`.
- **SQL Security:** Usage of `$wpdb->prepare()` is mandatory for all raw database queries.

## 3. Performance and Database Optimization
**Reference Skills:** `mhm-db-master` (SQL), `mhm-cli-operator` (Cache)
- **Query Management:** Avoid unnecessary loops and heavy queries; use the **Transient API** or **Object Cache** for expensive operations.
- **Assets:** CSS/JS files must be enqueued only on relevant pages and should be minified. Inline CSS/JS is prohibited.
- **Cleanup:** Detect and remove unnecessary logs (e.g., `cleanup_old_logs`), unused "dead code", and obsolete `option` entries in the database.

## 4. Documentation and Versioning
**Reference Skills:** `mhm-doc-writer`, `mhm-release-manager`, `mhm-git-ops`
- **Changelog:** Ensure `changelog.json` is updated for every functional change.
- **Docs:** Update `website/docs` for any UI or Feature change.
- **Git:** Use Conventional Commits (`feat:`, `fix:`) for all changes.

## 5. Workflow and Reporting Format

The Agent must use the following templates when analyzing and fixing code:

### A. Analysis Report Template
List issues found during file inspection:
> 📄 **[File Path]**
> ⚠️ **Issue:** (Short description)
> 💡 **Recommendation:** (WPCS compliant solution)

### B. Fix Report Template
Use "Before/After" format when applying changes:
> 🔧 **Before:**
> ```php
> // Flawed code
> ```
> ✅ **After:**
> ```php
> // Fixed and optimized code
> ```

### C. Test Results Template
Checks performed after operations:
> 🧪 **Test:** Plugin Activation → ✅ Success
> 🧪 **Test:** Nonce Check → ⚠️ Missing (Must be fixed)

## 5. Special Task Definitions

### Dead Code Cleanup
- Identify functions and classes that are never called.
- Remove unnecessary `console.log` or `error_log` lines.

### Settings Page Check
- If an analyzed function contains hardcoded settings parameters, refactor it to pull from the database.
- Move these parameters to the relevant tab in the Admin Settings page (or create a "General Settings" tab if none exists).