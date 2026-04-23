# S23 Funnel Analytics Dashboard Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Expose S22 Lite-to-Pro telemetry as a read-only admin dashboard without changing telemetry collection or database schema.

**Architecture:** Add a dedicated read/aggregate service (`FunnelAnalyticsService`) and a separate admin page renderer (`FunnelDashboard`) under Rentiva admin menu. Keep strict separation: collector writes data, analytics service only reads and aggregates `mhm_rentiva_upgrade_funnel_stats`.

**Tech Stack:** WordPress admin PHP, existing option-based telemetry, PHPUnit integration tests, PHPCS, Plugin Check (dist strict-json), composer check-release.

---

## Scope

1. Add Growth -> Upgrade Funnel admin page.
2. Implement read-only analytics aggregation service for daily rows and totals.
3. Add contract tests for aggregation, conversion, and empty state.
4. Keep booking/financial/payout behavior untouched.

## Non-Goals

1. No DB migration.
2. No telemetry write-path changes.
3. No A/B engine.
4. No frontend visualization framework dependency (MVP: cards + table).

## Acceptance Criteria

1. `FunnelAnalyticsService` returns deterministic daily rows for last 30 days.
2. Conversion is calculated as `clicks / views` with safe zero handling.
3. Empty dataset returns zero totals and empty rows.
4. Upgrade funnel page is visible to `manage_options` users at slug `mhm-rentiva-growth-funnel`.
5. Full gates pass (`phpunit`, `phpcs`, Plugin Check dist `TOTAL_ERROR=0`, `composer check-release`).

---

### Task 1: RED - Funnel Analytics Service Contract Tests

**Files:**
- Create: `tests/Integration/Growth/FunnelAnalyticsServiceTest.php`

**Step 1: Write failing aggregation test**

- Seed `mhm_rentiva_upgrade_funnel_stats` with two recent days.
- Assert `get_last_30_days()` returns expected `views`, `clicks`, `conversion` per row.

**Step 2: Write failing conversion test**

- Seed one day with 10 views, 2 clicks.
- Assert `get_conversion_rate()` returns `0.2`.

**Step 3: Write failing empty dataset test**

- Delete option.
- Assert daily rows are empty and totals are zero.

**Step 4: Run targeted test (expect RED)**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter FunnelAnalyticsServiceTest"
```

Expected: FAIL (`Class "MHMRentiva\Admin\Growth\FunnelAnalyticsService" not found`).

---

### Task 2: GREEN - Implement Analytics Service

**Files:**
- Create: `src/Admin/Growth/FunnelAnalyticsService.php`

**Step 1: Implement read-only service**

- `get_last_30_days(): array`
- `get_daily_stats(int $days): array`
- `get_totals(int $days = 30): array`
- `get_conversion_rate(int $days = 30): float`

**Step 2: Keep deterministic behavior**

- UTC date keys (`Y-m-d`)
- clamp days to positive range
- safe integer casting
- safe zero division handling

**Step 3: Run targeted test (expect GREEN)**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter FunnelAnalyticsServiceTest"
```

---

### Task 3: Dashboard Surface (MVP)

**Files:**
- Create: `src/Admin/Growth/FunnelDashboard.php`
- Modify: `src/Admin/Utilities/Menu/Menu.php`
- Modify: `src/Plugin.php` (class availability wiring if required)

**Step 1: Register submenu**

- Parent: `mhm-rentiva`
- Title: `Growth`
- Page title: `Upgrade Funnel`
- Slug: `mhm-rentiva-growth-funnel`
- Capability: `manage_options`

**Step 2: Render cards + table**

- Summary cards: views, clicks, conversion
- Daily table: date, views, clicks, conversion
- Escape all output (`esc_html`, etc.)

**Step 3: Add minimal admin page test coverage**

- Ensure submenu slug is present in admin menu integration tests.

---

### Task 4: Full Gate + Release Prep

**Step 1: Full gate**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage"
vendor/bin/phpcs
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && wp plugin check mhm-rentiva-dist --slug=mhm-rentiva --allow-root --format=strict-json"
docker exec rentiva-dev-wpcli-1 bash -lc "cd /var/www/html/wp-content/plugins/mhm-rentiva && export COMPOSER_PROCESS_TIMEOUT=0 && composer check-release"
```

**Step 2: Changelog and tag update after gates pass**

- Add `v4.20.9` entries (EN/TR) with S23 dashboard scope.

