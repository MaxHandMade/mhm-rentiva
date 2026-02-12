<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\Helpers;

use MHMRentiva\Admin\Core\MetaKeys;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rating Sort & Filter Helper
 *
 * Central helper for sorting and filtering vehicles by rating metrics.
 * Used by VehiclesList, VehiclesGrid shortcodes and their Gutenberg block counterparts.
 *
 * Sorting Strategy: Uses persisted post meta values for SQL-level sorting via WP_Query.
 * No N+1 queries — all sorting happens at the database level.
 *
 * @since 4.7.0
 * @package MHMRentiva\Admin\Vehicle\Helpers
 */
final class RatingSortHelper {

	/**
	 * Known rating-based orderby keys
	 *
	 * @var array<string, string>
	 */
	private const ORDERBY_META_MAP = array(
		'rating'         => '_mhm_rentiva_rating_average',
		'rating_average' => '_mhm_rentiva_rating_average',
		'rating_count'   => '_mhm_rentiva_rating_count',
		'confidence'     => '_mhm_rentiva_confidence_score',
	);

	/**
	 * Compute confidence score from rating average and count.
	 *
	 * Formula: (avg * 1000) + min(cnt, 999)
	 * - Average dominates: a 4.5-star vehicle always outranks a 3.0-star vehicle
	 * - Count is tie-breaker: among equal averages, more reviews rank higher
	 * - Capped at 999 to prevent count from overflowing into the next average tier
	 *
	 * Examples:
	 *   avg=4.5, cnt=12  → 4512
	 *   avg=4.5, cnt=3   → 4503
	 *   avg=3.0, cnt=50  → 3050
	 *   avg=0.0, cnt=0   → 0
	 *
	 * @since 4.7.0
	 *
	 * @param float $average Rating average (0.0–5.0).
	 * @param int   $count   Review count (>= 0).
	 * @return int Computed confidence score.
	 */
	public static function compute_score( float $average, int $count ): int {
		$average = max( 0.0, min( 5.0, $average ) );
		$count   = max( 0, $count );

		return (int) ( ( $average * 1000 ) + min( $count, 999 ) );
	}

	/**
	 * Apply rating-based sort arguments to a WP_Query args array.
	 *
	 * Only acts on known rating orderby keys. Non-rating keys pass through untouched,
	 * preserving backward compatibility with existing sort options (title, date, price, etc.).
	 *
	 * Tie-breaking order:
	 * 1. Primary sort field (meta_value_num)
	 * 2. post_date DESC (newer first)
	 * 3. ID DESC (deterministic)
	 *
	 * @since 4.7.0
	 *
	 * @param array  $args    WP_Query args array (modified by reference).
	 * @param string $orderby The orderby value from shortcode attributes.
	 * @param string $order   Sort direction: 'ASC' or 'DESC'.
	 * @return void
	 */
	public static function apply_sort_args( array &$args, string $orderby, string $order = 'DESC' ): void {
		$orderby = strtolower( trim( $orderby ) );
		$order   = strtoupper( trim( $order ) );

		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}

		if ( ! isset( self::ORDERBY_META_MAP[ $orderby ] ) ) {
			return; // Not a rating key — let WP_Query handle natively
		}

		$meta_key = self::ORDERBY_META_MAP[ $orderby ];

		// Named meta query for compound orderby
		$args['meta_query'] = array_merge(
			$args['meta_query'] ?? array(),
			array(
				'mhm_rating_sort' => array(
					'key'     => $meta_key,
					'type'    => 'NUMERIC',
					'compare' => 'EXISTS',
				),
			)
		);

		// Compound orderby with tie-breakers
		$args['orderby'] = array(
			'mhm_rating_sort' => $order,
			'date'            => 'DESC',
			'ID'              => 'DESC',
		);

		// Remove the simple 'order' key — compound orderby defines direction per-field
		unset( $args['order'] );
	}

	/**
	 * Apply rating-based filter arguments to a WP_Query args array.
	 *
	 * Supports:
	 * - min_rating: Minimum rating average (float 1.0–5.0)
	 * - min_reviews: Minimum review count (int >= 1)
	 *
	 * Empty/zero values are ignored (opt-in filtering).
	 *
	 * @since 4.7.0
	 *
	 * @param array $args WP_Query args array (modified by reference).
	 * @param array $atts Shortcode attributes containing filter values.
	 * @return void
	 */
	public static function apply_filter_args( array &$args, array $atts ): void {
		$min_rating  = isset( $atts['min_rating'] ) ? (float) $atts['min_rating'] : 0.0;
		$min_reviews = isset( $atts['min_reviews'] ) ? (int) $atts['min_reviews'] : 0;

		if ( $min_rating <= 0.0 && $min_reviews <= 0 ) {
			return; // No filters active
		}

		if ( ! isset( $args['meta_query'] ) ) {
			$args['meta_query'] = array();
		}

		// Ensure 'AND' relation for combining with existing meta queries
		if ( ! isset( $args['meta_query']['relation'] ) ) {
			$args['meta_query']['relation'] = 'AND';
		}

		if ( $min_rating > 0.0 ) {
			$min_rating           = min( 5.0, $min_rating );
			$args['meta_query'][] = array(
				'key'     => MetaKeys::VEHICLE_RATING_AVERAGE,
				'value'   => $min_rating,
				'type'    => 'DECIMAL(2,1)',
				'compare' => '>=',
			);
		}

		if ( $min_reviews > 0 ) {
			$args['meta_query'][] = array(
				'key'     => MetaKeys::VEHICLE_RATING_COUNT,
				'value'   => $min_reviews,
				'type'    => 'NUMERIC',
				'compare' => '>=',
			);
		}
	}

	/**
	 * Check if a given orderby value is a rating-based sort key.
	 *
	 * @since 4.7.0
	 *
	 * @param string $orderby The orderby value to check.
	 * @return bool True if this is a rating sort key.
	 */
	public static function is_rating_sort( string $orderby ): bool {
		return isset( self::ORDERBY_META_MAP[ strtolower( trim( $orderby ) ) ] );
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
