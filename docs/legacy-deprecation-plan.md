# Legacy Deprecation Plan (Plugin)

## Purpose
This document defines a safe, phased deprecation plan for legacy admin modules in `mhm-rentiva`.
Goal: reduce maintenance overhead without breaking booking, frontend, and payment-critical flows.

## Current Legacy Entry Points
The following modules are currently loaded in runtime and must be controlled before removal:

- `src/Plugin.php:288` and `src/Plugin.php:289`:
  - `\MHMRentiva\Admin\Setup\SetupWizard::register()`
- `src/Admin/Utilities/Menu/Menu.php:208`:
  - Setup page render callback (`SetupWizard`)

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
- Phase 3 completed:
  - Contract tests added in `tests/Integration/Legacy/LegacyFeatureFlagTest.php`.
  - `legacy=off` behavior is validated by CI matrix.
- Phase 4 in progress:
  - `Admin\Testing\TestAdminPage` has been physically removed (Phase 5 Candidate A completed).
  - `Admin\About\About` has been physically removed (Phase 5 Candidate B completed).
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
- Add centralized helpers (active filters):
  - `mhm_rentiva_legacy_feature_enabled`
  - `mhm_rentiva_legacy_setup_wizard_enabled`
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

## Phase 5 Removal Inventory (Prepared)
The following entries are now candidates for physical removal because all three legacy modules are default OFF and CI is green in both `legacy=on` and `legacy=off` modes.

### Candidate A: Admin Testing Suite (lowest risk)
- Status: completed on `2026-02-13`.
- Removed runtime registration from `src/Plugin.php`.
- Removed asset touchpoint from `src/Admin/Core/AssetManager.php`.
- Removed module files under `src/Admin/Testing/*`.

### Candidate B: About Module
- Status: completed on `2026-02-13`.
- Removed runtime registration from `src/Plugin.php` and `src/Admin/Utilities/Menu/Menu.php`.
- Removed asset touchpoint from `src/Admin/Core/AssetManager.php`.
- Removed module files under `src/Admin/About/*`.

### Candidate C: Setup Wizard Module (highest risk among legacy set)
- Runtime registrations:
  - `src/Plugin.php:288`
  - `src/Admin/Utilities/Menu/Menu.php:208`
- Module files:
  - `src/Admin/Setup/SetupWizard.php`
- Data/options and action hooks to migrate/cleanup:
  - Option keys:
    - `mhm_rentiva_setup_completed`
    - `mhm_rentiva_setup_redirect`
  - Action handlers:
    - `admin_post_mhm_rentiva_setup_save_license`
    - `admin_post_mhm_rentiva_setup_create_pages`
    - `admin_post_mhm_rentiva_setup_save_email`
    - `admin_post_mhm_rentiva_setup_save_frontend`
    - `admin_post_mhm_rentiva_setup_finish`
    - `admin_post_mhm_rentiva_setup_skip`
    - `admin_post_mhm_rentiva_dismiss_permalink_notice`

## Phase 5 Execution Order
1. Remove Candidate C (Setup Wizard), run CI, release.
2. Remove now-unused hooks/assets/options cleanup code after one additional green cycle.

## Exit Criteria
- All CI checks pass with legacy modules disabled.
- No regression in booking, compare/favorites, or payment integrations.
- Removed modules have no active references in:
  - `src/Plugin.php`
  - `src/Admin/Utilities/Menu/Menu.php`
  - hooks registered during bootstrap.

## Commands for Verification
- Search active references:
  - `rg -n "SetupWizard::register|Admin\\\\Setup" src`
- Run test suite:
  - `composer test`
- Run coding standards:
  - `composer phpcs`
