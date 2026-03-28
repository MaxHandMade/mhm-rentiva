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

    // max_login_attempts — min=3, max=20, default=5

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

    // login_lockout_duration — min=5, max=1440, default=30

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

    // log_level — enum: error|warning|info|debug, default=error

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

    // rate_limit_booking_per_minute — min=1, max=100, default=5

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

    // Security checkboxes

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
