<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Notifications;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Emails\Core\Mailer;
use MHMRentiva\Admin\Licensing\Mode;



/**
 * Sends transactional emails for vendor marketplace events.
 *
 * Hooks into do_action() calls fired by VendorOnboardingController,
 * VendorVehicleReviewManager, and Vehicle Lifecycle hooks.
 * Pro-only: registered only when canUseVendorMarketplace() is true.
 *
 * @since 4.22.0
 */
final class VendorNotifications {


	/**
	 * Register hooks and extend the email registry.
	 */
	public static function register(): void
	{
		if (! Mode::canUseVendorMarketplace()) {
			return;
		}

		add_filter('mhm_rentiva_email_registry', array( self::class, 'add_templates' ));

		add_action('mhm_rentiva_vendor_approved', array( self::class, 'on_vendor_approved' ), 10, 2);
		add_action('mhm_rentiva_vendor_rejected', array( self::class, 'on_vendor_rejected' ), 10, 3);
		add_action('mhm_rentiva_vehicle_approved', array( self::class, 'on_vehicle_approved' ), 10, 2);
		add_action('mhm_rentiva_vehicle_rejected', array( self::class, 'on_vehicle_rejected' ), 10, 3);
		add_action('mhm_rentiva_vendor_application_submitted', array( self::class, 'on_application_submitted' ), 10, 1);
		add_action('mhm_rentiva_vendor_suspended', array( self::class, 'on_vendor_suspended' ), 10, 1);

		// Admin notifications for vendor vehicle activities
		add_action('mhm_rentiva_vendor_vehicle_submitted', array( self::class, 'on_vehicle_submitted' ), 10, 2);
		add_action('mhm_rentiva_vehicle_needs_rereview', array( self::class, 'on_vehicle_needs_rereview' ), 10, 1);

		// Vehicle lifecycle notifications
		add_action('mhm_rentiva_vehicle_lifecycle_changed', array( self::class, 'on_lifecycle_changed' ), 10, 3);
		add_action('mhm_rentiva_vehicle_expiry_warning_first', array( self::class, 'on_expiry_warning_first' ), 10, 4);
		add_action('mhm_rentiva_vehicle_expiry_warning_second', array( self::class, 'on_expiry_warning_second' ), 10, 4);
		add_action('mhm_rentiva_vehicle_renewed', array( self::class, 'on_vehicle_renewed' ), 10, 1);
		add_action('mhm_rentiva_vehicle_relist', array( self::class, 'on_vehicle_relisted' ), 10, 2);

		// Financial notifications
		add_action('mhm_rentiva_payout_approved', array( self::class, 'on_payout_approved' ), 10, 3);
		add_action('mhm_rentiva_payout_rejected', array( self::class, 'on_payout_rejected' ), 10, 4);
		add_action('mhm_rentiva_iban_change_approved', array( self::class, 'on_iban_change_approved' ), 10, 1);
		add_action('mhm_rentiva_iban_change_rejected', array( self::class, 'on_iban_change_rejected' ), 10, 1);
	}

	/**
	 * Extend email registry with vendor notification templates.
	 *
	 * @param array $registry Existing registry.
	 * @return array Extended registry.
	 */
	public static function add_templates(array $registry): array
	{
		$registry['vendor_approved']              = array(
			'subject' => __('Welcome! Your vendor account is approved — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'vendor-approved',
		);
		$registry['vendor_rejected']              = array(
			'subject' => __('Your vendor application — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'vendor-rejected',
		);
		$registry['vendor_application_received']  = array(
			'subject' => __('We received your application — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'vendor-application-received',
		);
		$registry['vendor_application_new_admin'] = array(
			'subject' => __('[Admin] New vendor application from {{vendor.name}} — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'vendor-application-new-admin',
		);
		$registry['vehicle_approved']             = array(
			'subject' => __('Your vehicle is live! — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'vehicle-approved',
		);
		$registry['vehicle_rejected']             = array(
			'subject' => __('Your vehicle listing — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'vehicle-rejected',
		);
		$registry['payout_approved']              = array(
			'subject' => __('Payout approved — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'payout-approved',
		);
		$registry['payout_rejected']              = array(
			'subject' => __('Payout request update — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'payout-rejected',
		);
		$registry['iban_change_approved']         = array(
			'subject' => __('IBAN change approved — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'iban-change-approved',
		);
		$registry['iban_change_rejected']         = array(
			'subject' => __('IBAN change update — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'iban-change-rejected',
		);
		$registry['vendor_suspended']             = array(
			'subject' => __('Your vendor account has been suspended — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'vendor-suspended',
		);
		$registry['vehicle_submitted_admin']      = array(
			'subject' => __('[Admin] New vehicle submitted by {{vendor.name}} — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'vehicle-submitted-admin',
		);
		$registry['vehicle_rereview_admin']       = array(
			'subject' => __('[Admin] Vehicle edited — re-review needed — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'vehicle-rereview-admin',
		);

		// Vehicle Lifecycle Templates
		$registry['vehicle_activated']             = array(
			'subject' => __('Your listing is now live! — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'vehicle-activated',
		);
		$registry['vehicle_paused']                = array(
			'subject' => __('Vehicle paused — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'vehicle-paused',
		);
		$registry['vehicle_resumed']               = array(
			'subject' => __('Vehicle resumed — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'vehicle-resumed',
		);
		$registry['vehicle_withdrawn']             = array(
			'subject' => __('Vehicle withdrawn — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'vehicle-withdrawn',
		);
		$registry['vehicle_expired']               = array(
			'subject' => __('Your listing has expired — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'vehicle-expired',
		);
		$registry['vehicle_expiry_warning_first']  = array(
			'subject' => __('Your listing expires in {{lifecycle.days_remaining}} days — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'vehicle-expiry-warning',
		);
		$registry['vehicle_expiry_warning_second'] = array(
			'subject' => __('Urgent: Your listing expires in {{lifecycle.days_remaining}} days — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'vehicle-expiry-warning',
		);
		$registry['vehicle_renewed']               = array(
			'subject' => __('Listing renewed — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'vehicle-renewed',
		);
		$registry['vehicle_relisted']              = array(
			'subject' => __('Vehicle submitted for review — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'vehicle-relisted',
		);

		return $registry;
	}

	/**
	 * Vendor application submitted — notify vendor + admin.
	 *
	 * @param int $user_id Applicant user ID.
	 */
	public static function on_application_submitted(int $user_id): void
	{
		$user = get_userdata($user_id);
		if (! $user) {
			return;
		}

		$ctx = self::build_vendor_context($user);

		// Notify applicant
		Mailer::send('vendor_application_received', $user->user_email, $ctx);

		// Notify admin
		$admin_email = (string) get_option('admin_email');
		Mailer::send('vendor_application_new_admin', $admin_email, $ctx);
	}

	/**
	 * Vendor approved — notify vendor.
	 *
	 * @param int $user_id        Approved vendor user ID.
	 * @param int $application_id Application post ID.
	 */
	public static function on_vendor_approved(int $user_id, int $application_id): void
	{
		$user = get_userdata($user_id);
		if (! $user) {
			return;
		}

		$ctx = self::build_vendor_context($user);
		Mailer::send('vendor_approved', $user->user_email, $ctx);
	}

	/**
	 * Vendor rejected — notify vendor with reason.
	 *
	 * @param int    $user_id        Rejected user ID.
	 * @param int    $application_id Application post ID.
	 * @param string $reason         Rejection reason.
	 */
	public static function on_vendor_rejected(int $user_id, int $application_id, string $reason): void
	{
		$user = get_userdata($user_id);
		if (! $user) {
			return;
		}

		$ctx                        = self::build_vendor_context($user);
		$ctx['rejection']['reason'] = $reason;

		Mailer::send('vendor_rejected', $user->user_email, $ctx);
	}

	/**
	 * Vendor suspended — notify vendor.
	 *
	 * @param int $user_id Suspended vendor user ID.
	 */
	public static function on_vendor_suspended(int $user_id): void
	{
		$user = get_userdata($user_id);
		if (! $user) {
			return;
		}

		$ctx = self::build_vendor_context($user);
		Mailer::send('vendor_suspended', $user->user_email, $ctx);
	}

	/**
	 * Vehicle approved — notify vendor.
	 *
	 * @param int $vehicle_id Post ID of the approved vehicle.
	 * @param int $vendor_id  Vendor user ID.
	 */
	public static function on_vehicle_approved(int $vehicle_id, int $vendor_id): void
	{
		$user = get_userdata($vendor_id);
		if (! $user) {
			return;
		}

		$vehicle = get_post($vehicle_id);
		$ctx     = self::build_vendor_context($user);

		$ctx['vehicle']['title'] = $vehicle ? (string) $vehicle->post_title : '';
		$ctx['vehicle']['url']   = $vehicle ? (string) get_permalink($vehicle_id) : '';

		Mailer::send('vehicle_approved', $user->user_email, $ctx);
	}

	/**
	 * Vehicle rejected — notify vendor with reason.
	 *
	 * @param int    $vehicle_id Post ID of the rejected vehicle.
	 * @param int    $vendor_id  Vendor user ID.
	 * @param string $reason     Rejection reason.
	 */
	public static function on_vehicle_rejected(int $vehicle_id, int $vendor_id, string $reason): void
	{
		$user = get_userdata($vendor_id);
		if (! $user) {
			return;
		}

		$vehicle = get_post($vehicle_id);
		$ctx     = self::build_vendor_context($user);

		$ctx['vehicle']['title']    = $vehicle ? (string) $vehicle->post_title : '';
		$ctx['rejection']['reason'] = $reason;

		Mailer::send('vehicle_rejected', $user->user_email, $ctx);
	}

	/**
	 * Build base context array for a vendor user.
	 *
	 * @param \WP_User $user Vendor user object.
	 * @return array Context data.
	 */
	private static function build_vendor_context(\WP_User $user): array
	{
		return array(
			'vendor' => array(
				'name'  => $user->display_name,
				'email' => $user->user_email,
			),
			'site'   => array(
				'name' => (string) get_bloginfo('name'),
				'url'  => (string) home_url('/'),
			),
			'panel'  => array(
				'url' => (string) home_url('/panel/'),
			),
		);
	}

	/**
	 * Format a currency amount for display in emails.
	 *
	 * @param float $amount Amount to format.
	 * @return string Formatted string.
	 */
	private static function format_amount(float $amount): string
	{
		if (function_exists('wc_price')) {
			return wp_strip_all_tags( (string) wc_price($amount));
		}
		$symbol = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '₺';
		return $symbol . number_format(abs($amount), 2, '.', ',');
	}

	// ---------------------------------------------------------------
	// Admin notification handlers (vendor vehicle activities)
	// ---------------------------------------------------------------

	/**
	 * Vendor submitted a new vehicle — notify admin.
	 *
	 * @param int $vehicle_id Vehicle post ID.
	 * @param int $vendor_id  Vendor user ID.
	 */
	public static function on_vehicle_submitted(int $vehicle_id, int $vendor_id): void
	{
		$user = get_userdata($vendor_id);
		if (! $user) {
			return;
		}

		$vehicle        = get_post($vehicle_id);
		$ctx            = self::build_vendor_context($user);
		$ctx['vehicle'] = array(
			'title'     => $vehicle ? (string) $vehicle->post_title : '',
			'admin_url' => admin_url('post.php?post=' . $vehicle_id . '&action=edit'),
		);

		$admin_email = (string) get_option('admin_email');
		Mailer::send('vehicle_submitted_admin', $admin_email, $ctx);
	}

	/**
	 * Vendor edited a published vehicle with critical changes — notify admin.
	 *
	 * @param int $vehicle_id Vehicle post ID.
	 */
	public static function on_vehicle_needs_rereview(int $vehicle_id): void
	{
		$vehicle = get_post($vehicle_id);
		if (! $vehicle) {
			return;
		}

		$user = get_userdata( (int) $vehicle->post_author);
		if (! $user) {
			return;
		}

		$ctx            = self::build_vendor_context($user);
		$ctx['vehicle'] = array(
			'title'     => (string) $vehicle->post_title,
			'admin_url' => admin_url('post.php?post=' . $vehicle_id . '&action=edit'),
		);

		$admin_email = (string) get_option('admin_email');
		Mailer::send('vehicle_rereview_admin', $admin_email, $ctx);
	}

	// ---------------------------------------------------------------
	// Financial notification handlers
	// ---------------------------------------------------------------

	/**
	 * Payout approved — notify vendor.
	 *
	 * @param int   $payout_id Payout post ID.
	 * @param int   $vendor_id Vendor user ID.
	 * @param float $amount    Payout amount.
	 */
	public static function on_payout_approved(int $payout_id, int $vendor_id, float $amount): void
	{
		$user = get_userdata($vendor_id);
		if (! $user) {
			return;
		}

		$ctx           = self::build_vendor_context($user);
		$ctx['payout'] = array(
			'id'               => $payout_id,
			'amount'           => $amount,
			'amount_formatted' => self::format_amount($amount),
		);

		Mailer::send('payout_approved', $user->user_email, $ctx);
	}

	/**
	 * Payout rejected — notify vendor with reason.
	 *
	 * @param int    $payout_id Payout post ID.
	 * @param int    $vendor_id Vendor user ID.
	 * @param float  $amount    Payout amount.
	 * @param string $reason    Rejection reason.
	 */
	public static function on_payout_rejected(int $payout_id, int $vendor_id, float $amount, string $reason): void
	{
		$user = get_userdata($vendor_id);
		if (! $user) {
			return;
		}

		$ctx              = self::build_vendor_context($user);
		$ctx['payout']    = array(
			'id'               => $payout_id,
			'amount'           => $amount,
			'amount_formatted' => self::format_amount($amount),
		);
		$ctx['rejection'] = array(
			'reason' => $reason,
		);

		Mailer::send('payout_rejected', $user->user_email, $ctx);
	}

	/**
	 * IBAN change approved — notify vendor.
	 *
	 * @param int $vendor_id Vendor user ID.
	 */
	public static function on_iban_change_approved(int $vendor_id): void
	{
		$user = get_userdata($vendor_id);
		if (! $user) {
			return;
		}

		$ctx = self::build_vendor_context($user);
		Mailer::send('iban_change_approved', $user->user_email, $ctx);
	}

	/**
	 * IBAN change rejected — notify vendor.
	 *
	 * @param int $vendor_id Vendor user ID.
	 */
	public static function on_iban_change_rejected(int $vendor_id): void
	{
		$user = get_userdata($vendor_id);
		if (! $user) {
			return;
		}

		$ctx = self::build_vendor_context($user);
		Mailer::send('iban_change_rejected', $user->user_email, $ctx);
	}

	// ---------------------------------------------------------------
	// Vehicle lifecycle notification handlers
	// ---------------------------------------------------------------

	/**
	 * Vehicle lifecycle state changed — route to specific email template.
	 *
	 * @param int    $vehicle_id Vehicle post ID.
	 * @param string $old_status Previous lifecycle status.
	 * @param string $new_status New lifecycle status.
	 */
	public static function on_lifecycle_changed(int $vehicle_id, string $old_status, string $new_status): void
	{
		$vendor_id = (int) get_post_field('post_author', $vehicle_id);
		$user      = get_userdata($vendor_id);

		if (! $user) {
			return;
		}

		$vehicle = get_post($vehicle_id);
		$ctx     = self::build_vendor_context($user);

		$ctx['vehicle']   = array(
			'title' => $vehicle ? (string) $vehicle->post_title : '',
			'url'   => $vehicle ? (string) get_permalink($vehicle_id) : '',
			'id'    => $vehicle_id,
		);
		$ctx['lifecycle'] = array(
			'old_status' => $old_status,
			'new_status' => $new_status,
		);

		$template_map = array(
			'active'    => 'vehicle_activated',
			'paused'    => 'vehicle_paused',
			'expired'   => 'vehicle_expired',
			'withdrawn' => 'vehicle_withdrawn',
		);

		// Resume = paused → active
		if ($old_status === 'paused' && $new_status === 'active') {
			Mailer::send('vehicle_resumed', $user->user_email, $ctx);
			return;
		}

		if (isset($template_map[ $new_status ])) {
			// Add penalty info for withdrawal.
			if ($new_status === 'withdrawn') {
				$penalty                               = \MHMRentiva\Admin\Vehicle\PenaltyCalculator::calculate_withdrawal_penalty($vehicle_id, $vendor_id);
				$ctx['lifecycle']['penalty']           = $penalty;
				$ctx['lifecycle']['penalty_formatted'] = self::format_amount($penalty);
				$ctx['lifecycle']['cooldown_days']     = \MHMRentiva\Admin\Vehicle\VehicleLifecycleStatus::withdrawal_cooldown_days();
			}

			// Add expiry info for activation.
			if ($new_status === 'active') {
				$expires                           = get_post_meta($vehicle_id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_LISTING_EXPIRES_AT, true);
				$ctx['lifecycle']['expires_at']    = $expires ?: '';
				$ctx['lifecycle']['duration_days'] = \MHMRentiva\Admin\Vehicle\VehicleLifecycleStatus::listing_duration_days();
			}

			Mailer::send($template_map[ $new_status ], $user->user_email, $ctx);
		}
	}

	/**
	 * First expiry warning (10 days before).
	 *
	 * @param int    $vehicle_id Vehicle post ID.
	 * @param int    $vendor_id  Vendor user ID.
	 * @param string $expires_at Expiry datetime (UTC).
	 * @param int    $days_before Days before expiry.
	 */
	public static function on_expiry_warning_first(int $vehicle_id, int $vendor_id, string $expires_at, int $days_before): void
	{
		self::send_expiry_warning('vehicle_expiry_warning_first', $vehicle_id, $vendor_id, $expires_at, $days_before);
	}

	/**
	 * Second expiry warning (3 days before).
	 *
	 * @param int    $vehicle_id Vehicle post ID.
	 * @param int    $vendor_id  Vendor user ID.
	 * @param string $expires_at Expiry datetime (UTC).
	 * @param int    $days_before Days before expiry.
	 */
	public static function on_expiry_warning_second(int $vehicle_id, int $vendor_id, string $expires_at, int $days_before): void
	{
		self::send_expiry_warning('vehicle_expiry_warning_second', $vehicle_id, $vendor_id, $expires_at, $days_before);
	}

	/**
	 * Vehicle renewed — notify vendor.
	 *
	 * @param int $vehicle_id Vehicle post ID.
	 */
	public static function on_vehicle_renewed(int $vehicle_id): void
	{
		$vendor_id = (int) get_post_field('post_author', $vehicle_id);
		$user      = get_userdata($vendor_id);

		if (! $user) {
			return;
		}

		$vehicle = get_post($vehicle_id);
		$ctx     = self::build_vendor_context($user);

		$ctx['vehicle'] = array(
			'title' => $vehicle ? (string) $vehicle->post_title : '',
			'url'   => $vehicle ? (string) get_permalink($vehicle_id) : '',
		);

		$expires          = get_post_meta($vehicle_id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_LISTING_EXPIRES_AT, true);
		$ctx['lifecycle'] = array(
			'expires_at'    => $expires ?: '',
			'duration_days' => \MHMRentiva\Admin\Vehicle\VehicleLifecycleStatus::listing_duration_days(),
		);

		Mailer::send('vehicle_renewed', $user->user_email, $ctx);
	}

	/**
	 * Vehicle relisted — notify vendor.
	 *
	 * @param int $vehicle_id Vehicle post ID.
	 * @param int $vendor_id  Vendor user ID.
	 */
	public static function on_vehicle_relisted(int $vehicle_id, int $vendor_id): void
	{
		$user = get_userdata($vendor_id);
		if (! $user) {
			return;
		}

		$vehicle = get_post($vehicle_id);
		$ctx     = self::build_vendor_context($user);

		$ctx['vehicle'] = array(
			'title' => $vehicle ? (string) $vehicle->post_title : '',
		);

		Mailer::send('vehicle_relisted', $user->user_email, $ctx);
	}

	/**
	 * Send an expiry warning email.
	 *
	 * @param string $template_key Email template key.
	 * @param int    $vehicle_id   Vehicle post ID.
	 * @param int    $vendor_id    Vendor user ID.
	 * @param string $expires_at   Expiry datetime (UTC).
	 * @param int    $days_before  Days before expiry.
	 */
	private static function send_expiry_warning(string $template_key, int $vehicle_id, int $vendor_id, string $expires_at, int $days_before): void
	{
		$user = get_userdata($vendor_id);
		if (! $user) {
			return;
		}

		$vehicle = get_post($vehicle_id);
		$ctx     = self::build_vendor_context($user);

		$ctx['vehicle'] = array(
			'title' => $vehicle ? (string) $vehicle->post_title : '',
			'url'   => $vehicle ? (string) get_permalink($vehicle_id) : '',
		);

		$days_remaining   = max(0, (int) ceil(( strtotime($expires_at) - time() ) / DAY_IN_SECONDS));
		$ctx['lifecycle'] = array(
			'expires_at'     => $expires_at,
			'days_remaining' => $days_remaining,
		);

		Mailer::send($template_key, $user->user_email, $ctx);
	}
}
