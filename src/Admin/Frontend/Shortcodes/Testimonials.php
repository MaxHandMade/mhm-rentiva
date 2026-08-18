<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Testimonials Shortcode
 *
 * Shows customer reviews and ratings
 *
 * Usage: [rentiva_testimonials limit="5" rating="4" vehicle_id="123" show_rating="1" show_date="1"]
 */


// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Testimonials shortcode intentionally runs bounded review/rating queries.




if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;

/**
 * Testimonials Shortcode
 *
 * Shows customer reviews and ratings
 *
 * Usage: [rentiva_testimonials limit="5" rating="4" vehicle_id="123" show_rating="1" show_date="1"]
 */
final class Testimonials extends AbstractShortcode {



	public const SHORTCODE = 'rentiva_testimonials';

	/**
	 * Safe sanitize text field that handles null values
	 *
	 * @param mixed $value Input value
	 * @return string
	 */
	public static function sanitize_text_field_safe($value): string
	{
		if ($value === null || $value === '') {
			return '';
		}
		return sanitize_text_field( (string) $value);
	}

	public static function register(): void
	{
		parent::register();

		add_action('wp_ajax_mhmrentiva_load_testimonials', array( self::class, 'ajax_load_testimonials' ));
		add_action('wp_ajax_nopriv_mhmrentiva_load_testimonials', array( self::class, 'ajax_load_testimonials' ));
	}

	protected static function get_shortcode_tag(): string
	{
		return self::SHORTCODE;
	}

	protected static function get_template_path(): string
	{
		return 'shortcodes/testimonials';
	}

	protected static function get_default_attributes(): array
	{
		return array(
			'limit'         => apply_filters('mhmrentiva_testimonials_limit', '5'),
			'rating'        => apply_filters('mhmrentiva_testimonials_rating', ''),
			'vehicle_id'    => apply_filters('mhmrentiva_testimonials_vehicle_id', ''),
			'orderby'       => apply_filters('mhmrentiva_testimonials_orderby', 'date'),
			'order'         => apply_filters('mhmrentiva_testimonials_order', 'DESC'),
			'show_rating'   => apply_filters('mhmrentiva_testimonials_show_rating', '1'),
			'show_date'     => apply_filters('mhmrentiva_testimonials_show_date', '1'),
			'show_vehicle'  => apply_filters('mhmrentiva_testimonials_show_vehicle', '1'),
			'show_customer' => apply_filters('mhmrentiva_testimonials_show_customer', '1'),
			'layout'        => apply_filters('mhmrentiva_testimonials_layout', 'grid'),
			'columns'       => apply_filters('mhmrentiva_testimonials_columns', '3'),
			'auto_rotate'   => apply_filters('mhmrentiva_testimonials_auto_rotate', '0'),
			'class'         => apply_filters('mhmrentiva_testimonials_class', ''),
		);
	}

	protected static function get_css_filename(): string
	{
		return 'testimonials.css';
	}

	protected static function get_js_filename(): string
	{
		return 'testimonials.js';
	}

	/**
	 * Load asset files
	 */
	protected static function enqueue_assets(array $atts = array()): void
	{
		// CSS
		wp_enqueue_style(
			'mhm-rentiva-testimonials',
			MHMRENTIVA_PLUGIN_URL . 'assets/css/frontend/testimonials.css',
			array(),
			MHMRENTIVA_VERSION
		);

		// JavaScript
		wp_enqueue_script(
			'mhm-rentiva-testimonials',
			MHMRENTIVA_PLUGIN_URL . 'assets/js/frontend/testimonials.js',
			array( 'jquery' ),
			MHMRENTIVA_VERSION,
			true
		);

		// Localize script
		self::localize_script('mhm-rentiva-testimonials');
	}

	protected static function get_script_object_name(): string
	{
		return 'mhmRentivaTestimonials';
	}

	protected static function get_localized_data(): array
	{
		$data = parent::get_localized_data();

		/*
		 * The endpoint verifies `mhmrentiva_testimonials_nonce`. The parent
		 * mints its token from the shortcode tag, which yields
		 * `mhmrentiva_rentiva_testimonials_nonce` -- the two names never met,
		 * so "Load More" failed closed for every visitor. Every sibling that
		 * checks a nonce of its own overrides it here the same way
		 * (AvailabilityCalendar, VehicleDetails, BookingForm, ...).
		 */
		$data['nonce'] = wp_create_nonce('mhmrentiva_testimonials_nonce');

		$data['icons'] = array(
			'star'     => \MHMRentiva\Helpers\Icons::get('star', array( 'class' => 'rv-icon-star' )),
			'car'      => \MHMRentiva\Helpers\Icons::get('car', array( 'class' => 'rv-icon-car' )),
			'calendar' => \MHMRentiva\Helpers\Icons::get('calendar', array( 'class' => 'rv-icon-calendar' )),
		);
		return $data;
	}

	protected static function get_localized_strings(): array
	{
		return array(
			'loading'        => __('Loading...', 'mhm-rentiva'),
			'error'          => __('An error occurred', 'mhm-rentiva'),
			'noTestimonials' => __('No testimonials found yet', 'mhm-rentiva'),
			'loadMore'       => __('Load More Reviews', 'mhm-rentiva'),
		);
	}

	public static function render(array $atts = array(), ?string $content = null): string
	{
		$defaults = self::get_default_attributes();
		$atts     = shortcode_atts($defaults, $atts, self::SHORTCODE);

		// Load CSS manually
		self::enqueue_assets($atts);

		// Prepare template data
		$data = self::prepare_template_data($atts);

		// Render template
		return Templates::render(self::get_template_path(), $data, true);
	}

	protected static function prepare_template_data(array $atts): array
	{
		$testimonials = self::get_testimonials($atts);

		return array(
			'atts'             => $atts,
			'testimonials'     => $testimonials,
			'total_count'      => self::get_testimonials_count($atts),
			'has_testimonials' => ! empty($testimonials),
		);
	}

	/**
	 * Ceiling for a single testimonials read.
	 *
	 * Matches the maximum the nopriv `mhmrentiva_load_testimonials` endpoint
	 * already clamps `limit` to, so the bound is the one the feature advertises.
	 */
	private const MAX_ROWS = 50;

	private static function get_testimonials(array $atts): array
	{
		$limit   = (int) ( $atts['limit'] ?? 5 );
		$orderby = self::sanitize_orderby( (string) ( $atts['orderby'] ?? 'date' ));
		$order   = self::sanitize_order( (string) ( $atts['order'] ?? 'DESC' ));

		// How many rows each source may return. Both sources are sorted newest
		// first, so for a date ordering the newest $limit of each is guaranteed
		// to contain the newest $limit of the merge. A random ordering needs a
		// pool to draw from, and an unlimited `limit` still needs a ceiling --
		// both use the same maximum the testimonials endpoint already enforces.
		$fetch_limit = ( $limit > 0 && 'rand' !== $orderby ) ? $limit : self::MAX_ROWS;

		// Source 1: Booking post meta reviews
		$testimonials = self::get_booking_reviews($atts, $fetch_limit);

		// Source 2: Approved WordPress comments on vehicle posts
		$testimonials = array_merge($testimonials, self::get_vehicle_comments($atts, $fetch_limit));

		if ('rand' === $orderby) {
			shuffle($testimonials);
		} else {
			usort($testimonials, static function (array $a, array $b) use ($order): int {
				$cmp = strcmp( (string) ( $a['date'] ?? '' ), (string) ( $b['date'] ?? '' ));
				return 'ASC' === $order ? $cmp : -$cmp;
			});
		}

		// Apply limit on merged set
		if ($limit > 0) {
			$testimonials = \array_slice($testimonials, 0, $limit);
		}

		return $testimonials;
	}

	/**
	 * Get reviews stored as vehicle_booking post meta.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_booking_reviews(array $atts, int $fetch_limit = self::MAX_ROWS): array
	{
		$args = array(
			'post_type'      => 'mhmrentiva_booking',
			'post_status'    => 'publish',
			// Bounded: this runs on a public page and the caller slices to the
			// displayed count anyway.
			'posts_per_page' => max(1, min($fetch_limit, self::MAX_ROWS)),
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => '_mhmrentiva_customer_review',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => '_mhmrentiva_review_approved',
					'value'   => '1',
					'compare' => '=',
				),
			),
		);

		if (! empty($atts['rating'])) {
			$args['meta_query'][] = array(
				'key'     => '_mhmrentiva_customer_rating',
				'value'   => (int) $atts['rating'],
				'compare' => '>=',
			);
		}

		if (! empty($atts['vehicle_id'])) {
			$args['meta_query'][] = array(
				'key'     => '_mhmrentiva_vehicle_id',
				'value'   => (int) $atts['vehicle_id'],
				'compare' => '=',
			);
		}

		$bookings     = get_posts($args);
		$testimonials = array();

		foreach ($bookings as $booking) {
			$vid            = (int) get_post_meta($booking->ID, '_mhmrentiva_vehicle_id', true);
			$testimonials[] = array(
				'id'            => $booking->ID,
				'review'        => get_post_meta($booking->ID, '_mhmrentiva_customer_review', true),
				'rating'        => (int) get_post_meta($booking->ID, '_mhmrentiva_customer_rating', true),
				'customer_name' => get_post_meta($booking->ID, '_mhmrentiva_customer_name', true),
				// No e-mail address here. These rows are returned verbatim by the
				// nopriv mhmrentiva_load_testimonials endpoint, and nothing in the
				// template or the script ever read this field -- it existed only
				// on the wire.
				'date'          => $booking->post_date,
				'vehicle_id'    => $vid,
				'vehicle_name'  => self::get_vehicle_name($vid),
			);
		}

		return $testimonials;
	}

	/**
	 * Get approved WordPress comments on vehicle posts.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_vehicle_comments(array $atts, int $fetch_limit = self::MAX_ROWS): array
	{
		$comment_args = array(
			'post_type'   => 'mhmrentiva_vehicle',
			// A comment query does not inherit the post's visibility: without
			// this, reviews of a draft or private vehicle are listed on a public
			// page. WP_Comment_Query applies it to the parent post.
			'post_status' => 'publish',
			'status'      => 'approve',
			// Bounded for the same reason as the booking read above.
			'number'      => max(1, min($fetch_limit, self::MAX_ROWS)),
			'orderby'     => 'comment_date',
			'order'       => 'DESC',
		);

		if (! empty($atts['vehicle_id'])) {
			$comment_args['post_id'] = (int) $atts['vehicle_id'];
		}

		if (! empty($atts['rating'])) {
			$comment_args['meta_query'] = array(
				array(
					'key'     => 'mhmrentiva_rating',
					'value'   => (int) $atts['rating'],
					'compare' => '>=',
					'type'    => 'NUMERIC',
				),
			);
		}

		$comments     = get_comments($comment_args);
		$testimonials = array();

		foreach ($comments as $comment) {
			$rating     = (int) get_comment_meta($comment->comment_ID, 'mhmrentiva_rating', true);
			$vehicle_id = (int) $comment->comment_post_ID;

			// Prefer comment_author; fallback to WP user display_name.
			$customer_name = $comment->comment_author;
			if (empty($customer_name) && $comment->user_id) {
				$user = get_userdata( (int) $comment->user_id);
				if ($user) {
					$customer_name = $user->display_name;
				}
			}

			$testimonials[] = array(
				'id'            => (int) $comment->comment_ID,
				'review'        => $comment->comment_content,
				'rating'        => $rating,
				'customer_name' => $customer_name ?: '',
				// See the booking builder above: this payload is public.
				'date'          => $comment->comment_date,
				'vehicle_id'    => $vehicle_id,
				'vehicle_name'  => self::get_vehicle_name($vehicle_id),
			);
		}

		return $testimonials;
	}

	/**
	 * Sanitize orderby value for testimonial queries.
	 */
	private static function sanitize_orderby(string $value): string
	{
		$allowed = array( 'date', 'title', 'rand', 'modified' );
		$value   = strtolower($value);
		return in_array($value, $allowed, true) ? $value : 'date';
	}

	/**
	 * Sanitize order value for testimonial queries.
	 */
	private static function sanitize_order(string $value): string
	{
		$value = strtoupper($value);
		return in_array($value, array( 'ASC', 'DESC' ), true) ? $value : 'DESC';
	}

	private static function get_testimonials_count(array $atts): int
	{
		// Count booking reviews
		$booking_args = array(
			'post_type'      => 'mhmrentiva_booking',
			'post_status'    => 'publish',
			// The total comes from found_posts, so one row is enough to run the
			// query; -1 pulled every matching ID into PHP to be discarded.
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => '_mhmrentiva_customer_review',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => '_mhmrentiva_review_approved',
					'value'   => '1',
					'compare' => '=',
				),
			),
		);

		if (! empty($atts['rating'])) {
			$booking_args['meta_query'][] = array(
				'key'     => '_mhmrentiva_customer_rating',
				'value'   => (int) $atts['rating'],
				'compare' => '>=',
			);
		}

		if (! empty($atts['vehicle_id'])) {
			$booking_args['meta_query'][] = array(
				'key'     => '_mhmrentiva_vehicle_id',
				'value'   => (int) $atts['vehicle_id'],
				'compare' => '=',
			);
		}

		$booking_query = new \WP_Query($booking_args);
		$booking_count = (int) $booking_query->found_posts;

		// Count vehicle comments
		$comment_args = array(
			'post_type'   => 'mhmrentiva_vehicle',
			// Same restriction as the read above, or the total would count rows
			// the list will not show and "load more" would promise them.
			'post_status' => 'publish',
			'status'      => 'approve',
			'count'       => true,
		);

		if (! empty($atts['vehicle_id'])) {
			$comment_args['post_id'] = (int) $atts['vehicle_id'];
		}

		if (! empty($atts['rating'])) {
			$comment_args['meta_query'] = array(
				array(
					'key'     => 'mhmrentiva_rating',
					'value'   => (int) $atts['rating'],
					'compare' => '>=',
					'type'    => 'NUMERIC',
				),
			);
		}

		$comment_count = (int) get_comments($comment_args);

		return $booking_count + $comment_count;
	}

	private static function get_vehicle_name(int $vehicle_id): string
	{
		if ($vehicle_id <= 0) {
			return '';
		}

		$vehicle = get_post($vehicle_id);

		// A vehicle taken off the site must not keep being named through its
		// reviews. This runs on a public page and the name is printed next to
		// every testimonial.
		if (! $vehicle || $vehicle->post_status !== 'publish') {
			return '';
		}

		return (string) $vehicle->post_title;
	}

	/**
	 * Load more testimonials via AJAX
	 *
	 * @return void
	 */
	public static function ajax_load_testimonials(): void
	{
		// Security check
		if (! check_ajax_referer('mhmrentiva_testimonials_nonce', 'nonce', false)) {
			wp_send_json_error(array( 'message' => __('Security check failed.', 'mhm-rentiva') ));
			return;
		}

		$page = isset($_POST['page']) ? absint(sanitize_text_field(wp_unslash( (string) $_POST['page']))) : 1;
		// Clamped to the 50 the testimonials widget declares: this endpoint is
		// nopriv and the value decides how many entries are rendered.
		$limit      = isset($_POST['limit']) ? min(self::MAX_ROWS, max(1, absint(sanitize_text_field(wp_unslash( (string) $_POST['limit']))))) : 5;
		$rating     = isset($_POST['rating']) ? sanitize_text_field(wp_unslash( (string) $_POST['rating'])) : '';
		$vehicle_id = isset($_POST['vehicle_id']) ? sanitize_text_field(wp_unslash( (string) $_POST['vehicle_id'])) : '';

		$atts = array(
			'limit'      => $limit,
			'rating'     => $rating,
			'vehicle_id' => $vehicle_id,
		);

		try {
			$testimonials = self::get_testimonials($atts);
			$total_count  = self::get_testimonials_count($atts);
		} catch (\Exception $e) {
			wp_send_json_error(array( 'message' => __('An error occurred while loading reviews.', 'mhm-rentiva') ));
		}

		// Outside the try: wp_send_json_* terminates through wp_die(), and a
		// catch(Exception) around it swallows that terminator and writes a
		// second, contradictory JSON document after the first.
		wp_send_json_success(
			array(
				'testimonials' => $testimonials,
				'total_count'  => $total_count,
				'has_more'     => ( $page * $limit ) < $total_count,
				'message'      => __('Reviews loaded successfully.', 'mhm-rentiva'),
			)
		);
	}
}
