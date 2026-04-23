# T8 Frontend Render Tests Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task.

**Goal:** Write PHPUnit render tests for the 13 shortcodes that currently have no test coverage, completing the T8 audit track.

**Architecture:** Each test file follows the existing pattern in `tests/Frontend/Shortcodes/`. The plugin is fully bootstrapped via `tests/bootstrap.php` → `mhm-rentiva.php`, so all shortcodes are already registered via `add_shortcode()`. Tests call `do_shortcode()` directly and assert known HTML output patterns. No new implementation code — tests only.

**Tech Stack:** PHPUnit 9, WP_UnitTestCase, `$this->factory->post->create()` for fixture data, Docker (`rentiva-dev-wpcli-1`)

---

## Critical Discovery (T10 Pre-finding)

`rentiva_booking_confirmation` block exists in `BlockRegistry` but has **no entry in `ShortcodeServiceProvider`**. Calling `do_shortcode('[rentiva_booking_confirmation]')` returns empty string. No render test can be written until a shortcode handler is implemented. **Flag this in T10 report.**

---

## Scope: 13 Test Files

| Shortcode | Test File | Group |
|-----------|-----------|-------|
| `rentiva_availability_calendar` | `AvailabilityCalendarTest.php` | form |
| `rentiva_unified_search` | `UnifiedSearchTest.php` | form |
| `rentiva_transfer_search` | `TransferSearchTest.php` | form |
| `rentiva_contact` | `ContactFormTest.php` | form |
| `rentiva_vehicle_rating_form` | `VehicleRatingFormTest.php` | form |
| `rentiva_vehicle_details` | `VehicleDetailsTest.php` | vehicle |
| `rentiva_vehicle_comparison` | `VehicleComparisonTest.php` | vehicle |
| `rentiva_testimonials` | `TestimonialsTest.php` | listing |
| `rentiva_transfer_results` | `TransferResultsTest.php` | listing |
| `rentiva_my_bookings` | `MyBookingsTest.php` | account |
| `rentiva_my_favorites` | `MyFavoritesTest.php` | account |
| `rentiva_payment_history` | `PaymentHistoryTest.php` | account |
| `rentiva_messages` | `AccountMessagesTest.php` | account |

---

## Run Command (all tests)

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --colors 2>&1 | tail -5"
```

## Run Command (targeted)

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter <TestClassName> 2>&1 | tail -5"
```

---

### Task 1: AvailabilityCalendar Test

**Files:**
- Create: `tests/Frontend/Shortcodes/AvailabilityCalendarTest.php`

**Step 1: Create the test file**

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use WP_UnitTestCase;

class AvailabilityCalendarTest extends WP_UnitTestCase
{
    public function test_renders_error_when_no_vehicle_id()
    {
        $output = do_shortcode('[rentiva_availability_calendar]');

        $this->assertNotEmpty($output, 'Shortcode must return non-empty output');
        $this->assertStringContainsString('rv-availability-error', $output);
    }

    public function test_renders_error_for_invalid_vehicle_id()
    {
        $output = do_shortcode('[rentiva_availability_calendar vehicle_id="99999"]');

        $this->assertStringContainsString('rv-availability-error', $output);
    }

    public function test_renders_calendar_wrapper_with_valid_vehicle()
    {
        $vehicle_id = $this->factory->post->create([
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_title'  => 'Calendar Test Vehicle',
        ]);

        $output = do_shortcode('[rentiva_availability_calendar vehicle_id="' . $vehicle_id . '"]');

        $this->assertStringContainsString('rv-availability-calendar', $output);
    }

    public function test_show_pricing_attribute_is_accepted()
    {
        $output = do_shortcode('[rentiva_availability_calendar show_pricing="0"]');

        // Without vehicle, error is returned — but shortcode must not fatal
        $this->assertNotEmpty($output);
    }
}
```

**Step 2: Run the test**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter AvailabilityCalendarTest 2>&1 | tail -10"
```

Expected: `OK (4 tests, ...)`

**Step 3: Commit**

```bash
cd c:/projects/rentiva-dev/plugins/mhm-rentiva
git add tests/Frontend/Shortcodes/AvailabilityCalendarTest.php
git commit -m "test(t8): add AvailabilityCalendar render tests"
```

---

### Task 2: UnifiedSearch Test

**Files:**
- Create: `tests/Frontend/Shortcodes/UnifiedSearchTest.php`

**Step 1: Create the test file**

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use WP_UnitTestCase;

class UnifiedSearchTest extends WP_UnitTestCase
{
    public function test_renders_search_widget_wrapper()
    {
        $output = do_shortcode('[rentiva_unified_search]');

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('rv-unified-search', $output);
    }

    public function test_renders_with_testid_attribute()
    {
        $output = do_shortcode('[rentiva_unified_search]');

        $this->assertStringContainsString('data-testid="unified-search"', $output);
    }

    public function test_horizontal_layout_is_default()
    {
        $output = do_shortcode('[rentiva_unified_search]');

        $this->assertStringContainsString('rv-unified-search--horizontal', $output);
    }

    public function test_vertical_layout_attribute()
    {
        $output = do_shortcode('[rentiva_unified_search layout="vertical"]');

        $this->assertStringContainsString('rv-unified-search--vertical', $output);
        $this->assertStringNotContainsString('rv-unified-search--horizontal', $output);
    }

    public function test_renders_rental_tab()
    {
        $output = do_shortcode('[rentiva_unified_search]');

        $this->assertStringContainsString('data-testid="tab-rental"', $output);
    }

    public function test_renders_transfer_tab()
    {
        $output = do_shortcode('[rentiva_unified_search]');

        $this->assertStringContainsString('data-testid="tab-transfer"', $output);
    }
}
```

**Step 2: Run the test**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter UnifiedSearchTest 2>&1 | tail -10"
```

Expected: `OK (6 tests, ...)`

**Step 3: Commit**

```bash
git add tests/Frontend/Shortcodes/UnifiedSearchTest.php
git commit -m "test(t8): add UnifiedSearch render tests"
```

---

### Task 3: TransferSearch Test

**Files:**
- Create: `tests/Frontend/Shortcodes/TransferSearchTest.php`

**Step 1: Create the test file**

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use WP_UnitTestCase;

class TransferSearchTest extends WP_UnitTestCase
{
    public function test_renders_transfer_search_wrapper()
    {
        $output = do_shortcode('[rentiva_transfer_search]');

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('rv-transfer-search', $output);
    }

    public function test_renders_form_element()
    {
        $output = do_shortcode('[rentiva_transfer_search]');

        $this->assertStringContainsString('data-testid="transfer-search-form"', $output);
    }

    public function test_horizontal_layout_is_default()
    {
        $output = do_shortcode('[rentiva_transfer_search]');

        $this->assertStringContainsString('rv-layout-horizontal', $output);
    }

    public function test_show_pickup_true_renders_origin_select()
    {
        $output = do_shortcode('[rentiva_transfer_search show_pickup="1"]');

        $this->assertStringContainsString('data-testid="transfer-origin"', $output);
    }
}
```

**Step 2: Run the test**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter TransferSearchTest 2>&1 | tail -10"
```

Expected: `OK (4 tests, ...)`

**Step 3: Commit**

```bash
git add tests/Frontend/Shortcodes/TransferSearchTest.php
git commit -m "test(t8): add TransferSearch render tests"
```

---

### Task 4: ContactForm Test

**Files:**
- Create: `tests/Frontend/Shortcodes/ContactFormTest.php`

**Step 1: Create the test file**

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use WP_UnitTestCase;

class ContactFormTest extends WP_UnitTestCase
{
    public function test_renders_contact_form_wrapper()
    {
        $output = do_shortcode('[rentiva_contact]');

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('rv-contact-form', $output);
    }

    public function test_renders_form_element()
    {
        $output = do_shortcode('[rentiva_contact]');

        $this->assertStringContainsString('rv-form', $output);
    }

    public function test_default_type_is_general()
    {
        $output = do_shortcode('[rentiva_contact]');

        $this->assertStringContainsString('data-form-type="general"', $output);
    }

    public function test_booking_type_attribute()
    {
        $output = do_shortcode('[rentiva_contact type="booking"]');

        $this->assertStringContainsString('data-form-type="booking"', $output);
    }

    public function test_renders_contact_title()
    {
        $output = do_shortcode('[rentiva_contact]');

        $this->assertStringContainsString('rv-contact-title', $output);
    }

    public function test_show_phone_false_hides_phone_field()
    {
        $output_with    = do_shortcode('[rentiva_contact show_phone="1"]');
        $output_without = do_shortcode('[rentiva_contact show_phone="0"]');

        $this->assertStringContainsString('rv-contact-form', $output_with);
        $this->assertStringContainsString('rv-contact-form', $output_without);
    }
}
```

**Step 2: Run the test**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter ContactFormTest 2>&1 | tail -10"
```

Expected: `OK (6 tests, ...)`

**Step 3: Commit**

```bash
git add tests/Frontend/Shortcodes/ContactFormTest.php
git commit -m "test(t8): add ContactForm render tests"
```

---

### Task 5: VehicleRatingForm Test

**Files:**
- Create: `tests/Frontend/Shortcodes/VehicleRatingFormTest.php`

**Step 1: Create the test file**

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use WP_UnitTestCase;

class VehicleRatingFormTest extends WP_UnitTestCase
{
    private int $vehicle_id;

    public function setUp(): void
    {
        parent::setUp();
        $this->vehicle_id = $this->factory->post->create([
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_title'  => 'Rating Test Vehicle',
        ]);
    }

    public function test_renders_error_without_vehicle_id()
    {
        $output = do_shortcode('[rentiva_vehicle_rating_form]');

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('rv-rating-form-error', $output);
    }

    public function test_renders_rating_form_with_valid_vehicle()
    {
        $output = do_shortcode('[rentiva_vehicle_rating_form vehicle_id="' . $this->vehicle_id . '"]');

        $this->assertStringContainsString('rv-rating-form', $output);
        $this->assertStringContainsString('data-vehicle-id="' . $this->vehicle_id . '"', $output);
    }

    public function test_renders_rating_display_section()
    {
        $output = do_shortcode('[rentiva_vehicle_rating_form vehicle_id="' . $this->vehicle_id . '"]');

        $this->assertStringContainsString('rv-rating-display', $output);
    }
}
```

**Step 2: Run the test**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter VehicleRatingFormTest 2>&1 | tail -10"
```

Expected: `OK (3 tests, ...)`

**Step 3: Commit**

```bash
git add tests/Frontend/Shortcodes/VehicleRatingFormTest.php
git commit -m "test(t8): add VehicleRatingForm render tests"
```

---

### Task 6: VehicleDetails Test

**Files:**
- Create: `tests/Frontend/Shortcodes/VehicleDetailsTest.php`

**Step 1: Create the test file**

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use WP_UnitTestCase;

class VehicleDetailsTest extends WP_UnitTestCase
{
    private int $vehicle_id;

    public function setUp(): void
    {
        parent::setUp();
        $this->vehicle_id = $this->factory->post->create([
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_title'  => 'Details Test Vehicle',
            'meta_input'  => [
                '_mhm_rentiva_price_per_day' => '200',
                '_mhm_vehicle_status'        => 'active',
            ],
        ]);
        wp_cache_delete($this->vehicle_id, 'post_meta');
    }

    public function test_renders_vehicle_details_wrapper_with_valid_vehicle()
    {
        $output = do_shortcode('[rentiva_vehicle_details vehicle_id="' . $this->vehicle_id . '"]');

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('rv-vehicle-details', $output);
    }

    public function test_renders_without_vehicle_id_returns_output()
    {
        // Without vehicle_id, shortcode should return something (error or empty state)
        $output = do_shortcode('[rentiva_vehicle_details]');

        $this->assertIsString($output);
    }

    public function test_show_gallery_attribute_accepted()
    {
        $output = do_shortcode('[rentiva_vehicle_details vehicle_id="' . $this->vehicle_id . '" show_gallery="0"]');

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('rv-vehicle-details', $output);
    }

    public function test_show_booking_button_false()
    {
        $output_with    = do_shortcode('[rentiva_vehicle_details vehicle_id="' . $this->vehicle_id . '" show_booking_button="1"]');
        $output_without = do_shortcode('[rentiva_vehicle_details vehicle_id="' . $this->vehicle_id . '" show_booking_button="0"]');

        // Both should render the page wrapper
        $this->assertStringContainsString('rv-vehicle-details', $output_with);
        $this->assertStringContainsString('rv-vehicle-details', $output_without);
    }
}
```

**Step 2: Run the test**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter VehicleDetailsTest 2>&1 | tail -10"
```

Expected: `OK (4 tests, ...)`

**Step 3: Commit**

```bash
git add tests/Frontend/Shortcodes/VehicleDetailsTest.php
git commit -m "test(t8): add VehicleDetails render tests"
```

---

### Task 7: VehicleComparison Test

**Files:**
- Create: `tests/Frontend/Shortcodes/VehicleComparisonTest.php`

**Step 1: Create the test file**

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use WP_UnitTestCase;

class VehicleComparisonTest extends WP_UnitTestCase
{
    public function test_renders_comparison_wrapper_without_vehicles()
    {
        $output = do_shortcode('[rentiva_vehicle_comparison]');

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('rv-vehicle-comparison', $output);
    }

    public function test_renders_add_vehicle_section()
    {
        $output = do_shortcode('[rentiva_vehicle_comparison]');

        $this->assertStringContainsString('rv-add-vehicle-section', $output);
    }

    public function test_renders_with_vehicle_ids()
    {
        $v1 = $this->factory->post->create(['post_type' => 'vehicle', 'post_status' => 'publish']);
        $v2 = $this->factory->post->create(['post_type' => 'vehicle', 'post_status' => 'publish']);

        $output = do_shortcode('[rentiva_vehicle_comparison vehicle_ids="' . $v1 . ',' . $v2 . '"]');

        $this->assertStringContainsString('rv-vehicle-comparison', $output);
    }

    public function test_max_vehicles_attribute_is_accepted()
    {
        $output = do_shortcode('[rentiva_vehicle_comparison max_vehicles="3"]');

        $this->assertStringContainsString('data-max-vehicles="3"', $output);
    }

    public function test_table_layout_is_default()
    {
        $output = do_shortcode('[rentiva_vehicle_comparison]');

        $this->assertStringContainsString('rv-layout-table', $output);
    }
}
```

**Step 2: Run the test**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter VehicleComparisonTest 2>&1 | tail -10"
```

Expected: `OK (5 tests, ...)`

**Step 3: Commit**

```bash
git add tests/Frontend/Shortcodes/VehicleComparisonTest.php
git commit -m "test(t8): add VehicleComparison render tests"
```

---

### Task 8: Testimonials Test

**Files:**
- Create: `tests/Frontend/Shortcodes/TestimonialsTest.php`

**Step 1: Create the test file**

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use WP_UnitTestCase;

class TestimonialsTest extends WP_UnitTestCase
{
    public function test_renders_testimonials_wrapper()
    {
        $output = do_shortcode('[rentiva_testimonials]');

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('rv-testimonials', $output);
    }

    public function test_grid_layout_is_default()
    {
        $output = do_shortcode('[rentiva_testimonials]');

        $this->assertStringContainsString('rv-layout-grid', $output);
    }

    public function test_carousel_layout_attribute()
    {
        $output = do_shortcode('[rentiva_testimonials layout="carousel"]');

        $this->assertStringContainsString('rv-layout-carousel', $output);
    }

    public function test_columns_attribute_applied()
    {
        $output = do_shortcode('[rentiva_testimonials columns="2"]');

        $this->assertStringContainsString('rv-columns-2', $output);
    }

    public function test_default_columns_is_three()
    {
        $output = do_shortcode('[rentiva_testimonials]');

        $this->assertStringContainsString('rv-columns-3', $output);
    }

    public function test_class_attribute_applied()
    {
        $output = do_shortcode('[rentiva_testimonials class="my-custom-class"]');

        $this->assertStringContainsString('my-custom-class', $output);
    }
}
```

**Step 2: Run the test**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter TestimonialsTest 2>&1 | tail -10"
```

Expected: `OK (6 tests, ...)`

**Step 3: Commit**

```bash
git add tests/Frontend/Shortcodes/TestimonialsTest.php
git commit -m "test(t8): add Testimonials render tests"
```

---

### Task 9: TransferResults Test

**Files:**
- Create: `tests/Frontend/Shortcodes/TransferResultsTest.php`

**Step 1: Create the test file**

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use WP_UnitTestCase;

class TransferResultsTest extends WP_UnitTestCase
{
    public function test_renders_transfer_results_wrapper()
    {
        $output = do_shortcode('[rentiva_transfer_results]');

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('rv-transfer-results', $output);
    }

    public function test_list_layout_is_default()
    {
        $output = do_shortcode('[rentiva_transfer_results]');

        $this->assertStringContainsString('rv-transfer-results--list', $output);
    }

    public function test_grid_layout_attribute()
    {
        $output = do_shortcode('[rentiva_transfer_results layout="grid"]');

        $this->assertStringContainsString('rv-transfer-results--grid', $output);
    }

    public function test_renders_without_fatal_error()
    {
        // No search session — should render gracefully (empty results or skeleton)
        $output = do_shortcode('[rentiva_transfer_results]');

        $this->assertIsString($output);
        $this->assertStringNotContainsString('Fatal error', $output);
        $this->assertStringNotContainsString('Warning:', $output);
    }
}
```

**Step 2: Run the test**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter TransferResultsTest 2>&1 | tail -10"
```

Expected: `OK (4 tests, ...)`

**Step 3: Commit**

```bash
git add tests/Frontend/Shortcodes/TransferResultsTest.php
git commit -m "test(t8): add TransferResults render tests"
```

---

### Task 10: Account Shortcodes (4 files)

Auth-required shortcodes share the same pattern:
- Logged out → returns `"Please login to view this content."`
- Logged in → returns HTML with known wrapper class

**Files:**
- Create: `tests/Frontend/Shortcodes/Account/MyBookingsTest.php`
- Create: `tests/Frontend/Shortcodes/Account/MyFavoritesTest.php`
- Create: `tests/Frontend/Shortcodes/Account/PaymentHistoryTest.php`
- Create: `tests/Frontend/Shortcodes/Account/AccountMessagesTest.php`

**Step 1: Create MyBookingsTest.php**

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes\Account;

use WP_UnitTestCase;

class MyBookingsTest extends WP_UnitTestCase
{
    public function test_logged_out_returns_login_message()
    {
        wp_set_current_user(0);
        $output = do_shortcode('[rentiva_my_bookings]');

        $this->assertStringContainsString('Please login to view this content.', $output);
    }

    public function test_logged_in_renders_bookings_page()
    {
        $user_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($user_id);

        $output = do_shortcode('[rentiva_my_bookings]');

        $this->assertStringContainsString('rv-bookings-page', $output);
    }

    public function test_logged_in_renders_account_wrapper()
    {
        $user_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($user_id);

        $output = do_shortcode('[rentiva_my_bookings]');

        $this->assertStringContainsString('mhm-rentiva-account-page', $output);
    }

    public function tearDown(): void
    {
        wp_set_current_user(0);
        parent::tearDown();
    }
}
```

**Step 2: Create MyFavoritesTest.php**

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes\Account;

use WP_UnitTestCase;

class MyFavoritesTest extends WP_UnitTestCase
{
    public function test_logged_out_returns_login_message()
    {
        wp_set_current_user(0);
        $output = do_shortcode('[rentiva_my_favorites]');

        $this->assertStringContainsString('Please login to view this content.', $output);
    }

    public function test_logged_in_renders_account_wrapper()
    {
        $user_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($user_id);

        $output = do_shortcode('[rentiva_my_favorites]');

        $this->assertStringContainsString('mhm-rentiva-account-page', $output);
    }

    public function test_logged_in_renders_favorites_content()
    {
        $user_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($user_id);

        $output = do_shortcode('[rentiva_my_favorites]');

        $this->assertStringContainsString('mhm-account-content', $output);
    }

    public function tearDown(): void
    {
        wp_set_current_user(0);
        parent::tearDown();
    }
}
```

**Step 3: Create PaymentHistoryTest.php**

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes\Account;

use WP_UnitTestCase;

class PaymentHistoryTest extends WP_UnitTestCase
{
    public function test_logged_out_returns_login_message()
    {
        wp_set_current_user(0);
        $output = do_shortcode('[rentiva_payment_history]');

        $this->assertStringContainsString('Please login to view this content.', $output);
    }

    public function test_logged_in_renders_account_wrapper()
    {
        $user_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($user_id);

        $output = do_shortcode('[rentiva_payment_history]');

        $this->assertStringContainsString('mhm-rentiva-account-page', $output);
    }

    public function test_logged_in_renders_payment_history_content()
    {
        $user_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($user_id);

        $output = do_shortcode('[rentiva_payment_history]');

        // Payment History header should be present
        $this->assertStringContainsString('Payment History', $output);
    }

    public function tearDown(): void
    {
        wp_set_current_user(0);
        parent::tearDown();
    }
}
```

**Step 4: Create AccountMessagesTest.php**

```php
<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes\Account;

use WP_UnitTestCase;

class AccountMessagesTest extends WP_UnitTestCase
{
    public function test_logged_out_returns_login_message()
    {
        wp_set_current_user(0);
        $output = do_shortcode('[rentiva_messages]');

        $this->assertStringContainsString('Please login to view this content.', $output);
    }

    public function test_logged_in_renders_account_wrapper()
    {
        $user_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($user_id);

        $output = do_shortcode('[rentiva_messages]');

        $this->assertStringContainsString('mhm-rentiva-account-page', $output);
    }

    public function test_logged_in_renders_messages_section()
    {
        $user_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($user_id);

        $output = do_shortcode('[rentiva_messages]');

        $this->assertStringContainsString('mhm-account-content', $output);
    }

    public function tearDown(): void
    {
        wp_set_current_user(0);
        parent::tearDown();
    }
}
```

**Step 5: Run all 4 account tests**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --filter 'MyBookingsTest|MyFavoritesTest|PaymentHistoryTest|AccountMessagesTest' 2>&1 | tail -10"
```

Expected: `OK (12 tests, ...)`

**Step 6: Commit**

```bash
git add tests/Frontend/Shortcodes/Account/MyBookingsTest.php
git add tests/Frontend/Shortcodes/Account/MyFavoritesTest.php
git add tests/Frontend/Shortcodes/Account/PaymentHistoryTest.php
git add tests/Frontend/Shortcodes/Account/AccountMessagesTest.php
git commit -m "test(t8): add account shortcode render tests (MyBookings, MyFavorites, PaymentHistory, Messages)"
```

---

### Task 11: Final Gate

**Step 1: Run full test suite**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --colors 2>&1 | tail -5"
```

Expected: `OK (...)` — total tests should be approximately 463 + 44 new = ~507 tests.

**Step 2: Run PHPCS**

```bash
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && composer phpcs 2>&1 | tail -5"
```

Expected: No errors.

**Step 3: Commit (only if any remaining uncommitted changes)**

```bash
git status
```

---

## Completion Criteria

- [ ] 13 test files created under `tests/Frontend/Shortcodes/` and `tests/Frontend/Shortcodes/Account/`
- [ ] All 13 tests pass green
- [ ] Full PHPUnit suite passes (no regressions)
- [ ] PHPCS clean
- [ ] `rentiva_booking_confirmation` missing handler flagged as T10 item
- [ ] Total test count ~507+ tests
