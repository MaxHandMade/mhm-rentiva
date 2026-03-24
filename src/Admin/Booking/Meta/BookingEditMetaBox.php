<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Meta;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Core\MetaBoxes\AbstractMetaBox;
use MHMRentiva\Admin\Booking\Helpers\Util;
use MHMRentiva\Admin\Booking\Core\Status;

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
		return 'vehicle_booking';
	}

	protected static function get_meta_box_id(): string {
		return 'mhm_rentiva_booking_edit';
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
		if ( $pagenow !== 'post.php' || ! $post || ! $post->ID || $post->post_type !== 'vehicle_booking' ) {
			return array();
		}

		return array(
			'mhm_booking_edit_fields' => array(
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
		if ( $pagenow !== 'post.php' || ! $post || ! $post->ID || $post->post_type !== 'vehicle_booking' ) {
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
		if ( $hook === 'post.php' && $post_type === 'vehicle_booking' ) {
			wp_enqueue_style(
				'mhm-booking-edit-meta',
				MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/booking-edit-meta.css',
				array(),
				MHM_RENTIVA_VERSION
			);

			wp_enqueue_script(
				'mhm-booking-edit-meta',
				MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/booking-edit-meta.js',
				array( 'jquery' ),
				MHM_RENTIVA_VERSION,
				true
			);

			// Localize script for AJAX usage
			wp_localize_script(
				'mhm-booking-edit-meta',
				'mhmBookingEdit',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'mhm_booking_edit_nonce' ),
					'text'    => array(
						'saving'  => __( 'Saving...', 'mhm-rentiva' ),
						'error'   => __( 'An error occurred', 'mhm-rentiva' ),
						'success' => __( 'Booking updated', 'mhm-rentiva' ),
					),
				)
			);
		}
	}

	public static function render( \WP_Post $post, array $args = array() ): void {
		wp_nonce_field( 'mhm_booking_edit_action', 'mhm_booking_edit_meta_nonce' );

		// Fetch current booking data
		$vehicle_id   = get_post_meta( $post->ID, '_mhm_vehicle_id', true ) ?: get_post_meta( $post->ID, '_booking_vehicle_id', true );
		$pickup_date  = get_post_meta( $post->ID, '_mhm_pickup_date', true ) ?: get_post_meta( $post->ID, '_booking_pickup_date', true );
		$pickup_time  = get_post_meta( $post->ID, '_mhm_start_time', true ) ?: get_post_meta( $post->ID, '_mhm_pickup_time', true ) ?: get_post_meta( $post->ID, '_booking_pickup_time', true );
		$dropoff_date = get_post_meta( $post->ID, '_mhm_dropoff_date', true ) ?: get_post_meta( $post->ID, '_booking_dropoff_date', true );
		$dropoff_time = get_post_meta( $post->ID, '_mhm_end_time', true ) ?: get_post_meta( $post->ID, '_mhm_dropoff_time', true ) ?: get_post_meta( $post->ID, '_booking_dropoff_time', true );

		$guests = get_post_meta( $post->ID, '_mhm_guests', true ) ?: get_post_meta( $post->ID, '_booking_guests', true ) ?: 1;
		$status = Status::get( $post->ID );

		// Additional booking information
		$display_id        = mhm_rentiva_get_display_id( $post->ID );
		$booking_prefix    = __( 'BK-', 'mhm-rentiva' );
		$booking_reference = $booking_prefix . str_pad( (string) $display_id, 6, '0', STR_PAD_LEFT );
		$booking_type  = get_post_meta( $post->ID, '_mhm_booking_type', true ) ?: 'online';
		$rental_days   = get_post_meta( $post->ID, '_mhm_rental_days', true );
		$special_notes = get_post_meta( $post->ID, '_mhm_special_notes', true ) ?: '';

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
		echo '<label for="mhm_edit_vehicle_id" class="mhm-field-label">' . esc_html__( 'Vehicle', 'mhm-rentiva' ) . '</label>';

		// Get all available vehicles
		$vehicles = get_posts(
			array(
				'post_type'      => 'vehicle',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		echo '<select id="mhm_booking_edit_vehicle_id" name="mhm_edit_vehicle_id" class="mhm-field-select">';
		echo '<option value="">' . esc_html__( 'Select Vehicle', 'mhm-rentiva' ) . '</option>';

		foreach ( $vehicles as $vehicle_option ) {
			$plate         = get_post_meta( $vehicle_option->ID, '_mhm_rentiva_license_plate', true );
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
		echo '<label for="mhm_edit_pickup_date" class="mhm-field-label">' . esc_html__( 'Pickup Date', 'mhm-rentiva' ) . '</label>';
		echo '<input type="date" id="mhm_booking_edit_pickup_date" name="mhm_edit_pickup_date" class="mhm-field-input" value="' . esc_attr( $pickup_date ) . '">';
		echo '</div>';

		echo '<div class="mhm-field-group mhm-field-half">';
		echo '<label for="mhm_edit_pickup_time" class="mhm-field-label">' . esc_html__( 'Pickup Time', 'mhm-rentiva' ) . '</label>';
		echo '<input type="time" id="mhm_booking_edit_pickup_time" name="mhm_edit_pickup_time" class="mhm-field-input" value="' . esc_attr( $pickup_time ) . '">';
		echo '</div>';
		echo '</div>';

		echo '<div class="mhm-field-row">';
		echo '<div class="mhm-field-group mhm-field-half">';
		echo '<label for="mhm_edit_dropoff_date" class="mhm-field-label">' . esc_html__( 'Return Date', 'mhm-rentiva' ) . '</label>';
		echo '<input type="date" id="mhm_booking_edit_dropoff_date" name="mhm_edit_dropoff_date" class="mhm-field-input" value="' . esc_attr( $dropoff_date ) . '">';
		echo '</div>';

		echo '<div class="mhm-field-group mhm-field-half">';
		echo '<label for="mhm_edit_dropoff_time" class="mhm-field-label">' . esc_html__( 'Return Time', 'mhm-rentiva' ) . '</label>';
		echo '<input type="time" id="mhm_booking_edit_dropoff_time" name="mhm_edit_dropoff_time" class="mhm-field-input" value="' . esc_attr( $dropoff_time ) . '">';
		echo '</div>';
		echo '</div>';

		echo '<div class="mhm-field-row">';
		echo '<div class="mhm-field-group mhm-field-half">';
		echo '<label for="mhm_edit_guests" class="mhm-field-label">' . esc_html__( 'Number of Guests', 'mhm-rentiva' ) . '</label>';
		echo '<input type="number" id="mhm_booking_edit_guests" name="mhm_edit_guests" class="mhm-field-input" value="' . esc_attr( $guests ) . '" min="1" max="10">';
		echo '</div>';

		echo '<div class="mhm-field-group mhm-field-half">';
		echo '<label for="mhm_edit_status" class="mhm-field-label">' . esc_html__( 'Status', 'mhm-rentiva' ) . '</label>';
		echo '<select id="mhm_booking_edit_status" name="mhm_edit_status" class="mhm-field-select">';
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

		// Fetch current add-ons
		$addons = get_posts(
			array(
				'post_type'      => 'vehicle_addon',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			)
		);

		$available_addons = array();
		foreach ( $addons as $addon ) {
			$available_addons[] = array(
				'id'          => $addon->ID,
				'title'       => $addon->post_title,
				'price'       => get_post_meta( $addon->ID, 'addon_price', true ) ?: '0',
				'description' => $addon->post_excerpt,
				'required'    => (bool) get_post_meta( $addon->ID, 'addon_required', true ),
			);
		}

		// Fetch currently selected add-ons
		$selected_addons = get_post_meta( $post->ID, '_mhm_selected_addons', true )
	    ?: get_post_meta( $post->ID, 'mhm_selected_addons', true )
	    ?: array();

		if ( ! empty( $available_addons ) ) {
			echo '<div class="mhm-addon-selection">';
			echo '<p class="description">' . esc_html__( 'Select the additional services needed for this booking.', 'mhm-rentiva' ) . '</p>';

			echo '<div class="mhm-addon-grid">';
			foreach ( $available_addons as $addon ) {
				$checked       = in_array( $addon['id'], $selected_addons ) ? 'checked' : '';
				$checked      .= $addon['required'] ? ' disabled' : '';
				$required_text = $addon['required'] ? ' <span class="required">*</span>' : '';

				echo '<div class="mhm-addon-card">';
				echo '<label class="mhm-addon-item">';
				echo '<input type="checkbox" name="mhm_edit_selected_addons[]" value="' . esc_attr( $addon['id'] ) . '" class="mhm-addon-checkbox" data-price="' . esc_attr( $addon['price'] ) . '" ' . esc_attr( $checked ) . '>';
				echo '<div class="mhm-addon-content">';
				echo '<div class="mhm-addon-header">';
				echo '<span class="mhm-addon-title">' . esc_html( $addon['title'] ) . wp_kses_post( $required_text ) . '</span>';
				echo '<span class="mhm-addon-price">+ ' . esc_html( number_format( (float) $addon['price'], 2, ',', '.' ) ) . ' ' . esc_html( \MHMRentiva\Admin\Reports\Reports::get_currency_symbol() ) . '</span>';
				echo '</div>';
				if ( ! empty( $addon['description'] ) ) {
					echo '<div class="mhm-addon-description">' . esc_html( $addon['description'] ) . '</div>';
				}
				echo '</div>';
				echo '</label>';
				echo '</div>';
			}
			echo '</div>';

			echo '<div class="mhm-addon-total" style="display: none;">';
			echo '<strong>' . esc_html__( 'Additional Services Total:', 'mhm-rentiva' ) . ' <span class="mhm-addon-total-amount">0,00 ' . esc_html( \MHMRentiva\Admin\Reports\Reports::get_currency_symbol() ) . '</span></strong>';
			echo '</div>';

			echo '</div>';
		} else {
			echo '<p class="description">' . esc_html__( 'No additional services available.', 'mhm-rentiva' ) . '</p>';
		}
		echo '</div>';

		// Special notes/requests
		echo '<div class="mhm-field-group mhm-special-notes-section">';
		echo '<label for="mhm_edit_special_notes" class="mhm-field-label">' . esc_html__( 'Special Notes / Requests', 'mhm-rentiva' ) . '</label>';
		echo '<textarea id="mhm_booking_edit_special_notes" name="mhm_edit_special_notes" class="mhm-field-textarea" rows="4" placeholder="' . esc_attr__( 'Enter any special notes or customer requests...', 'mhm-rentiva' ) . '">' . esc_textarea( $special_notes ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Add any special notes or customer requests for this booking.', 'mhm-rentiva' ) . '</p>';
		echo '</div>';

		echo '</div>';

		echo '</div>';
	}

	/**
	 * Persist booking edits.
	 */
	public static function save_booking_details( int $post_id ): void {
		// Autosave and revision check
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Nonce validation - Check if this is our form or WordPress standard form
		$nonce_valid = false;

		// Check our custom nonce
		if (
			isset( $_POST['mhm_booking_edit_meta_nonce'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mhm_booking_edit_meta_nonce'] ) ), 'mhm_booking_edit_action' )
		) {
			$nonce_valid = true;
		}

		// Check WordPress standard nonce (for standard Update button)
		if ( ! $nonce_valid && isset( $_POST['_wpnonce'] ) ) {
			$wpnonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) );
			// Try different WordPress nonce formats
			if (
				wp_verify_nonce( $wpnonce, 'update-post_' . $post_id ) ||
				wp_verify_nonce( $wpnonce, 'update-post' ) ||
				wp_verify_nonce( $wpnonce, 'post_' . $post_id )
			) {
				$nonce_valid = true;
			}
		}

		// If we have our form fields, allow save even without nonce (for compatibility)
		// This ensures special_notes and other fields are always saved
		$has_our_fields = isset( $_POST['mhm_edit_special_notes'] ) ||
			isset( $_POST['mhm_edit_pickup_date'] ) ||
			isset( $_POST['mhm_edit_vehicle_id'] );

		if ( ! $nonce_valid && ! $has_our_fields ) {
			return;
		}

		// Capability check
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Ensure post type is vehicle_booking
		if ( get_post_type( $post_id ) !== 'vehicle_booking' ) {
			return;
		}

		// Fetch and persist data
		$new_vehicle_id = isset( $_POST['mhm_edit_vehicle_id'] ) ? absint( wp_unslash( $_POST['mhm_edit_vehicle_id'] ) ) : 0;
		$pickup_date    = isset( $_POST['mhm_edit_pickup_date'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['mhm_edit_pickup_date'] ) ) : '';
		$pickup_time    = isset( $_POST['mhm_edit_pickup_time'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['mhm_edit_pickup_time'] ) ) : '';
		$dropoff_date   = isset( $_POST['mhm_edit_dropoff_date'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['mhm_edit_dropoff_date'] ) ) : '';
		$dropoff_time   = isset( $_POST['mhm_edit_dropoff_time'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['mhm_edit_dropoff_time'] ) ) : '';
		$guests         = max( 1, isset( $_POST['mhm_edit_guests'] ) ? absint( wp_unslash( $_POST['mhm_edit_guests'] ) ) : 1 );
		$status         = isset( $_POST['mhm_edit_status'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['mhm_edit_status'] ) ) : 'pending';

		// Get special notes - always save if field exists
		$special_notes = isset( $_POST['mhm_edit_special_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['mhm_edit_special_notes'] ) ) : '';

		// Update vehicle if changed
		$old_vehicle_id = get_post_meta( $post_id, '_mhm_vehicle_id', true );
		if ( $new_vehicle_id > 0 && $new_vehicle_id != $old_vehicle_id ) {
			// Verify vehicle exists
			$vehicle_post = get_post( $new_vehicle_id );
			if ( $vehicle_post && $vehicle_post->post_type === 'vehicle' ) {
				update_post_meta( $post_id, '_mhm_vehicle_id', $new_vehicle_id );
				update_post_meta( $post_id, '_booking_vehicle_id', $new_vehicle_id ); // Legacy support

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

		// Process selected add-ons
		$selected_addons = isset( $_POST['mhm_edit_selected_addons'] ) ? array_map( 'absint', wp_unslash( $_POST['mhm_edit_selected_addons'] ) ) : array();
		$addon_details   = array();
		$addon_total     = 0;

		if ( ! empty( $selected_addons ) ) {
			foreach ( $selected_addons as $addon_id ) {
				$addon_post = get_post( $addon_id );
				if ( $addon_post && $addon_post->post_type === 'vehicle_addon' ) {
					$price           = (float) get_post_meta( $addon_id, 'addon_price', true );
					$addon_details[] = array(
						'id'    => $addon_id,
						'title' => $addon_post->post_title,
						'price' => $price,
					);
					$addon_total    += $price;
				}
			}
		}

		// Update meta values
		update_post_meta( $post_id, '_mhm_pickup_date', $pickup_date );
		update_post_meta( $post_id, '_mhm_start_time', $pickup_time );
		update_post_meta( $post_id, '_mhm_pickup_time', $pickup_time );
		update_post_meta( $post_id, '_mhm_dropoff_date', $dropoff_date );
		update_post_meta( $post_id, '_mhm_end_time', $dropoff_time );
		update_post_meta( $post_id, '_mhm_dropoff_time', $dropoff_time );
		update_post_meta( $post_id, '_mhm_guests', $guests );
		update_post_meta( $post_id, '_mhm_special_notes', $special_notes );

		// Recalculate rental days if dates changed
		if ( $pickup_date && $dropoff_date ) {
			$start       = new \DateTime( $pickup_date );
			$end         = new \DateTime( $dropoff_date );
			$rental_days = $start->diff( $end )->days ?: 1;
			update_post_meta( $post_id, '_mhm_rental_days', $rental_days );
		}

		// Save add-on meta data
		update_post_meta( $post_id, '_mhm_selected_addons', $selected_addons );
		update_post_meta( $post_id, '_mhm_addon_details', $addon_details );
		update_post_meta( $post_id, '_mhm_addon_total', $addon_total );

		// Update status
		$old_status = get_post_meta( $post_id, '_mhm_status', true );
		Status::update_status( $post_id, $status, get_current_user_id() );

		// Append automatic status change note
		if ( $old_status !== $status ) {
			\MHMRentiva\Admin\Booking\Meta\BookingMeta::auto_add_status_change_note( $post_id, $old_status, $status );
		}

		// Update legacy meta keys for backward compatibility
		update_post_meta( $post_id, '_booking_pickup_date', $pickup_date );
		update_post_meta( $post_id, '_booking_pickup_time', $pickup_time );
		update_post_meta( $post_id, '_booking_dropoff_date', $dropoff_date );
		update_post_meta( $post_id, '_booking_dropoff_time', $dropoff_time );
		update_post_meta( $post_id, '_booking_guests', $guests );
	}
}
