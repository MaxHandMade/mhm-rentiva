<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Addons;

use MHMRentiva\Admin\Addons\AddonManager;
use MHMRentiva\Admin\Addons\AddonMeta;
use WP_UnitTestCase;

/**
 * Unchecking "Active" in the full post editor has to actually disable the service.
 *
 * WHY THIS EXISTS
 * ---------------
 * AddonManager::is_sellable() refuses only an explicit '0', because a service
 * that has never carried the flag predates the field and must stay sellable --
 * otherwise the booking form empties on every site that upgraded into it. That
 * rule was written against a premise: "unchecking the box writes '0' rather than
 * deleting the row", citing AddonMeta::update_addon_meta().
 *
 * The premise was false. update_addon_meta() has no caller anywhere in the
 * production tree; the editor saves through AbstractMetaBox::save_meta(), whose
 * save_field() runs delete_post_meta() for a checkbox absent from the POST. So
 * unchecking DELETED the flag, absence read as "legacy", and is_sellable() went
 * on selling a service the operator had just switched off -- the exact defect
 * the new screen's toggle was built to fix, reintroduced through the editor door
 * that every row of that screen links to.
 *
 * Writing a security predicate on the behaviour of a function nothing calls is
 * the failure this codebase keeps repeating. These tests drive the real editor
 * save path instead of the helper the docblock believed in.
 */
final class AddonEditorSaveTest extends WP_UnitTestCase {

	private int $addon_id;

	protected function setUp(): void {
		parent::setUp();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->addon_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_addon',
				'post_title'  => 'Wi-Fi Router',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $this->addon_id, 'mhmrentiva_addon_enabled', '1' );
	}

	protected function tearDown(): void {
		$_POST = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Save the add-on the way the editor does: every box's nonce present, and the
	 * settings fields carrying only what the operator actually ticked.
	 *
	 * @param array<string,string> $ticked Checkbox fields present in the POST.
	 */
	private function save_from_editor( array $ticked ): void {
		$_POST = array(
			// Both boxes are walked by save_meta(); each verifies its own nonce.
			'mhmrentiva_addon_addon_details_nonce'  => wp_create_nonce( 'mhmrentiva_addon_addon_details_nonce' ),
			'mhmrentiva_addon_addon_settings_nonce' => wp_create_nonce( 'mhmrentiva_addon_addon_settings_nonce' ),
			// The details box always posts these; leaving them out would make the
			// test about a half-filled form rather than about the checkbox.
			'mhmrentiva_addon_price'                => '25',
			'_mhmrentiva_addon_pricing_type'        => 'per_booking',
		);

		foreach ( $ticked as $key => $value ) {
			$_POST[ $key ] = $value;
		}

		AddonMeta::save_meta( $this->addon_id, get_post( $this->addon_id ) );
	}

	/**
	 * The editor's own checkbox was the surviving reader of the flag.
	 *
	 * A service created before the flag existed carries no meta row. Every other
	 * surface calls it active -- it is sold, the KPI band counts it, the row
	 * badge says Active. The editor rendered its box UNTICKED, because
	 * checked( '', '1' ) does not match, and an unticked box is not submitted,
	 * so `absent_value` wrote '0'. An operator fixing a typo in the description
	 * and pressing Update took a selling service off sale, and the screen that
	 * would have shown it is not the one they were on.
	 *
	 * grep could not find this reader: AbstractMetaBox is generic and takes the
	 * field name from a variable, so a sweep for 'mhmrentiva_addon_enabled'
	 * walks straight past it.
	 */
	public function test_the_editor_ticks_active_for_a_service_that_never_carried_the_flag(): void {
		delete_post_meta( $this->addon_id, 'mhmrentiva_addon_enabled' );

		$this->assertTrue(
			AddonManager::is_sellable( $this->addon_id ),
			'Precondition: with no flag the service IS sold -- that is what makes an unticked box a lie.'
		);

		$html = $this->render_settings_box();

		$this->assertMatchesRegularExpression(
			'/<input type="checkbox" id="mhmrentiva_addon_enabled"[^>]*checked/',
			$html,
			'The box must show the state the seller uses, or Update silently switches the service off.'
		);
	}

	/**
	 * NEGATIVE CONTROL. Both checkboxes declare absent_value => '0', so a fix
	 * driven off that option alone would tick "Required" for every service that
	 * never set it. Absence means active for one flag and not-required for the
	 * other; only the first one opts in.
	 */
	public function test_the_editor_does_not_tick_required_for_a_service_that_never_carried_it(): void {
		delete_post_meta( $this->addon_id, 'mhmrentiva_addon_required' );

		$html = $this->render_settings_box();

		$this->assertDoesNotMatchRegularExpression(
			'/<input type="checkbox" id="mhmrentiva_addon_required"[^>]*checked/',
			$html,
			'An absent required flag means NOT required.'
		);
	}

	public function test_the_editor_leaves_an_explicitly_disabled_service_unticked(): void {
		update_post_meta( $this->addon_id, 'mhmrentiva_addon_enabled', '0' );

		$html = $this->render_settings_box();

		$this->assertDoesNotMatchRegularExpression(
			'/<input type="checkbox" id="mhmrentiva_addon_enabled"[^>]*checked/',
			$html,
			'An explicit 0 is the operator saying off, and must stay off.'
		);
	}

	/**
	 * The whole point, end to end: the box renders ticked, so a browser submits
	 * it, so a plain Update HEALS the legacy row to '1' instead of killing it.
	 */
	public function test_a_plain_update_keeps_a_flagless_service_on_sale(): void {
		delete_post_meta( $this->addon_id, 'mhmrentiva_addon_enabled' );

		// A ticked box is submitted by the browser; that is what the render fix buys.
		$this->save_from_editor( array( 'mhmrentiva_addon_enabled' => '1' ) );

		$this->assertTrue( AddonManager::is_sellable( $this->addon_id ) );
		$this->assertSame( '1', (string) get_post_meta( $this->addon_id, 'mhmrentiva_addon_enabled', true ) );
	}

	/** The settings box exactly as the editor renders it. */
	private function render_settings_box(): string {
		ob_start();
		try {
			AddonMeta::render_meta_box( get_post( $this->addon_id ), array( 'id' => 'addon_settings' ) );
			return (string) ob_get_contents();
		} finally {
			ob_end_clean();
		}
	}

	public function test_unticking_active_in_the_editor_makes_the_service_unsellable(): void {
		$this->assertTrue( AddonManager::is_sellable( $this->addon_id ), 'Precondition.' );

		// An unticked checkbox is simply absent from the POST. That is the whole
		// mechanism -- there is no "0" for the browser to send.
		$this->save_from_editor( array() );

		$this->assertFalse(
			AddonManager::is_sellable( $this->addon_id ),
			'The operator unticked "Enable this additional service" beside the words '
			. '"Only active additional services are visible in booking form".'
		);
	}

	public function test_the_flag_is_written_as_zero_rather_than_deleted(): void {
		$this->save_from_editor( array() );

		$this->assertSame(
			'0',
			(string) get_post_meta( $this->addon_id, AddonManager::ENABLED_META, true ),
			'Deleting the row would make it indistinguishable from a service that predates '
			. 'the field, which is_sellable() must keep treating as active.'
		);
	}

	public function test_ticking_active_keeps_the_service_sellable(): void {
		$this->save_from_editor( array() );
		$this->assertFalse( AddonManager::is_sellable( $this->addon_id ) );

		$this->save_from_editor( array( 'mhmrentiva_addon_enabled' => '1' ) );

		$this->assertTrue(
			AddonManager::is_sellable( $this->addon_id ),
			'Switching it back on has to work through the same door.'
		);
	}

	/**
	 * The negative control for the fix.
	 *
	 * Writing '0' on absence must not spread to a service the editor has never
	 * been opened on. Those carry no flag, is_sellable() calls them active, and
	 * that is what keeps upgraded sites from losing their whole add-on list.
	 */
	public function test_a_service_the_editor_never_touched_stays_sellable(): void {
		$untouched = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_addon',
				'post_title'  => 'Legacy Roof Rack',
				'post_status' => 'publish',
			)
		);

		$this->save_from_editor( array() );

		$this->assertFalse( AddonManager::is_sellable( $this->addon_id ) );
		$this->assertTrue(
			AddonManager::is_sellable( $untouched ),
			'Saving one add-on must not change what absence means for every other.'
		);
		$this->assertSame(
			'',
			(string) get_post_meta( $untouched, AddonManager::ENABLED_META, true )
		);
	}

	public function test_required_is_written_on_absence_too(): void {
		update_post_meta( $this->addon_id, 'mhmrentiva_addon_required', '1' );

		$this->save_from_editor( array() );

		$this->assertSame(
			'0',
			(string) get_post_meta( $this->addon_id, 'mhmrentiva_addon_required', true ),
			'The other checkbox in the same box goes through the same handler; leaving it '
			. 'on delete would be two rules for two boxes side by side.'
		);
	}
}
