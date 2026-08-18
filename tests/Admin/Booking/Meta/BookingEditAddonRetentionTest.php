<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\BookingEditMetaBox;
use WP_UnitTestCase;

/**
 * What the booking editor must still be carrying after a save.
 *
 * The metabox already knew the risk and wrote it down: "drop it from this
 * screen and its checkbox disappears, the form posts without it, and saving the
 * booking deletes a service the customer paid for." save_booking_details()
 * takes the posted list as the whole truth -- which is correct for a checkbox
 * an operator can actually clear -- so anything the form cannot post is
 * silently removed on the next save.
 *
 * Two shapes the form could not post:
 *
 *   - A REQUIRED service renders as `checked disabled`, and a disabled control
 *     is not submitted. The operator opens a booking, changes the pickup time,
 *     saves, and the mandatory service is gone.
 *   - A TRASHED service never entered the pool at all: the query behind the
 *     union is post_status => 'publish', so the "already-attached" branch could
 *     never see it. The union covered a service switched OFF but not one moved
 *     to the trash.
 *
 * The third test is the negative control. The fix must not make everything
 * unremovable -- an ordinary attached service has to keep being something the
 * operator can uncheck, which means it must NOT gain a hidden field.
 */
final class BookingEditAddonRetentionTest extends WP_UnitTestCase {

	private int $booking_id;

	protected function setUp(): void {
		parent::setUp();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->booking_id = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_booking' ) );
	}

	private function makeAddon( string $title, array $meta = array(), string $status = 'publish' ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_addon',
				'post_title'  => $title,
				'post_status' => $status,
			)
		);
		update_post_meta( $id, 'mhmrentiva_addon_price', '50' );
		foreach ( $meta as $k => $v ) {
			update_post_meta( $id, $k, $v );
		}

		return (int) $id;
	}

	private function attach( int ...$addon_ids ): void {
		update_post_meta( $this->booking_id, '_mhmrentiva_selected_addons', $addon_ids );
	}

	private function render(): string {
		ob_start();
		try {
			BookingEditMetaBox::render( get_post( $this->booking_id ) );
			return (string) ob_get_contents();
		} finally {
			ob_end_clean();
		}
	}

	/** Every value the form would actually submit for the add-on field. */
	private function postedIds( string $html ): array {
		$ids = array();

		// Checkboxes only count when they are not disabled; hidden fields always count.
		if ( preg_match_all( '/<input type="(checkbox|hidden)" name="mhmrentiva_edit_selected_addons\[\]" value="(\d+)"([^>]*)>/', $html, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $field ) {
				$is_hidden   = 'hidden' === $field[1];
				$is_checked  = false !== strpos( $field[3], 'checked' );
				$is_disabled = false !== strpos( $field[3], 'disabled' );

				if ( $is_hidden || ( $is_checked && ! $is_disabled ) ) {
					$ids[] = (int) $field[2];
				}
			}
		}

		return array_values( array_unique( $ids ) );
	}

	public function test_a_required_service_is_still_posted_back(): void {
		$required = $this->makeAddon( 'Insurance', array( 'mhmrentiva_addon_required' => '1' ) );
		$this->attach( $required );

		$this->assertContains(
			$required,
			$this->postedIds( $this->render() ),
			'A required service renders disabled, so without a hidden field the next save drops it.'
		);
	}

	public function test_a_trashed_but_attached_service_is_still_posted_back(): void {
		$trashed = $this->makeAddon( 'Winter Kit', array(), 'trash' );
		$this->attach( $trashed );

		$this->assertContains(
			$trashed,
			$this->postedIds( $this->render() ),
			'The booking already carries this service; trashing the catalogue entry must not delete it from the booking.'
		);
	}

	/**
	 * NEGATIVE CONTROL. If the fix simply hidden-fields everything attached,
	 * both tests above pass and the screen quietly stops being able to remove
	 * a service at all.
	 */
	public function test_an_ordinary_attached_service_stays_removable(): void {
		$ordinary = $this->makeAddon( 'GPS' );
		$this->attach( $ordinary );

		$html = $this->render();

		$this->assertContains( $ordinary, $this->postedIds( $html ), 'Precondition: it is attached and posted.' );
		$this->assertDoesNotMatchRegularExpression(
			'/<input type="hidden" name="mhmrentiva_edit_selected_addons\[\]" value="' . $ordinary . '"/',
			$html,
			'An ordinary service must stay unremovable-by-hidden-field; clearing its checkbox has to remove it.'
		);
	}
}
