# Admin Module Registration Contract (Plugin)

## Purpose
`Setup Wizard`, `About`, and `Test Suite` are treated as core admin modules in this repository.

## Current Contract
The following modules must be registered as part of normal plugin bootstrap when classes are available:

- `src/Plugin.php`
  - `\MHMRentiva\Admin\Setup\SetupWizard::register()`
  - `\MHMRentiva\Admin\About\About::register()`
  - `\MHMRentiva\Admin\Testing\TestAdminPage::register()` (admin context)
- `src/Admin/Utilities/Menu/Menu.php`
  - Setup submenu slug: `mhm-rentiva-setup`
  - About submenu slug: `mhm-rentiva-about`
- `src/Admin/Testing/TestAdminPage.php`
  - Test Suite submenu slug: `mhm-rentiva-tests`

## CI/Test Baseline
- PHPUnit matrix:
  - PHP: `8.1`, `8.2`
  - WP: `6.7`, `latest`
- No deprecated feature-toggle matrix axis.
- No `MHM_TEST_LEGACY_*` environment controls.
- Contract test file:
  - `tests/Integration/Admin/CoreAdminPagesTest.php`

## Change Policy
- These three pages are plugin features, not temporary toggles.
- Any change to menu visibility, slug, or register path must include:
  1. Test updates in `CoreAdminPagesTest.php`
  2. CI green status (`composer test`, workflow matrix)
  3. Documentation update in this file if behavior contract changes

## Verification Commands
- `composer test`
- `composer phpcs`
- `rg -n "SetupWizard::register|About::register|TestAdminPage::register|mhm-rentiva-setup|mhm-rentiva-about|mhm-rentiva-tests" src tests`

## Latest Hardening (2026-02-13)
- Composer scripts are pinned to project-local binaries:
  - `composer test` → `@php ./vendor/bin/phpunit -c phpunit.xml`
  - `composer phpcs` → `@php ./vendor/bin/phpcs --standard=phpcs.xml`
- CI test steps are standardized to use composer scripts where applicable.
- Bootstrap and runtime performance hardening delivered:
  - Block asset versioning now uses stable `filemtime` (no `time()` cache thrash).
  - Duplicate script localization in a single request is guarded.
  - Plugin bootstrap scopes admin-only registrations and avoids duplicate favorites init.
