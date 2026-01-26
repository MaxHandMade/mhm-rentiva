<?php

/**
 * Comments Settings Manager.
 *
 * Handles comment configuration, spam protection logic, and setting persistence.
 *
 * @package MHMRentiva\Admin\Settings\Comments
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Comments;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CommentsSettings
 *
 * Manages settings retrieval, saving, and validation for the comments module.
 */
final class CommentsSettings {


	/**
	 * Database option key.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'mhm_rentiva_comments_settings';

	/**
	 * Runtime cache to store settings during a single request lifecycle.
	 *
	 * @var array|null
	 */
	private static $runtime_cache = null;

	/**
	 * Returns default settings structure.
	 *
	 * @return array Default settings configuration.
	 */
	public static function get_default_settings(): array {
		return apply_filters(
			'mhm_rentiva_comments_default_settings',
			array(
				'approval'        => array(
					'auto_approve'         => false,
					'require_login'        => true,
					'allow_guest_comments' => false,
					'moderation_required'  => true,
					'admin_notification'   => true,
				),
				'limits'          => array(
					'comments_per_page'        => 10,
					'max_comments_per_user'    => 0, // 0 = unlimited.
					'max_comments_per_vehicle' => 0,
					'comment_length_min'       => 5,
					'comment_length_max'       => 1000,
					'rating_required'          => true,
				),
				'spam_protection' => array(
					'enabled'             => true,
					'rate_limiting'       => array(
						'enabled'         => true,
						'time_window'     => 1, // Minutes.
						'max_attempts'    => 1, // Allowed comments count per window.
						'cooldown_period' => 10,
					),
					'duplicate_detection' => array(
						'enabled'        => true,
						'time_window'    => 1, // Minutes.
						'max_duplicates' => 1,
						'check_content'  => true,
					),
					'spam_words'          => apply_filters(
						'mhm_rentiva_spam_words',
						array(
							'spam',
							'viagra',
							'casino',
							'loan',
							'free money',
							'click here',
							'buy now',
							'discount',
						)
					),
					'ip_blocking'         => array(
						'enabled'        => false,
						'block_duration' => 24,
						'max_violations' => 5,
					),
				),
				'display'         => array(
					'show_ratings'        => true,
					'show_avatars'        => true,
					'show_dates'          => true,
					'show_edit_buttons'   => true,
					'show_delete_buttons' => true,
					'allow_editing'       => true,
					'allow_deletion'      => true,
					'edit_time_limit'     => 24, // Hours.
					'sort_order'          => 'newest',
					'pagination'          => true,
				),
				'notifications'   => array(
					'admin_new_comment'     => true,
					'admin_comment_edited'  => true,
					'admin_comment_deleted' => true,
					'user_comment_approved' => true,
					'user_comment_rejected' => true,
					'email_template'        => 'default',
				),
				'cache'           => array(
					'enabled'          => true,
					'duration'         => 15, // Minutes.
					'clear_on_comment' => true,
					'clear_on_edit'    => true,
					'clear_on_delete'  => true,
				),
				'performance'     => array(
					'lazy_loading'          => true,
					'ajax_loading'          => true,
					'infinite_scroll'       => false,
					'batch_size'            => 10,
					'background_processing' => false,
				),
			)
		);
	}

	/**
	 * Retrieves active settings merged with defaults.
	 * Implements runtime caching for performance.
	 *
	 * @return array The complete settings array.
	 */
	public static function get_settings(): array {
		// Return cached settings if available in memory.
		if ( null !== self::$runtime_cache ) {
			return self::$runtime_cache;
		}

		$defaults = self::get_default_settings();
		$settings = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		// Merge user settings with defaults recursively.
		$final_settings = array_replace_recursive( $defaults, $settings );

		/**
		 * Core Settings Integration.
		 * Checks for global settings overrides from the Core module if available.
		 */
		if (
			class_exists( '\MHMRentiva\Admin\Settings\Core\SettingsCore' ) &&
			method_exists( '\MHMRentiva\Admin\Settings\Core\SettingsCore', 'get_all' )
		) {
			$main_settings = \MHMRentiva\Admin\Settings\Core\SettingsCore::get_all();
			$core_map      = array();

			$sections = array(
				'comments_approval'        => 'approval',
				'comments_limits'          => 'limits',
				'comments_spam_protection' => 'spam_protection',
				'comments_display'         => 'display',
				'comments_cache'           => 'cache',
			);

			foreach ( $sections as $core_key => $local_key ) {
				if ( isset( $main_settings[ $core_key ] ) && is_array( $main_settings[ $core_key ] ) ) {
					$core_map[ $local_key ] = $main_settings[ $core_key ];
				}
			}

			if ( ! empty( $core_map ) ) {
				$final_settings = array_replace_recursive( $final_settings, $core_map );
			}
		}

		// Store in runtime cache.
		self::$runtime_cache = $final_settings;

		return $final_settings;
	}

	/**
	 * Retrieves a single setting value safely using dot notation.
	 *
	 * @param string $key     Dot-separated key (e.g., 'section.key').
	 * @param mixed  $default Default value if not found.
	 * @return mixed
	 */
	public static function get_setting( string $key, $default = null ) {
		$settings = self::get_settings();
		$keys     = explode( '.', $key );

		foreach ( $keys as $k ) {
			if ( ! is_array( $settings ) || ! isset( $settings[ $k ] ) ) {
				return $default;
			}
			$settings = $settings[ $k ];
		}

		return $settings;
	}

	/**
	 * Saves settings securely.
	 *
	 * Security Note: This method acts as a Data Manager.
	 * The Controller calling this method MUST perform `wp_verify_nonce` checks.
	 *
	 * @param array $settings Raw settings array to save.
	 * @return bool Success status.
	 */
	public static function save_settings( array $settings ): bool {
		// 1. Capability Check.
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		if ( empty( $settings ) ) {
			return false;
		}

		$defaults       = self::get_default_settings();
		$clean_settings = array();

		// Iterate over defaults to ensure schema strictness (reject unknown keys/sections).
		foreach ( $defaults as $section => $fields ) {
			if ( ! isset( $settings[ $section ] ) || ! is_array( $settings[ $section ] ) ) {
				continue;
			}

			foreach ( $fields as $key => $default_val ) {
				if ( ! isset( $settings[ $section ][ $key ] ) ) {
					continue;
				}

				$value                              = $settings[ $section ][ $key ];
				$clean_settings[ $section ][ $key ] = self::sanitize_value( $value, $default_val, $key );
			}
		}

		$saved = update_option( self::OPTION_NAME, $clean_settings );

		if ( $saved ) {
			// Clear runtime cache so subsequent calls get the new data.
			self::$runtime_cache = null;
		}

		return $saved;
	}

	/**
	 * Helper to sanitize a value based on its default type.
	 *
	 * @param mixed  $value       The input value.
	 * @param mixed  $default_val The default value (for type inference).
	 * @param string $key         The setting key (for specific rules).
	 * @return mixed Sanitized value.
	 */
	private static function sanitize_value( $value, $default_val, string $key ) {
		// Boolean sanitization.
		if ( is_bool( $default_val ) ) {
			return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
		}

		// Integer sanitization.
		if ( is_int( $default_val ) ) {
			return absint( $value );
		}

		// Array sanitization.
		if ( is_array( $default_val ) ) {
			if ( 'spam_words' === $key ) {
				// Handle comma-separated string from textarea or array.
				$words = is_array( $value ) ? $value : explode( ',', (string) $value );
				$words = array_map( 'sanitize_text_field', $words );
				return array_values( array_filter( array_map( 'trim', $words ) ) );
			}
			// Recursive sanitization for deep arrays.
			return map_deep( $value, 'sanitize_text_field' );
		}

		// Specific keys requiring restricted key format.
		if ( in_array( $key, array( 'email_template', 'sort_order' ), true ) ) {
			return sanitize_key( (string) $value );
		}

		// Default text sanitization.
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Resets settings to default.
	 *
	 * @return bool True if deleted successfully.
	 */
	public static function reset_settings(): bool {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		self::$runtime_cache = null;
		return delete_option( self::OPTION_NAME );
	}

	/**
	 * Runs spam protection checks.
	 *
	 * @param int    $user_id    User ID (0 for guest).
	 * @param int    $vehicle_id Vehicle (Post) ID.
	 * @param string $comment    Comment content.
	 * @return bool True if safe (not spam), False if spam.
	 */
	public static function check_spam_protection( int $user_id, int $vehicle_id, string $comment ): bool {
		// Basic validation.
		if ( $user_id < 0 || $vehicle_id <= 0 || empty( trim( $comment ) ) ) {
			return false;
		}

		$spam_protection = self::get_setting( 'spam_protection' );

		if ( empty( $spam_protection['enabled'] ) ) {
			return true;
		}

		// 1. Check Spam Words (Fastest check, no DB).
		$spam_words = isset( $spam_protection['spam_words'] ) ? $spam_protection['spam_words'] : array();
		if ( ! self::check_spam_words( $comment, $spam_words ) ) {
			return false;
		}

		// 2. Check Length Limits.
		$limits = self::get_setting( 'limits' );
		$len    = mb_strlen( trim( $comment ) );
		$min    = (int) ( isset( $limits['comment_length_min'] ) ? $limits['comment_length_min'] : 0 );
		$max    = (int) ( isset( $limits['comment_length_max'] ) ? $limits['comment_length_max'] : 0 );

		if ( $min > 0 && $len < $min ) {
			return false;
		}
		if ( $max > 0 && $len > $max ) {
			return false;
		}

		// 3. Rate Limiting (Database Query).
		if ( ! empty( $spam_protection['rate_limiting']['enabled'] ) ) {
			if ( ! self::check_rate_limit( $user_id, $vehicle_id, $spam_protection['rate_limiting'] ) ) {
				return false;
			}
		}

		// 4. Duplicate Detection (Database Query).
		if ( ! empty( $spam_protection['duplicate_detection']['enabled'] ) ) {
			if ( ! self::check_duplicate_detection( $user_id, $vehicle_id, $comment, $spam_protection['duplicate_detection'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Checks the number of comments within a time window.
	 *
	 * @param int   $user_id       User ID.
	 * @param int   $vehicle_id    Post ID.
	 * @param array $rate_limiting Rate limit settings.
	 * @return bool True if allowed, False if limit exceeded.
	 */
	private static function check_rate_limit( int $user_id, int $vehicle_id, array $rate_limiting ): bool {
		if ( empty( $rate_limiting['time_window'] ) || empty( $rate_limiting['max_attempts'] ) ) {
			return true;
		}

		$time_window  = absint( $rate_limiting['time_window'] ) * 60;
		$max_attempts = absint( $rate_limiting['max_attempts'] );

		if ( $time_window <= 0 || $max_attempts <= 0 ) {
			return true;
		}

		$timestamp_limit = gmdate( 'Y-m-d H:i:s', time() - $time_window );

		$args = array(
			'post_id'                   => $vehicle_id,
			'date_query'                => array(
				array(
					'after' => $timestamp_limit,
				),
			),
			'number'                    => $max_attempts + 1,
			'fields'                    => 'ids',
			'status'                    => array( 'approve', 'hold' ), // Count valid and pending.
			'no_found_rows'             => true,
			'update_comment_meta_cache' => false,
			'update_comment_post_cache' => false,
		);

		// Apply User ID check if logged in.
		if ( $user_id > 0 ) {
			$args['user_id'] = $user_id;
		} else {
			// For guests, we rely on IP address (Standard WP behavior).
			// Note: This requires the server to correctly pass REMOTE_ADDR.
			if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
				$ip = filter_var( wp_unslash( $_SERVER['REMOTE_ADDR'] ), FILTER_VALIDATE_IP );
				if ( false !== $ip ) {
					$args['author_ip'] = $ip;
				}
			}
		}

		$recent_comments = get_comments( $args );

		return count( $recent_comments ) < $max_attempts;
	}

	/**
	 * Checks if identical content was submitted recently.
	 *
	 * @param int    $user_id             User ID.
	 * @param int    $vehicle_id          Post ID.
	 * @param string $comment             Content.
	 * @param array  $duplicate_detection Settings.
	 * @return bool True if allowed, False if duplicate found.
	 */
	private static function check_duplicate_detection( int $user_id, int $vehicle_id, string $comment, array $duplicate_detection ): bool {
		if ( empty( $duplicate_detection['check_content'] ) ) {
			return true;
		}

		$time_window    = absint( isset( $duplicate_detection['time_window'] ) ? $duplicate_detection['time_window'] : 0 ) * 60;
		$max_duplicates = absint( isset( $duplicate_detection['max_duplicates'] ) ? $duplicate_detection['max_duplicates'] : 0 );

		if ( $time_window <= 0 ) {
			return true;
		}

		$clean_comment   = wp_strip_all_tags( $comment );
		$timestamp_limit = gmdate( 'Y-m-d H:i:s', time() - $time_window );

		// Note: get_comments 'search' parameter is NOT exact match, so we fetch and compare manually.
		$args = array(
			'post_id'                   => $vehicle_id,
			'date_query'                => array(
				array(
					'after' => $timestamp_limit,
				),
			),
			'number'                    => 100, // Fetch more to check for exact matches.
			'fields'                    => 'all', // Need full comment object for content comparison.
			'status'                    => array( 'approve', 'hold' ),
			'no_found_rows'             => true,
			'update_comment_meta_cache' => false,
			'update_comment_post_cache' => false,
		);

		if ( $user_id > 0 ) {
			$args['user_id'] = $user_id;
		} else {
			// Check by IP for guests to prevent spam bots from flooding same comment.
			if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
				$ip = filter_var( wp_unslash( $_SERVER['REMOTE_ADDR'] ), FILTER_VALIDATE_IP );
				if ( false !== $ip ) {
					$args['author_ip'] = $ip;
				}
			}
		}

		$comments = get_comments( $args );

		// Manually count exact matches.
		$exact_matches = 0;
		foreach ( $comments as $existing_comment ) {
			if ( trim( wp_strip_all_tags( $existing_comment->comment_content ) ) === $clean_comment ) {
				++$exact_matches;
			}
		}

		return $exact_matches <= $max_duplicates;
	}

	/**
	 * Checks comment content against spam words.
	 *
	 * @param string $comment    Content.
	 * @param array  $spam_words List of forbidden words.
	 * @return bool True if safe, False if spam word found.
	 */
	private static function check_spam_words( string $comment, array $spam_words ): bool {
		if ( empty( $comment ) || empty( $spam_words ) ) {
			return true;
		}

		// Use mb_stripos for case-insensitive matching.
		// Note: This is a simple substring match. For strict word boundaries, Regex would be needed.
		foreach ( $spam_words as $spam_word ) {
			if ( ! is_string( $spam_word ) || empty( trim( $spam_word ) ) ) {
				continue;
			}

			if ( false !== mb_stripos( $comment, trim( $spam_word ) ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Helper: Get auto-approve setting.
	 *
	 * @return int 1 or 0
	 */
	public static function get_comment_approval_status(): int {
		return (bool) self::get_setting( 'approval.auto_approve' ) ? 1 : 0;
	}

	/**
	 * Helper: Get pagination limit.
	 *
	 * @return int
	 */
	public static function get_comments_per_page(): int {
		return (int) self::get_setting( 'limits.comments_per_page', 10 );
	}

	/**
	 * Helper: Get cache duration setting.
	 *
	 * @return int
	 */
	public static function get_cache_duration(): int {
		$cache = self::get_setting( 'cache' );
		return ! empty( $cache['enabled'] ) ? (int) ( isset( $cache['duration'] ) ? $cache['duration'] : 0 ) : 0;
	}
}
