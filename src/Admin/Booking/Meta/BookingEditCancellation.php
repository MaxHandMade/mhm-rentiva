<?php
/**
 * Cancelling a booking from its edit form.
 *
 * @package MHM_Rentiva
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Actions\DepositManagementAjax;
use MHMRentiva\Admin\Booking\Core\BookingPricingInputs;
use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Booking\Helpers\CancellationHandler;
use MHMRentiva\Admin\Core\Security\VerifiedRequest;
use MHMRentiva\Admin\Payment\Core\MoneyAuthorization;
use MHMRentiva\Admin\Payment\Core\RefundStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Choosing "Cancelled" in the status list and pressing Update does what the
 * "Cancel booking" button does (spec PR 3b "Tek iptal yolu", plan PR 3b-2).
 *
 * Before, the form only flipped the status (fonksiyon-taramasi.md §8): no
 * refund, no cancellation record, two mails instead of five, no
 * mhmrentiva_booking_cancelled for Pro. Now the edit form hands the booking to
 * CancellationHandler::cancel_booking() with the button's own arguments.
 *
 * Such an Update is a cancellation request and nothing else: the handler
 * refunds from the STORED total, frees the STORED dates and mails the STORED
 * booking, so neither saver may write vehicle, dates, add-ons, guests or
 * price first -- a re-priced total would open a refund for money that was
 * never taken (Fable plan audit C1). Special notes are written; they move no
 * money.
 *
 * Request-scoped: the priority-5 saver runs the request, the priority-10 saver
 * asks handled() and leaves the booking alone, refused or not.
 */
final class BookingEditCancellation {

	/** Statuses the button cancels from (DepositManagementAjax::cancel_booking()). */
	private const CANCELLABLE = array( 'pending', 'confirmed' );

	/** @var array<int, true> */
	private static array $handled = array();

	/**
	 * Is this Update a cancellation request: the form posts "cancelled" and
	 * the booking is not cancelled yet?
	 */
	public static function requested( int $post_id, VerifiedRequest $request ): bool {
		// The same status the savers write: the edit box's field, or -- when it
		// is empty -- the older mhmrentiva_booking_status that BookingMeta's
		// saver still accepts. Read here too, or that field would reach
		// Status::update_status() past the handler and its money check (Codex
		// code audit 3b-2, Important 1).
		$status = $request->text( 'mhmrentiva_edit_status' );
		if ( '' === $status ) {
			$status = $request->text( 'mhmrentiva_booking_status' );
		}
		if ( Status::CANCELLED !== $status ) {
			return false;
		}
		wp_cache_delete( $post_id, 'post_meta' );
		return Status::CANCELLED !== Status::get( $post_id );
	}

	/**
	 * Was the cancellation request of this booking handled in this request
	 * (cancelled or refused)? The second saver writes nothing then.
	 */
	public static function handled( int $post_id ): bool {
		return isset( self::$handled[ $post_id ] );
	}

	/**
	 * May this actor cancel from the edit screen? The handler's own question
	 * (surface 'cancel'). User decision 2026-11-07: only an actor with money
	 * rights cancels; nobody else is offered "Cancelled".
	 */
	public static function actor_may_cancel( int $post_id ): bool {
		return MoneyAuthorization::mayMoveMoney( $post_id, get_current_user_id(), 'cancel' );
	}

	/**
	 * Did the form post a price input other than the stored one? Compared the
	 * way BookingPricingInputs reads them, so an untouched form is never
	 * reported as changed.
	 */
	public static function form_changes_money( int $post_id, VerifiedRequest $request ): bool {
		$posted = BookingPricingInputs::form_view(
			$post_id,
			array(
				'vehicle'   => $request->int( 'mhmrentiva_edit_vehicle_id' ),
				'pickup'    => $request->text( 'mhmrentiva_edit_pickup_date' ),
				'start'     => $request->text( 'mhmrentiva_edit_pickup_time' ),
				'dropoff'   => $request->text( 'mhmrentiva_edit_dropoff_date' ),
				'end'       => $request->text( 'mhmrentiva_edit_dropoff_time' ),
				'selection' => BookingEditMetaBox::posted_selection( $post_id, $request ),
			)
		);

		if ( BookingPricingInputs::form_view( $post_id ) !== $posted ) {
			return true;
		}

		// Guests is not a price input, but a cancellation request skips it too.
		return $request->has( 'mhmrentiva_edit_guests' )
			&& max( 1, $request->int( 'mhmrentiva_edit_guests', 1 ) ) !== max( 1, (int) get_post_meta( $post_id, '_mhmrentiva_guests', true ) );
	}

	/**
	 * Run the cancellation request. Writes the special notes, then cancels
	 * through the handler or explains why not; the status and every price
	 * input stay as stored either way.
	 */
	public static function run( int $post_id, VerifiedRequest $request ): void {
		// Once per booking and request: a listener that re-saves the booking
		// must not cancel it twice (Codex code audit 3b-2, Important 2).
		if ( isset( self::$handled[ $post_id ] ) ) {
			return;
		}
		self::$handled[ $post_id ] = true;

		$ignored = self::form_changes_money( $post_id, $request )
			? ' ' . __( 'Other changes in the form were not saved.', 'mhm-rentiva' )
			: '';

		if ( $request->has( 'mhmrentiva_edit_special_notes' ) ) {
			// textarea() is unslashed; the meta API unslashes again.
			update_post_meta( $post_id, '_mhmrentiva_special_notes', wp_slash( $request->textarea( 'mhmrentiva_edit_special_notes' ) ) );
		}

		$status = Status::get( $post_id );
		if ( ! in_array( $status, self::CANCELLABLE, true ) ) {
			BookingEditNotice::queue(
				$post_id,
				( Status::IN_PROGRESS === $status
					? __( 'A rental that has started cannot be cancelled here.', 'mhm-rentiva' )
					: __( 'This booking cannot be cancelled.', 'mhm-rentiva' ) ) . $ignored
			);
			return;
		}

		// The button's call (DepositManagementAjax::cancel_booking()): forced,
		// because the deadline is the customer's policy, not the operator's.
		$result = CancellationHandler::cancel_booking( $post_id, get_current_user_id(), '', true, false, 'admin_deposit' );

		if ( is_wp_error( $result ) ) {
			if ( 'already_cancelled' !== $result->get_error_code() ) {
				BookingEditNotice::queue( $post_id, $result->get_error_message() . $ignored );
			}
			return;
		}

		DepositManagementAjax::add_booking_log(
			$post_id,
			'booking_cancelled',
			array(
				'cancelled_by' => get_current_user_id(),
			)
		);

		wp_cache_delete( $post_id, 'post_meta' );
		if ( ! empty( $result['problems'] ) || RefundStatus::FAILED === RefundStatus::get( $post_id ) ) {
			BookingEditNotice::queue( $post_id, __( 'Booking cancelled, but the refund could not be completed. See the refund status on this screen.', 'mhm-rentiva' ) . $ignored, 'warning' );
			return;
		}

		if ( '' !== $ignored ) {
			BookingEditNotice::queue( $post_id, __( 'The booking was cancelled. Other changes in the form were not saved; edit the booking first, then cancel.', 'mhm-rentiva' ), 'warning' );
		}
	}

	/**
	 * @internal Tests only.
	 */
	public static function reset(): void {
		self::$handled = array();
	}
}
