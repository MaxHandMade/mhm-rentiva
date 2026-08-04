<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger;
use MHMRentiva\Admin\PostTypes\Maintenance\LogRetention;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use MHMRentiva\Admin\Settings\Groups\LogsSettings;
use MHMRentiva\Admin\Settings\Groups\MaintenanceSettings;
use MHMRentiva\Admin\Settings\View\TabRendererRegistry;
use WP_UnitTestCase;

/**
 * WP.org T8 fix wave, group C (arch-Important-2): K5-F3 deleted
 * LogsSettings::register()'s field wiring because self::SECTION_LOGS was
 * unreachable, but left the 4 keys it fed (mhmrentiva_log_level,
 * mhmrentiva_log_cleanup_enabled, mhmrentiva_log_retention_days,
 * mhmrentiva_debug_mode) with live readers and no writer at all -- an
 * administrator could never again change, or even see, what log level or
 * retention was in effect. Applying the seasonal-switch precedent (Görev
 * 10c-B / MaintenanceSettings' own K5-F4 evidence-conflict): the readers
 * stay, they get a writer back. register() is restored verbatim to its
 * pre-K5-F3 shape and self::SECTION_LOGS is now named in the 'system' tab's
 * $sections list, the exact same reachability path
 * MaintenanceSettingsLiveViaSystemTabTest already proved for
 * MaintenanceSettings::SECTION_ID.
 *
 * @covers \MHMRentiva\Admin\Settings\Groups\LogsSettings
 */
final class LogsSettingsLiveViaSystemTabTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Populates $wp_settings_sections / $wp_settings_fields for the
        // 'system' tab's mhmrentiva_logs_section -- same is_admin()-gated-
        // bootstrap reason MaintenanceSettingsLiveViaSystemTabTest documents:
        // Plugin::initialize_admin_services() never runs in this suite's
        // shared bootstrap, so nothing registers these fields unless a test
        // does. MaintenanceSettings::register() is included so the whole
        // tab (not just this section) renders as it would in production.
        LogsSettings::register();
        MaintenanceSettings::register();
    }

    public function tearDown(): void
    {
        delete_option('mhmrentiva_settings');
        parent::tearDown();
    }

    private function render_live_system_tab(): string
    {
        $registry = new TabRendererRegistry();
        $renderer = $registry->get('system');
        $this->assertNotNull($renderer, 'Premise: the system tab renderer must be registered.');

        ob_start();
        $renderer->render();
        return (string) ob_get_clean();
    }

    public function test_all_four_log_fields_render_on_the_live_system_tab(): void
    {
        $html = $this->render_live_system_tab();

        foreach ([
            'mhmrentiva_log_level',
            'mhmrentiva_log_cleanup_enabled',
            'mhmrentiva_log_retention_days',
            'mhmrentiva_debug_mode',
        ] as $key) {
            $this->assertStringContainsString(
                'name="mhmrentiva_settings[' . $key . ']"',
                $html,
                $key . ' must render on the live System & Performance tab -- reached via the '
                . "tab's \$sections list (SECTION_LOGS), the same path MaintenanceSettings::SECTION_ID uses."
            );
        }

        $this->assertStringContainsString('Logs &amp; Debugging', $html, 'The section title must render.');
    }

    public function test_render_settings_section_does_not_exist(): void
    {
        // Mirrors MaintenanceSettingsLiveViaSystemTabTest's regression lock:
        // the fields render through the generic $sections mechanism, so a
        // dedicated render_settings_section() wrapper would be a second,
        // unreachable, zero-caller copy -- do not reintroduce it.
        $this->assertFalse(method_exists(LogsSettings::class, 'render_settings_section'));
    }

    /**
     * Save -> read end-to-end for the retention-days key (and, as a
     * corroborating premise, the other three): a POST through the real
     * sanitizer must land in the option, and SettingsCore::get() must read
     * back exactly what was saved.
     */
    public function test_save_then_read_round_trip_for_all_four_keys(): void
    {
        $sanitized = SettingsSanitizer::sanitize([
            'current_active_tab'             => 'system',
            'mhmrentiva_log_level'            => 'warning',
            'mhmrentiva_log_cleanup_enabled'  => '1',
            'mhmrentiva_log_retention_days'   => '90',
            'mhmrentiva_debug_mode'           => '1',
        ]);
        update_option('mhmrentiva_settings', $sanitized);

        $this->assertSame(90, (int) SettingsCore::get('mhmrentiva_log_retention_days', 30));
        $this->assertSame('warning', SettingsCore::get('mhmrentiva_log_level', 'error'));
        $this->assertSame('1', SettingsCore::get('mhmrentiva_log_cleanup_enabled', '0'));
        $this->assertSame('1', SettingsCore::get('mhmrentiva_debug_mode', '0'));
    }

    /**
     * Reconciliation lock (arch-Important-2): every surviving call site's
     * OWN literal fallback must agree with LogsSettings' central default for
     * the same key -- on a fresh install (no saved value at all), it must
     * not matter which literal a given reader happens to pass, because
     * SettingsCore::get()'s central defaults array always wins first. This
     * is the general form of the specific contradiction the audit found
     * ('1' at LogRetention.php:44 vs '0' at the now-deleted
     * LogMaintenanceScheduler.php:46 for mhmrentiva_log_cleanup_enabled):
     * proving it for all 4 keys, not just the one that happened to have two
     * cron readers.
     */
    public function test_every_reader_fallback_agrees_with_the_central_default(): void
    {
        delete_option('mhmrentiva_settings');
        $defaults = LogsSettings::get_default_settings();

        // mhmrentiva_log_level: AdvancedLogger.php's own fallback is 'error'.
        $this->assertSame($defaults['mhmrentiva_log_level'], SettingsCore::get('mhmrentiva_log_level', 'error'));
        $this->assertSame($defaults['mhmrentiva_log_level'], SettingsCore::get('mhmrentiva_log_level', 'THIS-LITERAL-MUST-NOT-WIN'));

        // mhmrentiva_debug_mode: AdvancedLogger.php's own fallback is '0'.
        $this->assertSame($defaults['mhmrentiva_debug_mode'], SettingsCore::get('mhmrentiva_debug_mode', '0'));
        $this->assertSame($defaults['mhmrentiva_debug_mode'], SettingsCore::get('mhmrentiva_debug_mode', '1'));

        // mhmrentiva_log_cleanup_enabled: LogRetention.php's own fallback is '1'
        // (the historically contradictory second reader, LogMaintenanceScheduler,
        // is deleted -- see LogMaintenanceCronDedupTest).
        $this->assertSame($defaults['mhmrentiva_log_cleanup_enabled'], SettingsCore::get('mhmrentiva_log_cleanup_enabled', '1'));
        $this->assertSame($defaults['mhmrentiva_log_cleanup_enabled'], SettingsCore::get('mhmrentiva_log_cleanup_enabled', '0'));

        // mhmrentiva_log_retention_days: LogRetention.php's own fallback is 30.
        $this->assertSame($defaults['mhmrentiva_log_retention_days'], SettingsCore::get('mhmrentiva_log_retention_days', 30));
        $this->assertSame($defaults['mhmrentiva_log_retention_days'], SettingsCore::get('mhmrentiva_log_retention_days', 999));
    }

    /**
     * Corroborating premise: LogRetention::run() itself (not just
     * SettingsCore::get() in isolation) honours a freshly-saved retention
     * value end-to-end.
     */
    public function test_log_retention_run_honours_the_saved_retention_days(): void
    {
        update_option('mhmrentiva_settings', [
            'mhmrentiva_log_cleanup_enabled' => '1',
            'mhmrentiva_log_retention_days'  => 365,
        ]);

        $old = self::factory()->post->create([
            'post_type'     => 'mhmrentiva_app_log',
            'post_status'   => 'publish',
            'post_date'     => gmdate('Y-m-d H:i:s', strtotime('-200 days')),
            'post_date_gmt' => gmdate('Y-m-d H:i:s', strtotime('-200 days')),
        ]);

        LogRetention::run();

        $this->assertNotNull(
            get_post($old),
            'A 200-day-old log was purged although retention was saved as 365 days.'
        );
    }
}
