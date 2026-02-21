<?php

declare(strict_types=1);
// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded application queries are intentional in this module.

namespace MHMRentiva\Admin\Frontend\Shortcodes;

use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper;
use MHMRentiva\Admin\Core\CurrencyHelper;
use DateTime;
use DateInterval;
use MHMRentiva\Admin\Core\AssetManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vehicle Details Shortcode
 */
final class VehicleDetails extends AbstractShortcode {

	public const SHORTCODE      = 'rentiva_vehicle_details';
	private const CACHE_VERSION = 'card_fields_v8'; // Bumped version

	public static function register(): void {
		parent::register();
		add_action( 'wp_ajax_mhm_rentiva_get_calendar', array( self::class, 'ajax_get_calendar' ) );
		add_action( 'wp_ajax_nopriv_mhm_rentiva_get_calendar', array( self::class, 'ajax_get_calendar' ) );
	}

	protected static function get_shortcode_tag(): string {
		return self::SHORTCODE;
	}

	protected static function get_template_path(): string {
		return 'shortcodes/vehicle-details';
	}

	protected static function get_default_attributes(): array {
		return array(
			'vehicle_id'          => '',
			'show_gallery'        => '1',
			'show_features'       => '1',
			'show_pricing'        => '1',
			'show_booking'        => '1',
			'show_calendar'       => '1',
			'show_price'          => '1',
			'show_booking_button' => '1',
			'class'               => '',
		);
	}

	protected static function get_css_filename(): string {
		return 'vehicle-details.css';
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
		$data['nonce'] = wp_create_nonce( 'mhm_rentiva_calendar_nonce' );
		return $data;
	}

	protected static function prepare_template_data( array $atts ): array {
		$vehicle_id = self::get_vehicle_id( $atts );
		if ( ! $vehicle_id ) {
			return array();
		}

		// Cache disabled for real-time rating updates
		// To re-enable, uncomment the following lines:
		// $cache_key   = 'vehicle_details_' . self::CACHE_VERSION . '_' . $vehicle_id . '_' . md5(serialize($atts));
		// $cached_data = get_transient($cache_key);
		// if ($cached_data !== false) {
		//  return $cached_data;
		// }

		$vehicle = get_post( $vehicle_id );
		if ( ! $vehicle || $vehicle->post_type !== 'vehicle' ) {
			return self::get_default_template_data( $atts );
		}

		$availability = self::check_vehicle_availability( $vehicle_id );

		$template_data = array(
			'vehicle_id'      => $vehicle_id,
			'vehicle'         => $vehicle,
			'atts'            => $atts,
			'is_available'    => $availability['is_available'],
			'status'          => $availability['status'],
			'status_text'     => $availability['text'],
			'title'           => $vehicle->post_title,
			'content'         => $vehicle->post_content,
			'excerpt'         => $vehicle->post_excerpt,
			'featured_image'  => self::get_featured_image( $vehicle_id ),
			'gallery'         => self::get_gallery( $vehicle_id ),
			'brand'           => get_post_meta( $vehicle_id, '_mhm_rentiva_brand', true ),
			'model'           => get_post_meta( $vehicle_id, '_mhm_rentiva_model', true ),
			'year'            => self::get_meta_with_fallback( $vehicle_id, array( '_mhm_rentiva_year', 'year' ) ),
			'fuel_type'       => self::get_meta_with_fallback( $vehicle_id, array( '_mhm_rentiva_fuel_type', 'fuel_type' ) ),
			'transmission'    => self::get_meta_with_fallback( $vehicle_id, array( '_mhm_rentiva_transmission', 'transmission' ) ),
			'seats'           => self::get_meta_with_fallback( $vehicle_id, array( '_mhm_rentiva_seats', 'seats' ) ),
			'doors'           => self::get_meta_with_fallback( $vehicle_id, array( '_mhm_rentiva_doors', 'doors' ) ),
			'mileage'         => self::get_meta_with_fallback( $vehicle_id, array( '_mhm_rentiva_mileage', 'mileage' ) ),
			'price_per_day'   => get_post_meta( $vehicle_id, '_mhm_rentiva_price_per_day', true ),
			'currency_symbol' => CurrencyHelper::get_currency_symbol(),
			'features'        => self::get_features( $vehicle_id ),
			'card_features'   => VehicleFeatureHelper::collect_items( $vehicle_id ),
			'categories'      => self::get_categories( $vehicle_id ),
			'booking_url'     => self::get_booking_url( $vehicle_id ),
			'rating'          => self::get_vehicle_rating( $vehicle_id ),
		);

		// Add SVGs to card_features for consistency
		foreach ( $template_data['card_features'] as &$feature ) {
			$feature['svg'] = \MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList::get_feature_icon_svg( $feature['icon'] ?? '' );
		}

		return $template_data;
	}

	private static function get_vehicle_id( array $atts ): int {
		if ( ! empty( $atts['vehicle_id'] ) ) {
			return absint( $atts['vehicle_id'] );
		}
		global $post;
		if ( $post && $post->post_type === 'vehicle' ) {
			return $post->ID;
		}
		$current_id = get_the_ID();
		if ( $current_id && get_post_type( $current_id ) === 'vehicle' ) {
			return $current_id;
		}
		return 0;
	}

	public static function render_monthly_calendar( ?int $vehicle_id = null, ?int $month = null, ?int $year = null ): string {
		if ( ! $vehicle_id || $vehicle_id <= 0 ) {
			return '<div class="rv-calendar-error"><p>' . esc_html__( 'Vehicle ID required', 'mhm-rentiva' ) . '</p></div>';
		}

		$current_month = $month ?? (int) gmdate( 'n' );
		$current_year  = $year ?? (int) gmdate( 'Y' );
		$days_in_month = (int) gmdate( 't', mktime( 0, 0, 0, $current_month, 1, $current_year ) );
		$first_day     = (int) gmdate( 'w', mktime( 0, 0, 0, $current_month, 1, $current_year ) );
		$booked_days   = self::get_booked_days( $vehicle_id, $current_month, $current_year );
		$week_start    = (int) get_option( 'start_of_week', 1 );

		$all_day_names = array( __( 'Paz', 'mhm-rentiva' ), __( 'Pzt', 'mhm-rentiva' ), __( 'Sal', 'mhm-rentiva' ), __( 'Çar', 'mhm-rentiva' ), __( 'Per', 'mhm-rentiva' ), __( 'Cum', 'mhm-rentiva' ), __( 'Cmt', 'mhm-rentiva' ) );
		$day_names     = array_merge( array_slice( $all_day_names, $week_start ), array_slice( $all_day_names, 0, $week_start ) );

		$calendar_html  = '<div class="rv-calendar-grid">';
		$calendar_html .= '<div class="rv-calendar-header-row">';
		foreach ( $day_names as $day_name ) {
			$calendar_html .= '<div class="rv-calendar-day-name">' . esc_html( $day_name ) . '</div>';
		}
		$calendar_html .= '</div>';
		$calendar_html .= '<div class="rv-calendar-days">';

		$first_day_adjusted = ( $first_day - $week_start + 7 ) % 7;
		for ( $i = 0; $i < $first_day_adjusted; $i++ ) {
			$calendar_html .= '<div class="rv-calendar-day empty"></div>';
		}

		for ( $day = 1; $day <= $days_in_month; $day++ ) {
			$is_booked      = in_array( $day, $booked_days, true );
			$is_today       = ( (int) $day === (int) gmdate( 'j' ) && (int) $current_month === (int) gmdate( 'n' ) && (int) $current_year === (int) gmdate( 'Y' ) );
			$class          = 'rv-calendar-day' . ( $is_booked ? ' booked' : '' ) . ( $is_today ? ' today' : '' );
			$calendar_html .= '<div class="' . esc_attr( $class ) . '">' . absint( $day ) . '</div>';
		}

		for ( $i = 0; $i < ( 42 - ( $days_in_month + $first_day_adjusted ) ); $i++ ) {
			$calendar_html .= '<div class="rv-calendar-day empty"></div>';
		}

		$calendar_html .= '</div></div>';
		return $calendar_html;
	}

	private static function get_booked_days( int $vehicle_id, int $month, int $year ): array {
		global $wpdb;
		$start_date = sprintf( '%04d-%02d-01', $year, $month );
		$end_date   = sprintf( '%04d-%02d-%02d', $year, $month, (int) gmdate( 't', mktime( 0, 0, 0, $month, 1, $year ) ) );

		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm_start.meta_value as start_date, pm_end.meta_value as end_date
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_v ON p.ID = pm_v.post_id AND pm_v.meta_key = '_mhm_vehicle_id'
             INNER JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key = '_mhm_pickup_date'
             INNER JOIN {$wpdb->postmeta} pm_end ON p.ID = pm_end.post_id AND pm_end.meta_key = '_mhm_dropoff_date'
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_mhm_status'
             WHERE p.post_type = 'vehicle_booking' AND p.post_status = 'publish'
             AND pm_v.meta_value = %d AND pm_status.meta_value IN ('confirmed', 'pending', 'completed')
             AND ((pm_start.meta_value <= %s AND pm_end.meta_value >= %s) OR (pm_start.meta_value >= %s AND pm_start.meta_value <= %s))",
				$vehicle_id,
				$end_date,
				$start_date,
				$start_date,
				$end_date
			)
		);

		$booked_days = array();
		foreach ( $bookings as $booking ) {
			$start = new DateTime( $booking->start_date );
			$end   = new DateTime( $booking->end_date );
			while ( $start <= $end ) {
				if ( (int) $start->format( 'n' ) === $month && (int) $start->format( 'Y' ) === $year ) {
					$booked_days[] = (int) $start->format( 'j' );
				}
				$start->add( new DateInterval( 'P1D' ) );
			}
		}
		return array_unique( $booked_days );
	}

	private static function check_vehicle_availability( int $vehicle_id ): array {
		return array(
			'is_available' => true,
			'status'       => 'available',
			'text'         => __( 'Available', 'mhm-rentiva' ),
		);
	}

	private static function get_featured_image( int $vehicle_id ): array {
		$image_id = get_post_thumbnail_id( $vehicle_id );
		return array(
			'url' => $image_id ? (string) wp_get_attachment_image_url( $image_id, 'large' ) : '',
			'alt' => (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ) ?: '',
		);
	}

	private static function get_gallery( int $vehicle_id ): array {
		// Try multiple possible meta keys for compatibility
		$keys         = array( '_mhm_rentiva_gallery_images', '_mhm_gallery_images', '_mhm_rentiva_gallery' );
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

		$gallery_ids = is_string( $gallery_data ) ? json_decode( $gallery_data, true ) : $gallery_data;

		if ( ! is_array( $gallery_ids ) ) {
			return array();
		}

		$gallery = array();
		foreach ( $gallery_ids as $item ) {
			$id = is_array( $item ) ? (int) ( $item['id'] ?? 0 ) : (int) $item;
			if ( $id > 0 ) {
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

	private static function get_features( int $vehicle_id ): array {
		$features = get_post_meta( $vehicle_id, '_mhm_rentiva_features', true );
		return is_array( $features ) ? $features : array();
	}

	private static function get_categories( int $vehicle_id ): array {
		$terms = get_the_terms( $vehicle_id, 'vehicle_category' );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return array();
		}
		$cats = array();
		foreach ( $terms as $t ) {
			$cats[] = array(
				'name' => $t->name,
				'url'  => (string) get_term_link( $t ),
			);
		}
		return $cats;
	}

	private static function get_booking_url( int $vehicle_id ): string {
		$url = (string) SettingsCore::get( 'mhm_rentiva_booking_url', '' );
		if ( ! $url && class_exists( '\MHMRentiva\Admin\Core\ShortcodeUrlManager' ) ) {
			$url = \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url( 'rentiva_booking_form' );
		}
		return (string) add_query_arg( 'vehicle_id', $vehicle_id, $url ?: home_url( '/' ) );
	}

	private static function get_vehicle_rating( int $vehicle_id ): array {
		// Single Source of Truth: delegate to RatingHelper (reads post meta).
		return \MHMRentiva\Admin\Vehicle\Helpers\RatingHelper::get_rating( $vehicle_id );
	}

	private static function get_meta_with_fallback( int $vid, array $keys ): string {
		foreach ( $keys as $k ) {
			$v = get_post_meta( $vid, $k, true );
			if ( $v ) {
				return (string) $v;
			}
		}
		return '';
	}

	/**
	 * AJAX handler to get calendar
	 *
	 * @return void
	 */
	public static function ajax_get_calendar(): void {
		// Security check
		if ( ! check_ajax_referer( 'mhm_rentiva_calendar_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'mhm-rentiva' ) ) );
			return;
		}

		$vid   = isset( $_POST['vehicle_id'] ) ? absint( sanitize_text_field( wp_unslash( (string) $_POST['vehicle_id'] ) ) ) : 0;
		$month = isset( $_POST['month'] ) ? absint( sanitize_text_field( wp_unslash( (string) $_POST['month'] ) ) ) : (int) gmdate( 'n' );
		$year  = isset( $_POST['year'] ) ? absint( sanitize_text_field( wp_unslash( (string) $_POST['year'] ) ) ) : (int) gmdate( 'Y' );

		if ( ! $vid ) {
			wp_send_json_error( array( 'message' => __( 'Vehicle ID required', 'mhm-rentiva' ) ) );
			return;
		}

		$calendar_html = self::render_monthly_calendar( $vid, $month, $year );
		$month_year    = date_i18n( 'F Y', mktime( 0, 0, 0, $month, 1, $year ) );

		wp_send_json_success(
			array(
				'calendar_html' => $calendar_html,
				'month_year'    => $month_year,
			)
		);
	}

	private static function get_default_template_data( array $atts ): array {
		return array(
			'vehicle_id' => 0,
			'title'      => __( 'Vehicle not found', 'mhm-rentiva' ),
			'atts'       => $atts,
		);
	}
}

