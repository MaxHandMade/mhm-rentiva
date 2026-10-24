<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\BookingUrl;
use WP_UnitTestCase;

/**
 * A page built with Elementor keeps its content in `_elementor_data`, not in
 * post_content. get_page_id() only searched post_content, so on an Elementor site
 * every "Book now" button fell back to the home page (mhmrentiva.com, 2026-10-24).
 *
 * @covers \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_id
 */
final class ElementorPageDetectionTest extends WP_UnitTestCase
{
	public function set_up(): void
	{
		parent::set_up();
		wp_cache_flush();
		ShortcodeUrlManager::clear_cache();
	}

	private function elementor_page( array $widget, string $status = 'publish', string $date = '2020-01-02 00:00:00' ): int
	{
		$id   = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => $status,
				'post_content' => '',
				'post_date'    => $date,
			)
		);
		$data = array(
			array(
				'id'       => 'sec1',
				'elType'   => 'container',
				'settings' => array(),
				'elements' => array( array_merge( array( 'id' => 'w1', 'elType' => 'widget', 'settings' => array() ), $widget ) ),
			),
		);
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_data', wp_slash( (string) wp_json_encode( $data ) ) );
		return $id;
	}

	public function test_a_rentiva_elementor_widget_is_found(): void
	{
		$page = $this->elementor_page( array( 'widgetType' => 'rv-booking-form' ) );

		$this->assertSame( $page, ShortcodeUrlManager::get_page_id( 'rentiva_booking_form' ) );
	}

	public function test_a_shortcode_inside_an_elementor_shortcode_widget_is_found(): void
	{
		$page = $this->elementor_page(
			array(
				'widgetType' => 'shortcode',
				'settings'   => array( 'shortcode' => '[rentiva_booking_form vehicle_id="5"]' ),
			)
		);

		$this->assertSame( $page, ShortcodeUrlManager::get_page_id( 'rentiva_booking_form' ) );
	}

	public function test_a_page_with_the_shortcode_in_its_content_still_wins(): void
	{
		$this->elementor_page( array( 'widgetType' => 'rv-booking-form' ), 'publish', '2021-01-01 00:00:00' );
		$classic = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '[rentiva_booking_form]',
				'post_date'    => '2020-01-01 00:00:00',
			)
		);

		$this->assertSame( $classic, ShortcodeUrlManager::get_page_id( 'rentiva_booking_form' ) );
	}

	public function test_an_unpublished_elementor_page_is_not_found(): void
	{
		$this->elementor_page( array( 'widgetType' => 'rv-booking-form' ), 'draft' );

		$this->assertNull( ShortcodeUrlManager::get_page_id( 'rentiva_booking_form' ) );
	}

	public function test_a_page_switched_back_to_the_classic_editor_is_not_found(): void
	{
		$page = $this->elementor_page( array( 'widgetType' => 'rv-booking-form' ) );
		delete_post_meta( $page, '_elementor_edit_mode' );

		$this->assertNull( ShortcodeUrlManager::get_page_id( 'rentiva_booking_form' ) );
	}

	public function test_the_oldest_elementor_page_wins_over_a_later_one(): void
	{
		$details  = $this->elementor_page( array( 'widgetType' => 'rv-vehicle-details' ), 'publish', '2020-01-01 00:00:00' );
		$campaign = $this->elementor_page( array( 'widgetType' => 'rv-vehicle-details', 'settings' => array( 'vehicle_id' => '12' ) ), 'publish', '2021-06-01 00:00:00' );

		$this->assertSame( $details, ShortcodeUrlManager::get_page_id( 'rentiva_vehicle_details' ) );
		$this->assertNotSame( $campaign, ShortcodeUrlManager::get_page_id( 'rentiva_vehicle_details' ) );
	}

	public function test_a_page_the_plugin_created_wins(): void
	{
		$this->elementor_page( array( 'widgetType' => 'rv-booking-form' ), 'publish', '2019-01-01 00:00:00' );
		$own = $this->elementor_page( array( 'widgetType' => 'rv-booking-form' ), 'publish', '2022-01-01 00:00:00' );
		update_post_meta( $own, '_mhmrentiva_auto_created', '1' );

		$this->assertSame( $own, ShortcodeUrlManager::get_page_id( 'rentiva_booking_form' ) );
	}

	public function test_a_miss_is_remembered_until_the_cache_is_cleared(): void
	{
		$this->assertNull( ShortcodeUrlManager::get_page_id( 'rentiva_booking_form' ) );

		$page = $this->elementor_page( array( 'widgetType' => 'rv-booking-form' ) );
		$this->assertNull( ShortcodeUrlManager::get_page_id( 'rentiva_booking_form' ), 'the miss is cached' );

		ShortcodeUrlManager::clear_cache_on_page_update( $page );
		$this->assertSame( $page, ShortcodeUrlManager::get_page_id( 'rentiva_booking_form' ), 'saving an Elementor page clears it' );
	}

	public function test_another_widget_does_not_match(): void
	{
		$this->elementor_page( array( 'widgetType' => 'mhmrentiva_vehicles_list' ) );

		$this->assertNull( ShortcodeUrlManager::get_page_id( 'rentiva_vehicles_grid' ) );
	}

	public function test_an_add_on_can_name_its_own_widget(): void
	{
		add_filter(
			'mhmrentiva_elementor_widgets_for_shortcode',
			static function ( array $names, string $shortcode ): array {
				if ( 'rentiva_transfer_search' === $shortcode ) {
					$names[] = 'rv-transfer-search';
				}
				return $names;
			},
			10,
			2
		);
		$page = $this->elementor_page( array( 'widgetType' => 'rv-transfer-search' ) );

		$this->assertSame( $page, ShortcodeUrlManager::get_page_id( 'rentiva_transfer_search' ) );
	}

	public function test_the_booking_button_opens_the_elementor_booking_page(): void
	{
		$page = $this->elementor_page( array( 'widgetType' => 'rv-booking-form' ) );

		$this->assertSame( get_permalink( $page ), BookingUrl::base_url() );
	}
}
