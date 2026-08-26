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

		// Email sending handler
		add_action('admin_post_mhmrentiva_send_customer_email', array( self::class, 'handle_send_customer_email' ));

		// History note adding handler
		add_action('admin_post_mhmrentiva_add_booking_history_note', array( self::class, 'handle_add_booking_history_note' ));

		// AJAX handlers
		add_action('wp_ajax_mhmrentiva_send_customer_email', array( self::class, 'ajax_send_customer_email' ));
		add_action('wp_ajax_mhmrentiva_get_email_template', array( self::class, 'ajax_get_email_template' ));
		add_action('wp_ajax_mhmrentiva_add_booking_history_note', array( self::class, 'ajax_add_booking_history_note' ));

		// Auto note adding hooks
		// Note: "Booking created" note is added manually (in ManualBookingMetaBox and BookingForm)
		add_action('mhmrentiva_booking_status_changed', array( self::class, 'auto_add_status_change_note' ), 10, 3);
		// `mhmrentiva_payment_status_changed` was listened for here but fired by nothing in
		// either edition, so `auto_add_payment_note()` never ran and no payment note
		// was ever written. Rather than invent a firing site, the listener goes; the
		// method stays available for a caller that means it.

		// Show admin notices
		add_action('admin_notices', array( self::class, 'show_admin_notices' ));

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

		// Receipt review meta box
		add_meta_box(
			'mhmrentiva_receipt_box',
			__('Payment Receipt', 'mhm-rentiva'),
			array( self::class, 'render_receipt_box' ),
			self::get_post_type(),
			'side',
			'default'
		);
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
	public static function render_receipt_box(\WP_Post $post): void
	{
		$attach_id = (int) get_post_meta($post->ID, '_mhmrentiva_receipt_attachment_id', true);
		$status    = get_post_meta($post->ID, '_mhmrentiva_receipt_status', true);
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

		// Status update - Handle both field names from different meta boxes
		$new_status = $req->text('mhmrentiva_edit_status');
		if ($new_status === '') {
			$new_status = $req->text('mhmrentiva_booking_status');
		}
		if ($new_status) {
			$actor_user_id = get_current_user_id();

			Status::update_status($post_id, $new_status, $actor_user_id);
		}

		// Update booking details
		$pickup_date   = $req->text('mhmrentiva_edit_pickup_date');
		$pickup_time   = $req->text('mhmrentiva_edit_pickup_time');
		$dropoff_date  = $req->text('mhmrentiva_edit_dropoff_date');
		$dropoff_time  = $req->text('mhmrentiva_edit_dropoff_time');
		$guests        = max(1, $req->int('mhmrentiva_edit_guests', 1));
		$special_notes = $req->textarea('mhmrentiva_edit_special_notes');

		// Update meta data
		update_post_meta($post_id, '_mhmrentiva_pickup_date', $pickup_date);
		update_post_meta($post_id, '_mhmrentiva_start_time', $pickup_time); // Correct meta key
		update_post_meta($post_id, '_mhmrentiva_dropoff_date', $dropoff_date);
		update_post_meta($post_id, '_mhmrentiva_end_time', $dropoff_time); // Correct meta key
		update_post_meta($post_id, '_mhmrentiva_guests', $guests);
		update_post_meta($post_id, '_mhmrentiva_special_notes', $special_notes);

		// ✅ Auto calculation - When date is changed
		if ($pickup_date && $dropoff_date) {
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
		update_post_meta($post_id, '_mhmrentiva_booking_guests', $guests);

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
					self::email_customer_receipt_status($post_id, 'approved');
				} elseif ($action === 'reject') {
					update_post_meta($post_id, '_mhmrentiva_receipt_status', 'rejected');
					self::email_customer_receipt_status($post_id, 'rejected');
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
		$subject    = $req->text('email_subject');
		// wp_kses_post rather than sanitize_text_field: the body is authored in a
		// rich-text field and its HTML formatting has to survive into the email.
		$message = wp_kses_post( (string) ( $req->raw('email_message') ?? '' ) );

		$customer_email = get_post_meta($booking_id, '_mhmrentiva_customer_email', true);
		$customer_name  = get_post_meta($booking_id, '_mhmrentiva_customer_name', true);

		if (! $customer_email) {
			wp_die(esc_html__('Customer email not found.', 'mhm-rentiva'));
		}

		// Prepare subject and message based on email type
		$email_data = self::prepare_email_content($booking_id, $email_type, $subject, $message);

		// Wrap with premium layout
		$context   = \MHMRentiva\Admin\Emails\Core\Mailer::getBookingContext($booking_id) ?? array();
		$html_body = \MHMRentiva\Admin\Emails\Core\Templates::wrapWithLayout(
			$context,
			$email_data['subject'],
			$email_data['message']
		);

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
	public static function render_booking_history_box(\WP_Post $post): void
	{
		$history = get_post_meta($post->ID, '_mhmrentiva_booking_history', true);
		if (! is_array($history)) {
			$history = array();
		}

		echo '<div class="mhm-booking-history-box">';

		// Yeni not ekleme formu
		echo '<div class="mhm-add-history-note">';
		echo '<h4>' . esc_html__('Add Note', 'mhm-rentiva') . '</h4>';

		echo '<div class="mhm-add-history-note" data-booking-id="' . (int) $post->ID . '">';

		wp_nonce_field('mhmrentiva_add_history_note', 'mhmrentiva_history_nonce');

		echo '<p>';
		echo '<label for="note_content">' . esc_html__('Note:', 'mhm-rentiva') . '</label><br/>';
		echo '<textarea id="note_content" name="note_content" rows="3" class="widefat" placeholder="' . esc_attr__('Add a note about this booking...', 'mhm-rentiva') . '" required></textarea>';
		echo '</p>';

		echo '<p>';
		echo '<label for="note_type">' . esc_html__('Type:', 'mhm-rentiva') . '</label><br/>';
		echo '<select id="note_type" name="note_type" class="widefat" style="margin-bottom: 10px;">';
		echo '<option value="note">' . esc_html__('Note', 'mhm-rentiva') . '</option>';
		echo '<option value="status_change">' . esc_html__('Status Change', 'mhm-rentiva') . '</option>';
		echo '<option value="payment_update">' . esc_html__('Payment Update', 'mhm-rentiva') . '</option>';
		echo '<option value="customer_contact">' . esc_html__('Customer Contact', 'mhm-rentiva') . '</option>';
		echo '<option value="system">' . esc_html__('System', 'mhm-rentiva') . '</option>';
		echo '</select>';
		echo '</p>';

		echo '<p>';
		echo '<button type="button" class="button button-primary mhm-add-history-btn">' . esc_html__('Add Note', 'mhm-rentiva') . '</button>';
		echo '</p>';

		echo '</div>';
		echo '</div>';

		// JavaScript for AJAX forms

		echo '<hr style="margin: 20px 0;">';

		// Show history notes
		echo '<div class="mhm-history-list">';
		echo '<h4>' . esc_html__('History', 'mhm-rentiva') . '</h4>';

		if ( empty( $history ) ) {
			echo '<p class="description">' . esc_html__( 'No history found.', 'mhm-rentiva' ) . '</p>';
		} else {
			krsort( $history );

			$threshold = 5;
			$total     = count( $history );
			$index     = 0;

			foreach ( $history as $timestamp => $note ) {
				$timestamp = (int) $timestamp;
				$date      = date_i18n( get_option( 'date_format' ), $timestamp );
				$time      = date_i18n( get_option( 'time_format' ), $timestamp );
				$user      = get_userdata( $note['user_id'] );
				$user_name = $user ? $user->display_name : esc_html__( 'System', 'mhm-rentiva' );

				$type_class   = 'mhm-history-' . $note['type'];
				$type_label   = self::get_history_type_label( $note['type'] );
				$hidden_class = ( $index >= $threshold ) ? ' mhm-history-item--hidden' : '';

				echo '<div class="mhm-history-item ' . esc_attr( $type_class ) . esc_attr($hidden_class) . '">';
				echo '<div class="mhm-history-header">';
				echo '<span class="mhm-history-type">' . esc_html( $type_label ) . '</span>';
				echo '<span class="mhm-history-date">' . esc_html( $date . ' ' . $time ) . '</span>';
				echo '</div>';
				echo '<div class="mhm-history-content">';
				echo '<p>' . esc_html( $note['note'] ) . '</p>';
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
	 * History note adding handler
	 */
	public static function handle_add_booking_history_note(): void
	{
		// Nonce check
		$nonce = sanitize_text_field(wp_unslash($_POST['mhmrentiva_history_nonce'] ?? ''));
		if (! wp_verify_nonce($nonce, 'mhmrentiva_add_history_note')) {
			wp_die(esc_html__('Security check failed.', 'mhm-rentiva'));
		}

		$req = VerifiedRequest::from($_POST);

		$booking_id = $req->int('booking_id');

		// edit_post on the booking the request actually names, not the blanket
		// edit_posts: this handler acts on whichever booking_id arrives, so a
		// generic "can edit something" check was not a check on this object.
		if (! $booking_id || ! current_user_can('edit_post', $booking_id)) {
			wp_die(esc_html__('You do not have permission to add notes.', 'mhm-rentiva'));
		}

		// edit_post answers "may this user edit that post", never "is that post a
		// booking". map_meta_cap grants it for any post the caller owns, so without a
		// type check this handler acts on whatever id arrives.
		if ('mhmrentiva_booking' !== get_post_type($booking_id)) {
			wp_die(esc_html__('Booking not found.', 'mhm-rentiva'));
		}

		$note = $req->textarea('history_note');
		$type = $req->text('history_type', 'note');

		if (! $note) {
			wp_die(esc_html__('Invalid booking ID or empty note.', 'mhm-rentiva'));
		}

		// Get existing history
		$history = get_post_meta($booking_id, '_mhmrentiva_booking_history', true);
		if (! is_array($history)) {
			$history = array();
		}

		// Yeni not ekle
		$timestamp             = time() + current_datetime()->getOffset();
		$history[ $timestamp ] = array(
			'note'      => $note,
			'type'      => $type,
			'user_id'   => get_current_user_id(),
			'timestamp' => $timestamp,
		);

		// Save history
		update_post_meta($booking_id, '_mhmrentiva_booking_history', $history);

		self::queue_admin_notice('note_added');
		$redirect_url = add_query_arg(
			array(
				'post'   => $booking_id,
				'action' => 'edit',
			),
			admin_url('post.php')
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Add history note (programmatically)
	 */
	public static function add_history_note(int $booking_id, string $note, string $type = 'note', ?int $user_id = null): bool
	{
		if (! $user_id) {
			$user_id = get_current_user_id();
		}

		$history = get_post_meta($booking_id, '_mhmrentiva_booking_history', true);
		if (! is_array($history)) {
			$history = array();
		}
		$timestamp = time() + current_datetime()->getOffset();

		$history[ $timestamp ] = array(
			'note'      => $note,
			'type'      => $type,
			'user_id'   => $user_id,
			'timestamp' => $timestamp,
		);

		return update_post_meta($booking_id, '_mhmrentiva_booking_history', $history) !== false;
	}

	/**
	 * ✅ Rezervasyon maliyetlerini yeniden hesapla
	 */
	public static function recalculate_booking_costs(int $booking_id, string $pickup_date, string $dropoff_date): void
	{

		// Normalize date format
		$pickup_timestamp  = strtotime($pickup_date);
		$dropoff_timestamp = strtotime($dropoff_date);

		if (! $pickup_timestamp || ! $dropoff_timestamp) {
			return;
		}

		// Calculate days
		$days = max(1, ceil(( $dropoff_timestamp - $pickup_timestamp ) / ( 24 * 60 * 60 )));

		// Get vehicle ID
		$vehicle_id = get_post_meta($booking_id, '_mhmrentiva_vehicle_id', true);

		if (! $vehicle_id) {
			return;
		}

		// Get vehicle daily price - CORRECT META KEY
		$daily_price = (float) get_post_meta($vehicle_id, '_mhmrentiva_price_per_day', true);

		if ($daily_price <= 0) {
			return;
		}

		// Calculate total price
		$total_price = $daily_price * $days;

		// Get additional services price
		$additional_services_price = (float) get_post_meta($booking_id, '_mhmrentiva_additional_services_price', true);
		$total_price              += $additional_services_price;

		// ✅ Deposit calculation
		$payment_type     = get_post_meta($booking_id, '_mhmrentiva_payment_type', true);
		$deposit_amount   = 0;
		$remaining_amount = $total_price;

		if ($payment_type === 'deposit') {
			// Get deposit percentage (default 20%)
			$deposit_percentage = (float) get_post_meta($booking_id, '_mhmrentiva_deposit_percentage', true);
			if ($deposit_percentage <= 0) {
				$deposit_percentage = 20.0; // Default 20%
			}

			$deposit_amount   = ( $total_price * $deposit_percentage ) / 100;
			$remaining_amount = $total_price - $deposit_amount;
		}

		// Update meta keys
		update_post_meta($booking_id, '_mhmrentiva_rental_days', $days);
		update_post_meta($booking_id, '_mhmrentiva_total_price', $total_price);

		// ✅ Update deposit meta keys
		if ($payment_type === 'deposit') {
			update_post_meta($booking_id, '_mhmrentiva_deposit_amount', $deposit_amount);
			update_post_meta($booking_id, '_mhmrentiva_remaining_amount', $remaining_amount);
		}

		// Update old meta keys for compatibility
		update_post_meta($booking_id, '_mhmrentiva_booking_rental_days', $days);
		update_post_meta($booking_id, '_mhmrentiva_booking_total_price', $total_price);
	}

	/**
	 * ✅ WordPress hook listener - Auto calculation when meta is updated
	 */
	public static function on_booking_meta_updated(int $post_id, string $pickup_date, string $dropoff_date): void
	{
		self::recalculate_booking_costs($post_id, $pickup_date, $dropoff_date);
	}

	/**
	 * Auto add booking notes
	 */
	public static function auto_add_booking_notes(int $post_id, \WP_Post $post): void
	{
		// Only for booking post type
		if ($post->post_type !== 'mhmrentiva_booking') {
			return;
		}

		// Autosave and revision check
		if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
			return;
		}

		$already_created = get_post_meta($post_id, '_mhmrentiva_booking_created', true);

		// When new booking is created
		if ($post->post_status === 'publish' && $already_created !== '1') {
			self::add_history_note(
				$post_id,
				__('Booking created', 'mhm-rentiva'),
				'system'
			);
			update_post_meta($post_id, '_mhmrentiva_booking_created', '1');
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

		self::add_history_note($booking_id, $note, 'status_change');
	}

	/**
	 * Add payment status change note
	 */
	public static function auto_add_payment_note(int $booking_id, string $old_status, string $new_status): void
	{
		$status_labels = array(
			'unpaid'               => __('Unpaid', 'mhm-rentiva'),
			'pending_verification' => __('Pending Verification', 'mhm-rentiva'),
			'paid'                 => __('Paid', 'mhm-rentiva'),
			'partially_paid'       => __('Partially Paid', 'mhm-rentiva'),
			'refunded'             => __('Refunded', 'mhm-rentiva'),
		);

		$old_label = $status_labels[ $old_status ] ?? $old_status;
		$new_label = $status_labels[ $new_status ] ?? $new_status;

		$note = sprintf(
			/* translators: 1: old payment status, 2: new payment status */
			__('Payment status changed from %1$s to %2$s', 'mhm-rentiva'),
			$old_label,
			$new_label
		);

		self::add_history_note($booking_id, $note, 'payment_update');
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
			case 'note_added':
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Note added successfully!', 'mhm-rentiva') . '</p></div>';
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

		if (! $booking_id || ! $email_type) {
			wp_send_json_error(__('Missing required fields.', 'mhm-rentiva'));
			return;
		}

		// Get template content (empty subject and message to get defaults)
		$template = self::prepare_email_content($booking_id, $email_type, '', '');

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

		if (! $email_type) {
			wp_send_json_error(__('Missing required fields.', 'mhm-rentiva'));
			return;
		}

		// Prepare email content (if subject/message empty, defaults will be used)
		$email_content = self::prepare_email_content($booking_id, $email_type, $subject, $message);

		// Get customer information
		$customer_email = get_post_meta($booking_id, '_mhmrentiva_customer_email', true);

		if (! $customer_email) {
			wp_send_json_error(__('Customer email not found.', 'mhm-rentiva'));
			return;
		}

		// Wrap with premium layout
		$context   = \MHMRentiva\Admin\Emails\Core\Mailer::getBookingContext($booking_id) ?? array();
		$html_body = \MHMRentiva\Admin\Emails\Core\Templates::wrapWithLayout(
			$context,
			$email_content['subject'],
			$email_content['message']
		);

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
