<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Settings\Groups\CoreSettings;
use MHMRentiva\Admin\Settings\Groups\MaintenanceSettings;
use MHMRentiva\Admin\Settings\View\TabRendererRegistry;
use WP_UnitTestCase;

/**
 * T8 Görev 10c-A (K5-F4) evidence-conflict lock.
 *
 * task-10a-endpoint-table.md's F4/D5 row classified
 * MaintenanceSettings::render_settings_section() as an orphan and concluded
 * from that alone that "no admin can ever reach the [WIPE ALL DATA ON
 * UNINSTALL] checkbox to opt in". The method-level claim is correct --
 * render_settings_section() truly has zero direct callers, confirmed and
 * deleted in this same task. The reachability conclusion drawn from it was
 * not: WordPress' Settings API is decoupled from whichever class originally
 * called add_settings_section()/add_settings_field(). MaintenanceSettings::
 * SECTION_ID ('mhmrentiva_maintenance_section') is one of the four section
 * ids TabRendererRegistry's 'system' tab renders via its own generic
 * $sections-array path (BaseSettingsTabRenderer::render() ->
 * AbstractTabRenderer::render_section_clean() ->
 * SettingsViewHelper::render_section_cleanly() -> do_settings_fields()) --
 * a completely different call path than render_settings_section(), and one
 * that runs regardless of whether that method exists at all.
 *
 * This test renders the REAL production 'system' tab through the REAL
 * TabRendererRegistry (not a hand-derived section list) and proves the
 * checkbox is on the page today. Consequence for K5-F4: register()'s
 * add_settings_section()/add_settings_field() wiring was NOT deleted --
 * only the dead render_settings_section() wrapper was. See
 * task-10c-A-report.md for the full writeup.
 *
 * @covers \MHMRentiva\Admin\Settings\Groups\MaintenanceSettings
 */
final class MaintenanceSettingsLiveViaSystemTabTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Populates $wp_settings_sections / $wp_settings_fields for the
        // 'system' tab's mhmrentiva_maintenance_section, the same
        // is_admin()-gated-bootstrap reason documented on
        // Task10bDeadEndpointsRemovedTest's setUp(): Plugin::
        // initialize_admin_services() never runs in this suite's shared
        // bootstrap, so nothing registers these fields unless a test does.
        MaintenanceSettings::register();
        // render_group_cache() also calls this itself at render time, but
        // registering it here up front matches how SettingsCore::
        // register_sub_groups() really runs both on every admin_init.
        CoreSettings::register();
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

    public function test_clean_data_on_uninstall_checkbox_renders_on_the_live_system_tab(): void
    {
        $html = $this->render_live_system_tab();

        $this->assertStringContainsString(
            'name="mhmrentiva_settings[mhmrentiva_clean_data_on_uninstall]" value="1"',
            $html,
            'MaintenanceSettings::render_group_db_cleanup() must render the opt-in checkbox on the live System & Performance tab -- it is reached via the tab\'s $sections list (SECTION_ID), not via the dead render_settings_section() wrapper.'
        );
        $this->assertStringContainsString(
            'WIPE ALL DATA ON UNINSTALL',
            $html,
            'The checkbox label text must be present -- proves this is the real field, not just a matching name= attribute.'
        );
    }

    /**
     * Corroborating premise: the nested CoreSettings cache fields (reached
     * the same indirect way, via render_group_cache()'s own manual
     * do_settings_fields() call) render too -- this is not a one-field
     * fluke, the whole section's field-callback mechanism is live.
     */
    public function test_nested_cache_fields_also_render_on_the_live_system_tab(): void
    {
        $html = $this->render_live_system_tab();

        $this->assertStringContainsString(
            'name="mhmrentiva_settings[mhmrentiva_cache_enabled]"',
            $html,
            'CoreSettings::render_section_description()/its cache fields are pulled in by MaintenanceSettings::render_group_cache() -- must render on the live tab.'
        );
    }

    /**
     * Regression lock for the deletion step: the dead wrapper method must
     * not come back without a caller.
     */
    public function test_render_settings_section_no_longer_exists(): void
    {
        $this->assertFalse(
            method_exists(MaintenanceSettings::class, 'render_settings_section'),
            'MaintenanceSettings::render_settings_section() had zero direct callers in either repo (task-10a D5) -- must be gone (K5-F4). Its section\'s fields still render live via the system tab\'s own $sections mechanism (see the tests above), so this deletion is not a reachability regression.'
        );
    }

    public function test_log_max_size_default_is_gone(): void
    {
        $this->assertArrayNotHasKey(
            'mhmrentiva_log_max_size',
            MaintenanceSettings::get_default_settings(),
            'mhmrentiva_log_max_size was fully dead (K5-F4): no reader, no field, not even in SettingsSanitizer.php -- must be gone from the defaults skeleton.'
        );
    }
}
