<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded application queries are intentional in this module.

use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Services\CompareService;
use DateTime;
use DateInterval;
use MHMRentiva\Admin\Core\AssetManager;
use MHMRentiva\Frontend\PreviewSamples;
use MHMRentiva\Frontend\RenderContext;
use MHMRentiva\Admin\Frontend\Shortcodes\Rvs\VehicleDetailsView;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vehicle page (`rentiva_vehicle_details`) on the front-end standard.
 *
 * Aşama 2 PR B: the page's data is VehicleDetailsView; the template prints it
 * inside `.rvs-shell`. Two endpoints (months, booking-card quote) answer with
 * the same partials.
 */
final class VehicleDetails extends AbstractShortcode {

	public const SHORTCODE = 'rentiva_vehicle_details';

	protected static bool $rvs_shell = true;

	public static function register(): void {
		parent::register();
		// Aşama 2 PR B: the rvs page's months and live booking-card quote.
		add_action( 'wp_ajax_mhmrentiva_vd_months', array( self::class, 'ajax_months' ) );
		add_action( 'wp_ajax_nopriv_mhmrentiva_vd_months', array( self::class, 'ajax_months' ) );
		add_action( 'wp_ajax_mhmrentiva_vd_quote', array( self::class, 'ajax_quote' ) );
		add_action( 'wp_ajax_nopriv_mhmrentiva_vd_quote', array( self::class, 'ajax_quote' ) );
	}

	/**
	 * A published vehicle from the request, or a JSON error (and 0).
	 */
	private static function requested_vehicle(): int {
		if ( ! check_ajax_referer( 'mhmrentiva_vd_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'mhm-rentiva' ) ) );
		}
		$vehicle_id = isset( $_POST['vehicle_id'] ) ? absint( wp_unslash( (string) $_POST['vehicle_id'] ) ) : 0;
		$vehicle    = $vehicle_id > 0 ? get_post( $vehicle_id ) : null;
		if ( ! $vehicle || 'mhmrentiva_vehicle' !== $vehicle->post_type || 'publish' !== $vehicle->post_status ) {
			wp_send_json_error( array( 'message' => __( 'Vehicle not found.', 'mhm-rentiva' ) ) );
		}
		return $vehicle_id;
	}

	/**
	 * @param list<array<string, mixed>> $months
	 */
	private static function months_html( array $months ): string {
		$html = '';
		foreach ( $months as $month ) {
			$html .= (string) Templates::render( 'partials/rvs/calendar-month', array( 'calendar' => $month ), true );
		}
		return $html;
	}

	/**
	 * Two months from year/month, for the vehicle page's month navigation.
	 */
	public static function ajax_months(): void {
		$vehicle_id = self::requested_vehicle();
		if ( $vehicle_id <= 0 ) {
			return;
		}
		$year  = isset( $_POST['year'] ) ? absint( wp_unslash( (string) $_POST['year'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in requested_vehicle().
		$month = isset( $_POST['month'] ) ? absint( wp_unslash( (string) $_POST['month'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in requested_vehicle().
		if ( $year < 2000 || $year > 2100 || $month < 1 || $month > 12 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid month.', 'mhm-rentiva' ) ) );
		}
		$window = VehicleDetailsView::window( self::posted_window() );
		$months = VehicleDetailsView::months( $vehicle_id, sprintf( '%04d-%02d-01', $year, $month ), $window );
		wp_send_json_success( array( 'html' => self::months_html( $months ) ) );
	}

	/**
	 * The booking card for a new window: total, breakdown, continue link,
	 * availability, and the months around it -- the page's own partials.
	 */
	public static function ajax_quote(): void {
		$vehicle_id = self::requested_vehicle();
		if ( $vehicle_id <= 0 ) {
			return;
		}
		// Same allowance as the booking form's price endpoint: a visitor trying a
		// few date ranges fires a request per change.
		\MHMRentiva\Admin\Core\SecurityHelper::check_rate_limit_or_die( 'vehicle_quote', 30, 60, __( 'Too many price requests. Please wait.', 'mhm-rentiva' ) );

		$window  = VehicleDetailsView::window( self::posted_window() );
		$booking = VehicleDetailsView::booking( $vehicle_id, array(), $window );
		$months  = VehicleDetailsView::months( $vehicle_id, $window['pickup_date'], $window );

		wp_send_json_success(
			array(
				'days'           => (int) $booking['days'],
				'total_html'     => (string) $booking['breakdown']['total']['html'],
				'breakdown_html' => (string) Templates::render( 'partials/rvs/breakdown', array(
					'breakdown'   => $booking['breakdown'],
					'collapsible' => true,
				), true ),
				'continue_url'   => (string) $booking['continue_url'],
				'available'      => (bool) $booking['available'],
				'message'        => (string) $booking['message'],
				'months_html'    => self::months_html( $months ),
			)
		);
	}

	/**
	 * @return array<string, string>
	 */
	private static function posted_window(): array {
		$window = array();
		foreach ( array( 'pickup_date', 'pickup_time', 'return_date', 'pickup_location' ) as $key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in requested_vehicle(); VehicleDetailsView::window() validates each value.
			$window[ $key ] = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) ) : '';
		}
		return $window;
	}

	protected static function get_shortcode_tag(): string {
		return self::SHORTCODE;
	}

	protected static function get_template_path(): string {
		return 'shortcodes/vehicle-details';
	}

	protected static function get_default_attributes(): array {
		return array(
			'vehicle_id'             => '',
			'show_gallery'           => '1',
			'show_features'          => '1',
			'show_pricing'           => '1',
			'show_booking'           => '1',
			'show_calendar'          => '1',
			'show_price'             => '1',
			'show_booking_button'    => '1',
			'show_favorite_button'   => '1',
			'show_compare_button'    => '1',
			// Spec 3A Task 4: the rating line, the reviews section (exception 2) and the
			// similar section; an empty limit keeps the template's mobile 6 / desktop 3.
			'show_rating'            => '1',
			'show_reviews'           => '1',
			'show_similar_vehicles'  => '1',
			'similar_vehicles_limit' => '',
			'class'                  => '',
		);
	}

	protected static function get_css_filename(): string {
		return 'vehicle-details.css';
	}

	/**
	 * The rvs page: the vehicle stylesheet (card, calendar, breakdown, dialog
	 * underneath), the dialog module, favourites/compare, the date picker and
	 * the page script. The old vehicle-details.css / .js are not loaded here.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 */
	protected static function enqueue_assets( array $atts = array() ): void {
		AssetManager::enqueue_core_js();
		AssetManager::enqueue_rvs_vehicle_styles();
		AssetManager::enqueue_rvs_dialog();
		AssetManager::enqueue_vehicle_interactions();
		\MHMRentiva\Admin\Core\Assets\DatepickerAssets::enqueue();
	}

	protected static function get_js_filename(): string {
		return 'vehicle-details.js';
	}

	protected static function get_script_object_name(): string {
		return 'mhmRentivaVehicleDetails';
	}

	protected static function get_localized_strings(): array {
		return array(
			'loading'        => __( 'Loading...', 'mhm-rentiva' ),
			'error_occurred' => __( 'An error occurred', 'mhm-rentiva' ),
			'try_again'      => __( 'Please try again', 'mhm-rentiva' ),
			'book_now'       => __( 'Book Now', 'mhm-rentiva' ),
			'view_gallery'   => __( 'View Gallery', 'mhm-rentiva' ),
		);
	}

	protected static function get_localized_data(): array {
		$data          = parent::get_localized_data();
		$data['nonce'] = wp_create_nonce( 'mhmrentiva_vd_nonce' );
		return $data;
	}

	protected static function prepare_template_data( array $atts ): array {
		// An explicit id is checked by the shared rule; the URL slug is only consulted
		// when none is given. Neither a rejected id nor a missing context renders the
		// page skeleton: render_template() draws a short empty state instead.
		$given = (int) ( $atts['vehicle_id'] ?? 0 );

		[ $vehicle_id, $badge ] = PreviewSamples::resolve_vehicle( $given, 0 === $given ? self::get_routed_vehicle_id() : 0 );
		if ( $vehicle_id <= 0 ) {
			return array(
				'vehicle_id'            => 0,
				self::PREVIEW_BADGE_KEY => $badge,
			);
		}

		$vehicle = get_post( $vehicle_id );

		// Public shortcode: an unpublished vehicle renders as "not found" rather
		// than exposing its title, gallery, specification and price to anyone
		// who knows or guesses the ID.
		if ( ! $vehicle || $vehicle->post_type !== 'mhmrentiva_vehicle' || $vehicle->post_status !== 'publish' ) {
			return self::get_default_template_data( $atts );
		}

		$template_data = array(
			'vehicle_id'      => $vehicle_id,
			'atts'            => $atts,
			'view'            => VehicleDetailsView::build( $vehicle_id, $atts, UnifiedSearch::values() ),
			// A transfer-only vehicle keeps its page but gets no rental booking card.
			'rental_bookable' => \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::allows_service( $vehicle_id, 'rental' ),
			'is_favorite'     => \MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList::is_favorite( $vehicle_id ),
			'is_in_compare'   => class_exists( CompareService::class ) ? CompareService::is_in_compare( $vehicle_id ) : false,
		);

		$template_data[ self::PREVIEW_BADGE_KEY ] = $badge;

		return $template_data;
	}

	/**
	 * The badge (if any) is prefixed by the base class; a missing vehicle is a short
	 * empty state, never the page skeleton and never an internal path.
	 *
	 * @param array<string,mixed> $template_data Prepared template data.
	 */
	protected static function render_template( array $template_data ): string {
		if ( (int) ( $template_data['vehicle_id'] ?? 0 ) > 0 ) {
			return parent::render_template( $template_data );
		}

		// A badge with no vehicle is the invalid-vehicle badge: it already says what
		// to check, so no second, contradictory instruction follows it. (Outside a
		// preview the badge is '' and the visitor's neutral state below is drawn.)
		$badge      = (string) ( $template_data[ self::PREVIEW_BADGE_KEY ] ?? '' );
		$badge_html = '' !== $badge ? RenderContext::badge( $badge ) : '';
		if ( '' !== $badge_html ) {
			return $badge_html;
		}

		$is_preview = RenderContext::current()->is_preview();
		$text       = $is_preview
			? __( 'No vehicle selected. Set a vehicle ID in the widget settings, or place this widget on a vehicle page.', 'mhm-rentiva' )
			: __( 'Vehicle details are not available here.', 'mhm-rentiva' );

		return '<div class="rv-vehicle-details-empty">' . esc_html( $text ) . '</div>';
	}

	/**
	 * The vehicle the URL names (SEO sub-path slug), or 0.
	 */
	private static function get_routed_vehicle_id(): int {
		// Support SEO-friendly sub-path routing: /vehicle-details/car-slug/ → mhmrentiva_vehicle_slug
		// query var, registered via Plugin::register_vehicle_rewrite_rules() +
		// add_rewrite_tag('%mhmrentiva_vehicle_slug%', ...). The URL itself is unchanged;
		// only the internal query-var name carries the plugin prefix.
		$get_slug = sanitize_title( get_query_var( 'mhmrentiva_vehicle_slug', '' ) );
		if ( '' !== $get_slug ) {
			// Use get_posts() instead of get_page_by_path() — the latter is designed for
			// hierarchical post types and can return unexpected results for CPTs.
			$matches = get_posts( array(
				'post_type'      => 'mhmrentiva_vehicle',
				'post_status'    => 'publish',
				'name'           => $get_slug,
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'fields'         => 'ids',
				'meta_query'     => array(
					\MHMRentiva\Admin\Core\Utilities\MetaQueryHelper::get_active_vehicle_meta_query(),
				),
			) );
			if ( ! empty( $matches ) ) {
				return (int) $matches[0];
			}
		}
		return 0;
	}

	/**
	 * The vehicle's cache key includes the vehicle the URL names: every
	 * /vehicle-details/{slug}/ page shares one page id.
	 *
	 * @return array<string, mixed>
	 */
	protected static function cache_request_factors(): array {
		$factors                 = parent::cache_request_factors();
		$factors['vehicle_slug'] = (string) get_query_var( 'mhmrentiva_vehicle_slug', '' );
		return $factors;
	}

	/**
	 * Days the vehicle is taken between $from and $to (inclusive, `Y-m-d`), from
	 * pickup to return day inclusive. The calendar is a guide; the booking card
	 * checks the exact window with Util::check_availability().
	 *
	 * @return list<string>
	 */
	public static function booked_dates( int $vehicle_id, string $from, string $to ): array {
		global $wpdb;
		$start_date = $from;
		$end_date   = $to;

		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm_start.meta_value as start_date, pm_end.meta_value as end_date
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_v ON p.ID = pm_v.post_id AND pm_v.meta_key = '_mhmrentiva_vehicle_id'
             INNER JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key = '_mhmrentiva_pickup_date'
             INNER JOIN {$wpdb->postmeta} pm_end ON p.ID = pm_end.post_id AND pm_end.meta_key = '_mhmrentiva_dropoff_date'
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_mhmrentiva_status'
             WHERE p.post_type = 'mhmrentiva_booking' AND p.post_status = 'publish'
             AND pm_v.meta_value = %d AND pm_status.meta_value IN ('pending_payment', 'pending', 'confirmed', 'in_progress', 'completed')
             AND ((pm_start.meta_value <= %s AND pm_end.meta_value >= %s) OR (pm_start.meta_value >= %s AND pm_start.meta_value <= %s))",
				$vehicle_id,
				$end_date,
				$start_date,
				$start_date,
				$end_date
			)
		);

		$booked = array();
		foreach ( $bookings as $booking ) {
			try {
				$day  = new DateTime( substr( (string) $booking->start_date, 0, 10 ) );
				$last = new DateTime( substr( (string) $booking->end_date, 0, 10 ) );
			} catch ( \Exception $e ) {
				continue;
			}
			while ( $day <= $last ) {
				$date = $day->format( 'Y-m-d' );
				if ( $date >= $from && $date <= $to ) {
					$booked[ $date ] = true;
				}
				$day->add( new DateInterval( 'P1D' ) );
			}
		}
		$dates = array_keys( $booked );
		sort( $dates );
		return $dates;
	}

	/**
	 * Gallery attachment ids, displayable ones only (legacy keys included).
	 *
	 * @return list<int>
	 */
	public static function gallery_ids( int $vehicle_id ): array {
		return array_values( array_map( static fn( array $image ): int => (int) $image['id'], self::get_gallery( $vehicle_id ) ) );
	}

	private static function get_gallery( int $vehicle_id ): array {
		// prefix-rename:ignore-start
		// Try multiple possible meta keys for compatibility.
		//
		// This was a three-rung fallback and the 6.0.0 rename collapsed two of the
		// rungs onto one name -- '_mhm_rentiva_gallery_images' and
		// '_mhm_gallery_images' both become '_mhmrentiva_gallery_images', so the
		// list read as a duplicate and a rung was silently lost. Both pre-rename
		// spellings are listed explicitly, because a site that has not run the
		// 13's migration still stores its gallery under one of them and would
		// otherwise render no gallery at all.
		$keys = array(
			'_mhmrentiva_gallery_images',
			'_mhm_rentiva_gallery_images',
			'_mhm_gallery_images',
			'_mhmrentiva_gallery',
			'_mhm_rentiva_gallery',
		);
		// prefix-rename:ignore-end
		$gallery_data = '';

		foreach ( $keys as $key ) {
			$val = get_post_meta( $vehicle_id, $key, true );
			if ( ! empty( $val ) ) {
				$gallery_data = $val;
				break;
			}
		}

		if ( empty( $gallery_data ) ) {
			return array();
		}

		$gallery_ids = \MHMRentiva\Admin\Vehicle\Meta\VehicleGallery::normalize_gallery( $gallery_data );

		$gallery = array();
		foreach ( $gallery_ids as $item ) {
			$id = \MHMRentiva\Admin\Vehicle\Meta\VehicleGallery::entry_id( $item );
			if ( $id > 0 && \MHMRentiva\Admin\Vehicle\Meta\VehicleGallery::is_displayable_image( $vehicle_id, $id ) ) {
				$gallery[] = array(
					'id'        => $id,
					'url'       => wp_get_attachment_image_url( $id, 'medium' ),
					'url_large' => wp_get_attachment_image_url( $id, 'large' ),
					'url_full'  => wp_get_attachment_image_url( $id, 'full' ),
					'alt'       => get_post_meta( $id, '_wp_attachment_image_alt', true ) ?: '',
				);
			}
		}
		return $gallery;
	}

	private static function get_default_template_data( array $atts ): array {
		unset( $atts );
		return array( 'vehicle_id' => 0 );
	}
}
