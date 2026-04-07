<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Admin\Testing;

use MHMRentiva\Admin\Testing\DemoNoticeManager;
use WP_UnitTestCase;

/**
 * DemoNoticeManager Test Suite
 *
 * Verifies demo-active detection, expiry flag, hook registration, and
 * the post-state filter for demo records.
 *
 * @package MHMRentiva\Tests\Unit\Admin\Testing
 * @since   4.25.1
 */
final class DemoNoticeManagerTest extends WP_UnitTestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function activate_demo(): void
    {
        update_option( 'mhm_rentiva_demo_active', '1' );
    }

    private function deactivate_demo(): void
    {
        delete_option( 'mhm_rentiva_demo_active' );
        delete_option( 'mhm_rentiva_demo_seeded_at' );
    }

    protected function tearDown(): void
    {
        $this->deactivate_demo();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // is_demo_active
    // -------------------------------------------------------------------------

    public function test_is_demo_active_returns_false_when_option_absent(): void
    {
        $this->deactivate_demo();

        $this->assertFalse( DemoNoticeManager::is_demo_active() );
    }

    public function test_is_demo_active_returns_true_when_option_is_one(): void
    {
        $this->activate_demo();

        $this->assertTrue( DemoNoticeManager::is_demo_active() );
    }

    // -------------------------------------------------------------------------
    // is_expiry_warning
    // -------------------------------------------------------------------------

    public function test_is_expiry_warning_returns_false_when_no_seed_timestamp(): void
    {
        delete_option( 'mhm_rentiva_demo_seeded_at' );

        $this->assertFalse( DemoNoticeManager::is_expiry_warning() );
    }

    public function test_is_expiry_warning_returns_false_when_less_than_seven_days(): void
    {
        update_option( 'mhm_rentiva_demo_seeded_at', time() - ( 3 * DAY_IN_SECONDS ) );

        $this->assertFalse( DemoNoticeManager::is_expiry_warning() );
    }

    public function test_is_expiry_warning_returns_true_when_seven_or_more_days(): void
    {
        update_option( 'mhm_rentiva_demo_seeded_at', time() - ( 8 * DAY_IN_SECONDS ) );

        $this->assertTrue( DemoNoticeManager::is_expiry_warning() );
    }

    // -------------------------------------------------------------------------
    // register — hook presence
    // -------------------------------------------------------------------------

    public function test_register_does_not_add_hooks_when_demo_inactive(): void
    {
        $this->deactivate_demo();
        DemoNoticeManager::register();

        $this->assertFalse( has_action( 'admin_notices', array( DemoNoticeManager::class, 'render_global_notice' ) ) );
    }

    public function test_register_adds_admin_notices_hook_when_demo_active(): void
    {
        $this->activate_demo();
        DemoNoticeManager::register();

        $this->assertNotFalse( has_action( 'admin_notices', array( DemoNoticeManager::class, 'render_global_notice' ) ) );
    }

    public function test_register_adds_admin_bar_hook_when_demo_active(): void
    {
        $this->activate_demo();
        DemoNoticeManager::register();

        $this->assertNotFalse( has_action( 'admin_bar_menu', array( DemoNoticeManager::class, 'render_admin_bar_badge' ) ) );
    }

    public function test_register_adds_display_post_states_filter_when_demo_active(): void
    {
        $this->activate_demo();
        DemoNoticeManager::register();

        $this->assertNotFalse( has_filter( 'display_post_states', array( DemoNoticeManager::class, 'add_demo_post_state' ) ) );
    }

    // -------------------------------------------------------------------------
    // add_demo_post_state
    // -------------------------------------------------------------------------

    public function test_add_demo_post_state_appends_demo_label_to_demo_post(): void
    {
        $post_id = self::factory()->post->create(
            array(
                'post_type'   => 'vehicle',
                'post_status' => 'publish',
            )
        );
        update_post_meta( $post_id, '_mhm_is_demo', '1' );

        $post   = get_post( $post_id );
        $states = DemoNoticeManager::add_demo_post_state( array(), $post );

        $this->assertArrayHasKey( 'mhm_demo', $states );
    }

    public function test_add_demo_post_state_does_not_modify_non_demo_post(): void
    {
        $post_id = self::factory()->post->create();
        $post    = get_post( $post_id );

        $states = DemoNoticeManager::add_demo_post_state( array(), $post );

        $this->assertArrayNotHasKey( 'mhm_demo', $states );
    }

    // -------------------------------------------------------------------------
    // render_post_editor_banner — output
    // -------------------------------------------------------------------------

    public function test_render_post_editor_banner_outputs_for_demo_vehicle(): void
    {
        $post_id = self::factory()->post->create(
            array(
                'post_type'   => 'vehicle',
                'post_status' => 'publish',
            )
        );
        update_post_meta( $post_id, '_mhm_is_demo', '1' );

        $post = get_post( $post_id );

        ob_start();
        DemoNoticeManager::render_post_editor_banner( $post );
        $output = ob_get_clean();

        $this->assertStringContainsString( 'demo record', $output );
    }

    public function test_render_post_editor_banner_silent_for_non_demo_post(): void
    {
        $post_id = self::factory()->post->create( array( 'post_type' => 'vehicle' ) );
        $post    = get_post( $post_id );

        ob_start();
        DemoNoticeManager::render_post_editor_banner( $post );
        $output = ob_get_clean();

        $this->assertSame( '', $output );
    }

    public function test_render_post_editor_banner_silent_for_unrelated_post_type(): void
    {
        $post_id = self::factory()->post->create( array( 'post_type' => 'post' ) );
        update_post_meta( $post_id, '_mhm_is_demo', '1' );
        $post = get_post( $post_id );

        ob_start();
        DemoNoticeManager::render_post_editor_banner( $post );
        $output = ob_get_clean();

        $this->assertSame( '', $output );
    }
}
