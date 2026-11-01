<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes;

use MHMRentiva\Admin\Booking\Helpers\BookingConflicts;
use MHMRentiva\Admin\Booking\Helpers\SiteTime;
use MHMRentiva\Admin\Booking\Pricing\PriceDisplay;
use MHMRentiva\Admin\Booking\Pricing\RentalPricing;
use MHMRentiva\Admin\Frontend\Shortcodes\Rvs\RvsCalendar;
use MHMRentiva\Admin\Frontend\Shortcodes\Rvs\VehicleDetailsView;
use MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What the availability calendar shows, as plain data (Aşama 2 PR E).
 *
 * The template only prints this and the two endpoints answer from the same
 * methods, so the first render and a later month or date change cannot
 * disagree. Booked days are BookingConflicts::calendar_days() (live, the
 * search's rule); a total is the vehicle page's own booking card.
 */
final class CalendarView {

	/**
	 * Largest month span one request may render: the months endpoint is nopriv
	 * and this value sizes the grid walk.
	 */
	public const MAX_MONTH_SPAN = 12;

	/**
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return array<string, mixed>
	 */
	public static function build( int $vehicle_id, array $atts ): array {
		$vehicles   = self::vehicles();
		$week_start = self::week_start( $atts['start_week_on'] ?? '1' );
		$count      = self::span( $atts['months_to_show'] ?? '2' );
		$first      = self::opening_month( (string) ( $atts['start_month'] ?? '' ) );

		return array(
			'uid'            => wp_unique_id( 'rvs-availability-' ),
			'vehicle'        => self::vehicle_card( $vehicle_id ),
			'vehicles'       => $vehicles,
			'start_month'    => $first,
			'months_to_show' => $count,
			'week_start'     => $week_start,
			'months'         => self::months( $vehicle_id, $first, $count, array( 'week_start' => $week_start ) ),
			'prev_allowed'   => self::prev_allowed( $first ),
			'show_pricing'   => self::is_on( $atts['show_pricing'] ?? '1' ),
			'show_booking'   => self::is_on( $atts['show_booking_button'] ?? ( $atts['show_booking_btn'] ?? '1' ) ),
			'show_legend'    => self::is_on( $atts['show_legend'] ?? '1' ),
			'show_nav'       => self::is_on( $atts['show_month_nav'] ?? '1' ),
			'show_selector'  => self::is_on( $atts['show_vehicle_selector'] ?? '1' ) && count( $vehicles ) > 1,
			'theme'          => (string) ( $atts['theme'] ?? 'default' ),
			'class'          => (string) ( $atts['class'] ?? '' ),
		);
	}

	/**
	 * The vehicle an attribute names, the newest rental vehicle without one, or
	 * 0: a named vehicle that cannot be shown never falls back to another car.
	 *
	 * @param mixed $attribute The `vehicle_id` attribute.
	 */
	public static function resolve_vehicle( $attribute ): int {
		if ( self::names_vehicle( $attribute ) ) {
			$vehicle_id = (int) $attribute;
			return self::is_offered( $vehicle_id ) ? $vehicle_id : 0;
		}

		$newest = 0;
		$date   = '';
		foreach ( self::vehicles() as $row ) {
			if ( (string) $row['date'] > $date ) {
				$date   = (string) $row['date'];
				$newest = (int) $row['id'];
			}
		}
		return $newest;
	}

	/**
	 * Does the attribute name a vehicle? Empty and 0 (the attribute mapper's "none") do not.
	 *
	 * @param mixed $attribute The `vehicle_id` attribute.
	 */
	public static function names_vehicle( $attribute ): bool {
		$attribute = is_scalar( $attribute ) ? trim( (string) $attribute ) : '';
		return '' !== $attribute && '0' !== $attribute;
	}

	/**
	 * The vehicles this calendar offers: public, rentable and not out of order.
	 * A closed vehicle is not listed, so it can not be chosen.
	 *
	 * @return list<array{id: int, title: string, date: string, price_html: string}>
	 */
	public static function vehicles(): array {
		$posts = get_posts(
			VehicleDataHelper::restrict_query_to_service(
				array(
					'post_type'    => 'mhmrentiva_vehicle',
					'post_status'  => 'publish',
					'has_password' => false,
					'numberposts'  => -1,
					'orderby'      => 'title',
					'order'        => 'ASC',
				),
				'rental'
			)
		);

		$rows = array();
		foreach ( $posts as $post ) {
			if ( 'active' !== VehicleDataHelper::get_status( (int) $post->ID ) ) {
				continue;
			}
			$rows[] = array(
				'id'         => (int) $post->ID,
				'title'      => (string) $post->post_title,
				'date'       => (string) $post->post_date_gmt,
				'price_html' => self::price_html( (int) $post->ID ),
			);
		}
		return $rows;
	}

	/**
	 * Image, name and daily price of the vehicle card; also the vehicle endpoint's answer.
	 *
	 * @return array{id: int, title: string, image: string, price_html: string, bookable: bool}
	 */
	public static function vehicle_card( int $vehicle_id ): array {
		$image = (string) get_the_post_thumbnail_url( $vehicle_id, 'large' );

		return array(
			'id'         => $vehicle_id,
			'title'      => (string) get_post_field( 'post_title', $vehicle_id ),
			'image'      => '' !== $image ? $image : VehiclesList::get_vehicle_image( $vehicle_id ),
			'price_html' => self::price_html( $vehicle_id ),
			'bookable'   => 'active' === VehicleDataHelper::get_status( $vehicle_id ),
		);
	}

	/**
	 * The months from $first on: booked days from the one rule, once for the span.
	 *
	 * @param array{week_start?: int, selected?: list<string>, now?: int|null} $options `now` only for tests.
	 * @return list<array<string, mixed>>
	 */
	public static function months( int $vehicle_id, string $first, int $count, array $options = array() ): array {
		$now   = $options['now'] ?? null;
		$utc   = new \DateTimeZone( 'UTC' );
		$start = new \DateTimeImmutable( self::normalize_month( $first, $now ) . '-01', $utc );
		$count = max( 1, min( $count, self::MAX_MONTH_SPAN ) );
		$last  = $start->modify( '+' . ( $count - 1 ) . ' months' );

		$booked = BookingConflicts::calendar_days( $vehicle_id, $start->format( 'Y-m-d' ), $last->format( 'Y-m-t' ) );
		$today  = SiteTime::today( $now );

		$months = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$month    = $start->modify( '+' . $i . ' months' );
			$months[] = RvsCalendar::month(
				(int) $month->format( 'Y' ),
				(int) $month->format( 'n' ),
				$booked,
				$today,
				$options['selected'] ?? array(),
				array(
					'week_start'  => $options['week_start'] ?? null,
					'interactive' => true,
				)
			);
		}
		return $months;
	}

	/**
	 * The selection panel's answer, from the vehicle page's booking card (the
	 * same total, rules and link). A window that cannot be booked has no total.
	 *
	 * @return array{priced: bool, days: int, total_html: string, available: bool, message: string, continue_url: string, range_label: string, time_hint: string}
	 */
	public static function quote( int $vehicle_id, string $start, string $end ): array {
		if ( ! self::is_date( $start ) || ! self::is_date( $end ) ) {
			return self::refusal( '', __( 'Invalid date selection. Please check your pickup and return dates.', 'mhm-rentiva' ) );
		}

		$window = VehicleDetailsView::window(
			array(
				'pickup_date' => $start,
				'return_date' => $end,
			)
		);
		$range  = sprintf(
			/* translators: 1: pickup date, 2: return date. */
			__( '%1$s – %2$s', 'mhm-rentiva' ),
			self::date_text( $window['pickup_date'] ),
			self::date_text( $window['return_date'] )
		);

		if ( array() !== BookingConflicts::calendar_days( $vehicle_id, $window['pickup_date'], $window['return_date'] ) ) {
			return self::refusal( $range, __( 'The dates you selected include a booked day.', 'mhm-rentiva' ) );
		}

		$booking = VehicleDetailsView::booking( $vehicle_id, array(), $window );
		if ( empty( $booking['priced'] ) || empty( $booking['available'] ) ) {
			return self::refusal( $range, (string) $booking['message'] );
		}

		$days  = (int) $booking['days'];
		$total = (array) $booking['breakdown']['total'];

		return array(
			'priced'       => true,
			'days'         => $days,
			'total_html'   => (string) $total['html'] . (string) $total['suffix'],
			'available'    => true,
			'message'      => '',
			'continue_url' => (string) $booking['continue_url'],
			/* translators: %d: number of rental days. */
			'range_label'  => $range . ' · ' . sprintf( _n( '%d day', '%d days', $days, 'mhm-rentiva' ), $days ),
			'time_hint'    => self::time_hint( $window['pickup_time'] ),
		);
	}

	/**
	 * A real `Y-m` from the supported range, else the site's current month.
	 */
	public static function normalize_month( string $month, ?int $now = null ): string {
		if ( 1 === preg_match( '/^(\d{4})-(\d{2})$/', $month, $parts ) ) {
			$year = (int) $parts[1];
			if ( $year >= 2000 && $year <= 2100 && checkdate( (int) $parts[2], 1, $year ) ) {
				return $month;
			}
		}
		return self::current_month( $now );
	}

	/**
	 * The month a calendar opens on: never one that has passed (R9).
	 */
	public static function opening_month( string $month, ?int $now = null ): string {
		$current = self::current_month( $now );
		$month   = self::normalize_month( $month, $now );
		return $month < $current ? $current : $month;
	}

	/**
	 * The site's current month, not UTC's.
	 *
	 * @param int|null $now Only for tests.
	 */
	public static function current_month( ?int $now = null ): string {
		return substr( SiteTime::today( $now ), 0, 7 );
	}

	/**
	 * Can the visitor step back from this month? Past months stay closed (R9).
	 */
	public static function prev_allowed( string $first, ?int $now = null ): bool {
		return $first > self::current_month( $now );
	}

	/**
	 * Months to show, in the supported span.
	 *
	 * @param mixed $value The attribute.
	 */
	public static function span( $value ): int {
		return max( 1, min( is_scalar( $value ) ? (int) $value : 1, self::MAX_MONTH_SPAN ) );
	}

	/**
	 * Off for the values a toggle writes when it is off; a missing flag never gets here.
	 *
	 * @param mixed $value The attribute.
	 */
	public static function is_on( $value ): bool {
		return ! in_array( $value, array( '0', 0, false, 'false', 'no', 'off', '' ), true );
	}

	/**
	 * @param mixed $value The `start_week_on` attribute; 1 = Monday, 0 = Sunday.
	 */
	private static function week_start( $value ): int {
		return is_scalar( $value ) && '' !== (string) $value ? (int) $value : 1;
	}

	private static function is_offered( int $vehicle_id ): bool {
		return $vehicle_id > 0
			&& VehicleDataHelper::is_publicly_readable( $vehicle_id )
			&& VehicleDataHelper::allows_service( $vehicle_id, 'rental' )
			&& 'active' === VehicleDataHelper::get_status( $vehicle_id );
	}

	/**
	 * Daily price with WooCommerce's suffix, or the card's wording without a price.
	 */
	private static function price_html( int $vehicle_id ): string {
		if ( VehicleDataHelper::get_price_per_day( $vehicle_id ) <= 0 ) {
			return esc_html__( 'Price on request', 'mhm-rentiva' );
		}
		$daily = PriceDisplay::for_shop( RentalPricing::quote_for_days( $vehicle_id, 1, 0 )->rental_amount );

		return $daily['html'] . $daily['suffix'] . ' ' . esc_html__( 'per day', 'mhm-rentiva' );
	}

	/**
	 * @return array{priced: bool, days: int, total_html: string, available: bool, message: string, continue_url: string, range_label: string, time_hint: string}
	 */
	private static function refusal( string $range, string $message ): array {
		return array(
			'priced'       => false,
			'days'         => 0,
			'total_html'   => '',
			'available'    => false,
			'message'      => $message,
			'continue_url' => '',
			'range_label'  => $range,
			'time_hint'    => '',
		);
	}

	private static function is_date( string $date ): bool {
		$parsed = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date, new \DateTimeZone( 'UTC' ) );
		return false !== $parsed && $parsed->format( 'Y-m-d' ) === $date;
	}

	/**
	 * A calendar day in the site's date format; a day, not a moment, so in UTC.
	 */
	private static function date_text( string $date ): string {
		$utc = new \DateTimeZone( 'UTC' );
		return (string) wp_date( (string) get_option( 'date_format' ), ( new \DateTimeImmutable( $date . ' 12:00:00', $utc ) )->getTimestamp(), $utc );
	}

	private static function time_hint( string $time ): string {
		$utc = new \DateTimeZone( 'UTC' );
		return sprintf(
			/* translators: %s: pickup and return time. */
			__( 'Pickup and return at %s', 'mhm-rentiva' ),
			(string) wp_date( (string) get_option( 'time_format' ), ( new \DateTimeImmutable( '2000-01-01 ' . $time . ':00', $utc ) )->getTimestamp(), $utc )
		);
	}
}
