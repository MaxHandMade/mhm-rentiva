# T9 Settings Sanitizer Tests Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task.

**Goal:** PHPUnit testleriyle `SettingsSanitizer` sınıfının tab bazlı sanitizasyon mantığını ve public yardımcı metodlarını doğrulamak.

**Architecture:** `SettingsSanitizer` sınıfı tek bir `sanitize($input)` entry point'i üzerinden çalışır; `$input['current_active_tab']` değerine göre PHP 8 `match()` ile ilgili private metoda yönlendirir. Public static metodlar (`sanitize_dark_mode_option`, `safe_text`, `sanitize_addon_settings_option`) doğrudan çağrılabilir. Tab testleri `sanitize()` üzerinden entegrasyon testi olarak yazılır.

**Tech Stack:** PHPUnit 9, WP_UnitTestCase, Docker (`rentiva-dev-wpcli-1`)

---

## Kapsam

| Dosya | Ne test eder |
|-------|-------------|
| `SettingsSanitizerPublicTest.php` | `sanitize_dark_mode_option`, `safe_text` — public static, pure unit |
| `SettingsSanitizerBookingTabTest.php` | booking tab: deadline clamp, checkbox, default_rental_days |
| `SettingsSanitizerVehicleTabTest.php` | vehicle tab: URL slug, sort enum, min/max clamp, tax clamp |
| `SettingsSanitizerSystemTabTest.php` | system tab: login attempts clamp, log_level enum, rate limits |

**Mevcut coverage:** `tests/Admin/Settings/SettingsSanitizerTest.php` — 2 test, yalnızca `Sanitizer::text_field_safe` (farklı sınıf, alakasız)

---

## Önemli Notlar

- `tests/` dizini public GitHub repo'da gitignored — **commit atılmaz**, dosyalar local-only
- `SettingsSanitizer::sanitize()` içinde `get_option('mhm_rentiva_settings')` çağrılır — WP_UnitTestCase'de boş array döner, bu beklenen davranış
- `SettingsCore::get_defaults()` bootstrap ile zaten yüklü — ayrıca register() çağırmaya gerek yok
- Sanitizer namespace: `MHMRentiva\Admin\Settings\Core\SettingsSanitizer`

---

## Çalıştırma Komutları

```bash
# Tek test sınıfı:
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter <TestClassName> 2>&1 | tail -15"

# Tüm suite:
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --colors 2>&1 | tail -5"

# PHPCS:
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && composer phpcs 2>&1 | tail -5"
```

---

### Task 1: SettingsSanitizer Public Methods Test

**File:**
- Create: `tests/Admin/Settings/SettingsSanitizerPublicTest.php`

**Step 1: Create the test file**

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use WP_UnitTestCase;

/**
 * Tests for SettingsSanitizer public static methods.
 * These are pure unit tests — no DB interaction.
 */
class SettingsSanitizerPublicTest extends WP_UnitTestCase
{
    // -------------------------------------------------------------------------
    // sanitize_dark_mode_option
    // -------------------------------------------------------------------------

    public function test_dark_mode_auto_is_default_for_empty_value()
    {
        $this->assertSame('auto', SettingsSanitizer::sanitize_dark_mode_option(''));
    }

    public function test_dark_mode_returns_auto_for_unknown_value()
    {
        $this->assertSame('auto', SettingsSanitizer::sanitize_dark_mode_option('rainbow'));
    }

    public function test_dark_mode_truthy_values_map_to_dark()
    {
        foreach (['1', 'on', 'yes', 'true', 'dark'] as $value) {
            $this->assertSame('dark', SettingsSanitizer::sanitize_dark_mode_option($value), "Failed for value: $value");
        }
    }

    public function test_dark_mode_falsy_values_map_to_light()
    {
        foreach (['0', 'off', 'no', 'false', 'light'] as $value) {
            $this->assertSame('light', SettingsSanitizer::sanitize_dark_mode_option($value), "Failed for value: $value");
        }
    }

    public function test_dark_mode_auto_maps_to_auto()
    {
        $this->assertSame('auto', SettingsSanitizer::sanitize_dark_mode_option('auto'));
    }

    public function test_dark_mode_custom_default_returned_for_invalid_input()
    {
        $this->assertSame('light', SettingsSanitizer::sanitize_dark_mode_option('', 'light'));
    }

    // -------------------------------------------------------------------------
    // safe_text
    // -------------------------------------------------------------------------

    public function test_safe_text_strips_html_tags()
    {
        $result = SettingsSanitizer::safe_text('<script>alert("xss")</script>Hello');
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('Hello', $result);
    }

    public function test_safe_text_returns_empty_string_for_null()
    {
        $this->assertSame('', SettingsSanitizer::safe_text(null));
    }

    public function test_safe_text_returns_empty_string_for_array()
    {
        $this->assertSame('', SettingsSanitizer::safe_text(['foo', 'bar']));
    }

    public function test_safe_text_trims_whitespace()
    {
        $this->assertSame('hello', SettingsSanitizer::safe_text('  hello  '));
    }

    public function test_safe_text_returns_empty_for_empty_string()
    {
        $this->assertSame('', SettingsSanitizer::safe_text(''));
    }
}
```

**Step 2: Run the test**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter SettingsSanitizerPublicTest 2>&1 | tail -10"
```

Expected: `OK (11 tests, ...)`

**Step 3: No commit** (tests/ is gitignored in public repo)

---

### Task 2: Booking Tab Sanitization Test

**File:**
- Create: `tests/Admin/Settings/SettingsSanitizerBookingTabTest.php`

**Step 1: Create the test file**

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use WP_UnitTestCase;

/**
 * Tests for SettingsSanitizer booking tab.
 *
 * Calls SettingsSanitizer::sanitize() with current_active_tab='booking'.
 * Uses merge-over-DB-values approach — only keys in input are asserted.
 */
class SettingsSanitizerBookingTabTest extends WP_UnitTestCase
{
    private function sanitize_booking(array $fields): array
    {
        $input = array_merge(['current_active_tab' => 'booking'], $fields);
        return SettingsSanitizer::sanitize($input);
    }

    // -------------------------------------------------------------------------
    // cancellation_deadline_hours — min=1, max=168, default=24
    // -------------------------------------------------------------------------

    public function test_cancellation_deadline_accepts_valid_value()
    {
        $result = $this->sanitize_booking(['mhm_rentiva_booking_cancellation_deadline_hours' => '48']);
        $this->assertSame(48, $result['mhm_rentiva_booking_cancellation_deadline_hours']);
    }

    public function test_cancellation_deadline_clamps_below_min()
    {
        $result = $this->sanitize_booking(['mhm_rentiva_booking_cancellation_deadline_hours' => '0']);
        $this->assertSame(1, $result['mhm_rentiva_booking_cancellation_deadline_hours']);
    }

    public function test_cancellation_deadline_clamps_above_max()
    {
        $result = $this->sanitize_booking(['mhm_rentiva_booking_cancellation_deadline_hours' => '999']);
        $this->assertSame(168, $result['mhm_rentiva_booking_cancellation_deadline_hours']);
    }

    public function test_cancellation_deadline_uses_default_when_absent()
    {
        $result = $this->sanitize_booking([]);
        $this->assertSame(24, $result['mhm_rentiva_booking_cancellation_deadline_hours']);
    }

    // -------------------------------------------------------------------------
    // payment_deadline_minutes — min=0, max=1440, default=30
    // -------------------------------------------------------------------------

    public function test_payment_deadline_accepts_valid_value()
    {
        $result = $this->sanitize_booking(['mhm_rentiva_booking_payment_deadline_minutes' => '60']);
        $this->assertSame(60, $result['mhm_rentiva_booking_payment_deadline_minutes']);
    }

    public function test_payment_deadline_clamps_above_max()
    {
        $result = $this->sanitize_booking(['mhm_rentiva_booking_payment_deadline_minutes' => '9999']);
        $this->assertSame(1440, $result['mhm_rentiva_booking_payment_deadline_minutes']);
    }

    public function test_payment_deadline_allows_zero()
    {
        $result = $this->sanitize_booking(['mhm_rentiva_booking_payment_deadline_minutes' => '0']);
        $this->assertSame(0, $result['mhm_rentiva_booking_payment_deadline_minutes']);
    }

    // -------------------------------------------------------------------------
    // Checkbox fields
    // -------------------------------------------------------------------------

    public function test_auto_cancel_enabled_truthy_value()
    {
        $result = $this->sanitize_booking(['mhm_rentiva_booking_auto_cancel_enabled' => '1']);
        $this->assertSame('1', $result['mhm_rentiva_booking_auto_cancel_enabled']);
    }

    public function test_auto_cancel_enabled_absent_returns_zero_string()
    {
        $result = $this->sanitize_booking([]);
        $this->assertSame('0', $result['mhm_rentiva_booking_auto_cancel_enabled']);
    }

    // -------------------------------------------------------------------------
    // default_rental_days — min=1, max=365, default=1
    // -------------------------------------------------------------------------

    public function test_default_rental_days_accepts_valid_value()
    {
        $result = $this->sanitize_booking(['mhm_rentiva_default_rental_days' => '7']);
        $this->assertSame(7, $result['mhm_rentiva_default_rental_days']);
    }

    public function test_default_rental_days_clamps_below_min()
    {
        $result = $this->sanitize_booking(['mhm_rentiva_default_rental_days' => '0']);
        $this->assertSame(1, $result['mhm_rentiva_default_rental_days']);
    }
}
```

**Step 2: Run the test**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter SettingsSanitizerBookingTabTest 2>&1 | tail -10"
```

Expected: `OK (11 tests, ...)`

**Step 3: No commit** (tests/ is gitignored)

---

### Task 3: Vehicle Tab Sanitization Test

**File:**
- Create: `tests/Admin/Settings/SettingsSanitizerVehicleTabTest.php`

**Step 1: Create the test file**

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use WP_UnitTestCase;

/**
 * Tests for SettingsSanitizer vehicle management tab.
 */
class SettingsSanitizerVehicleTabTest extends WP_UnitTestCase
{
    private function sanitize_vehicle(array $fields): array
    {
        $input = array_merge(['current_active_tab' => 'vehicle'], $fields);
        return SettingsSanitizer::sanitize($input);
    }

    // -------------------------------------------------------------------------
    // vehicle_url_base — sanitize_title(), empty → 'vehicle'
    // -------------------------------------------------------------------------

    public function test_url_base_is_slugified()
    {
        $result = $this->sanitize_vehicle(['mhm_rentiva_vehicle_url_base' => 'My Vehicles!']);
        $this->assertSame('my-vehicles', $result['mhm_rentiva_vehicle_url_base']);
    }

    public function test_url_base_empty_falls_back_to_vehicle()
    {
        $result = $this->sanitize_vehicle(['mhm_rentiva_vehicle_url_base' => '']);
        $this->assertSame('vehicle', $result['mhm_rentiva_vehicle_url_base']);
    }

    // -------------------------------------------------------------------------
    // vehicle_default_sort — enum: price_asc|price_desc|name_asc|name_desc|year_desc|year_asc
    // -------------------------------------------------------------------------

    public function test_sort_accepts_valid_enum_value()
    {
        $result = $this->sanitize_vehicle(['mhm_rentiva_vehicle_default_sort' => 'name_desc']);
        $this->assertSame('name_desc', $result['mhm_rentiva_vehicle_default_sort']);
    }

    public function test_sort_invalid_value_falls_back_to_price_asc()
    {
        $result = $this->sanitize_vehicle(['mhm_rentiva_vehicle_default_sort' => 'totally_invalid']);
        $this->assertSame('price_asc', $result['mhm_rentiva_vehicle_default_sort']);
    }

    // -------------------------------------------------------------------------
    // vehicle_min_rental_days — min=1, max=365, default=1
    // -------------------------------------------------------------------------

    public function test_min_rental_days_accepts_valid_value()
    {
        $result = $this->sanitize_vehicle(['mhm_rentiva_vehicle_min_rental_days' => '3']);
        $this->assertSame(3, $result['mhm_rentiva_vehicle_min_rental_days']);
    }

    public function test_min_rental_days_clamps_below_min()
    {
        $result = $this->sanitize_vehicle(['mhm_rentiva_vehicle_min_rental_days' => '0']);
        $this->assertSame(1, $result['mhm_rentiva_vehicle_min_rental_days']);
    }

    public function test_min_rental_days_clamps_above_max()
    {
        $result = $this->sanitize_vehicle(['mhm_rentiva_vehicle_min_rental_days' => '999']);
        $this->assertSame(365, $result['mhm_rentiva_vehicle_min_rental_days']);
    }

    // -------------------------------------------------------------------------
    // vehicle_tax_rate — clamp 0-100
    // -------------------------------------------------------------------------

    public function test_tax_rate_accepts_valid_value()
    {
        $result = $this->sanitize_vehicle(['mhm_rentiva_vehicle_tax_rate' => '18']);
        $this->assertEqualsWithDelta(18.0, $result['mhm_rentiva_vehicle_tax_rate'], 0.001);
    }

    public function test_tax_rate_clamps_negative_to_zero()
    {
        $result = $this->sanitize_vehicle(['mhm_rentiva_vehicle_tax_rate' => '-5']);
        $this->assertEqualsWithDelta(0.0, $result['mhm_rentiva_vehicle_tax_rate'], 0.001);
    }

    public function test_tax_rate_clamps_above_100()
    {
        $result = $this->sanitize_vehicle(['mhm_rentiva_vehicle_tax_rate' => '150']);
        $this->assertEqualsWithDelta(100.0, $result['mhm_rentiva_vehicle_tax_rate'], 0.001);
    }

    // -------------------------------------------------------------------------
    // vehicle_base_price — min enforced at 0.1
    // -------------------------------------------------------------------------

    public function test_base_price_negative_is_floored_to_minimum()
    {
        $result = $this->sanitize_vehicle(['mhm_rentiva_vehicle_base_price' => '-10']);
        $this->assertGreaterThanOrEqual(0.1, $result['mhm_rentiva_vehicle_base_price']);
    }
}
```

**Step 2: Run the test**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter SettingsSanitizerVehicleTabTest 2>&1 | tail -10"
```

Expected: `OK (11 tests, ...)`

**Step 3: No commit** (tests/ is gitignored)

---

### Task 4: System/Security Tab Sanitization Test

**File:**
- Create: `tests/Admin/Settings/SettingsSanitizerSystemTabTest.php`

**Step 1: Create the test file**

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use WP_UnitTestCase;

/**
 * Tests for SettingsSanitizer system/security tab.
 */
class SettingsSanitizerSystemTabTest extends WP_UnitTestCase
{
    private function sanitize_system(array $fields): array
    {
        $input = array_merge(['current_active_tab' => 'system'], $fields);
        return SettingsSanitizer::sanitize($input);
    }

    // -------------------------------------------------------------------------
    // max_login_attempts — min=3, max=20, default=5
    // -------------------------------------------------------------------------

    public function test_login_attempts_accepts_valid_value()
    {
        $result = $this->sanitize_system(['mhm_rentiva_max_login_attempts' => '10']);
        $this->assertSame(10, $result['mhm_rentiva_max_login_attempts']);
    }

    public function test_login_attempts_clamps_below_min()
    {
        $result = $this->sanitize_system(['mhm_rentiva_max_login_attempts' => '1']);
        $this->assertSame(3, $result['mhm_rentiva_max_login_attempts']);
    }

    public function test_login_attempts_clamps_above_max()
    {
        $result = $this->sanitize_system(['mhm_rentiva_max_login_attempts' => '100']);
        $this->assertSame(20, $result['mhm_rentiva_max_login_attempts']);
    }

    public function test_login_attempts_uses_default_when_absent()
    {
        $result = $this->sanitize_system([]);
        $this->assertSame(5, $result['mhm_rentiva_max_login_attempts']);
    }

    // -------------------------------------------------------------------------
    // login_lockout_duration — min=5, max=1440, default=30
    // -------------------------------------------------------------------------

    public function test_lockout_duration_accepts_valid_value()
    {
        $result = $this->sanitize_system(['mhm_rentiva_login_lockout_duration' => '60']);
        $this->assertSame(60, $result['mhm_rentiva_login_lockout_duration']);
    }

    public function test_lockout_duration_clamps_below_min()
    {
        $result = $this->sanitize_system(['mhm_rentiva_login_lockout_duration' => '1']);
        $this->assertSame(5, $result['mhm_rentiva_login_lockout_duration']);
    }

    public function test_lockout_duration_clamps_above_max()
    {
        $result = $this->sanitize_system(['mhm_rentiva_login_lockout_duration' => '9999']);
        $this->assertSame(1440, $result['mhm_rentiva_login_lockout_duration']);
    }

    // -------------------------------------------------------------------------
    // log_level — enum: error|warning|info|debug, default=error
    // -------------------------------------------------------------------------

    public function test_log_level_accepts_valid_enum_values()
    {
        foreach (['error', 'warning', 'info', 'debug'] as $level) {
            $result = $this->sanitize_system(['mhm_rentiva_log_level' => $level]);
            $this->assertSame($level, $result['mhm_rentiva_log_level'], "Failed for level: $level");
        }
    }

    public function test_log_level_invalid_value_falls_back_to_error()
    {
        $result = $this->sanitize_system(['mhm_rentiva_log_level' => 'verbose']);
        $this->assertSame('error', $result['mhm_rentiva_log_level']);
    }

    // -------------------------------------------------------------------------
    // rate_limit_booking_per_minute — min=1, max=100, default=5
    // -------------------------------------------------------------------------

    public function test_rate_limit_booking_accepts_valid_value()
    {
        $result = $this->sanitize_system(['mhm_rentiva_rate_limit_booking_per_minute' => '10']);
        $this->assertSame(10, $result['mhm_rentiva_rate_limit_booking_per_minute']);
    }

    public function test_rate_limit_booking_clamps_above_max()
    {
        $result = $this->sanitize_system(['mhm_rentiva_rate_limit_booking_per_minute' => '999']);
        $this->assertSame(100, $result['mhm_rentiva_rate_limit_booking_per_minute']);
    }

    // -------------------------------------------------------------------------
    // Security checkboxes
    // -------------------------------------------------------------------------

    public function test_brute_force_protection_enabled()
    {
        $result = $this->sanitize_system(['mhm_rentiva_brute_force_protection' => '1']);
        $this->assertSame('1', $result['mhm_rentiva_brute_force_protection']);
    }

    public function test_security_checkbox_absent_returns_zero_string()
    {
        $result = $this->sanitize_system([]);
        $this->assertSame('0', $result['mhm_rentiva_brute_force_protection']);
    }
}
```

**Step 2: Run the test**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter SettingsSanitizerSystemTabTest 2>&1 | tail -10"
```

Expected: `OK (13 tests, ...)`

**Step 3: No commit** (tests/ is gitignored)

---

### Task 5: Final Gate

**Step 1: Full PHPUnit suite**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --colors 2>&1 | tail -5"
```

Expected: `OK (...)` — mevcut 517 üzerine ~46 yeni test = ~563 test

**Step 2: PHPCS**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && composer phpcs 2>&1 | tail -5"
```

Expected: No errors.

**Step 3: git status kontrol**

```bash
cd c:/projects/rentiva-dev/plugins/mhm-rentiva && git status
```

Test dosyaları gitignored olduğundan working tree temiz olmalı.

---

## Completion Criteria

- [ ] 4 test dosyası oluşturuldu: `SettingsSanitizerPublicTest`, `SettingsSanitizerBookingTabTest`, `SettingsSanitizerVehicleTabTest`, `SettingsSanitizerSystemTabTest`
- [ ] ~46 yeni test — tümü yeşil
- [ ] Full PHPUnit suite geçiyor (0 failure, 0 regression)
- [ ] PHPCS temiz
- [ ] Git working tree temiz (test dosyaları gitignored)

## T10 İçin Notlar

T9 tamamlandıktan sonra T10 final audit raporunda şunlar belgelenir:

- **SettingsSanitizer test coverage genişlemesi:** 2 test → ~48 test
- **Kalan boşluklar (kapsam dışı):** email tab (50+ key), transfer tab, comments tab, frontend labels tab
- **Açık bug:** `rentiva_contact` shortcode `type` attribute CAM pipeline'dan geçmiyor (ContactFormTest'te belgelenmiş)
- **T4 açık bulgu:** block.json'da `defaultDays`, `minDays`, `maxDays` için default değer eksik; `limit` string/int tip uyumsuzluğu
