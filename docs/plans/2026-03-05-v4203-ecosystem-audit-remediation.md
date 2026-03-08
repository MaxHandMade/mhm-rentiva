# v4.20.3 Ecosystem Audit Remediation Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Close the ecosystem audit findings from `docs/reports/2026-03-02-ecosystem-audit.md` with test-backed fixes and a production-safe rollout order.

**Architecture:** First re-baseline the findings (some may already be partially fixed), then execute strict priority order: P0 dashboard runtime breakage, P1 settings/runtime consistency, P2 schema/widget hardening. Keep each change atomic with RED→GREEN tests and small commits.

**Tech Stack:** WordPress plugin (PHP), WPCS/PHPCS, PHPUnit in Docker (`rentiva-dev-wpcli-1`), admin JS enqueue, Elementor widgets, Gutenberg block metadata, shortcode CAM pipeline.

---

## Scope

1. Fix the critical dashboard sortable runtime issue.
2. Resolve high-priority config/runtime inconsistencies.
3. Harden Allowlist/Tag mapping and settings sanitization.
4. Improve Elementor SearchResultsWidget attribute coverage.
5. Add missing docs and close low-risk tech-debt items.

## Non-Goals

1. No redesign of frontend templates.
2. No database schema migration.
3. No broad refactor of all widgets/shortcodes in one sprint.

## Acceptance Criteria

1. Dashboard page loads without `$container.sortable is not a function`.
2. Log retention defaults are consistent across all call sites.
3. `show_booking_button` default type is consistent across mappings.
4. `status`, `default_payment`, `service_type` enum attributes have `values`.
5. `mhm_rentiva_dark_mode` save path is sanitizer-aligned.
6. SearchResultsWidget exposes at least 10 core controls and maps values consistently.
7. Full gate passes: `phpunit`, `phpcs`.

---

### Task 0: Re-baseline Audit Findings (Stale vs Active)

**Files:**
- Create: `docs/reports/2026-03-05-ecosystem-audit-rebaseline.md`
- Modify: none

**Step 1: Reproduce critical dashboard runtime error**
- Check script registration order and dependencies for `mhm-dashboard`.
- Confirm if `AssetManager` and `DashboardPage` both enqueue same handle with different deps.

**Step 2: Re-run focused static checks**
Run:
```bash
docker exec rentiva-dev-wpcli-1 bash -lc "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter SchemaParityTest"
```

**Step 3: Record which findings are still active**
- Mark each report item as `ACTIVE`, `PARTIALLY_FIXED`, or `STALE`.

**Step 4: Commit evidence doc**
```bash
git add docs/reports/2026-03-05-ecosystem-audit-rebaseline.md
git commit -m "docs(audit): add v4.20.3 ecosystem rebaseline findings"
```

---

### Task 1: P0 Critical Fix - Dashboard Sortable Dependency

**Files:**
- Modify: `src/Admin/Core/AssetManager.php`
- Modify: `src/Admin/Utilities/Dashboard/DashboardPage.php` (if needed to avoid duplicate handle conflict)
- Create: `tests/Integration/Admin/DashboardScriptDepsTest.php`

**Step 1: Write failing integration test**
- Assert `mhm-dashboard` has `jquery-ui-sortable` dependency on top-level dashboard screen.
- Assert only one authoritative registration path for `mhm-dashboard` handle.

**Step 2: Run RED test**
```bash
docker exec rentiva-dev-wpcli-1 bash -lc "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter DashboardScriptDepsTest"
```
Expected: FAIL.

**Step 3: Implement minimal fix**
- In `AssetManager` dashboard enqueue block, add `jquery-ui-sortable` to deps.
- Remove/guard duplicate conflicting registration path if it overrides deps.

**Step 4: Re-run targeted + full tests**
```bash
docker exec rentiva-dev-wpcli-1 bash -lc "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter DashboardScriptDepsTest"
docker exec rentiva-dev-wpcli-1 bash -lc "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage"
```

**Step 5: Commit**
```bash
git add src/Admin/Core/AssetManager.php src/Admin/Utilities/Dashboard/DashboardPage.php tests/Integration/Admin/DashboardScriptDepsTest.php
git commit -m "fix(admin): ensure dashboard sortable dependency is always loaded"
```

---

### Task 2: P1 High - Log Retention Default Parity (30 vs 90)

**Files:**
- Modify: `src/Admin/Actions/Actions.php`
- Modify: `src/Admin/Utilities/Actions/Actions.php`
- Modify: `src/Admin/PostTypes/Maintenance/LogRetention.php`
- Modify: `src/Admin/Settings/Groups/LogsSettings.php` (only if final chosen default changes)
- Create: `tests/Admin/Settings/LogRetentionDefaultParityTest.php`

**Step 1: Write failing test for default parity**
- Assert all runtime fallbacks for `mhm_rentiva_log_retention_days` resolve to one canonical default.

**Step 2: Run RED test**
```bash
docker exec rentiva-dev-wpcli-1 bash -lc "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter LogRetentionDefaultParityTest"
```

**Step 3: Implement fix**
- Replace hardcoded `90` fallbacks with `LogsSettings::get_log_retention_days()` or canonical `30` fallback everywhere.

**Step 4: Run tests**
```bash
docker exec rentiva-dev-wpcli-1 bash -lc "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter LogRetentionDefaultParityTest"
```

**Step 5: Commit**
```bash
git add src/Admin/Actions/Actions.php src/Admin/Utilities/Actions/Actions.php src/Admin/PostTypes/Maintenance/LogRetention.php src/Admin/Settings/Groups/LogsSettings.php tests/Admin/Settings/LogRetentionDefaultParityTest.php
git commit -m "fix(settings): unify log retention default across runtime paths"
```

---

### Task 3: P1 High - Register and Sanitize Addon Settings Properly

**Files:**
- Modify: `src/Admin/Addons/AddonSettings.php`
- Modify: `src/Admin/Settings/Core/SettingsCore.php`
- Modify: `src/Admin/Settings/Core/SettingsSanitizer.php`
- Create: `tests/Admin/Settings/AddonSettingsRegistrationTest.php`

**Step 1: Write failing test**
- Assert `mhm_rentiva_addon_settings` is registered in settings lifecycle and sanitized via central sanitizer path.

**Step 2: Run RED test**
```bash
docker exec rentiva-dev-wpcli-1 bash -lc "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter AddonSettingsRegistrationTest"
```

**Step 3: Implement minimal registration/sanitization wiring**
- Keep existing page UX; ensure persistence path is centrally managed and validated.

**Step 4: Run tests**
```bash
docker exec rentiva-dev-wpcli-1 bash -lc "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter AddonSettingsRegistrationTest"
```

**Step 5: Commit**
```bash
git add src/Admin/Addons/AddonSettings.php src/Admin/Settings/Core/SettingsCore.php src/Admin/Settings/Core/SettingsSanitizer.php tests/Admin/Settings/AddonSettingsRegistrationTest.php
git commit -m "fix(settings): register addon settings in central settings lifecycle"
```

---

### Task 4: P1 High - Canonicalize `show_booking_button` Type and Alias Behavior

**Files:**
- Modify: `src/Core/Attribute/AllowlistRegistry.php`
- Modify: `tests/Core/Attribute/SchemaParityTest.php`
- Create: `tests/Core/Attribute/TagMappingDefaultTypeTest.php`

**Step 1: Write failing tests**
- Assert `show_booking_button` defaults are consistent type (`'1'/'0'` string) across all relevant tag mappings.
- Assert canonical alias precedence (avoid ambiguous dual keys for same semantic option).

**Step 2: Run RED tests**
```bash
docker exec rentiva-dev-wpcli-1 bash -lc "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter TagMappingDefaultTypeTest"
```

**Step 3: Implement minimal mapping cleanup**
- Normalize only conflicting mappings now; avoid wide registry rewrite.

**Step 4: Run tests**
```bash
docker exec rentiva-dev-wpcli-1 bash -lc "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter 'SchemaParityTest|TagMappingDefaultTypeTest'"
```

**Step 5: Commit**
```bash
git add src/Core/Attribute/AllowlistRegistry.php tests/Core/Attribute/SchemaParityTest.php tests/Core/Attribute/TagMappingDefaultTypeTest.php
git commit -m "fix(cam): normalize show_booking_button defaults across tag mappings"
```

---

### Task 5: P2 Medium - Add Missing Enum `values` for Three Attributes

**Files:**
- Modify: `src/Core/Attribute/AllowlistRegistry.php`
- Modify: `tests/Core/Attribute/SchemaParityTest.php`

**Step 1: Write/update failing test**
- Existing enum-values assertions should fail for `status`, `default_payment`, `service_type` if values absent.

**Step 2: Run RED**
```bash
docker exec rentiva-dev-wpcli-1 bash -lc "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter SchemaParityTest::test_enum_attributes_have_values"
```

**Step 3: Implement enum values**
- Define explicit allowed value sets for the 3 attributes.

**Step 4: Run tests**
```bash
docker exec rentiva-dev-wpcli-1 bash -lc "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter SchemaParityTest"
```

**Step 5: Commit**
```bash
git add src/Core/Attribute/AllowlistRegistry.php tests/Core/Attribute/SchemaParityTest.php
git commit -m "fix(cam): add missing enum values for status and payment/service attributes"
```

---

### Task 6: P2 Medium - Route Dark Mode Save Through Central Sanitizer

**Files:**
- Modify: `src/Admin/Settings/Core/SettingsCore.php`
- Modify: `src/Admin/Settings/Core/SettingsSanitizer.php`
- Create: `tests/Admin/Settings/DarkModeAjaxSanitizationTest.php`

**Step 1: Write failing test**
- Assert AJAX dark-mode save accepts only allowed values and persists through sanitizer-compatible flow.

**Step 2: Run RED**
```bash
docker exec rentiva-dev-wpcli-1 bash -lc "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter DarkModeAjaxSanitizationTest"
```

**Step 3: Implement fix**
- Replace direct `update_option` bypass behavior with sanitizer-aware storage update.

**Step 4: Run tests**
```bash
docker exec rentiva-dev-wpcli-1 bash -lc "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter 'DarkModeAjaxSanitizationTest|SettingsSanitizerTest'"
```

**Step 5: Commit**
```bash
git add src/Admin/Settings/Core/SettingsCore.php src/Admin/Settings/Core/SettingsSanitizer.php tests/Admin/Settings/DarkModeAjaxSanitizationTest.php
git commit -m "fix(settings): sanitize dark mode ajax updates via central rules"
```

---

### Task 7: P2 Medium - Expand SearchResultsWidget Control Coverage

**Files:**
- Modify: `src/Admin/Frontend/Widgets/Elementor/SearchResultsWidget.php`
- Modify: `src/Admin/Frontend/Widgets/Base/ElementorWidgetBase.php` (if reusable yes/no→1/0 helper needed)
- Create: `tests/Admin/Frontend/Widgets/Elementor/SearchResultsWidgetCoverageTest.php`

**Step 1: Write failing test**
- Assert at least 10 core shortcode attributes are exposed and mapped correctly.

**Step 2: Run RED**
```bash
docker exec rentiva-dev-wpcli-1 bash -lc "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter SearchResultsWidgetCoverageTest"
```

**Step 3: Implement minimal control expansion**
- Add core controls: `limit`, `orderby`, `order`, `show_pagination`, `show_sorting`, `show_favorite_button`, `show_compare_button`, `show_booking_button`, `layout`, `show_price`.
- Normalize switchers to shortcode-friendly values via one shared conversion method.

**Step 4: Run tests**
```bash
docker exec rentiva-dev-wpcli-1 bash -lc "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter SearchResultsWidgetCoverageTest"
```

**Step 5: Commit**
```bash
git add src/Admin/Frontend/Widgets/Elementor/SearchResultsWidget.php src/Admin/Frontend/Widgets/Base/ElementorWidgetBase.php tests/Admin/Frontend/Widgets/Elementor/SearchResultsWidgetCoverageTest.php
git commit -m "feat(elementor): expand SearchResults widget attribute coverage"
```

---

### Task 8: Low-Risk Cleanup + Documentation

**Files:**
- Modify: `src/Core/Attribute/KeyNormalizer.php`
- Modify: `src/Blocks/BlockRegistry.php`
- Modify/Create: docs for CAM pipeline behavior (e.g. `docs/SHORTCODES.md` or `docs/architecture/cam.md`)
- Modify: `src/Admin/Frontend/Shortcodes/HomePoc.php` (WP_DEBUG guard or disable in production)

**Step 1: Document camelCase→snake_case normalization contract**
- Add concise inline docs and architecture note.

**Step 2: Guard or remove POC shortcode for production**
- Restrict registration in non-debug environments.

**Step 3: Verify no behavior regressions**
```bash
docker exec rentiva-dev-wpcli-1 bash -lc "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage"
docker exec rentiva-dev-wpcli-1 bash -lc "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpcs --standard=phpcs.xml"
```

**Step 4: Commit**
```bash
git add src/Core/Attribute/KeyNormalizer.php src/Blocks/BlockRegistry.php src/Admin/Frontend/Shortcodes/HomePoc.php docs
git commit -m "docs(core): document CAM normalization and lock POC shortcode in production"
```

---

### Task 9: Final Gate + Release Readiness

**Run in order:**
```bash
docker exec rentiva-dev-wpcli-1 bash -lc "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage"
docker exec rentiva-dev-wpcli-1 bash -lc "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpcs --standard=phpcs.xml"
docker exec rentiva-dev-wpcli-1 bash -lc "cd /var/www/html/wp-content/plugins/mhm-rentiva && export COMPOSER_PROCESS_TIMEOUT=0 && composer check-release"
```

If all pass:
- Update changelog for v4.20.x remediation release.
- Prepare deployment notes for dashboard/runtime fix and settings parity fixes.

---

## Recommended Execution Order

1. Task 0 (re-baseline)
2. Task 1 (critical runtime)
3. Task 2 + Task 3 + Task 4 (high priority)
4. Task 5 + Task 6 + Task 7 (medium)
5. Task 8 (low-risk cleanup)
6. Task 9 (final gate)

## Risk Notes

1. `mhm-dashboard` handle is currently enqueued from multiple places; fix must avoid handle collision side effects.
2. Allowlist changes can affect shortcode/block behavior; keep changes minimal and test-backed.
3. Settings lifecycle changes can impact backward compatibility; preserve option names and stored shapes.
