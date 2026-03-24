<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Auth;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Settings\Core\SettingsCore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Account Lockout Manager
 *
 * Handles account lockout after failed login attempts
 *
 * @since 4.0.0
 */
final class LockoutManager {

	/**
	 * Default maximum failed login attempts
	 */
	public const DEFAULT_MAX_ATTEMPTS = 5;

	/**
	 * Default lockout duration in minutes
	 */
	public const DEFAULT_LOCKOUT_DURATION = 30;

	/**
	 * Initialize lockout management
	 */
	public static function init(): void {
		add_action( 'wp_login_failed', array( self::class, 'handle_failed_login' ) );
		add_action( 'wp_login', array( self::class, 'clear_failed_attempts' ) );
		add_filter( 'authenticate', array( self::class, 'check_account_lockout' ), 30, 3 );
	}

	/**
	 * Handle failed login attempt
	 */
	public static function handle_failed_login( string $username ): void {
		$user = get_user_by( 'login', $username );
		if ( ! $user ) {
			return;
		}

		$user_id         = $user->ID;
		$failed_attempts = get_user_meta( $user_id, 'mhm_failed_login_attempts', true );
		$failed_attempts = $failed_attempts ? (int) $failed_attempts : 0;
		++$failed_attempts;

		update_user_meta( $user_id, 'mhm_failed_login_attempts', $failed_attempts );
		update_user_meta( $user_id, 'mhm_last_failed_login', time() );

		// Check if brute force protection is enabled
		$protection_enabled = SettingsCore::get( 'mhm_rentiva_brute_force_protection', '0' ) === '1';
		if ( ! $protection_enabled ) {
			return;
		}

		// Check if account should be locked
		$lockout_attempts = SettingsCore::get( 'mhm_rentiva_max_login_attempts', self::DEFAULT_MAX_ATTEMPTS );
		if ( $failed_attempts >= (int) $lockout_attempts ) {
			self::lock_account( $user_id );
		}
	}

	/**
	 * Clear failed attempts on successful login
	 */
	public static function clear_failed_attempts( string $user_login, \WP_User $user ): void {
		delete_user_meta( $user->ID, 'mhm_failed_login_attempts' );
		delete_user_meta( $user->ID, 'mhm_last_failed_login' );
		delete_user_meta( $user->ID, 'mhm_account_locked' );
		delete_user_meta( $user->ID, 'mhm_lockout_expires' );
	}

	/**
	 * Check if account is locked during authentication
	 */
	public static function check_account_lockout( $user, string $username, string $password ) {
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		if ( ! $user ) {
			return $user;
		}

		$user_id = $user->ID;

		// Check if account is locked
		if ( self::is_account_locked( $user_id ) ) {
			$lockout_duration = SettingsCore::get( 'mhm_rentiva_login_lockout_duration', self::DEFAULT_LOCKOUT_DURATION );
			$lockout_minutes  = (int) $lockout_duration;

			return new \WP_Error(
				'account_locked',
				sprintf(
					/* translators: %d: number of minutes. */
					__( 'Account is locked due to too many failed login attempts. Please try again in %d minutes.', 'mhm-rentiva' ),
					$lockout_minutes
				)
			);
		}

		return $user;
	}

	/**
	 * Lock user account
	 */
	public static function lock_account( int $user_id ): void {
		$lockout_duration = SettingsCore::get( 'mhm_rentiva_login_lockout_duration', self::DEFAULT_LOCKOUT_DURATION );
		$lockout_seconds  = (int) $lockout_duration * 60;

		update_user_meta( $user_id, 'mhm_account_locked', '1' );
		update_user_meta( $user_id, 'mhm_lockout_expires', time() + $lockout_seconds );

		// Log the lockout
		if ( class_exists( '\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger' ) ) {
			\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::warning(
				sprintf(
					/* translators: %d: user ID. */
					__( 'Account locked for user ID: %d', 'mhm-rentiva' ),
					$user_id
				),
				array(
					'user_id'          => $user_id,
					'lockout_duration' => $lockout_duration,
				),
				\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::CATEGORY_SECURITY
			);
		}
	}

	/**
	 * Check if account is currently locked
	 */
	public static function is_account_locked( int $user_id ): bool {
		$locked = get_user_meta( $user_id, 'mhm_account_locked', true );
		if ( $locked !== '1' ) {
			return false;
		}

		$expires = get_user_meta( $user_id, 'mhm_lockout_expires', true );
		if ( ! $expires ) {
			return false;
		}

		// Check if lockout has expired
		if ( time() > (int) $expires ) {
			self::unlock_account( $user_id );
			return false;
		}

		return true;
	}

	/**
	 * Unlock user account
	 */
	public static function unlock_account( int $user_id ): void {
		delete_user_meta( $user_id, 'mhm_account_locked' );
		delete_user_meta( $user_id, 'mhm_lockout_expires' );
		delete_user_meta( $user_id, 'mhm_failed_login_attempts' );
		delete_user_meta( $user_id, 'mhm_last_failed_login' );
	}

	/**
	 * Get remaining lockout time in minutes
	 */
	public static function get_remaining_lockout_time( int $user_id ): int {
		if ( ! self::is_account_locked( $user_id ) ) {
			return 0;
		}

		$expires = get_user_meta( $user_id, 'mhm_lockout_expires', true );
		if ( ! $expires ) {
			return 0;
		}

		$remaining = (int) $expires - time();
		return max( 0, (int) ( $remaining / 60 ) );
	}

	/**
	 * Get failed login attempts count
	 */
	public static function get_failed_attempts( int $user_id ): int {
		$attempts = get_user_meta( $user_id, 'mhm_failed_login_attempts', true );
		return $attempts ? (int) $attempts : 0;
	}

	/**
	 * Get lockout settings
	 */
	public static function get_lockout_settings(): array {
		return array(
			'max_attempts'     => SettingsCore::get( 'mhm_rentiva_max_login_attempts', self::DEFAULT_MAX_ATTEMPTS ),
			'duration_minutes' => SettingsCore::get( 'mhm_rentiva_login_lockout_duration', self::DEFAULT_LOCKOUT_DURATION ),
		);
	}
}
