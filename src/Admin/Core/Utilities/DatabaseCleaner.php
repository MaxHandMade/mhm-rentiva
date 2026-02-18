<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Legacy/public hook and template naming kept for backward compatibility.

declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Textdomain is loaded automatically by WordPress since 4.6 for plugins on WordPress.org
// For custom plugins, you can still use load_plugin_textdomain in a hook if needed.


/**
 * Database Cleaner
 *
 * Cleans orphaned data, expired transients and unused meta keys
 */
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Maintenance operations require dynamic table/placeholder composition with strict internal whitelisting.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- This utility performs intentional maintenance/migration operations directly on custom tables and wp_* metadata for cleanup and recovery workflows.
final class DatabaseCleaner {



	/**
	 * Get valid meta keys list (with filter hook support)
	 *
	 * @return array<string> Array of valid meta key strings
	 */
	private static function get_valid_meta_keys(): array {
		// Base valid meta keys
		$valid_keys = array(
			// Vehicle meta keys
			'_mhm_vehicle_availability',
			'_mhm_vehicle_status',
			'_mhm_rentiva_availability', // Deprecated but still valid
			'_mhm_rentiva_price_per_day',
			'_mhm_rentiva_brand',
			'_mhm_rentiva_model',
			'_mhm_rentiva_year',
			'_mhm_rentiva_color',
			'_mhm_rentiva_seats',
			'_mhm_rentiva_doors',
			'_mhm_rentiva_transmission',
			'_mhm_rentiva_fuel_type',
			'_mhm_rentiva_engine_size',
			'_mhm_rentiva_mileage',
			'_mhm_rentiva_license_plate',
			'_mhm_rentiva_deposit',
			'_mhm_rentiva_features',
			'_mhm_rentiva_equipment',
			'_mhm_rentiva_gallery_images',
			'_mhm_rentiva_rating_average',
			'_mhm_rentiva_rating_count',

			// Booking meta keys
			'_mhm_vehicle_id',
			'_mhm_status',
			'_mhm_booking_type',
			'_mhm_created_via',
			'_mhm_created_by',
			'_mhm_booking_created',
			'_mhm_start_date',
			'_mhm_start_time',
			'_mhm_start_ts',
			'_mhm_end_date',
			'_mhm_end_time',
			'_mhm_end_ts',
			'_mhm_pickup_date',
			'_mhm_pickup_time',
			'_mhm_dropoff_date',
			'_mhm_dropoff_time',
			'_mhm_rental_days',
			'_mhm_guests',

			// Customer meta keys
			'_mhm_customer_user_id',
			'_mhm_customer_name',
			'_mhm_customer_first_name',
			'_mhm_customer_last_name',
			'_mhm_customer_email',
			'_mhm_customer_phone',

			// Payment meta keys
			'_mhm_payment_method',
			'_mhm_payment_gateway',
			'_mhm_payment_type',
			'_mhm_payment_status',
			'_mhm_payment_deadline',
			'_mhm_payment_display',
			'_mhm_total_price',
			'_mhm_deposit_amount',
			'_mhm_deposit_type',
			'_mhm_remaining_amount',
			'_mhm_selected_addons',

			// Receipt meta keys
			'_mhm_receipt_status',
			'_mhm_receipt_attachment_id',
			'_mhm_receipt_uploaded_at',
			'_mhm_receipt_uploaded_by',

			// System meta keys
			'_mhm_shortcode',
			'_mhm_auto_created',
			'_mhm_booking_history',
			'_mhm_booking_logs',
			'_mhm_cancellation_deadline',
			'_mhm_cancellation_policy',
			'_mhm_removed_details',
			'_mhm_custom_details',

			// Core Booking Meta
			'_mhm_vehicle_id',
			'_mhm_start_date',
			'_mhm_end_date',
			'_mhm_start_time',
			'_mhm_end_time',
			'_mhm_start_ts',
			'_mhm_end_ts',
			'_mhm_pickup_date',
			'_mhm_dropoff_date',
			'_mhm_pickup_time',
			'_mhm_dropoff_time',
			'_mhm_status',
			'_mhm_booking_type',
			'_mhm_created_via',
			'_mhm_created_by',
			'_mhm_booking_created',
			'_mhm_guests',

			// Customer Meta
			'_mhm_customer_user_id',
			'_mhm_customer_first_name',
			'_mhm_customer_last_name',
			'_mhm_customer_name',
			'_mhm_customer_email',
			'_mhm_customer_phone',
			'_mhm_contact_name',
			'_mhm_contact_email',
			'_mhm_contact_phone',
			'_mhm_client_ip',
			'_mhm_user_agent',
			'_mhm_customer_id',

			// Payment & Pricing Meta
			'_mhm_total_price',
			'_mhm_rental_days',
			'_mhm_deposit_amount',
			'_mhm_remaining_amount',
			'_mhm_deposit_type',
			'_mhm_payment_display',
			'_mhm_payment_type',
			'_mhm_payment_method',
			'_mhm_payment_gateway',
			'_mhm_payment_status',
			'_mhm_payment_amount',
			'_mhm_payment_deadline',
			'_mhm_refunded_amount',
			'_mhm_payment_currency',
			'_mhm_offline_receipt_id',
			'_mhm_vehicle_plate',
			'_mhm_rentiva_license_plate',
			'_mhm_selected_addons',

			// WooCommerce Integration
			'_mhm_wc_order_id',
			'_mhm_woocommerce_order_id',
			'_mhm_order_id',
			'_mhm_refund_txn_id',

			// Addon meta keys
			'addon_price',
			'addon_type',
			'addon_description',

			// Legacy booking meta keys
			'_booking_vehicle_id',
			'_booking_customer_email',
			'_booking_customer_name',
			'_booking_customer_phone',
			'_booking_pickup_date',
			'_booking_return_date',
			'_booking_rental_days',
			'_booking_total_price',
			'_booking_payment_status',
			'_booking_payment_gateway',

			// Message meta keys
			'_mhm_message_category',
			'_mhm_message_status',
			'_mhm_thread_id',
			'_mhm_is_read',
		);

		/**
		 * Filter: Allow addons and third-party plugins to add custom valid meta keys
		 *
		 * This is CRITICAL to prevent DatabaseCleaner from deleting valid meta keys
		 * added by addons or custom implementations.
		 *
		 * @param array<string> $valid_keys Array of valid meta key strings
		 * @return array Modified valid meta keys array
		 *
		 * @example
		 * add_filter('mhm_rentiva_valid_meta_keys', function($keys) {
		 *     $keys[] = '_mhm_custom_addon_meta';
		 *     $keys[] = '_mhm_payment_custom_field';
		 *     return $keys;
		 * });
		 */
		return apply_filters( 'mhm_rentiva_valid_meta_keys', $valid_keys );
	}

	/**
	 * Create cleanup report (pre-backup analysis)
	 */
	public static function analyze_database(): array {
		return array(
			'orphaned_postmeta'  => self::find_orphaned_postmeta(),
			'orphaned_usermeta'  => self::find_orphaned_usermeta(),
			'expired_transients' => self::find_expired_transients(),
			'unused_options'     => self::find_unused_options(),
			'invalid_meta_keys'  => self::find_invalid_meta_keys(),
			'old_logs'           => self::cleanup_old_logs( 30, true ),
			'table_stats'        => self::get_table_stats(),
		);
	}

	/**
	 * Detect orphaned postmeta (post no longer exists but meta does)
	 */
	public static function find_orphaned_postmeta(): array {
		global $wpdb;

		$orphaned = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT pm.meta_id, pm.post_id, pm.meta_key, pm.meta_value 
            FROM {$wpdb->postmeta} pm
            LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.ID IS NULL
            AND pm.meta_key LIKE %s
            LIMIT 100
        ",
				'_mhm%%'
			),
			ARRAY_A
		);

		return array(
			'count'               => count( $orphaned ),
			'samples'             => array_slice( $orphaned, 0, 10 ),
			'total_size_estimate' => count( $orphaned ) * 200, // bytes estimate
		);
	}

	/**
	 * Detect orphaned usermeta (user no longer exists but meta does)
	 */
	public static function find_orphaned_usermeta(): array {
		global $wpdb;

		$orphaned = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT um.umeta_id, um.user_id, um.meta_key, um.meta_value 
            FROM {$wpdb->usermeta} um
            LEFT JOIN {$wpdb->users} u ON um.user_id = u.ID
            WHERE u.ID IS NULL
            AND um.meta_key LIKE %s
            LIMIT 100
        ",
				'mhm_rentiva%%'
			),
			ARRAY_A
		);

		return array(
			'count'   => count( $orphaned ),
			'samples' => array_slice( $orphaned, 0, 10 ),
		);
	}

	/**
	 * Detect expired transients
	 */
	public static function find_expired_transients(): array {
		global $wpdb;

		$expired = $wpdb->get_results(
			"
            SELECT o1.option_name, o1.option_value, o2.option_value as timeout
            FROM {$wpdb->options} o1
            INNER JOIN {$wpdb->options} o2 
                ON o2.option_name = CONCAT('_transient_timeout_', SUBSTRING(o1.option_name, 12))
            WHERE o1.option_name LIKE '_transient_mhm%'
            AND CAST(o2.option_value AS UNSIGNED) < UNIX_TIMESTAMP()
            LIMIT 100
        ",
			ARRAY_A
		);

		return array(
			'count'               => count( $expired ),
			'samples'             => array_slice( $expired, 0, 10 ),
			'total_size_estimate' => $wpdb->get_var(
				"
                SELECT SUM(LENGTH(option_value))
                FROM {$wpdb->options}
                WHERE option_name LIKE '_transient_mhm%'
            "
			),
		);
	}

	/**
	 * Detect unused options
	 */
	public static function find_unused_options(): array {
		global $wpdb;

		// MHM Rentiva options
		$all_options = $wpdb->get_results(
			"
            SELECT option_name, LENGTH(option_value) as size
            FROM {$wpdb->options}
            WHERE option_name LIKE 'mhm_rentiva%'
            AND option_name NOT LIKE '_transient%'
        ",
			ARRAY_A
		);

		// Autoload options (unnecessary memory usage)
		$autoload_options = $wpdb->get_results(
			"
            SELECT option_name, LENGTH(option_value) as size
            FROM {$wpdb->options}
            WHERE option_name LIKE 'mhm_rentiva%'
            AND autoload = 'yes'
        ",
			ARRAY_A
		);

		return array(
			'total_options'    => count( $all_options ),
			'autoload_options' => count( $autoload_options ),
			'autoload_size'    => array_sum( array_column( $autoload_options, 'size' ) ),
			'samples'          => array_slice( $all_options, 0, 20 ),
		);
	}

	/**
	 * Detect invalid meta keys (not in VALID_META_KEYS list)
	 */
	public static function find_invalid_meta_keys(): array {
		global $wpdb;

		$valid_keys   = self::get_valid_meta_keys();
		$placeholders = implode( ',', array_fill( 0, count( $valid_keys ), '%s' ) );

		$invalid_keys = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT DISTINCT meta_key, COUNT(*) as count
            FROM {$wpdb->postmeta}
            WHERE meta_key LIKE %s
            AND meta_key NOT IN ({$placeholders})
            GROUP BY meta_key
            ORDER BY count DESC
            LIMIT 50
        ",
				array_merge( array( '_mhm%' ), $valid_keys )
			),
			ARRAY_A
		);

		return array(
			'count' => count( $invalid_keys ),
			'keys'  => $invalid_keys,
		);
	}

	/**
	 * Clean invalid meta keys (not in VALID_META_KEYS list)
	 */
	public static function cleanup_invalid_meta_keys( bool $dry_run = true ): array {
		global $wpdb;

		// Get invalid meta keys first
		$invalid_data = self::find_invalid_meta_keys();

		if ( $dry_run ) {
			return array(
				'dry_run'      => true,
				'would_delete' => array_sum( array_column( $invalid_data['keys'] ?? array(), 'count' ) ),
				'keys'         => $invalid_data['keys'] ?? array(),
			);
		}

		if ( empty( $invalid_data['keys'] ) ) {
			return array(
				'dry_run'      => false,
				'deleted'      => 0,
				'keys_removed' => array(),
			);
		}

		// Create backup table
		$backup_table         = $wpdb->prefix . 'mhm_postmeta_backup_invalid_' . gmdate( 'Ymd_His' );
		$backup_table_escaped = esc_sql( $backup_table );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot be prepared, $backup_table_escaped and $wpdb->postmeta are safe.
		$wpdb->query( "CREATE TABLE `{$backup_table_escaped}` LIKE `{$wpdb->postmeta}`" );

		// Extract meta keys
		$invalid_keys = array_column( $invalid_data['keys'], 'meta_key' );
		$placeholders = implode( ',', array_fill( 0, count( $invalid_keys ), '%s' ) );

		// Backup invalid meta data
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot be prepared, variables are safe.
		$wpdb->query(
			$wpdb->prepare(
				"
            INSERT INTO `{$backup_table}`
            SELECT pm.*
            FROM `{$wpdb->postmeta}` pm
            WHERE pm.meta_key IN ({$placeholders})
        ",
				$invalid_keys
			)
		);

		// Delete invalid meta keys
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and keys are safe.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"
            DELETE pm
            FROM `{$wpdb->postmeta}` pm
            WHERE pm.meta_key IN ({$placeholders})
        ",
				$invalid_keys
			)
		);

		return array(
			'dry_run'      => false,
			'deleted'      => (int) $deleted,
			'keys_removed' => $invalid_keys,
			'backup_table' => $backup_table,
		);
	}

	/**
	 * Table statistics
	 */
	public static function get_table_stats(): array {
		global $wpdb;

		$tables = array(
			'payment_log'        => $wpdb->prefix . 'mhm_payment_log',
			'sessions'           => $wpdb->prefix . 'mhm_sessions',
			'transfer_routes'    => $wpdb->prefix . 'rentiva_transfer_routes',
			'transfer_locations' => $wpdb->prefix . 'rentiva_transfer_locations',
			'queue'              => $wpdb->prefix . 'mhm_rentiva_queue',
			'ratings'            => $wpdb->prefix . 'mhm_rentiva_ratings',
			'report_queue'       => $wpdb->prefix . 'mhm_rentiva_background_jobs',
			'message_logs'       => $wpdb->prefix . 'mhm_message_logs',
		);

		$stats = array();

		foreach ( $tables as $key => $table_name ) {
			// Check if table exists
			$table_exists = $wpdb->get_var(
				$wpdb->prepare(
					'
                SHOW TABLES LIKE %s
            ',
					$table_name
				)
			);

			if ( ! $table_exists ) {
				$stats[ $key ] = array( 'exists' => false );
				continue;
			}

			// Get table information
			$row_count  = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
			$table_size = $wpdb->get_var(
				$wpdb->prepare(
					'
                SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb
                FROM information_schema.TABLES
                WHERE table_schema = DATABASE()
                AND table_name = %s
            ',
					$table_name
				)
			);

			$stats[ $key ] = array(
				'exists'     => true,
				'rows'       => (int) $row_count,
				'size_mb'    => (float) $table_size,
				'table_name' => $table_name,
			);
		}

		return $stats;
	}

	/**
	 * Clean orphaned postmeta (WITH BACKUP)
	 */
	public static function cleanup_orphaned_postmeta( bool $dry_run = true ): array {
		global $wpdb;

		// Get orphaned meta count first
		$count = $wpdb->get_var(
			"
            SELECT COUNT(*)
            FROM {$wpdb->postmeta} pm
            LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.ID IS NULL
            AND pm.meta_key LIKE '_mhm%'
        "
		);

		if ( ! $dry_run && $count > 0 ) {
			// Create backup table
			$backup_table = $wpdb->prefix . 'mhm_postmeta_backup_' . gmdate( 'Ymd_His' );
			// Cannot use prepare() for table names
			$wpdb->query( "CREATE TABLE `{$backup_table}` LIKE `{$wpdb->postmeta}`" );

			// Backup orphaned data
			$wpdb->query(
				"
                INSERT INTO `{$backup_table}`
                SELECT pm.*
                FROM `{$wpdb->postmeta}` pm
                LEFT JOIN `{$wpdb->posts}` p ON pm.post_id = p.ID
                WHERE p.ID IS NULL
                AND pm.meta_key LIKE '_mhm%'
            "
			);

			// Delete orphaned meta
			// Cannot use prepare() for table names in DELETE queries
			$deleted = $wpdb->query(
				"
                DELETE pm
                FROM `{$wpdb->postmeta}` pm
                LEFT JOIN `{$wpdb->posts}` p ON pm.post_id = p.ID
                WHERE p.ID IS NULL
                AND pm.meta_key LIKE '_mhm%'
            "
			);

			return array(
				'dry_run'      => false,
				'deleted'      => $deleted,
				'backup_table' => $backup_table ?? null,
			);
		}

		return array(
			'dry_run'      => true,
			'would_delete' => (int) $count,
			'action'       => 'Set dry_run=false to execute cleanup',
		);
	}

	/**
	 * Clean expired transients
	 */
	public static function cleanup_expired_transients( bool $dry_run = true ): array {
		global $wpdb;

		// Count expired transients
		$count = $wpdb->get_var(
			"
            SELECT COUNT(*)
            FROM {$wpdb->options} o1
            INNER JOIN {$wpdb->options} o2 
                ON o2.option_name = CONCAT('_transient_timeout_', SUBSTRING(o1.option_name, 12))
            WHERE o1.option_name LIKE '_transient_mhm%'
            AND CAST(o2.option_value AS UNSIGNED) < UNIX_TIMESTAMP()
        "
		);

		if ( $dry_run ) {
			return array(
				'dry_run'      => true,
				'would_delete' => (int) $count,
			);
		}

		// Execute cleanup even if count is 0 (to ensure consistency)
		if ( $count > 0 ) {
			// Delete expired transients
			$deleted = $wpdb->query(
				"
                DELETE o1, o2
                FROM {$wpdb->options} o1
                INNER JOIN {$wpdb->options} o2 
                    ON o2.option_name = CONCAT('_transient_timeout_', SUBSTRING(o1.option_name, 12))
                WHERE o1.option_name LIKE '_transient_mhm%'
                AND CAST(o2.option_value AS UNSIGNED) < UNIX_TIMESTAMP()
            "
			);

			// Calculate size freed (approximate)
			$size_freed = $wpdb->get_var(
				"
                SELECT SUM(LENGTH(option_value))
                FROM {$wpdb->options}
                WHERE option_name LIKE '_transient_mhm%'
            "
			);

			return array(
				'dry_run'          => false,
				'deleted'          => (int) $deleted,
				'size_freed_bytes' => (int) $size_freed,
			);
		}

		// No expired transients to clean
		return array(
			'dry_run'          => false,
			'deleted'          => 0,
			'size_freed_bytes' => 0,
		);
	}

	/**
	 * Clean old log records
	 */
	public static function cleanup_old_logs( int $days = 30, bool $dry_run = true ): array {
		global $wpdb;

		$tables = array(
			'queue'        => $wpdb->prefix . 'mhm_rentiva_queue',
			'report_queue' => $wpdb->prefix . 'mhm_rentiva_background_jobs',
			'message_logs' => $wpdb->prefix . 'mhm_message_logs',
		);

		$results     = array();
		$cutoff_date = gmdate( 'Y-m-d H:i:s', (int) strtotime( "-{$days} days" ) );

		foreach ( $tables as $key => $table_name ) {
			// Check if table exists
			$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

			if ( ! $table_exists ) {
				$results[ $key ] = array( 'exists' => false );
				continue;
			}

			// Count old records
			$date_column = ( $key === 'queue' ) ? 'created_at' : 'created_at';
			// Security: whitelist allow columns for direct SQL injection prevention
			$allowed_columns = array( 'created_at' );
			if ( ! in_array( $date_column, $allowed_columns, true ) ) {
				continue;
			}

			// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table/column are constrained by internal whitelist.
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM `{$table_name}` WHERE `{$date_column}` < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dynamic identifiers are internally constrained.
					$cutoff_date
				)
			);

			if ( ! $dry_run && $count > 0 ) {
				// Create backup
				$backup_table         = $table_name . '_backup_' . gmdate( 'Ymd_His' );
				$backup_table_escaped = esc_sql( $backup_table );
				$table_name_escaped   = esc_sql( $table_name );
				$date_column_escaped  = esc_sql( $date_column );

				$wpdb->query( "CREATE TABLE `{$backup_table_escaped}` LIKE `{$table_name_escaped}`" );
				$wpdb->query(
					$wpdb->prepare(
						"
                    INSERT INTO `{$backup_table_escaped}`
                    SELECT * FROM `{$table_name_escaped}`
                    WHERE `{$date_column_escaped}` < %s
                ",
						$cutoff_date
					)
				);

				// Delete old records
				$deleted = $wpdb->query(
					$wpdb->prepare(
						"
                    DELETE FROM `{$table_name_escaped}`
                    WHERE `{$date_column_escaped}` < %s
                ",
						$cutoff_date
					)
				);

				$results[ $key ] = array(
					'exists'       => true,
					'deleted'      => $deleted,
					'backup_table' => $backup_table,
				);
			} else {
				$results[ $key ] = array(
					'exists'       => true,
					'would_delete' => (int) $count,
				);
			}
		}

		return $results;
	}

	/**
	 * Autoload options optimization
	 */
	public static function optimize_autoload_options( bool $dry_run = true ): array {
		global $wpdb;

		// Find large autoload options
		$large_autoload = $wpdb->get_results(
			"
            SELECT option_name, LENGTH(option_value) as size
            FROM {$wpdb->options}
            WHERE option_name LIKE 'mhm_rentiva%'
            AND autoload = 'yes'
            AND LENGTH(option_value) > 1024
            ORDER BY size DESC
            LIMIT 20
        ",
			ARRAY_A
		);

		if ( $dry_run ) {
			return array(
				'dry_run'      => true,
				'would_update' => count( $large_autoload ),
				'options'      => $large_autoload,
			);
		}

		// Execute optimization
		if ( ! empty( $large_autoload ) ) {
			$updated      = 0;
			$memory_saved = 0;
			foreach ( $large_autoload as $option ) {
				// Set large options to autoload=no
				$update_result = $wpdb->update(
					$wpdb->options,
					array( 'autoload' => 'no' ),
					array( 'option_name' => $option['option_name'] ),
					array( '%s' ),
					array( '%s' )
				);
				if ( $update_result !== false ) {
					++$updated;
					$memory_saved += (int) ( $option['size'] ?? 0 );
				}
			}

			return array(
				'dry_run'            => false,
				'updated'            => $updated,
				'memory_saved_bytes' => $memory_saved,
			);
		}

		// No large autoload options to optimize
		return array(
			'dry_run'            => false,
			'updated'            => 0,
			'memory_saved_bytes' => 0,
		);
	}

	/**
	 * Optimize database tables
	 */
	public static function optimize_tables(): array {
		global $wpdb;

		$tables = array(
			$wpdb->posts,
			$wpdb->postmeta,
			$wpdb->options,
			$wpdb->prefix . 'mhm_rentiva_queue',
			$wpdb->prefix . 'mhm_rentiva_ratings',
			$wpdb->prefix . 'mhm_rentiva_background_jobs',
			$wpdb->prefix . 'mhm_message_logs',
		);

		$results = array();

		foreach ( $tables as $table ) {
			// Check if table exists
			// Cannot use prepare() for table names - escape manually
			$table_escaped = esc_sql( $table );
			$table_exists  = $wpdb->get_var( "SHOW TABLES LIKE '{$table_escaped}'" );

			if ( ! $table_exists ) {
				continue;
			}

			$start_time = microtime( true );
			// Cannot use prepare() for table names in OPTIMIZE TABLE
			$result         = $wpdb->query( "OPTIMIZE TABLE `{$table_escaped}`" );
			$execution_time = microtime( true ) - $start_time;

			$results[ $table ] = array(
				'success'           => $result !== false,
				'execution_time_ms' => round( $execution_time * 1000, 2 ),
			);
		}

		return $results;
	}

	/**
	 * Full cleanup process (ALL CLEANUP OPERATIONS)
	 */
	public static function full_cleanup( bool $dry_run = true, array $options = array() ): array {
		$default_options = array(
			'orphaned_postmeta'  => true,
			'orphaned_usermeta'  => true,
			'expired_transients' => true,
			'old_logs_days'      => 30,
			'optimize_autoload'  => true,
			'optimize_tables'    => false, // Can be slow
		);

		$options = array_merge( $default_options, $options );

		$results = array(
			'dry_run'    => $dry_run,
			'timestamp'  => current_time( 'mysql' ),
			'operations' => array(),
		);

		// Clean orphaned postmeta
		if ( $options['orphaned_postmeta'] ) {
			$results['operations']['orphaned_postmeta'] = self::cleanup_orphaned_postmeta( $dry_run );
		}

		// Clean orphaned usermeta (usually none but let's check)
		if ( $options['orphaned_usermeta'] ) {
			$orphaned_usermeta                          = self::find_orphaned_usermeta();
			$results['operations']['orphaned_usermeta'] = array(
				'checked' => true,
				'found'   => $orphaned_usermeta['count'],
			);
		}

		// Clean expired transients
		if ( $options['expired_transients'] ) {
			$results['operations']['expired_transients'] = self::cleanup_expired_transients( $dry_run );
		}

		// Clean old logs
		if ( $options['old_logs_days'] ) {
			$results['operations']['old_logs'] = self::cleanup_old_logs( $options['old_logs_days'], $dry_run );
		}

		// Optimize autoload
		if ( $options['optimize_autoload'] ) {
			$results['operations']['autoload_optimization'] = self::optimize_autoload_options( $dry_run );
		}

		// Optimize tables
		if ( $options['optimize_tables'] && ! $dry_run ) {
			$results['operations']['table_optimization'] = self::optimize_tables();
		}

		return $results;
	}

	/**
	 * Render cleanup report HTML
	 */
	public static function render_cleanup_report( array $analysis ): string {
		ob_start();
		?>
		<div class="mhm-database-cleanup-report">
			<h3><?php esc_html_e( 'Database Cleanup Report', 'mhm-rentiva' ); ?></h3>

			<table class="widefat">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Category', 'mhm-rentiva' ); ?></th>
						<th><?php esc_html_e( 'Count', 'mhm-rentiva' ); ?></th>
						<th><?php esc_html_e( 'Size', 'mhm-rentiva' ); ?></th>
						<th><?php esc_html_e( 'Action', 'mhm-rentiva' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><?php esc_html_e( 'Orphaned Post Meta', 'mhm-rentiva' ); ?></td>
						<td><?php echo esc_html( $analysis['orphaned_postmeta']['count'] ); ?></td>
						<td><?php echo esc_html( size_format( $analysis['orphaned_postmeta']['total_size_estimate'] ) ); ?></td>
						<td>
							<?php if ( $analysis['orphaned_postmeta']['count'] > 0 ) : ?>
								<span class="dashicons dashicons-warning" style="color: orange;"></span>
								<?php esc_html_e( 'Cleanup Recommended', 'mhm-rentiva' ); ?>
							<?php else : ?>
								<span class="dashicons dashicons-yes-alt" style="color: green;"></span>
								<?php esc_html_e( 'Clean', 'mhm-rentiva' ); ?>
							<?php endif; ?>
						</td>
					</tr>

					<tr>
						<td><?php esc_html_e( 'Expired Transients', 'mhm-rentiva' ); ?></td>
						<td><?php echo esc_html( $analysis['expired_transients']['count'] ); ?></td>
						<td><?php echo esc_html( size_format( $analysis['expired_transients']['total_size_estimate'] ) ); ?></td>
						<td>
							<?php if ( $analysis['expired_transients']['count'] > 0 ) : ?>
								<span class="dashicons dashicons-warning" style="color: orange;"></span>
								<?php esc_html_e( 'Cleanup Recommended', 'mhm-rentiva' ); ?>
							<?php else : ?>
								<span class="dashicons dashicons-yes-alt" style="color: green;"></span>
								<?php esc_html_e( 'Clean', 'mhm-rentiva' ); ?>
							<?php endif; ?>
						</td>
					</tr>

					<tr>
						<td><?php esc_html_e( 'Autoload Options', 'mhm-rentiva' ); ?></td>
						<td><?php echo esc_html( $analysis['unused_options']['autoload_options'] ); ?></td>
						<td><?php echo esc_html( size_format( $analysis['unused_options']['autoload_size'] ) ); ?></td>
						<td>
							<?php if ( $analysis['unused_options']['autoload_size'] > 10240 ) : ?>
								<span class="dashicons dashicons-warning" style="color: orange;"></span>
								<?php esc_html_e( 'Optimization Recommended', 'mhm-rentiva' ); ?>
							<?php else : ?>
								<span class="dashicons dashicons-yes-alt" style="color: green;"></span>
								<?php esc_html_e( 'Optimized', 'mhm-rentiva' ); ?>
							<?php endif; ?>
						</td>
					</tr>

					<tr>
						<td><?php esc_html_e( 'Invalid Meta Keys', 'mhm-rentiva' ); ?></td>
						<td><?php echo esc_html( $analysis['invalid_meta_keys']['count'] ); ?></td>
						<td>-</td>
						<td>
							<?php if ( $analysis['invalid_meta_keys']['count'] > 0 ) : ?>
								<span class="dashicons dashicons-warning" style="color: orange;"></span>
								<?php esc_html_e( 'Cleanup Recommended', 'mhm-rentiva' ); ?>
								<button type="button" class="button button-small" id="mhm-cleanup-invalid-meta-btn" style="margin-left: 10px;">
									<span class="dashicons dashicons-trash"></span>
									<?php esc_html_e( 'Clean', 'mhm-rentiva' ); ?>
								</button>
							<?php else : ?>
								<span class="dashicons dashicons-yes-alt" style="color: green;"></span>
								<?php esc_html_e( 'All Valid', 'mhm-rentiva' ); ?>
							<?php endif; ?>
						</td>
					</tr>

					<tr>
						<td><?php esc_html_e( 'Log Records (>30 days)', 'mhm-rentiva' ); ?></td>
						<td>
							<?php
							$log_count = 0;
							foreach ( $analysis['old_logs'] as $table_log ) {
								$log_count += ( $table_log['would_delete'] ?? 0 );
							}
							echo esc_html( (string) $log_count );
							?>
						</td>
						<td>-</td>
						<td>
							<?php if ( $log_count > 0 ) : ?>
								<span class="dashicons dashicons-warning" style="color: orange;"></span>
								<?php esc_html_e( 'Cleanup Recommended', 'mhm-rentiva' ); ?>
							<?php else : ?>
								<span class="dashicons dashicons-yes-alt" style="color: green;"></span>
								<?php esc_html_e( 'Clean', 'mhm-rentiva' ); ?>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>

			<h4><?php esc_html_e( 'Custom Tables', 'mhm-rentiva' ); ?></h4>
			<table class="widefat">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Table', 'mhm-rentiva' ); ?></th>
						<th><?php esc_html_e( 'Exists', 'mhm-rentiva' ); ?></th>
						<th><?php esc_html_e( 'Rows', 'mhm-rentiva' ); ?></th>
						<th><?php esc_html_e( 'Size (MB)', 'mhm-rentiva' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $analysis['table_stats'] as $key => $stats ) : ?>
						<tr>
							<td><?php echo esc_html( $key ); ?></td>
							<td>
								<?php if ( $stats['exists'] ) : ?>
									<span class="dashicons dashicons-yes-alt" style="color: green;"></span>
								<?php else : ?>
									<span class="dashicons dashicons-dismiss" style="color: red;"></span>
									<button type="button" class="button button-small mhm-repair-table-btn" data-table="<?php echo esc_attr( $key ); ?>" style="margin-left: 5px;">
										<?php esc_html_e( 'Repair/Create', 'mhm-rentiva' ); ?>
									</button>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $stats['rows'] ?? '-' ); ?></td>
							<td><?php echo esc_html( $stats['size_mb'] ?? '-' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<script>
				jQuery(document).ready(function($) {
					$('.mhm-repair-table-btn').on('click', function() {
						var btn = $(this);
						var table = btn.data('table');
						var originalText = btn.text();

						if (!confirm('<?php echo esc_js( __( 'Are you sure you want to attempt to create/repair this table?', 'mhm-rentiva' ) ); ?>')) {
							return;
						}

						btn.prop('disabled', true).text('Processing...');

						$.post(mhm_db_cleanup_vars.ajaxurl || ajaxurl, {
							action: 'mhm_repair_table',
							nonce: mhm_db_cleanup_vars.nonce,
							table_name: table
						}, function(response) {
							if (response.success) {
								alert(response.data.message);
								// Trigger analysis again to refresh the list
								$('#mhm-analyze-db-btn').click();
							} else {
								alert(response.data || 'Error occurred');
								btn.prop('disabled', false).text(originalText);
							}
						}).fail(function() {
							alert('Request failed');
							btn.prop('disabled', false).text(originalText);
						});
					});
				});
			</script>

			</tbody>
			</table>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render cleanup buttons
	 */
	public static function render_cleanup_buttons(): string {
		ob_start();
		?>
		<div class="mhm-cleanup-actions">
			<button type="button" class="button button-primary" id="mhm-analyze-db-btn">
				<span class="dashicons dashicons-search"></span>
				<?php esc_html_e( 'Analyze Database', 'mhm-rentiva' ); ?>
			</button>

			<button type="button" class="button button-secondary" id="mhm-cleanup-orphaned-btn">
				<span class="dashicons dashicons-trash"></span>
				<?php esc_html_e( 'Clean Orphaned Meta', 'mhm-rentiva' ); ?>
			</button>

			<button type="button" class="button button-secondary" id="mhm-cleanup-transients-btn">
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Clean Expired Transients', 'mhm-rentiva' ); ?>
			</button>

			<button type="button" class="button button-secondary" id="mhm-optimize-autoload-btn">
				<span class="dashicons dashicons-performance"></span>
				<?php esc_html_e( 'Optimize Autoload', 'mhm-rentiva' ); ?>
			</button>

			<button type="button" class="button" id="mhm-optimize-tables-btn">
				<span class="dashicons dashicons-database"></span>
				<?php esc_html_e( 'Optimize Tables', 'mhm-rentiva' ); ?>
			</button>

			<button type="button" class="button button-secondary" id="mhm-cleanup-logs-btn">
				<span class="dashicons dashicons-calendar-alt"></span>
				<?php esc_html_e( 'Purge Old Logs', 'mhm-rentiva' ); ?>
			</button>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * List all backup tables
	 */
	public static function list_backups(): array {
		global $wpdb;

		// Find all backup tables
		$backup_tables = $wpdb->get_col(
			"
            SHOW TABLES LIKE '{$wpdb->prefix}mhm_%_backup%'
        "
		);

		$backups = array();

		foreach ( $backup_tables as $table_name ) {
			// Get table info
			// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Backup table names are discovered from database and treated as trusted maintenance identifiers.
			$row_count  = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table_name}`" );
			// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Backup table names are discovered from database and treated as trusted maintenance identifiers.
			$table_size = $wpdb->get_var(
				"
                SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb
                FROM information_schema.TABLES
                WHERE table_schema = DATABASE()
                AND table_name = '{$table_name}'
            "
			);

			// Parse backup type from table name
			$backup_type = 'unknown';
			if ( strpos( $table_name, 'postmeta_backup_invalid' ) !== false ) {
				$backup_type = 'invalid_meta';
			} elseif ( strpos( $table_name, 'postmeta_backup_' ) !== false ) {
				$backup_type = 'orphaned_meta';
			} elseif ( strpos( $table_name, '_backup_' ) !== false ) {
				$backup_type = 'custom';
			}

			// Extract date from table name (format: YYYYMMDD_HHMMSS)
			$date_match = array();
			preg_match( '/(\d{8}_\d{6})/', $table_name, $date_match );
			$backup_date = ! empty( $date_match[1] ) ? $date_match[1] : 'unknown';

			$backups[] = array(
				'table_name' => $table_name,
				'type'       => $backup_type,
				'date'       => $backup_date,
				'rows'       => (int) $row_count,
				'size_mb'    => (float) $table_size,
			);
		}

		// Sort by date (newest first)
		usort(
			$backups,
			function ( $a, $b ) {
				return strcmp( $b['date'], $a['date'] );
			}
		);

		return $backups;
	}

	/**
	 * Generate SQL export for a backup table
	 */
	public static function export_backup_to_sql( string $table_name ): string {
		global $wpdb;

		// Verify table exists
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'
            SHOW TABLES LIKE %s
        ',
				$table_name
			)
		);

		if ( ! $table_exists ) {
			return '';
		}

		// Get table structure
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is validated for existence via SHOW TABLES LIKE before use.
		$create_table = $wpdb->get_row( "SHOW CREATE TABLE `{$table_name}`", ARRAY_A );
		$sql          = "-- Backup Export: {$table_name}\n";
		$sql         .= '-- Generated: ' . gmdate( 'Y-m-d H:i:s' ) . "\n\n";
		$sql         .= "DROP TABLE IF EXISTS `{$table_name}`;\n";
		$sql         .= $create_table['Create Table'] . ";\n\n";

		// Get all rows
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is validated for existence via SHOW TABLES LIKE before use.
		$rows = $wpdb->get_results( "SELECT * FROM `{$table_name}`", ARRAY_A );

		if ( ! empty( $rows ) ) {
			$sql   .= "INSERT INTO `{$table_name}` VALUES\n";
			$values = array();
			foreach ( $rows as $row ) {
				$row_values = array();
				foreach ( $row as $value ) {
					if ( $value === null ) {
						$row_values[] = 'NULL';
					} else {
						$row_values[] = "'" . esc_sql( $value ) . "'";
					}
				}
				$values[] = '(' . implode( ',', $row_values ) . ')';
			}
			$sql .= implode( ",\n", $values ) . ";\n";
		}

		return $sql;
	}

	/**
	 * Restore backup to original table
	 */
	public static function restore_backup( string $backup_table ): array {
		global $wpdb;

		// Verify backup table exists
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'
            SHOW TABLES LIKE %s
        ',
				$backup_table
			)
		);

		if ( ! $table_exists ) {
			return array(
				'success' => false,
				'message' => __( 'Backup table not found', 'mhm-rentiva' ),
			);
		}

		// Determine target table based on backup type
		$target_table = $wpdb->postmeta; // Default

		if ( strpos( $backup_table, 'postmeta_backup_invalid' ) !== false ) {
			$target_table = $wpdb->postmeta;
		} elseif ( strpos( $backup_table, 'postmeta_backup_' ) !== false ) {
			$target_table = $wpdb->postmeta;
		} else {
			// Try to determine from backup table name
			// Format: prefix_table_backup_YYYYMMDD_HHMMSS
			$parts = explode( '_backup_', $backup_table );
			if ( count( $parts ) === 2 ) {
				$possible_table = $parts[0];
				if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $possible_table ) ) ) {
					$target_table = $possible_table;
				}
			}
		}

		// Restore data
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Source and target table names are derived from vetted backup naming conventions and existence checks.
		$restored = $wpdb->query(
			"
            INSERT INTO `{$target_table}`
            SELECT * FROM `{$backup_table}`
            ON DUPLICATE KEY UPDATE
                meta_id = VALUES(meta_id),
                post_id = VALUES(post_id),
                meta_key = VALUES(meta_key),
                meta_value = VALUES(meta_value)
        "
		);

		return array(
			'success'      => $restored !== false,
			'restored'     => (int) $restored,
			'target_table' => $target_table,
			'message'      => sprintf(
				/* translators: 1: %d; 2: %s. */
				__( 'Restored %1$d records to %2$s', 'mhm-rentiva' ),
				(int) $restored,
				$target_table
			),
		);
	}

	/**
	 * Delete backup table
	 */
	public static function delete_backup( string $table_name ): array {
		global $wpdb;

		// Verify it's a backup table
		if ( strpos( $table_name, 'backup' ) === false ) {
			return array(
				'success' => false,
				'message' => __( 'Not a backup table', 'mhm-rentiva' ),
			);
		}

		// Verify table exists
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'
            SHOW TABLES LIKE %s
        ',
				$table_name
			)
		);

		if ( ! $table_exists ) {
			return array(
				'success' => false,
				'message' => __( 'Backup table not found', 'mhm-rentiva' ),
			);
		}

		// Delete table
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Backup table name is validated for existence via SHOW TABLES LIKE before deletion.
		$deleted = $wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" );

		return array(
			'success' => $deleted !== false,
			'message' => $deleted ? __( 'Backup deleted successfully', 'mhm-rentiva' ) : __( 'Failed to delete backup', 'mhm-rentiva' ),
		);
	}

	/**
	 * Create full database backup (all plugin-related tables)
	 */
	public static function create_full_backup(): array {
		global $wpdb;

		$backup_name = 'mhm_rentiva_full_backup_' . gmdate( 'Ymd_His' );
		$backup_dir  = WP_CONTENT_DIR . '/mhm-rentiva-backups';

		// Initialize Filesystem
		if ( ! self::init_filesystem() ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to initialize filesystem.', 'mhm-rentiva' ),
			);
		}

		global $wp_filesystem;

		// Create backup directory if it doesn't exist
		if ( ! $wp_filesystem->exists( $backup_dir ) ) {
			$wp_filesystem->mkdir( $backup_dir );
		}

		// Always ensure backup directory is secure (even if it already existed)
		self::secure_backup_directory( $backup_dir );

		// Define tables to backup
		$tables_to_backup = array(
			// WordPress core tables used by plugin
			$wpdb->posts,
			$wpdb->postmeta,
			$wpdb->options,

			// Plugin custom tables
			$wpdb->prefix . 'mhm_rentiva_queue',
			$wpdb->prefix . 'mhm_rentiva_ratings',
			$wpdb->prefix . 'mhm_rentiva_report_queue',
			$wpdb->prefix . 'mhm_message_logs',
			$wpdb->prefix . 'mhm_rentiva_background_jobs',
		);

		// Filter existing tables only
		$existing_tables = array();
		foreach ( $tables_to_backup as $table ) {
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $exists ) {
				$existing_tables[] = $table;
			}
		}

		if ( empty( $existing_tables ) ) {
			return array(
				'success' => false,
				'message' => __( 'No tables found to backup', 'mhm-rentiva' ),
			);
		}

		// Generate SQL file
		$sql_content  = "-- MHM Rentiva Full Database Backup\n";
		$sql_content .= '-- Generated: ' . gmdate( 'Y-m-d H:i:s' ) . "\n";
		$sql_content .= "-- Backup Name: {$backup_name}\n\n";

		$total_rows = 0;

		foreach ( $existing_tables as $table ) {
			// Table structure
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot be prepared and are verified existing tables above.
			$create_table = $wpdb->get_row( "SHOW CREATE TABLE `{$table}`", ARRAY_A );
			if ( ! $create_table ) {
				continue;
			}

			$sql_content .= "\n-- Table: {$table}\n";
			$sql_content .= "DROP TABLE IF EXISTS `{$table}`;\n";
			$sql_content .= $create_table['Create Table'] . ";\n\n";

			// Table data
			$rows = $wpdb->get_results( "SELECT * FROM `{$table}`", ARRAY_A );
			if ( ! empty( $rows ) ) {
				$sql_content .= "INSERT INTO `{$table}` VALUES\n";
				$values       = array();
				foreach ( $rows as $row ) {
					$row_values = array();
					foreach ( $row as $value ) {
						if ( $value === null ) {
							$row_values[] = 'NULL';
						} else {
							$row_values[] = "'" . esc_sql( $value ) . "'";
						}
					}
					$values[] = '(' . implode( ',', $row_values ) . ')';
				}
				$sql_content .= implode( ",\n", $values ) . ";\n\n";
				$total_rows  += count( $rows );
			}
		}

		// Save to file
		$file_path    = $backup_dir . '/' . $backup_name . '.sql';
		$file_written = $wp_filesystem->put_contents( $file_path, $sql_content, FS_CHMOD_FILE );

		if ( ! $file_written ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to write backup file', 'mhm-rentiva' ),
			);
		}

		// Also create a record in database for management
		$backup_table = $wpdb->prefix . 'mhm_backup_records';

		// Create backup records table if it doesn't exist
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Internal maintenance table name based on plugin prefix.
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS `{$backup_table}` (
            `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `backup_name` varchar(255) NOT NULL,
            `backup_type` varchar(50) NOT NULL DEFAULT 'full',
            `file_path` varchar(500) NOT NULL,
            `file_size` bigint(20) UNSIGNED DEFAULT 0,
            `tables_count` int(11) DEFAULT 0,
            `rows_count` int(11) DEFAULT 0,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `backup_name` (`backup_name`),
            KEY `backup_type` (`backup_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
		);

		// Insert record
		$wpdb->insert(
			$backup_table,
			array(
				'backup_name'  => $backup_name,
				'backup_type'  => 'full',
				'file_path'    => $file_path,
				'file_size'    => $wp_filesystem->size( $file_path ),
				'tables_count' => count( $existing_tables ),
				'rows_count'   => $total_rows,
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%d', '%d', '%d', '%s' )
		);

		return array(
			'success'      => true,
			'backup_name'  => $backup_name,
			'file_path'    => $file_path,
			'file_size'    => $wp_filesystem->size( $file_path ),
			'tables_count' => count( $existing_tables ),
			'rows_count'   => $total_rows,
			'message'      => sprintf(
				/* translators: 1: %d; 2: %d. */
				__( 'Full backup created successfully: %1$d tables, %2$d rows', 'mhm-rentiva' ),
				count( $existing_tables ),
				$total_rows
			),
		);
	}

	/**
	 * List all full backups (from files and database records)
	 */
	public static function list_full_backups(): array {
		global $wpdb;

		$backups      = array();
		$backup_dir   = WP_CONTENT_DIR . '/mhm-rentiva-backups';
		$backup_table = $wpdb->prefix . 'mhm_backup_records';

		// Get backups from database records
		$backup_table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $backup_table ) );

		if ( $backup_table_exists ) {
			// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Internal maintenance table name based on plugin prefix and existence check.
			$records = $wpdb->get_results(
				"
                SELECT * FROM `{$backup_table}`
                WHERE backup_type = 'full'
                ORDER BY created_at DESC
            ",
				ARRAY_A
			);

			foreach ( $records as $record ) {
				$file_exists = false;
				if ( self::init_filesystem() ) {
					global $wp_filesystem;
					$file_exists = $wp_filesystem->exists( $record['file_path'] );
				}

				$backups[] = array(
					'id'           => (int) $record['id'],
					'backup_name'  => $record['backup_name'],
					'type'         => 'full',
					'file_path'    => $record['file_path'],
					'file_exists'  => $file_exists,
					'file_size'    => (int) $record['file_size'],
					'file_size_mb' => round( $record['file_size'] / 1024 / 1024, 2 ),
					'tables_count' => (int) $record['tables_count'],
					'rows_count'   => (int) $record['rows_count'],
					'created_at'   => $record['created_at'],
					'date'         => $record['created_at'],
				);
			}
		}

		// Also check backup directory for files not in database
		if ( self::init_filesystem() ) {
			global $wp_filesystem;

			if ( $wp_filesystem->exists( $backup_dir ) && $wp_filesystem->is_dir( $backup_dir ) ) {
				// WP_Filesystem doesn't have a direct glob() alternative that works consistently across all methods.
				// However, dirlist() works for FTP/Direct etc.
				$file_list = $wp_filesystem->dirlist( $backup_dir );

				if ( is_array( $file_list ) ) {
					foreach ( $file_list as $file_info ) {
						if ( strpos( $file_info['name'], 'mhm_rentiva_full_backup_' ) !== 0 || substr( $file_info['name'], -4 ) !== '.sql' ) {
							continue;
						}

						$file_path   = $backup_dir . '/' . $file_info['name'];
						$file_name   = $file_info['name'];
						$backup_name = str_replace( '.sql', '', $file_name );

						// Check if already in database
						$exists_in_db = false;
						foreach ( $backups as $backup ) {
							if ( $backup['backup_name'] === $backup_name ) {
								$exists_in_db = true;
								break;
							}
						}

						if ( ! $exists_in_db ) {
							$backups[] = array(
								'id'           => 0,
								'backup_name'  => $backup_name,
								'type'         => 'full',
								'file_path'    => $file_path,
								'file_exists'  => true,
								'file_size'    => isset( $file_info['size'] ) ? (int) $file_info['size'] : 0,
								'file_size_mb' => isset( $file_info['size'] ) ? round( $file_info['size'] / 1024 / 1024, 2 ) : 0,
								'tables_count' => 0, // Unknown
								'rows_count'   => 0, // Unknown
								'created_at'   => isset( $file_info['lastmod'] ) ? gmdate( 'Y-m-d H:i:s', $file_info['lastmod'] ) : '',
								'date'         => isset( $file_info['lastmod'] ) ? gmdate( 'Y-m-d H:i:s', $file_info['lastmod'] ) : '',
							);
						}
					}
				}
			}
		}

		// Sort by date (newest first)
		usort(
			$backups,
			function ( $a, $b ) {
				return strcmp( $b['date'], $a['date'] );
			}
		);

		return $backups;
	}

	/**
	 * Restore full backup from SQL file
	 */
	public static function restore_full_backup( string $file_path ): array {
		global $wpdb;

		if ( ! self::init_filesystem() ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to initialize filesystem', 'mhm-rentiva' ),
			);
		}

		global $wp_filesystem;

		if ( ! $wp_filesystem->exists( $file_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'Backup file not found', 'mhm-rentiva' ),
			);
		}

		// Read SQL file
		$sql_content = $wp_filesystem->get_contents( $file_path );
		if ( empty( $sql_content ) ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to read backup file', 'mhm-rentiva' ),
			);
		}

		// Execute SQL (split by semicolon)
		$queries = array_filter(
			array_map( 'trim', explode( ';', $sql_content ) ),
			function ( $query ) {
				return ! empty( $query ) && ! preg_match( '/^--/', $query );
			}
		);

		$executed = 0;
		$errors   = array();

		foreach ( $queries as $query ) {
			if ( empty( trim( $query ) ) ) {
				continue;
			}
			// Security: $query is from a trusted backup file.
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- The query variable contains a full SQL statement restored from trusted backup input.
			$result = $wpdb->query( $query );
			if ( $result === false ) {
				$errors[] = $wpdb->last_error;
			} else {
				++$executed;
			}
		}

		return array(
			'success'  => empty( $errors ),
			'executed' => $executed,
			'errors'   => $errors,
			'message'  => empty( $errors )
				/* translators: %d placeholder. */
				? sprintf( __( 'Restored %d queries successfully', 'mhm-rentiva' ), $executed )
				/* translators: 1: %d; 2: %d. */
				: sprintf( __( 'Restored %1$d queries, %2$d errors occurred', 'mhm-rentiva' ), $executed, count( $errors ) ),
		);
	}

	/**
	 * Delete full backup
	 */
	public static function delete_full_backup( string $backup_name ): array {
		global $wpdb;

		$backup_dir   = WP_CONTENT_DIR . '/mhm-rentiva-backups';
		$file_path    = $backup_dir . '/' . $backup_name . '.sql';
		$backup_table = $wpdb->prefix . 'mhm_backup_records';

		// Initialize filesystem
		$file_deleted = false;
		if ( self::init_filesystem() ) {
			global $wp_filesystem;
			if ( $wp_filesystem->exists( $file_path ) ) {
				$file_deleted = $wp_filesystem->delete( $file_path );
			} else {
				// If file doesn't exist, consider it "deleted" from filesystem perspective
				$file_deleted = true;
			}
		}

		// Delete database record
		$record_deleted      = false;
		$backup_table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $backup_table ) );
		if ( $backup_table_exists ) {
			$record_deleted = $wpdb->delete(
				$backup_table,
				array( 'backup_name' => $backup_name ),
				array( '%s' )
			);
		}

		return array(
			'success' => $file_deleted || $record_deleted !== false,
			'message' => ( $file_deleted && $record_deleted !== false )
				? __( 'Backup deleted successfully', 'mhm-rentiva' )
				: __( 'Backup deletion completed with some warnings', 'mhm-rentiva' ),
		);
	}

	/**
	 * Secure backup directory from direct web access
	 * Creates .htaccess and index.php files (WordPress standards compliant)
	 */
	/**
	 * Secure backup directory from direct web access
	 * Creates .htaccess and index.php files (WordPress standards compliant)
	 */
	private static function secure_backup_directory( string $directory ): void {
		if ( ! self::init_filesystem() ) {
			return;
		}

		global $wp_filesystem;

		// Create .htaccess file to deny web access (Apache)
		$htaccess_content  = "# MHM Rentiva Backup Directory Protection\n";
		$htaccess_content .= "# This file prevents direct web access to backup files\n";
		$htaccess_content .= "# WordPress Security Standards Compliant\n\n";
		$htaccess_content .= "<IfModule mod_authz_core.c>\n";
		$htaccess_content .= "    Require all denied\n";
		$htaccess_content .= "</IfModule>\n";
		$htaccess_content .= "<IfModule !mod_authz_core.c>\n";
		$htaccess_content .= "    Order deny,allow\n";
		$htaccess_content .= "    Deny from all\n";
		$htaccess_content .= "</IfModule>\n";

		$htaccess_file = $directory . '/.htaccess';
		// Always update .htaccess to ensure it's secure
		$wp_filesystem->put_contents( $htaccess_file, $htaccess_content, FS_CHMOD_FILE );

		// Create index.php file as additional protection (WordPress standard)
		$index_content  = "<?php\n";
		$index_content .= "// Silence is golden.\n";
		$index_content .= "// This file prevents directory listing.\n";

		$index_file = $directory . '/index.php';
		// Always update index.php to ensure it exists
		$wp_filesystem->put_contents( $index_file, $index_content, FS_CHMOD_FILE );
	}

	/**
	 * Verify backup directory security
	 */
	public static function verify_backup_directory_security( string $directory ): array {
		$issues = array();

		if ( ! self::init_filesystem() ) {
			return array(
				'secure' => false,
				'issues' => array( __( 'Filesystem initialization failed', 'mhm-rentiva' ) ),
			);
		}

		global $wp_filesystem;

		// Check if directory exists
		if ( ! $wp_filesystem->exists( $directory ) ) {
			return array(
				'secure' => false,
				'issues' => array( __( 'Backup directory does not exist', 'mhm-rentiva' ) ),
			);
		}

		// Check .htaccess file
		$htaccess_file = $directory . '/.htaccess';
		if ( ! $wp_filesystem->exists( $htaccess_file ) ) {
			$issues[] = __( '.htaccess file missing - directory is not protected from web access', 'mhm-rentiva' );
		} else {
			$htaccess_content = $wp_filesystem->get_contents( $htaccess_file );
			if ( strpos( $htaccess_content, 'Deny from all' ) === false && strpos( $htaccess_content, 'Require all denied' ) === false ) {
				$issues[] = __( '.htaccess file exists but does not deny access properly', 'mhm-rentiva' );
			}
		}

		// Check index.php file
		$index_file = $directory . '/index.php';
		if ( ! $wp_filesystem->exists( $index_file ) ) {
			$issues[] = __( 'index.php file missing - directory listing is possible', 'mhm-rentiva' );
		}

		return array(
			'secure' => empty( $issues ),
			'issues' => $issues,
		);
	}

	/**
	 * Helper: Initialize WP_Filesystem
	 */
	private static function init_filesystem(): bool {
		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			if ( ! WP_Filesystem() ) {
				return false;
			}
		}

		return ! empty( $wp_filesystem );
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
// phpcs:enable
