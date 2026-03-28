<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use WP_UnitTestCase;

/**
 * Tests for SettingsSanitizer booking tab.
 *
 * Calls SettingsSanitizer::sanitize() with current_active_tab='booking'.
 */
class SettingsSanitizerBookingTabTest extends WP_UnitTestCase
{
    private function sanitize_booking(array $fields): array
    {
        $input = array_merge(['current_active_tab' => 'booking'], $fields);
        return SettingsSanitizer::sanitize($input);
    }

    // cancellation_deadline_hours — min=1, max=168, default=24

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

    // payment_deadline_minutes — min=0, max=1440, default=30

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

    // Checkbox fields

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

    // default_rental_days — min=1, max=365, default=1

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
