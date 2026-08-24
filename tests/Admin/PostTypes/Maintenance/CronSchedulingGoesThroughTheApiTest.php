<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\PostTypes\Maintenance;

use MHMRentiva\Admin\PostTypes\Maintenance\AutoCancel;
use MHMRentiva\Admin\PostTypes\Maintenance\AutoComplete;
use WP_UnitTestCase;

/**
 * Both maintenance sweeps must be scheduled through WordPress, not written
 * into the cron option behind its back.
 *
 * Slice 5, Minor debt M4. AutoCancel::direct_schedule_event() and
 * AutoComplete's equivalent built the cron array by hand and saved it with
 * _set_cron_array(). Their stated reason -- "bypasses wp_schedule_event's
 * schedule validation ... to avoid the invalid_schedule error" -- is
 * contradicted by the lines immediately above each call, which register the
 * cron_schedules filter and then assert the schedule is present in
 * wp_get_schedules(). wp_schedule_event() consults that same
 * wp_get_schedules(), so by the time the hand-rolled path ran, the validation
 * it was avoiding would have passed.
 *
 * The cost of avoiding it was not stylistic. wp-includes/cron.php fires
 * 'pre_schedule_event' (:287) and 'schedule_event' (:305) inside
 * wp_schedule_event(); a direct _set_cron_array() fires neither. Any install
 * that replaces cron storage or dispatch by short-circuiting
 * 'pre_schedule_event' -- Cavalcade and the external-runner family do exactly
 * this -- therefore never learned these two events existed. They were written
 * into a `cron` option that such a site does not read, so the deadline sweep
 * and the auto-complete sweep silently never ran there.
 *
 * This test asserts the seam, not the storage: it stands where an intercepting
 * host stands and requires that it be told.
 */
final class CronSchedulingGoesThroughTheApiTest extends WP_UnitTestCase
{
    /** @var array<int, string> Hooks seen by the pre_schedule_event filter. */
    private array $intercepted = array();

    protected function setUp(): void
    {
        parent::setUp();

        $this->intercepted = array();

        // Stand exactly where Cavalcade stands: short-circuit the scheduling
        // API and record what it was asked to schedule. Returning true tells
        // wp_schedule_event() the event was handled elsewhere, which is what an
        // external runner does.
        add_filter(
            'pre_schedule_event',
            function ($pre, $event) {
                if (is_object($event) && isset($event->hook)) {
                    $this->intercepted[] = (string) $event->hook;
                }

                return true;
            },
            10,
            2
        );
    }

    private function clear(string $hook): void
    {
        wp_clear_scheduled_hook($hook);
    }

    public function test_auto_cancel_schedules_through_wp_schedule_event(): void
    {
        $this->clear(AutoCancel::EVENT);

        AutoCancel::maybe_schedule();

        $this->assertContains(
            AutoCancel::EVENT,
            $this->intercepted,
            'AutoCancel wrote its event straight into the cron array, so a host that owns cron scheduling was'
                . ' never told the deadline sweep exists -- and on that host it never runs.'
        );
    }

    public function test_auto_complete_schedules_through_wp_schedule_event(): void
    {
        $this->clear(AutoComplete::EVENT);

        AutoComplete::maybe_schedule();

        $this->assertContains(
            AutoComplete::EVENT,
            $this->intercepted,
            'AutoComplete wrote its event straight into the cron array, so a host that owns cron scheduling'
                . ' was never told the auto-complete sweep exists -- and on that host it never runs.'
        );
    }
}
