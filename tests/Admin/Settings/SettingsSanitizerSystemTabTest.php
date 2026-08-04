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
 * T8 Görev 10c-A (K5-F3): the log_level/log_cleanup_enabled/log_retention_days/
 * debug_mode arms are gone the same way -- LogsSettings::register()'s field
 * wiring (their only input path) was deleted as an orphan (task-10a-endpoint-
 * table.md D4/F3). Their own dedicated validation tests below are replaced
 * with a "not sanitized back in" test mirroring
 * test_the_removed_security_keys_are_not_sanitized_back_in()'s own shape.
 * The 4 option READS these fed stay live at their safe defaults -- unaffected,
 * since AdvancedLogger/LogRetention/LogMaintenanceScheduler read them via
 * SettingsCore::get() directly, never through this sanitizer.
 */
class SettingsSanitizerSystemTabTest extends WP_UnitTestCase
{
    private function sanitize_system(array $fields): array
    {
        $input = array_merge(['current_active_tab' => 'system'], $fields);
        return SettingsSanitizer::sanitize($input);
    }

    /**
     * The deleted field wiring must not come back through the sanitizer: a
     * key it still accepted would recreate the wired-but-unreachable shape
     * K5-F3 removed.
     */
    public function test_the_removed_log_keys_are_not_sanitized_back_in()
    {
        $result = $this->sanitize_system([
            'mhmrentiva_log_level'           => 'debug',
            'mhmrentiva_log_cleanup_enabled' => '1',
            'mhmrentiva_log_retention_days'  => '90',
            'mhmrentiva_debug_mode'          => '1',
        ]);

        foreach ([
            'mhmrentiva_log_level',
            'mhmrentiva_log_cleanup_enabled',
            'mhmrentiva_log_retention_days',
            'mhmrentiva_debug_mode',
        ] as $key) {
            $this->assertArrayNotHasKey(
                $key,
                $result,
                $key . ' is still accepted; LogsSettings\' deleted field wiring would be reachable again.'
            );
        }
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
