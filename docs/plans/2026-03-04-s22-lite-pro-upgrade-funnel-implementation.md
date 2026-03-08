# S22 Lite-to-Pro Upgrade Funnel Optimization Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Increase Lite→Pro conversion surface quality with deterministic, testable funnel telemetry and consistent Pro CTA messaging without changing core business behavior.

**Architecture:** Reuse existing licensing UI surfaces (`LicenseAdmin`, `ProFeatureNotice`, `Mode` comparison table) and introduce a small, write-safe telemetry collector in licensing domain. Track only admin-side funnel events through explicit hooks and a single persistence option, with daily counters and strict sanitization.

**Tech Stack:** WordPress admin PHP, existing licensing classes (`Mode`, `LicenseAdmin`, `ProFeatureNotice`), PHPUnit (integration + unit), PHPCS, Plugin Check (dist), Docker WP-CLI authoritative environment.

---

## Scope

1. Add deterministic telemetry contract for Lite funnel events.
2. Standardize Pro CTA copy/placement across license and notice surfaces.
3. Add tests that lock event counting and CTA rendering behavior.
4. Keep Lite/Pro runtime isolation and migration behavior untouched.

## Non-Goals

1. No license API protocol changes.
2. No pricing/plan model changes.
3. No frontend booking UX redesign.
4. No changes to payout/business logic.

## Risk Map

1. **Overcount risk** from duplicate hooks in same request.
   Mitigation: request-scope debounce key in telemetry collector.
2. **Security risk** from unsanitized query/action params.
   Mitigation: strict allowlist event names + nonce/capability checks where actions exist.
3. **Regression risk** in existing compliance gates.
   Mitigation: full Docker gate after each task group.

## Acceptance Criteria

1. Lite license page emits a deterministic "view" event once/request.
2. Pro CTA clicks emit deterministic "click" events through tracked endpoint/action.
3. Event storage remains bounded and schema-stable (daily counters map).
4. Existing boot isolation tests keep passing.
5. `phpunit`, `phpcs`, Plugin Check dist `TOTAL_ERROR=0`, `composer check-release` pass.

---

### Task 1: RED - Funnel Telemetry Contract Tests

**Files:**
- Create: `tests/Integration/Licensing/UpgradeFunnelTelemetryTest.php`
- Modify: `tests/bootstrap.php` (only if new test hook bootstrap is needed)

**Step 1: Write failing integration test for view event idempotency**

```php
public function test_lite_license_page_tracks_view_once_per_request(): void
{
    update_option('mhm_rentiva_license_state', array());

    do_action('mhm_rentiva_track_upgrade_funnel_event', 'license_page_view_lite');
    do_action('mhm_rentiva_track_upgrade_funnel_event', 'license_page_view_lite');

    $stats = get_option('mhm_rentiva_upgrade_funnel_stats', array());
    $today = gmdate('Y-m-d');

    $this->assertSame(1, (int) ($stats[$today]['license_page_view_lite'] ?? 0));
}
```

**Step 2: Write failing integration test for click event counting**

```php
public function test_upgrade_cta_click_increments_counter(): void
{
    do_action('mhm_rentiva_track_upgrade_funnel_event', 'upgrade_cta_click_license_page');

    $stats = get_option('mhm_rentiva_upgrade_funnel_stats', array());
    $today = gmdate('Y-m-d');

    $this->assertSame(1, (int) ($stats[$today]['upgrade_cta_click_license_page'] ?? 0));
}
```

**Step 3: Run targeted tests and verify RED**

Run:

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter UpgradeFunnelTelemetryTest"
```

Expected: FAIL (collector/hooks not implemented yet).

**Step 4: Commit RED tests**

```bash
git add tests/Integration/Licensing/UpgradeFunnelTelemetryTest.php
git commit -m "test(licensing): add RED upgrade funnel telemetry contract tests"
```

---

### Task 2: GREEN - Implement Upgrade Funnel Telemetry Collector

**Files:**
- Create: `src/Admin/Licensing/UpgradeFunnelTelemetry.php`
- Modify: `src/Plugin.php` (service registration)

**Step 1: Implement minimal collector with strict allowlist**

```php
final class UpgradeFunnelTelemetry {
    private const OPTION = 'mhm_rentiva_upgrade_funnel_stats';

    private const ALLOWED_EVENTS = array(
        'license_page_view_lite',
        'upgrade_cta_click_license_page',
        'upgrade_cta_click_pro_notice',
        'upgrade_cta_click_setup_wizard',
    );

    private static array $request_guard = array();

    public static function register(): void {
        add_action('mhm_rentiva_track_upgrade_funnel_event', array(self::class, 'track'), 10, 1);
    }

    public static function track(string $event): void {
        $event = sanitize_key($event);
        if (! in_array($event, self::ALLOWED_EVENTS, true)) {
            return;
        }

        $guard_key = $event . ':' . gmdate('Y-m-d');
        if (isset(self::$request_guard[$guard_key])) {
            return;
        }
        self::$request_guard[$guard_key] = true;

        $stats = get_option(self::OPTION, array());
        $date = gmdate('Y-m-d');
        $stats[$date] = is_array($stats[$date] ?? null) ? $stats[$date] : array();
        $stats[$date][$event] = (int) ($stats[$date][$event] ?? 0) + 1;

        update_option(self::OPTION, $stats, false);
    }
}
```

**Step 2: Wire collector registration in plugin init path**

- In `Plugin::initialize_services()` register collector once.

```php
if ($this->is_class_available('\MHMRentiva\Admin\Licensing\UpgradeFunnelTelemetry')) {
    \MHMRentiva\Admin\Licensing\UpgradeFunnelTelemetry::register();
}
```

**Step 3: Run targeted tests and verify GREEN**

Run:

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter UpgradeFunnelTelemetryTest"
```

Expected: PASS.

**Step 4: Commit GREEN implementation**

```bash
git add src/Admin/Licensing/UpgradeFunnelTelemetry.php src/Plugin.php
git commit -m "feat(licensing): add deterministic upgrade funnel telemetry collector"
```

---

### Task 3: CTA Surface Consistency (License + Notices + Setup)

**Files:**
- Modify: `src/Admin/Licensing/LicenseAdmin.php`
- Modify: `src/Admin/Core/ProFeatureNotice.php`
- Modify: `src/Admin/Setup/SetupWizard.php`

**Step 1: Emit page view event on Lite license page render**

- In `LicenseAdmin::render_page()` when `! $is_active`, call:

```php
do_action('mhm_rentiva_track_upgrade_funnel_event', 'license_page_view_lite');
```

**Step 2: Standardize upgrade CTA link labels/copy**

- Ensure wording stays consistent:
  - `Upgrade to Pro`
  - `Enter your license key`
- Preserve i18n translator comments for placeholders.

**Step 3: Add deterministic click tracking endpoint/action for admin CTA**

- Add `admin_post_mhm_rentiva_track_upgrade_cta` handler that:
  - checks capability (`manage_options`)
  - validates nonce
  - tracks allowed event
  - redirects to license page

**Step 4: Update CTA links to use tracking action URL**

- Replace plain `admin.php?page=mhm-rentiva-license` links in targeted surfaces with nonce-protected tracked URL builder.

**Step 5: Add targeted tests for CTA rendering + action behavior**

**Files:**
- Create: `tests/Integration/Licensing/UpgradeCtaSurfaceTest.php`

Tests:
1. License page in Lite contains tracked CTA.
2. Tracked action increments expected event.
3. Invalid event is ignored.

**Step 6: Run targeted tests**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter 'UpgradeCtaSurfaceTest|UpgradeFunnelTelemetryTest'"
```

Expected: PASS.

**Step 7: Commit**

```bash
git add src/Admin/Licensing/LicenseAdmin.php src/Admin/Core/ProFeatureNotice.php src/Admin/Setup/SetupWizard.php tests/Integration/Licensing/UpgradeCtaSurfaceTest.php
git commit -m "feat(licensing): standardize and track Lite-to-Pro CTA surfaces"
```

---

### Task 4: Comparison Table and Copy Contract Lock

**Files:**
- Modify: `src/Admin/Licensing/Mode.php`
- Create: `tests/Unit/Licensing/ComparisonTableContractTest.php`

**Step 1: Lock presence of critical Pro-only rows**

- Ensure comparison table contains deterministic rows:
  - `Vendor & Payout`
  - `Messaging System`
  - `Advanced Reports`

**Step 2: Write unit test for row existence + Lite/Pro values**

```php
public function test_comparison_contains_vendor_payout_pro_only_row(): void
{
    $rows = Mode::get_comparison_table_data();
    $row = $this->findByName($rows, 'Vendor & Payout');

    $this->assertSame('Not available', $row['lite']);
    $this->assertSame('Available', $row['pro']);
}
```

**Step 3: Run unit tests**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter ComparisonTableContractTest"
```

Expected: PASS.

**Step 4: Commit**

```bash
git add src/Admin/Licensing/Mode.php tests/Unit/Licensing/ComparisonTableContractTest.php
git commit -m "test(licensing): lock Lite-vs-Pro comparison contract rows"
```

---

### Task 5: Full Verification and Release Closure (S22)

**Files:**
- Modify: `changelog.json`
- Modify: `changelog-tr.json`
- Modify: `docs/internal/GOVERNANCE_LOG.md`

**Step 1: Full gate in Docker-authoritative flow**

Run:

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage"
vendor/bin/phpcs
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && wp plugin check mhm-rentiva-dist --slug=mhm-rentiva --allow-root --format=strict-json"
docker exec rentiva-dev-wpcli-1 bash -lc "cd /var/www/html/wp-content/plugins/mhm-rentiva && export COMPOSER_PROCESS_TIMEOUT=0 && composer check-release"
```

Expected:
1. PHPUnit PASS
2. PHPCS PASS
3. Plugin Check `TOTAL_ERROR=0`
4. Composer check-release PASS

**Step 2: Update release docs/changelog for `v4.20.8`**

- Add short EN/TR entries with no behavior break claims.

**Step 3: Commit + tag**

```bash
git add changelog.json changelog-tr.json docs/internal/GOVERNANCE_LOG.md
git commit -m "chore(release): s22 lite-pro upgrade funnel optimization closure"
git tag -a v4.20.8 -m "Lite-to-Pro Upgrade Funnel Optimization"
git push origin main --tags
```

---

## Rollback Strategy

1. If telemetry causes instability, disable registration hook in `Plugin::initialize_services()`.
2. Keep CTA links functional by falling back to direct license page URLs.
3. No schema migrations introduced in S22; rollback is code-only.

## Evidence Requirements

Store logs under:

- `docs/internal/evidence/s22_upgrade_funnel/`

Required files:
1. `targeted_telemetry_tests.log`
2. `full_phpunit.log`
3. `phpcs.log`
4. `plugin_check_strict_json.log`
5. `composer_check_release.log`
6. `run_summary.md`
