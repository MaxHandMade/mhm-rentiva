<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Utilities\Cron;

use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\Utilities\Cron\CronMonitor;
use WP_UnitTestCase;

/**
 * The Cron Monitor must not advertise the Pro data-retention cron on an unlicensed
 * site.
 *
 * The retention cleanup is a licensed GDPR feature and is only scheduled when
 * canUseGdpr(). It used to be listed unconditionally, so a Lite/unlicensed admin saw
 * "Data Retention Cleanup — not scheduled / idle" flagged with a warning, reading as
 * a fault when it was simply an unlicensed Pro job. This tree has no LicenseManager,
 * so canUseGdpr() is false — the unlicensed state.
 *
 * @covers \MHMRentiva\Admin\Utilities\Cron\CronMonitor::get_all_cron_jobs
 */
final class CronMonitorGdprGateTest extends WP_UnitTestCase
{
    public function test_premise_this_tree_is_unlicensed(): void
    {
        $this->assertFalse(Mode::canUseGdpr(), 'Premise failed: GDPR is licensed in the Lite test tree.');
    }

    public function test_data_retention_cron_absent_unlicensed(): void
    {
        $hooks = array_column(CronMonitor::get_all_cron_jobs(), 'hook');

        $this->assertNotContains(
            'mhm_data_retention_cleanup',
            $hooks,
            'The Cron Monitor listed the Pro data-retention cron on an unlicensed site.'
        );
    }
}
