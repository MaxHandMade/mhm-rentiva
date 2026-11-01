<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Account;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded application queries are intentional in this module.



use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Core\ShortcodeUrlManager;



/**
 * Account Renderer
 *
 * Renders My Account templates
 *
 * @since 4.0.0
 */
final class AccountRenderer {

	/**
	 * The normalized payment statuses (get_user_payments()); the payment history
	 * `status` filter takes one of them, anything else is no filter.
	 */
	public const PAYMENT_STATUSES = array( 'pending', 'completed', 'cancelled' );

	/**
	 * Public query vars carrying the page of each account list. Two lists on one
	 * page (or one account page with both) must not page together. The bookings
	 * var is the original `mhmrentiva_page`, so existing links keep working.
	 */
	public const PAGE_VAR_BOOKINGS = 'mhmrentiva_page';
	public const PAGE_VAR_PAYMENTS = 'mhmrentiva_payments_page';

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

	/**
	 * Dashboard render
	 */
	public static function render_dashboard(array $atts = array()): string
	{
		return Templates::render('account/dashboard', self::get_dashboard_data($atts), true);
	}

	/*
	 * Direct-output counterparts of the render_*() methods below.
	 *
	 * The render_*() methods return a string because shortcodes must return their markup.
	 * Callers that simply print the result (the WooCommerce My Account endpoints) use these
	 * instead, so the account templates write to the output buffer themselves and escape each
	 * dynamic value at its own output site. That leaves no assembled-HTML string to echo --
	 * which matters here because these views embed inline SVG icons that wp_kses_post() would
	 * strip, so escaping a returned string was never an option.
	 */

	/**
	 * Dashboard, rendered straight to the page.
	 */
	public static function output_dashboard(array $atts = array()): void
	{
		Templates::render('account/dashboard', self::get_dashboard_data($atts));
	}

	/**
	 * Bookings list, rendered straight to the page.
	 */
	public static function output_bookings(array $atts = array()): void
	{
		Templates::render('account/bookings', self::get_bookings_data($atts));
	}

	/**
	 * Favorites, rendered straight to the page.
	 */
	public static function output_favorites(array $atts = array()): void
	{
		Templates::render('account/favorites', self::get_favorites_data($atts));
	}

	/**
	 * Payment history, rendered straight to the page.
	 */
	public static function output_payment_history(array $atts = array()): void
	{
		Templates::render('account/payment-history', self::get_payment_history_data($atts));
	}

	/**
	 * Messages, rendered straight to the page.
	 */
	public static function output_messages(array $atts = array()): void
	{
		$data_res = self::get_messages_data($atts);
		if (isset($data_res['error'])) {
			echo wp_kses_post($data_res['error']);
			return;
		}

		Templates::render('account/messages', $data_res);
	}

	/**
	 * Single booking detail, rendered straight to the page.
	 */
	public static function output_booking_detail(int $booking_id, bool $hide_nav = false): void
	{
		$data_res = self::get_booking_detail_data($booking_id, $hide_nav);
		if (isset($data_res['error'])) {
			echo wp_kses_post($data_res['error']);
			return;
		}

		// Vendor sees a dedicated detail view (mirrors the vendor email).
		$template = empty($data_res['data']['is_vendor_view']) ? 'account/booking-detail' : 'account/vendor-booking-detail';
		Templates::render($template, $data_res);
	}

	/**
	 * Get dashboard data
	 */
	public static function get_dashboard_data(array $atts = array()): array
	{
		unset($atts);

		$user = wp_get_current_user();

		// User bookings
		$bookings        = self::get_user_bookings($user->ID);
		$active_bookings = array_filter(
			$bookings,
			function ($booking) {
				$status = get_post_meta($booking->ID, '_mhmrentiva_status', true);
				return in_array($status, array( 'confirmed', 'in_progress' ), true);
			}
		);

		// The next rental (card + line under the greeting) and the upcoming list
		// (PR D, y16). `recent_bookings` stays filled for back-compat (Pro or a
		// theme template may read it); the shipped template no longer does.
		$now_ts  = time();
		$next_id = AccountDashboardView::next_booking_id( (int) $user->ID, $now_ts);
		$next    = null;
		if (null !== $next_id) {
			$next_pickup = AccountBookingsQuery::pickup_ts($next_id);
			$next        = array(
				'status'      => (string) get_post_meta($next_id, '_mhmrentiva_status', true),
				'pickup_ts'   => $next_pickup,
				'return_text' => AccountDashboardView::return_text($next_id),
			);
		}

		$upcoming = AccountBookingsQuery::page( (int) $user->ID, AccountBookingsQuery::UPCOMING, 1, 5);
		$counts   = AccountBookingsQuery::counts( (int) $user->ID);

		$data = array(
			'user'                  => $user,
			'bookings_count'        => count($bookings),
			'active_bookings_count' => count($active_bookings),
			'recent_bookings'       => array_slice($bookings, 0, 5),
			'navigation'            => self::get_navigation(),
			'greeting_name'         => AccountDashboardView::greeting_name($user),
			'subline'               => AccountDashboardView::subline($next, $now_ts),
			'next_rental'           => null !== $next_id ? AccountDashboardView::next_card($next_id) : null,
			'upcoming_posts'        => $upcoming['posts'],
			'counts'                => $counts,
			'has_bookings'          => array_sum($counts) > 0,
		);

		return array( 'data' => $data );
	}

	/**
	 * Bookings render
	 */
	public static function render_bookings(array $atts = array()): string
	{
		return Templates::render('account/bookings', self::get_bookings_data($atts), true);
	}

	/**
	 * Get bookings data
	 *
	 * The list shows one section (PR D, R5/R6): `mhmrentiva_view` names it,
	 * Upcoming by default. The shortcode's `status` attribute fixes the list to
	 * one status: no section control, no section. The old filter vars
	 * (mhmrentiva_status_filter, mhmrentiva_search_booking) still narrow the
	 * query; a URL that carries one of them and no section (an old bookmark)
	 * searches every section.
	 */
	public static function get_bookings_data(array $atts = array()): array
	{
		$user = wp_get_current_user();

		// Without an `order` each section keeps its own pickup order
		// (AccountBookingsQuery::segment_ids()). An explicit Sort Order wins: by
		// created date, the only `orderby` left (spec 3A Task 7a).
		$args = array(
			'limit'   => (int) ( $atts['limit'] ?? 10 ),
			'status'  => self::sanitize_text_field_safe($atts['status'] ?? ''),
			'orderby' => 'date',
			'order'   => self::sanitize_text_field_safe($atts['order'] ?? ''),
		);

		// The list's old filter params narrow the query itself, so the rows and
		// the page count both follow them (T7a-1).
		$args['status_filter'] = self::list_filter_var('mhmrentiva_status_filter');
		if ('all' === $args['status_filter']) {
			$args['status_filter'] = '';
		}
		$args['search'] = self::list_filter_var('mhmrentiva_search_booking');

		$show_segments   = '' === $args['status'];
		$args['segment'] = $show_segments ? self::requested_segment('' !== $args['status_filter'] || '' !== $args['search']) : '';

		// Pagination (show_pagination, on unless turned off): the page comes from
		// the public query var, within the section; a page past the end shows
		// the last one. Off is today's list -- the first `limit` bookings.
		$paginate = self::flag_is_on($atts['show_pagination'] ?? '1') && $args['limit'] > 0;
		// Capped so the query's offset ((page - 1) * limit) stays an integer: an
		// overflowing page number would reach SQL as a float.
		$page        = $paginate ? min(self::current_page(self::PAGE_VAR_BOOKINGS), intdiv(PHP_INT_MAX, $args['limit'])) : 1;
		$query       = self::query_user_bookings($user->ID, $args + array( 'page' => $page ));
		$total_pages = $paginate ? max(1, (int) $query->max_num_pages) : 1;

		// Past the last page (Review Focus 2), however large the number, shows the
		// last page. WP_Query leaves found_posts at 0 for an empty page, so count
		// on page 1.
		if ($paginate && $page > 1 && ( array() === $query->posts || $page > $total_pages )) {
			$total_pages = max(1, (int) self::query_user_bookings($user->ID, $args + array( 'page' => 1 ))->max_num_pages);
			$page        = min($page, $total_pages);
			$query       = self::query_user_bookings($user->ID, $args + array( 'page' => $page ));
		}

		$counts = AccountBookingsQuery::counts( (int) $user->ID);

		$data = array(
			'user'               => $user,
			'bookings'           => $query->posts,
			'navigation'         => ( isset($atts['hide_nav']) && $atts['hide_nav'] ) ? array() : self::get_navigation(),
			'atts'               => $atts,
			// Sections (PR D).
			'segment'            => $args['segment'],
			'show_segments'      => $show_segments,
			'counts'             => $counts,
			'has_bookings'       => array_sum($counts) > 0,
			// Column toggles: missing means on, the columns were unconditional.
			'show_image'         => self::flag_is_on($atts['show_image'] ?? '1'),
			'show_booking_dates' => self::flag_is_on($atts['show_booking_dates'] ?? '1'),
			'show_status'        => self::flag_is_on($atts['show_status_toggle'] ?? '1'),
			'show_price'         => self::flag_is_on($atts['show_price'] ?? '1'),
			'show_pagination'    => $paginate,
			'current_page'       => $page,
			'total_pages'        => $total_pages,
		);

		return array( 'data' => $data );
	}

	/**
	 * The section the request asks for (`mhmrentiva_view`). Missing or unknown
	 * is Upcoming -- or no section when $legacy_filtered (an old filter link).
	 */
	private static function requested_segment(bool $legacy_filtered): string
	{
		$view = self::list_filter_var('mhmrentiva_view');
		if (in_array($view, AccountBookingsQuery::SEGMENTS, true)) {
			return $view;
		}

		return $legacy_filtered ? '' : AccountBookingsQuery::UPCOMING;
	}

	/**
	 * URL of a section of the bookings list: the current URL with
	 * `mhmrentiva_view` set, from page 1, without the old filter vars.
	 *
	 * @param string $segment An AccountBookingsQuery::SEGMENTS value.
	 */
	public static function segment_url(string $segment): string
	{
		return add_query_arg(
			'mhmrentiva_view',
			$segment,
			remove_query_arg(array( self::PAGE_VAR_BOOKINGS, 'mhmrentiva_status_filter', 'mhmrentiva_search_booking' ))
		);
	}

	/**
	 * The bookings list for an editor preview: the shape get_bookings_data()
	 * returns, with no booking queried. The rows are PreviewSamples::bookings_rows()
	 * as view rows (booking_view_row()) in `booking_rows`; a sample row has no
	 * view link. One page, so no pager.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return array{data: array<string,mixed>}
	 */
	public static function get_bookings_preview_data(array $atts = array()): array
	{
		$rows = array();
		foreach (\MHMRentiva\Frontend\PreviewSamples::bookings_rows() as $sample) {
			$rows[] = $sample + array( 'view_url' => '' );
		}

		$data = array(
			'user'               => wp_get_current_user(),
			'bookings'           => array(),
			'booking_rows'       => $rows,
			'navigation'         => ( isset($atts['hide_nav']) && $atts['hide_nav'] ) ? array() : self::get_navigation(),
			'atts'               => $atts,
			// Every sample row is drawn, so all of them count as Upcoming (r2 m7).
			'segment'            => AccountBookingsQuery::UPCOMING,
			'show_segments'      => '' === self::sanitize_text_field_safe($atts['status'] ?? ''),
			'counts'             => array(
				AccountBookingsQuery::UPCOMING  => count($rows),
				AccountBookingsQuery::PAST      => 0,
				AccountBookingsQuery::CANCELLED => 0,
			),
			'has_bookings'       => array() !== $rows,
			'show_image'         => self::flag_is_on($atts['show_image'] ?? '1'),
			'show_booking_dates' => self::flag_is_on($atts['show_booking_dates'] ?? '1'),
			'show_status'        => self::flag_is_on($atts['show_status_toggle'] ?? '1'),
			'show_price'         => self::flag_is_on($atts['show_price'] ?? '1'),
			'show_pagination'    => false,
			'current_page'       => 1,
			'total_pages'        => 1,
		);

		return array( 'data' => $data );
	}

	/**
	 * One row of templates/account/bookings.php: the values the template prints
	 * for a booking, read exactly as the template used to read them per row
	 * (same metas, same fallbacks, raw values; the template formats them).
	 *
	 * `vehicle_title` is null when the vehicle post is missing (the template
	 * prints "N/A"); `vehicle_image` is the thumbnail URL or '' (the template
	 * falls back to the placeholder); `view_url` is '' for a row without a
	 * details page (preview samples).
	 *
	 * @return array{id:int,display_id:int,vehicle_title:?string,vehicle_image:string,status:mixed,pickup_date:mixed,pickup_time:mixed,dropoff_date:mixed,return_day:string,total_price:mixed,view_url:string}
	 */
	public static function booking_view_row(\WP_Post $booking): array
	{
		$vehicle_id = get_post_meta($booking->ID, '_mhmrentiva_vehicle_id', true);
		$vehicle    = get_post($vehicle_id);
		// Pickup time with fallbacks.
		$pickup_time = get_post_meta($booking->ID, '_mhmrentiva_start_time', true);
		if (! $pickup_time) {
			$pickup_time = get_post_meta($booking->ID, '_mhmrentiva_pickup_time', true);
		}
		if (! $pickup_time) {
			$pickup_time = get_post_meta($booking->ID, '_mhmrentiva_booking_pickup_time', true);
		}
		$vehicle_image = get_the_post_thumbnail_url($vehicle_id, 'thumbnail');

		return array(
			'id'            => (int) $booking->ID,
			// The number every other screen and email shows (the order number
			// when there is one); the raw post ID made one booking two numbers.
			'display_id'    => mhmrentiva_get_display_id( (int) $booking->ID),
			'vehicle_title' => $vehicle instanceof \WP_Post ? $vehicle->post_title : null,
			'vehicle_image' => is_string($vehicle_image) ? $vehicle_image : '',
			'status'        => get_post_meta($booking->ID, '_mhmrentiva_status', true),
			'pickup_date'   => get_post_meta($booking->ID, '_mhmrentiva_pickup_date', true),
			'pickup_time'   => $pickup_time,
			'dropoff_date'  => get_post_meta($booking->ID, '_mhmrentiva_dropoff_date', true),
			// The return day the list's section rule reads (AccountBookingsQuery chain).
			'return_day'    => AccountBookingsQuery::return_day( (int) $booking->ID),
			'total_price'   => get_post_meta($booking->ID, '_mhmrentiva_total_price', true),
			'view_url'      => AccountController::get_booking_view_url( (int) $booking->ID),
		);
	}

	/**
	 * The page of an account list the request asked for: the public query var
	 * $var (AccountController::PUBLIC_QUERY_VARS). Anything but a positive whole
	 * number -- zero, negative, letters, an array -- is page 1.
	 *
	 * @param string $var The list's page variable: PAGE_VAR_BOOKINGS or PAGE_VAR_PAYMENTS.
	 */
	public static function current_page(string $var): int
	{
		$raw = get_query_var($var, '');
		if (is_int($raw)) {
			$raw = (string) $raw;
		}
		if (! is_string($raw) || '' === $raw || ! ctype_digit($raw)) {
			return 1;
		}

		return max(1, absint($raw));
	}

	/**
	 * URL of page $page of the account list that pages on $var: the current URL
	 * with that variable set, so the list's filter vars (mhmrentiva_status_filter,
	 * mhmrentiva_search_booking, ...) and the other list's page stay. Page 1 drops
	 * the var.
	 *
	 * @param string $var  The list's page variable: PAGE_VAR_BOOKINGS or PAGE_VAR_PAYMENTS.
	 * @param int    $page Page number.
	 */
	public static function page_url(string $var, int $page): string
	{
		if ($page <= 1) {
			return remove_query_arg($var);
		}

		return add_query_arg($var, $page);
	}

	/**
	 * A filter param of the bookings list off WordPress's query_vars whitelist
	 * (AccountController::PUBLIC_QUERY_VARS); '' when absent or an array.
	 */
	private static function list_filter_var(string $name): string
	{
		$raw = get_query_var($name, null);
		if (null === $raw || is_array($raw)) {
			return '';
		}

		return sanitize_text_field(wp_unslash( (string) $raw));
	}

	/**
	 * A visibility flag: on unless explicitly off (CAM sends '0' for off).
	 *
	 * @param mixed $value Attribute value.
	 */
	private static function flag_is_on($value): bool
	{
		return ! in_array($value, array( '0', 0, false, 'false', 'no' ), true);
	}

	/**
	 * Favorites render
	 */
	public static function render_favorites(array $atts = array()): string
	{
		return Templates::render('account/favorites', self::get_favorites_data($atts), true);
	}

	/**
	 * Get favorites data
	 */
	public static function get_favorites_data(array $atts = array()): array
	{
		$user      = wp_get_current_user();
		$favorites = get_user_meta($user->ID, 'mhmrentiva_favorites', true);

		if (! is_array($favorites) || empty($favorites)) {
			$favorites = array();
		}

		// The favourites page renders the front-end standard card inside its own
		// .rvs-shell (templates/account/favorites.php): the rvs chain + rvs-card.css,
		// not the old vehicles-grid.css / vehicle-card.css.
		\MHMRentiva\Admin\Core\AssetManager::enqueue_rvs_card_styles();

		// Enqueue global interactions (v1.3.3) -- through AssetManager, so the
		// script always arrives with its one mhmrentiva_vars payload.
		\MHMRentiva\Admin\Core\AssetManager::enqueue_vehicle_interactions();

		// The favorites template's "Clear all favorites" and empty-state redraw
		// live in my-account.js. Enqueued here, not only from
		// AccountController::enqueue_assets() (account pages only), so the
		// payload below never lands on an unregistered handle, whoever renders
		// this template.
		AccountController::enqueue_account_script();

		// Localize (grid/list ortak)
		wp_localize_script(
			'mhm-rentiva-my-account',
			'mhmRentivaVehiclesList',
			array(
				'ajaxUrl'    => admin_url('admin-ajax.php'),
				'nonce'      => wp_create_nonce('mhmrentiva_vehicles_list'),
				'bookingUrl' => ShortcodeUrlManager::get_page_url('rentiva_booking_form'),
				'loginUrl'   => wp_login_url(),
				'icons'      => array(
					'heart' => \MHMRentiva\Helpers\Icons::get('heart'),
				),
				'text'       => array(
					'loading'                => __('Loading...', 'mhm-rentiva'),
					'no_vehicles'            => __('No vehicles found', 'mhm-rentiva'),
					'error'                  => __('An error occurred', 'mhm-rentiva'),
					'book_now'               => __('Book Now', 'mhm-rentiva'),
					'view_details'           => __('View Details', 'mhm-rentiva'),
					'added_to_favorites'     => __('Added to favorites', 'mhm-rentiva'),
					'removed_from_favorites' => __('Removed from favorites', 'mhm-rentiva'),
					'login_required'         => __('You must be logged in to add to favorites', 'mhm-rentiva'),
				),
			)
		);

		$data = array(
			'user'       => $user,
			'favorites'  => $favorites,
			'columns'    => (int) ( $atts['columns'] ?? 3 ),
			'navigation' => ( isset($atts['hide_nav']) && $atts['hide_nav'] ) ? array() : self::get_navigation(),
		);

		return array( 'data' => $data );
	}

	/**
	 * Payment History render
	 */
	public static function render_payment_history(array $atts = array()): string
	{
		return Templates::render('account/payment-history', self::get_payment_history_data($atts), true);
	}

	/**
	 * Get payment history data
	 */
	public static function get_payment_history_data(array $atts = array()): array
	{
		// Spec 3A Task 7b. The page comes from its own public query var
		// mhmrentiva_payments_page (exception 5; 3B-2a U5-2: it used to share
		// mhmrentiva_page with the bookings list): core `paged` is moved into the path by
		// redirect_canonical() on a WooCommerce endpoint, so "Next" always showed
		// page 1. Pagination off is today's first page, the page var ignored.
		// A limit of 0, empty or negative is not a page size: today's 10.
		$user     = wp_get_current_user();
		$per_page = (int) ( $atts['limit'] ?? 10 );
		$per_page = $per_page > 0 ? $per_page : 10;
		$paginate = self::flag_is_on($atts['show_pagination'] ?? '1');

		$args = array(
			'orderby' => 'amount' === ( $atts['orderby'] ?? '' ) ? 'amount' : 'date',
			'order'   => 'asc' === strtolower( (string) ( $atts['order'] ?? '' )) ? 'asc' : 'desc',
			'status'  => in_array($atts['status'] ?? '', self::PAYMENT_STATUSES, true) ? (string) $atts['status'] : '',
		);

		// Capped so the offset ((page - 1) * per_page) stays an integer.
		$page   = $paginate ? min(self::current_page(self::PAGE_VAR_PAYMENTS), intdiv(PHP_INT_MAX, $per_page)) : 1;
		$result = self::get_user_payments($user->ID, $per_page, $page, $args);

		$data = array(
			'user'                => $user,
			'payments'            => $result['items'],
			'total'               => $result['total'],
			'total_pages'         => $result['total_pages'],
			'current_page'        => $result['current_page'],
			'navigation'          => ( isset($atts['hide_nav']) && $atts['hide_nav'] ) ? array() : self::get_navigation(),
			'atts'                => $atts,
			// Columns: missing means today's (date and method shown, title not a link).
			'show_date'           => self::flag_is_on($atts['show_date'] ?? '1'),
			'show_payment_method' => self::flag_is_on($atts['show_payment_method'] ?? '1'),
			'show_booking_link'   => self::flag_is_on($atts['show_booking_link'] ?? '0'),
			'show_pagination'     => $paginate,
		);

		return array( 'data' => $data );
	}

	/**
	 * The payment history for an editor preview: the shape
	 * get_payment_history_data() returns, with PreviewSamples::payment_rows()
	 * as the payments and no booking queried. One page, so no pager.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return array{data: array<string,mixed>}
	 */
	public static function get_payment_history_preview_data(array $atts = array()): array
	{
		$payments = \MHMRentiva\Frontend\PreviewSamples::payment_rows();

		$data = array(
			'user'                => wp_get_current_user(),
			'payments'            => $payments,
			'total'               => count($payments),
			'total_pages'         => 1,
			'current_page'        => 1,
			'navigation'          => ( isset($atts['hide_nav']) && $atts['hide_nav'] ) ? array() : self::get_navigation(),
			'atts'                => $atts,
			'show_date'           => self::flag_is_on($atts['show_date'] ?? '1'),
			'show_payment_method' => self::flag_is_on($atts['show_payment_method'] ?? '1'),
			'show_booking_link'   => self::flag_is_on($atts['show_booking_link'] ?? '0'),
			'show_pagination'     => false,
			// Sample rows: no link to a booking, no receipt upload.
			'is_sample'           => true,
		);

		return array( 'data' => $data );
	}

	// Account details rendering stays with WooCommerce My Account.

	/**
	 * Messages render
	 */
	public static function render_messages(array $atts = array()): string
	{
		$data_res = self::get_messages_data($atts);
		if (isset($data_res['error'])) {
			return $data_res['error'];
		}
		return Templates::render('account/messages', $data_res, true);
	}

	/**
	 * Get messages data
	 */
	public static function get_messages_data(array $atts = array()): array
	{
		// Messaging is provided by the add-on. When it is absent this renders NOTHING at all:
		// no notice, no placeholder, no mention of the add-on --
		// a feature Lite does not have simply does not render). The empty 'error'
		// keeps the caller's existing short-circuit, which is shared with the
		// booking-detail path and must stay intact. The decision now comes from
		// the same `mhmrentiva_account_nav_items` mechanism that decides whether
		// the WC My Account "Messages" tab itself registers (extension
		// point) -- Lite no longer names the deleted mode-routing class's messages gate.
		$nav_items = WooCommerceIntegration::get_account_nav_items();
		if ( ! isset( $nav_items['messages'] ) ) {
			return array( 'error' => '' );
		}

		$user          = wp_get_current_user();
		$customer_name = $user->display_name;
		if (! $customer_name) {
			$customer_name = $user->user_login;
		}

		// The customer-messages stylesheet + account-messages script enqueue/localize
		// used to live here directly. Both assets belong to the add-on now
		// (messaging is a feature of the add-on) -- Lite no longer knows their
		// filenames, handles, or Lite-hosted paths. The add-on performs the actual
		// enqueue itself, at the exact point this render happens.
		do_action('mhmrentiva_account_messages_render_assets', $user, $customer_name);

		$data = array(
			'user'           => $user,
			'customer_email' => $user->user_email,
			'customer_name'  => $customer_name,
			'navigation'     => ( isset($atts['hide_nav']) && $atts['hide_nav'] ) ? array() : self::get_navigation(),
		);

		return array( 'data' => $data );
	}



	/**
	 * Booking detail render
	 */
	/**
	 * Booking detail render
	 */
	public static function render_booking_detail(int $booking_id, bool $hide_nav = false): string
	{
		$data_res = self::get_booking_detail_data($booking_id, $hide_nav);
		if (isset($data_res['error'])) {
			return $data_res['error'];
		}
		// Vendor sees a dedicated detail view (mirrors the vendor email)
		if (! empty($data_res['data']['is_vendor_view'])) {
			return Templates::render('account/vendor-booking-detail', $data_res, true);
		}
		return Templates::render('account/booking-detail', $data_res, true);
	}

	/**
	 * Get booking detail data
	 */
	public static function get_booking_detail_data(int $booking_id, bool $hide_nav = false): array
	{
		$booking = get_post($booking_id);

		if (! $booking || $booking->post_type !== 'mhmrentiva_booking') {
			return array( 'error' => '<p>' . __('Booking not found.', 'mhm-rentiva') . '</p>' );
		}

		// User check — customer, vendor (vehicle owner), or admin may view
		$user            = wp_get_current_user();
		$booking_user_id = (int) get_post_meta($booking_id, '_mhmrentiva_customer_user_id', true);
		$vehicle_id      = (int) get_post_meta($booking_id, '_mhmrentiva_vehicle_id', true);
		$vendor_user_id  = $vehicle_id > 0 ? (int) get_post_field('post_author', $vehicle_id) : 0;

		$is_customer = ( $booking_user_id > 0 && $booking_user_id === (int) $user->ID );
		$is_vendor   = ( $vendor_user_id > 0 && $vendor_user_id === (int) $user->ID );
		$is_admin    = current_user_can('manage_options');

		if (! $is_customer && ! $is_vendor && ! $is_admin) {
			return array( 'error' => '<p>' . __('You do not have permission to view this booking.', 'mhm-rentiva') . '</p>' );
		}

		$navigation = $hide_nav ? array() : self::get_navigation();

		// If navigation is empty (e.g. WooCommerce integration), provide minimal nav for breadcrumbs
		if (empty($navigation)) {
			$dashboard_url = home_url('/');
			$bookings_url  = home_url('/');

			if (class_exists('WooCommerce') && function_exists('wc_get_endpoint_url')) {
				$my_account_url = wc_get_page_permalink('myaccount');
				$dashboard_url  = $my_account_url;
				$bookings_slug  = AccountController::get_endpoint_slug('bookings', 'rentiva-bookings');
				$bookings_url   = wc_get_endpoint_url($bookings_slug, '', $my_account_url);
			} else {
				$dashboard_url = AccountController::get_account_url();
				$bookings_url  = AccountController::get_booking_view_url($booking_id);
			}

			$navigation = array(
				'dashboard' => array( 'url' => $dashboard_url ),
				'bookings'  => array( 'url' => $bookings_url ),
			);
		}

		$data = array(
			'booking'        => $booking,
			'booking_id'     => $booking_id,
			'navigation'     => $navigation,
			'is_integrated'  => $hide_nav,
			'is_vendor_view' => ( $is_vendor && ! $is_customer ),
			'is_admin_view'  => $is_admin,
		);

		return array( 'data' => $data );
	}

	/**
	 * Get navigation menu
	 * Returns empty array if WooCommerce My Account page is active (to avoid duplicate navigation)
	 */
	private static function get_navigation(): array
	{
		// ⭐ Don't show custom navigation if WooCommerce is active
		// WooCommerce provides its own navigation which is preferred
		if (class_exists('WooCommerce')) {
			return array();
		}

		// Main account page URL
		$base_url = AccountController::get_account_url();

		return array(
			'dashboard'       => array(
				'title' => __('Dashboard', 'mhm-rentiva'),
				'url'   => $base_url,
				'icon'  => '📊',
			),
			'bookings'        => array(
				'title' => __('My Bookings', 'mhm-rentiva'),
				'url'   => add_query_arg('endpoint', 'bookings', $base_url),
				'icon'  => '📅',
			),
			'favorites'       => array(
				'title' => __('Favorite Vehicles', 'mhm-rentiva'),
				'url'   => add_query_arg('endpoint', 'favorites', $base_url),
				'icon'  => '❤️',
			),
			'payment-history' => array(
				'title' => __('Payment history', 'mhm-rentiva'),
				'url'   => add_query_arg('endpoint', 'payment-history', $base_url),
				'icon'  => '💳',
			),
			'messages'        => array(
				'title' => __('Messages', 'mhm-rentiva'),
				'url'   => add_query_arg('endpoint', 'messages', $base_url),
				'icon'  => '💬',
			),
			'edit-account'    => array(
				'title' => __('Account Details', 'mhm-rentiva'),
				'url'   => add_query_arg('endpoint', 'edit-account', $base_url),
				'icon'  => '⚙️',
			),
			'logout'          => array(
				'title' => __('Logout', 'mhm-rentiva'),
				'url'   => wp_logout_url($base_url),
				'icon'  => '🚪',
			),
		);
	}

	/**
	 * Check if current page is WooCommerce My Account page
	 *
	 * @return bool True if WooCommerce My Account page is active
	 */
	private static function is_woocommerce_account_page(): bool
	{
		if (! class_exists('WooCommerce') || ! function_exists('wc_get_page_id')) {
			return false;
		}

		$account_page_id = wc_get_page_id('myaccount');
		if (! $account_page_id || $account_page_id <= 0) {
			return false;
		}

		// Check if we're on the WooCommerce My Account page
		return is_page($account_page_id) || is_account_page();
	}

	/**
	 * Get user bookings
	 */
	private static function get_user_bookings(int $user_id, array $args = array()): array
	{
		return self::query_user_bookings($user_id, $args)->posts;
	}

	/**
	 * The query behind get_user_bookings(), for callers that page through it
	 * (`page`, 1-based; max_num_pages on the result). A thin wrapper: the one
	 * bookings query path is AccountBookingsQuery (r3 y2); `segment` narrows to
	 * a section of the list.
	 */
	private static function query_user_bookings(int $user_id, array $args = array()): \WP_Query
	{
		return AccountBookingsQuery::query($user_id, $args);
	}

	/**
	 * Get user payments
	 */
	private static function get_user_payments(int $user_id, int $per_page = 10, int $page = 1, array $args = array()): array
	{
		$args = wp_parse_args(
			$args,
			array(
				'orderby' => 'date',
				'order'   => 'desc',
				'status'  => '',
			)
		);

		// Get all user bookings
		$bookings = self::get_user_bookings($user_id, array( 'limit' => -1 ));

		$payments = array();

		foreach ($bookings as $booking) {
			$payment_status  = get_post_meta($booking->ID, '_mhmrentiva_payment_status', true);
			$payment_method  = get_post_meta($booking->ID, '_mhmrentiva_payment_method', true);
			$payment_gateway = get_post_meta($booking->ID, '_mhmrentiva_payment_gateway', true);
			$total_raw       = get_post_meta($booking->ID, '_mhmrentiva_total_price', true);
			$deposit_raw     = get_post_meta($booking->ID, '_mhmrentiva_deposit_amount', true);
			$total_price     = (float) $total_raw;
			$deposit_amount  = (float) $deposit_raw;
			$payment_type    = get_post_meta($booking->ID, '_mhmrentiva_payment_type', true);
			// No amount meta at all (not a zero amount): sorted last by amount.
			$has_amount = '' !== ( $payment_type === 'deposit' ? $deposit_raw : $total_raw );

			// Normalize status values from various sources
			$status_key = strtolower( (string) $payment_status);
			$status_key = str_replace(' ', '_', $status_key);
			switch ($status_key) {
				case 'pending_verification':
				case 'verification_pending':
				case 'processing':
				case 'awaiting_payment':
					$payment_status = 'pending';
					break;
				case 'completed':
				case 'paid':
				case 'succeeded':
					$payment_status = 'completed';
					break;
				case 'cancelled':
				case 'canceled':
				case 'refunded':
				case 'failed':
					$payment_status = 'cancelled';
					break;
				default:
					// Fallback to original or pending when unknown
					$payment_status = ! empty($status_key) ? $status_key : 'pending';
			}

			// The truth about the row (R13, PR D). What the detail and the card call
			// refunded, or a stored cancelled / refunded / failed (a terminal state
			// written on purpose), is cancelled and is not overruled by the money
			// once received. A partial refund on a live rental stays completed. Otherwise money
			// received (PaymentState, through AccountPaymentSummary) is completed,
			// whatever the stored status still says; the stored status speaks
			// only when no money is on record.
			$booking_status = \MHMRentiva\Admin\Booking\Core\Status::get($booking->ID);
			$payment_state  = \MHMRentiva\Admin\Payment\Core\PaymentState::forBooking( (int) $booking->ID);
			$summary        = AccountPaymentSummary::for_booking($booking->ID, $booking_status, $payment_state);
			if (AccountPaymentSummary::REFUNDED === $summary['kind'] || 'cancelled' === $payment_status) {
				$payment_status = 'cancelled';
			} elseif ($summary['money_received']) {
				$payment_status = 'completed';
			} elseif (in_array($booking_status, array( \MHMRentiva\Admin\Booking\Core\Status::CANCELLED, \MHMRentiva\Admin\Booking\Core\Status::REFUNDED ), true)) {
				$payment_status = 'cancelled';
			}

			// Fallback gateway if empty (manual/offline)
			if (empty($payment_gateway)) {
				$payment_gateway = ! empty($payment_method) ? $payment_method : 'woocommerce';
			}

			// The row's date: when the money arrived, else when the booking was made.
			$row_ts = AccountPaymentSummary::row_date_ts( (int) $booking->ID, $summary['order_ids']);

			if ($payment_status) {
				// Translate Status
				$status_labels = array(
					'pending'   => __('Pending', 'mhm-rentiva'),
					'completed' => __('Completed', 'mhm-rentiva'),
					'cancelled' => __('Cancelled', 'mhm-rentiva'),
				);
				$status_label  = $status_labels[ $payment_status ] ?? ucfirst($payment_status);
				// A refunded row keeps the cancelled colour but says what happened.
				if (AccountPaymentSummary::REFUNDED === $summary['kind'] || \MHMRentiva\Admin\Booking\Core\Status::REFUNDED === $booking_status || 'refunded' === $status_key) {
					$status_label = \MHMRentiva\Admin\Booking\Core\Status::get_label(\MHMRentiva\Admin\Booking\Core\Status::REFUNDED);
				}

				// Format Gateway/Method
				$woocommerce_method_title = '';

				// Try to get method title from WooCommerce Order if exists
				$wc_order_id = \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::resolve_wc_order_id( (int) $booking->ID);
				if ($wc_order_id && function_exists('wc_get_order')) {
					$order = wc_get_order($wc_order_id);
					if ($order instanceof \WC_Order && method_exists($order, 'get_payment_method_title')) {
						$method                   = 'get_payment_method_title';
						$woocommerce_method_title = $order->$method();
					}
				}

				// If we found a real title, use it. Otherwise fallback to existing method or gateway
				if (! empty($woocommerce_method_title)) {
					$method_display = $woocommerce_method_title;
				} elseif (! empty($payment_method) && $payment_method !== 'manual') {
					$method_display = ucfirst($payment_method);
				} else {
					// Fallback to gateway name if method is empty
					$method_display = $payment_gateway === 'woocommerce' ? 'WooCommerce' : ucfirst($payment_gateway);
				}

				$receipt_attachment_id = (int) get_post_meta($booking->ID, '_mhmrentiva_receipt_attachment_id', true);

				// The title is the car, the number the one every screen shows (R4).
				$vehicle_post  = get_post( (int) get_post_meta($booking->ID, '_mhmrentiva_vehicle_id', true));
				$vehicle_title = $vehicle_post instanceof \WP_Post && '' !== $vehicle_post->post_title ? $vehicle_post->post_title : __('N/A', 'mhm-rentiva');

				// Money that moved is shown as recorded (gross, its own currency);
				// a row nobody paid shows what is expected.
				$expected = $payment_type === 'deposit' ? $deposit_amount : $total_price;
				if ($summary['paid_minor'] > 0) {
					$amount_html = BookingTimeline::recorded_html($summary['paid_minor'], $summary['currency']);
				} elseif (array() !== $summary['order_ids']) {
					// Orders in different currencies: no single sum exists, so each
					// order shows as recorded (never an expected figure).
					$amount_html = BookingTimeline::recorded_orders_html($summary['order_ids']);
				} elseif ($has_amount) {
					$amount_html = \MHMRentiva\Admin\Booking\Pricing\PriceDisplay::for_cart($expected)['html'];
				} else {
					$amount_html = '';
				}
				$row_type = AccountPaymentSummary::DEPOSIT === $summary['kind']
					? 'deposit'
					: ( AccountPaymentSummary::PAID === $summary['kind'] ? 'full' : (string) $payment_type );

				$payments[] = array(
					'booking_id'         => $booking->ID,
					'number'             => (string) mhmrentiva_get_display_id( (int) $booking->ID),
					'booking_title'      => $vehicle_title,
					'amount_html'        => $amount_html,
					'can_upload_receipt' => AccountController::receipt_upload_allowed( (int) $booking->ID, $payment_state),
					'date'               => $row_ts > 0 ? BookingTimeline::moment_text($row_ts) : '',
					'sort_ts'            => $row_ts,
					'status'             => $payment_status,
					'status_label'       => $status_label,
					'method'             => $method_display,
					'amount'             => $payment_type === 'deposit' ? $deposit_amount : $total_price,
					'has_amount'         => $has_amount,
					'total'              => $total_price,
					'type'               => $row_type,
					'receipt'            => array(
						'attachment_id' => $receipt_attachment_id,
						'status'        => get_post_meta($booking->ID, '_mhmrentiva_receipt_status', true),
						'status_label'  => \MHMRentiva\Admin\Booking\Meta\BookingMeta::receipt_status_label( (string) get_post_meta($booking->ID, '_mhmrentiva_receipt_status', true)),
						// Guarded endpoint: a `private` attachment still sits at a
						// publicly served uploads path, so the customer is never
						// handed the raw file URL.
						'url'           => $receipt_attachment_id > 0
							? \MHMRentiva\Admin\Frontend\Account\AccountController::get_receipt_url( (int) $booking->ID)
							: '',
					),
				);
			}
		}

		// The status filter runs before the sort and the page cut, so the rows,
		// the total and the page count all follow it.
		if ('' !== $args['status']) {
			$payments = array_values(
				array_filter(
					$payments,
					static function ($payment) use ($args) {
						return $payment['status'] === $args['status'];
					}
				)
			);
		}

		// Latest payments first by default. A payment without a date (by date) or
		// without an amount (by amount) goes last in both orders; booking ID on a
		// tie, so a row is on exactly one page.
		$by_amount = 'amount' === $args['orderby'];
		$sign      = 'asc' === $args['order'] ? 1 : -1;
		usort(
			$payments,
			static function ($a, $b) use ($by_amount, $sign) {
				if ($by_amount) {
					if (! $a['has_amount'] || ! $b['has_amount']) {
						return ( ! $a['has_amount'] ) <=> ( ! $b['has_amount'] );
					}
					return $sign * ( array( $a['amount'], $a['booking_id'] ) <=> array( $b['amount'], $b['booking_id'] ) );
				}
				if (0 === $a['sort_ts'] || 0 === $b['sort_ts']) {
					return ( 0 === $a['sort_ts'] ) <=> ( 0 === $b['sort_ts'] );
				}
				return $sign * ( array( $a['sort_ts'], $a['booking_id'] ) <=> array( $b['sort_ts'], $b['booking_id'] ) );
			}
		);

		$total       = count($payments);
		$total_pages = max(1, (int) ceil($total / max(1, $per_page)));
		// Past the last page (Review Focus 2) shows the last page.
		$page   = min(max(1, $page), $total_pages);
		$offset = ( $page - 1 ) * $per_page;

		return array(
			'items'        => array_slice($payments, $offset, $per_page),
			'total'        => $total,
			'total_pages'  => $total_pages,
			'current_page' => $page,
			'per_page'     => $per_page,
		);
	}
}
