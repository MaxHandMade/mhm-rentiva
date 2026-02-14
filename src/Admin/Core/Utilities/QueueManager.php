<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ✅ BULK OPERATIONS QUEUE SYSTEM - Background Job Management
 *
 * Queue-based job management for large datasets
 */
final class QueueManager {



	/**
	 * Queue table name
	 */
	private const QUEUE_TABLE = 'mhm_rentiva_queue';

	/**
	 * Job statuses
	 */
	public const STATUS_PENDING    = 'pending';
	public const STATUS_PROCESSING = 'processing';
	public const STATUS_COMPLETED  = 'completed';
	public const STATUS_FAILED     = 'failed';
	public const STATUS_CANCELLED  = 'cancelled';

	/**
	 * Job types
	 */
	public const TYPE_BULK_BOOKING_UPDATE  = 'bulk_booking_update';
	public const TYPE_BULK_VEHICLE_UPDATE  = 'bulk_vehicle_update';
	public const TYPE_BULK_CUSTOMER_UPDATE = 'bulk_customer_update';
	public const TYPE_BULK_EMAIL_SEND      = 'bulk_email_send';
	public const TYPE_BULK_EXPORT          = 'bulk_export';
	public const TYPE_BULK_IMPORT          = 'bulk_import';
	public const TYPE_CACHE_WARMUP         = 'cache_warmup';
	public const TYPE_DATA_CLEANUP         = 'data_cleanup';
	public const TYPE_REPORT_GENERATION    = 'report_generation';

	/**
	 * Return sanitized custom table name.
	 */
	private static function get_table_name(): string {
		global $wpdb;
		return preg_replace( '/[^A-Za-z0-9_]/', '', $wpdb->prefix . self::QUEUE_TABLE ) ?? '';
	}

	/**
	 * Create queue table
	 */
	public static function create_table(): void {
		global $wpdb;

		$table_name = self::get_table_name();

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_type varchar(100) NOT NULL,
            job_data longtext NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            priority int(11) NOT NULL DEFAULT 10,
            attempts int(11) NOT NULL DEFAULT 0,
            max_attempts int(11) NOT NULL DEFAULT 3,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            started_at datetime NULL,
            completed_at datetime NULL,
            error_message text NULL,
            progress_percent int(11) NOT NULL DEFAULT 0,
            total_items int(11) NOT NULL DEFAULT 0,
            processed_items int(11) NOT NULL DEFAULT 0,
            user_id bigint(20) unsigned NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY job_type (job_type),
            KEY priority (priority),
            KEY created_at (created_at),
            KEY user_id (user_id)
        ) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Add job
	 */
	public static function add_job( string $job_type, array $job_data, int $priority = 10, int $max_attempts = 3, ?int $user_id = null ): int {
		global $wpdb;

		$table_name = self::get_table_name();

		$result = $wpdb->insert(
			$table_name,
			array(
				'job_type'     => $job_type,
				'job_data'     => wp_json_encode( $job_data, JSON_UNESCAPED_UNICODE ),
				'priority'     => $priority,
				'max_attempts' => $max_attempts,
				'user_id'      => ( $user_id !== null && $user_id > 0 ) ? $user_id : get_current_user_id(),
				'status'       => self::STATUS_PENDING,
			),
			array( '%s', '%s', '%d', '%d', '%d', '%s' )
		);

		if ( $result === false ) {
			return 0;
		}

		// Start queue processing (if cron is not running)
		self::maybe_start_processing();

		return $wpdb->insert_id;
	}

	/**
	 * Get job (FIFO + priority)
	 */
	public static function get_next_job(): ?array {
		global $wpdb;

		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is sanitized in get_table_name().
		$job = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is sanitized.
				"SELECT * FROM {$table_name}
             WHERE status = %s 
             AND attempts < max_attempts
             ORDER BY priority ASC, created_at ASC 
             LIMIT 1",
				self::STATUS_PENDING
			),
			ARRAY_A
		);

		if ( ! $job ) {
			return null;
		}

		// Mark job as processing
		$wpdb->update(
			$table_name,
			array(
				'status'     => self::STATUS_PROCESSING,
				'started_at' => current_time( 'mysql' ),
				'attempts'   => $job['attempts'] + 1,
			),
			array( 'id' => $job['id'] ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);

		// Decode job data
		$job_data        = json_decode( $job['job_data'], true );
		$job['job_data'] = is_array( $job_data ) ? $job_data : array();

		return $job;
	}

	/**
	 * Update job status
	 */
	public static function update_job_status( int $job_id, string $status, ?string $error_message = null, ?int $progress_percent = null ): bool {
		global $wpdb;

		$table_name = self::get_table_name();

		$update_data   = array( 'status' => $status );
		$update_format = array( '%s' );

		if ( $status === self::STATUS_COMPLETED ) {
			$update_data['completed_at']     = current_time( 'mysql' );
			$update_data['progress_percent'] = 100;
			$update_format[]                 = '%s';
			$update_format[]                 = '%d';
		} elseif ( $status === self::STATUS_FAILED && $error_message ) {
			$update_data['error_message'] = $error_message;
			$update_format[]              = '%s';
		}

		if ( $progress_percent !== null ) {
			$update_data['progress_percent'] = $progress_percent;
			$update_format[]                 = '%d';
		}

		$result = $wpdb->update(
			$table_name,
			$update_data,
			array( 'id' => $job_id ),
			$update_format,
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Update job progress
	 */
	public static function update_job_progress( int $job_id, int $processed_items, int $total_items ): bool {
		global $wpdb;

		$table_name = self::get_table_name();

		$progress_percent = $total_items > 0 ? round( ( $processed_items / $total_items ) * 100 ) : 0;

		return $wpdb->update(
			$table_name,
			array(
				'processed_items'  => $processed_items,
				'total_items'      => $total_items,
				'progress_percent' => $progress_percent,
			),
			array( 'id' => $job_id ),
			array( '%d', '%d', '%d' ),
			array( '%d' )
		) !== false;
	}

	/**
	 * Cancel job
	 */
	public static function cancel_job( int $job_id ): bool {
		return self::update_job_status( $job_id, self::STATUS_CANCELLED );
	}

	/**
	 * Delete job
	 */
	public static function delete_job( int $job_id ): bool {
		global $wpdb;

		$table_name = self::get_table_name();

		return $wpdb->delete( $table_name, array( 'id' => $job_id ), array( '%d' ) ) !== false;
	}

	/**
	 * Get job details
	 */
	public static function get_job( int $job_id ): ?array {
		global $wpdb;

		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is sanitized in get_table_name().
		$job = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is sanitized.
				"SELECT * FROM {$table_name} WHERE id = %d",
				$job_id
			),
			ARRAY_A
		);

		if ( ! $job ) {
			return null;
		}

		$job_data        = json_decode( $job['job_data'], true );
		$job['job_data'] = is_array( $job_data ) ? $job_data : array();

		return $job;
	}

	/**
	 * Get user jobs
	 */
	public static function get_user_jobs( int $user_id, string $status = '', int $limit = 50 ): array {
		global $wpdb;

		$table_name = self::get_table_name();

		if ( $status !== '' ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is sanitized in get_table_name().
			$jobs = $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is sanitized.
					"SELECT * FROM {$table_name}
					 WHERE user_id = %d AND status = %s
					 ORDER BY created_at DESC
					 LIMIT %d",
					$user_id,
					$status,
					$limit
				),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is sanitized in get_table_name().
			$jobs = $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is sanitized.
					"SELECT * FROM {$table_name}
					 WHERE user_id = %d
					 ORDER BY created_at DESC
					 LIMIT %d",
					$user_id,
					$limit
				),
				ARRAY_A
			);
		}

		foreach ( $jobs as &$job ) {
			$job_data        = json_decode( $job['job_data'], true );
			$job['job_data'] = is_array( $job_data ) ? $job_data : array();
		}

		return $jobs;
	}

	/**
	 * Queue statistics
	 */
	public static function get_queue_stats(): array {
		global $wpdb;

		$table_name = self::get_table_name();

		$stats_query = $wpdb->prepare(
			"SELECT 
                COUNT(*) as total_jobs,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_jobs,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_jobs,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_jobs,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_jobs,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_jobs,
                AVG(progress_percent) as avg_progress
             FROM %i",
			$table_name
		);

		$stats = $wpdb->get_row(
			$stats_query, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is already prepared with %i placeholder above.
			ARRAY_A
		);

		return is_array( $stats ) ? $stats : array();
	}

	/**
	 * Clean up old jobs
	 */
	public static function cleanup_old_jobs( int $days = 30 ): int {
		global $wpdb;

		$table_name  = self::get_table_name();
		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is sanitized in get_table_name().
		return $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is sanitized.
				"DELETE FROM {$table_name}
             WHERE status IN (%s, %s) 
             AND completed_at < %s",
				self::STATUS_COMPLETED,
				self::STATUS_CANCELLED,
				$cutoff_date
			)
		);
	}

	/**
	 * Retry failed jobs
	 */
	public static function retry_failed_jobs(): int {
		global $wpdb;

		$table_name = self::get_table_name();

		return $wpdb->update(
			$table_name,
			array(
				'status'        => self::STATUS_PENDING,
				'error_message' => null,
				'attempts'      => 0,
			),
			array(
				'status' => self::STATUS_FAILED,
			),
			array( '%s', '%s', '%d' ),
			array( '%s' )
		);
	}

	/**
	 * Start queue processing (if needed)
	 */
	public static function maybe_start_processing(): void {
		// If cron job is not running, start manually
		if ( ! wp_next_scheduled( 'mhm_rentiva_process_queue' ) ) {
			wp_schedule_single_event( time(), 'mhm_rentiva_process_queue' );
		}
	}

	/**
	 * Bulk booking update job
	 */
	public static function add_bulk_booking_update_job( array $booking_ids, array $update_data, int $user_id = 0 ): int {
		return self::add_job(
			self::TYPE_BULK_BOOKING_UPDATE,
			array(
				'booking_ids' => $booking_ids,
				'update_data' => $update_data,
			),
			5, // High priority
			3,
			( $user_id > 0 ) ? $user_id : get_current_user_id()
		);
	}

	/**
	 * Bulk vehicle update job
	 */
	public static function add_bulk_vehicle_update_job( array $vehicle_ids, array $update_data, int $user_id = 0 ): int {
		return self::add_job(
			self::TYPE_BULK_VEHICLE_UPDATE,
			array(
				'vehicle_ids' => $vehicle_ids,
				'update_data' => $update_data,
			),
			5,
			3,
			( $user_id > 0 ) ? $user_id : get_current_user_id()
		);
	}

	/**
	 * Bulk email send job
	 */
	public static function add_bulk_email_job( array $recipients, string $subject, string $template, array $template_data, int $user_id = 0 ): int {
		return self::add_job(
			self::TYPE_BULK_EMAIL_SEND,
			array(
				'recipients'    => $recipients,
				'subject'       => $subject,
				'template'      => $template,
				'template_data' => $template_data,
			),
			7, // Medium priority
			2,
			( $user_id > 0 ) ? $user_id : get_current_user_id()
		);
	}

	/**
	 * Bulk export job
	 */
	public static function add_bulk_export_job( string $export_type, array $filters, string $format, int $user_id = 0 ): int {
		return self::add_job(
			self::TYPE_BULK_EXPORT,
			array(
				'export_type' => $export_type,
				'filters'     => $filters,
				'format'      => $format,
			),
			8, // Low priority
			1,
			( $user_id > 0 ) ? $user_id : get_current_user_id()
		);
	}

	/**
	 * Cache warmup job
	 */
	public static function add_cache_warmup_job( array $cache_keys, int $user_id = 0 ): int {
		return self::add_job(
			self::TYPE_CACHE_WARMUP,
			array(
				'cache_keys' => $cache_keys,
			),
			9, // Lowest priority
			1,
			( $user_id > 0 ) ? $user_id : get_current_user_id()
		);
	}

	/**
	 * Job processor - process different job types
	 */
	public static function process_job( array $job ): bool {
		try {
			switch ( $job['job_type'] ) {
				case self::TYPE_BULK_BOOKING_UPDATE:
					return self::process_bulk_booking_update( $job );

				case self::TYPE_BULK_VEHICLE_UPDATE:
					return self::process_bulk_vehicle_update( $job );

				case self::TYPE_BULK_EMAIL_SEND:
					return self::process_bulk_email_send( $job );

				case self::TYPE_BULK_EXPORT:
					return self::process_bulk_export( $job );

				case self::TYPE_CACHE_WARMUP:
					return self::process_cache_warmup( $job );

				default:
					self::update_job_status( $job['id'], self::STATUS_FAILED, 'Unknown job type: ' . $job['job_type'] );
					return false;
			}
		} catch ( \Exception $e ) {
			self::update_job_status( $job['id'], self::STATUS_FAILED, $e->getMessage() );
			return false;
		}
	}

	/**
	 * Bulk booking update processing
	 */
	private static function process_bulk_booking_update( array $job ): bool {
		$booking_ids = $job['job_data']['booking_ids'] ?? array();
		$update_data = $job['job_data']['update_data'] ?? array();
		$total_items = count( $booking_ids );

		if ( $total_items === 0 ) {
			self::update_job_status( $job['id'], self::STATUS_COMPLETED );
			return true;
		}

		self::update_job_progress( $job['id'], 0, $total_items );

		$processed = 0;
		foreach ( $booking_ids as $booking_id ) {
			// Check if job is cancelled
			$current_job = self::get_job( $job['id'] );
			if ( $current_job && $current_job['status'] === self::STATUS_CANCELLED ) {
				return false;
			}

			// Update booking
			foreach ( $update_data as $meta_key => $meta_value ) {
				update_post_meta( $booking_id, $meta_key, $meta_value );
			}

			++$processed;
			self::update_job_progress( $job['id'], $processed, $total_items );

			// Check memory usage
			if ( $processed % 50 === 0 ) {
				wp_cache_flush();
			}
		}

		self::update_job_status( $job['id'], self::STATUS_COMPLETED );
		return true;
	}

	/**
	 * Bulk vehicle update processing
	 */
	private static function process_bulk_vehicle_update( array $job ): bool {
		$vehicle_ids = $job['job_data']['vehicle_ids'] ?? array();
		$update_data = $job['job_data']['update_data'] ?? array();
		$total_items = count( $vehicle_ids );

		if ( $total_items === 0 ) {
			self::update_job_status( $job['id'], self::STATUS_COMPLETED );
			return true;
		}

		self::update_job_progress( $job['id'], 0, $total_items );

		$processed = 0;
		foreach ( $vehicle_ids as $vehicle_id ) {
			// Check if job is cancelled
			$current_job = self::get_job( $job['id'] );
			if ( $current_job && $current_job['status'] === self::STATUS_CANCELLED ) {
				return false;
			}

			// Update vehicle
			foreach ( $update_data as $meta_key => $meta_value ) {
				update_post_meta( $vehicle_id, $meta_key, $meta_value );
			}

			++$processed;
			self::update_job_progress( $job['id'], $processed, $total_items );

			// Check memory usage
			if ( $processed % 50 === 0 ) {
				wp_cache_flush();
			}
		}

		self::update_job_status( $job['id'], self::STATUS_COMPLETED );
		return true;
	}

	/**
	 * Bulk email send processing
	 */
	private static function process_bulk_email_send( array $job ): bool {
		$recipients    = $job['job_data']['recipients'] ?? array();
		$subject       = $job['job_data']['subject'] ?? '';
		$template      = $job['job_data']['template'] ?? '';
		$template_data = $job['job_data']['template_data'] ?? array();
		$total_items   = count( $recipients );

		if ( $total_items === 0 ) {
			self::update_job_status( $job['id'], self::STATUS_COMPLETED );
			return true;
		}

		self::update_job_progress( $job['id'], 0, $total_items );

		$processed = 0;
		foreach ( $recipients as $recipient ) {
			// Check if job is cancelled
			$current_job = self::get_job( $job['id'] );
			if ( $current_job && $current_job['status'] === self::STATUS_CANCELLED ) {
				return false;
			}

			// Send email
			$email_data = array_merge( $template_data, array( 'recipient' => $recipient ) );
			// Email sending logic will be here

			++$processed;
			self::update_job_progress( $job['id'], $processed, $total_items );

			// Rate limiting
			if ( $processed % 10 === 0 ) {
				sleep( 1 );
			}
		}

		self::update_job_status( $job['id'], self::STATUS_COMPLETED );
		return true;
	}

	/**
	 * Bulk export processing
	 */
	private static function process_bulk_export( array $job ): bool {
		$export_type = $job['job_data']['export_type'] ?? '';
		$filters     = $job['job_data']['filters'] ?? array();
		$format      = $job['job_data']['format'] ?? 'csv';

		// Export logic will be here
		// Simple implementation in this example

		self::update_job_progress( $job['id'], 50, 100 );
		sleep( 2 ); // Simulated processing
		self::update_job_progress( $job['id'], 100, 100 );

		self::update_job_status( $job['id'], self::STATUS_COMPLETED );
		return true;
	}

	/**
	 * Cache warmup processing
	 */
	private static function process_cache_warmup( array $job ): bool {
		$cache_keys  = $job['job_data']['cache_keys'] ?? array();
		$total_items = count( $cache_keys );

		if ( $total_items === 0 ) {
			self::update_job_status( $job['id'], self::STATUS_COMPLETED );
			return true;
		}

		self::update_job_progress( $job['id'], 0, $total_items );

		$processed = 0;
		foreach ( $cache_keys as $cache_key ) {
			// Cache warmup logic will be here
			// Example: ObjectCache::get($cache_key, $group);

			++$processed;
			self::update_job_progress( $job['id'], $processed, $total_items );
		}

		self::update_job_status( $job['id'], self::STATUS_COMPLETED );
		return true;
	}
}
