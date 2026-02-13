# Legacy Deprecation Plan (Plugin)

## Purpose
This document defines a safe, phased deprecation plan for legacy admin modules in `mhm-rentiva`.
Goal: reduce maintenance overhead without breaking booking, frontend, and payment-critical flows.

## Current Legacy Entry Points
The following modules are currently loaded in runtime and must be controlled before removal:

- `src/Plugin.php:288` and `src/Plugin.php:289`:
  - `\MHMRentiva\Admin\Setup\SetupWizard::register()`
- `src/Plugin.php:493` and `src/Plugin.php:494`:
  - `Admin\About\About::register()`
- `src/Plugin.php:557`:
  - `Admin\Testing\TestAdminPage::register()` (debug-only registration path)
- `src/Admin/Utilities/Menu/Menu.php:208`:
  - Setup page render callback (`SetupWizard`)
- `src/Admin/Utilities/Menu/Menu.php:220`:
  - About page render callback (`About`)

## Legacy Module Decision Matrix (Current Baseline)
This table is the source of truth for whether a module is kept, deprecated, or removed.

| Module | Runtime Role | Current Default | Decision | How It Will Be Updated |
|---|---|---|---|---|
| `Admin\Setup\SetupWizard` | Optional onboarding UI | Enabled | **Deprecate (staged)** | Keep feature flag + tests; move only required onboarding pieces into modern settings flow; remove class/hook after one stable release cycle with legacy off. |
| `Admin\About\About` | Informational admin page | Enabled | **Deprecate (staged)** | Keep feature flag + menu gate; migrate useful diagnostics into existing settings/license pages; remove class/menu callback after acceptance. |
| `Admin\Testing\TestAdminPage` | Debug/development page | Disabled | **Remove first** | Keep default OFF; verify no production dependency; remove registration and dead files in first removal pass. |

## Legacy Update Policy (Why It Exists Before First Deploy)
- "Legacy" in this repository means **older architecture/modules inside current codebase**, not necessarily previously deployed production versions.
- A module can be marked legacy even before first public deploy if:
  - there is a newer replacement path, and
  - keeping both paths increases maintenance risk.
- Updates must happen in this order:
  1. Gate module with feature flag.
  2. Add/keep regression tests for `legacy=on` and `legacy=off`.
  3. Migrate or replace behavior in the new path.
  4. Remove old module only after CI stays green for at least one release cycle.

## Principles
- No direct removals before a feature-flag gate exists.
- Keep production behavior unchanged until a flag is explicitly disabled.
- Every phase must be covered by regression tests and CI green status.
- Prefer small, reversible commits.
- Production-used admin pages are protected:
  - If a legacy page is still used in real plugin operations, do not physically remove it.
  - In that case, manage visibility/availability only via feature flags and role/capability checks.
  - Physical removal requires explicit owner approval after usage validation.

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
- Add centralized helpers (active filters):
  - `mhm_rentiva_legacy_feature_enabled`
  - `mhm_rentiva_legacy_setup_wizard_enabled`
  - `mhm_rentiva_legacy_about_page_enabled`
  - `mhm_rentiva_legacy_admin_testing_page_enabled`
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
- Runtime registrations:
  - `src/Plugin.php:557`
- Module files:
  - `src/Admin/Testing/TestAdminPage.php`
  - `src/Admin/Testing/TestRunner.php`
  - `src/Admin/Testing/ActivationTest.php`
  - `src/Admin/Testing/FunctionalTest.php`
  - `src/Admin/Testing/IntegrationTest.php`
  - `src/Admin/Testing/PerformanceAnalyzer.php`
  - `src/Admin/Testing/PerformanceTest.php`
  - `src/Admin/Testing/SecurityTest.php`
  - `src/Admin/Testing/ShortcodeTestHandler.php`
  - `src/Admin/Testing/DemoSeeder.php`
- Asset touchpoints to recheck:
  - `src/Admin/Core/AssetManager.php:905`

### Candidate B: About Module
- Runtime registrations:
  - `src/Plugin.php:493`
  - `src/Admin/Utilities/Menu/Menu.php:220`
- Module files:
  - `src/Admin/About/About.php`
  - `src/Admin/About/Helpers.php`
  - `src/Admin/About/SystemInfo.php`
  - `src/Admin/About/Tabs/DeveloperTab.php`
  - `src/Admin/About/Tabs/FeaturesTab.php`
  - `src/Admin/About/Tabs/GeneralTab.php`
  - `src/Admin/About/Tabs/SupportTab.php`
  - `src/Admin/About/Tabs/SystemTab.php`
- Asset touchpoints to recheck:
  - `src/Admin/Core/AssetManager.php:1154`

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
1. Remove Candidate A (Admin Testing Suite), run CI, release.
2. Remove Candidate B (About Module), run CI, release.
3. Remove Candidate C (Setup Wizard), run CI, release.
4. Remove now-unused hooks/assets/options cleanup code after one additional green cycle.

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
