<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\ListTable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VehicleColumns {


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
		add_filter( 'manage_vehicle_posts_columns', array( self::class, 'columns' ) );
		add_action( 'manage_vehicle_posts_custom_column', array( self::class, 'render' ), 10, 2 );
		add_filter( 'manage_edit-vehicle_sortable_columns', array( self::class, 'sortable' ) );
		add_action( 'pre_get_posts', array( self::class, 'apply_sorting' ) );
		add_action( 'restrict_manage_posts', array( self::class, 'availability_filter' ) );
		add_action( 'pre_get_posts', array( self::class, 'apply_availability_filter' ) );

		// Add custom columns for quick editing
		add_action( 'quick_edit_custom_box', array( self::class, 'quick_edit_fields' ), 10, 2 );
		add_action( 'save_post', array( self::class, 'save_quick_edit' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_scripts' ) );

		// Cache clearing hooks
		add_action( 'save_post_vehicle', array( self::class, 'clear_vehicle_cache' ) );
		add_action( 'delete_post', array( self::class, 'clear_vehicle_cache_on_delete' ) );
		add_action( 'save_post_vehicle_booking', array( self::class, 'clear_vehicle_cache' ) );

		// Add statistics cards
		add_action( 'admin_notices', array( self::class, 'add_vehicle_stats_cards' ) );

		// Add monthly reservation calendar
		add_action( 'admin_notices', array( self::class, 'add_monthly_calendar' ) );
	}

	public static function columns( array $cols ): array {
		// Keep title; move date column to end
		$date = $cols['date'] ?? null;
		unset( $cols['date'] );

		$cols['mhm_license_plate'] = __( 'License Plate', 'mhm-rentiva' );
		$cols['mhm_price_per_day'] = __( 'Price/Day', 'mhm-rentiva' );
		$cols['mhm_seats']         = __( 'Seats', 'mhm-rentiva' );
		$cols['mhm_transmission']  = __( 'Transmission', 'mhm-rentiva' );
		$cols['mhm_fuel_type']     = __( 'Fuel', 'mhm-rentiva' );
		$cols['mhm_available']     = __( 'Available', 'mhm-rentiva' );

		if ( $date !== null ) {
			$cols['date'] = $date;
		}
		return $cols;
	}

	public static function render( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'mhm_license_plate':
				$v = get_post_meta( $post_id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_LICENSE_PLATE, true );
				echo ! empty( $v ) ? esc_html( $v ) : '—';
				break;

			case 'mhm_price_per_day':
				$v = get_post_meta( $post_id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_PRICE_PER_DAY, true );
				$n = is_numeric( $v ) ? (int) $v : null;
				if ( $n !== null ) {
					$currency_symbol = \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_symbol();
					echo esc_html( number_format_i18n( $n, 0 ) . ' ' . $currency_symbol );
				} else {
					echo '—';
				}
				break;

			case 'mhm_seats':
				$v = (int) get_post_meta( $post_id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_SEATS, true );
				echo $v > 0 ? esc_html( (string) $v ) : '—';
				break;

			case 'mhm_transmission':
				$map = \MHMRentiva\Admin\Vehicle\Meta\VehicleMeta::get_transmission_types();
				$v   = (string) get_post_meta( $post_id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_TRANSMISSION, true );
				echo isset( $map[ $v ] ) ? esc_html( $map[ $v ] ) : '—';
				break;

			case 'mhm_fuel_type':
				$map = \MHMRentiva\Admin\Vehicle\Meta\VehicleMeta::get_fuel_types();
				$v   = (string) get_post_meta( $post_id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_FUEL_TYPE, true );
				echo isset( $map[ $v ] ) ? esc_html( $map[ $v ] ) : '—';
				break;

			case 'mhm_available':
				// Check new meta key first
				$v = get_post_meta( $post_id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_STATUS, true );

				// Use old meta key if new one is empty
				if ( empty( $v ) ) {
					$v = get_post_meta( $post_id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_AVAILABILITY, true );
				}

				// Backward compatibility - use normalize function
				$v = self::normalize_availability( $v );

				// UI Consistency: Use CSS variables
				$status_config = array(
					'active'      => array(
						'color' => 'var(--mhm-success-color, #28a745)',
						'icon'  => '✅',
						'class' => 'status-active',
					),
					'inactive'    => array(
						'color' => 'var(--mhm-danger-color, #dc3545)',
						'icon'  => '❌',
						'class' => 'status-inactive',
					),
					'maintenance' => array(
						'color' => 'var(--mhm-warning-color, #ffc107)',
						'icon'  => '🔧',
						'class' => 'status-maintenance',
					),
				);

				$config = $status_config[ $v ] ?? array(
					'color' => 'var(--mhm-muted-color, #6c757d)',
					'icon'  => '',
					'class' => 'status-default',
				);
				$label  = self::get_vehicle_status_values()[ $v ] ?? '—';

				echo '<span class="vehicle-status ' . esc_attr( $config['class'] ) . '" data-status="' . esc_attr( $v ) . '" style="color: ' . esc_attr( $config['color'] ) . '; font-weight: bold;">';
				echo $config['icon'] ? esc_html( $config['icon'] ) . ' ' : '';
				echo esc_html( $label );
				echo '</span>';
				break;
		}
	}

	public static function sortable( array $cols ): array {
		$cols['mhm_price_per_day'] = 'mhm_price_per_day';
		$cols['mhm_seats']         = 'mhm_seats';
		return $cols;
	}

	public static function apply_sorting( \WP_Query $q ): void {
		if ( ! is_admin() || ! $q->is_main_query() ) {
			return;
		}
		if ( ( $q->get( 'post_type' ) ?? '' ) !== 'vehicle' ) {
			return;
		}
		$orderby = $q->get( 'orderby' );
		if ( $orderby === 'mhm_price_per_day' ) {
			$q->set( 'meta_key', \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_PRICE_PER_DAY );
			$q->set( 'orderby', 'meta_value_num' );
		} elseif ( $orderby === 'mhm_seats' ) {
			$q->set( 'meta_key', \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_SEATS );
			$q->set( 'orderby', 'meta_value_num' );
		}
	}

	public static function availability_filter( string $post_type ): void {
		if ( $post_type !== 'vehicle' ) {
			return;
		}

		// Security: Sanitize GET parameter
		$current = '';
		if ( isset( $_GET['mhm_available'] ) ) {
			$current = self::sanitize_text_field_safe( wp_unslash( $_GET['mhm_available'] ) );
		}

		// Dynamic status values
		$status_values = self::get_vehicle_status_values();
		$legacy_values = self::get_legacy_status_values();

		echo '<select name="mhm_available" class="postform">';
		echo '  <option value="">' . esc_html__( 'All availability statuses', 'mhm-rentiva' ) . '</option>';

		// New status values
		foreach ( $status_values as $value => $label ) {
			echo '  <option value="' . esc_attr( $value ) . '"' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}

		echo '</select>';
	}

	public static function apply_availability_filter( \WP_Query $q ): void {
		if ( ! is_admin() || ! $q->is_main_query() ) {
			return;
		}
		if ( ( $q->get( 'post_type' ) ?? '' ) !== 'vehicle' ) {
			return;
		}
		if ( ! isset( $_GET['mhm_available'] ) || $_GET['mhm_available'] === '' ) {
			return;
		}

		// Security: Sanitize GET parameter
		$val = self::sanitize_text_field_safe( wp_unslash( $_GET['mhm_available'] ) );

		// Dynamic status values validation
		$status_values  = array_keys( self::get_vehicle_status_values() );
		$legacy_values  = array_keys( self::get_legacy_status_values() );
		$allowed_values = array_merge( $status_values, $legacy_values );

		if ( ! in_array( $val, $allowed_values, true ) ) {
			return;
		}

		// Permission check
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$q->set(
			'meta_query',
			array(
				array(
					'key'     => \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_AVAILABILITY,
					'value'   => $val,
					'compare' => '=',
				),
			)
		);
	}

	/**
	 * Enqueue scripts
	 */
	public static function enqueue_scripts( string $hook ): void {
		global $post_type;

		if ( $post_type === 'vehicle' && $hook === 'edit.php' ) {
			wp_enqueue_script(
				'mhm-vehicle-quick-edit',
				MHM_RENTIVA_PLUGIN_URL . 'assets/js/components/vehicle-quick-edit.js',
				array( 'jquery' ),
				MHM_RENTIVA_VERSION,
				true
			);

			// Load statistics cards CSS
			wp_enqueue_style(
				'mhm-stats-cards',
				MHM_RENTIVA_PLUGIN_URL . 'assets/css/components/stats-cards.css',
				array(),
				MHM_RENTIVA_VERSION
			);

			// Load calendar CSS
			wp_enqueue_style(
				'mhm-calendars',
				MHM_RENTIVA_PLUGIN_URL . 'assets/css/components/calendars.css',
				array(),
				MHM_RENTIVA_VERSION
			);
		}
	}

	/**
	 * Add vehicle statistics cards
	 */
	public static function add_vehicle_stats_cards(): void {
		global $pagenow, $post_type;

		// Show only on vehicle list page
		if ( $pagenow !== 'edit.php' || $post_type !== 'vehicle' ) {
			return;
		}

		// Add page header
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php echo esc_html__( 'Vehicles', 'mhm-rentiva' ); ?></h1>
			<hr class="wp-header-end">
		</div>

		<?php
		// Display Developer Mode banner and limit notices
		\MHMRentiva\Admin\Core\ProFeatureNotice::displayDeveloperModeAndLimits(
			'vehicles',
			array(
				__( 'Unlimited Vehicles', 'mhm-rentiva' ),
				__( 'Advanced Vehicle Management', 'mhm-rentiva' ),
			)
		);

		// Get statistics data
		$stats = self::get_vehicle_stats();

		?>
		<div class="mhm-stats-cards">
			<div class="stats-grid">
				<!-- Reserved Vehicles -->
				<div class="stat-card stat-card-reserved">
					<div class="stat-icon">
						<span class="dashicons dashicons-calendar-alt"></span>
					</div>
					<div class="stat-content">
						<div class="stat-number"><?php echo esc_html( $stats['reserved'] ); ?></div>
						<div class="stat-label"><?php esc_html_e( 'Monthly Reserved Vehicles', 'mhm-rentiva' ); ?></div>
						<div class="stat-trend">
							<span class="trend-text"><?php echo esc_html( $stats['reserved_all_time'] ?? 0 ); ?> <?php esc_html_e( 'total', 'mhm-rentiva' ); ?></span>
						</div>
					</div>
				</div>

				<!-- Inactive Vehicles -->
				<div class="stat-card stat-card-passive">
					<div class="stat-icon">
						<span class="dashicons dashicons-dismiss"></span>
					</div>
					<div class="stat-content">
						<div class="stat-number"><?php echo esc_html( $stats['passive'] ?? 0 ); ?></div>
						<div class="stat-label"><?php esc_html_e( 'Inactive Vehicles', 'mhm-rentiva' ); ?></div>
						<div class="stat-trend">
							<span class="trend-text"><?php echo esc_html( $stats['passive_this_month'] ?? 0 ); ?> <?php esc_html_e( 'This month', 'mhm-rentiva' ); ?></span>
						</div>
					</div>
				</div>

				<!-- Maintenance Vehicles -->
				<div class="stat-card stat-card-maintenance">
					<div class="stat-icon">
						<span class="dashicons dashicons-hammer"></span>
					</div>
					<div class="stat-content">
						<div class="stat-number"><?php echo esc_html( $stats['maintenance'] ?? 0 ); ?></div>
						<div class="stat-label"><?php esc_html_e( 'Vehicles Under Maintenance', 'mhm-rentiva' ); ?></div>
						<div class="stat-trend">
							<span class="trend-text"><?php echo esc_html( $stats['maintenance_this_month'] ?? 0 ); ?> <?php esc_html_e( 'This month', 'mhm-rentiva' ); ?></span>
						</div>
					</div>
				</div>

				<!-- Monthly Revenue Average -->
				<div class="stat-card stat-card-revenue">
					<div class="stat-icon">
						<span class="dashicons dashicons-money-alt"></span>
					</div>
					<div class="stat-content">
						<div class="stat-number"><?php echo esc_html( self::format_currency( (float) ( $stats['monthly_avg_revenue'] ?? 0 ) ) ); ?></div>
						<div class="stat-label"><?php esc_html_e( 'Avg. Monthly Revenue', 'mhm-rentiva' ); ?></div>
						<div class="stat-trend">
							<span class="trend-text <?php echo ( $stats['revenue_trend'] ?? 0 ) >= 0 ? 'trend-up' : 'trend-down'; ?>">
								<?php echo ( $stats['revenue_trend'] ?? 0 ) >= 0 ? '+' : ''; ?><?php echo esc_html( $stats['revenue_trend'] ?? 0 ); ?>% <?php esc_html_e( 'vs last month', 'mhm-rentiva' ); ?>
							</span>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get vehicle statistics data - Optimized pivot query
	 */
	private static function get_vehicle_stats(): array {
		global $wpdb;

		// Remove cache completely - get real data
		// $cache_key = 'mhm_vehicle_stats_' . get_current_user_id();
		// $stats = get_transient($cache_key);

		// if ($stats !== false && is_array($stats)) {
		// return $stats;
		// }

		// Debug: Check all vehicles and their statuses
		// Note: This is a static query with no user input, but using prepare() for best practice
		$all_vehicles = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT v.ID, v.post_title, pm_status.meta_value as status
            FROM {$wpdb->posts} v
            LEFT JOIN {$wpdb->postmeta} pm_status ON v.ID = pm_status.post_id AND pm_status.meta_key = %s
            WHERE v.post_type = %s AND v.post_status = %s
            ORDER BY v.ID
        ",
				\MHMRentiva\Admin\Core\MetaKeys::VEHICLE_STATUS,
				'vehicle',
				'publish'
			)
		);

		// Debug log removed

		// Optimized pivot query - all statistics in single query
		$vehicle_stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
                COUNT(DISTINCT v.ID) as total_vehicles,
                COUNT(DISTINCT CASE WHEN (pm_status.meta_value = 'inactive' OR (pm_status.meta_value IS NULL AND pm_legacy.meta_value = 'passive')) THEN v.ID END) as passive,
                COUNT(DISTINCT CASE WHEN (pm_status.meta_value = 'maintenance' OR (pm_status.meta_value IS NULL AND pm_legacy.meta_value = 'maintenance')) THEN v.ID END) as maintenance,
                COUNT(DISTINCT CASE WHEN (pm_status.meta_value = 'inactive' OR (pm_status.meta_value IS NULL AND pm_legacy.meta_value = 'passive')) AND v.post_date >= %s THEN v.ID END) as passive_this_month,
                COUNT(DISTINCT CASE WHEN (pm_status.meta_value = 'maintenance' OR (pm_status.meta_value IS NULL AND pm_legacy.meta_value = 'maintenance')) AND v.post_date >= %s THEN v.ID END) as maintenance_this_month,
                COUNT(DISTINCT pm_booking.meta_value) as reserved,
                COUNT(DISTINCT CASE WHEN b.post_date >= %s THEN pm_booking.meta_value END) as reserved_this_week
             FROM {$wpdb->posts} v
             LEFT JOIN {$wpdb->postmeta} pm_status ON v.ID = pm_status.post_id AND pm_status.meta_key = %s
             LEFT JOIN {$wpdb->postmeta} pm_legacy ON v.ID = pm_legacy.post_id AND pm_legacy.meta_key = %s
             LEFT JOIN {$wpdb->posts} b ON b.post_type = %s AND b.post_status = %s
             LEFT JOIN {$wpdb->postmeta} pm_booking ON b.ID = pm_booking.post_id AND pm_booking.meta_key = %s
             WHERE v.post_type = %s AND v.post_status = %s",
				gmdate( 'Y-m-01' ), // passive_this_month
				gmdate( 'Y-m-01' ), // maintenance_this_month
				gmdate( 'Y-m-d', strtotime( '-7 days' ) ), // reserved_this_week
				\MHMRentiva\Admin\Core\MetaKeys::VEHICLE_STATUS,
				\MHMRentiva\Admin\Core\MetaKeys::VEHICLE_AVAILABILITY,
				'vehicle_booking',
				'publish',
				\MHMRentiva\Admin\Core\MetaKeys::BOOKING_VEHICLE_ID,
				'vehicle',
				'publish'
			)
		);

		$total_vehicles         = (int) ( $vehicle_stats->total_vehicles ?? 0 );
		$reserved_all_time      = (int) ( $vehicle_stats->reserved ?? 0 );
		$passive                = (int) ( $vehicle_stats->passive ?? 0 );
		$maintenance            = (int) ( $vehicle_stats->maintenance ?? 0 );
		$reserved_this_week     = (int) ( $vehicle_stats->reserved_this_week ?? 0 );
		$passive_this_month     = (int) ( $vehicle_stats->passive_this_month ?? 0 );
		$maintenance_this_month = (int) ( $vehicle_stats->maintenance_this_month ?? 0 );

		// Calculate reserved vehicles for current month (similar to dashboard logic)
		$current_month_start = gmdate( 'Y-m-01 00:00:00' );
		$current_month_end   = gmdate( 'Y-m-t 23:59:59' );
		$month_start_ts      = strtotime( $current_month_start );
		$month_end_ts        = strtotime( $current_month_end );

		// Get bookings with date overlaps for current month
		// Note: We fetch all active bookings and check date overlaps in PHP
		// This ensures we catch bookings that overlap with current month regardless of when they were created
		// No user input in this query, but using esc_sql for best practice
		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT pm_vehicle.meta_value as vehicle_id,
						pm_pickup.meta_value as pickup_date,
						COALESCE(pm_return1.meta_value, pm_return2.meta_value, pm_return3.meta_value) as return_date
				 FROM {$wpdb->posts} b
				 INNER JOIN {$wpdb->postmeta} pm_vehicle ON b.ID = pm_vehicle.post_id AND pm_vehicle.meta_key = %s
				 INNER JOIN {$wpdb->postmeta} pm_pickup ON b.ID = pm_pickup.post_id AND pm_pickup.meta_key = %s
				 LEFT JOIN {$wpdb->postmeta} pm_return1 ON b.ID = pm_return1.post_id AND pm_return1.meta_key = %s
				 LEFT JOIN {$wpdb->postmeta} pm_return2 ON b.ID = pm_return2.post_id AND pm_return2.meta_key = %s
				 LEFT JOIN {$wpdb->postmeta} pm_return3 ON b.ID = pm_return3.post_id AND pm_return3.meta_key = %s
				 INNER JOIN {$wpdb->postmeta} pm_status ON b.ID = pm_status.post_id AND pm_status.meta_key = %s
				 WHERE b.post_type = %s
				 AND b.post_status = %s
				 AND pm_status.meta_value IN ('confirmed', 'active', 'pending')
				 AND pm_vehicle.meta_value IS NOT NULL AND pm_vehicle.meta_value != ''
				 AND pm_pickup.meta_value IS NOT NULL AND pm_pickup.meta_value != ''
				 AND (pm_return1.meta_value IS NOT NULL OR pm_return2.meta_value IS NOT NULL OR pm_return3.meta_value IS NOT NULL)",
				'_mhm_vehicle_id',
				'_mhm_pickup_date',
				'_mhm_return_date',
				'_mhm_dropoff_date',
				'_mhm_end_date',
				'_mhm_status',
				'vehicle_booking',
				'publish'
			)
		);

		$reserved_vehicle_ids_this_month = array();
		if ( $bookings ) {
			foreach ( $bookings as $booking ) {
				$pickup_ts = strtotime( $booking->pickup_date );
				$return_ts = strtotime( $booking->return_date );

				if ( $pickup_ts === false || $return_ts === false ) {
					continue;
				}

				// Check if booking overlaps with current month
				$overlaps = ( $pickup_ts <= $month_end_ts && $return_ts >= $month_start_ts );

				if ( $overlaps ) {
					$reserved_vehicle_ids_this_month[] = (int) $booking->vehicle_id;
				}
			}
		}

		$reserved_this_month = count( array_unique( $reserved_vehicle_ids_this_month ) );

		// Debug: Log vehicle stats in detail
		// Debug log removed

		// Monthly revenue average - use real reservation data
		$monthly_avg_revenue = 0;
		$revenue_trend       = 0;

		if ( $total_vehicles > 0 ) {
			// This month's revenue - ONLY COMPLETED AND CONFIRMED RESERVATIONS
			$current_month_revenue = (float) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2))) 
                 FROM {$wpdb->posts} p 
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
                 WHERE p.post_type = %s AND p.post_status = %s 
                 AND pm.meta_key = %s
                 AND pm_status.meta_key = '_mhm_status'
                 AND pm_status.meta_value IN ('completed', 'confirmed')
                 AND p.post_date >= %s",
					'vehicle_booking',
					'publish',
					'_mhm_total_price',
					gmdate( 'Y-m-01' )
				)
			);

			// Last month's revenue (for trend calculation)
			$last_month_start = gmdate( 'Y-m-01', strtotime( '-1 month' ) );
			$last_month_end   = gmdate( 'Y-m-t', strtotime( '-1 month' ) );

			$last_month_revenue = (float) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2))) 
                 FROM {$wpdb->posts} p 
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
                 WHERE p.post_type = %s AND p.post_status = %s 
                 AND pm.meta_key = %s
                 AND pm_status.meta_key = '_mhm_status'
                 AND pm_status.meta_value IN ('completed', 'confirmed')
                 AND p.post_date >= %s AND p.post_date <= %s",
					'vehicle_booking',
					'publish',
					'_mhm_total_price',
					$last_month_start,
					$last_month_end . ' 23:59:59'
				)
			);

			// Debug log removed

			// Monthly revenue average = this month's revenue / number of vehicles
			$monthly_avg_revenue = $current_month_revenue;

			// Trend calculation
			if ( $last_month_revenue > 0 ) {
				$revenue_trend = round( ( ( $current_month_revenue - $last_month_revenue ) / $last_month_revenue ) * 100 );
			} else {
				$revenue_trend = $current_month_revenue > 0 ? 100 : 0;
			}
		}

		$stats = array(
			'reserved'               => $reserved_this_month, // Monthly reserved (for display)
			'reserved_all_time'      => $reserved_all_time, // Total reserved (for trend text)
			'passive'                => $passive,
			'maintenance'            => $maintenance,
			'reserved_this_week'     => $reserved_this_week,
			'passive_this_month'     => $passive_this_month,
			'maintenance_this_month' => $maintenance_this_month,
			'monthly_avg_revenue'    => (float) ( $monthly_avg_revenue ?? 0 ),
			'revenue_trend'          => (float) ( $revenue_trend ?? 0 ),
		);

		// Debug: Log final stats
		// Debug log removed

		// Remove cache completely - get real data
		// set_transient($cache_key, $stats, 5 * MINUTE_IN_SECONDS);

		return $stats;
	}

	/**
	 * Add monthly reservation calendar
	 */
	public static function add_monthly_calendar(): void {
		global $pagenow, $post_type;

		// Show only on vehicle list page
		if ( $pagenow !== 'edit.php' || $post_type !== 'vehicle' ) {
			return;
		}

		// Security: Sanitize URL parameters
		$current_month = (int) gmdate( 'n' );
		$current_year  = (int) gmdate( 'Y' );

		if ( isset( $_GET['month'] ) ) {
			$month = (int) self::sanitize_text_field_safe( wp_unslash( $_GET['month'] ) );
			if ( $month >= 1 && $month <= 12 ) {
				$current_month = $month;
			}
		}

		if ( isset( $_GET['year'] ) ) {
			$year = (int) self::sanitize_text_field_safe( wp_unslash( $_GET['year'] ) );
			if ( $year >= 2020 && $year <= 2030 ) {
				$current_year = $year;
			}
		}

		// Dynamic month names (i18n supported)
		$month_names = array(
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

		// Get vehicles
		$vehicles = self::get_calendar_vehicles();

		// Get reservation data
		$bookings = self::get_monthly_bookings( $current_month, $current_year );

		?>
		<div class="mhm-calendars">
			<!-- Calendar Header -->
			<div class="calendar-header">
				<h2><?php esc_html_e( 'Monthly Booking Calendar', 'mhm-rentiva' ); ?></h2>

				<!-- Month Navigation -->
				<div class="calendar-navigation">
					<?php
					$prev_month = $current_month == 1 ? 12 : $current_month - 1;
					$prev_year  = $current_month == 1 ? $current_year - 1 : $current_year;
					$next_month = $current_month == 12 ? 1 : $current_month + 1;
					$next_year  = $current_month == 12 ? $current_year + 1 : $current_year;
					?>

					<a href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'month' => $prev_month,
								'year'  => $prev_year,
							)
						)
					);
					?>
								"
						class="calendar-nav-btn prev-btn" data-action="prev">
						<span class="dashicons dashicons-arrow-left-alt2"></span>
						<?php echo esc_html( $month_names[ $prev_month ] ); ?>
					</a>

					<div class="calendar-current">
						<strong><?php echo esc_html( $month_names[ $current_month ] . ' ' . $current_year ); ?></strong>
					</div>

					<a href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'month' => $next_month,
								'year'  => $next_year,
							)
						)
					);
					?>
								"
						class="calendar-nav-btn next-btn" data-action="next">
						<?php echo esc_html( $month_names[ $next_month ] ); ?>
						<span class="dashicons dashicons-arrow-right-alt2"></span>
					</a>
				</div>
			</div>

			<!-- Calendar Table -->
			<div class="calendar-container">
				<div class="calendar-table-wrapper">
					<table class="calendar-table">
						<thead>
							<tr>
								<th class="vehicle-column"><?php esc_html_e( 'Vehicles', 'mhm-rentiva' ); ?></th>
								<?php
								// Create days of the month
								$days_in_month = cal_days_in_month( CAL_GREGORIAN, $current_month, $current_year );
								for ( $day = 1; $day <= $days_in_month; $day++ ) {
									echo '<th class="day-header">' . esc_html( $day ) . '</th>';
								}
								?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $vehicles as $vehicle ) : ?>
								<tr>
									<td class="vehicle-info">
										<div class="vehicle-name"><?php echo esc_html( $vehicle['title'] ); ?></div>
										<div class="vehicle-plate"><?php echo esc_html( $vehicle['plate'] ); ?></div>
									</td>
									<?php
									// Check reservation status for each day
									for ( $day = 1; $day <= $days_in_month; $day++ ) {
										$date      = sprintf( '%04d-%02d-%02d', $current_year, $current_month, $day );
										$is_booked = isset( $bookings[ $vehicle['id'] ][ $date ] );

										$class = $is_booked ? 'day-cell booked' : 'day-cell available';

										if ( $is_booked ) {
											$booking_data = $bookings[ $vehicle['id'] ][ $date ];
											/* translators: %s: customer name. */
											$title = sprintf( esc_attr__( 'Reserved: %s', 'mhm-rentiva' ), $booking_data['customer_name'] );

											// Status-based color system
											$status        = $booking_data['status'] ?? 'pending';
											$status_colors = array(
												'pending' => 'status-pending',      // 🟡 Yellow
												'confirmed' => 'status-confirmed',  // 🟢 Green
												'completed' => 'status-completed',  // 🔵 Blue
												'cancelled' => 'status-cancelled',   // 🔴 Red
											);

											$status_class = $status_colors[ $status ] ?? 'status-pending';
											$class        = 'day-cell booked ' . $status_class;

											// Get translated status label
											$status_label = \MHMRentiva\Admin\Booking\Core\Status::get_label( $status );

											// Data attributes for popup
											$data_attrs = sprintf(
												'data-booking-id="%s" data-customer-name="%s" data-customer-email="%s" data-customer-phone="%s" data-total-price="%s" data-status="%s" data-status-label="%s" data-start-date="%s" data-end-date="%s" data-created-date="%s"',
												esc_attr( $booking_data['booking_id'] ),
												esc_attr( $booking_data['customer_name'] ),
												esc_attr( $booking_data['customer_email'] ),
												esc_attr( $booking_data['customer_phone'] ),
												esc_attr( $booking_data['total_price'] ),
												esc_attr( $booking_data['status'] ),
												esc_attr( $status_label ),
												esc_attr( $booking_data['start_date'] ),
												esc_attr( $booking_data['end_date'] ),
												esc_attr( $booking_data['created_date'] )
											);

											// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes are escaped dynamically
											echo '<td class="' . esc_attr( $class ) . '" title="' . esc_attr( $title ) . '" ' . $data_attrs . ' data-booking-popup>';
										} else {
											$title = esc_attr__( 'Available', 'mhm-rentiva' );
											echo '<td class="' . esc_attr( $class ) . '" title="' . esc_attr( $title ) . '">';
										}

										echo $is_booked ? '<span class="dashicons dashicons-calendar-alt booking-icon"></span>' : '';
										echo '</td>';
									}
									?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>

			<!-- Status Color Information -->
			<div class="calendar-legend">
				<h4><?php esc_html_e( 'Status Legend', 'mhm-rentiva' ); ?></h4>
				<div class="legend-items">
					<div class="legend-item">
						<span class="legend-color status-pending"></span>
						<span class="legend-label"><?php esc_html_e( 'Pending', 'mhm-rentiva' ); ?></span>
					</div>
					<div class="legend-item">
						<span class="legend-color status-confirmed"></span>
						<span class="legend-label"><?php esc_html_e( 'Confirmed', 'mhm-rentiva' ); ?></span>
					</div>
					<div class="legend-item">
						<span class="legend-color status-completed"></span>
						<span class="legend-label"><?php esc_html_e( 'Completed', 'mhm-rentiva' ); ?></span>
					</div>
				</div>
			</div>
		</div>

		<!-- Booking Popup Modal -->
		<div id="mhm-booking-popup" class="mhm-popup-modal" style="display: none;">
			<div class="mhm-popup-overlay"></div>
			<div class="mhm-popup-content">
				<div class="mhm-popup-header">
					<h3><?php esc_html_e( 'Booking Details', 'mhm-rentiva' ); ?></h3>
					<button class="mhm-popup-close" type="button">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
				<div class="mhm-popup-body">
					<div class="booking-info-grid">
						<div class="info-item">
							<label><?php esc_html_e( 'Customer Name:', 'mhm-rentiva' ); ?></label>
							<span id="popup-customer-name">—</span>
						</div>
						<div class="info-item">
							<label><?php esc_html_e( 'Email:', 'mhm-rentiva' ); ?></label>
							<span id="popup-customer-email">—</span>
						</div>
						<div class="info-item">
							<label><?php esc_html_e( 'Phone:', 'mhm-rentiva' ); ?></label>
							<span id="popup-customer-phone">—</span>
						</div>
						<div class="info-item">
							<label><?php esc_html_e( 'Start Date:', 'mhm-rentiva' ); ?></label>
							<span id="popup-start-date">—</span>
						</div>
						<div class="info-item">
							<label><?php esc_html_e( 'End Date:', 'mhm-rentiva' ); ?></label>
							<span id="popup-end-date">—</span>
						</div>
						<div class="info-item">
							<label><?php esc_html_e( 'Total Price:', 'mhm-rentiva' ); ?></label>
							<span id="popup-total-price">—</span>
						</div>
						<div class="info-item">
							<label><?php esc_html_e( 'Status:', 'mhm-rentiva' ); ?></label>
							<span id="popup-status">—</span>
						</div>
						<div class="info-item">
							<label><?php esc_html_e( 'Created:', 'mhm-rentiva' ); ?></label>
							<span id="popup-created-date">—</span>
						</div>
					</div>
				</div>
				<div class="mhm-popup-footer">
					<button class="button button-primary" id="popup-edit-booking" type="button">
						<?php esc_html_e( 'Edit Booking', 'mhm-rentiva' ); ?>
					</button>
					<button class="button button-secondary mhm-popup-close">
						<?php esc_html_e( 'Close', 'mhm-rentiva' ); ?>
					</button>
				</div>
			</div>
		</div>

		<script>
			jQuery(document).ready(function($) {
				// Open popup
				$('[data-booking-popup]').on('click', function(e) {
					e.preventDefault();

					var $this = $(this);
					var bookingId = $this.data('booking-id');
					var customerName = $this.data('customer-name');
					var customerEmail = $this.data('customer-email');
					var customerPhone = $this.data('customer-phone');
					var totalPrice = $this.data('total-price');
					var status = $this.data('status');
					var statusLabel = $this.data('status-label') || status; // Use translated label if available
					var startDate = $this.data('start-date');
					var endDate = $this.data('end-date');
					var createdDate = $this.data('created-date');

					// Fill popup content
					$('#popup-customer-name').text(customerName || '—');
					$('#popup-customer-email').text(customerEmail || '—');
					$('#popup-customer-phone').text(customerPhone || '—');
					$('#popup-start-date').text(startDate || '—');
					$('#popup-end-date').text(endDate || '—');
					$('#popup-total-price').text(totalPrice ? totalPrice + ' €' : '—');
					$('#popup-status').text(statusLabel || '—');
					$('#popup-created-date').text(createdDate || '—');

					// Add Edit booking link as click event
					$('#popup-edit-booking').off('click').on('click', function(e) {
						e.preventDefault();
						if (bookingId) {
							window.location.href = 'post.php?post=' + bookingId + '&action=edit';
						}
					});

					// Show popup
					$('#mhm-booking-popup').fadeIn(300);
				});

				// Close popup
				$('.mhm-popup-close, .mhm-popup-overlay').on('click', function() {
					$('#mhm-booking-popup').fadeOut(300);
				});

				// Close with ESC key
				$(document).on('keydown', function(e) {
					if (e.keyCode === 27) {
						$('#mhm-booking-popup').fadeOut(300);
					}
				});
			});
		</script>

		<style>
			/* Popup Styles */
			.mhm-popup-modal {
				position: fixed;
				top: 0;
				left: 0;
				width: 100%;
				height: 100%;
				z-index: 9999;
			}

			.mhm-popup-overlay {
				position: absolute;
				top: 0;
				left: 0;
				width: 100%;
				height: 100%;
				background: rgba(0, 0, 0, 0.7);
			}

			.mhm-popup-content {
				position: absolute;
				top: 50%;
				left: 50%;
				transform: translate(-50%, -50%);
				background: #fff;
				border-radius: 8px;
				box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
				max-width: 600px;
				width: 90%;
				max-height: 80vh;
				overflow-y: auto;
			}

			.mhm-popup-header {
				padding: 20px;
				border-bottom: 1px solid #ddd;
				display: flex;
				justify-content: space-between;
				align-items: center;
			}

			.mhm-popup-header h3 {
				margin: 0;
				color: #333;
			}

			.mhm-popup-close {
				background: none;
				border: none;
				font-size: 20px;
				cursor: pointer;
				color: #666;
			}

			.mhm-popup-close:hover {
				color: #000;
			}

			.mhm-popup-body {
				padding: 20px;
			}

			.booking-info-grid {
				display: grid;
				grid-template-columns: 1fr 1fr;
				gap: 15px;
			}

			.info-item {
				display: flex;
				flex-direction: column;
			}

			.info-item label {
				font-weight: bold;
				color: #555;
				margin-bottom: 5px;
			}

			.info-item span {
				color: #333;
				padding: 5px 0;
			}

			.mhm-popup-footer {
				padding: 20px;
				border-top: 1px solid #ddd;
				display: flex;
				gap: 10px;
				justify-content: flex-end;
			}

			/* Booking icon hover effect */
			.booking-icon {
				cursor: pointer;
				transition: color 0.2s ease;
			}

			.booking-icon:hover {
				color: #0073E6;
			}

			[data-booking-popup] {
				cursor: pointer;
			}

			[data-booking-popup]:hover {
				background-color: rgba(0, 115, 230, 0.1);
			}

			/* Calendar Status Colors */
			.day-cell.booked.status-pending {
				background-color: #ffc107 !important;
				color: #000;
			}

			.day-cell.booked.status-confirmed {
				background-color: #28a745 !important;
				color: #fff;
			}

			.day-cell.booked.status-in-progress {
				background-color: #fd7e14 !important;
				color: #fff;
			}

			.day-cell.booked.status-completed {
				background-color: #0073E6 !important;
				color: #fff;
			}

			.day-cell.booked.status-cancelled {
				background-color: #dc3545 !important;
				color: #fff;
			}

			/* Legend Styles */
			.calendar-legend {
				margin-top: 20px;
				padding: 15px;
				background: #f8f9fa;
				border-radius: 8px;
				border: 1px solid #dee2e6;
			}

			.calendar-legend h4 {
				margin: 0 0 10px 0;
				color: #333;
				font-size: 14px;
			}

			.legend-items {
				display: flex;
				flex-wrap: wrap;
				gap: 15px;
			}

			.legend-item {
				display: flex;
				align-items: center;
				gap: 8px;
			}

			.legend-color {
				width: 16px;
				height: 16px;
				border-radius: 3px;
				display: inline-block;
			}

			.legend-color.status-pending {
				background-color: #ffc107;
			}

			.legend-color.status-confirmed {
				background-color: #28a745;
			}

			.legend-color.status-in-progress {
				background-color: #fd7e14;
			}

			.legend-color.status-completed {
				background-color: #0073E6;
			}

			.legend-color.status-cancelled {
				background-color: #dc3545;
			}

			.legend-label {
				font-size: 12px;
				color: #555;
			}
		</style>
		<?php
	}

	/**
	 * Get vehicles for calendar
	 */
	private static function get_calendar_vehicles(): array {
		global $wpdb;

		$vehicles = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, pm.meta_value as plate
             FROM {$wpdb->posts} p 
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
             WHERE p.post_type = %s AND p.post_status = %s
             ORDER BY p.post_title ASC",
				'_mhm_rentiva_license_plate',
				'vehicle',
				'publish'
			)
		);

		$result = array();
		foreach ( $vehicles as $vehicle ) {
			$result[] = array(
				'id'    => $vehicle->ID,
				'title' => $vehicle->post_title,
				'plate' => $vehicle->plate ?: '—',
			);
		}

		return $result;
	}

	/**
	 * Get monthly reservation data
	 */
	private static function get_monthly_bookings( int $month, int $year ): array {
		global $wpdb;

		$start_date = sprintf( '%04d-%02d-01', $year, $month );
		$end_date   = sprintf( '%04d-%02d-%02d', $year, $month, cal_days_in_month( CAL_GREGORIAN, $month, $year ) );

		// Use same meta keys as dashboard - detailed data for popup
		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT 
                p.ID as booking_id,
                p.post_title as booking_title,
                pm_vehicle.meta_value as vehicle_id,
                pm_start.meta_value as start_date,
                pm_end.meta_value as end_date,
                pm_customer.meta_value as customer_name,
                pm_customer_email.meta_value as customer_email,
                pm_customer_phone.meta_value as customer_phone,
                pm_total_price.meta_value as total_price,
                pm_status.meta_value as status,
                p.post_date as created_date
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_vehicle ON p.ID = pm_vehicle.post_id 
                AND pm_vehicle.meta_key = '_mhm_vehicle_id'
            LEFT JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id 
                AND pm_start.meta_key = '_mhm_pickup_date'
            LEFT JOIN {$wpdb->postmeta} pm_end ON p.ID = pm_end.post_id 
                AND pm_end.meta_key = '_mhm_dropoff_date'
            LEFT JOIN {$wpdb->postmeta} pm_customer ON p.ID = pm_customer.post_id 
                AND pm_customer.meta_key = '_mhm_customer_name'
            LEFT JOIN {$wpdb->postmeta} pm_customer_email ON p.ID = pm_customer_email.post_id 
                AND pm_customer_email.meta_key = '_mhm_customer_email'
            LEFT JOIN {$wpdb->postmeta} pm_customer_phone ON p.ID = pm_customer_phone.post_id 
                AND pm_customer_phone.meta_key = '_mhm_customer_phone'
            LEFT JOIN {$wpdb->postmeta} pm_total_price ON p.ID = pm_total_price.post_id 
                AND pm_total_price.meta_key = '_mhm_total_price'
            LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id 
                AND pm_status.meta_key = '_mhm_status'
            WHERE p.post_type = 'vehicle_booking'
                AND p.post_status = 'publish'
                AND pm_start.meta_value <= %s
                AND pm_end.meta_value >= %s
                AND pm_vehicle.meta_value IS NOT NULL
                AND pm_status.meta_value IN ('pending', 'confirmed', 'completed')
        ",
				$end_date,
				$start_date
			)
		);

		$result = array();
		foreach ( $bookings as $booking ) {
			if ( ! $booking->vehicle_id || ! $booking->start_date || ! $booking->end_date ) {
				continue;
			}

			// Get customer info using BookingQueryHelper (handles WooCommerce & WordPress integration)
			$customer_info = array();
			if ( class_exists( '\\MHMRentiva\\Admin\\Core\\Utilities\\BookingQueryHelper' ) ) {
				$customer_info = \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::getBookingCustomerInfo( (int) $booking->booking_id );
			}

			// Build customer name from first_name and last_name
			$customer_name = '';
			if ( ! empty( $customer_info['first_name'] ) && ! empty( $customer_info['last_name'] ) ) {
				$customer_name = trim( $customer_info['first_name'] . ' ' . $customer_info['last_name'] );
			} elseif ( ! empty( $customer_info['first_name'] ) ) {
				$customer_name = $customer_info['first_name'];
			} elseif ( ! empty( $customer_info['last_name'] ) ) {
				$customer_name = $customer_info['last_name'];
			}

			// Fallback to SQL result if BookingQueryHelper didn't find anything
			if ( empty( $customer_name ) ) {
				$customer_name = $booking->customer_name ?: '';
			}

			// Use customer info from BookingQueryHelper (prioritizes WooCommerce/WordPress data)
			$customer_email = ! empty( $customer_info['email'] ) ? $customer_info['email'] : ( $booking->customer_email ?: '' );
			$customer_phone = ! empty( $customer_info['phone'] ) ? $customer_info['phone'] : ( $booking->customer_phone ?: '' );

			// Normalize date format
			$start_date = self::normalize_date( $booking->start_date );
			$end_date   = self::normalize_date( $booking->end_date );

			if ( ! $start_date || ! $end_date ) {
				continue;
			}

			// Mark each day in the date range
			$current = new \DateTime( $start_date );
			$end     = new \DateTime( $end_date );

			while ( $current <= $end ) {
				$date                                    = $current->format( 'Y-m-d' );
				$result[ $booking->vehicle_id ][ $date ] = array(
					'customer_name'  => $customer_name ?: __( 'Reserved', 'mhm-rentiva' ),
					'booking_id'     => $booking->booking_id,
					'booking_title'  => $booking->booking_title,
					'customer_email' => $customer_email,
					'customer_phone' => $customer_phone,
					'total_price'    => $booking->total_price,
					'status'         => $booking->status,
					'start_date'     => $start_date,
					'end_date'       => $end_date,
					'created_date'   => $booking->created_date,
				);
				$current->add( new \DateInterval( 'P1D' ) );
			}
		}

		return $result;
	}

	/**
	 * Normalize date to YYYY-MM-DD format
	 */
	private static function normalize_date( string $date ): ?string {
		if ( empty( $date ) ) {
			return null;
		}

		// If already in YYYY-MM-DD format
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return $date;
		}

		// If in DD.MM.YYYY format
		if ( preg_match( '/^\d{2}\.\d{2}\.\d{4}$/', $date ) ) {
			$parts = explode( '.', $date );
			return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
		}

		// Try other formats
		$timestamp = strtotime( $date );
		if ( $timestamp !== false ) {
			return gmdate( 'Y-m-d', $timestamp );
		}

		return null;
	}

	/**
	 * Add quick edit fields
	 */
	public static function quick_edit_fields( string $column_name, string $post_type ): void {
		if ( $post_type !== 'vehicle' ) {
			return;
		}

		static $nonce_added = false;
		if ( ! $nonce_added ) {
			wp_nonce_field( 'mhm_vehicle_quick_edit', 'mhm_vehicle_quick_edit_nonce' );
			$nonce_added = true;
		}

		switch ( $column_name ) {
			case 'mhm_license_plate':
				echo '<fieldset class="inline-edit-col-left">';
				echo '<div class="inline-edit-col">';
				echo '<label>';
				echo '<span class="title">' . esc_html__( 'License Plate', 'mhm-rentiva' ) . '</span>';
				echo '<input type="text" name="mhm_license_plate" class="mhm_license_plate" value="" />';
				echo '</label>';
				echo '</div>';
				echo '</fieldset>';
				break;

			case 'mhm_price_per_day':
				echo '<fieldset class="inline-edit-col-left">';
				echo '<div class="inline-edit-col">';
				echo '<label>';
				echo '<span class="title">' . esc_html__( 'Price/Day', 'mhm-rentiva' ) . '</span>';
				echo '<input type="number" name="mhm_price_per_day" class="mhm_price_per_day" value="" step="1" min="0" />';
				echo '</label>';
				echo '</div>';
				echo '</fieldset>';
				break;

			case 'mhm_seats':
				$max_seats = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_rentiva_vehicle_max_seats', 100 );
				echo '<fieldset class="inline-edit-col-left">';
				echo '<div class="inline-edit-col">';
				echo '<label>';
				echo '<span class="title">' . esc_html__( 'Seats', 'mhm-rentiva' ) . '</span>';
				echo '<input type="number" name="mhm_seats" class="mhm_seats" value="" min="1" max="' . esc_attr( $max_seats ) . '" />';
				echo '</label>';
				echo '</div>';
				echo '</fieldset>';
				break;

			case 'mhm_transmission':
				$transmission_types = \MHMRentiva\Admin\Vehicle\Meta\VehicleMeta::get_transmission_types();
				echo '<fieldset class="inline-edit-col-left">';
				echo '<div class="inline-edit-col">';
				echo '<label>';
				echo '<span class="title">' . esc_html__( 'Transmission', 'mhm-rentiva' ) . '</span>';
				echo '<select name="mhm_transmission" class="mhm_transmission">';
				foreach ( $transmission_types as $type_key => $type_label ) {
					echo '<option value="' . esc_attr( $type_key ) . '">' . esc_html( $type_label ) . '</option>';
				}
				echo '</select>';
				echo '</label>';
				echo '</div>';
				echo '</fieldset>';
				break;

			case 'mhm_fuel_type':
				$fuel_types = \MHMRentiva\Admin\Vehicle\Meta\VehicleMeta::get_fuel_types();
				echo '<fieldset class="inline-edit-col-left">';
				echo '<div class="inline-edit-col">';
				echo '<label>';
				echo '<span class="title">' . esc_html__( 'Fuel', 'mhm-rentiva' ) . '</span>';
				echo '<select name="mhm_fuel_type" class="mhm_fuel_type">';
				foreach ( $fuel_types as $fuel_key => $fuel_label ) {
					echo '<option value="' . esc_attr( $fuel_key ) . '">' . esc_html( $fuel_label ) . '</option>';
				}
				echo '</select>';
				echo '</label>';
				echo '</div>';
				echo '</fieldset>';
				break;

			case 'mhm_available':
				echo '<fieldset class="inline-edit-col-left">';
				echo '<div class="inline-edit-col">';
				echo '<label>';
				echo '<span class="title">' . esc_html__( 'Available', 'mhm-rentiva' ) . '</span>';
				echo '<select name="mhm_available" class="mhm_available">';

				// Dynamic status values
				$status_values = self::get_vehicle_status_values();
				foreach ( $status_values as $value => $label ) {
					echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
				}

				// Legacy values (backward compatibility)
				echo '</select>';
				echo '</label>';
				echo '</div>';
				echo '</fieldset>';
				break;
		}
	}

	/**
	 * Save quick edit data
	 */
	public static function save_quick_edit( int $post_id ): void {
		// Security: Nonce check
		if (
			! isset( $_POST['mhm_vehicle_quick_edit_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mhm_vehicle_quick_edit_nonce'] ) ), 'mhm_vehicle_quick_edit' )
		) {
			return;
		}

		// Permission check
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Post type check
		if ( get_post_type( $post_id ) !== 'vehicle' ) {
			return;
		}

		// Autosave and revision check
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Save meta values securely
		$meta_fields = array(
			'mhm_license_plate' => array(
				'key'      => '_mhm_rentiva_license_plate',
				'sanitize' => 'sanitize_text_field',
			),
			'mhm_price_per_day' => array(
				'key'      => '_mhm_rentiva_price_per_day',
				'sanitize' => 'floatval',
			),
			'mhm_seats'         => array(
				'key'      => '_mhm_rentiva_seats',
				'sanitize' => 'intval',
			),
			'mhm_transmission'  => array(
				'key'      => '_mhm_rentiva_transmission',
				'sanitize' => 'sanitize_text_field',
			),
			'mhm_fuel_type'     => array(
				'key'      => '_mhm_rentiva_fuel_type',
				'sanitize' => 'sanitize_text_field',
			),
			'mhm_available'     => array(
				'key'      => '_mhm_vehicle_availability',
				'sanitize' => 'sanitize_text_field',
			),
		);

		foreach ( $meta_fields as $field_name => $config ) {
			if ( ! isset( $_POST[ $field_name ] ) ) {
				continue;
			}

			$value = wp_unslash( $_POST[ $field_name ] );

			if ( $value === null ) {
				$value = '';
			}

			if ( $config['sanitize'] === 'sanitize_text_field' ) {
				$sanitized_value = sanitize_text_field( (string) ( $value ?: '' ) );
			} else {
				$sanitized_value = call_user_func( $config['sanitize'], $value );
			}

			if ( $field_name === 'mhm_available' ) {
				$normalized_status = self::normalize_availability( $sanitized_value );
				update_post_meta( $post_id, '_mhm_vehicle_status', $normalized_status );
				update_post_meta( $post_id, '_mhm_vehicle_availability', $normalized_status );
				continue;
			}

			if ( $sanitized_value !== '' && $sanitized_value !== null ) {
				update_post_meta( $post_id, $config['key'], $sanitized_value );
			}
		}
	}



	/**
	 * Format currency (same as dashboard)
	 */
	private static function format_currency( float $amount ): string {
		// Same format as dashboard: 2 decimal, dot separator
		$formatted = number_format( $amount, 2, '.', ',' );
		return $formatted . ' ' . \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_symbol();
	}

	/**
	 * Get vehicle status values (dynamic)
	 */
	private static function get_vehicle_status_values(): array {
		return array(
			'active'      => __( 'Active', 'mhm-rentiva' ),
			'maintenance' => __( 'Maintenance', 'mhm-rentiva' ),
		);
	}

	/**
	 * Get legacy status values (backward compatibility)
	 */
	private static function get_legacy_status_values(): array {
		return array(
			'passive' => __( 'Passive', 'mhm-rentiva' ),
		);
	}

	/**
	 * Normalize availability value (backward compatibility)
	 */
	private static function normalize_availability( $value ): string {
		$status_values = array_keys( self::get_vehicle_status_values() );
		// Old format: '1' -> 'active', '0' -> 'passive'
		if ( $value === '1' ) {
			return 'active';
		}
		if ( $value === '0' ) {
			return 'passive';
		}

		// New format: 'active', 'inactive', 'maintenance'
		if ( in_array( $value, $status_values, true ) ) {
			return $value;
		}

		// Old format: 'passive' -> 'inactive'
		if ( $value === 'passive' ) {
			return 'inactive';
		}

		// Default
		return 'active';
	}

	/**
	 * Clear cache when vehicle changes
	 */
	public static function clear_vehicle_cache( int $post_id ): void {
		if ( get_post_type( $post_id ) === 'vehicle' ) {
			self::clear_vehicle_stats_cache();
		}
	}

	/**
	 * Clear cache when vehicle is deleted
	 */
	public static function clear_vehicle_cache_on_delete( int $post_id ): void {
		if ( get_post_type( $post_id ) === 'vehicle' ) {
			self::clear_vehicle_stats_cache();
		}
	}

	/**
	 * Clear all vehicle statistics caches
	 */
	public static function clear_vehicle_stats_cache(): void {
		global $wpdb;

		// Clear vehicle stats caches for all users
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_mhm_vehicle_stats_%'
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_timeout_mhm_vehicle_stats_%'
			)
		);
	}
}
