<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use WP_UnitTestCase;

/**
 * Tests for SettingsSanitizer system tab.
 *
 * This file used to cover the security half of the tab too — login attempts,
 * lockout duration, rate limits, the protection checkboxes. Those keys are gone
 * along with the Settings -> Security screen that wrote them: none was ever read
 * by anything, so the sanitizer was clamping values into rows no code consulted.
 * `DeadSecuritySettingKeysTest` covers removing them from existing installs.
 */
class SettingsSanitizerSystemTabTest extends WP_UnitTestCase
{
    private function sanitize_system(array $fields): array
    {
        $input = array_merge(['current_active_tab' => 'system'], $fields);
        return SettingsSanitizer::sanitize($input);
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

    /**
     * The removed screen must not come back through the sanitizer: a key it
     * still accepted would recreate the row the migration deletes.
     */
    public function test_the_removed_security_keys_are_not_sanitized_back_in()
    {
        $result = $this->sanitize_system([
            'mhm_rentiva_brute_force_protection' => '1',
            'mhm_rentiva_max_login_attempts'     => '10',
            'mhm_rentiva_rate_limit_enabled'     => '1',
            'mhm_rentiva_ip_whitelist'           => '1.2.3.4',
        ]);

        foreach ([
            'mhm_rentiva_brute_force_protection',
            'mhm_rentiva_max_login_attempts',
            'mhm_rentiva_rate_limit_enabled',
            'mhm_rentiva_ip_whitelist',
        ] as $key) {
            $this->assertArrayNotHasKey(
                $key,
                $result,
                $key . ' is still accepted; the removed Security tab would write it again.'
            );
        }
    }
}
