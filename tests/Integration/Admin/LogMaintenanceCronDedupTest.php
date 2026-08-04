<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Plugin;
use ReflectionClass;
use WP_UnitTestCase;

/**
 * WP.org T8 fix wave, group C (arch-Important-2): Plugin.php registered TWO
 * daily crons that both purged the exact same mhmrentiva_app_log posts,
 * gated by the exact same two settings (mhmrentiva_log_cleanup_enabled /
 * mhmrentiva_log_retention_days) -- LogRetention's
 * `mhmrentiva_log_purge_event` (via wp_delete_post(), bounded per run) and
 * LogMaintenanceScheduler's `mhmrentiva_daily_log_cleanup` (via one
 * unbounded raw multi-table DELETE, AdvancedLogger::cleanup_old_logs()).
 * Duplicated work, not a distinct feature: LogMaintenanceScheduler and
 * AdvancedLogger::cleanup_old_logs() are deleted, and LogRetention is kept
 * as the sole actor.
 *
 * @covers \MHMRentiva\Plugin::initialize_core_services
 */
final class LogMaintenanceCronDedupTest extends WP_UnitTestCase
{
    private const RETIRED_HOOK = 'mhmrentiva_daily_log_cleanup';

    public function tearDown(): void
    {
        wp_clear_scheduled_hook(self::RETIRED_HOOK);
        parent::tearDown();
    }

    public function test_log_maintenance_scheduler_class_no_longer_exists(): void
    {
        $this->assertFalse(
            class_exists('MHMRentiva\Admin\Core\Utilities\LogMaintenanceScheduler'),
            'LogMaintenanceScheduler must be gone -- its cron duplicated LogRetention\'s.'
        );
    }

    public function test_advanced_logger_cleanup_old_logs_no_longer_exists(): void
    {
        $this->assertFalse(
            method_exists('MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger', 'cleanup_old_logs'),
            'AdvancedLogger::cleanup_old_logs() had exactly one caller (LogMaintenanceScheduler), now deleted.'
        );
    }

    /**
     * Self-heal: an install upgrading from a version where
     * LogMaintenanceScheduler was still active may already have
     * `mhmrentiva_daily_log_cleanup` sitting in wp_cron. Plugin's
     * initialize_core_services() -- run unconditionally, every request, the
     * same place LogRetention/EmailLogRetention's own maybe_schedule() calls
     * live -- must clear it, not merely stop re-scheduling it.
     */
    public function test_a_lingering_retired_cron_is_cleared_on_the_next_request(): void
    {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::RETIRED_HOOK);
        $this->assertNotFalse(
            wp_next_scheduled(self::RETIRED_HOOK),
            'Premise: the retired hook is scheduled, simulating an install that upgraded from an older Lite.'
        );

        $this->invoke_initialize_core_services();

        $this->assertFalse(
            wp_next_scheduled(self::RETIRED_HOOK),
            'initialize_core_services() must clear a lingering mhmrentiva_daily_log_cleanup schedule.'
        );
    }

    /**
     * And the cheap common case: nothing scheduled, nothing to clear --
     * wp_clear_scheduled_hook() must not even be reached (it is a no-op
     * either way, but the guard is what keeps this check as cheap per
     * request as LogRetention's own maybe_schedule() pattern).
     */
    public function test_no_lingering_cron_is_a_silent_no_op(): void
    {
        wp_clear_scheduled_hook(self::RETIRED_HOOK);
        $this->assertFalse(wp_next_scheduled(self::RETIRED_HOOK), 'Premise: nothing scheduled.');

        $this->invoke_initialize_core_services();

        $this->assertFalse(wp_next_scheduled(self::RETIRED_HOOK));
    }

    /**
     * Calls the private Plugin::initialize_core_services() on a fresh
     * instance built without running the constructor (which would register
     * a full second copy of every plugin hook against the shared $wp_filter
     * global). is_class_available()/the self-heal block need no constructed
     * state, only class_exists() and WP's own cron functions.
     */
    private function invoke_initialize_core_services(): void
    {
        $reflection = new ReflectionClass(Plugin::class);
        $instance   = $reflection->newInstanceWithoutConstructor();
        $method     = $reflection->getMethod('initialize_core_services');
        $method->setAccessible(true);
        $method->invoke($instance);
    }
}
