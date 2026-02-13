# Legacy Deprecation Plan (Plugin)

## Purpose
This document defines a safe, phased deprecation plan for legacy admin modules in `mhm-rentiva`.
Goal: reduce maintenance overhead without breaking booking, frontend, and payment-critical flows.

## Current Legacy Entry Points
The following modules are currently loaded in runtime and must be controlled before removal:

- `src/Plugin.php:266` and `src/Plugin.php:267`:
  - `\MHMRentiva\Admin\Setup\SetupWizard::register()`
- `src/Plugin.php:471` and `src/Plugin.php:472`:
  - `Admin\About\About::register()`
- `src/Plugin.php:536`:
  - `Admin\Testing\TestAdminPage::register()` (debug-only registration path)
- `src/Admin/Utilities/Menu/Menu.php:187`:
  - Setup page render callback (`SetupWizard`)
- `src/Admin/Utilities/Menu/Menu.php:197`:
  - About page render callback (`About`)

## Principles
- No direct removals before a feature-flag gate exists.
- Keep production behavior unchanged until a flag is explicitly disabled.
- Every phase must be covered by regression tests and CI green status.
- Prefer small, reversible commits.

## Current Status (2026-02-13)
- Phase 2 completed:
  - Feature gates added in `src/Plugin.php` and `src/Admin/Utilities/Menu/Menu.php`.
  - Active filters:
    - `mhm_rentiva_legacy_feature_enabled`
    - `mhm_rentiva_legacy_setup_wizard_enabled`
    - `mhm_rentiva_legacy_about_page_enabled`
    - `mhm_rentiva_legacy_admin_testing_page_enabled`
- Phase 3 completed:
  - Contract tests added in `tests/Integration/Legacy/LegacyFeatureFlagTest.php`.
  - `legacy=off` behavior is validated by CI matrix.
- Phase 4 in progress:
  - `Admin\Testing\TestAdminPage` is now default OFF (`src/Plugin.php`).
  - `Admin\About\About` is now default OFF (`src/Plugin.php`, `src/Admin/Utilities/Menu/Menu.php`).
  - `Admin\Setup\SetupWizard` is now default OFF (`src/Plugin.php`, `src/Admin/Utilities/Menu/Menu.php`).
  - Feature-specific filter can still re-enable it when needed.
  - CI pipeline is green with matrix:
    - PHP: 8.1, 8.2
    - WP: 6.7, latest
    - Legacy mode: on, off

## Phase Plan

### Phase 1: Inventory and Safety Baseline
- Completed by this document.
- No runtime code changes.

### Phase 2: Feature Flag Layer
- Add centralized helpers (example flags):
  - `mhm_rentiva_enable_setup_wizard`
  - `mhm_rentiva_enable_about_page`
  - `mhm_rentiva_enable_admin_testing_page`
- Wire `src/Plugin.php` and `src/Admin/Utilities/Menu/Menu.php` to these flags.
- Default values: enabled (`true`) for backward compatibility.

### Phase 3: Contract Tests for Flag-Off Mode
- Add tests ensuring plugin remains functional when legacy flags are disabled:
  - booking flow unaffected
  - compare/favorites unaffected
  - critical settings pages still reachable
- Add CI job variant (optional) with legacy flags disabled.

### Phase 4: Controlled Deactivation
- Disable lowest-risk module first (recommended order):
  1. `Admin\Testing\TestAdminPage`
  2. `Admin\About\About`
  3. `Admin\Setup\SetupWizard`
- Monitor CI and functional smoke checks after each module.

### Phase 5: Physical Removal
- Remove dead registrations and menu entries.
- Remove unused classes/files only after at least one green cycle with flags off.
- Update docs/changelog.

## Exit Criteria
- All CI checks pass with legacy modules disabled.
- No regression in booking, compare/favorites, or payment integrations.
- Removed modules have no active references in:
  - `src/Plugin.php`
  - `src/Admin/Utilities/Menu/Menu.php`
  - hooks registered during bootstrap.

## Commands for Verification
- Search active references:
  - `rg -n "SetupWizard::register|About::register|TestAdminPage::register|Admin\\\\Setup|Admin\\\\About" src`
- Run test suite:
  - `composer test`
- Run coding standards:
  - `composer phpcs`
