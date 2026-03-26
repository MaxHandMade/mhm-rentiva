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
 * Hooks into do_action() calls fired by VendorOnboardingController and VendorVehicleReviewManager.
 * Pro-only: registered only when canUseVendorMarketplace() is true.
 */
final class VendorNotifications
{

	/**
	 * Register hooks and extend the email registry.
	 */
	public static function register(): void
	{
		if (! Mode::canUseVendorMarketplace()) {
			return;
		}

		add_filter('mhm_rentiva_email_registry', array(self::class, 'add_templates'));

		add_action('mhm_rentiva_vendor_approved', array(self::class, 'on_vendor_approved'), 10, 2);
		add_action('mhm_rentiva_vendor_rejected', array(self::class, 'on_vendor_rejected'), 10, 3);
		add_action('mhm_rentiva_vehicle_approved', array(self::class, 'on_vehicle_approved'), 10, 2);
		add_action('mhm_rentiva_vehicle_rejected', array(self::class, 'on_vehicle_rejected'), 10, 3);
		add_action('mhm_rentiva_vendor_application_submitted', array(self::class, 'on_application_submitted'), 10, 1);
		add_action('mhm_rentiva_vendor_suspended', array(self::class, 'on_vendor_suspended'), 10, 1);

		// Admin notifications for vendor vehicle activities
		add_action('mhm_rentiva_vendor_vehicle_submitted', array(self::class, 'on_vehicle_submitted'), 10, 2);
		add_action('mhm_rentiva_vehicle_needs_rereview', array(self::class, 'on_vehicle_needs_rereview'), 10, 1);

		// Financial notifications
		add_action('mhm_rentiva_payout_approved', array(self::class, 'on_payout_approved'), 10, 3);
		add_action('mhm_rentiva_payout_rejected', array(self::class, 'on_payout_rejected'), 10, 4);
		add_action('mhm_rentiva_iban_change_approved', array(self::class, 'on_iban_change_approved'), 10, 1);
		add_action('mhm_rentiva_iban_change_rejected', array(self::class, 'on_iban_change_rejected'), 10, 1);
	}

	/**
	 * Extend email registry with vendor notification templates.
	 *
	 * @param array $registry Existing registry.
	 * @return array Extended registry.
	 */
	public static function add_templates(array $registry): array
	{
		$registry['vendor_approved'] = array(
			'subject' => __('Welcome! Your vendor account is approved — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'vendor-approved',
		);
		$registry['vendor_rejected'] = array(
			'subject' => __('Your vendor application — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'vendor-rejected',
		);
		$registry['vendor_application_received'] = array(
			'subject' => __('We received your application — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'vendor-application-received',
		);
		$registry['vendor_application_new_admin'] = array(
			'subject' => __('[Admin] New vendor application from {{vendor.name}} — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'vendor-application-new-admin',
		);
		$registry['vehicle_approved'] = array(
			'subject' => __('Your vehicle is live! — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'vehicle-approved',
		);
		$registry['vehicle_rejected'] = array(
			'subject' => __('Your vehicle listing — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'vehicle-rejected',
		);
		$registry['payout_approved'] = array(
			'subject' => __('Payout approved — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'payout-approved',
		);
		$registry['payout_rejected'] = array(
			'subject' => __('Payout request update — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'payout-rejected',
		);
		$registry['iban_change_approved'] = array(
			'subject' => __('IBAN change approved — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'iban-change-approved',
		);
		$registry['iban_change_rejected'] = array(
			'subject' => __('IBAN change update — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'iban-change-rejected',
		);
		$registry['vendor_suspended'] = array(
			'subject' => __('Your vendor account has been suspended — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'vendor-suspended',
		);
		$registry['vehicle_submitted_admin'] = array(
			'subject' => __('[Admin] New vehicle submitted by {{vendor.name}} — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'vehicle-submitted-admin',
		);
		$registry['vehicle_rereview_admin'] = array(
			'subject' => __('[Admin] Vehicle edited — re-review needed — {{site.name}}', 'mhm-rentiva'),
			'file'    => 'vehicle-rereview-admin',
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

		$ctx                          = self::build_vendor_context($user);
		$ctx['rejection']['reason']   = $reason;

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
			return wp_strip_all_tags((string) wc_price($amount));
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

		$vehicle     = get_post($vehicle_id);
		$ctx         = self::build_vendor_context($user);
		$ctx['vehicle'] = array(
			'title'    => $vehicle ? (string) $vehicle->post_title : '',
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

		$user = get_userdata((int) $vehicle->post_author);
		if (! $user) {
			return;
		}

		$ctx         = self::build_vendor_context($user);
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

		$ctx = self::build_vendor_context($user);
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

		$ctx = self::build_vendor_context($user);
		$ctx['payout'] = array(
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
}
