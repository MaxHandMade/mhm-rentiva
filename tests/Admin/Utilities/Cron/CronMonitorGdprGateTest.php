<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Utilities\Cron;

use MHMRentiva\Admin\Utilities\Cron\CronMonitor;
use WP_UnitTestCase;

/**
 * The Cron Monitor must not advertise Pro-only crons (license validation,
 * licence-server check-in, GDPR data-retention) on a Lite-only install.
 *
 * Task A9c seam inversion: CronMonitor::get_all_cron_jobs() no longer
 * hardcodes these descriptions gated on Mode::canUseGdpr() / a class_exists()
 * check for Pro's license-manager class. It now filters
 * `mhmrentiva_cron_descriptions`, and only Pro's CronExtensions subscriber
 * (absent from this tree) would add them back. This tree has no Pro classes,
 * so the filter's default (Lite's own cron map) applies unchanged.
 *
 * @covers \MHMRentiva\Admin\Utilities\Cron\CronMonitor::get_all_cron_jobs
 */
final class CronMonitorGdprGateTest extends WP_UnitTestCase
{
    public function test_premise_this_tree_has_no_pro_cron_extensions(): void
    {
        $this->assertFalse(
            class_exists('\MHMRentiva\Pro\Extensions\CronExtensions'),
            'Premise failed: Pro CronExtensions is present in the Lite test tree.'
        );
    }

    public function test_data_retention_cron_absent_without_pro(): void
    {
        $hooks = array_column(CronMonitor::get_all_cron_jobs(), 'hook');

        $this->assertNotContains(
            'mhmrentiva_data_retention_cleanup',
            $hooks,
            'The Cron Monitor listed the Pro data-retention cron without Pro contributing it.'
        );
    }

    public function test_license_crons_absent_without_pro(): void
    {
        $hooks = array_column(CronMonitor::get_all_cron_jobs(), 'hook');

        $this->assertNotContains('mhmrentiva_license_daily', $hooks);
        $this->assertNotContains('mhmrentiva_instance_checkin', $hooks);
    }

    public function test_a_subscriber_can_add_a_cron_description(): void
    {
        $add_description = static function (array $descriptions): array {
            $descriptions['mhmrentiva_data_retention_cleanup'] = array(
                'name'        => 'Data Retention Cleanup',
                'description' => 'Cleans up expired data according to retention policies',
            );
            return $descriptions;
        };

        add_filter('mhmrentiva_cron_descriptions', $add_description);

        $hooks = array_column(CronMonitor::get_all_cron_jobs(), 'hook');

        remove_filter('mhmrentiva_cron_descriptions', $add_description);

        $this->assertContains(
            'mhmrentiva_data_retention_cleanup',
            $hooks,
            'A subscriber on mhmrentiva_cron_descriptions must be able to add a cron description.'
        );
    }

    public function test_a_subscriber_can_add_a_known_cron_hook_for_manual_run(): void
    {
        $admin_id = self::factory()->user->create(array('role' => 'administrator'));
        wp_set_current_user($admin_id);

        $add_hook = static function (array $hooks): array {
            $hooks[] = 'mhmrentiva_license_daily';
            return $hooks;
        };

        add_filter('mhmrentiva_known_cron_hooks', $add_hook);

        // Not registered, so run_cron_job() must fail past the whitelist
        // check with the "not registered" message, not the "invalid cron
        // hook" message -- proving the hook passed the whitelist.
        $result = CronMonitor::run_cron_job('mhmrentiva_license_daily');

        remove_filter('mhmrentiva_known_cron_hooks', $add_hook);
        wp_set_current_user(0);

        $this->assertFalse($result['success']);
        $this->assertStringNotContainsString('Invalid cron hook', $result['message']);
    }
}
