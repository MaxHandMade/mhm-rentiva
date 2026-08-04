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
 *
 * The log_level/log_cleanup_enabled/log_retention_days/debug_mode arms are
 * live again: LogsSettings::register() renders their fields on the 'system'
 * tab (see LogsSettingsLiveViaSystemTabTest), and this is their write path.
 */
class SettingsSanitizerSystemTabTest extends WP_UnitTestCase
{
    private function sanitize_system(array $fields): array
    {
        $input = array_merge(['current_active_tab' => 'system'], $fields);
        return SettingsSanitizer::sanitize($input);
    }

    /**
     * The four log/debug fields must be sanitized and returned, validated
     * against the same bounds LogsSettings::register() declares for them.
     */
    public function test_the_log_keys_are_sanitized_and_returned()
    {
        $result = $this->sanitize_system([
            'mhmrentiva_log_level'           => 'debug',
            'mhmrentiva_log_cleanup_enabled' => '1',
            'mhmrentiva_log_retention_days'  => '90',
            'mhmrentiva_debug_mode'          => '1',
        ]);

        $this->assertSame('debug', $result['mhmrentiva_log_level']);
        $this->assertSame('1', $result['mhmrentiva_log_cleanup_enabled']);
        $this->assertSame(90, $result['mhmrentiva_log_retention_days']);
        $this->assertSame('1', $result['mhmrentiva_debug_mode']);
    }

    /**
     * An invalid log level and an out-of-range retention value must be
     * clamped/rejected rather than stored as submitted.
     */
    public function test_the_log_keys_reject_out_of_bounds_input()
    {
        $result = $this->sanitize_system([
            'mhmrentiva_log_level'           => 'not_a_real_level',
            'mhmrentiva_log_cleanup_enabled' => 'not_a_real_value',
            'mhmrentiva_log_retention_days'  => '99999',
            'mhmrentiva_debug_mode'          => 'not_a_real_value',
        ]);

        $this->assertSame('error', $result['mhmrentiva_log_level'], 'An unrecognised level must fall back to the safe default.');
        $this->assertSame('0', $result['mhmrentiva_log_cleanup_enabled'], 'A non-"1" value must sanitize to the boolean-off string.');
        $this->assertSame(365, $result['mhmrentiva_log_retention_days'], 'Retention must clamp to its declared 365-day ceiling.');
        $this->assertSame('0', $result['mhmrentiva_debug_mode']);
    }

    /**
     * An absent/unchecked checkbox must sanitize to the safe '0' -- a
     * never-submitted form (fresh install) must not silently enable
     * cleanup or debug mode.
     */
    public function test_absent_log_checkboxes_default_to_off()
    {
        $result = $this->sanitize_system([
            'mhmrentiva_log_level'          => 'error',
            'mhmrentiva_log_retention_days' => '30',
        ]);

        $this->assertSame('0', $result['mhmrentiva_log_cleanup_enabled']);
        $this->assertSame('0', $result['mhmrentiva_debug_mode']);
    }

    /**
     * The removed screen must not come back through the sanitizer: a key it
     * still accepted would recreate the row the migration deletes.
     */
    public function test_the_removed_security_keys_are_not_sanitized_back_in()
    {
        $result = $this->sanitize_system([
            'mhmrentiva_brute_force_protection' => '1',
            'mhmrentiva_max_login_attempts'     => '10',
            'mhmrentiva_rate_limit_enabled'     => '1',
            'mhmrentiva_ip_whitelist'           => '1.2.3.4',
        ]);

        foreach ([
            'mhmrentiva_brute_force_protection',
            'mhmrentiva_max_login_attempts',
            'mhmrentiva_rate_limit_enabled',
            'mhmrentiva_ip_whitelist',
        ] as $key) {
            $this->assertArrayNotHasKey(
                $key,
                $result,
                $key . ' is still accepted; the removed Security tab would write it again.'
            );
        }
    }
}
