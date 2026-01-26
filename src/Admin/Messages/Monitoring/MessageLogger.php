<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Messages\Monitoring;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Messaging system log manager
 */
final class MessageLogger {



	private const LOG_TABLE  = 'mhm_message_logs';
	private const LOG_LEVELS = array(
		'debug'    => 0,
		'info'     => 1,
		'warning'  => 2,
		'error'    => 3,
		'critical' => 4,
	);

	private static bool $logging_enabled = true;
	private static int $min_log_level    = 1; // info and above
	private static array $log_buffer     = array();

	/**
	 * Initialize logger
	 */
	public static function init(): void {
		self::create_log_table();
		add_action( 'wp_ajax_mhm_get_message_logs', array( self::class, 'ajax_get_logs' ) );
		add_action( 'wp_ajax_mhm_clear_message_logs', array( self::class, 'ajax_clear_logs' ) );
		add_action( 'shutdown', array( self::class, 'flush_log_buffer' ) );
	}

	/**
	 * Create log table
	 */
	private static function create_log_table(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . self::LOG_TABLE;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            level varchar(20) NOT NULL DEFAULT 'info',
            message text NOT NULL,
            context longtext,
            user_id bigint(20),
            ip_address varchar(45),
            user_agent text,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY level (level),
            KEY created_at (created_at),
            KEY user_id (user_id)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Debug level log
	 */
	public static function debug( string $message, array $context = array() ): void {
		self::log( 'debug', $message, $context );
	}

	/**
	 * Info level log
	 */
	public static function info( string $message, array $context = array() ): void {
		self::log( 'info', $message, $context );
	}

	/**
	 * Warning level log
	 */
	public static function warning( string $message, array $context = array() ): void {
		self::log( 'warning', $message, $context );
	}

	/**
	 * Error level log
	 */
	public static function error( string $message, array $context = array() ): void {
		self::log( 'error', $message, $context );
	}

	/**
	 * Critical level log
	 */
	public static function critical( string $message, array $context = array() ): void {
		self::log( 'critical', $message, $context );
	}

	/**
	 * Main log function
	 */
	private static function log( string $level, string $message, array $context = array() ): void {
		if ( ! self::$logging_enabled ) {
			return;
		}

		if ( self::LOG_LEVELS[ $level ] < self::$min_log_level ) {
			return;
		}

		$log_entry = array(
			'level'      => $level,
			'message'    => $message,
			'context'    => json_encode( $context ),
			'user_id'    => get_current_user_id(),
			'ip_address' => self::get_client_ip(),
			'user_agent' => sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
			'created_at' => current_time( 'mysql' ),
		);

		// Add to buffer (for performance)
		self::$log_buffer[] = $log_entry;

		// Critical errors should be written immediately
		if ( $level === 'critical' ) {
			self::flush_log_buffer();
		}
	}

	/**
	 * Clear log buffer and write to database
	 */
	public static function flush_log_buffer(): void {
		if ( empty( self::$log_buffer ) ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . self::LOG_TABLE;

		// Create VALUES string for batch insert
		$values = array();
		foreach ( self::$log_buffer as $entry ) {
			$values[] = $wpdb->prepare(
				'(%s, %s, %s, %d, %s, %s, %s)',
				$entry['level'],
				$entry['message'],
				$entry['context'],
				$entry['user_id'],
				$entry['ip_address'],
				$entry['user_agent'],
				$entry['created_at']
			);
		}

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Bulk insert values are individualy prepared.
			$wpdb->query( "INSERT INTO `{$table_name}` (level, message, context, user_id, ip_address, user_agent, created_at) VALUES " . implode( ', ', $values ) );
		}

		// Clear buffer
		self::$log_buffer = array();
	}

	/**
	 * Get logs
	 */
	public static function get_logs( array $args = array() ): array {
		global $wpdb;
		$table_name = $wpdb->prefix . self::LOG_TABLE;

		$default_args = array(
			'level'   => null,
			'user_id' => null,
			'limit'   => 100,
			'offset'  => 0,
			'orderby' => 'created_at',
			'order'   => 'DESC',
			'search'  => '',
		);

		$args = wp_parse_args( $args, $default_args );

		$where_conditions = array( '1=1' );
		$where_values     = array();

		if ( $args['level'] ) {
			$where_conditions[] = 'level = %s';
			$where_values[]     = $args['level'];
		}

		if ( $args['user_id'] ) {
			$where_conditions[] = 'user_id = %d';
			$where_values[]     = $args['user_id'];
		}

		if ( $args['search'] ) {
			$where_conditions[] = '(message LIKE %s OR context LIKE %s)';
			$search_term        = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where_values[]     = $search_term;
			$where_values[]     = $search_term;
		}

		$where_clause = implode( ' AND ', $where_conditions );

		// Get total count
		if ( ! empty( $where_values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name is safe, where clause is built with placeholders.
			$total_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table_name}` WHERE {$where_clause}", $where_values ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name is safe, where clause is safe.
			$total_count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table_name}` WHERE {$where_clause}" );
		}

		// Validate orderby and order
		$allowed_orderby = array( 'id', 'level', 'user_id', 'created_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		// Get logs
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, where clause is built with placeholders.
		$logs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table_name}` WHERE {$where_clause} ORDER BY `{$orderby}` {$order} LIMIT %d OFFSET %d",
				array_merge( $where_values, array( (int) $args['limit'], (int) $args['offset'] ) )
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name is safe.

		// Decode contexts
		foreach ( $logs as &$log ) {
			$log['context'] = json_decode( $log['context'], true );
		}

		return array(
			'logs'  => $logs,
			'total' => (int) $total_count,
			'pages' => ceil( $total_count / $args['limit'] ),
		);
	}

	/**
	 * Clear logs
	 */
	public static function clear_logs( array $args = array() ): int {
		global $wpdb;
		$table_name = $wpdb->prefix . self::LOG_TABLE;

		$default_args = array(
			'older_than' => null, // days
			'level'      => null,
			'user_id'    => null,
		);

		$args = wp_parse_args( $args, $default_args );

		$where_conditions = array( '1=1' );
		$where_values     = array();

		if ( $args['older_than'] ) {
			$where_conditions[] = 'created_at < %s';
			$where_values[]     = gmdate( 'Y-m-d H:i:s', strtotime( "-{$args['older_than']} days" ) );
		}

		if ( $args['level'] ) {
			$where_conditions[] = 'level = %s';
			$where_values[]     = $args['level'];
		}

		if ( $args['user_id'] ) {
			$where_conditions[] = 'user_id = %d';
			$where_values[]     = $args['user_id'];
		}

		$where_clause = implode( ' AND ', $where_conditions );

		if ( ! empty( $where_values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name is safe, where clause is built with placeholders.
			$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM `{$table_name}` WHERE {$where_clause}", $where_values ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name is safe, where clause is safe.
			$deleted = $wpdb->query( "DELETE FROM `{$table_name}` WHERE {$where_clause}" );
		}

		return (int) $deleted;
	}

	/**
	 * Get log statistics
	 */
	public static function get_log_stats(): array {
		global $wpdb;
		$table_name = $wpdb->prefix . self::LOG_TABLE;

		$stats = array();

		// Daily log counts
		$daily_stats = $wpdb->get_results(
			"
            SELECT 
                DATE(created_at) as date,
                level,
                COUNT(*) as count
            FROM `{$table_name}` 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at), level
            ORDER BY date DESC
        ",
			ARRAY_A
		);

		// Level-based statistics
		$level_stats = $wpdb->get_results(
			"
            SELECT 
                level,
                COUNT(*) as count
            FROM $table_name 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY level
        ",
			ARRAY_A
		);

		// Total log count
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name is safe.
		$total_logs = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );

		// Last log
		$last_log = $wpdb->get_row(
			"
            SELECT created_at, level, message 
            FROM $table_name 
            ORDER BY created_at DESC 
            LIMIT 1
        ",
			ARRAY_A
		);

		return array(
			'daily_stats' => $daily_stats,
			'level_stats' => $level_stats,
			'total_logs'  => (int) $total_logs,
			'last_log'    => $last_log,
		);
	}

	/**
	 * Get client IP address
	 */
	private static function get_client_ip(): string {
		$ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

		foreach ( $ip_keys as $key ) {
			if ( array_key_exists( $key, $_SERVER ) === true ) {
				foreach ( explode( ',', $_SERVER[ $key ] ) as $ip ) {
					$ip = trim( $ip );
					if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
						return $ip;
					}
				}
			}
		}

		return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
	}

	/**
	 * AJAX - Get logs
	 */
	public static function ajax_get_logs(): void {
		check_ajax_referer( 'mhm_message_logs', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access', 'mhm-rentiva' ) );
		}

		$args = array(
			'level'   => sanitize_text_field( $_POST['level'] ?? '' ),
			'user_id' => (int) ( $_POST['user_id'] ?? 0 ),
			'limit'   => (int) ( $_POST['limit'] ?? 50 ),
			'offset'  => (int) ( $_POST['offset'] ?? 0 ),
			'search'  => sanitize_text_field( $_POST['search'] ?? '' ),
		);

		// Clean empty values
		$args = array_filter(
			$args,
			function ( $value ) {
				return $value !== '' && $value !== 0;
			}
		);

		$logs = self::get_logs( $args );
		wp_send_json_success( $logs );
	}

	/**
	 * AJAX - Clear logs
	 */
	public static function ajax_clear_logs(): void {
		check_ajax_referer( 'mhm_message_logs', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access', 'mhm-rentiva' ) );
		}

		$args = array(
			'older_than' => (int) ( $_POST['older_than'] ?? 0 ),
			'level'      => sanitize_text_field( $_POST['level'] ?? '' ),
			'user_id'    => (int) ( $_POST['user_id'] ?? 0 ),
		);

		// Clean empty values
		$args = array_filter(
			$args,
			function ( $value ) {
				return $value !== '' && $value !== 0;
			}
		);

		$deleted_count = self::clear_logs( $args );

		wp_send_json_success(
			array(
				'deleted_count' => $deleted_count,
				/* translators: %d placeholder. */
				'message'       => sprintf( __( '%d log records deleted.', 'mhm-rentiva' ), $deleted_count ),
			)
		);
	}

	/**
	 * Configure logger settings
	 */
	public static function configure( array $config ): void {
		self::$logging_enabled = $config['enabled'] ?? true;
		self::$min_log_level   = self::LOG_LEVELS[ $config['min_level'] ] ?? 1;
	}

	/**
	 * Get log settings
	 */
	public static function get_config(): array {
		return array(
			'enabled'          => self::$logging_enabled,
			'min_level'        => array_search( self::$min_log_level, self::LOG_LEVELS ),
			'available_levels' => array_keys( self::LOG_LEVELS ),
		);
	}

	/**
	 * Log dashboard widget
	 */
	public static function render_log_widget(): void {
		$stats = self::get_log_stats();

		?>
		<div class="mhm-log-widget">
			<h4><?php esc_html_e( 'Message System Logs', 'mhm-rentiva' ); ?></h4>

			<div class="log-stats">
				<div class="stat-item">
					<span class="label"><?php esc_html_e( 'Total Logs:', 'mhm-rentiva' ); ?></span>
					<span class="value"><?php echo esc_html( number_format( (int) $stats['total_logs'] ) ); ?></span>
				</div>

				<?php if ( $stats['last_log'] ) : ?>
					<div class="stat-item">
						<span class="label"><?php esc_html_e( 'Last Log:', 'mhm-rentiva' ); ?></span>
						<span class="value">
							<span class="log-level log-level-<?php echo esc_attr( (string) $stats['last_log']['level'] ); ?>">
								<?php echo esc_html( strtoupper( (string) $stats['last_log']['level'] ) ); ?>
							</span>
							<?php echo esc_html( human_time_diff( (int) strtotime( (string) $stats['last_log']['created_at'] ), (int) current_time( 'timestamp' ) ) . ' ' . esc_html__( 'ago', 'mhm-rentiva' ) ); ?>
						</span>
					</div>
				<?php endif; ?>
			</div>

			<div class="log-actions">
				<button type="button" class="button button-small" id="view-logs-btn">
					<?php esc_html_e( 'View Logs', 'mhm-rentiva' ); ?>
				</button>

				<button type="button" class="button button-small" id="clear-logs-btn">
					<?php esc_html_e( 'Clear Old Logs', 'mhm-rentiva' ); ?>
				</button>
			</div>
		</div>

		<!-- CSS will be moved to separate file -->

		<!-- JavaScript will be moved to separate file -->
		<?php
	}
}
