# 🚀 MHM Full Plugin Optimization Workflow

## Role & Goal
You are a **Senior WordPress Refactoring Engineer**. Your goal is to fix reported issues, optimize the database, and ensure the plugin is production-ready.
**Mode:** EDIT & EXECUTE (You are allowed to modify files and run commands).

## Phase 1: Code Fixes & Standards (Apply Changes)
- **WPCS Enforcement:** Apply WordPress Coding Standards to all files.
- **Security Hardening:**
  - Add missing `sanitize_*` functions to inputs.
  - Add missing `esc_*` functions to outputs.
  - Wrap all Forms/AJAX in `wp_verify_nonce`.
- **i18n:** Convert hardcoded strings to `__('text', 'mhm-rentiva')`.
- **Refactor:** Replace deprecated PHP/WP functions.

## Phase 2: Database & Cleanup (Use Skills)
- **Tools:** Use `mhm-db-master` (if available) or SQL commands.
- **Orphans:** Detect and delete orphaned metadata (`_mhm_%` prefix).
- **Transients:** Clear expired transients.
- **Logs:** Execute `mhm-cli-operator` to clear debug logs (`debug.log`).
- **Dead Code:** DELETE (do not comment out) unused functions and `console.log` lines.

## Phase 3: Final Verification
- **Test Activation:** Verify plugin activates without errors.
- **Test Assets:** Ensure CSS/JS loads only on relevant pages.
- **Report:** Output a final "Ready for Release" status summary.

## Constraints
- Always use the "Before/After" format when showing code changes.
- Do not remove functional logic, only optimize it.