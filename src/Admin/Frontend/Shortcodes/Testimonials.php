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
			'limit'              => apply_filters('mhmrentiva_testimonials_limit', '5'),
			'rating'             => apply_filters('mhmrentiva_testimonials_rating', ''),
			'vehicle_id'         => apply_filters('mhmrentiva_testimonials_vehicle_id', ''),
			'orderby'            => apply_filters('mhmrentiva_testimonials_orderby', 'date'),
			'order'              => apply_filters('mhmrentiva_testimonials_order', 'DESC'),
			'show_rating'        => apply_filters('mhmrentiva_testimonials_show_rating', '1'),
			'show_date'          => apply_filters('mhmrentiva_testimonials_show_date', '1'),
			'show_vehicle'       => apply_filters('mhmrentiva_testimonials_show_vehicle', '1'),
			'show_customer'      => apply_filters('mhmrentiva_testimonials_show_customer', '1'),
			'layout'             => apply_filters('mhmrentiva_testimonials_layout', 'grid'),
			'columns'            => apply_filters('mhmrentiva_testimonials_columns', '3'),
			'auto_rotate'        => apply_filters('mhmrentiva_testimonials_auto_rotate', '0'),
			'class'              => apply_filters('mhmrentiva_testimonials_class', ''),
			// The initial-letter avatar and the cards' quote icon (spec 3A Task 8);
			// both rendered unconditionally before, so both default on.
			'show_author_avatar' => '1',
			'show_quotes'        => '1',
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
			array( 'mhm-rentiva-css-variables' ),
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
		$data['nonce'] = \MHMRentiva\Frontend\RenderContext::script_nonce('mhmrentiva_testimonials_nonce');

		$data['icons'] = array(
			// The two icons of a card, as the template renders them; the cards
			// "Load More" adds use these.
			'card_quote' => \MHMRentiva\Helpers\Icons::get(
				'quote',
				array(
					'width'  => '32px',
					'height' => '32px',
					'class'  => 'rv-icon rv-icon-quote-deco',
				)
			),
			'card_star'  => \MHMRentiva\Helpers\Icons::get(
				'star',
				array(
					'width'  => '15px',
					'height' => '15px',
				)
			),
			'star'       => \MHMRentiva\Helpers\Icons::get('star', array( 'class' => 'rv-icon-star' )),
			'car'        => \MHMRentiva\Helpers\Icons::get('car', array( 'class' => 'rv-icon-car' )),
			'calendar'   => \MHMRentiva\Helpers\Icons::get('calendar', array( 'class' => 'rv-icon-calendar' )),
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
		// This class does not go through AbstractShortcode::render(), so it opens the
		// preview scope itself (a pass-through on the front end).
		return \MHMRentiva\Frontend\RenderContext::render_preview_scope(
			static fn (): string => self::render_in_scope($atts, $content)
		);
	}

	/**
	 * @param array<string,mixed> $atts    Shortcode attributes.
	 * @param string|null         $content Shortcode content.
	 */
	private static function render_in_scope(array $atts, ?string $content): string
	{
		$defaults = self::get_default_attributes();

		// Same contract as AbstractShortcode::render(): a `_canonical` payload (widgets,
		// blocks) is already mapped and must not be truncated by shortcode_atts(); it
		// only needs this shortcode's defaults for the keys it omits. Only boolean true
		// counts: shortcode text yields strings, so an author cannot forge the flag.
		if (true !== ( $atts['_canonical'] ?? null )) {
			unset($atts['_canonical']);
			$atts = shortcode_atts($defaults, $atts, self::SHORTCODE);
		} else {
			$atts = wp_parse_args($atts, $defaults);
		}

		// Load CSS manually
		self::enqueue_assets($atts);

		// Prepare template data
		$data = self::prepare_template_data($atts);

		// The internal guard is not part of the template data or the filter contract.
		unset($data['atts']['_canonical']);
		$filter_atts = $atts;
		unset($filter_atts['_canonical']);

		// Render template
		$html = Templates::render(self::get_template_path(), $data, true);

		// The public per-tag filter every AbstractShortcode::render() applies; this
		// override used to skip it.
		return (string) apply_filters('mhmrentiva_shortcodes_' . self::SHORTCODE . '_html', $html, $filter_atts, $content);
	}

	protected static function prepare_template_data(array $atts): array
	{
		// A random order is drawn from a seed, so "Load More" can ask for the
		// next page of the same draw instead of a new one.
		if ('rand' === self::sanitize_orderby( (string) ( $atts['orderby'] ?? 'date' ))) {
			$atts['seed'] = (string) wp_rand(1, 2147483647);
		}

		$testimonials = self::get_testimonials($atts);

		return array(
			'atts'             => $atts,
			'testimonials'     => $testimonials,
			// What can be served, not every review: "Load More" is offered only
			// while there is more of the pool to show.
			'total_count'      => self::servable_count(self::get_testimonials_count($atts)),
			'has_testimonials' => ! empty($testimonials),
			'load_more'        => self::load_more_attributes($atts),
		);
	}

	/**
	 * What "Load More" needs to ask for the next page of this list: the order,
	 * the filters and the display flags of the cards it renders. Only values
	 * that differ from the defaults are listed (the script falls back to the
	 * defaults), so a default render keeps its markup.
	 *
	 * @param array<string,mixed> $atts Resolved attributes.
	 * @return array<string,string> data-attribute name (without "data-") => value
	 */
	public static function load_more_attributes(array $atts): array
	{
		$out = array();

		$orderby = self::sanitize_orderby( (string) ( $atts['orderby'] ?? 'date' ));
		if ('date' !== $orderby) {
			$out['orderby'] = $orderby;
		}
		if ('ASC' === self::sanitize_order( (string) ( $atts['order'] ?? 'DESC' ))) {
			$out['order'] = 'ASC';
		}
		if ('rand' === $orderby && ! empty($atts['seed'])) {
			$out['seed'] = (string) absint($atts['seed']);
		}
		if (! empty($atts['rating']) && (int) $atts['rating'] > 0) {
			$out['rating'] = (string) (int) $atts['rating'];
		}
		if (! empty($atts['vehicle_id']) && (int) $atts['vehicle_id'] > 0) {
			$out['vehicle-id'] = (string) (int) $atts['vehicle_id'];
		}
		foreach (array( 'show_rating', 'show_date', 'show_vehicle', 'show_customer', 'show_author_avatar', 'show_quotes' ) as $flag) {
			if ('1' !== (string) ( $atts[ $flag ] ?? '1' )) {
				$out[ str_replace('_', '-', $flag) ] = '0';
			}
		}

		return $out;
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

		// How far into the merged list this read starts. The shortcode never
		// sets it; "Load More" does, and without it every click re-fetched the
		// first page and appended the reviews already on screen.
		$offset = max(0, (int) ( $atts['offset'] ?? 0 ));

		// How many rows each source may return. Each source is read in the
		// requested order (date either way, or rating with unrated last), so the
		// first N of each are guaranteed to contain the first N of the merge. A
		// random order draws from a fixed pool (the newest of each source) that
		// must be the same on every page, and an unlimited `limit` still needs a
		// ceiling -- both use the same maximum the testimonials endpoint already
		// enforces. A paged read has to fetch past its own start, or the slice
		// below has nothing left to take.
		$fetch_limit = ( $limit > 0 && 'rand' !== $orderby )
			? min( self::MAX_ROWS, $offset + $limit )
			: self::MAX_ROWS;

		// Source 1: Booking post meta reviews
		$testimonials = self::get_booking_reviews($atts, $fetch_limit, $orderby, $order);

		// Source 2: Approved WordPress comments on vehicle posts
		$testimonials = array_merge($testimonials, self::get_vehicle_comments($atts, $fetch_limit, $orderby, $order));

		if ('rand' === $orderby) {
			// A seeded draw: the same seed gives the same order, so a later page
			// continues it. Without a seed (no caller passes none today) one is
			// drawn here.
			$seed = isset($atts['seed']) && absint($atts['seed']) > 0 ? (string) absint($atts['seed']) : (string) wp_rand(1, 2147483647);
			usort($testimonials, static function (array $a, array $b) use ($seed): int {
				return strcmp(
					md5($seed . '|' . ( $a['id'] ?? '' ) . '|' . ( $a['date'] ?? '' )),
					md5($seed . '|' . ( $b['id'] ?? '' ) . '|' . ( $b['date'] ?? '' ))
				);
			});
		} elseif ('rating' === $orderby) {
			// Highest first for DESC, lowest first for ASC. A review without a
			// rating (0) goes last in both orders; ties keep the newest first.
			usort($testimonials, static function (array $a, array $b) use ($order): int {
				$ra = (int) ( $a['rating'] ?? 0 );
				$rb = (int) ( $b['rating'] ?? 0 );
				if (( $ra > 0 ) !== ( $rb > 0 )) {
					return $ra > 0 ? -1 : 1;
				}
				$cmp = 'ASC' === $order ? $ra <=> $rb : $rb <=> $ra;
				return 0 !== $cmp ? $cmp : strcmp( (string) ( $b['date'] ?? '' ), (string) ( $a['date'] ?? '' ));
			});
		} else {
			usort($testimonials, static function (array $a, array $b) use ($order): int {
				$cmp = strcmp( (string) ( $a['date'] ?? '' ), (string) ( $b['date'] ?? '' ));
				return 'ASC' === $order ? $cmp : -$cmp;
			});
		}

		// A later page never reads past the pool: only its first MAX_ROWS rows
		// are known to be the right ones (servable_count()).
		if ($offset > 0) {
			$testimonials = \array_slice($testimonials, 0, self::MAX_ROWS);
		}

		// Apply offset and limit on the merged set
		$testimonials = \array_slice($testimonials, $offset, $limit > 0 ? $limit : null);

		return $testimonials;
	}

	/**
	 * How many of $total reviews the list can serve: each source is read up to
	 * MAX_ROWS rows, so only the first MAX_ROWS of the merged order are known
	 * to be right. "Load More" stops there instead of promising pages it would
	 * serve empty (or out of order).
	 */
	private static function servable_count(int $total): int
	{
		return min($total, self::MAX_ROWS);
	}

	/**
	 * Read one source in the list's order (spec 3A Task 10a item 7).
	 *
	 * Date: by date in the requested direction. Rating: the rated rows (a
	 * rating of 1 or more) by rating in the requested direction, newest first
	 * on a tie -- topped up with the unrated rows, newest first, only when
	 * fewer than $fetch_limit rated rows came back, so unrated go last in both
	 * directions, as the merge sorts them. Random: newest first (the pool).
	 * Every orderby value is a constant; $order is already allowlisted.
	 *
	 * @param callable $read       fn(?array $rating_clause, array $sort, int $rows): list<row>.
	 * @param string   $date_key   The source's date orderby key.
	 * @param string   $rating_key The source's rating meta key.
	 * @return array<int, array<string, mixed>>
	 */
	private static function read_in_order(callable $read, int $fetch_limit, string $orderby, string $order, string $date_key, string $rating_key): array
	{
		if ('rating' !== $orderby) {
			return $read(null, array( $date_key => 'date' === $orderby ? $order : 'DESC' ), $fetch_limit);
		}

		$rated = $read(
			array(
				'rating_sort' => array(
					'key'     => $rating_key,
					'value'   => 1,
					'compare' => '>=',
					'type'    => 'NUMERIC',
				),
			),
			array(
				'rating_sort' => $order,
				$date_key     => 'DESC',
			),
			$fetch_limit
		);
		if (count($rated) >= $fetch_limit) {
			return $rated;
		}

		$unrated = $read(
			array(
				array(
					'relation' => 'OR',
					array(
						'key'     => $rating_key,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => $rating_key,
						'value'   => 1,
						'compare' => '<',
						'type'    => 'NUMERIC',
					),
				),
			),
			array( $date_key => 'DESC' ),
			$fetch_limit - count($rated)
		);

		return array_merge($rated, $unrated);
	}

	/**
	 * Get reviews stored as vehicle_booking post meta.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_booking_reviews(array $atts, int $fetch_limit = self::MAX_ROWS, string $orderby = 'date', string $order = 'DESC'): array
	{
		$read = static function (?array $rating_clause, array $sort, int $rows) use ($atts): array {
			return self::read_booking_reviews($atts, $rows, $sort, $rating_clause);
		};
		return self::read_in_order($read, $fetch_limit, $orderby, $order, 'date', '_mhmrentiva_customer_rating');
	}

	/**
	 * One booking-review read.
	 *
	 * @param array                     $atts          Shortcode attributes.
	 * @param int                       $fetch_limit   Rows to read.
	 * @param array<string,string>      $sort          WP_Query orderby (constants only).
	 * @param array<int|string,mixed>|null $rating_clause Extra meta_query clauses for the rating order.
	 * @return array<int, array<string, mixed>>
	 */
	private static function read_booking_reviews(array $atts, int $fetch_limit, array $sort, ?array $rating_clause): array
	{
		$args = array(
			'post_type'      => 'mhmrentiva_booking',
			'post_status'    => 'publish',
			// Bounded: this runs on a public page and the caller slices to the
			// displayed count anyway.
			'posts_per_page' => max(1, min($fetch_limit, self::MAX_ROWS)),
			'orderby'        => $sort,
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

		if (null !== $rating_clause) {
			$args['meta_query'] = array_merge($args['meta_query'], $rating_clause);
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
				'date_display'  => self::date_display( (string) $booking->post_date),
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
	private static function get_vehicle_comments(array $atts, int $fetch_limit = self::MAX_ROWS, string $orderby = 'date', string $order = 'DESC'): array
	{
		$read = static function (?array $rating_clause, array $sort, int $rows) use ($atts): array {
			return self::read_vehicle_comments($atts, $rows, $sort, $rating_clause);
		};
		return self::read_in_order($read, $fetch_limit, $orderby, $order, 'comment_date', 'mhmrentiva_rating');
	}

	/**
	 * One vehicle-comment read.
	 *
	 * @param array                        $atts          Shortcode attributes.
	 * @param int                          $fetch_limit   Rows to read.
	 * @param array<string,string>         $sort          WP_Comment_Query orderby (constants only).
	 * @param array<int|string,mixed>|null $rating_clause Extra meta_query clauses for the rating order.
	 * @return array<int, array<string, mixed>>
	 */
	private static function read_vehicle_comments(array $atts, int $fetch_limit, array $sort, ?array $rating_clause): array
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
			'orderby'     => $sort,
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

		if (null !== $rating_clause) {
			$comment_args['meta_query'] = array_merge($comment_args['meta_query'] ?? array(), $rating_clause);
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
				'date_display'  => self::date_display( (string) $comment->comment_date),
				'vehicle_id'    => $vehicle_id,
				'vehicle_name'  => self::get_vehicle_name($vehicle_id),
			);
		}

		return $testimonials;
	}

	/**
	 * Sanitize orderby value for testimonial queries.
	 *
	 * The orders the list applies (the schema's values). Anything else --
	 * including the Elementor control's old title/modified options, which never
	 * sorted by anything but date -- is the date order.
	 */
	private static function sanitize_orderby(string $value): string
	{
		$allowed = array( 'date', 'rating', 'rand' );
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

	/**
	 * The date as the template prints it, for cards "Load More" renders.
	 */
	private static function date_display(string $date): string
	{
		$time = strtotime($date);
		return false === $time ? '' : (string) date_i18n( (string) get_option('date_format', 'd.m.Y'), $time);
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

		// The first page's order: get_testimonials() maps them onto its allowlists
		// (sanitize_orderby / sanitize_order); the seed continues a random draw.
		$orderby = isset($_POST['orderby']) ? sanitize_key(wp_unslash( (string) $_POST['orderby'])) : 'date';
		$order   = isset($_POST['order']) ? sanitize_key(wp_unslash( (string) $_POST['order'])) : 'desc';
		$seed    = isset($_POST['seed']) ? absint(wp_unslash( (string) $_POST['seed'])) : 0;

		$atts = array(
			'limit'      => $limit,
			'rating'     => $rating,
			'vehicle_id' => $vehicle_id,
			'orderby'    => $orderby,
			'order'      => $order,
			'seed'       => $seed,
			// Without this the page number only ever reached has_more, and every
			// click re-sent the first page.
			'offset'     => ( max(1, $page) - 1 ) * $limit,
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
				// Up to what can be served (servable_count()), not every review:
				// past the pool the button stayed and served empty pages.
				'has_more'     => ( $page * $limit ) < self::servable_count($total_count),
				'message'      => __('Reviews loaded successfully.', 'mhm-rentiva'),
			)
		);
	}
}
