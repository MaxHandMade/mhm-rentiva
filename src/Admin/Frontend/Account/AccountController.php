<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Account;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Legacy/public hook and template naming kept for backward compatibility.





use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Booking\Helpers\CancellationHandler;
use MHMRentiva\Admin\Settings\Core\SettingsCore;



/**
 * Account Controller
 *
 * Customer account management using WordPress standard login system
 * Similar structure to WooCommerce My Account system
 *
 * @since 4.0.0
 */
final class AccountController {


	use EndpointHelperTrait;



	/**
	 * Safe sanitize text field that handles null values
	 */
	public static function sanitize_text_field_safe($value)
	{
		if ($value === null || $value === '') {
			return '';
		}
		return sanitize_text_field( (string) $value);
	}



	private static ?self $instance = null;

	private function __construct()
	{
		// Singleton pattern
	}

	public static function instance(): self
	{
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function register(): void
	{
		$instance = self::instance();

		// AJAX handlers
		add_action('wp_ajax_mhm_rentiva_update_account', array( self::class, 'ajax_update_account' ));
		add_action('wp_ajax_mhm_rentiva_add_favorite', array( self::class, 'ajax_add_favorite' ));
		add_action('wp_ajax_mhm_rentiva_remove_favorite', array( self::class, 'ajax_remove_favorite' ));
		add_action('wp_ajax_mhm_rentiva_clear_favorites', array( self::class, 'ajax_clear_favorites' ));
		add_action('wp_ajax_mhm_rentiva_cancel_booking', array( self::class, 'ajax_cancel_booking' ));

		// Receipt management
		add_action('wp_ajax_mhm_rentiva_upload_receipt', array( self::class, 'ajax_upload_receipt' ));
		add_action('wp_ajax_mhm_rentiva_remove_receipt', array( self::class, 'ajax_remove_receipt' ));

		// Assets
		add_action('wp_enqueue_scripts', array( self::class, 'enqueue_assets' ));

		// Rewrite endpoints
		add_action('init', array( self::class, 'add_endpoints' ));

		// Login/Logout redirects
		add_filter('login_redirect', array( self::class, 'login_redirect' ), 10, 3);
		add_filter('logout_redirect', array( self::class, 'logout_redirect' ), 10, 3);

		// Route wp-login.php to WC My Account for non-admin users
		add_filter('login_url', array( self::class, 'override_login_url' ), 10, 3);
		add_action('login_init', array( self::class, 'redirect_wp_login' ));

		// Communication preferences handler
		add_action('init', array( self::class, 'handle_communication_preferences' ));

		// Remaining payment AJAX handler
		\MHMRentiva\Admin\Payment\WooCommerce\RemainingPaymentHandler::register();
	}

	// Logic moved to EndpointHelperTrait

	/**
	 * Add rewrite endpoints
	 */
	public static function add_endpoints(): void
	{
		// If WooCommerce is active, do not register standalone endpoints to avoid conflicts
		// WooCommerceIntegration class handles endpoints for WooCommerce My Account
		if (class_exists('WooCommerce')) {
			return;
		}

		add_rewrite_endpoint(self::get_endpoint_slug('bookings', 'rentiva-bookings'), EP_ROOT | EP_PAGES);
		add_rewrite_endpoint(self::get_endpoint_slug('favorites', 'rentiva-favorites'), EP_ROOT | EP_PAGES);
		add_rewrite_endpoint(self::get_endpoint_slug('payment_history', 'rentiva-payment-history'), EP_ROOT | EP_PAGES);
		add_rewrite_endpoint(self::get_endpoint_slug('messages', 'rentiva-messages'), EP_ROOT | EP_PAGES);
	}





	/**
	 * Load assets
	 */
	public static function enqueue_assets(): void
	{
		// Enqueue Notifications (v1.3.3)
		wp_enqueue_style('mhm-rentiva-notifications');

		// Check if we're on messages endpoint - dequeue customer-messages scripts
		$messages_slug = self::get_endpoint_slug('messages', 'rentiva-messages');
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only endpoint hint used only for conditional asset loading.
		$endpoint = get_query_var('endpoint');
		if (! is_string($endpoint) || '' === $endpoint) {
			$endpoint = isset($_GET['endpoint']) ? sanitize_text_field(wp_unslash($_GET['endpoint'])) : '';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Check both logical name and dynamic slug query var
		if ($endpoint === 'messages' || get_query_var($messages_slug) !== '' || apply_filters('mhm_rentiva_force_messages_assets', false)) {
			// Prevent customer-messages.js from loading (we use REST API in template)
			add_action(
				'wp_enqueue_scripts',
				function () {
					wp_dequeue_script('mhm-customer-messages');
					wp_dequeue_script('mhm-customer-messages-standalone');
					wp_deregister_script('mhm-customer-messages');
					wp_deregister_script('mhm-customer-messages-standalone');
				},
				999
			);

			// Enqueue Account Messages JS (filemtime-based version avoids stale browser cache during dev).
			$account_messages_js_path = MHM_RENTIVA_PLUGIN_PATH . 'assets/js/frontend/account-messages.js';
			$account_messages_js_ver  = file_exists($account_messages_js_path)
				? MHM_RENTIVA_VERSION . '.' . filemtime($account_messages_js_path)
				: MHM_RENTIVA_VERSION;
			wp_enqueue_script(
				'mhm-rentiva-account-messages',
				MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/account-messages.js',
				array( 'jquery' ),
				$account_messages_js_ver,
				true
			);

			// Enqueue Dashicons for password toggles
			wp_enqueue_style('dashicons');

			// Localize Account Messages JS
			$current_user  = wp_get_current_user();
			$customer_name = $current_user->display_name;
			if (! $customer_name) {
				$customer_name = $current_user->user_login;
			}
			wp_localize_script(
				'mhm-rentiva-account-messages',
				'mhmRentivaMessages',
				array(
					'restUrl'       => rest_url('mhm-rentiva/v1/'),
					'restNonce'     => wp_create_nonce('wp_rest'),
					'customerEmail' => $current_user->user_email,
					'customerName'  => $customer_name,
					'i18n'          => array(
						'threadIdNotFound'   => __('Thread ID not found.', 'mhm-rentiva'),
						'confirmClose'       => __('Are you sure you want to close this conversation? You won\'t be able to send more messages.', 'mhm-rentiva'),
						'closing'            => __('Closing...', 'mhm-rentiva'),
						'messageClosed'      => __('Message closed successfully.', 'mhm-rentiva'),
						'closeFailed'        => __('Failed to close message.', 'mhm-rentiva'),
						'loadingMessages'    => __('Loading messages...', 'mhm-rentiva'),
						'noMessages'         => __('No messages found yet.', 'mhm-rentiva'),
						'loadFailed'         => __('Failed to load messages.', 'mhm-rentiva'),
						'loginRequired'      => __('Please login to access your messages.', 'mhm-rentiva'),
						'permissionDenied'   => __('You do not have permission to access messages.', 'mhm-rentiva'),
						'new'                => __('New', 'mhm-rentiva'),
						'customer'           => __('Customer', 'mhm-rentiva'),
						'administrator'      => __('Administrator', 'mhm-rentiva'),
						'loadingThread'      => __('Loading thread...', 'mhm-rentiva'),
						'threadLoadFailed'   => __('Failed to load thread.', 'mhm-rentiva'),
						'noMessagesFound'    => __('No messages found.', 'mhm-rentiva'),
						'closeMessage'       => __('Close Message', 'mhm-rentiva'),
						'conversationClosed' => __('This conversation is closed.', 'mhm-rentiva'),
						'fillRequired'       => __('Please fill in all required fields.', 'mhm-rentiva'),
						'sending'            => __('Sending...', 'mhm-rentiva'),
						'messageSent'        => __('Message sent successfully.', 'mhm-rentiva'),
						'messageSendFailed'  => __('Message could not be sent.', 'mhm-rentiva'),
						'errorOccurred'      => __('An error occurred. Please try again.', 'mhm-rentiva'),
						'enterReply'         => __('Please enter your reply.', 'mhm-rentiva'),
						'replySent'          => __('Reply sent successfully.', 'mhm-rentiva'),
						'replyFailed'        => __('Failed to send reply.', 'mhm-rentiva'),
					),
				)
			);
		}

		// Enqueue Vehicle Interactions on Favorites endpoint (v1.3.3)
		$favorites_slug = self::get_endpoint_slug('favorites', 'rentiva-favorites');
		if ($endpoint === 'favorites' || get_query_var($favorites_slug) !== '') {
			wp_enqueue_script('mhm-vehicle-interactions');
		}

		// Enqueue Account Privacy JS on Dashboard
		if ($endpoint === 'dashboard' || empty($endpoint)) {
			wp_enqueue_script(
				'mhm-rentiva-account-privacy',
				MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/account-privacy.js',
				array( 'jquery' ),
				MHM_RENTIVA_VERSION,
				true
			);

			wp_localize_script(
				'mhm-rentiva-account-privacy',
				'mhmRentivaPrivacy',
				array(
					'ajaxUrl' => admin_url('admin-ajax.php'),
					'nonce'   => wp_create_nonce('mhm_gdpr_nonce'),
					'homeUrl' => home_url(),
					'i18n'    => array(
						'confirmExport'       => __('Are you sure you want to export your data? This may take a few minutes.', 'mhm-rentiva'),
						'exporting'           => __('Exporting...', 'mhm-rentiva'),
						'exportSuccess'       => __('Data exported successfully!', 'mhm-rentiva'),
						'exportError'         => __('An error occurred while exporting data', 'mhm-rentiva'),
						'confirmWithdraw'     => __('Are you sure you want to withdraw your consent? This will disable data processing for your account.', 'mhm-rentiva'),
						'processing'          => __('Processing...', 'mhm-rentiva'),
						'withdrawSuccess'     => __('Consent withdrawn successfully!', 'mhm-rentiva'),
						'withdrawError'       => __('An error occurred while withdrawing consent', 'mhm-rentiva'),
						'confirmDeletePrompt' => __('This action cannot be undone. Type "DELETE" to confirm account deletion:', 'mhm-rentiva'),
						'confirmDeleteFinal'  => __('Are you absolutely sure? This will permanently delete your account and all data.', 'mhm-rentiva'),
						'deleting'            => __('Deleting...', 'mhm-rentiva'),
						'deleteSuccess'       => __('Account deleted successfully. You will be redirected to the homepage.', 'mhm-rentiva'),
						'deleteError'         => __('An error occurred while deleting account', 'mhm-rentiva'),
						'error'               => __('Error', 'mhm-rentiva'),
						'unknownError'        => __('Unknown error', 'mhm-rentiva'),
					),
				)
			);
		}

		// Enqueue Booking Cancellation JS on Booking Detail
		$view_booking_slug = self::get_endpoint_slug('view_booking', 'view-rentiva-booking');
		if ($endpoint === 'booking-detail' || get_query_var($view_booking_slug) !== '') {
			wp_enqueue_script(
				'mhm-rentiva-booking-cancellation',
				MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/booking-cancellation.js',
				array( 'jquery' ),
				MHM_RENTIVA_VERSION,
				true
			);

			wp_localize_script(
				'mhm-rentiva-booking-cancellation',
				'mhmRentivaCancellation',
				array(
					'ajaxUrl' => admin_url('admin-ajax.php'),
					'nonce'   => wp_create_nonce('mhm_cancel_booking_nonce'),
					'i18n'    => array(
						'cancelling' => __('Cancelling...', 'mhm-rentiva'),
						'error'      => __('An error occurred. Please try again.', 'mhm-rentiva'),
					),
				)
			);
		}

		// Conditional Loading: Only load account assets on account pages or endpoints
		// This prevents CSS leakage onto other pages (like Vehicles List).
		// See M5 Audit (Step 4B).
		$is_account             = self::is_account_page();
		$has_endpoint           = ! empty($endpoint);
		$is_woocommerce_account = function_exists('is_account_page') && is_account_page();

		if ($is_account || $has_endpoint || $is_woocommerce_account) {
			// Load CSS file
			wp_enqueue_style(
				'mhm-rentiva-my-account',
				MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/my-account.css',
				array(),
				MHM_RENTIVA_VERSION
			);

			// Load Stats Cards CSS
			wp_enqueue_style(
				'mhm-rentiva-stats-cards',
				MHM_RENTIVA_PLUGIN_URL . 'assets/css/components/stats-cards.css',
				array(),
				MHM_RENTIVA_VERSION
			);

			wp_enqueue_style(
				'mhm-rentiva-booking-detail',
				MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/booking-detail.css',
				array(),
				MHM_RENTIVA_VERSION
			);

			wp_enqueue_style(
				'mhm-rentiva-bookings-page',
				MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/bookings-page.css',
				array(),
				MHM_RENTIVA_VERSION
			);
		}

		wp_enqueue_script(
			'mhm-rentiva-my-account',
			MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/my-account.js',
			array( 'jquery' ),
			MHM_RENTIVA_VERSION,
			true
		);

		wp_localize_script(
			'mhm-rentiva-my-account',
			'mhmRentivaAccount',
			array(
				'ajaxUrl'     => admin_url('admin-ajax.php'),
				'restUrl'     => rest_url('mhm-rentiva/v1/'),
				'nonce'       => wp_create_nonce('mhm_rentiva_account'),
				'uploadNonce' => wp_create_nonce('mhm_rentiva_upload_receipt'),
				'restNonce'   => wp_create_nonce('wp_rest'),
				'i18n'        => array(
					'loading'                => __('Loading...', 'mhm-rentiva'),
					'error'                  => __('An error occurred.', 'mhm-rentiva'),
					'success'                => __('Success!', 'mhm-rentiva'),
					'confirm'                => __('Are you sure?', 'mhm-rentiva'),
					'uploading'              => __('Uploading...', 'mhm-rentiva'),
					'upload_success'         => __('Receipt uploaded successfully.', 'mhm-rentiva'),
					'upload_error'           => __('Receipt upload failed.', 'mhm-rentiva'),
					'savedSuccessfully'      => __('Account details saved successfully.', 'mhm-rentiva'),
					'removedFromFavorites'   => __('Vehicle removed from favorites.', 'mhm-rentiva'),
					'addedToFavorites'       => __('Vehicle added to favorites.', 'mhm-rentiva'),
					'favoritesCleared'       => __('All favorites cleared.', 'mhm-rentiva'),
					'passwords_do_not_match' => __('Passwords do not match.', 'mhm-rentiva'),
					'cancel_changes_confirm' => __('Are you sure you want to cancel changes?', 'mhm-rentiva'),
					'no_favorites'           => __('No favorite vehicles yet.', 'mhm-rentiva'),
					'login_required'         => __('You can add vehicles to favorites using the heart icon.', 'mhm-rentiva'),
					'cancel_booking'         => __('Cancel Booking', 'mhm-rentiva'),
					'save_changes'           => __('Save Changes', 'mhm-rentiva'),
					'favorites_count_single' => __('vehicle in your favorites', 'mhm-rentiva'),
					'favorites_count_plural' => __('vehicles in your favorites', 'mhm-rentiva'),
				),
			)
		);
	}

	/**
	 * Are we on My Account page?
	 */
	private static function is_account_page(): bool
	{
		// WooCommerce My Account page (plugin renders via endpoint hooks here)
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			return true;
		}

		global $post;

		if (! $post) {
			return false;
		}

		// Standalone shortcode pages
		return has_shortcode($post->post_content, 'rentiva_my_bookings') ||
			has_shortcode($post->post_content, 'rentiva_my_favorites') ||
			has_shortcode($post->post_content, 'rentiva_messages') ||
			has_shortcode($post->post_content, 'rentiva_payment_history');
	}

	/**
	 * Login redirect — route users by role after authentication.
	 *
	 * Admin    → default (wp-admin)
	 * Vendor   → /panel/
	 * Customer → WC My Account (/hesabim/)
	 */
	public static function login_redirect(string $redirect_to, string $request, $user): string
	{
		if (! is_a($user, 'WP_User')) {
			return $redirect_to;
		}

		// Admins go to wp-admin (default behaviour)
		if ($user->has_cap('manage_options')) {
			return $redirect_to;
		}

		// Vendors go to /panel/
		if (in_array('rentiva_vendor', (array) $user->roles, true)) {
			$panel_page = get_page_by_path('panel');
			if ($panel_page instanceof \WP_Post) {
				return (string) get_permalink($panel_page);
			}
			return home_url('/panel/');
		}

		// Customers go to My Account
		if (get_user_meta($user->ID, 'mhm_rentiva_customer', true)) {
			return self::get_account_url();
		}

		return $redirect_to;
	}

	/**
	 * Override wp_login_url() to point to WC My Account login form.
	 *
	 * Affects every wp_login_url() call site-wide (VendorApply, VehiclesList,
	 * SessionManager, etc.) so they all use the WC form instead of wp-login.php.
	 * Admin-initiated requests (AJAX, REST, wp-admin) are excluded.
	 *
	 * @param string $login_url  The default wp-login.php URL.
	 * @param string $redirect   Where to redirect after login.
	 * @param bool   $force_reauth Whether to force re-authentication.
	 */
	public static function override_login_url(string $login_url, string $redirect, bool $force_reauth): string
	{
		unset($force_reauth);

		// Don't override for admin/AJAX/REST contexts
		if (is_admin() || wp_doing_ajax() || ( defined('REST_REQUEST') && REST_REQUEST )) {
			return $login_url;
		}

		$wc_login = self::get_wc_login_url();
		if ($wc_login === '') {
			return $login_url;
		}

		if ($redirect !== '') {
			$wc_login = add_query_arg('redirect_to', rawurlencode($redirect), $wc_login);
		}

		return $wc_login;
	}

	/**
	 * Redirect wp-login.php to WC My Account for non-admin visitors.
	 *
	 * Admins and POST requests (actual login form submissions) are not redirected.
	 */
	public static function redirect_wp_login(): void
	{
		// Allow POST (form submission) — WP handles auth, then login_redirect takes over
		$request_method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
		if ($request_method === 'POST') {
			return;
		}

		// Allow specific actions that need wp-login.php (password reset, registration, logout confirmation)
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only wp-login routing parameter; no state change occurs here.
		$action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$allowed_actions = array( 'logout', 'lostpassword', 'rp', 'resetpass', 'confirmaction' );
		if (in_array($action, $allowed_actions, true)) {
			return;
		}

		// Already logged-in admin — let WP handle normally
		if (is_user_logged_in() && current_user_can('manage_options')) {
			return;
		}

		$wc_login = self::get_wc_login_url();
		if ($wc_login === '') {
			return;
		}

		// Preserve redirect_to parameter
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only wp-login redirect hint; no state change occurs here.
		$redirect_to = isset($_GET['redirect_to']) ? sanitize_url(wp_unslash($_GET['redirect_to'])) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ($redirect_to !== '') {
			$wc_login = add_query_arg('redirect_to', rawurlencode($redirect_to), $wc_login);
		}

		wp_safe_redirect($wc_login);
		exit;
	}

	/**
	 * Get WooCommerce My Account URL (login form).
	 */
	private static function get_wc_login_url(): string
	{
		if (! function_exists('wc_get_page_permalink')) {
			return '';
		}
		$url = wc_get_page_permalink('myaccount');
		return ( is_string($url) && $url !== '' ) ? $url : '';
	}

	/**
	 * Logout redirect
	 */
	public static function logout_redirect(string $redirect_to, string $requested_redirect_to, $user): string
	{
		unset($user);

		// If logging out from My Account, redirect to homepage
		$account_url  = self::get_account_url();
		$account_path = wp_parse_url($account_url, PHP_URL_PATH);

		if (is_string($account_path) && $account_path !== '' && strpos($requested_redirect_to, $account_path) !== false) {
			return home_url('/');
		}

		return $redirect_to;
	}

	/**
	 * Get My Account URL
	 */
	public static function get_account_url(): string
	{
		// If WooCommerce exists, use WooCommerce My Account URL
		if (class_exists('WooCommerce') && function_exists('wc_get_page_id')) {
			$account_page_id = \wc_get_page_id('myaccount');
			if ($account_page_id && $account_page_id > 0) {
				$permalink = get_permalink($account_page_id);
				if (is_string($permalink) && $permalink !== '') {
					return $permalink;
				}
			}
		}

		// Use global Shortcode URL Manager
		$shortcode_url = ShortcodeUrlManager::get_page_url('rentiva_my_account');
		if (is_string($shortcode_url) && $shortcode_url !== '') {
			return $shortcode_url;
		}

		// Fallback to home URL
		return home_url('/');
	}

	/**
	 * AJAX: Clear all favorites
	 */
	public static function ajax_clear_favorites(): void
	{
		$nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
		if (empty($nonce) || ! wp_verify_nonce($nonce, 'mhm_rentiva_account')) {
			wp_send_json_error(array( 'message' => __('Security error.', 'mhm-rentiva') ), 400);
		}

		if (! is_user_logged_in()) {
			wp_send_json_error(array( 'message' => __('You must be logged in', 'mhm-rentiva') ), 403);
		}

		$user_id = get_current_user_id();
		delete_user_meta($user_id, 'mhm_rentiva_favorites');

		wp_send_json_success(
			array(
				'message'         => __('All favorites cleared', 'mhm-rentiva'),
				'favorites_count' => 0,
			)
		);
	}


	/**
	 * AJAX: Upload payment receipt (deposit slip)
	 */
	public static function ajax_upload_receipt(): void
	{
		if (! is_user_logged_in()) {
			wp_send_json_error(array( 'message' => __('You must be logged in.', 'mhm-rentiva') ), 403);
		}

		if (! isset($_POST['nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'mhm_rentiva_upload_receipt')) {
			wp_send_json_error(array( 'message' => __('Security check failed.', 'mhm-rentiva') ), 400);
		}

		$user_id    = get_current_user_id();
		$booking_id = isset($_POST['booking_id']) ? (int) $_POST['booking_id'] : 0;
		if ($booking_id <= 0) {
			wp_send_json_error(array( 'message' => __('Invalid booking ID.', 'mhm-rentiva') ), 400);
		}

		// Ownership check
		$booking_author = (int) get_post_field('post_author', $booking_id);
		if ($booking_author !== $user_id && ! current_user_can('edit_post', $booking_id)) {
			wp_send_json_error(array( 'message' => __('You are not allowed to upload for this booking.', 'mhm-rentiva') ), 403);
		}

		if (empty($_FILES['receipt'])) {
			wp_send_json_error(array( 'message' => __('No file uploaded.', 'mhm-rentiva') ), 400);
		}

		// Allow only images/pdf
		$allowed_mimes = array(
			'image/jpeg'      => 'jpg',
			'image/png'       => 'png',
			'application/pdf' => 'pdf',
		);

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File array is validated by wp_handle_upload().
		$file = isset($_FILES['receipt']) ? wp_unslash($_FILES['receipt']) : array();
		if (empty($file) || ! isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
			wp_send_json_error(array( 'message' => __('Upload error.', 'mhm-rentiva') ), 400);
		}

		$filetype = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
		if (empty($filetype['type']) || ! isset($allowed_mimes[ $filetype['type'] ])) {
			wp_send_json_error(array( 'message' => __('Invalid file type. Allowed: JPG, PNG, PDF.', 'mhm-rentiva') ), 400);
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$overrides = array( 'test_form' => false );
		$upload    = wp_handle_upload($file, $overrides);
		if (isset($upload['error'])) {
			wp_send_json_error(array( 'message' => $upload['error'] ), 400);
		}

		// Create attachment
		$attachment = array(
			'post_mime_type' => $upload['type'],
			'post_title'     => sanitize_file_name($file['name']),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		$attach_id  = wp_insert_attachment($attachment, $upload['file']);
		if (! is_wp_error($attach_id)) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
			wp_update_attachment_metadata($attach_id, $attach_data);
		}

		// Save to booking meta
		update_post_meta($booking_id, '_mhm_receipt_attachment_id', $attach_id);
		update_post_meta($booking_id, '_mhm_receipt_status', 'submitted');
		update_post_meta($booking_id, '_mhm_receipt_uploaded_by', $user_id);
		update_post_meta($booking_id, '_mhm_receipt_uploaded_at', current_time('mysql'));

		wp_send_json_success(
			array(
				'message'       => __('Receipt uploaded successfully.', 'mhm-rentiva'),
				'attachment_id' => $attach_id,
				'url'           => wp_get_attachment_url($attach_id),
			)
		);
	}

	/**
	 * AJAX: Remove payment receipt
	 */
	public static function ajax_remove_receipt(): void
	{
		if (! is_user_logged_in()) {
			wp_send_json_error(array( 'message' => __('You must be logged in.', 'mhm-rentiva') ), 403);
		}

		if (! isset($_POST['nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'mhm_rentiva_upload_receipt')) {
			wp_send_json_error(array( 'message' => __('Security check failed.', 'mhm-rentiva') ), 400);
		}

		$user_id    = get_current_user_id();
		$booking_id = isset($_POST['booking_id']) ? (int) $_POST['booking_id'] : 0;

		if ($booking_id <= 0) {
			wp_send_json_error(array( 'message' => __('Invalid booking ID.', 'mhm-rentiva') ), 400);
		}

		// Ownership check
		$booking_author = (int) get_post_field('post_author', $booking_id);
		if ($booking_author !== $user_id && ! current_user_can('edit_post', $booking_id)) {
			wp_send_json_error(array( 'message' => __('You are not allowed to remove receipt for this booking.', 'mhm-rentiva') ), 403);
		}

		$attachment_id = (int) get_post_meta($booking_id, '_mhm_receipt_attachment_id', true);
		if (! $attachment_id) {
			wp_send_json_error(array( 'message' => __('No receipt found to remove.', 'mhm-rentiva') ), 404);
		}

		// Remove attachment
		$deleted = wp_delete_attachment($attachment_id, true);

		if ($deleted) {
			// Clean up meta
			delete_post_meta($booking_id, '_mhm_receipt_attachment_id');
			delete_post_meta($booking_id, '_mhm_receipt_status');
			delete_post_meta($booking_id, '_mhm_receipt_uploaded_by');
			delete_post_meta($booking_id, '_mhm_receipt_uploaded_at');

			wp_send_json_success(array( 'message' => __('Receipt removed successfully.', 'mhm-rentiva') ));
		} else {
			wp_send_json_error(array( 'message' => __('Failed to remove receipt file.', 'mhm-rentiva') ), 500);
		}
	}


	/**
	 * AJAX: Update account
	 */
	public static function ajax_update_account(): void
	{
		try {
			// Nonce verification
			if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'mhm_rentiva_account')) {
				wp_send_json_error(array( 'message' => __('Security check failed.', 'mhm-rentiva') ));
			}

			// Login check
			if (! is_user_logged_in()) {
				wp_send_json_error(array( 'message' => __('Please login.', 'mhm-rentiva') ));
			}

			// Profile editing check
			$profile_editable = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_customer_profile_editable', '1');
			if ($profile_editable !== '1') {
				wp_send_json_error(array( 'message' => __('Profile editing is currently disabled.', 'mhm-rentiva') ));
			}

			$user_id = get_current_user_id();

			// Get and sanitize data - apply wp_unslash() before sanitization
			$display_name = isset($_POST['display_name']) ? sanitize_text_field(wp_unslash( (string) $_POST['display_name'])) : '';
			$first_name   = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash( (string) $_POST['first_name'])) : '';
			$last_name    = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash( (string) $_POST['last_name'])) : '';
			$phone        = isset($_POST['phone']) ? sanitize_text_field(wp_unslash( (string) $_POST['phone'])) : '';
			$address      = isset($_POST['address']) ? sanitize_textarea_field(wp_unslash($_POST['address'])) : '';

			// Update user information
			$user_data = array(
				'ID' => $user_id,
			);

			if (! empty($display_name)) {
				$user_data['display_name'] = $display_name;
			}
			if (! empty($first_name)) {
				$user_data['first_name'] = $first_name;
			}
			if (! empty($last_name)) {
				$user_data['last_name'] = $last_name;
			}

			$result = wp_update_user($user_data);

			if (is_wp_error($result)) {
				wp_send_json_error(array( 'message' => $result->get_error_message() ));
			}

			// Update meta information
			if (! empty($phone)) {
				update_user_meta($user_id, 'mhm_rentiva_phone', $phone);
			}
			if (! empty($address)) {
				update_user_meta($user_id, 'mhm_rentiva_address', $address);
			}

			wp_send_json_success(
				array(
					'message' => __('Account updated successfully.', 'mhm-rentiva'),
				)
			);
		} catch (\Exception $e) {
			wp_send_json_error(array( 'message' => __('An error occurred while updating your account.', 'mhm-rentiva') ));
		}
	}

	/**
	 * AJAX: Add favorite
	 */
	/**
	 * AJAX: Add favorite
	 *
	 * @deprecated 1.3.3 Use mhm_rentiva_toggle_favorite via FavoritesService
	 */
	public static function ajax_add_favorite(): void
	{
		// Forward to shared service logic or just die if dead?
		// Since JS is cleaned up, this shouldn't be called.
		// We'll return error to signal deprecation if hit.
		wp_send_json_error(array( 'message' => __('This endpoint is deprecated. Please refresh your page.', 'mhm-rentiva') ), 410);
	}

	/**
	 * AJAX: Remove favorite
	 */
	/**
	 * AJAX: Remove favorite
	 *
	 * @deprecated 1.3.3 Use mhm_rentiva_toggle_favorite via FavoritesService
	 */
	public static function ajax_remove_favorite(): void
	{
		wp_send_json_error(array( 'message' => __('This endpoint is deprecated. Please refresh your page.', 'mhm-rentiva') ), 410);
	}



	/**
	 * Handle communication preferences update
	 */
	public static function handle_communication_preferences(): void
	{
		if (! is_user_logged_in()) {
			return;
		}

		if (! isset($_POST['update_comm_preferences'])) {
			return;
		}

		// Nonce check
		if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['comm_prefs_nonce'] ?? '')), 'update_comm_preferences')) {
			wp_die(esc_html__('Security check failed.', 'mhm-rentiva'));
			return;
		}

		$user_id = get_current_user_id();

		// Update communication preferences
		$welcome_email         = isset($_POST['welcome_email']) ? '1' : '0';
		$booking_notifications = isset($_POST['booking_notifications']) ? '1' : '0';
		$marketing_emails      = isset($_POST['marketing_emails']) ? '1' : '0';

		update_user_meta($user_id, 'mhm_welcome_email', $welcome_email);
		update_user_meta($user_id, 'mhm_booking_notifications', $booking_notifications);
		update_user_meta($user_id, 'mhm_marketing_emails', $marketing_emails);

		// Redirect back to dashboard with success message
		wp_safe_redirect(add_query_arg('comm_prefs_updated', '1', self::get_account_url()));
		exit;
	}

	/**
	 * AJAX: Cancel Booking
	 * Handles booking cancellation requests from frontend
	 */
	public static function ajax_cancel_booking(): void
	{
		// Check if user is logged in
		if (! is_user_logged_in()) {
			wp_send_json_error(
				array(
					'message' => __('You must be logged in to cancel a booking.', 'mhm-rentiva'),
				),
				403
			);
		}

		// Verify nonce
		if (! isset($_POST['nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'mhm_cancel_booking_nonce')) {
			wp_send_json_error(
				array(
					'message' => __('Security verification failed. Please refresh and try again.', 'mhm-rentiva'),
				),
				400
			);
		}

		// Get booking ID
		$booking_id = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;
		if ($booking_id === 0) {
			wp_send_json_error(
				array(
					'message' => __('Invalid booking ID.', 'mhm-rentiva'),
				),
				400
			);
		}

		// Get cancellation reason (optional)
		$reason = isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash($_POST['reason'])) : '';

		// Get current user ID
		$user_id = get_current_user_id();

		// Use CancellationHandler to process cancellation
		$result = CancellationHandler::cancel_booking($booking_id, $user_id, $reason, false);

		if (is_wp_error($result)) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
				),
				400
			);
		}

		// Success
		wp_send_json_success(
			array(
				'message'    => __('Booking cancelled successfully. A confirmation email has been sent.', 'mhm-rentiva'),
				'booking_id' => $booking_id,
			)
		);
	}
	/**
	 * Get booking view URL
	 * Handles both standalone and WooCommerce environments
	 *
	 * @param int $booking_id Booking ID
	 * @return string URL
	 */
	public static function get_booking_view_url(int $booking_id): string
	{
		// WooCommerce Integration
		if (class_exists('WooCommerce') && function_exists('wc_get_endpoint_url')) {
			// Get base bookings URL
			// Get view booking URL
			$view_slug = self::get_endpoint_slug('view_booking', 'view-vehicle-booking');
			return \wc_get_endpoint_url($view_slug, (string) $booking_id, \wc_get_page_permalink('myaccount'));
		}

		// Standalone
		return add_query_arg(
			array(
				'action' => 'view_booking',
				'id'     => $booking_id,
			),
			self::get_account_url()
		);
	}
}
