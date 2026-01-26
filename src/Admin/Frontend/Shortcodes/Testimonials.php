<?php

namespace MHMRentiva\Admin\Frontend\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
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
	 */
	public static function sanitize_text_field_safe( $value ) {
		if ( $value === null || $value === '' ) {
			return '';
		}
		return sanitize_text_field( (string) $value );
	}

	public static function register(): void {
		parent::register();

		add_action( 'wp_ajax_mhm_rentiva_load_testimonials', array( self::class, 'ajax_load_testimonials' ) );
		add_action( 'wp_ajax_nopriv_mhm_rentiva_load_testimonials', array( self::class, 'ajax_load_testimonials' ) );
	}

	protected static function get_shortcode_tag(): string {
		return 'rentiva_testimonials';
	}

	protected static function get_template_path(): string {
		return 'shortcodes/testimonials';
	}

	protected static function get_default_attributes(): array {
		return array(
			'limit'         => apply_filters( 'mhm_rentiva/testimonials/limit', '5' ),
			'rating'        => apply_filters( 'mhm_rentiva/testimonials/rating', '' ),
			'vehicle_id'    => apply_filters( 'mhm_rentiva/testimonials/vehicle_id', '' ),
			'show_rating'   => apply_filters( 'mhm_rentiva/testimonials/show_rating', '1' ),
			'show_date'     => apply_filters( 'mhm_rentiva/testimonials/show_date', '1' ),
			'show_vehicle'  => apply_filters( 'mhm_rentiva/testimonials/show_vehicle', '1' ),
			'show_customer' => apply_filters( 'mhm_rentiva/testimonials/show_customer', '1' ),
			'layout'        => apply_filters( 'mhm_rentiva/testimonials/layout', 'grid' ),
			'columns'       => apply_filters( 'mhm_rentiva/testimonials/columns', '3' ),
			'auto_rotate'   => apply_filters( 'mhm_rentiva/testimonials/auto_rotate', '0' ),
			'class'         => apply_filters( 'mhm_rentiva/testimonials/class', '' ),
		);
	}

	protected static function get_css_filename(): string {
		return 'testimonials.css';
	}

	protected static function get_js_filename(): string {
		return 'testimonials.js';
	}

	/**
	 * Load asset files
	 */
	protected static function enqueue_assets(): void {
		// CSS
		wp_enqueue_style(
			'mhm-rentiva-testimonials',
			MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/testimonials.css',
			array(),
			MHM_RENTIVA_VERSION
		);

		// JavaScript
		wp_enqueue_script(
			'mhm-rentiva-testimonials',
			MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/testimonials.js',
			array( 'jquery' ),
			MHM_RENTIVA_VERSION,
			true
		);

		// Localize script
		self::localize_script( 'mhm-rentiva-testimonials' );
	}

	protected static function get_script_object_name(): string {
		return 'mhmRentivaTestimonials';
	}

	protected static function get_localized_strings(): array {
		return array(
			'loading'        => __( 'Loading...', 'mhm-rentiva' ),
			'error'          => __( 'An error occurred', 'mhm-rentiva' ),
			'noTestimonials' => __( 'No testimonials found yet', 'mhm-rentiva' ),
			'loadMore'       => __( 'Load More Reviews', 'mhm-rentiva' ),
		);
	}

	public static function render( array $atts = array(), ?string $content = null ): string {
		$defaults = array(
			'limit'         => apply_filters( 'mhm_rentiva/testimonials/limit', '5' ),
			'rating'        => apply_filters( 'mhm_rentiva/testimonials/rating', '' ),
			'vehicle_id'    => apply_filters( 'mhm_rentiva/testimonials/vehicle_id', '' ),
			'show_rating'   => apply_filters( 'mhm_rentiva/testimonials/show_rating', '1' ),
			'show_date'     => apply_filters( 'mhm_rentiva/testimonials/show_date', '1' ),
			'show_vehicle'  => apply_filters( 'mhm_rentiva/testimonials/show_vehicle', '1' ),
			'show_customer' => apply_filters( 'mhm_rentiva/testimonials/show_customer', '1' ),
			'layout'        => apply_filters( 'mhm_rentiva/testimonials/layout', 'grid' ),
			'columns'       => apply_filters( 'mhm_rentiva/testimonials/columns', '3' ),
			'auto_rotate'   => apply_filters( 'mhm_rentiva/testimonials/auto_rotate', '0' ),
			'class'         => apply_filters( 'mhm_rentiva/testimonials/class', '' ),
		);

		$atts = shortcode_atts( $defaults, $atts, self::SHORTCODE );

		// Load CSS manually
		self::enqueue_assets();

		// Prepare template data
		$data = self::prepare_template_data( $atts );

		// Render template
		return Templates::render( 'shortcodes/testimonials', $data, true );
	}

	protected static function prepare_template_data( array $atts ): array {
		$testimonials = self::get_testimonials( $atts );

		return array(
			'atts'             => $atts,
			'testimonials'     => $testimonials,
			'total_count'      => self::get_testimonials_count( $atts ),
			'has_testimonials' => ! empty( $testimonials ),
		);
	}

	private static function get_testimonials( array $atts ): array {
		$args = array(
			'post_type'      => 'vehicle_booking',
			'post_status'    => 'publish',
			'posts_per_page' => intval( $atts['limit'] ),
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => '_mhm_rentiva_customer_review',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => '_mhm_rentiva_review_approved',
					'value'   => '1',
					'compare' => '=',
				),
			),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		// Rating filter
		if ( ! empty( $atts['rating'] ) ) {
			$args['meta_query'][] = array(
				'key'     => '_mhm_rentiva_customer_rating',
				'value'   => intval( $atts['rating'] ),
				'compare' => '>=',
			);
		}

		// Vehicle filter
		if ( ! empty( $atts['vehicle_id'] ) ) {
			$args['meta_query'][] = array(
				'key'     => '_mhm_rentiva_vehicle_id',
				'value'   => intval( $atts['vehicle_id'] ),
				'compare' => '=',
			);
		}

		$bookings     = get_posts( $args );
		$testimonials = array();

		foreach ( $bookings as $booking ) {
			$testimonials[] = array(
				'id'             => $booking->ID,
				'review'         => get_post_meta( $booking->ID, '_mhm_rentiva_customer_review', true ),
				'rating'         => intval( get_post_meta( $booking->ID, '_mhm_rentiva_customer_rating', true ) ),
				'customer_name'  => get_post_meta( $booking->ID, '_mhm_rentiva_customer_name', true ),
				'customer_email' => get_post_meta( $booking->ID, '_mhm_rentiva_customer_email', true ),
				'date'           => $booking->post_date,
				'vehicle_id'     => get_post_meta( $booking->ID, '_mhm_rentiva_vehicle_id', true ),
				'vehicle_name'   => self::get_vehicle_name( get_post_meta( $booking->ID, '_mhm_rentiva_vehicle_id', true ) ),
			);
		}

		return $testimonials;
	}

	private static function get_testimonials_count( array $atts ): int {
		$args = array(
			'post_type'      => 'vehicle_booking',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => '_mhm_rentiva_customer_review',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => '_mhm_rentiva_review_approved',
					'value'   => '1',
					'compare' => '=',
				),
			),
		);

		// Rating filter
		if ( ! empty( $atts['rating'] ) ) {
			$args['meta_query'][] = array(
				'key'     => '_mhm_rentiva_customer_rating',
				'value'   => intval( $atts['rating'] ),
				'compare' => '>=',
			);
		}

		// Vehicle filter
		if ( ! empty( $atts['vehicle_id'] ) ) {
			$args['meta_query'][] = array(
				'key'     => '_mhm_rentiva_vehicle_id',
				'value'   => intval( $atts['vehicle_id'] ),
				'compare' => '=',
			);
		}

		$query = new \WP_Query( $args );
		return $query->found_posts;
	}

	private static function get_vehicle_name( int $vehicle_id ): string {
		if ( $vehicle_id <= 0 ) {
			return '';
		}

		$vehicle = get_post( $vehicle_id );
		return $vehicle ? $vehicle->post_title : '';
	}

	/**
	 * Load more testimonials via AJAX
	 */
	public static function ajax_load_testimonials(): void {
		try {
			// Nonce check
			$nonce = $_POST['nonce'] ?? '';
			if ( ! wp_verify_nonce( $nonce, 'mhm_rentiva_testimonials_nonce' ) ) {
				wp_send_json_error( array( 'message' => __( 'Security check failed.', 'mhm-rentiva' ) ) );
			}

			$page       = intval( $_POST['page'] ?? 1 );
			$limit      = intval( $_POST['limit'] ?? 5 );
			$rating     = self::sanitize_text_field_safe( $_POST['rating'] ?? '' );
			$vehicle_id = self::sanitize_text_field_safe( $_POST['vehicle_id'] ?? '' );

			$atts = array(
				'limit'      => $limit,
				'rating'     => $rating,
				'vehicle_id' => $vehicle_id,
			);

			$testimonials = self::get_testimonials( $atts );
			$total_count  = self::get_testimonials_count( $atts );

			wp_send_json_success(
				array(
					'testimonials' => $testimonials,
					'total_count'  => $total_count,
					'has_more'     => ( $page * $limit ) < $total_count,
					'message'      => __( 'Reviews loaded successfully.', 'mhm-rentiva' ),
				)
			);
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => __( 'An error occurred while loading reviews.', 'mhm-rentiva' ) ) );
		}
	}
}
