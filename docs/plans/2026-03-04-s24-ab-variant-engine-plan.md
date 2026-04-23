# S24 A/B Variant Engine Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add deterministic A/B variant assignment to Lite→Pro upgrade funnel and expose variant-level conversion metrics in the Growth dashboard.

**Architecture:** Keep collector/service/dashboard separation from S22/S23. Introduce a pure resolver (`AbVariantResolver`) for stable user-to-variant mapping, extend telemetry with an optional variant field, then aggregate and render variant breakdowns in dashboard.

**Tech Stack:** WordPress admin PHP, existing telemetry option (`mhm_rentiva_upgrade_funnel_stats`), PHPUnit integration + unit tests, PHPCS, Plugin Check (dist strict-json), Docker WP-CLI.

---

## Scope

1. Add deterministic A/B resolver (`variant_a` / `variant_b`) using user id hashing.
2. Add variant-aware telemetry tracking without DB migration.
3. Add variant breakdown aggregation in analytics service.
4. Render variant conversion table in Growth dashboard.

## Non-Goals

1. No random/session/cookie-based assignment.
2. No ML or bandit logic.
3. No frontend redesign.
4. No booking/financial/payout behavior changes.

## Acceptance Criteria

1. Same user always resolves to same variant in same code version.
2. Variant telemetry is recorded deterministically and remains bounded by daily map retention.
3. Dashboard shows variant A/B views, clicks, conversion.
4. Existing S22/S23 telemetry behavior remains backward compatible.
5. Full gate passes (`phpunit`, `phpcs`, Plugin Check dist `TOTAL_ERROR=0`, `composer check-release`).

---

## Data Contract

Store variant at event bucket level (no schema migration):

```php
[
  '2026-03-05' => [
    'license_page_view_lite' => [
      'total' => 12,
      'variant_a' => 7,
      'variant_b' => 5
    ],
    'upgrade_cta_click_license_page' => [
      'total' => 3,
      'variant_a' => 1,
      'variant_b' => 2
    ]
  ]
]
```

Backward compatibility:
- If existing value is integer, treat as legacy `total`.
- New writes should normalize to bucket array shape.

---

### Task 1: RED - A/B Resolver Contract Tests

**Files:**
- Create: `tests/Unit/Growth/AbVariantResolverTest.php`
- Create: `src/Admin/Growth/AbVariantResolver.php` (stub only after RED if needed)

**Tests:**
1. `test_same_user_id_always_gets_same_variant`
2. `test_variant_is_one_of_a_or_b`
3. `test_guest_user_falls_back_to_variant_a`

**Run (RED expected):**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter AbVariantResolverTest"
```

---

### Task 2: GREEN - Implement `AbVariantResolver`

**Files:**
- Create: `src/Admin/Growth/AbVariantResolver.php`

**Behavior:**
- `resolve_for_user_id(int $user_id): string`
- Variant rule: `(crc32((string) $user_id) % 2 === 0) ? 'variant_a' : 'variant_b'`
- `user_id <= 0` => `variant_a`

**Run:**
- Targeted resolver tests PASS.

---

### Task 3: RED - Variant Telemetry Contract Tests

**Files:**
- Modify: `tests/Integration/Licensing/UpgradeFunnelTelemetryTest.php`

**Tests:**
1. `track(event, variant)` increments both `total` and variant bucket.
2. Duplicate event in same request still debounced (`total` + variant increment only once).
3. Invalid variant is ignored (track `total` only).

**Run (RED expected):**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter UpgradeFunnelTelemetryTest"
```

---

### Task 4: GREEN - Extend Telemetry for Variant

**Files:**
- Modify: `src/Admin/Licensing/UpgradeFunnelTelemetry.php`

**Changes:**
- Track signature: `track(string $event, ?string $variant = null): void`
- Allow variants: `variant_a`, `variant_b`
- Normalize legacy integer event values into bucket array with `total`.
- Request guard key must include variant when present.

---

### Task 5: RED - Analytics Variant Breakdown Tests

**Files:**
- Modify/Create: `tests/Integration/Growth/FunnelAnalyticsServiceTest.php`

**Tests:**
1. Aggregates `views/clicks/conversion` per variant from mixed daily data.
2. Handles legacy non-variant records safely.
3. Empty data returns zeroed variant breakdown.

---

### Task 6: GREEN - Analytics Service Variant Aggregation

**Files:**
- Modify: `src/Admin/Growth/FunnelAnalyticsService.php`

**Add methods:**
- `get_variant_breakdown(int $days = 30): array`
- return shape:

```php
[
  'variant_a' => ['views' => 0, 'clicks' => 0, 'conversion_rate' => 0.0],
  'variant_b' => ['views' => 0, 'clicks' => 0, 'conversion_rate' => 0.0],
]
```

---

### Task 7: Dashboard Variant Surface

**Files:**
- Modify: `src/Admin/Growth/FunnelDashboard.php`
- Modify: `tests/Integration/Growth/FunnelDashboardPageTest.php`

**UI additions (MVP):**
- New table:
  - Variant
  - Views
  - Clicks
  - Conversion
- Show `variant_a` and `variant_b` rows.

---

### Task 8: Wire CTA Variant Source

**Files:**
- Modify: `src/Admin/Licensing/LicenseAdmin.php`
- Modify: `src/Admin/Core/ProFeatureNotice.php`
- Modify: `src/Admin/Setup/SetupWizard.php`

**Approach:**
- Resolve variant from current user id in admin context.
- Include variant in tracking call consistently for view/click events.

---

### Task 9: Full Gate + Release Prep

**Run in order:**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage"
vendor/bin/phpcs
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && wp plugin check mhm-rentiva-dist --slug=mhm-rentiva --allow-root --format=strict-json"
docker exec rentiva-dev-wpcli-1 bash -lc "cd /var/www/html/wp-content/plugins/mhm-rentiva && export COMPOSER_PROCESS_TIMEOUT=0 && composer check-release"
```

If all pass:
- Prepare `v4.21.0` changelog EN/TR (feature-level sprint).

