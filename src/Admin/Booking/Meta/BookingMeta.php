<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Meta;

if (! defined('ABSPATH')) {
	exit;
}


use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Settings\Settings;
use MHMRentiva\Admin\Core\MetaBoxes\AbstractMetaBox;
use MHMRentiva\Admin\Core\Security\VerifiedRequest;
use MHMRentiva\Admin\Core\Utilities\SiteLocaleString;



// Include manual booking meta box
require_once __DIR__ . '/ManualBookingMetaBox.php';

// Include booking edit meta box
require_once __DIR__ . '/BookingEditMetaBox.php';

final class BookingMeta extends AbstractMetaBox {



	/**
	 * Safe sanitize text field that handles null values
	 * Includes wp_unslash for WPCS compliance with superglobal sanitization
	 */
	public static function sanitize_text_field_safe($value)
	{
		if ($value === null || $value === '') {
			return '';
		}
		return sanitize_text_field(wp_unslash( (string) $value));
	}

	/**
	 * Flag to ensure register() is called only once
	 */
	private static bool $registered = false;

	/**
	 * The "Send email to customer" types: the select's options, and the keys
	 * prepare_email_content() has a default subject and message for. Anything
	 * else is refused before a template is looked up.
	 */
	private const CUSTOMER_EMAIL_TYPES = array( 'booking_confirmation', 'payment_reminder', 'booking_reminder', 'booking_cancelled', 'custom' );

	/** Note types an operator may add by hand ('manual' is the AJAX default). */
	private const NOTE_TYPES = array( 'note', 'manual', 'customer_contact' );

	/**
	 * Flag to ensure admin_action_editpost hook is registered only once
	 */
	private static bool $editpostHookRegistered = false;

	protected static function get_post_type(): string
	{
		return 'mhmrentiva_booking';
	}

	protected static function get_meta_box_id(): string
	{
		return 'mhmrentiva_booking_status';
	}

	protected static function get_title(): string
	{
		return __('Booking Status', 'mhm-rentiva');
	}

	protected static function get_fields(): array
	{
		return array(
			'mhmrentiva_booking_status' => array(
				'title'    => __('Booking Status', 'mhm-rentiva'),
				'context'  => 'side',
				'priority' => 'high',
				'template' => 'render_meta_box',
			),
		);
	}

	/**
	 * Registers meta box - only for existing bookings
	 */
	public static function register(): void
	{
		// Exit if already registered
		if (self::$registered) {
			return;
		}

		self::$registered = true;

		// Show meta box only for existing bookings
		add_action('add_meta_boxes', array( self::class, 'add_meta_boxes' ));

		// Load scripts
		add_action('admin_enqueue_scripts', array( self::class, 'enqueue_scripts' ));

		// Save meta
		add_action('save_post', array( self::class, 'save_meta' ), 10, 2);

		// Payment history around every booking Update (PaymentView): opens
		// before the writers at 5 and 10, closes after them.
		add_action('save_post', array( \MHMRentiva\Admin\Booking\Core\PaymentView::class, 'open_update_scope' ), 1, 2);
		add_action('save_post', array( \MHMRentiva\Admin\Booking\Core\PaymentView::class, 'close_update_scope' ), 99, 2);

		// Re-pricing only when a price input moved (BookingPricingInputs): the
		// fingerprint is taken before the writers at 5 and 10.
		add_action('save_post', array( \MHMRentiva\Admin\Booking\Core\BookingPricingInputs::class, 'open' ), 1, 2);
		add_action('save_post', array( \MHMRentiva\Admin\Booking\Core\BookingPricingInputs::class, 'close' ), 99, 2);

		// Email sending handler
		add_action('admin_post_mhmrentiva_send_customer_email', array( self::class, 'handle_send_customer_email' ));

		// AJAX handlers
		add_action('wp_ajax_mhmrentiva_send_customer_email', array( self::class, 'ajax_send_customer_email' ));
		add_action('wp_ajax_mhmrentiva_get_email_template', array( self::class, 'ajax_get_email_template' ));
		add_action('wp_ajax_mhmrentiva_add_booking_history_note', array( self::class, 'ajax_add_booking_history_note' ));

		// Note: "Booking created" note is added manually (in ManualBookingMetaBox and BookingForm).
		// The status-change note is hooked in Core\Hooks: transitions happen in every context.
		// `mhmrentiva_payment_status_changed` was listened for here but fired by nothing in
		// either edition, so no payment note was ever written; the listener and its
		// method are gone. Payment notes come from the payment view's scopes (PR 3a).

		// Show admin notices
		add_action('admin_notices', array( self::class, 'show_admin_notices' ));
		add_action('admin_notices', array( self::class, 'show_blocked_day_notice' ));

		// ✅ Auto calculation hook
		add_action('mhmrentiva_booking_meta_updated', array( self::class, 'on_booking_meta_updated' ), 10, 3);
	}

	/**
	 * Adds meta box - only for existing bookings
	 */
	public static function add_meta_boxes(): void
	{
		global $post, $pagenow;

		// Show only for existing bookings (not when creating new booking)
		if ($pagenow === 'post-new.php' || ! $post || ! $post->ID || $post->post_type !== 'mhmrentiva_booking') {
			return;
		}

		// Booking summary meta box intentionally remains disabled to avoid conflicts with BookingEditMetaBox.

		// Customer email meta box
		add_meta_box(
			'mhmrentiva_customer_email_box',
			__('Send Email to Customer', 'mhm-rentiva'),
			array( self::class, 'render_customer_email_box' ),
			self::get_post_type(),
			'side',
			'default'
		);

		// Receipt review meta box -- only when the customer uploaded a receipt
		// (canvas v135). Without one the box only said "no receipt"; the review
		// controls inside it need an attachment to act on.
		if ( (int) get_post_meta($post->ID, '_mhmrentiva_receipt_attachment_id', true) > 0 ) {
			add_meta_box(
				'mhmrentiva_receipt_box',
				__('Payment Receipt', 'mhm-rentiva'),
				array( self::class, 'render_receipt_box' ),
				self::get_post_type(),
				'side',
				'default'
			);
		}

		// Booking history (spec PR 3a) is not a box of its own any more: the
		// Payment Management box draws it (render_history_section(), PR 3b-1).
	}

	public static function enqueue_scripts(string $hook): void
	{
		global $post_type;

		// Load only on booking edit screen
		if ($hook === 'post.php' && $post_type === 'mhmrentiva_booking') {
			wp_enqueue_script(
				'mhm-rentiva-booking-email-send',
				MHMRENTIVA_PLUGIN_URL . 'assets/js/admin/booking-email-send.js',
				array( 'jquery' ),
				MHMRENTIVA_VERSION,
				true
			);

			// Get booking ID for template loading
			global $post;
			$booking_id = $post && $post->ID ? $post->ID : 0;

			// Localize script
			wp_localize_script(
				'mhm-rentiva-booking-email-send',
				'mhmBookingEmail',
				array(
					'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
					'bookingId'            => $booking_id,
					'emailNonce'           => wp_create_nonce( 'mhmrentiva_send_email' ),
					'confirmCreateAccount' => __( 'Create a WordPress account for this customer?', 'mhm-rentiva' ),
					'confirmRefund'        => __( 'Proceed with refund?', 'mhm-rentiva' ),
					'priceDecimals'        => \MHMRentiva\Admin\Payment\Core\Money::decimals(),
					'strings'              => array(
						'noteEmpty'       => __( 'Please enter a note.', 'mhm-rentiva' ),
						'noteAdded'       => __( 'Note added.', 'mhm-rentiva' ),
						/* translators: %s: the error message the server returned. */
						'noteError'       => __( 'Error: %s', 'mhm-rentiva' ),
						'noteFailed'      => __( 'The note could not be added.', 'mhm-rentiva' ),
						'sending'         => __( 'Sending...', 'mhm-rentiva' ),
						'success'         => __( 'Email sent successfully!', 'mhm-rentiva' ),
						'error'           => __( 'Error:', 'mhm-rentiva' ),
						'unknownError'    => __( 'Unknown error', 'mhm-rentiva' ),
						'errorOccurred'   => __( 'An error occurred:', 'mhm-rentiva' ),
						'loadingTemplate' => __( 'Loading template...', 'mhm-rentiva' ),
					),
				)
			);
		}
	}

	/**
	 * Render receipt review meta box
	 */
	/**
	 * Display label for a stored receipt status key; '-' when there is none.
	 *
	 * @param string $status Stored key (submitted / approved / rejected).
	 */
	public static function receipt_status_label(string $status): string
	{
		if ('' === $status) {
			return '-';
		}

		$labels = array(
			'submitted' => __('Submitted', 'mhm-rentiva'),
			'approved'  => __('Approved', 'mhm-rentiva'),
			'rejected'  => __('Rejected', 'mhm-rentiva'),
		);

		return $labels[ $status ] ?? $status;
	}

	public static function render_receipt_box(\WP_Post $post): void
	{
		$attach_id = (int) get_post_meta($post->ID, '_mhmrentiva_receipt_attachment_id', true);
		// The stored value is a key (submitted / approved / rejected); show its label.
		$status = self::receipt_status_label( (string) get_post_meta($post->ID, '_mhmrentiva_receipt_status', true));
		// Guarded endpoint rather than the raw uploads path: the file is customer
		// financial data and `private` post status does not gate the file itself.
		$url  = $attach_id ? \MHMRentiva\Admin\Frontend\Account\AccountController::get_receipt_url( (int) $post->ID) : '';
		$note = get_post_meta($post->ID, '_mhmrentiva_receipt_note', true);

		wp_nonce_field('mhmrentiva_receipt_review', 'mhmrentiva_receipt_nonce');

		// Template kullan
		$template_data = array(
			'attach_id' => $attach_id,
			'url'       => $url,
			'status'    => $status,
			'note'      => $note,
		);

		$template_path = plugin_dir_path(__FILE__) . '../../../templates/admin/booking-meta/receipt-box.php';
		if (file_exists($template_path)) {
			include $template_path;
		} else {
			// Fallback - old method
			echo '<div class="mhm-receipt-box">';
			if ($attach_id && $url) {
				echo '<p><strong>' . esc_html__('Receipt file:', 'mhm-rentiva') . '</strong><br/>';
				echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html__('View / Download', 'mhm-rentiva') . '</a></p>';
			} else {
				echo '<p>' . esc_html__('No receipt uploaded.', 'mhm-rentiva') . '</p>';
			}
			$receipt_status = '' !== (string) $status ? $status : '-';
			echo '<p><strong>' . esc_html__('Status:', 'mhm-rentiva') . '</strong> ' . esc_html($receipt_status) . '</p>';
			echo '<p><label for="mhmrentiva_receipt_note"><strong>' . esc_html__('Admin Note', 'mhm-rentiva') . '</strong></label><br/>';
			echo '<textarea id="mhmrentiva_receipt_note" name="mhmrentiva_receipt_note" rows="3" style="width:100%">' . esc_textarea($note) . '</textarea></p>';
			echo '<p>';
			echo '<button type="submit" name="mhmrentiva_receipt_action" value="approve" class="button button-primary">' . esc_html__('Approve Receipt', 'mhm-rentiva') . '</button> ';
			echo '<button type="submit" name="mhmrentiva_receipt_action" value="reject" class="button">' . esc_html__('Reject Receipt', 'mhm-rentiva') . '</button>';
			echo '</p>';
			echo '</div>';
		}
	}



	/**
	 * Email customer about receipt status
	 */
	private static function email_customer_receipt_status(int $booking_id, string $status): void
	{
		// Same confusion as the receipt endpoints used to have: post_author on a
		// vehicle_booking is the admin (or the staff member who created it), never
		// the customer. Without this the receipt-status email went to the site
		// owner instead of the person who uploaded the receipt.
		$user_id = (int) get_post_meta($booking_id, '_mhmrentiva_customer_user_id', true);
		$user    = get_user_by('id', $user_id);
		if (! $user) {
			return;
		}

		// Get customer name from booking meta
		$customer_name = get_post_meta($booking_id, '_mhmrentiva_customer_name', true);
		if (! $customer_name) {
			$customer_name = $user->display_name;
			if (! $customer_name) {
				$customer_name = $user->user_login;
			}
		}

		$subject = ( $status === 'approved' )
			? __('Your payment receipt has been approved', 'mhm-rentiva')
			: __('Your payment receipt has been rejected', 'mhm-rentiva');

		$note          = get_post_meta($booking_id, '_mhmrentiva_receipt_note', true);
		$booking_title = get_the_title($booking_id);
		$account_url   = \MHMRentiva\Admin\Frontend\Account\AccountController::get_account_url();
		$link          = add_query_arg(array( 'endpoint' => 'payment-history' ), $account_url);

		// Prepare template data
		$template_data = array(
			'status'        => $status,
			'customer_name' => $customer_name,
			'booking_title' => $booking_title,
			'admin_note'    => $note,
			'account_url'   => $link,
		);

		// Load HTML template
		$template_path = MHMRENTIVA_PLUGIN_PATH . 'templates/emails/receipt-status-email.html.php';
		if (file_exists($template_path)) {
			ob_start();
			// Pass template data as $args for template
			$args = $template_data;
			include $template_path;
			$html_body = ob_get_clean();
		} else {
			// Fallback to plain text
			$html_body = self::get_receipt_email_plain_text($template_data);
		}

		// Send HTML email
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
		);

		wp_mail($user->user_email, $subject, $html_body, $headers);
	}

	/**
	 * Get plain text fallback for receipt email
	 */
	private static function get_receipt_email_plain_text(array $data): string
	{
		$status_text = ( $data['status'] === 'approved' )
			? __('Your payment receipt has been approved', 'mhm-rentiva')
			: __('Your payment receipt has been rejected', 'mhm-rentiva');

		$body = sprintf(
			"%s\n\n%s: %s\n%s: %s\n\n%s: %s",
			$status_text,
			__('Booking', 'mhm-rentiva'),
			$data['booking_title'],
			__('Status', 'mhm-rentiva'),
			ucfirst($data['status']),
			__('Details', 'mhm-rentiva'),
			$data['account_url']
		);

		if (! empty($data['admin_note'])) {
			$body .= "\n\n" . __('Admin Note', 'mhm-rentiva') . ': ' . $data['admin_note'];
		}

		return $body;
	}

	public static function render_meta_box(\WP_Post $post, array $args = array()): void
	{
		// Nonce field
		wp_nonce_field('mhmrentiva_booking_meta_action', 'mhmrentiva_booking_meta_main_nonce');

		// Mevcut durum
		$current_status = Status::get($post->ID);

		// Booking details
		$vehicle_id    = (int) get_post_meta($post->ID, '_mhmrentiva_vehicle_id', true);
		$pickup_date   = get_post_meta($post->ID, '_mhmrentiva_pickup_date', true);
		$pickup_time   = get_post_meta($post->ID, '_mhmrentiva_pickup_time', true);
		$dropoff_date  = get_post_meta($post->ID, '_mhmrentiva_dropoff_date', true);
		$dropoff_time  = get_post_meta($post->ID, '_mhmrentiva_dropoff_time', true);
		$rental_days   = (int) get_post_meta($post->ID, '_mhmrentiva_rental_days', true);
		$total_price   = (float) get_post_meta($post->ID, '_mhmrentiva_total_price', true);
		$contact_name  = get_post_meta($post->ID, '_mhmrentiva_contact_name', true);
		$contact_email = get_post_meta($post->ID, '_mhmrentiva_contact_email', true);

		echo '<div class="mhm-rentiva-wrap">';

		// Summary text
		echo '<div class="booking-summary-header">';
		echo '<h4>' . esc_html__('Booking Details', 'mhm-rentiva') . '</h4>';
		echo '</div>';

		// Booking summary
		echo '<div class="booking-summary">';
		echo '<h4>' . esc_html__('Booking Summary', 'mhm-rentiva') . '</h4>';

		// Vehicle
		if ($vehicle_id) {
			$vehicle_title = get_the_title($vehicle_id);
			$vehicle_link  = get_edit_post_link($vehicle_id);
			echo '<p><strong>' . esc_html__('Vehicle:', 'mhm-rentiva') . '</strong> ';
			if ($vehicle_link) {
				echo '<a href="' . esc_url($vehicle_link) . '" target="_blank">' . esc_html($vehicle_title) . '</a>';
			} else {
				echo esc_html($vehicle_title);
			}
			echo '</p>';
		}

		// Editable booking details
		$guests = get_post_meta($post->ID, '_mhmrentiva_guests', true);
		if (! $guests) {
			$guests = get_post_meta($post->ID, '_mhmrentiva_booking_guests', true);
		}
		if (! $guests) {
			$guests = 1;
		}

		// Get time data from correct meta keys
		$pickup_time_correct = get_post_meta($post->ID, '_mhmrentiva_start_time', true);
		if (! $pickup_time_correct) {
			$pickup_time_correct = get_post_meta($post->ID, '_mhmrentiva_pickup_time', true);
		}
		if (! $pickup_time_correct) {
			$pickup_time_correct = get_post_meta($post->ID, '_mhmrentiva_booking_pickup_time', true);
		}

		$dropoff_time_correct = get_post_meta($post->ID, '_mhmrentiva_end_time', true);
		if (! $dropoff_time_correct) {
			$dropoff_time_correct = get_post_meta($post->ID, '_mhmrentiva_dropoff_time', true);
		}
		if (! $dropoff_time_correct) {
			$dropoff_time_correct = get_post_meta($post->ID, '_mhmrentiva_booking_dropoff_time', true);
		}

		echo '<div class="edit-section">';
		echo '<h5>' . esc_html__('Booking Details', 'mhm-rentiva') . '</h5>';

		echo '<div class="field-row">';
		echo '<div class="field-group">';
		echo '<label for="mhmrentiva_edit_pickup_date">' . esc_html__('Pickup Date', 'mhm-rentiva') . '</label>';
		echo '<input type="date" id="mhmrentiva_edit_pickup_date" name="mhmrentiva_edit_pickup_date" value="' . esc_attr($pickup_date) . '" class="regular-text">';
		echo '</div>';

		echo '<div class="field-group">';
		echo '<label for="mhmrentiva_edit_pickup_time">' . esc_html__('Pickup Time', 'mhm-rentiva') . '</label>';
		echo '<input type="time" id="mhmrentiva_edit_pickup_time" name="mhmrentiva_edit_pickup_time" value="' . esc_attr($pickup_time_correct) . '" class="regular-text">';
		echo '</div>';
		echo '</div>';

		echo '<div class="field-row">';
		echo '<div class="field-group">';
		echo '<label for="mhmrentiva_edit_dropoff_date">' . esc_html__('Return Date', 'mhm-rentiva') . '</label>';
		echo '<input type="date" id="mhmrentiva_edit_dropoff_date" name="mhmrentiva_edit_dropoff_date" value="' . esc_attr($dropoff_date) . '" class="regular-text">';
		echo '</div>';

		echo '<div class="field-group">';
		echo '<label for="mhmrentiva_edit_dropoff_time">' . esc_html__('Return Time', 'mhm-rentiva') . '</label>';
		echo '<input type="time" id="mhmrentiva_edit_dropoff_time" name="mhmrentiva_edit_dropoff_time" value="' . esc_attr($dropoff_time_correct) . '" class="regular-text">';
		echo '</div>';
		echo '</div>';

		echo '<div class="field-group">';
		echo '<label for="mhmrentiva_edit_guests">' . esc_html__('Number of Guests', 'mhm-rentiva') . '</label>';
		echo '<input type="number" id="mhmrentiva_edit_guests" name="mhmrentiva_edit_guests" value="' . esc_attr($guests) . '" min="1" max="10" class="small-text">';
		echo '</div>';
		echo '</div>';

		// Read-only summary information
		echo '<div class="readonly-section">';
		echo '<h5>' . esc_html__('Booking Summary', 'mhm-rentiva') . '</h5>';

		// Vehicle (read-only)
		if ($vehicle_id) {
			$vehicle_title = get_the_title($vehicle_id);
			$vehicle_link  = get_edit_post_link($vehicle_id);
			echo '<p><strong>' . esc_html__('Vehicle:', 'mhm-rentiva') . '</strong> ';
			if ($vehicle_link) {
				echo '<a href="' . esc_url($vehicle_link) . '" target="_blank">' . esc_html($vehicle_title) . '</a>';
			} else {
				echo esc_html($vehicle_title);
			}
			echo '</p>';
		}

		// Days count (read-only) - Can be updated via JavaScript
		if ($rental_days > 0) {
			echo '<p><strong>' . esc_html__('Days:', 'mhm-rentiva') . '</strong> <span id="mhmrentiva_rental_days_display">' . esc_html( (string) $rental_days) . '</span></p>';
		}

		// Total price (read-only) - Can be updated via JavaScript
		if ($total_price > 0) {
			echo '<p><strong>' . esc_html__('Total:', 'mhm-rentiva') . '</strong> <span id="mhmrentiva_total_price_display">' . esc_html(self::format_price($total_price)) . '</span></p>';
		}
		echo '</div>';

		echo '</div>';

		echo '</div>';
	}

	/**
	 * The submitted booking meta, or null when this request carries no nonce
	 * that authorises saving this booking.
	 *
	 * Both accepted forms answer here, each as its own early return: the
	 * metabox's own form (mhmrentiva_booking_meta_main_nonce) and the classic
	 * editor's Update button (_wpnonce for core's update-post_{id}). The
	 * verified payload travels back with the verdict so the nonce check and the
	 * superglobal access stay in one scope.
	 */
	private static function verified_save_request(int $post_id): ?VerifiedRequest
	{
		$own_nonce = sanitize_text_field(wp_unslash($_POST['mhmrentiva_booking_meta_main_nonce'] ?? ''));
		if (wp_verify_nonce($own_nonce, 'mhmrentiva_booking_meta_action')) {
			return VerifiedRequest::from($_POST);
		}

		$core_nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? ''));
		if (wp_verify_nonce($core_nonce, 'update-post_' . $post_id)) {
			return VerifiedRequest::from($_POST);
		}

		return null;
	}

	/**
	 * Status and booking-detail fields of an admin save, split out of save_meta()
	 * so they can be skipped as one unit when guard_edit_availability() refuses.
	 */
	private static function save_booking_fields(int $post_id, VerifiedRequest $req, string $new_status): void
	{
		// Status update - Handle both field names from different meta boxes
		if ($new_status) {
			$actor_user_id = get_current_user_id();

			Status::update_status($post_id, $new_status, $actor_user_id);
		}

		// Update booking details
		$pickup_date  = $req->text('mhmrentiva_edit_pickup_date');
		$pickup_time  = $req->text('mhmrentiva_edit_pickup_time');
		$dropoff_date = $req->text('mhmrentiva_edit_dropoff_date');
		$dropoff_time = $req->text('mhmrentiva_edit_dropoff_time');
		// No guests field on the edit form any more (PR 3b-1): only a posted
		// value is written, so the stored guests survive an Update.
		$guests_posted = $req->has('mhmrentiva_edit_guests');
		$guests        = max(1, $req->int('mhmrentiva_edit_guests', 1));
		$special_notes = $req->textarea('mhmrentiva_edit_special_notes');

		// Same rule as the edit box's saver (function scan H4): a reversed range
		// keeps the stored dates. The notice is queued there.
		if (BookingEditDates::is_refused($post_id, $pickup_date, $pickup_time, $dropoff_date, $dropoff_time)) {
			list($pickup_date, $pickup_time, $dropoff_date, $dropoff_time) = BookingEditDates::stored($post_id);
			// Queued here too: this saver can run alone (Codex code audit 3b-2, Minor 8).
			BookingEditNotice::queue($post_id, BookingEditDates::notice(), 'warning');
		}

		// Update meta data
		update_post_meta($post_id, '_mhmrentiva_pickup_date', $pickup_date);
		update_post_meta($post_id, '_mhmrentiva_start_time', $pickup_time); // Correct meta key
		update_post_meta($post_id, '_mhmrentiva_dropoff_date', $dropoff_date);
		update_post_meta($post_id, '_mhmrentiva_end_time', $dropoff_time); // Correct meta key
		if ($guests_posted) {
			update_post_meta($post_id, '_mhmrentiva_guests', $guests);
		}
		update_post_meta($post_id, '_mhmrentiva_special_notes', $special_notes);

		// Re-price only when a price input moved (BookingPricingInputs): the
		// dates are filled in on every Update, and re-pricing a paid booking at
		// today's rates on a status-only change rewrote what it had cost.
		if ($pickup_date && $dropoff_date && \MHMRentiva\Admin\Booking\Core\BookingPricingInputs::changed($post_id)) {
			self::recalculate_booking_costs($post_id, $pickup_date, $dropoff_date);
		}

		// Update old meta keys for compatibility
		update_post_meta($post_id, '_mhmrentiva_pickup_time', $pickup_time);
		update_post_meta($post_id, '_mhmrentiva_dropoff_time', $dropoff_time);

		// ✅ WordPress hook with auto calculation
		do_action('mhmrentiva_booking_meta_updated', $post_id, $pickup_date, $dropoff_date);
		update_post_meta($post_id, '_mhmrentiva_booking_pickup_date', $pickup_date);
		update_post_meta($post_id, '_mhmrentiva_booking_pickup_time', $pickup_time);
		update_post_meta($post_id, '_mhmrentiva_booking_dropoff_date', $dropoff_date);
		update_post_meta($post_id, '_mhmrentiva_booking_dropoff_time', $dropoff_time);
		if ($guests_posted) {
			update_post_meta($post_id, '_mhmrentiva_booking_guests', $guests);
		}

		self::sync_booking_timestamps($post_id, $pickup_date, $pickup_time, $dropoff_date, $dropoff_time);
		self::invalidate_edit_caches( (int) get_post_meta($post_id, '_mhmrentiva_vehicle_id', true));
		self::blocked_day_notice_after_write($post_id);
	}

	public static function save_meta(int $post_id, \WP_Post $post): void
	{
		// This handler is hooked to the UNTYPED `save_post`, so it runs for every post
		// type on the site. Nothing further down tells a booking apart from a page: the
		// nonce check below accepts core's own `update-post_{id}` nonce, which every
		// classic-editor save of every post type carries, and edit_post is true for an
		// author editing their own post. Without this line a page carrying
		// mhmrentiva_edit_status reached Status::update_status() and was given booking
		// status, history and dates.
		if ('mhmrentiva_booking' !== $post->post_type) {
			return;
		}

		// Autosave and revision check
		if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
			return;
		}

		// A valid nonce is REQUIRED. There used to be a "if our form fields are present, save
		// anyway" fallback here; it made the nonce effectively optional, so any cross-site POST
		// naming our fields wrote booking data on behalf of a logged-in editor (CSRF). The
		// metabox always renders wp_nonce_field() and the classic editor additionally sends
		// _wpnonce, so every legitimate save carries one of the two.
		$req = self::verified_save_request($post_id);
		if (null === $req) {
			return;
		}

		// Permission check
		if (! current_user_can('edit_post', $post_id)) {
			return;
		}

		// A cancellation request writes nothing here (PR 3b-2): the edit box's
		// saver ran it at priority 5, or -- when that saver did not run -- it is
		// run now. Either way the status and every price input stay as the
		// cancellation path left them. See BookingEditCancellation.
		$cancellation = BookingEditCancellation::handled($post_id);
		if (! $cancellation && BookingEditCancellation::requested($post_id, $req)) {
			BookingEditCancellation::run($post_id, $req);
			$cancellation = true;
		}

		// An edit that would put the booking on dates its vehicle is no longer free
		// for writes neither its status nor its dates; see guard_edit_availability().
		$new_status = $req->text('mhmrentiva_edit_status');
		if ($new_status === '') {
			$new_status = $req->text('mhmrentiva_booking_status');
		}
		$dates = array(
			$req->text('mhmrentiva_edit_pickup_date'),
			$req->text('mhmrentiva_edit_pickup_time'),
			$req->text('mhmrentiva_edit_dropoff_date'),
			$req->text('mhmrentiva_edit_dropoff_time'),
		);
		if (BookingEditDates::is_refused($post_id, ...$dates)) {
			$dates = BookingEditDates::stored($post_id);
		}
		$edit_allowed = ! $cancellation && self::guard_edit_availability(
			$post_id,
			$req->int('mhmrentiva_edit_vehicle_id'),
			$dates[0],
			$dates[1],
			$dates[2],
			$dates[3],
			$new_status
		);
		if ($edit_allowed) {
			self::save_booking_fields($post_id, $req, $new_status);
		}

		// Receipt review processing
		$receipt_nonce = $req->text('mhmrentiva_receipt_nonce');
		if ($receipt_nonce !== '' && wp_verify_nonce($receipt_nonce, 'mhmrentiva_receipt_review')) {
			if ($req->has('mhmrentiva_receipt_note')) {
				update_post_meta($post_id, '_mhmrentiva_receipt_note', $req->textarea('mhmrentiva_receipt_note'));
			}
			$action = $req->text('mhmrentiva_receipt_action');
			if ($action !== '') {
				if ($action === 'approve') {
					update_post_meta($post_id, '_mhmrentiva_receipt_status', 'approved');
					SiteLocaleString::in_site_locale(static fn() => self::email_customer_receipt_status($post_id, 'approved'));
				} elseif ($action === 'reject') {
					update_post_meta($post_id, '_mhmrentiva_receipt_status', 'rejected');
					SiteLocaleString::in_site_locale(static fn() => self::email_customer_receipt_status($post_id, 'rejected'));
				}
			}
		}
	}

	/**
	 * Customer email sending meta box
	 */
	public static function render_customer_email_box(\WP_Post $post): void
	{
		// Use BookingQueryHelper to get customer info (handles multiple meta keys)
		$customer_info       = \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::getBookingCustomerInfo( (int) $post->ID);
		$customer_email      = $customer_info['email'] ?? '';
		$customer_first_name = $customer_info['first_name'] ?? '';
		$customer_last_name  = $customer_info['last_name'] ?? '';
		$customer_name       = trim($customer_first_name . ' ' . $customer_last_name);
		if (! $customer_name) {
			$customer_name = get_post_meta($post->ID, '_mhmrentiva_customer_name', true);
		}

		// Also try to get from WooCommerce order if available
		if (empty($customer_email) && class_exists('WooCommerce') && function_exists('wc_get_order')) {
			$order_id = \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::resolve_wc_order_id( (int) $post->ID );
			if ($order_id) {
				$order = call_user_func('wc_get_order', $order_id);
				if ($order) {
					$customer_email = $order->get_billing_email();
					$customer_name  = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
				}
			}
		}

		// Also try to get from customer user ID
		if (empty($customer_email)) {
			$customer_user_id = (int) get_post_meta($post->ID, '_mhmrentiva_customer_user_id', true);
			if ($customer_user_id > 0) {
				$user = get_user_by('id', $customer_user_id);
				if ($user) {
					$customer_email = $user->user_email;
					$customer_name  = $user->display_name;
					if (! $customer_name) {
						$customer_name = $user->user_login;
					}
				}
			}
		}

		if (! $customer_email) {
			echo '<p class="description">' . esc_html__('No customer email found.', 'mhm-rentiva') . '</p>';
			return;
		}

		echo '<div class="mhm-customer-email-box">';
		if (! $customer_name) {
			$customer_name = 'N/A';
		}
		echo '<p><strong>' . esc_html__('Customer:', 'mhm-rentiva') . '</strong> ' . esc_html($customer_name) . '</p>';
		echo '<p><strong>' . esc_html__('Email:', 'mhm-rentiva') . '</strong> ' . esc_html($customer_email) . '</p>';

		echo '<hr style="margin: 15px 0;">';

		// Email types
		echo '<h4>' . esc_html__('Send Email', 'mhm-rentiva') . '</h4>';

		echo '<div class="mhm-customer-email-box mhm-email-form" data-booking-id="' . (int) $post->ID . '">';
		wp_nonce_field('mhmrentiva_send_email', 'mhmrentiva_email_nonce');

		echo '<p>';
		echo '<label for="email_type">' . esc_html__('Email Type:', 'mhm-rentiva') . '</label><br/>';
		echo '<select id="email_type" name="email_type" class="widefat" style="margin-bottom: 10px;">';
		echo '<option value="booking_confirmation">' . esc_html__('Booking Confirmation', 'mhm-rentiva') . '</option>';
		echo '<option value="payment_reminder">' . esc_html__('Payment Reminder', 'mhm-rentiva') . '</option>';
		echo '<option value="booking_reminder">' . esc_html__('Booking Reminder', 'mhm-rentiva') . '</option>';
		echo '<option value="booking_cancelled">' . esc_html__('Booking Cancelled', 'mhm-rentiva') . '</option>';
		echo '<option value="custom">' . esc_html__('Custom Message', 'mhm-rentiva') . '</option>';
		echo '</select>';
		echo '</p>';

		echo '<p>';
		echo '<label for="email_subject">' . esc_html__('Subject:', 'mhm-rentiva') . '</label><br/>';
		echo '<input type="text" id="email_subject" name="email_subject" class="widefat" placeholder="' . esc_attr__('Email subject...', 'mhm-rentiva') . '" style="margin-bottom: 10px;">';
		echo '</p>';

		echo '<p>';
		echo '<label for="email_message">' . esc_html__('Message:', 'mhm-rentiva') . '</label><br/>';
		echo '<textarea id="email_message" name="email_message" rows="4" class="widefat" placeholder="' . esc_attr__('Your message...', 'mhm-rentiva') . '" style="margin-bottom: 10px;"></textarea>';
		echo '</p>';

		echo '<p>';
		echo '<button type="button" class="button button-primary mhm-email-send-btn" style="width: 100%;">' . esc_html__('Send Email', 'mhm-rentiva') . '</button>';
		echo '</p>';

		echo '</div>';
		echo '</div>';
	}

	/**
	 * Customer email sending handler
	 */
	public static function handle_send_customer_email(): void
	{
		// Nonce check
		$nonce = sanitize_text_field(wp_unslash($_POST['mhmrentiva_email_nonce'] ?? ''));
		if (! wp_verify_nonce($nonce, 'mhmrentiva_send_email')) {
			wp_die(esc_html__('Security check failed.', 'mhm-rentiva'));
		}

		$req = VerifiedRequest::from($_POST);

		$booking_id = $req->int('booking_id');

		// edit_post on the booking the request actually names, not the blanket
		// edit_posts: this handler acts on whichever booking_id arrives, so a
		// generic "can edit something" check was not a check on this object.
		if (! $booking_id || ! current_user_can('edit_post', $booking_id)) {
			wp_die(esc_html__('You do not have permission to send emails.', 'mhm-rentiva'));
		}

		// edit_post answers "may this user edit that post", never "is that post a
		// booking". map_meta_cap grants it for any post the caller owns, so without a
		// type check this handler acts on whatever id arrives.
		if ('mhmrentiva_booking' !== get_post_type($booking_id)) {
			wp_die(esc_html__('Booking not found.', 'mhm-rentiva'));
		}

		$email_type = $req->text('email_type');
		if (! in_array($email_type, self::CUSTOMER_EMAIL_TYPES, true)) {
			wp_die(esc_html__('Missing required fields.', 'mhm-rentiva'));
		}
		$subject = $req->text('email_subject');
		// wp_kses_post rather than sanitize_text_field: the body is authored in a
		// rich-text field and its HTML formatting has to survive into the email.
		$message = wp_kses_post( (string) ( $req->raw('email_message') ?? '' ) );

		$customer_email = get_post_meta($booking_id, '_mhmrentiva_customer_email', true);
		$customer_name  = get_post_meta($booking_id, '_mhmrentiva_customer_name', true);

		if (! $customer_email) {
			wp_die(esc_html__('Customer email not found.', 'mhm-rentiva'));
		}

		// Prepare subject and message based on email type, wrapped in the layout
		$email_data = self::compose_customer_email($booking_id, $email_type, $subject, $message);
		$html_body  = $email_data['html'];

		// Get sender settings
		$from_name    = \MHMRentiva\Admin\Settings\Groups\EmailSettings::get_from_name();
		$from_address = \MHMRentiva\Admin\Settings\Groups\EmailSettings::get_from_address();

		// Handle test mode
		if (\MHMRentiva\Admin\Settings\Groups\EmailSettings::is_test_mode()) {
			$customer_email = \MHMRentiva\Admin\Settings\Groups\EmailSettings::get_test_address();
		}

		// Send email
		$sent = wp_mail(
			$customer_email,
			$email_data['subject'],
			$html_body,
			array(
				'Content-Type: text/html; charset=UTF-8',
				'From: ' . $from_name . ' <' . $from_address . '>',
			)
		);

		if ($sent) {
			self::queue_admin_notice('email_sent');
			$redirect_url = add_query_arg(
				array(
					'post'   => $booking_id,
					'action' => 'edit',
				),
				admin_url('post.php')
			);

			wp_safe_redirect( $redirect_url );
			exit;
		} else {
			wp_die(esc_html__('Failed to send email.', 'mhm-rentiva'));
		}
	}

	/**
	 * Prepares email content
	 */
	/**
	 * A customer e-mail from this screen, subject resolved and body wrapped in
	 * the layout, written in the SITE's language: the operator sending it may
	 * have a different profile language, and the defaults and the layout's
	 * footer used to follow the operator.
	 *
	 * @return array{subject: string, message: string, html: string}
	 */
	private static function compose_customer_email(int $booking_id, string $email_type, string $subject, string $message): array
	{
		return SiteLocaleString::in_site_locale(
			static function () use ($booking_id, $email_type, $subject, $message): array {
				$content         = self::prepare_email_content($booking_id, $email_type, $subject, $message);
				$content['html'] = \MHMRentiva\Admin\Emails\Core\Templates::wrapWithLayout(
					\MHMRentiva\Admin\Emails\Core\Mailer::getBookingContext($booking_id) ?? array(),
					$content['subject'],
					$content['message']
				);

				return $content;
			}
		);
	}

	private static function prepare_email_content(int $booking_id, string $email_type, string $subject, string $message): array
	{
		$customer_name = get_post_meta($booking_id, '_mhmrentiva_customer_name', true);
		$vehicle_id    = get_post_meta($booking_id, '_mhmrentiva_vehicle_id', true);
		$vehicle_name  = $vehicle_id ? get_the_title($vehicle_id) : __('Unknown Vehicle', 'mhm-rentiva');

		$pickup_date  = get_post_meta($booking_id, '_mhmrentiva_pickup_date', true);
		$dropoff_date = get_post_meta($booking_id, '_mhmrentiva_dropoff_date', true);
		$total_amount = (float) get_post_meta($booking_id, '_mhmrentiva_total_price', true);

		// Default subjects
		$default_subjects = array(
			/* translators: %s: vehicle name */
			'booking_confirmation' => sprintf(__('Booking Confirmation - %s', 'mhm-rentiva'), $vehicle_name),
			/* translators: %s: vehicle name */
			'payment_reminder'     => sprintf(__('Payment Reminder - %s', 'mhm-rentiva'), $vehicle_name),
			/* translators: %s: vehicle name */
			'booking_reminder'     => sprintf(__('Booking Reminder - %s', 'mhm-rentiva'), $vehicle_name),
			/* translators: %s: vehicle name */
			'booking_cancelled'    => sprintf(__('Booking Cancelled - %s', 'mhm-rentiva'), $vehicle_name),
			/* translators: %s: blog name. */
			'custom'               => $subject ? $subject : sprintf(__('Message from %s', 'mhm-rentiva'), get_bloginfo('name')),
		);

		// Default messages
		$default_messages = array(
			'booking_confirmation' => sprintf(
				/* translators: 1: customer name; 2: vehicle name; 3: pickup date; 4: return date; 5: total amount. */
				__('Hello %1$s,<br><br>Your booking for %2$s has been confirmed.<br><br>Pickup Date: %3$s<br>Return Date: %4$s<br>Total Amount: %5$s<br><br>Thank you for choosing us!', 'mhm-rentiva'),
				$customer_name,
				$vehicle_name,
				$pickup_date,
				$dropoff_date,
				self::format_price( (float) $total_amount)
			),
			'payment_reminder'     => sprintf(
				/* translators: 1: customer name; 2: vehicle name; 3: total amount. */
				__('Hello %1$s,<br><br>This is a reminder about your payment for %2$s.<br><br>Total Amount: %3$s<br><br>Please complete your payment as soon as possible.', 'mhm-rentiva'),
				$customer_name,
				$vehicle_name,
				self::format_price( (float) $total_amount)
			),
			'booking_reminder'     => sprintf(
				/* translators: 1: customer name; 2: vehicle name; 3: pickup date; 4: return date. */
				__('Hello %1$s,<br><br>This is a reminder about your upcoming booking for %2$s.<br><br>Pickup Date: %3$s<br>Return Date: %4$s<br><br>We look forward to serving you!', 'mhm-rentiva'),
				$customer_name,
				$vehicle_name,
				$pickup_date,
				$dropoff_date
			),
			'booking_cancelled'    => sprintf(
				/* translators: 1: customer name; 2: vehicle name. */
				__('Hello %1$s,<br><br>We regret to inform you that your booking for %2$s has been cancelled.<br><br>If you have any questions, please contact us.', 'mhm-rentiva'),
				$customer_name,
				$vehicle_name
			),
			/* translators: %s: blog name */
			'custom'               => $message ? $message : sprintf(__('You have received a message from %s', 'mhm-rentiva'), get_bloginfo('name')),
		);

		$resolved_subject = $subject ? $subject : $default_subjects[ $email_type ];
		$resolved_message = $message ? $message : $default_messages[ $email_type ];

		return array(
			'subject' => $resolved_subject,
			'message' => $resolved_message,
		);
	}

	/**
	 * Booking history meta box
	 */
	/**
	 * The history as a section of the Payment Management box (canvas v135).
	 */
	public static function render_history_section(\WP_Post $post): void
	{
		echo '<div class="mhm-payment-history-section">';
		echo '<h4>' . esc_html__('Booking History', 'mhm-rentiva') . '</h4>';
		self::render_booking_history_box($post);
		echo '</div>';
	}

	public static function render_booking_history_box(\WP_Post $post): void
	{
		// Newest first, by when each entry happened -- not by its array key.
		$history = array_reverse(self::history_sorted( (int) $post->ID));

		echo '<div class="mhm-booking-history-box">';

		// Add-note controls. Not a form of their own: the box sits inside the
		// post form, so nothing here may be `required` (that blocked Update);
		// "Add note" refuses an empty note on its own (JS and server).
		echo '<div class="mhm-add-history-note" data-booking-id="' . (int) $post->ID . '">';
		echo '<h4>' . esc_html__('Add Note', 'mhm-rentiva') . '</h4>';

		wp_nonce_field('mhmrentiva_add_history_note', 'mhmrentiva_history_nonce');

		echo '<p>';
		echo '<label for="note_content">' . esc_html__('Note:', 'mhm-rentiva') . '</label><br/>';
		echo '<textarea id="note_content" name="note_content" rows="3" class="widefat" placeholder="' . esc_attr__('Add a note about this booking...', 'mhm-rentiva') . '"></textarea>';
		echo '</p>';

		echo '<p>';
		echo '<label for="note_type">' . esc_html__('Type:', 'mhm-rentiva') . '</label><br/>';
		echo '<select id="note_type" name="note_type" class="widefat" style="margin-bottom: 10px;">';
		// Status, payment and system rows are Rentiva's own (PR 3b-1).
		echo '<option value="note">' . esc_html__('Note', 'mhm-rentiva') . '</option>';
		echo '<option value="customer_contact">' . esc_html__('Customer Contact', 'mhm-rentiva') . '</option>';
		echo '</select>';
		echo '<span class="description">' . esc_html__('Rentiva writes the status, payment and system rows itself.', 'mhm-rentiva') . '</span>';
		echo '</p>';

		echo '<p>';
		echo '<button type="button" class="button button-primary mhm-add-history-btn">' . esc_html__('Add Note', 'mhm-rentiva') . '</button>';
		echo '</p>';

		echo '</div>';

		// JavaScript for AJAX forms

		echo '<hr style="margin: 20px 0;">';

		// Show history notes
		echo '<div class="mhm-history-list">';
		echo '<h4>' . esc_html__('History', 'mhm-rentiva') . '</h4>';

		if ( empty( $history ) ) {
			echo '<p class="description">' . esc_html__( 'No history found.', 'mhm-rentiva' ) . '</p>';
		} else {
			$threshold = 5;
			$total     = count( $history );
			$index     = 0;

			foreach ( $history as $note ) {
				// `timestamp` is the site-local clock reading the writer stored.
				$timestamp = (int) ( $note['timestamp'] ?? 0 );
				$date      = date_i18n( get_option( 'date_format' ), $timestamp );
				$time      = date_i18n( get_option( 'time_format' ), $timestamp );
				$user      = get_userdata( (int) ( $note['user_id'] ?? 0 ) );
				$user_name = $user ? $user->display_name : esc_html__( 'System', 'mhm-rentiva' );

				$note_type    = (string) ( $note['type'] ?? 'note' );
				$type_class   = 'mhm-history-' . $note_type;
				$type_label   = self::get_history_type_label( $note_type );
				$hidden_class = ( $index >= $threshold ) ? ' mhm-history-item--hidden' : '';

				echo '<div class="mhm-history-item ' . esc_attr( $type_class ) . esc_attr($hidden_class) . '">';
				echo '<div class="mhm-history-header">';
				echo '<span class="mhm-history-type">' . esc_html( $type_label ) . '</span>';
				echo '<span class="mhm-history-date">' . esc_html( $date . ' ' . $time ) . '</span>';
				echo '</div>';
				echo '<div class="mhm-history-content">';
				echo '<p>' . esc_html( (string) ( $note['note'] ?? '' ) ) . '</p>';
				echo '<div class="mhm-history-meta">';
				/* translators: %s: user display name */
				echo '<span class="mhm-history-user">' . esc_html( sprintf( esc_html__( 'By %s', 'mhm-rentiva' ), $user_name ) ) . '</span>';
				echo '</div>';
				echo '</div>';
				echo '</div>';

				++$index;
			}

			if ( $total > $threshold ) {
				echo '<button type="button" class="mhm-history-show-more">+ '
					. absint( $total - $threshold )
					. ' ' . esc_html__( 'more entries', 'mhm-rentiva' )
					. '</button>';
			}
		}

		echo '</div>';

		// K4: the notes are not private to the staff.
		echo '<p class="description mhm-history-privacy">' . esc_html__("These notes are included in the customer's personal data export.", 'mhm-rentiva') . '</p>';
		echo '</div>';
	}

	/**
	 * Gets history type label
	 */
	private static function get_history_type_label(string $type): string
	{
		$labels = array(
			'note'             => __('Note', 'mhm-rentiva'),
			'status_change'    => __('Status Change', 'mhm-rentiva'),
			'payment_update'   => __('Payment Update', 'mhm-rentiva'),
			'customer_contact' => __('Customer Contact', 'mhm-rentiva'),
			'system'           => __('System', 'mhm-rentiva'),
		);

		return $labels[ $type ] ?? esc_html__('Note', 'mhm-rentiva');
	}

	/**
	 * Add history note (programmatically) -- the only writer of
	 * `_mhmrentiva_booking_history`.
	 *
	 * The key is unique per entry: it used to be the second alone, so two
	 * writes in the same second (a status change and its note) kept only the
	 * last. `timestamp` stays the site-local clock reading it always was;
	 * readers order entries by history_sorted(), never by key.
	 *
	 * @param array{status_from?: string, status_to?: string, at?: int} $fields Structured fields (status changes, 6.4.0).
	 */
	public static function add_history_note(int $booking_id, string $note, string $type = 'note', ?int $user_id = null, array $fields = array()): bool
	{
		if (! $user_id) {
			$user_id = get_current_user_id();
		}

		$history = get_post_meta($booking_id, '_mhmrentiva_booking_history', true);
		if (! is_array($history)) {
			$history = array();
		}
		$timestamp = time() + current_datetime()->getOffset();

		$entry = array(
			'note'      => $note,
			'type'      => $type,
			'user_id'   => $user_id,
			'timestamp' => $timestamp,
		);
		// payment_from/payment_to: PaymentView's rows. Not status_*: the
		// customer timeline reads status_to and must not see payments.
		foreach (array( 'status_from', 'status_to', 'payment_from', 'payment_to' ) as $field) {
			if (isset($fields[ $field ])) {
				$entry[ $field ] = (string) $fields[ $field ];
			}
		}
		// Always a UTC instant: `timestamp` is a wall clock an hour of the year cannot place.
		$entry['at'] = isset($fields['at']) ? (int) $fields['at'] : time();

		$history[ $timestamp . '-' . wp_generate_password(6, false) ] = $entry;

		// The meta API unslashes; a note's backslash must survive (Codex code
		// audit 3b-2, Minor 6).
		return update_post_meta($booking_id, '_mhmrentiva_booking_history', wp_slash($history)) !== false;
	}

	/**
	 * A booking's history, oldest first, each entry with `sort_ts` (UTC).
	 *
	 * The single reader order (admin history box, customer progress). New
	 * entries carry `at` (UTC); older ones only the site-local `timestamp`, a
	 * wall clock stored as if it were UTC, so it is read back as that wall clock
	 * in the site's timezone. In the repeated autumn hour two instants share a
	 * reading; the earlier one is taken (PHP alone picks the later), so an older
	 * entry never sorts after a newer one. Entries of the same instant keep their
	 * write order.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function history_sorted(int $booking_id): array
	{
		$history = get_post_meta($booking_id, '_mhmrentiva_booking_history', true);
		if (! is_array($history)) {
			return array();
		}

		$timezone = wp_timezone();
		$entries  = array();
		foreach ($history as $entry) {
			if (! is_array($entry)) {
				continue;
			}
			if (isset($entry['at']) && is_numeric($entry['at'])) {
				$sort_ts = (int) $entry['at'];
			} else {
				$wall    = gmdate('Y-m-d H:i:s', (int) ( $entry['timestamp'] ?? 0 ));
				$local   = new \DateTimeImmutable($wall, $timezone);
				$sort_ts = $local->getTimestamp();
				if ($local->setTimestamp($sort_ts - HOUR_IN_SECONDS)->format('Y-m-d H:i:s') === $wall) {
					$sort_ts -= HOUR_IN_SECONDS;
				}
			}
			$entry['sort_ts'] = $sort_ts;
			$entries[]        = $entry;
		}

		// usort is stable (PHP 8): equal instants keep their write order.
		usort($entries, static fn(array $a, array $b): int => $a['sort_ts'] <=> $b['sort_ts']);

		return $entries;
	}

	/**
	 * Is this booking a transfer rather than a rental? Same detection the mailer uses.
	 */
	private static function is_transfer_booking(int $booking_id): bool
	{
		$is_transfer_flag = (string) get_post_meta($booking_id, '_mhmrentiva_is_transfer', true);

		return (int) get_post_meta($booking_id, '_mhmrentiva_transfer_origin_id', true) > 0
			|| 'transfer' === (string) get_post_meta($booking_id, '_mhmrentiva_service_type', true)
			|| in_array($is_transfer_flag, array( '1', 'yes', 'true' ), true);
	}

	/**
	 * Re-price a rental booking after an admin edit.
	 *
	 * Delegates to DepositCalculator::calculate_booking_deposit(), the calculator the
	 * booking form and the manual booking screen use, so an admin save produces the
	 * same total a customer would have been quoted: weekend multiplier, add-on
	 * pricing type and the vehicle's own deposit setting included. This method used
	 * to keep its own arithmetic and read two keys nothing writes, which dropped the
	 * add-ons from the total and charged a hard-coded 20% deposit.
	 *
	 * Limits: prices are read as they are today (a vehicle or add-on price changed
	 * since the booking re-prices it), and per-passenger add-ons are counted once,
	 * as the calculator counts them at checkout. A deposit booking keeps the deposit
	 * it carries (what its order charged) and only the remaining amount follows the
	 * new total; payments received beyond the deposit are not looked at.
	 */
	public static function recalculate_booking_costs(int $booking_id, string $pickup_date, string $dropoff_date): void
	{
		// A transfer is priced by route, not by the vehicle's daily rate.
		if (self::is_transfer_booking($booking_id)) {
			return;
		}

		$vehicle_id = (int) get_post_meta($booking_id, '_mhmrentiva_vehicle_id', true);
		if ($vehicle_id <= 0) {
			return;
		}

		$datetimes = \MHMRentiva\Admin\Booking\Helpers\Util::parse_datetimes(
			$pickup_date,
			(string) get_post_meta($booking_id, '_mhmrentiva_start_time', true),
			$dropoff_date,
			(string) get_post_meta($booking_id, '_mhmrentiva_end_time', true)
		);
		if (is_wp_error($datetimes)) {
			return;
		}

		$days         = \MHMRentiva\Admin\Booking\Helpers\Util::rental_days($datetimes['start_ts'], $datetimes['end_ts']);
		$selection    = \MHMRentiva\Admin\Addons\AddonSelection::for_booking($booking_id);
		$payment_type = (string) get_post_meta($booking_id, '_mhmrentiva_payment_type', true);

		$result = \MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator::calculate_booking_deposit(
			$vehicle_id,
			$days,
			'full' === $payment_type ? 'full' : 'deposit',
			$selection->ids(),
			$datetimes['start_ts'],
			$selection->quantities()
		);
		if (empty($result['success'])) {
			return;
		}

		// The detail rows follow the new total, so the order, the account page
		// and the report do not keep the lines of the window before the edit.
		update_post_meta(
			$booking_id,
			'_mhmrentiva_addon_details',
			\MHMRentiva\Admin\Addons\AddonManager::build_addon_details(
				$selection->ids(),
				array(
					'rental_days'      => $days,
					'addon_quantities' => $selection->quantities(),
				)
			)
		);

		$total_price = $result['total_amount'];

		update_post_meta($booking_id, '_mhmrentiva_rental_days', $days);
		update_post_meta($booking_id, '_mhmrentiva_addon_total', $result['addon_total']);
		update_post_meta($booking_id, '_mhmrentiva_total_price', $total_price);

		$stored_deposit = (float) get_post_meta($booking_id, '_mhmrentiva_deposit_amount', true);
		if ('deposit' === $payment_type && ( $stored_deposit > 0 || 'full' === ( $result['payment_type'] ?? '' ) )) {
			// The deposit is what the order charged. Today's rate may differ (the
			// site or the vehicle changed since, or it now resolves to full
			// payment, which would call the whole total paid): keep the deposit
			// the booking carries -- none, if none was stored -- and leave the
			// rest of the new total owed.
			update_post_meta($booking_id, '_mhmrentiva_remaining_amount', max(0.0, round($total_price - $stored_deposit, 2)));
		} elseif ('deposit' === $payment_type || 'full' === $payment_type) {
			update_post_meta($booking_id, '_mhmrentiva_deposit_amount', $result['deposit_amount']);
			update_post_meta($booking_id, '_mhmrentiva_remaining_amount', $result['remaining_amount']);
		}

		// Update old meta keys for compatibility
		update_post_meta($booking_id, '_mhmrentiva_booking_rental_days', $days);
		update_post_meta($booking_id, '_mhmrentiva_booking_total_price', $total_price);
	}

	/**
	 * Statuses that hold a vehicle: the live statuses of the booking rule in
	 * BookingConflicts (STATUS_SQL). That rule also counts a completed booking
	 * whose return is still ahead; an edit into `completed` is not a move onto
	 * new dates, so it is not checked here.
	 */
	private const BLOCKING_STATUSES = array( 'pending_payment', 'pending', 'confirmed', 'in_progress' );

	/**
	 * One verdict per booking per request, so the two save_post handlers that
	 * both write an admin edit (BookingEditMetaBox at 5, save_meta at 10) see
	 * the same answer and only one notice is queued.
	 *
	 * @var array<int, bool>
	 */
	private static array $edit_availability_verdicts = array();

	/**
	 * Closed days an allowed edit reaches, held until the edit is written.
	 *
	 * @var array<int, list<string>>
	 */
	private static array $pending_blocked_days = array();

	/**
	 * May this admin edit be saved without putting the booking on dates its
	 * vehicle is no longer free for?
	 *
	 * Every other booking-writing path (checkout, manual booking) checks the
	 * vehicle under Locker::withLock(); an admin edit never did, so a booking
	 * could be moved onto someone else's dates, or a cancelled booking
	 * re-confirmed after its dates were sold. Only an edit that changes the
	 * vehicle, the dates or times, or moves the booking into a status that holds
	 * the vehicle is checked: an unrelated edit (a note, the guest count) on a
	 * booking that already overlaps another must not lock the operator out.
	 *
	 * The lock is released before the handlers write, so a checkout landing in
	 * that gap could still pass its own check. Admin edits are rare and the
	 * window is a few milliseconds; closing it would mean moving both handlers'
	 * writes inside the lock.
	 */
	public static function guard_edit_availability(int $booking_id, int $vehicle_id, string $pickup_date, string $pickup_time, string $dropoff_date, string $dropoff_time, string $new_status): bool
	{
		if (! isset(self::$edit_availability_verdicts[ $booking_id ])) {
			$verdict = self::edit_availability_verdict($booking_id, $vehicle_id, $pickup_date, $pickup_time, $dropoff_date, $dropoff_time, $new_status);
			$allowed = 'free' === $verdict;

			self::$edit_availability_verdicts[ $booking_id ] = $allowed;

			if (! $allowed) {
				// 'conflict' or 'unverified': a check that could not run refuses
				// too, because it is the only thing between this edit and a
				// double booking.
				$notices = array(
					'unverified' => 'availability_unverified',
					'service'    => 'service_unavailable',
				);
				self::queue_admin_notice($notices[ $verdict ] ?? 'availability_conflict');

				// Core would still add ?message=1 and show "Booking updated." next to
				// our refusal. Drop it for this booking only.
				add_filter(
					'redirect_post_location',
					static function (string $location, int $post_id) use ($booking_id): string {
						return $post_id === $booking_id ? remove_query_arg('message', $location) : $location;
					},
					10,
					2
				);
			}
		}

		return self::$edit_availability_verdicts[ $booking_id ];
	}

	public static function edit_availability_allowed(int $booking_id): bool
	{
		return self::$edit_availability_verdicts[ $booking_id ] ?? true;
	}

	public static function reset_edit_availability_verdicts(): void
	{
		self::$edit_availability_verdicts = array();
		self::$pending_blocked_days       = array();
	}

	/**
	 * Queue the closed-days notice of an edit that has just been written.
	 * Called by each handler that writes an admin edit, after its writes; the
	 * first one queues it, the rest find nothing left.
	 */
	public static function blocked_day_notice_after_write(int $booking_id): void
	{
		if (! isset(self::$pending_blocked_days[ $booking_id ])) {
			return;
		}

		self::queue_blocked_day_notice($booking_id, self::$pending_blocked_days[ $booking_id ]);
		unset(self::$pending_blocked_days[ $booking_id ]);
	}

	/**
	 * @return string 'free', 'conflict', 'service' when a rental booking is moved
	 *                onto a vehicle not offered for rental, or 'unverified' when the
	 *                locked query failed.
	 */
	private static function edit_availability_verdict(int $booking_id, int $vehicle_id, string $pickup_date, string $pickup_time, string $dropoff_date, string $dropoff_time, string $new_status): string
	{
		// Only a vehicle CHANGE is gated: a booking made before the service gate
		// existed must still accept a date fix on the vehicle it already has.
		if ($vehicle_id > 0
			&& $vehicle_id !== (int) get_post_meta($booking_id, '_mhmrentiva_vehicle_id', true)
			&& 'mhmrentiva_vehicle' === get_post_type($vehicle_id)
			&& ! self::is_transfer_booking($booking_id)
			&& ! \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::allows_service($vehicle_id, 'rental')
		) {
			return 'service';
		}

		$old_status = Status::get($booking_id);
		$status     = ( '' !== $new_status && Status::can_transition($old_status, $new_status) ) ? $new_status : $old_status;

		if (! in_array($status, self::BLOCKING_STATUSES, true)) {
			return 'free';
		}

		// Same rule BookingEditMetaBox applies before it writes a new vehicle.
		$old_vehicle_id = (int) get_post_meta($booking_id, '_mhmrentiva_vehicle_id', true);
		if ($vehicle_id <= 0 || 'mhmrentiva_vehicle' !== get_post_type($vehicle_id)) {
			$vehicle_id = $old_vehicle_id;
		}
		if ($vehicle_id <= 0) {
			return 'free';
		}

		$new_range = \MHMRentiva\Admin\Booking\Helpers\Util::parse_datetimes($pickup_date, $pickup_time, $dropoff_date, $dropoff_time);
		if (is_wp_error($new_range)) {
			// Unparseable dates were never checked here; refusing them now would
			// be a behaviour change outside this guard's job.
			return 'free';
		}

		$old_range = \MHMRentiva\Admin\Booking\Helpers\Util::parse_datetimes(
			(string) get_post_meta($booking_id, '_mhmrentiva_pickup_date', true),
			(string) ( get_post_meta($booking_id, '_mhmrentiva_start_time', true) ?: get_post_meta($booking_id, '_mhmrentiva_pickup_time', true) ),
			(string) get_post_meta($booking_id, '_mhmrentiva_dropoff_date', true),
			(string) ( get_post_meta($booking_id, '_mhmrentiva_end_time', true) ?: get_post_meta($booking_id, '_mhmrentiva_dropoff_time', true) )
		);

		$unchanged = $vehicle_id === $old_vehicle_id
			&& ! is_wp_error($old_range)
			&& $old_range === $new_range
			&& self::status_holds_vehicle($booking_id, $old_status);
		if ($unchanged) {
			return 'free';
		}

		// Read the database, not a request cache filled before the lock.
		if (class_exists(\MHMRentiva\Admin\Booking\Helpers\Cache::class)) {
			\MHMRentiva\Admin\Booking\Helpers\Cache::invalidateVehicle($vehicle_id);
		}

		// Bookings only: an operator may put a booking on a blocked day (or keep
		// one that a day was closed under later) -- it is saved and the days are
		// named in a notice, never refused.
		$outcome = \MHMRentiva\Admin\Booking\Helpers\Locker::withLock(
			$vehicle_id,
			static function () use ($vehicle_id, $new_range, $booking_id): ?int {
				return \MHMRentiva\Admin\Booking\Helpers\BookingConflicts::count_booking_overlaps_locked($vehicle_id, $new_range['start_ts'], $new_range['end_ts'], $booking_id);
			}
		);

		if (null === $outcome) {
			return 'unverified';
		}

		if ($outcome > 0) {
			return 'conflict';
		}

		// The notice says "saved anyway": it is queued by the write that
		// follows (blocked_day_notice_after_write()), not here.
		wp_cache_delete($vehicle_id, 'post_meta');
		self::$pending_blocked_days[ $booking_id ] = \MHMRentiva\Admin\Booking\Helpers\BookingConflicts::blocked_days_in($vehicle_id, $new_range['start_ts'], $new_range['end_ts']);

		return 'free';
	}

	/**
	 * Transient key holding the closed days of the operator's last booking save.
	 */
	private static function blocked_day_notice_key(): string
	{
		return 'mhmrentiva_admin_blocked_day_notice_' . get_current_user_id();
	}

	/**
	 * Leave a one-shot notice naming the closed days a booking the operator
	 * just saved reaches (manual booking, booking edit). Nothing when there
	 * are none. It is shown on that booking's edit screen only.
	 *
	 * @param list<string> $days `Y-m-d`, site-local.
	 */
	public static function queue_blocked_day_notice(int $booking_id, array $days): void
	{
		if (array() === $days || $booking_id <= 0) {
			return;
		}

		set_transient(
			self::blocked_day_notice_key(),
			array(
				'booking_id' => $booking_id,
				'days'       => array_values($days),
			),
			300
		);
	}

	/**
	 * Show the closed-days notice once, on the edit screen of the booking it
	 * is about, then forget it. Any other screen leaves it waiting: "the
	 * booking was saved anyway" next to an unrelated list would read as being
	 * about something else.
	 */
	public static function show_blocked_day_notice(): void
	{
		global $pagenow, $post;

		$notice = get_transient(self::blocked_day_notice_key());
		if (! is_array($notice) || ! isset($notice['booking_id'], $notice['days'])) {
			return;
		}

		if ('post.php' !== $pagenow || ! $post instanceof \WP_Post || (int) $post->ID !== (int) $notice['booking_id']) {
			return;
		}

		delete_transient(self::blocked_day_notice_key());
		$days = $notice['days'];

		$labels = array();
		foreach ( (array) $days as $day) {
			$noon = \MHMRentiva\Admin\Booking\Helpers\SiteTime::instant( (string) $day, '12:00');
			if (null !== $noon) {
				$labels[] = wp_date( (string) get_option('date_format'), $noon);
			}
		}
		if (array() === $labels) {
			return;
		}

		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html(
			sprintf(
				/* translators: %s: list of the vehicle's closed days, joined by the site's list format ("1 June, 2 June and 3 June"). */
				__('This vehicle has closed days in this period: %s. The booking was saved anyway.', 'mhm-rentiva'),
				wp_sprintf('%l', $labels)
			)
		) . '</p></div>';
	}

	/**
	 * Does a booking in this status hold its vehicle right now?
	 *
	 * Mirrors the booking rule (BookingConflicts::PAYMENT_SQL): a pending booking whose payment
	 * deadline has passed is not counted there, so its dates may already have
	 * been sold. Re-confirming it is a change, not a no-op.
	 */
	private static function status_holds_vehicle(int $booking_id, string $status): bool
	{
		if (! in_array($status, self::BLOCKING_STATUSES, true)) {
			return false;
		}

		if (in_array($status, array( 'pending_payment', 'pending' ), true)) {
			$deadline = (string) get_post_meta($booking_id, '_mhmrentiva_payment_deadline', true);
			if ('' !== $deadline && $deadline <= current_time('mysql', true)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Drop what was cached about the vehicles an admin edit wrote to.
	 *
	 * Status changes already clear these through mhmrentiva_booking_status_changed;
	 * a vehicle or date edit fires no such event. Util::check_availability()
	 * serves a cached "unavailable" without rechecking, so the vehicle a booking
	 * left would stay unbookable on dates it no longer holds.
	 */
	public static function invalidate_edit_caches(int ...$vehicle_ids): void
	{
		foreach (array_unique(array_filter($vehicle_ids)) as $vehicle_id) {
			\MHMRentiva\Admin\Booking\Helpers\Cache::invalidateVehicle($vehicle_id);
		}

		\MHMRentiva\Admin\Core\Utilities\OccupancyMapService::invalidate();
	}

	/**
	 * Keep the stored timestamps in step with the stored dates.
	 *
	 * The overlap query reads `_mhmrentiva_start_ts` / `_mhmrentiva_end_ts`
	 * before it falls back to the date and time fields, so a booking whose
	 * dates were edited without these kept occupying its OLD dates and left
	 * the new ones open for sale.
	 *
	 * Timestamps are the real instants of the local date and time in the site
	 * timezone (Util::parse_datetimes() -> SiteTime::instant()), the same
	 * reading as the booking form, the WooCommerce checkout and the overlap
	 * query's date fallback. Rows the checkout stored before 4.8.0 (wall time
	 * read as UTC) are rebuilt by DatabaseMigrator::normalise_booking_timestamps_480().
	 */
	public static function sync_booking_timestamps(int $booking_id, string $pickup_date, string $pickup_time, string $dropoff_date, string $dropoff_time): void
	{
		$range = \MHMRentiva\Admin\Booking\Helpers\Util::parse_datetimes($pickup_date, $pickup_time, $dropoff_date, $dropoff_time);
		if (is_wp_error($range)) {
			return;
		}

		update_post_meta($booking_id, '_mhmrentiva_start_ts', $range['start_ts']);
		update_post_meta($booking_id, '_mhmrentiva_end_ts', $range['end_ts']);
	}

	/**
	 * ✅ WordPress hook listener - Auto calculation when meta is updated
	 */
	public static function on_booking_meta_updated(int $post_id, string $pickup_date, string $dropoff_date): void
	{
		// Same rule as save_booking_fields(): no re-pricing without a moved input.
		if (\MHMRentiva\Admin\Booking\Core\BookingPricingInputs::changed($post_id)) {
			self::recalculate_booking_costs($post_id, $pickup_date, $dropoff_date);
		}
	}

	/**
	 * Add status change note
	 */
	public static function auto_add_status_change_note(int $booking_id, string $old_status, string $new_status): void
	{
		$status_labels = array(
			'pending'     => __('Pending', 'mhm-rentiva'),
			'confirmed'   => __('Confirmed', 'mhm-rentiva'),
			'in_progress' => __('In Progress', 'mhm-rentiva'),
			'completed'   => __('Completed', 'mhm-rentiva'),
			'cancelled'   => __('Cancelled', 'mhm-rentiva'),
		);

		$old_label = $status_labels[ $old_status ] ?? $old_status;
		$new_label = $status_labels[ $new_status ] ?? $new_status;

		$note = sprintf(
			/* translators: 1: old status, 2: new status */
			__('Status changed from %1$s to %2$s', 'mhm-rentiva'),
			$old_label,
			$new_label
		);

		// The sentence is for staff; the customer's progress reads the
		// structured fields (R9), never the sentence.
		self::add_history_note(
			$booking_id,
			$note,
			'status_change',
			null,
			array(
				'status_from' => $old_status,
				'status_to'   => $new_status,
				'at'          => time(),
			)
		);
	}

	/**
	 * Transient key holding the pending one-shot admin notice for one user.
	 */
	private static function admin_notice_key(): string
	{
		return 'mhmrentiva_booking_notice_' . get_current_user_id();
	}

	/**
	 * Queue a one-shot admin notice to survive the redirect that follows.
	 *
	 * @param string $notice One of the keys handled by show_admin_notices().
	 */
	private static function queue_admin_notice(string $notice): void
	{
		set_transient(self::admin_notice_key(), $notice, MINUTE_IN_SECONDS);
	}

	/**
	 * Read and consume the pending one-shot admin notice.
	 */
	private static function take_admin_notice(): string
	{
		$notice = get_transient(self::admin_notice_key());
		if (false === $notice) {
			return '';
		}

		delete_transient(self::admin_notice_key());

		return (string) $notice;
	}

	/**
	 * Shows admin notices
	 */
	public static function show_admin_notices(): void
	{
		global $pagenow, $post;

		// Show only on booking edit page
		if ($pagenow !== 'post.php' || ! $post || $post->post_type !== 'mhmrentiva_booking') {
			return;
		}

		// The notice travels in a short-lived per-user transient set just before
		// our own redirect, not in the URL. `?message=` is WordPress core's own
		// post.php parameter -- core indexes $messages[$post_type][ $message ]
		// with it -- so putting our string keys there both collided with core's
		// numeric indices and left the notice replayable on refresh.
		$message = self::take_admin_notice();

		switch ($message) {
			case 'email_sent':
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Email sent successfully!', 'mhm-rentiva') . '</p></div>';
				break;
			case 'availability_conflict':
				echo '<div class="notice notice-error"><p>' . esc_html__('The vehicle is already booked for these dates. The booking details were not saved.', 'mhm-rentiva') . '</p></div>';
				break;
			case 'service_unavailable':
				echo '<div class="notice notice-error"><p>' . esc_html__('This vehicle is not available for rental. Please select another vehicle.', 'mhm-rentiva') . '</p></div>';
				break;
			case 'availability_unverified':
				echo '<div class="notice notice-error"><p>' . esc_html__('Vehicle availability could not be checked. The booking details were not saved. Please try again.', 'mhm-rentiva') . '</p></div>';
				break;
		}
	}

	private static function format_price(float $price): string
	{
		// Canonical currency formatting. The placement was already canonical here,
		// but number_format_i18n() takes its separators from the WP locale rather
		// than from WooCommerce, so this cell could still drift from the rest.
		return \MHMRentiva\Admin\Core\CurrencyHelper::format_price($price, 2);
	}

	/**
	 * AJAX: Get email template for selected type
	 */
	public static function ajax_get_email_template()
	{
		// Nonce check
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'mhmrentiva_send_email' ) ) {
			wp_send_json_error( __( 'Security check failed.', 'mhm-rentiva' ) );
			return;
		}

		$req = VerifiedRequest::from($_POST);

		$booking_id = $req->int('booking_id');

		// edit_post on the booking the request actually names, not the blanket
		// edit_posts: this handler acts on whichever booking_id arrives, so a
		// generic "can edit something" check was not a check on this object.
		if ($booking_id && ! current_user_can('edit_post', $booking_id)) {
			wp_send_json_error(__('You do not have permission to perform this action.', 'mhm-rentiva'));
			return;
		}

		// edit_post answers "may this user edit that post", never "is that post a
		// booking". map_meta_cap grants it for any post the caller owns, so without a
		// type check this handler acts on whatever id arrives.
		if ($booking_id && 'mhmrentiva_booking' !== get_post_type($booking_id)) {
			// No return after this: wp_send_json_error() ends the request through
			// wp_die(), so a return here would be unreachable.
			wp_send_json_error(__('Booking not found.', 'mhm-rentiva'));
		}

		$email_type = $req->text('email_type');

		if (! $booking_id || ! in_array($email_type, self::CUSTOMER_EMAIL_TYPES, true)) {
			wp_send_json_error(__('Missing required fields.', 'mhm-rentiva'));
			return;
		}

		// Get template content (empty subject and message to get defaults)
		// The defaults the operator edits are the ones the customer will read.
		$template = SiteLocaleString::in_site_locale(static fn(): array => self::prepare_email_content($booking_id, $email_type, '', ''));

		wp_send_json_success(
			array(
				'subject' => $template['subject'],
				'message' => $template['message'],
			)
		);
	}

	public static function ajax_send_customer_email()
	{
		// Nonce check
		$nonce = isset($_POST['mhmrentiva_email_nonce']) ? sanitize_text_field(wp_unslash( (string) $_POST['mhmrentiva_email_nonce'])) : '';
		if (! wp_verify_nonce($nonce, 'mhmrentiva_send_email')) {
			wp_send_json_error(__('Security check failed.', 'mhm-rentiva'));
			return;
		}

		$req = VerifiedRequest::from($_POST);

		$booking_id = $req->int('booking_id');

		// edit_post on the booking the request actually names, not the blanket
		// edit_posts: this handler acts on whichever booking_id arrives, so a
		// generic "can edit something" check was not a check on this object.
		if (! $booking_id || ! current_user_can('edit_post', $booking_id)) {
			wp_send_json_error(__('You do not have permission to perform this action.', 'mhm-rentiva'));
			return;
		}

		// edit_post answers "may this user edit that post", never "is that post a
		// booking". map_meta_cap grants it for any post the caller owns, so without a
		// type check this handler acts on whatever id arrives.
		if ('mhmrentiva_booking' !== get_post_type($booking_id)) {
			// No return after this: wp_send_json_error() ends the request through
			// wp_die(), so a return here would be unreachable.
			wp_send_json_error(__('Booking not found.', 'mhm-rentiva'));
		}

		$email_type = $req->text('email_type');
		$subject    = $req->text('email_subject');
		// wp_kses_post rather than sanitize_text_field: the body is authored in a
		// rich-text field and its HTML formatting has to survive into the email.
		$message = wp_kses_post( (string) ( $req->raw('email_message') ?? '' ) );

		if (! in_array($email_type, self::CUSTOMER_EMAIL_TYPES, true)) {
			wp_send_json_error(__('Missing required fields.', 'mhm-rentiva'));
			return;
		}

		// Prepare email content (if subject/message empty, defaults will be used)
		$email_content = self::compose_customer_email($booking_id, $email_type, $subject, $message);

		// Get customer information
		$customer_email = get_post_meta($booking_id, '_mhmrentiva_customer_email', true);

		if (! $customer_email) {
			wp_send_json_error(__('Customer email not found.', 'mhm-rentiva'));
			return;
		}

		$html_body = $email_content['html'];

		// Get sender settings
		$from_name    = \MHMRentiva\Admin\Settings\Groups\EmailSettings::get_from_name();
		$from_address = \MHMRentiva\Admin\Settings\Groups\EmailSettings::get_from_address();

		// Handle test mode
		if (\MHMRentiva\Admin\Settings\Groups\EmailSettings::is_test_mode()) {
			$customer_email = \MHMRentiva\Admin\Settings\Groups\EmailSettings::get_test_address();
		}

		// Send email
		$sent = wp_mail(
			$customer_email,
			$email_content['subject'],
			$html_body,
			array(
				'Content-Type: text/html; charset=UTF-8',
				'From: ' . $from_name . ' <' . $from_address . '>',
			)
		);

		if ($sent) {
			// In the booking history (function scan H16): which e-mail went out.
			self::add_history_note(
				$booking_id,
				/* translators: %s: e-mail subject */
				sprintf(__('E-mail sent to the customer: %s', 'mhm-rentiva'), (string) $email_content['subject']),
				'customer_contact'
			);
			wp_send_json_success(__('Email sent successfully!', 'mhm-rentiva'));
		} else {
			wp_send_json_error(__('Failed to send email. Please check your WordPress email configuration.', 'mhm-rentiva'));
		}
	}

	/**
	 * AJAX: Add history note
	 */
	public static function ajax_add_booking_history_note()
	{
		// Nonce check
		$nonce = isset($_POST['mhmrentiva_history_nonce']) ? sanitize_text_field(wp_unslash( (string) $_POST['mhmrentiva_history_nonce'])) : '';
		if (! wp_verify_nonce($nonce, 'mhmrentiva_add_history_note')) {
			wp_send_json_error(__('Security check failed.', 'mhm-rentiva'));
			return;
		}

		$req = VerifiedRequest::from($_POST);

		$booking_id = $req->int('booking_id');

		// edit_post on the booking the request actually names, not the blanket
		// edit_posts: this handler acts on whichever booking_id arrives, so a
		// generic "can edit something" check was not a check on this object.
		if (! $booking_id || ! current_user_can('edit_post', $booking_id)) {
			wp_send_json_error(__('You do not have permission to perform this action.', 'mhm-rentiva'));
			return;
		}

		// edit_post answers "may this user edit that post", never "is that post a
		// booking". map_meta_cap grants it for any post the caller owns, so without a
		// type check this handler acts on whatever id arrives.
		if ('mhmrentiva_booking' !== get_post_type($booking_id)) {
			// No return after this: wp_send_json_error() ends the request through
			// wp_die(), so a return here would be unreachable.
			wp_send_json_error(__('Booking not found.', 'mhm-rentiva'));
		}

		$note_type    = $req->text('note_type', 'manual');
		$note_content = $req->textarea('note_content');

		// A hand-made status/payment/system row would read as Rentiva's own.
		if (! in_array($note_type, self::NOTE_TYPES, true)) {
			// wp_send_json_error() ends the request (wp_die()); no return after it.
			wp_send_json_error(__('Missing required fields.', 'mhm-rentiva'));
		}

		if (! $note_content) {
			wp_send_json_error(__('Missing required fields.', 'mhm-rentiva'));
			return;
		}

		// Add note
		$result = self::add_history_note($booking_id, $note_content, $note_type);

		if ($result) {
			wp_send_json_success(__('Note added successfully!', 'mhm-rentiva'));
		} else {
			wp_send_json_error(__('Failed to add note.', 'mhm-rentiva'));
		}
	}
}
