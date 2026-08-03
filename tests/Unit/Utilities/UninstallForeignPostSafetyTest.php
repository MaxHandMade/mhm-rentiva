<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Utilities;

use MHMRentiva\Admin\Utilities\Uninstall\Uninstaller;
use WP_UnitTestCase;

/**
 * Uninstall must never delete a post that is not ours.
 *
 * The 6.0.0 rename forces uninstall to carry the PRE-rename post types as well,
 * because a site that never ran Görev 13's migration still stores its rows under
 * them. But 'vehicle' and 'vehicle_booking' are generic slugs -- exactly the kind
 * of name any other rental plugin might register -- so matching on the slug alone
 * would delete THEIR content. On an uninstall path that is not a trade-off worth
 * making, and it is a thing WordPress.org reviewers look at closely.
 *
 * The legacy branch therefore additionally requires the post to carry one of this
 * plugin's own meta keys. These tests hold that line from both sides: a foreign
 * post survives, and one of ours in the old spelling still goes.
 *
 * @coversNothing
 */
final class UninstallForeignPostSafetyTest extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		// The legacy post types are not registered by this plugin any more, but a
		// row can exist without a registered type and uninstall works on rows.
		register_post_type( 'vehicle', array( 'public' => false ) );
		register_post_type( 'vehicle_booking', array( 'public' => false ) );
	}

	protected function tearDown(): void {
		unregister_post_type( 'vehicle' );
		unregister_post_type( 'vehicle_booking' );
		parent::tearDown();
	}

	/**
	 * A 'vehicle' post belonging to some other plugin carries none of our meta,
	 * so uninstall must leave it alone.
	 */
	public function test_a_foreign_vehicle_post_is_not_deleted(): void
	{
		$foreign = self::factory()->post->create(
			array(
				'post_type'  => 'vehicle',
				'post_title' => 'Another plugins vehicle',
			)
		);
		update_post_meta( $foreign, '_someoneelse_vehicle_data', 'not ours' );

		Uninstaller::uninstall_direct( false );

		$this->assertNotNull(
			get_post( $foreign ),
			'uninstall deleted a vehicle post that belongs to another plugin'
		);
		$this->assertSame(
			'not ours',
			get_post_meta( $foreign, '_someoneelse_vehicle_data', true ),
			'uninstall removed another plugin\'s meta'
		);
	}

	/**
	 * The negative control: without it the test above passes for a plugin that
	 * simply deletes nothing. One of OUR rows in the legacy spelling must still go.
	 */
	public function test_our_own_legacy_vehicle_post_is_still_deleted(): void
	{
		$ours = self::factory()->post->create( array( 'post_type' => 'vehicle' ) );
		update_post_meta( $ours, '_mhm_rentiva_price_per_day', '250' );

		Uninstaller::uninstall_direct( false );

		$this->assertNull(
			get_post( $ours ),
			'uninstall left one of our own pre-6.0.0 vehicles behind'
		);
	}

	/**
	 * Same pair for bookings, whose legacy slug is equally generic.
	 */
	public function test_a_foreign_booking_post_is_not_deleted_but_ours_is(): void
	{
		$foreign = self::factory()->post->create( array( 'post_type' => 'vehicle_booking' ) );
		$ours    = self::factory()->post->create( array( 'post_type' => 'vehicle_booking' ) );
		update_post_meta( $ours, '_mhm_booking_id', '4242' );

		Uninstaller::uninstall_direct( false );

		$this->assertNotNull( get_post( $foreign ), 'uninstall deleted a foreign booking post' );
		$this->assertNull( get_post( $ours ), 'uninstall left one of our own pre-6.0.0 bookings behind' );
	}

	// test_the_reported_count_excludes_foreign_posts() was removed (WP.org T8
	// Görev 10b, row A7): it covered Uninstaller::get_uninstall_stats(), the
	// stats reader for the now-deleted UninstallPage AJAX front door
	// (wp_ajax_mhmrentiva_get_uninstall_stats had zero shipped nonce producer
	// and zero rendering surface). get_uninstall_stats() was deleted with it;
	// uninstall_direct()'s foreign-post safety, tested by the three methods
	// above, is untouched.
}
