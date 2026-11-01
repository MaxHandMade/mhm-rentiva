<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Account;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Core\LocationContext;
use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Frontend\Shortcodes\Rvs\RvsBreakdown;
use MHMRentiva\Admin\Frontend\Shortcodes\Rvs\RvsStatus;
use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Admin\Payment\Core\PaymentState;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The printable "Booking summary" (PR D, K14, R12).
 *
 * One page per booking, opened from the dashboard card, the booking detail and
 * the thank-you page. It is the booking detail's view of the booking on a
 * plain document of its own (no theme header, no footer, no wp_head) with a
 * "Print or save as PDF" button. It is not an invoice and says so.
 *
 * Request flow (template_redirect, then template_include):
 *  - logged out: redirect to the My Account page;
 *  - not a booking, or not the user's (WooCommerceIntegration::can_view_booking()):
 *    a real 404 through the theme's 404 template;
 *  - otherwise: no-cache headers and the document.
 *
 * The URL is the booking's view endpoint plus `mhmrentiva_summary`: `1` on a
 * WooCommerce site (the endpoint carries the id); the id itself on a site
 * without WooCommerce (the standalone view URL is not an endpoint).
 */
final class BookingSummaryPage {
	/**
	 * The public query var that asks for the summary.
	 */
	public const QUERY_VAR = 'mhmrentiva_summary';

	/**
	 * The view of the request's booking; null unless the request is a valid one.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $view = null;

	/**
	 * Did this request ask for a summary it may not see?
	 */
	private static bool $denied = false;

	public static function register(): void
	{
		add_action('template_redirect', array( self::class, 'maybe_render' ), 1);
		add_filter('template_include', array( self::class, 'template_include' ), 99);
	}

	/**
	 * URL of a booking's summary.
	 *
	 * @param bool|null $endpoint_carries_id Does the view URL carry the id as a
	 *                                       WooCommerce endpoint? null: detect.
	 */
	public static function url(int $booking_id, ?bool $endpoint_carries_id = null): string
	{
		$endpoint = $endpoint_carries_id ?? ( class_exists('WooCommerce') && function_exists('wc_get_endpoint_url') );
		$value    = $endpoint ? '1' : (string) $booking_id;

		return add_query_arg(self::QUERY_VAR, $value, AccountController::get_booking_view_url($booking_id));
	}

	/**
	 * `template_redirect`: decide what this request is.
	 */
	public static function maybe_render(): void
	{
		self::$view   = null;
		self::$denied = false;

		$raw = get_query_var(self::QUERY_VAR, '');
		if (! is_string($raw) || '' === $raw) {
			return;
		}

		if (! is_user_logged_in()) {
			wp_safe_redirect(self::login_url());
			exit;
		}

		$booking_id = self::requested_id($raw);
		if (! self::may_open($booking_id)) {
			global $wp_query;
			$wp_query->set_404();
			status_header(404);
			nocache_headers();
			// Core's redirect_canonical (template_redirect, priority 10) would
			// guess the account page for this 404 and 301 there: keep it a 404.
			add_filter('do_redirect_guess_404_permalink', '__return_false');
			remove_action('template_redirect', 'redirect_canonical');
			self::$denied = true;

			return;
		}

		nocache_headers();
		self::$view = self::view($booking_id);
	}

	/**
	 * `template_include`: the 404 template for a refused request, the summary
	 * document for a valid one, WordPress's choice for every other request.
	 */
	public static function template_include(string $template): string
	{
		if (self::$denied) {
			$not_found = get_404_template();

			return '' !== $not_found ? $not_found : $template;
		}

		if (null !== self::$view) {
			$file = Templates::locate('account/booking-summary');

			return null !== $file ? $file : $template;
		}

		return $template;
	}

	/**
	 * The view of this request's booking, for the document template.
	 *
	 * @return array<string, mixed>
	 */
	public static function current(): array
	{
		return self::$view ?? array();
	}

	/**
	 * The "Paid" and "Refunded" rows under a breakdown, from the recorded gross
	 * money (not the cart's tax display). The deposit row already names a paid
	 * deposit, so "Paid" is left out when it would repeat it.
	 *
	 * @param array<string, mixed> $breakdown RvsBreakdown::from_booking().
	 * @return list<array{kind: string, label: string, html: string}>
	 */
	public static function paid_rows(int $booking_id, array $breakdown): array
	{
		$state   = PaymentState::forBooking($booking_id);
		$deposit = (float) get_post_meta($booking_id, '_mhmrentiva_deposit_amount', true);
		$rows    = array();

		if ($state->paid() > 0 && ( null === ( $breakdown['deposit'] ?? null ) || abs(Money::toMinor($deposit) - $state->paid()) > 0 )) {
			$rows[] = array(
				'kind'  => 'paid',
				'label' => _x('Paid', 'payment box label', 'mhm-rentiva'),
				'html'  => BookingTimeline::recorded_html($state->paid(), $state->currency()),
			);
		}
		if ($state->refunded() > 0) {
			$rows[] = array(
				'kind'  => 'refunded',
				'label' => _x('Refunded', 'payment box label', 'mhm-rentiva'),
				'html'  => BookingTimeline::recorded_html($state->refunded(), $state->currency()),
			);
		}

		return $rows;
	}

	/**
	 * Everything the document prints.
	 *
	 * @return array<string, mixed>
	 */
	private static function view(int $booking_id): array
	{
		$vehicle_id = (int) get_post_meta($booking_id, '_mhmrentiva_vehicle_id', true);
		$vehicle    = $vehicle_id > 0 ? get_post($vehicle_id) : null;
		$location   = $vehicle_id > 0 ? LocationContext::for_vehicle($vehicle_id) : null;
		$planned    = BookingTimeline::planned($booking_id);
		$breakdown  = RvsBreakdown::from_booking($booking_id);

		$customer = trim( (string) get_post_meta($booking_id, '_mhmrentiva_customer_name', true));
		if ('' === $customer) {
			$user     = get_userdata( (int) get_post_meta($booking_id, '_mhmrentiva_customer_user_id', true));
			$customer = $user instanceof \WP_User ? $user->display_name : '';
		}

		$logo_id = (int) get_theme_mod('custom_logo');

		return array(
			'site_name'    => wp_specialchars_decode( (string) get_bloginfo('name'), ENT_QUOTES),
			'logo_url'     => $logo_id > 0 ? (string) wp_get_attachment_image_url($logo_id, 'medium') : '',
			'number'       => (string) mhmrentiva_get_display_id($booking_id),
			'status'       => RvsStatus::for(Status::get($booking_id)),
			'customer'     => $customer,
			'vehicle'      => $vehicle instanceof \WP_Post ? $vehicle->post_title : __('Unknown Vehicle', 'mhm-rentiva'),
			'pickup'       => BookingTimeline::moment_text($planned['pickup']),
			'return'       => BookingTimeline::moment_text($planned['return']),
			'location'     => null !== $location ? (string) $location->name : '',
			'breakdown'    => $breakdown,
			'deposit_paid' => RvsBreakdown::money_received($booking_id),
			'paid_rows'    => self::paid_rows($booking_id, $breakdown),
			'created'      => BookingTimeline::moment_text( (int) get_post_time('U', true, $booking_id)),
		);
	}

	/**
	 * The booking id the request names: the view endpoint's value on WooCommerce
	 * (`1` in the summary var), the summary var's own value otherwise.
	 */
	private static function requested_id(string $summary_value): int
	{
		$endpoint = get_query_var(AccountController::get_endpoint_slug('view_booking', 'view-vehicle-booking'), '');
		if (is_string($endpoint) && ctype_digit($endpoint) && (int) $endpoint > 0) {
			return (int) $endpoint;
		}

		return ( ctype_digit($summary_value) && '1' !== $summary_value ) ? (int) $summary_value : 0;
	}

	/**
	 * A booking the current user may see: customer, vehicle owner or administrator.
	 */
	private static function may_open(int $booking_id): bool
	{
		return $booking_id > 0
			&& 'mhmrentiva_booking' === get_post_type($booking_id)
			&& WooCommerceIntegration::can_view_booking($booking_id);
	}

	/**
	 * Where a logged-out visitor goes: the My Account page, which shows the
	 * sign-in form.
	 */
	private static function login_url(): string
	{
		if (function_exists('wc_get_page_permalink')) {
			$account = (string) wc_get_page_permalink('myaccount');
			if ('' !== $account) {
				return $account;
			}
		}

		return AccountController::get_account_url();
	}
}
