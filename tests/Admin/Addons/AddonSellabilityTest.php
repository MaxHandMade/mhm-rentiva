<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Addons;

use MHMRentiva\Admin\Addons\AddonManager;
use MHMRentiva\Admin\Frontend\Shortcodes\BookingForm;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * "Is this additional service sellable right now?" -- one answer, four surfaces.
 *
 * WHY THIS TEST EXISTS
 * --------------------
 * The add-ons screen has an Aktif/Pasif toggle and its own meta box promises,
 * in as many words, that "Only active additional services are visible in
 * booking form." It was not true. Four places query add-ons and only one of
 * them applied the enabled flag:
 *
 *   AddonManager::get_available_addons()      meta_query on enabled   (correct)
 *   BookingForm::get_available_addons()       post_status only        (customer!)
 *   ManualBookingMetaBox (operator, new)      post_status only
 *   BookingEditMetaBox (operator, editing)    post_status only
 *
 * So an operator switched a service off, watched it go grey in the admin, and
 * the customer booking form kept selling it. The divergence predates the screen
 * redesign -- both implementations have existed since the first public release.
 *
 * AND FILTERING THE LISTS IS NOT THE FIX
 * --------------------------------------
 * The booking form accepts whatever ids the request carries and runs them
 * through SecurityHelper::validate_numeric_array(), which only asks "are these
 * numbers". Neither the post type nor the enabled flag is checked, and
 * AddonPricingCalculator does not look at the flag either. A replayed form, or
 * any hand-made request, could therefore buy a disabled service -- or attach an
 * arbitrary post id as a "service". Hiding a checkbox does not close that; the
 * acceptance point has to refuse. The lists are defence in depth.
 *
 * THE MEANING OF AN ABSENT FLAG IS NOT A GUESS
 * --------------------------------------------
 * AddonScreen's quick-create writes the flag explicitly and says why: "Absent
 * means active ... a service born switched off with no explanation is the
 * silent defect this endpoint exists to avoid." So only an explicit '0'
 * disables, and a service that has never carried the flag -- one created before
 * the field existed -- stays sellable.
 *
 * That rule needs absence to have exactly one cause, and for a while it did not:
 * the full editor deleted the row for an unticked checkbox, so unticking Active
 * put the service back on sale. AddonEditorSaveTest covers that door; the fix is
 * the `absent_value` option those fields now declare. That is what
 * test_addon_that_never_carried_the_flag_is_sellable() pins: a meta_query with
 * compare '=' is an INNER JOIN and would drop exactly those rows, which is how
 * the one "correct" implementation is also wrong.
 */
final class AddonSellabilityTest extends WP_UnitTestCase {

	private int $enabled_id;
	private int $disabled_id;
	private int $legacy_id;

	protected function setUp(): void {
		parent::setUp();

		$this->enabled_id = $this->make_addon( 'Child Seat' );
		update_post_meta( $this->enabled_id, 'mhmrentiva_addon_enabled', '1' );

		$this->disabled_id = $this->make_addon( 'Retired Wi-Fi Router' );
		update_post_meta( $this->disabled_id, 'mhmrentiva_addon_enabled', '0' );

		// Never carried the flag: created before the field existed.
		$this->legacy_id = $this->make_addon( 'Legacy Roof Rack' );
	}

	private function make_addon( string $title, string $status = 'publish' ): int {
		return (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_addon',
				'post_title'  => $title,
				'post_status' => $status,
			)
		);
	}

	/**
	 * The customer form's own offering list, which is private.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function booking_form_offers(): array {
		$method = new ReflectionMethod( BookingForm::class, 'get_available_addons' );
		$method->setAccessible( true );

		return (array) $method->invoke( null );
	}

	/**
	 * @param array<int, array<string, mixed>> $offers
	 * @return array<int, int>
	 */
	private function ids_of( array $offers ): array {
		return array_map( static fn( array $row ): int => (int) $row['id'], $offers );
	}

	// --- The canonical predicate -------------------------------------------

	public function test_disabled_addon_is_not_sellable(): void {
		$this->assertFalse(
			AddonManager::is_sellable( $this->disabled_id ),
			'An add-on switched off in the admin must not be sellable.'
		);
	}

	public function test_enabled_addon_is_sellable(): void {
		$this->assertTrue( AddonManager::is_sellable( $this->enabled_id ) );
	}

	public function test_addon_that_never_carried_the_flag_is_sellable(): void {
		$this->assertTrue(
			AddonManager::is_sellable( $this->legacy_id ),
			'An absent flag means active -- AddonScreen::quick-create says so in as many words. '
			. 'A service created before the field existed must not vanish from the booking form.'
		);
	}

	public function test_unpublished_addon_is_not_sellable(): void {
		$draft = $this->make_addon( 'Unfinished Extra', 'draft' );
		update_post_meta( $draft, 'mhmrentiva_addon_enabled', '1' );

		$this->assertFalse( AddonManager::is_sellable( $draft ) );
	}

	public function test_a_post_that_is_not_an_addon_is_not_sellable(): void {
		$page = (int) self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->assertFalse(
			AddonManager::is_sellable( $page ),
			'The submit path only checks that the ids are numeric, so any post id can arrive here.'
		);
	}

	public function test_a_nonexistent_id_is_not_sellable(): void {
		$this->assertFalse( AddonManager::is_sellable( 99999999 ) );
	}

	// --- The acceptance point ----------------------------------------------

	public function test_filter_sellable_drops_everything_that_is_not_sellable(): void {
		$page = (int) self::factory()->post->create( array( 'post_type' => 'page' ) );

		$kept = AddonManager::filter_sellable(
			array( $this->enabled_id, $this->disabled_id, $this->legacy_id, $page, 0, -5 )
		);

		sort( $kept );
		$expected = array( $this->enabled_id, $this->legacy_id );
		sort( $expected );

		$this->assertSame(
			$expected,
			$kept,
			'A replayed form or a hand-made request must not be able to buy a disabled service, '
			. 'nor attach an arbitrary post as one.'
		);
	}

	// --- The acceptance point, as the booking form actually reaches it -----
	//
	// filter_sellable() is tested directly above, which proves it works and
	// nothing else. The question these ask is whether the booking form REACHES
	// it -- the failure mode this plugin keeps producing, where a correct
	// implementation exists and no live path calls it. So they run the real
	// method the handler runs, on a request built the way a submit builds one.

	/**
	 * @param array<string, mixed> $request
	 * @return array<int, int>
	 */
	private function accept_submitted( array $request ): array {
		$method = new ReflectionMethod( BookingForm::class, 'accept_submitted_addons' );
		$method->setAccessible( true );

		return (array) $method->invoke( null, \MHMRentiva\Admin\Core\Security\VerifiedRequest::from( $request ) );
	}

	public function test_ajax_submit_cannot_buy_a_disabled_addon(): void {
		$accepted = $this->accept_submitted(
			array( 'addons' => array( $this->enabled_id, $this->disabled_id ) )
		);

		$this->assertSame(
			array( $this->enabled_id ),
			$accepted,
			'A replayed form still carries the id of a service that has since been switched off.'
		);
	}

	public function test_non_ajax_submit_cannot_buy_a_disabled_addon(): void {
		$accepted = $this->accept_submitted(
			array( 'selected_addons' => array( $this->disabled_id, $this->legacy_id ) )
		);

		$this->assertSame(
			array( $this->legacy_id ),
			$accepted,
			'The non-AJAX form posts under a different name and must be refused the same way.'
		);
	}

	public function test_submit_cannot_attach_an_arbitrary_post_as_a_service(): void {
		$page = (int) self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->assertSame(
			array(),
			$this->accept_submitted( array( 'addons' => array( $page ) ) ),
			'validate_numeric_array() only asks whether the ids are numbers.'
		);
	}

	public function test_submit_with_no_addons_is_empty_not_broken(): void {
		$this->assertSame( array(), $this->accept_submitted( array() ) );
	}

	// --- The offering lists (defence in depth) -----------------------------

	public function test_booking_form_does_not_offer_a_disabled_addon(): void {
		$ids = $this->ids_of( $this->booking_form_offers() );

		$this->assertNotContains(
			$this->disabled_id,
			$ids,
			'The add-on meta box tells the operator "Only active additional services are visible '
			. 'in booking form." This is that promise.'
		);
	}

	public function test_booking_form_still_offers_enabled_and_legacy_addons(): void {
		$ids = $this->ids_of( $this->booking_form_offers() );

		$this->assertContains( $this->enabled_id, $ids );
		$this->assertContains(
			$this->legacy_id,
			$ids,
			'Negative control for the sweep: filtering must not empty the form on sites whose '
			. 'add-ons predate the flag.'
		);
	}

	// --- The two operator screens ------------------------------------------

	private function render_metabox( string $class, int $booking_id ): string {
		$post = get_post( $booking_id );

		ob_start();
		try {
			$class::render( $post );
		} finally {
			$html = (string) ob_get_clean();
		}

		return $html;
	}

	private function make_booking(): int {
		return (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'publish',
			)
		);
	}

	public function test_manual_booking_screen_does_not_offer_a_disabled_addon(): void {
		$html = $this->render_metabox( \MHMRentiva\Admin\Booking\Meta\ManualBookingMetaBox::class, $this->make_booking() );

		$this->assertStringNotContainsString(
			'Retired Wi-Fi Router',
			$html,
			'Pasif means not sold. Offering it to the operator leaves the switch half-wired.'
		);
		$this->assertStringContainsString( 'Child Seat', $html );
		$this->assertStringContainsString( 'Legacy Roof Rack', $html );
	}

	public function test_edit_screen_does_not_offer_a_disabled_addon_that_is_not_attached(): void {
		$html = $this->render_metabox( \MHMRentiva\Admin\Booking\Meta\BookingEditMetaBox::class, $this->make_booking() );

		$this->assertStringNotContainsString( 'Retired Wi-Fi Router', $html );
		$this->assertStringContainsString( 'Child Seat', $html );
	}

	/**
	 * The negative control for the sweep.
	 *
	 * A booking taken last month may already carry a service the operator has
	 * since switched off. If filtering the offered list also removes it from the
	 * screen, the checkbox is gone, the form posts without it, and saving the
	 * booking silently drops a service the customer paid for. Filtering must
	 * subtract from what is OFFERED, never from what is ATTACHED.
	 */
	public function test_edit_screen_still_shows_a_disabled_addon_that_is_already_attached(): void {
		$booking_id = $this->make_booking();
		update_post_meta( $booking_id, '_mhmrentiva_selected_addons', array( $this->disabled_id ) );

		$html = $this->render_metabox( \MHMRentiva\Admin\Booking\Meta\BookingEditMetaBox::class, $booking_id );

		$this->assertStringContainsString(
			'Retired Wi-Fi Router',
			$html,
			'An attached service must survive being switched off, or editing the booking deletes it.'
		);
	}

	public function test_manager_available_addons_agrees_with_the_predicate(): void {
		$ids = array_map(
			static fn( array $row ): int => (int) $row['id'],
			AddonManager::get_available_addons()
		);

		$this->assertContains( $this->enabled_id, $ids );
		$this->assertContains(
			$this->legacy_id,
			$ids,
			'get_available_addons() filters with a meta_query, which is an INNER JOIN and silently '
			. 'drops rows that carry no flag at all -- the one implementation that looked correct.'
		);
		$this->assertNotContains( $this->disabled_id, $ids );
	}
}
