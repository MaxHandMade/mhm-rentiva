<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle;

use MHMRentiva\Admin\Vehicle\Meta\BlockedDatesMetaBox;
use WP_Ajax_UnitTestCase;

/**
 * WP.org T6, found by the pre-ZIP audit after the first capability pass missed it.
 *
 * "Apply blocked dates to all" and "remove from all" write blocked-date meta on
 * EVERY published vehicle, but gated on the blanket edit_posts and never checked
 * ownership of the vehicle the request names. Unlike vehicle_booking -- which
 * maps every capability to manage_options via
 * AbstractPostType::get_capabilities_args() -- the vehicle CPT registers with
 * map_meta_cap => true and the default capability_type of 'post', so a stock
 * WordPress Author who legitimately owns one listing holds edit_posts and is
 * handed a real nonce on their own vehicle's edit screen. That was enough to
 * overwrite the availability calendar of the entire fleet.
 *
 * A fleet-wide write needs the capability that means "may edit content you do
 * not own", so both handlers now require edit_others_posts in addition to
 * edit_post on the source vehicle.
 *
 * @covers \MHMRentiva\Admin\Vehicle\Meta\BlockedDatesMetaBox::ajax_apply_to_all
 * @covers \MHMRentiva\Admin\Vehicle\Meta\BlockedDatesMetaBox::ajax_remove_from_all
 */
final class FleetWideWriteCapabilityTest extends WP_Ajax_UnitTestCase
{
	private const META_KEY = '_mhm_blocked_dates';

	private int $author_id;
	private int $own_vehicle;
	private int $other_vehicle;

	public function setUp(): void
	{
		parent::setUp();

		$this->author_id = self::factory()->user->create( array( 'role' => 'author' ) );

		$this->own_vehicle = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_author' => $this->author_id,
			)
		);

		$this->other_vehicle = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_author' => self::factory()->user->create( array( 'role' => 'administrator' ) ),
			)
		);

		update_post_meta( $this->other_vehicle, self::META_KEY, wp_json_encode( array( '2030-01-01' ) ) );

		BlockedDatesMetaBox::register();
	}

	public function tearDown(): void
	{
		$_POST = array();
		parent::tearDown();
	}

	/**
	 * The Author holds edit_posts and can obtain a genuine nonce from their own
	 * vehicle's edit screen, so neither of those is what should stop them.
	 */
	public function test_author_cannot_apply_blocked_dates_across_the_fleet(): void
	{
		wp_set_current_user( $this->author_id );
		$this->assertTrue(
			current_user_can( 'edit_posts' ),
			'Precondition: an Author holds the capability the handler used to check.'
		);

		$_POST = array(
			'nonce'      => wp_create_nonce( 'mhmrentiva_apply_blocked_to_all' ),
			'vehicle_id' => $this->own_vehicle,
			'dates'      => wp_json_encode( array( '2031-05-05', '2031-05-06' ) ),
		);

		try {
			$this->_handleAjax( 'mhmrentiva_apply_blocked_dates_to_all' );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) {
			// wp_send_json_* terminates; the meta assertion below is the check.
		}

		$this->assertSame(
			wp_json_encode( array( '2030-01-01' ) ),
			get_post_meta( $this->other_vehicle, self::META_KEY, true ),
			'A vehicle the caller does not own must keep its own blocked dates.'
		);
	}

	public function test_author_cannot_remove_blocked_dates_across_the_fleet(): void
	{
		wp_set_current_user( $this->author_id );

		$_POST = array(
			'nonce'      => wp_create_nonce( 'mhmrentiva_remove_blocked_from_all' ),
			'vehicle_id' => $this->own_vehicle,
			'dates'      => wp_json_encode( array( '2030-01-01' ) ),
		);

		try {
			$this->_handleAjax( 'mhmrentiva_remove_blocked_dates_from_all' );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) {
			// see above
		}

		$this->assertSame(
			wp_json_encode( array( '2030-01-01' ) ),
			get_post_meta( $this->other_vehicle, self::META_KEY, true ),
			'A vehicle the caller does not own must keep its own blocked dates.'
		);
	}

	/**
	 * The fix must not take the feature away from the people it is for.
	 */
	public function test_administrator_can_still_apply_across_the_fleet(): void
	{
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$_POST = array(
			'nonce'      => wp_create_nonce( 'mhmrentiva_apply_blocked_to_all' ),
			'vehicle_id' => $this->own_vehicle,
			'dates'      => wp_json_encode( array( '2031-05-05' ) ),
		);

		try {
			$this->_handleAjax( 'mhmrentiva_apply_blocked_dates_to_all' );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) {
			// see above
		}

		$this->assertStringContainsString(
			'2031-05-05',
			(string) get_post_meta( $this->other_vehicle, self::META_KEY, true ),
			'An administrator must still be able to apply dates fleet-wide.'
		);
	}
}
