<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Meta;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Core\MetaBoxes\AbstractMetaBox;
use MHMRentiva\Admin\Core\Security\VerifiedRequest;
use MHMRentiva\Admin\Booking\Helpers\Util;
use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Addons\AddonManager;
use MHMRentiva\Admin\Addons\AddonSelection;
use MHMRentiva\Admin\Core\MetaKeys;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Booking Edit Meta Box
 *
 * For editing existing bookings
 */
final class BookingEditMetaBox extends AbstractMetaBox {


	protected static function get_post_type(): string {
		return 'mhmrentiva_booking';
	}

	protected static function get_meta_box_id(): string {
		return 'mhmrentiva_booking_edit';
	}

	protected static function get_title(): string {
		return __( 'Edit Booking Details', 'mhm-rentiva' );
	}

	protected static function get_context(): string {
		return 'normal';
	}

	protected static function get_priority(): string {
		return 'high';
	}

	protected static function get_fields(): array {
		global $post, $pagenow;

		// Display only when editing an existing booking
		if ( $pagenow !== 'post.php' || ! $post || ! $post->ID || $post->post_type !== 'mhmrentiva_booking' ) {
			return array();
		}

		return array(
			'mhmrentiva_booking_edit_fields' => array(
				'title'    => __( 'Edit Booking Details', 'mhm-rentiva' ),
				'context'  => 'normal',
				'priority' => 'high',
				'template' => 'render',
			),
		);
	}

	public static function register(): void {
		// Register meta box
		add_action( 'add_meta_boxes', array( self::class, 'add_meta_boxes' ) );

		// Scripts and styles
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_scripts' ) );

		// Save handler - Higher priority to run before other save hooks
		add_action( 'save_post', array( self::class, 'save_booking_details' ), 5 );
	}

	public static function add_meta_boxes(): void {
		global $post, $pagenow;

		// Display only when editing an existing booking
		if ( $pagenow !== 'post.php' || ! $post || ! $post->ID || $post->post_type !== 'mhmrentiva_booking' ) {
			return;
		}

		add_meta_box(
			self::get_meta_box_id(),
			self::get_title(),
			array( self::class, 'render' ),
			self::get_post_type(),
			self::get_context(),
			self::get_priority()
		);
	}

	public static function enqueue_scripts( string $hook ): void {
		global $post_type;

		// Load assets only on the booking edit screen
		if ( $hook === 'post.php' && $post_type === 'mhmrentiva_booking' ) {
			wp_enqueue_style(
				'mhm-rentiva-booking-edit-meta',
				MHMRENTIVA_PLUGIN_URL . 'assets/css/admin/booking-edit-meta.css',
				array( 'mhm-rentiva-css-variables' ),
				MHMRENTIVA_VERSION
			);

			wp_enqueue_script(
				'mhm-rentiva-booking-edit-meta',
				MHMRENTIVA_PLUGIN_URL . 'assets/js/admin/booking-edit-meta.js',
				array( 'jquery' ),
				MHMRENTIVA_VERSION,
				true
			);

			// Localize script for AJAX usage
			wp_localize_script(
				'mhm-rentiva-booking-edit-meta',
				'mhmBookingEdit',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'mhmrentiva_booking_edit_nonce' ),
					'text'    => array(
						'saving'              => __( 'Saving...', 'mhm-rentiva' ),
						'error'               => __( 'An error occurred', 'mhm-rentiva' ),
						'success'             => __( 'Booking updated', 'mhm-rentiva' ),
						'currentLicensePlate' => __( 'Current License Plate:', 'mhm-rentiva' ),
					),
				)
			);
		}
	}

	public static function render( \WP_Post $post, array $args = array() ): void {
		wp_nonce_field( 'mhmrentiva_booking_edit_action', 'mhmrentiva_booking_edit_meta_nonce' );

		// Fetch current booking data
		$vehicle_id   = get_post_meta( $post->ID, '_mhmrentiva_vehicle_id', true ) ?: get_post_meta( $post->ID, '_mhmrentiva_booking_vehicle_id', true );
		$pickup_date  = get_post_meta( $post->ID, '_mhmrentiva_pickup_date', true ) ?: get_post_meta( $post->ID, '_mhmrentiva_booking_pickup_date', true );
		$pickup_time  = get_post_meta( $post->ID, '_mhmrentiva_start_time', true ) ?: get_post_meta( $post->ID, '_mhmrentiva_pickup_time', true ) ?: get_post_meta( $post->ID, '_mhmrentiva_booking_pickup_time', true );
		$dropoff_date = get_post_meta( $post->ID, '_mhmrentiva_dropoff_date', true ) ?: get_post_meta( $post->ID, '_mhmrentiva_booking_dropoff_date', true );
		$dropoff_time = get_post_meta( $post->ID, '_mhmrentiva_end_time', true ) ?: get_post_meta( $post->ID, '_mhmrentiva_dropoff_time', true ) ?: get_post_meta( $post->ID, '_mhmrentiva_booking_dropoff_time', true );

		$guests = get_post_meta( $post->ID, '_mhmrentiva_guests', true ) ?: get_post_meta( $post->ID, '_mhmrentiva_booking_guests', true ) ?: 1;
		$status = Status::get( $post->ID );

		// Additional booking information
		$display_id        = mhmrentiva_get_display_id( $post->ID );
		$booking_prefix    = __( 'BK-', 'mhm-rentiva' );
		$booking_reference = $booking_prefix . str_pad( (string) $display_id, 6, '0', STR_PAD_LEFT );
		$booking_type      = get_post_meta( $post->ID, '_mhmrentiva_booking_type', true ) ?: 'online';
		$rental_days       = get_post_meta( $post->ID, '_mhmrentiva_rental_days', true );
		$special_notes     = get_post_meta( $post->ID, '_mhmrentiva_special_notes', true ) ?: '';

		// Calculate rental days if not set
		if ( empty( $rental_days ) && $pickup_date && $dropoff_date ) {
			$start       = new \DateTime( $pickup_date );
			$end         = new \DateTime( $dropoff_date );
			$rental_days = $start->diff( $end )->days ?: 1;
		}

		echo '<div class="mhm-booking-edit-form">';

		// Booking reference and type (info grid - similar to deposit info grid)
		echo '<div class="mhm-booking-info-grid">';

		echo '<div class="mhm-booking-info-item">';
		echo '<div class="mhm-booking-info-label">' . esc_html__( 'Booking Reference', 'mhm-rentiva' ) . '</div>';
		echo '<div class="mhm-booking-info-value">';
		echo '<span class="mhm-booking-reference-badge">' . esc_html( $booking_reference ) . '</span>';
		echo '</div>';
		echo '</div>';

		echo '<div class="mhm-booking-info-item">';
		echo '<div class="mhm-booking-info-label">' . esc_html__( 'Booking Type', 'mhm-rentiva' ) . '</div>';
		echo '<div class="mhm-booking-info-value">';
		$booking_type_class = $booking_type === 'manual' ? 'manual' : 'online';
		$booking_type_label = $booking_type === 'manual' ? __( 'Manual', 'mhm-rentiva' ) : __( 'Online', 'mhm-rentiva' );
		echo '<span class="mhm-booking-type-badge ' . esc_attr( $booking_type_class ) . '">' . esc_html( $booking_type_label ) . '</span>';
		echo '</div>';
		echo '</div>';

		echo '</div>';

		// Vehicle selection (editable)
		echo '<div class="mhm-field-group">';
		echo '<label for="mhmrentiva_edit_vehicle_id" class="mhm-field-label">' . esc_html__( 'Vehicle', 'mhm-rentiva' ) . '</label>';

		// Get all available vehicles
		$vehicles = get_posts(
			array(
				'post_type'      => 'mhmrentiva_vehicle',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		echo '<select id="mhmrentiva_booking_edit_vehicle_id" name="mhmrentiva_edit_vehicle_id" class="mhm-field-select">';
		echo '<option value="">' . esc_html__( 'Select Vehicle', 'mhm-rentiva' ) . '</option>';

		foreach ( $vehicles as $vehicle_option ) {
			$plate         = get_post_meta( $vehicle_option->ID, '_mhmrentiva_license_plate', true );
			$plate_display = $plate ? ' (' . esc_html( $plate ) . ')' : '';
			$selected      = selected( $vehicle_id, $vehicle_option->ID, false );
			echo '<option value="' . esc_attr( $vehicle_option->ID ) . '" ' . esc_attr( $selected ) . '>' . esc_html( $vehicle_option->post_title ) . esc_html( $plate_display ) . '</option>';
		}

		echo '</select>';

		// Plate is shown inside the vehicle dropdown option and updated dynamically by JS

		echo '</div>';

		// Booking details
		echo '<div class="mhm-booking-details">';
		echo '<h4>' . esc_html__( 'Booking Details', 'mhm-rentiva' ) . '</h4>';

		echo '<div class="mhm-field-row">';
		echo '<div class="mhm-field-group mhm-field-half">';
		echo '<label for="mhmrentiva_edit_pickup_date" class="mhm-field-label">' . esc_html__( 'Pickup Date', 'mhm-rentiva' ) . '</label>';
		echo '<input type="date" id="mhmrentiva_booking_edit_pickup_date" name="mhmrentiva_edit_pickup_date" class="mhm-field-input" value="' . esc_attr( $pickup_date ) . '">';
		echo '</div>';

		echo '<div class="mhm-field-group mhm-field-half">';
		echo '<label for="mhmrentiva_edit_pickup_time" class="mhm-field-label">' . esc_html__( 'Pickup Time', 'mhm-rentiva' ) . '</label>';
		echo '<input type="time" id="mhmrentiva_booking_edit_pickup_time" name="mhmrentiva_edit_pickup_time" class="mhm-field-input" value="' . esc_attr( $pickup_time ) . '">';
		echo '</div>';
		echo '</div>';

		echo '<div class="mhm-field-row">';
		echo '<div class="mhm-field-group mhm-field-half">';
		echo '<label for="mhmrentiva_edit_dropoff_date" class="mhm-field-label">' . esc_html__( 'Return Date', 'mhm-rentiva' ) . '</label>';
		echo '<input type="date" id="mhmrentiva_booking_edit_dropoff_date" name="mhmrentiva_edit_dropoff_date" class="mhm-field-input" value="' . esc_attr( $dropoff_date ) . '">';
		echo '</div>';

		echo '<div class="mhm-field-group mhm-field-half">';
		echo '<label for="mhmrentiva_edit_dropoff_time" class="mhm-field-label">' . esc_html__( 'Return Time', 'mhm-rentiva' ) . '</label>';
		echo '<input type="time" id="mhmrentiva_booking_edit_dropoff_time" name="mhmrentiva_edit_dropoff_time" class="mhm-field-input" value="' . esc_attr( $dropoff_time ) . '">';
		echo '</div>';
		echo '</div>';

		echo '<div class="mhm-field-row">';
		echo '<div class="mhm-field-group mhm-field-half">';
		echo '<label for="mhmrentiva_edit_guests" class="mhm-field-label">' . esc_html__( 'Number of Guests', 'mhm-rentiva' ) . '</label>';
		echo '<input type="number" id="mhmrentiva_booking_edit_guests" name="mhmrentiva_edit_guests" class="mhm-field-input" value="' . esc_attr( $guests ) . '" min="1" max="10">';
		echo '</div>';

		echo '<div class="mhm-field-group mhm-field-half">';
		echo '<label for="mhmrentiva_booking_edit_status" class="mhm-field-label">' . esc_html__( 'Status', 'mhm-rentiva' ) . '</label>';
		echo '<select id="mhmrentiva_booking_edit_status" name="mhmrentiva_edit_status" class="mhm-field-select">';
		echo '<option value="pending"' . selected( $status, 'pending', false ) . '>' . esc_html__( 'Pending', 'mhm-rentiva' ) . '</option>';
		echo '<option value="confirmed"' . selected( $status, 'confirmed', false ) . '>' . esc_html__( 'Confirmed', 'mhm-rentiva' ) . '</option>';
		echo '<option value="in_progress"' . selected( $status, 'in_progress', false ) . '>' . esc_html__( 'In Progress', 'mhm-rentiva' ) . '</option>';
		echo '<option value="completed"' . selected( $status, 'completed', false ) . '>' . esc_html__( 'Completed', 'mhm-rentiva' ) . '</option>';
		echo '<option value="cancelled"' . selected( $status, 'cancelled', false ) . '>' . esc_html__( 'Cancelled', 'mhm-rentiva' ) . '</option>';
		echo '</select>';
		echo '</div>';
		echo '</div>';

		// Additional services selection
		echo '<div class="mhm-field-group">';
		echo '<label class="mhm-field-label">' . esc_html__( 'Additional Services', 'mhm-rentiva' ) . '</label>';

		// Read the selection FIRST: the offered list is derived from it below.
		$selected_addons = get_post_meta( $post->ID, '_mhmrentiva_selected_addons', true )
	    ?: get_post_meta( $post->ID, 'mhmrentiva_selected_addons', true )
	    ?: array();

		$selected_ids = array_map( 'intval', (array) $selected_addons );
		$selection    = AddonSelection::of( $selected_ids, (array) get_post_meta( $post->ID, MetaKeys::BOOKING_ADDON_QUANTITIES, true ) );

		// Fetch current add-ons
		$addons = get_posts(
			array(
				'post_type'      => 'mhmrentiva_addon',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			)
		);

		// The union below can only work on services this query returned, and it
		// asks for 'publish' only -- so a service the operator moved to the
		// TRASH was never a candidate for "already attached" in the first
		// place. It vanished from the screen, the form posted without it, and
		// the next save deleted it from the booking: the exact failure the
		// comment below describes, one status further along. Anything the
		// booking already carries is pulled back in by id regardless of status.
		$pool_ids = array_map( 'intval', wp_list_pluck( $addons, 'ID' ) );
		$missing  = array_values( array_diff( $selected_ids, $pool_ids ) );

		if ( ! empty( $missing ) ) {
			$addons = array_merge(
				$addons,
				get_posts(
					array(
						'post_type'      => 'mhmrentiva_addon',
						'post__in'       => $missing,
						'post_status'    => array( 'publish', 'pending', 'draft', 'private', 'future', 'trash' ),
						'posts_per_page' => -1,
						'orderby'        => 'menu_order',
						'order'          => 'ASC',
					)
				)
			);
		}

		// Offered = sellable UNION already-attached, and the union is the whole
		// point. A booking taken last month may carry a service switched off
		// since; drop it from this screen and its checkbox disappears, the form
		// posts without it, and saving the booking deletes a service the
		// customer paid for. Filtering subtracts from what may be ADDED, never
		// from what is already ON the booking.
		$available_addons = array();
		foreach ( $addons as $addon ) {
			$addon_id    = (int) $addon->ID;
			$is_attached = in_array( $addon_id, $selected_ids, true );

			if ( ! $is_attached && ! \MHMRentiva\Admin\Addons\AddonManager::is_sellable( $addon_id ) ) {
				continue;
			}

			$title = $addon->post_title;

			// Say why it is here. Without this the operator sees a service the
			// add-ons screen shows as Pasif and has no way to tell that it is
			// listed because the booking already carries it.
			if ( $is_attached && ! \MHMRentiva\Admin\Addons\AddonManager::is_sellable( $addon_id ) ) {
				/* translators: %s: additional service name. */
				$title = sprintf( __( '%s (inactive)', 'mhm-rentiva' ), $title );
			}

			$available_addons[] = array(
				'id'          => $addon_id,
				'title'       => $title,
				'price'       => get_post_meta( $addon_id, 'mhmrentiva_addon_price', true ) ?: '0',
				'description' => $addon->post_excerpt,
				'required'    => (bool) get_post_meta( $addon_id, 'mhmrentiva_addon_required', true ),
				'max'         => AddonSelection::max_quantity( $addon_id ),
			);
		}

		if ( ! empty( $available_addons ) ) {
			echo '<div class="mhm-addon-selection">';
			echo '<p class="description">' . esc_html__( 'Select the additional services needed for this booking.', 'mhm-rentiva' ) . '</p>';

			echo '<div class="mhm-addon-grid">';
			foreach ( $available_addons as $addon ) {
				$is_attached   = in_array( (int) $addon['id'], $selected_ids, true );
				$checked       = $is_attached ? 'checked' : '';
				$checked      .= $addon['required'] ? ' disabled' : '';
				$required_text = $addon['required'] ? ' <span class="required">*</span>' : '';

				echo '<div class="mhm-addon-card">';
				echo '<label class="mhm-addon-item">';
				echo '<input type="checkbox" name="mhmrentiva_edit_selected_addons[]" value="' . esc_attr( $addon['id'] ) . '" class="mhm-addon-checkbox" data-price="' . esc_attr( $addon['price'] ) . '" ' . esc_attr( $checked ) . '>';

				// A disabled control is not submitted, so a required service
				// that is already on the booking would be dropped by the next
				// save -- save_booking_details() treats the posted list as the
				// whole truth, which is right for a checkbox the operator can
				// actually clear. Only the ones the form CANNOT post get this;
				// an ordinary attached service must stay removable by simply
				// unchecking it.
				if ( $is_attached && $addon['required'] ) {
					echo '<input type="hidden" name="mhmrentiva_edit_selected_addons[]" value="' . esc_attr( (string) $addon['id'] ) . '">';
				}
				echo '<div class="mhm-addon-content">';
				echo '<div class="mhm-addon-header">';
				echo '<span class="mhm-addon-title">' . esc_html( $addon['title'] ) . wp_kses_post( $required_text ) . '</span>';
				// Canonical formatting; this used to pin the symbol to the right.
				echo '<span class="mhm-addon-price">+ ' . esc_html( \MHMRentiva\Admin\Core\CurrencyHelper::format_price( (float) $addon['price'], 2 ) ) . '</span>';
				echo '</div>';
				if ( ! empty( $addon['description'] ) ) {
					echo '<div class="mhm-addon-description">' . esc_html( $addon['description'] ) . '</div>';
				}
				// Sold more than once: the quantity this booking carries, under
				// the title (after the checkbox, so the box stays the label's control).
				// A booked quantity above a maximum lowered since stays editable.
				$quantity = max( 1, $selection->quantity( (int) $addon['id'] ) );
				$cap      = max( (int) $addon['max'], $quantity );
				if ( $cap > 1 ) {
					echo '<div class="mhm-addon-qty-row">' . esc_html__( 'Quantity', 'mhm-rentiva' ) . ' ';
					echo '<input type="number" name="mhmrentiva_edit_addon_qty[' . esc_attr( (string) $addon['id'] ) . ']" class="mhm-addon-qty small-text" min="1" max="' . esc_attr( (string) $cap ) . '" step="1" value="' . esc_attr( (string) $quantity ) . '" aria-label="' . esc_attr( sprintf( /* translators: %s: additional service name. */ __( 'Quantity: %s', 'mhm-rentiva' ), $addon['title'] ) ) . '">';
					echo '</div>';
				}
				echo '</div>';
				echo '</label>';
				echo '</div>';
			}
			echo '</div>';

			echo '<div class="mhm-addon-total" style="display: none;">';
			echo '<strong>' . esc_html__( 'Additional Services Total:', 'mhm-rentiva' ) . ' <span class="mhm-addon-total-amount">0,00 ' . esc_html( \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_symbol() ) . '</span></strong>';
			echo '</div>';

			echo '</div>';
		} else {
			echo '<p class="description">' . esc_html__( 'No additional services available.', 'mhm-rentiva' ) . '</p>';
		}
		echo '</div>';

		// Special notes/requests
		echo '<div class="mhm-field-group mhm-special-notes-section">';
		echo '<label for="mhmrentiva_edit_special_notes" class="mhm-field-label">' . esc_html__( 'Special Notes / Requests', 'mhm-rentiva' ) . '</label>';
		echo '<textarea id="mhmrentiva_booking_edit_special_notes" name="mhmrentiva_edit_special_notes" class="mhm-field-textarea" rows="4" placeholder="' . esc_attr__( 'Enter any special notes or customer requests...', 'mhm-rentiva' ) . '">' . esc_textarea( $special_notes ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Add any special notes or customer requests for this booking.', 'mhm-rentiva' ) . '</p>';
		echo '</div>';

		echo '</div>';

		echo '</div>';
	}

	/**
	 * The submitted booking edit, or null when this request carries no nonce
	 * that authorises editing this booking.
	 *
	 * Two forms can legitimately reach save_post: the metabox's own form (which
	 * renders mhmrentiva_booking_edit_meta_nonce) and the classic editor's Update button
	 * (which sends _wpnonce for one of core's update-post actions). Both answers
	 * live here, each as its own early return, so the caller keeps a single `if`
	 * instead of accumulating a validity flag across branching conditions.
	 *
	 * The verified payload comes back with the verdict rather than being read
	 * again by the caller: that keeps the nonce check and the superglobal access
	 * in one scope, which is the property VerifiedRequest exists to preserve.
	 */
	private static function verified_edit_request( int $post_id ): ?VerifiedRequest {
		$own_nonce = sanitize_text_field( wp_unslash( $_POST['mhmrentiva_booking_edit_meta_nonce'] ?? '' ) );
		if ( wp_verify_nonce( $own_nonce, 'mhmrentiva_booking_edit_action' ) ) {
			return VerifiedRequest::from( $_POST );
		}

		$core_nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		foreach ( array( 'update-post_' . $post_id, 'update-post', 'post_' . $post_id ) as $core_action ) {
			if ( wp_verify_nonce( $core_nonce, $core_action ) ) {
				return VerifiedRequest::from( $_POST );
			}
		}

		return null;
	}

	/**
	 * Persist booking edits.
	 */
	public static function save_booking_details( int $post_id ): void {
		// Autosave and revision check
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// A valid nonce is REQUIRED. There used to be a "if our form fields are present, save
		// anyway" fallback here; it made the nonce effectively optional, so any cross-site POST
		// carrying those field names could persist booking edits on behalf of a logged-in editor
		// (CSRF). The metabox always renders wp_nonce_field() (see render()), and the classic
		// editor additionally sends _wpnonce, so every legitimate save has one of the two.
		$request = self::verified_edit_request( $post_id );
		if ( null === $request ) {
			return;
		}

		// Capability check
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Ensure post type is vehicle_booking
		if ( get_post_type( $post_id ) !== 'mhmrentiva_booking' ) {
			return;
		}

		// Fetch and persist data
		$new_vehicle_id = $request->int( 'mhmrentiva_edit_vehicle_id' );
		$pickup_date    = $request->text( 'mhmrentiva_edit_pickup_date' );
		$pickup_time    = $request->text( 'mhmrentiva_edit_pickup_time' );
		$dropoff_date   = $request->text( 'mhmrentiva_edit_dropoff_date' );
		$dropoff_time   = $request->text( 'mhmrentiva_edit_dropoff_time' );
		$guests         = max( 1, $request->int( 'mhmrentiva_edit_guests', 1 ) );
		$status         = $request->text( 'mhmrentiva_edit_status', 'pending' );

		// Get special notes - always save if field exists
		$special_notes = $request->textarea( 'mhmrentiva_edit_special_notes' );

		// This handler runs first (priority 5) and writes the vehicle and dates
		// before anything else does, so the availability check has to come before
		// that. A refused edit writes nothing; see guard_edit_availability().
		if ( ! BookingMeta::guard_edit_availability( $post_id, $new_vehicle_id, $pickup_date, $pickup_time, $dropoff_date, $dropoff_time, $status ) ) {
			return;
		}

		// Update vehicle if changed
		$old_vehicle_id = get_post_meta( $post_id, '_mhmrentiva_vehicle_id', true );
		if ( $new_vehicle_id > 0 && $new_vehicle_id != $old_vehicle_id ) {
			// Verify vehicle exists
			$vehicle_post = get_post( $new_vehicle_id );
			if ( $vehicle_post && $vehicle_post->post_type === 'mhmrentiva_vehicle' ) {
				update_post_meta( $post_id, '_mhmrentiva_vehicle_id', $new_vehicle_id );
				update_post_meta( $post_id, '_mhmrentiva_booking_vehicle_id', $new_vehicle_id ); // Legacy support

				// Update booking title to reflect new vehicle
				$new_title = sprintf(
					/* translators: %s: vehicle title. */
					__( 'Booking - %s', 'mhm-rentiva' ),
					$vehicle_post->post_title
				);
				wp_update_post(
					array(
						'ID'         => (int) $post_id,
						'post_title' => $new_title,
					)
				);

				// Recalculate booking costs if dates are set
				if ( $pickup_date && $dropoff_date ) {
					\MHMRentiva\Admin\Booking\Meta\BookingMeta::recalculate_booking_costs( $post_id, $pickup_date, $dropoff_date );
				}
			}
		}

		// Process selected add-ons. Not filter_sellable(): a booking may carry a
		// service switched off since, and this save must not delete it. Each
		// quantity is held to its add-on's maximum; the rows are priced for the
		// rental the way every other path prices them (they used to be written
		// at the unit price, so a per-day service read as one day).
		$selected_addons = array();
		$quantities      = array();
		$posted_qty      = $request->arr( 'mhmrentiva_edit_addon_qty' );
		// What the booking already carries is kept: a maximum lowered since
		// must not re-price a booking on its next unrelated save.
		$stored = AddonSelection::for_booking( $post_id );
		foreach ( $request->intList( 'mhmrentiva_edit_selected_addons' ) as $addon_id ) {
			if ( 'mhmrentiva_addon' !== get_post_type( $addon_id ) || in_array( $addon_id, $selected_addons, true ) ) {
				continue;
			}
			$selected_addons[] = $addon_id;
			$booked            = $stored->quantity( $addon_id );
			$raw               = $posted_qty[ $addon_id ] ?? max( 1, $booked );
			$cap               = max( AddonSelection::max_quantity( $addon_id ), $booked );
			$quantity          = is_scalar( $raw ) ? max( 1, min( (int) $raw, $cap ) ) : max( 1, $booked );
			if ( $quantity > 1 ) {
				$quantities[ $addon_id ] = $quantity;
			}
		}
		$selection = AddonSelection::of( $selected_addons, $quantities );

		// Update meta values
		update_post_meta( $post_id, '_mhmrentiva_pickup_date', $pickup_date );
		update_post_meta( $post_id, '_mhmrentiva_start_time', $pickup_time );
		update_post_meta( $post_id, '_mhmrentiva_pickup_time', $pickup_time );
		update_post_meta( $post_id, '_mhmrentiva_dropoff_date', $dropoff_date );
		update_post_meta( $post_id, '_mhmrentiva_end_time', $dropoff_time );
		update_post_meta( $post_id, '_mhmrentiva_dropoff_time', $dropoff_time );
		update_post_meta( $post_id, '_mhmrentiva_guests', $guests );
		update_post_meta( $post_id, '_mhmrentiva_special_notes', $special_notes );

		// Recalculate rental days if dates changed
		if ( $pickup_date && $dropoff_date ) {
			$start       = new \DateTime( $pickup_date );
			$end         = new \DateTime( $dropoff_date );
			$rental_days = $start->diff( $end )->days ?: 1;
			update_post_meta( $post_id, '_mhmrentiva_rental_days', $rental_days );
		}

		// Save add-on meta data
		$addon_details = AddonManager::build_addon_details(
			$selection->ids(),
			array(
				'rental_days'      => (int) get_post_meta( $post_id, '_mhmrentiva_rental_days', true ),
				'addon_quantities' => $selection->quantities(),
			)
		);
		$selection->store( $post_id );
		update_post_meta( $post_id, '_mhmrentiva_addon_details', $addon_details );
		update_post_meta( $post_id, '_mhmrentiva_addon_total', (float) array_sum( array_column( $addon_details, 'line_total' ) ) );

		// Update status
		$old_status = get_post_meta( $post_id, '_mhmrentiva_status', true );
		Status::update_status( $post_id, $status, get_current_user_id() );

		// Append automatic status change note
		if ( $old_status !== $status ) {
			\MHMRentiva\Admin\Booking\Meta\BookingMeta::auto_add_status_change_note( $post_id, $old_status, $status );
		}

		// Update legacy meta keys for backward compatibility
		update_post_meta( $post_id, '_mhmrentiva_booking_pickup_date', $pickup_date );
		update_post_meta( $post_id, '_mhmrentiva_booking_pickup_time', $pickup_time );
		update_post_meta( $post_id, '_mhmrentiva_booking_dropoff_date', $dropoff_date );
		update_post_meta( $post_id, '_mhmrentiva_booking_dropoff_time', $dropoff_time );
		update_post_meta( $post_id, '_mhmrentiva_booking_guests', $guests );

		BookingMeta::sync_booking_timestamps( $post_id, $pickup_date, $pickup_time, $dropoff_date, $dropoff_time );
		BookingMeta::invalidate_edit_caches( (int) $old_vehicle_id, (int) get_post_meta( $post_id, '_mhmrentiva_vehicle_id', true ) );
	}
}
