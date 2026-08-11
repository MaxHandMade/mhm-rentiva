<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Core\ListTable;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Core\Utilities\BookingQueryHelper;
use MHMRentiva\Admin\Core\Utilities\OccupancyMapService;
use MHMRentiva\Admin\Vehicle\Meta\BlockedDatesMetaBox;
use MHMRentiva\Helpers\Html;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared fleet occupancy calendar matrix.
 *
 * Faz 2 Task 4: the renderer both the Vehicles Calendar face (this task)
 * and, from Task 5, the Bookings Calendar face paint through. Replaces the
 * two near-duplicate monthly-calendar renderers that used to live inline in
 * VehicleColumns and BookingColumns.
 *
 * ONE `OccupancyMapService::get_map()` call per render, ONE
 * `update_meta_cache('post', ...)` call for the row vehicles' blocked-dates
 * meta, and ONE more for every booking touched by a painted cell — no
 * per-row or per-cell SQL (the WooCommerce fallback inside
 * `BookingQueryHelper::getBookingCustomerInfo()` is a declared exception:
 * it only fires for WC-born bookings).
 *
 * @since 6.1.0
 */
final class FleetOccupancyMatrix {


	/**
	 * `OccupancyMapService::reduce()` winners mapped to their cell class.
	 * `in_progress` gets its OWN class instead of falling back to
	 * `status-pending` the way the old vehicle calendar's color map did —
	 * the CSS for it lands in Task 8.
	 */
	private const STATUS_CLASSES = array(
		'pending'     => 'status-pending',
		'confirmed'   => 'status-confirmed',
		'in_progress' => 'status-in_progress',
		'completed'   => 'status-completed',
	);

	/**
	 * Render the matrix for a set of vehicle rows.
	 *
	 * @param \WP_Post[]           $vehicles Vehicle posts to render as rows — caller owns
	 *                                       selection/pagination.
	 * @param int                  $month    1-12.
	 * @param int                  $year     Full year.
	 * @param array{show_plate?: bool, enable_block_toggle?: bool, filter_statuses?: string[], screen?: string} $opts Rendering options.
	 */
	public static function render( array $vehicles, int $month, int $year, array $opts = array() ): void {
		$opts = wp_parse_args(
			$opts,
			array(
				'show_plate'          => true,
				'enable_block_toggle' => false,
				'filter_statuses'     => array(),
				'screen'              => 'vehicles',
			)
		);

		$vehicles = array_values( array_filter( $vehicles, static function ( $vehicle ): bool {
			return $vehicle instanceof \WP_Post;
		} ) );

		$calendar_date = new \DateTimeImmutable(
			sprintf( '%04d-%02d-01', $year, $month ),
			new \DateTimeZone( 'UTC' )
		);
		$days_in_month = (int) $calendar_date->format( 't' );
		$first_day     = $calendar_date->format( 'Y-m-d' );
		$last_day      = sprintf( '%04d-%02d-%02d', $year, $month, $days_in_month );

		$vehicle_ids = array();
		foreach ( $vehicles as $vehicle ) {
			$vehicle_ids[] = (int) $vehicle->ID;
		}
		if ( ! empty( $vehicle_ids ) ) {
			// Bulk-prime blocked-dates meta for every row vehicle — the
			// per-vehicle get_blocked_dates() calls in the loop below then
			// hit the warm cache instead of issuing their own query each.
			update_meta_cache( 'post', $vehicle_ids );
		}

		$map = OccupancyMapService::get_map( $first_day, $last_day );

		$filter_statuses = array_values(
			array_filter( array_map( 'strval', (array) $opts['filter_statuses'] ) )
		);

		// Pass 1 (no SQL): decide, per vehicle/day, what paints — blocked
		// dates first, then the filtered+reduced booking status — and
		// collect the booking IDs any painted cell will need fields for.
		$blocked_map     = array();
		$painted         = array();
		$all_booking_ids = array();

		foreach ( $vehicle_ids as $vehicle_id ) {
			$blocked_map[ $vehicle_id ] = BlockedDatesMetaBox::get_blocked_dates( $vehicle_id );

			for ( $day = 1; $day <= $days_in_month; $day++ ) {
				$date = sprintf( '%04d-%02d-%02d', $year, $month, $day );
				if ( in_array( $date, $blocked_map[ $vehicle_id ], true ) ) {
					continue; // Blocked beats booking; painted at render time.
				}

				$entries = $map[ $vehicle_id ][ $date ] ?? array();
				if ( ! empty( $filter_statuses ) ) {
					// Filter FIRST, then reduce — the raw per-cell entry
					// list survives the filter, so a filtered view still
					// reflects only the statuses it was asked to show.
					$entries = array_values(
						array_filter(
							$entries,
							static function ( array $entry ) use ( $filter_statuses ): bool {
								return in_array( (string) ( $entry['status'] ?? '' ), $filter_statuses, true );
							}
						)
					);
				}

				$status = OccupancyMapService::reduce( $entries );
				if ( '' === $status ) {
					continue;
				}

				// The entry whose status matches the winning reduced status
				// decides the cell's PAINT color and the single-booking
				// fallback attrs; the popup itself must list EVERY booking
				// on this cell (post-filter), not just the winner — that is
				// what the shared partial's #popup-multi-view exists for.
				$winner = null;
				foreach ( $entries as $entry ) {
					if ( ( $entry['status'] ?? '' ) === $status ) {
						$winner = $entry;
						break;
					}
				}

				$painted[ $vehicle_id ][ $date ] = array(
					'status'     => $status,
					'booking_id' => $winner ? (int) $winner['booking_id'] : 0,
					'entries'    => $entries,
				);
				foreach ( $entries as $entry ) {
					$all_booking_ids[] = (int) $entry['booking_id'];
				}
			}
		}

		$all_booking_ids = array_values( array_unique( $all_booking_ids ) );
		if ( ! empty( $all_booking_ids ) ) {
			// Meta cache for build_booking_fields()'s get_post_meta() reads,
			// PLUS the post object cache — get_post_field('post_date', ...)
			// below hits wp_cache_get('posts', ...) internally, which the
			// meta-cache prime does NOT populate. Without this, every
			// distinct booking triggers its own SELECT (caught by the
			// query-budget test after it was strengthened to clean the post
			// cache before measuring, instead of relying on the fixture
			// factory's incidental cache warm).
			update_meta_cache( 'post', $all_booking_ids );
			_prime_post_caches( $all_booking_ids, false, false );
		}

		$can_see_pii    = self::can_see_customer_pii();
		$booking_fields = array();
		foreach ( $all_booking_ids as $booking_id ) {
			// $can_see_pii travels INTO the field builder, not just around
			// its result: without it the customer lookup ran for every
			// booking on the screen and its answer was then thrown away —
			// PII read (and, through BookingQueryHelper's wc_get_order() /
			// get_userdata() fallbacks, queried per booking) for a user who
			// is not allowed to see it. "Never read" is the boundary; "read
			// then drop" is not.
			$booking_fields[ $booking_id ] = self::build_booking_fields( $booking_id, $can_see_pii );
		}

		self::render_markup( $vehicles, $month, $year, $days_in_month, $blocked_map, $painted, $booking_fields, $can_see_pii, $opts );
	}

	/**
	 * Whether the current user may see other people's booking customers.
	 *
	 * Derived from the BOOKING post type's own capability mapping rather
	 * than asked of core's `edit_others_posts`: bookings map every
	 * capability to `manage_options`, but the Vehicles CPT uses the default
	 * `post` caps — so a stock Editor, who holds core `edit_others_posts`,
	 * can open the Vehicles screen, switch to the Calendar face and read
	 * every customer's name/e-mail/phone out of the HTML source. The gate
	 * must be the ceiling of the DATA (bookings), not of the screen.
	 *
	 * Falls back to `manage_options` if the type is not registered yet —
	 * the same value the mapping resolves to, so an early call cannot be
	 * looser than a late one.
	 */
	private static function can_see_customer_pii(): bool {
		$booking_type = get_post_type_object( 'mhmrentiva_booking' );
		$capability   = ( $booking_type instanceof \WP_Post_Type && isset( $booking_type->cap->edit_others_posts ) )
			? (string) $booking_type->cap->edit_others_posts
			: 'manage_options';

		return current_user_can( $capability );
	}

	/**
	 * Read one booking's popup fields, relying entirely on the meta cache
	 * primed by the caller (no query per booking, WC fallback exception
	 * inside BookingQueryHelper aside).
	 *
	 * @param bool $can_see_pii When false the customer lookup is skipped
	 *                          ENTIRELY — the customer fields come back
	 *                          empty and no WC/user query is ever issued.
	 * @return array{customer_name:string,customer_email:string,customer_phone:string,total_price:string,start_date:string,end_date:string,start_time:string,end_time:string,created_date:string}
	 */
	private static function build_booking_fields( int $booking_id, bool $can_see_pii ): array {
		$customer_info = array();
		if ( $can_see_pii && class_exists( BookingQueryHelper::class ) ) {
			$customer_info = BookingQueryHelper::getBookingCustomerInfo( $booking_id );
		}

		$customer_name = '';
		if ( ! empty( $customer_info['first_name'] ) && ! empty( $customer_info['last_name'] ) ) {
			$customer_name = trim( $customer_info['first_name'] . ' ' . $customer_info['last_name'] );
		} elseif ( ! empty( $customer_info['first_name'] ) ) {
			$customer_name = $customer_info['first_name'];
		} elseif ( ! empty( $customer_info['last_name'] ) ) {
			$customer_name = $customer_info['last_name'];
		}

		return array(
			'customer_name'  => '' !== $customer_name ? $customer_name : __( 'Reserved', 'mhm-rentiva' ),
			'customer_email' => (string) ( $customer_info['email'] ?? '' ),
			'customer_phone' => (string) ( $customer_info['phone'] ?? '' ),
			'total_price'    => self::first_meta( $booking_id, array( MetaKeys::BOOKING_TOTAL_PRICE ) ),
			// Same fallback chain as OccupancyMapService::get_map()'s SQL —
			// one definition of "where a booking's dates live", read here
			// in PHP instead of a JOIN.
			'start_date'     => self::first_meta( $booking_id, array( MetaKeys::BOOKING_PICKUP_DATE, '_mhmrentiva_booking_pickup_date' ) ),
			'end_date'       => self::first_meta( $booking_id, array( MetaKeys::BOOKING_DROPOFF_DATE, MetaKeys::BOOKING_RETURN_DATE, MetaKeys::BOOKING_END_DATE ) ),
			'start_time'     => self::first_meta( $booking_id, array( MetaKeys::BOOKING_PICKUP_TIME ) ),
			'end_time'       => self::first_meta( $booking_id, array( MetaKeys::BOOKING_RETURN_TIME ) ),
			'created_date'   => (string) get_post_field( 'post_date', $booking_id ),
		);
	}

	/**
	 * First non-empty postmeta value across a fallback key list.
	 *
	 * @param string[] $keys
	 */
	private static function first_meta( int $post_id, array $keys ): string {
		foreach ( $keys as $key ) {
			$value = get_post_meta( $post_id, $key, true );
			if ( '' !== $value && null !== $value ) {
				return (string) $value;
			}
		}
		return '';
	}

	/**
	 * @param \WP_Post[]                          $vehicles
	 * @param array<int, string[]>                $blocked_map
	 * @param array<int, array<string, array{status:string,booking_id:int,entries:list<array{booking_id:int,status:string}>}>> $painted
	 * @param array<int, array<string, string>>   $booking_fields
	 * @param array{show_plate: bool, enable_block_toggle: bool, filter_statuses: string[], screen: string} $opts
	 */
	private static function render_markup( array $vehicles, int $month, int $year, int $days_in_month, array $blocked_map, array $painted, array $booking_fields, bool $can_see_pii, array $opts ): void {
		$month_names = self::month_names();
		?>
		<div class="mhm-calendars mhm-occupancy-matrix-wrap" data-screen="<?php echo esc_attr( $opts['screen'] ); ?>">
			<div class="calendar-header">
				<h2><?php esc_html_e( 'Monthly Occupancy Calendar', 'mhm-rentiva' ); ?></h2>

				<div class="calendar-navigation">
					<?php self::render_nav_links( $month, $year, $month_names ); ?>
				</div>
			</div>

			<div class="calendar-container">
				<div class="calendar-table-wrapper">
					<table class="calendar-table mhm-occupancy-matrix">
						<thead>
							<tr>
								<th class="vehicle-column"><?php esc_html_e( 'Vehicles', 'mhm-rentiva' ); ?></th>
								<?php for ( $day = 1; $day <= $days_in_month; $day++ ) : ?>
									<th class="day-header"><?php echo esc_html( (string) $day ); ?></th>
								<?php endfor; ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $vehicles as $vehicle ) : ?>
								<?php self::render_row( $vehicle, $month, $year, $days_in_month, $blocked_map[ (int) $vehicle->ID ] ?? array(), $painted[ (int) $vehicle->ID ] ?? array(), $booking_fields, $can_see_pii, $opts ); ?>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>

			<div class="calendar-legend">
				<h4><?php esc_html_e( 'Status Legend', 'mhm-rentiva' ); ?></h4>
				<div class="legend-items">
					<div class="legend-item">
						<span class="legend-color is-free"></span>
						<span class="legend-label"><?php esc_html_e( 'Available', 'mhm-rentiva' ); ?></span>
					</div>
					<div class="legend-item">
						<span class="legend-color status-pending"></span>
						<span class="legend-label"><?php echo esc_html( Status::get_label( 'pending' ) ); ?></span>
					</div>
					<div class="legend-item">
						<span class="legend-color status-confirmed"></span>
						<span class="legend-label"><?php echo esc_html( Status::get_label( 'confirmed' ) ); ?></span>
					</div>
					<div class="legend-item">
						<span class="legend-color status-in_progress"></span>
						<span class="legend-label"><?php echo esc_html( Status::get_label( 'in_progress' ) ); ?></span>
					</div>
					<div class="legend-item">
						<span class="legend-color status-completed"></span>
						<span class="legend-label"><?php echo esc_html( Status::get_label( 'completed' ) ); ?></span>
					</div>
					<div class="legend-item">
						<span class="legend-color legend-blocked-day"></span>
						<span class="legend-label"><?php esc_html_e( 'Blocked Day', 'mhm-rentiva' ); ?></span>
					</div>
				</div>
			</div>
		</div>

		<?php include MHMRENTIVA_PLUGIN_DIR . 'templates/admin/partials/booking-popup.php'; ?>
		<?php
	}

	/**
	 * @param string[]                               $blocked_dates
	 * @param array<string, array{status:string,booking_id:int,entries:list<array{booking_id:int,status:string}>}> $painted_days
	 * @param array<int, array<string, string>>      $booking_fields
	 * @param array{show_plate: bool, enable_block_toggle: bool, filter_statuses: string[], screen: string} $opts
	 */
	private static function render_row( \WP_Post $vehicle, int $month, int $year, int $days_in_month, array $blocked_dates, array $painted_days, array $booking_fields, bool $can_see_pii, array $opts ): void {
		$vehicle_id    = (int) $vehicle->ID;
		$vehicle_title = get_the_title( $vehicle );
		$vehicle_plate = (string) get_post_meta( $vehicle_id, MetaKeys::VEHICLE_LICENSE_PLATE, true );
		?>
		<tr>
			<td class="vehicle-info">
				<div class="vehicle-name"><?php echo esc_html( $vehicle_title ); ?></div>
				<?php if ( $opts['show_plate'] ) : ?>
					<div class="vehicle-plate"><?php echo esc_html( '' !== $vehicle_plate ? $vehicle_plate : '—' ); ?></div>
				<?php endif; ?>
			</td>
			<?php for ( $day = 1; $day <= $days_in_month; $day++ ) : ?>
				<?php
				$date       = sprintf( '%04d-%02d-%02d', $year, $month, $day );
				$is_blocked = in_array( $date, $blocked_dates, true );
				$cell       = $painted_days[ $date ] ?? null;
				?>
				<?php if ( $is_blocked ) : ?>
					<?php self::render_blocked_cell( $vehicle_id, $date, $day, $opts ); ?>
				<?php elseif ( null === $cell ) : ?>
					<?php self::render_free_cell( $vehicle_id, $date, $day, $opts ); ?>
				<?php else : ?>
					<?php self::render_booked_cell( $day, $cell, $booking_fields, $can_see_pii, $vehicle_title, $vehicle_plate ); ?>
				<?php endif; ?>
			<?php endfor; ?>
		</tr>
		<?php
	}

	/**
	 * @param array{show_plate: bool, enable_block_toggle: bool, filter_statuses: string[], screen: string} $opts
	 */
	private static function render_blocked_cell( int $vehicle_id, string $date, int $day, array $opts ): void {
		$title = sprintf( '%s · %d', __( 'Blocked Day', 'mhm-rentiva' ), $day );
		echo '<td class="day-cell blocked-day"';
		if ( $opts['enable_block_toggle'] ) {
			echo ' data-vehicle-id="' . esc_attr( (string) $vehicle_id ) . '" data-date="' . esc_attr( $date ) . '"';
		}
		echo ' title="' . esc_attr( $title ) . '"></td>';
	}

	/**
	 * @param array{show_plate: bool, enable_block_toggle: bool, filter_statuses: string[], screen: string} $opts
	 */
	private static function render_free_cell( int $vehicle_id, string $date, int $day, array $opts ): void {
		$title = sprintf( '%s · %d', __( 'Available', 'mhm-rentiva' ), $day );
		echo '<td class="day-cell available"';
		if ( $opts['enable_block_toggle'] ) {
			echo ' data-vehicle-id="' . esc_attr( (string) $vehicle_id ) . '" data-date="' . esc_attr( $date ) . '"';
		}
		echo ' title="' . esc_attr( $title ) . '"></td>';
	}

	/**
	 * @param array{status:string,booking_id:int,entries:list<array{booking_id:int,status:string}>} $cell
	 * @param array<int, array<string, string>> $booking_fields Keyed by booking id.
	 */
	private static function render_booked_cell( int $day, array $cell, array $booking_fields, bool $can_see_pii, string $vehicle_title, string $vehicle_plate ): void {
		$status        = $cell['status'];
		$status_class  = self::STATUS_CLASSES[ $status ] ?? 'status-pending';
		$status_label  = Status::get_label( $status );
		$title         = sprintf( '%s · %d', $status_label, $day );
		$winner_fields = $booking_fields[ $cell['booking_id'] ] ?? array();

		// PII condition: customer name/email/phone (and the other booking
		// fields that only make sense alongside them) are embedded ONLY for
		// a user who can see other people's bookings; everyone else gets
		// booking id, dates and status — enough for the badge, nothing that
		// identifies the customer.
		$data_attrs = array(
			'booking-id'   => $cell['booking_id'],
			'status'       => $status,
			'status-label' => $status_label,
			'start-date'   => $winner_fields['start_date'] ?? '',
			'end-date'     => $winner_fields['end_date'] ?? '',
		);
		if ( $can_see_pii ) {
			$data_attrs['customer-name']  = $winner_fields['customer_name'] ?? '';
			$data_attrs['customer-email'] = $winner_fields['customer_email'] ?? '';
			$data_attrs['customer-phone'] = $winner_fields['customer_phone'] ?? '';
			$data_attrs['total-price']    = $winner_fields['total_price'] ?? '';
			$data_attrs['start-time']     = $winner_fields['start_time'] ?? '';
			$data_attrs['end-time']       = $winner_fields['end_time'] ?? '';
			$data_attrs['created-date']   = $winner_fields['created_date'] ?? '';
		}

		// The winner-only flat attrs above are a single-booking fallback
		// (booking-popup.js reads them only when the JSON is absent or
		// empty); the FULL list —
		// every booking this cell holds after filter_statuses — rides as
		// 'bookings' JSON, the exact shape booking-popup.js's
		// showSingleBooking()/showMultiBooking() already expect (matches the
		// contract the now-retired BookingColumns::get_booking_calendar_days()
		// used to build for the same JS to read).
		// On a day with several bookings the popup must list ALL of them,
		// not just the one whose status won the cell's paint color.
		$all_bookings = array();
		$seen         = array();
		foreach ( $cell['entries'] as $entry ) {
			$booking_id = (int) ( $entry['booking_id'] ?? 0 );
			if ( $booking_id <= 0 || isset( $seen[ $booking_id ] ) ) {
				continue;
			}
			$seen[ $booking_id ] = true;

			$fields       = $booking_fields[ $booking_id ] ?? array();
			$entry_status = (string) ( $entry['status'] ?? $status );
			$booking      = array(
				'booking_id'    => $booking_id,
				'vehicle_title' => $vehicle_title,
				'vehicle_plate' => $vehicle_plate,
				'status'        => $entry_status,
				'status_label'  => Status::get_label( $entry_status ),
				'start_date'    => $fields['start_date'] ?? '',
				'end_date'      => $fields['end_date'] ?? '',
			);
			if ( $can_see_pii ) {
				$booking['customer_name']  = $fields['customer_name'] ?? '';
				$booking['customer_email'] = $fields['customer_email'] ?? '';
				$booking['customer_phone'] = $fields['customer_phone'] ?? '';
				$booking['total_price']    = $fields['total_price'] ?? '';
				$booking['created_date']   = $fields['created_date'] ?? '';
			}
			$all_bookings[] = $booking;
		}
		$data_attrs['bookings'] = wp_json_encode( $all_bookings );

		echo '<td class="day-cell booked ' . esc_attr( $status_class ) . '" title="' . esc_attr( $title ) . '"';
		Html::echo_data_attributes( $data_attrs );
		echo ' data-booking-popup>';
		echo '<span class="dashicons dashicons-calendar-alt booking-icon"></span>';
		echo '</td>';
	}

	/**
	 * Prev/current/next month navigation. `add_query_arg()` with no base
	 * URL preserves the full current query string (mhmrentiva_view=calendar
	 * included — this only ever renders on the calendar face — plus every
	 * other active filter), overriding only mhmrentiva_month/year.
	 *
	 * @param array<int, string> $month_names
	 */
	private static function render_nav_links( int $month, int $year, array $month_names ): void {
		$prev_month = 1 === $month ? 12 : $month - 1;
		$prev_year  = 1 === $month ? $year - 1 : $year;
		$next_month = 12 === $month ? 1 : $month + 1;
		$next_year  = 12 === $month ? $year + 1 : $year;

		$prev_url = add_query_arg(
			array(
				'mhmrentiva_month' => $prev_month,
				'mhmrentiva_year'  => $prev_year,
			)
		);
		$next_url = add_query_arg(
			array(
				'mhmrentiva_month' => $next_month,
				'mhmrentiva_year'  => $next_year,
			)
		);
		?>
		<a href="<?php echo esc_url( $prev_url ); ?>" class="calendar-nav-btn prev-btn" data-action="prev">
			<span class="dashicons dashicons-arrow-left-alt2"></span>
			<?php echo esc_html( $month_names[ $prev_month ] ); ?>
		</a>

		<div class="calendar-current">
			<strong><?php echo esc_html( $month_names[ $month ] . ' ' . $year ); ?></strong>
		</div>

		<a href="<?php echo esc_url( $next_url ); ?>" class="calendar-nav-btn next-btn" data-action="next">
			<?php echo esc_html( $month_names[ $next_month ] ); ?>
			<span class="dashicons dashicons-arrow-right-alt2"></span>
		</a>
		<?php
	}

	/**
	 * @return array<int, string>
	 */
	private static function month_names(): array {
		return array(
			1  => __( 'January', 'mhm-rentiva' ),
			2  => __( 'February', 'mhm-rentiva' ),
			3  => __( 'March', 'mhm-rentiva' ),
			4  => __( 'April', 'mhm-rentiva' ),
			5  => __( 'May', 'mhm-rentiva' ),
			6  => __( 'June', 'mhm-rentiva' ),
			7  => __( 'July', 'mhm-rentiva' ),
			8  => __( 'August', 'mhm-rentiva' ),
			9  => __( 'September', 'mhm-rentiva' ),
			10 => __( 'October', 'mhm-rentiva' ),
			11 => __( 'November', 'mhm-rentiva' ),
			12 => __( 'December', 'mhm-rentiva' ),
		);
	}
}
