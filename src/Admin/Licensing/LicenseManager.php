<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Licensing;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LicenseManager {


	public const OPTION    = 'mhm_rentiva_license';
	public const CRON_HOOK = 'mhm_rentiva_license_daily';

	private static ?self $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return self
	 */
	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Register license manager hooks
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'maybeHandleActions' ) );
		add_action( self::CRON_HOOK, array( $this, 'cronValidate' ) );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 3600, 'daily', self::CRON_HOOK );
		}

		add_action( 'admin_notices', array( $this, 'adminNotices' ) );
	}

	/**
	 * Deactivate plugin hook - cleanup scheduled events
	 */
	public static function deactivatePluginHook(): void {
		$ts = wp_next_scheduled( self::CRON_HOOK );
		if ( $ts ) {
			wp_unschedule_event( $ts, self::CRON_HOOK );
		}
	}

	/**
	 * Get license data
	 *
	 * @return array License data
	 */
	public function get(): array {
		$opt = get_option( self::OPTION, array() );
		return is_array( $opt ) ? $opt : array();
	}

	/**
	 * Save license data
	 *
	 * @param array $data License data to save
	 */
	public function save( array $data ): void {
		update_option( self::OPTION, $data, false );
	}

	/**
	 * Set license data
	 *
	 * @param array $data License data
	 */
	public function setLicenseData( array $data ): void {
		$this->save( $data );
	}

	/**
	 * Clear license data
	 */
	public function clearLicense(): void {
		$this->save( array() );
	}

	/**
	 * Get license key
	 *
	 * @return string License key
	 */
	public function getKey(): string {
		$o = $this->get();
		return (string) ( $o['key'] ?? '' );
	}

	/**
	 * Check if license is active
	 *
	 * @return bool True if active
	 */
	public function isActive(): bool {
		$o                 = $this->get();
		$has_license_key   = ! empty( $o['key'] );
		$has_activation_id = ! empty( $o['activation_id'] );

		// BUG FIX: If there's a license key but no activation_id, license is not truly active
		// This means the license was never successfully activated on the server
		if ( $has_license_key && ! $has_activation_id ) {
			return false; // License key exists but not activated on server
		}

		// Check if developer mode is manually disabled
		$disable_dev_mode = get_option( 'mhm_rentiva_disable_dev_mode', false );

		// Only automatic developer mode (secure) - unless manually disabled
		// Developer mode should only work if there's NO real license key
		if ( ! $disable_dev_mode && $this->isDevelopmentEnvironment() && ! $has_license_key ) {
			return true; // Developer mode only if no real license exists
		}

		// If we have a license key, check server status immediately
		// Use transient cache to prevent excessive API calls (30 seconds cache)
		if ( $has_license_key ) {
			$cache_key     = 'mhm_rentiva_license_status_' . md5( $o['key'] . $o['activation_id'] );
			$cached_status = get_transient( $cache_key );

			if ( $cached_status !== false ) {
				// Use cached status (30 seconds)
				return (bool) $cached_status;
			}

			// Validate with server immediately
			// Use transient lock to prevent multiple simultaneous validations
			$validation_transient = 'mhm_rentiva_license_validating';
			if ( ! get_transient( $validation_transient ) ) {
				set_transient( $validation_transient, true, 10 ); // Lock for 10 seconds

				// Validate synchronously to get immediate result
				$this->validate();

				// Refresh license data after validation
				$o = $this->get();
				delete_transient( $validation_transient );
			} else {
				// Another validation is in progress, use current data
				$o = $this->get();
			}

			// Check status after validation
			$is_active = false;
			if ( ( $o['status'] ?? '' ) === 'active' ) {
				$exp = $o['expires_at'] ?? null;
				if ( $exp && is_numeric( $exp ) ) {
					$is_active = (int) $exp > time() && ! empty( $o['activation_id'] );
				} else {
					$is_active = ! empty( $o['activation_id'] );
				}
			}

			// Cache result for 30 seconds to prevent excessive API calls
			set_transient( $cache_key, $is_active ? 1 : 0, 30 );

			return $is_active;
		}

		return false; // No license key, not active
	}

	/**
	 * Handle license actions
	 */
	public function maybeHandleActions(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! isset( $_POST['mhm_license_action'] ) ) {
			return;
		}
		check_admin_referer( 'mhm_rentiva_license_action', 'mhm_license_nonce' );

		$action = sanitize_text_field( (string) ( $_POST['mhm_license_action'] ?? '' ) );
		if ( $action === 'activate' ) {
			$key = sanitize_text_field( (string) ( $_POST['mhm_license_key'] ?? '' ) );
			$res = $this->activate( $key );
			$this->flash( $res instanceof WP_Error ? $res->get_error_message() : __( 'License activated.', 'mhm-rentiva' ), ! ( $res instanceof WP_Error ) );
		} elseif ( $action === 'deactivate' ) {
			$res = $this->deactivate();
			$this->flash( $res instanceof WP_Error ? $res->get_error_message() : __( 'License deactivated.', 'mhm-rentiva' ), ! ( $res instanceof WP_Error ) );
		} elseif ( $action === 'validate' ) {
			$res = $this->validate();
			$this->flash( $res instanceof WP_Error ? $res->get_error_message() : __( 'License validated.', 'mhm-rentiva' ), ! ( $res instanceof WP_Error ) );
		}

		wp_safe_redirect( remove_query_arg( array( 'mhm_notice' ) ) );
		exit;
	}

	/**
	 * Activate license
	 *
	 * @param string $key License key
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function activate( string $key ) {
		if ( $key === '' ) {
			return new WP_Error( 'empty_key', __( 'Please enter a license key.', 'mhm-rentiva' ) );
		}

		$resp = $this->request(
			'/licenses/activate',
			array(
				'license_key' => $key,
				'site_hash'   => $this->siteHash(),
				'site_url'    => home_url(),
				'is_staging'  => $this->isStaging(),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		// Check if response has success field (error response)
		if ( isset( $resp['success'] ) && $resp['success'] === false ) {
			$error_code    = $resp['error'] ?? 'license_activation_failed';
			$error_message = $resp['message'] ?? __( 'License activation failed.', 'mhm-rentiva' );
			return new WP_Error( $error_code, $error_message );
		}

		$data = array(
			'key'           => $key,
			'status'        => $resp['status'] ?? 'active',
			'plan'          => $resp['plan'] ?? 'pro',
			'expires_at'    => isset( $resp['expires_at'] ) ? (int) $resp['expires_at'] : null,
			'activation_id' => $resp['activation_id'] ?? '',
			'token'         => $resp['token'] ?? '',
			'last_check_at' => time(),
		);
		$this->save( $data );
		return true;
	}

	/**
	 * Deactivate license
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function deactivate() {
		$o             = $this->get();
		$key           = $o['key'] ?? '';
		$activation_id = $o['activation_id'] ?? '';

		// Send deactivation request to license server
		if ( $key !== '' && $activation_id !== '' ) {
			$result = $this->request(
				'/licenses/deactivate',
				array(
					'license_key'   => $key,
					'activation_id' => $activation_id,
				)
			);

			// If server request fails, log but still clear local data
			if ( is_wp_error( $result ) ) {
				// Log error but continue with local deactivation
				error_log( 'License deactivation server request failed: ' . $result->get_error_message() );
			}
		}

		// Always clear local license data
		$this->save( array() );
		return true;
	}

	/**
	 * Validate license
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function validate() {
		$o   = $this->get();
		$key = $o['key'] ?? '';
		if ( $key === '' ) {
			return new WP_Error( 'no_key', __( 'No saved license key found.', 'mhm-rentiva' ) );
		}

		$resp = $this->request(
			'/licenses/validate',
			array(
				'license_key' => $key,
				'site_hash'   => $this->siteHash(),
			)
		);
		if ( is_wp_error( $resp ) ) {
			// If validation fails, clear activation_id to mark as inactive
			$o['activation_id'] = '';
			$o['status']        = 'inactive';
			$o['last_check_at'] = time();
			$this->save( $o );

			// Don't return error if called from isActive() (silent validation)
			$backtrace            = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 3 );
			$called_from_isactive = false;
			foreach ( $backtrace as $trace ) {
				if ( isset( $trace['function'] ) && $trace['function'] === 'isActive' ) {
					$called_from_isactive = true;
					break;
				}
			}

			if ( ! $called_from_isactive ) {
				return $resp;
			}

			return false; // Silent failure for isActive() calls
		}

		// BUG FIX: If server says inactive, clear activation_id
		$server_status = $resp['status'] ?? 'inactive';
		if ( $server_status !== 'active' ) {
			// Clear activation_id and set status to inactive
			$o['activation_id'] = '';
			$o['status']        = 'inactive';
			$o['last_check_at'] = time();
			$this->save( $o );

			// Don't return error if called from isActive() (silent validation)
			// Only return error if explicitly called for validation
			$backtrace            = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 3 );
			$called_from_isactive = false;
			foreach ( $backtrace as $trace ) {
				if ( isset( $trace['function'] ) && $trace['function'] === 'isActive' ) {
					$called_from_isactive = true;
					break;
				}
			}

			if ( ! $called_from_isactive ) {
				return new WP_Error( 'license_inactive', __( 'License is not active on this site.', 'mhm-rentiva' ) );
			}

			return false; // Silent failure for isActive() calls
		}

		$o['status']        = $resp['status'] ?? ( $o['status'] ?? 'inactive' );
		$o['plan']          = $resp['plan'] ?? ( $o['plan'] ?? null );
		$o['expires_at']    = isset( $resp['expires_at'] ) ? (int) $resp['expires_at'] : ( $o['expires_at'] ?? null );
		$o['last_check_at'] = time();
		// Keep activation_id if it exists, don't clear it on successful validation
		$this->save( $o );
		return true;
	}

	/**
	 * Cron job to validate license
	 */
	public function cronValidate(): void {
		$this->validate();
	}

	/**
	 * Make API request to license server
	 *
	 * @param string $path API path
	 * @param array  $body Request body
	 * @return array|WP_Error Response data or error
	 */
	private function request( string $path, array $body ) {
		$base = defined( 'MHM_RENTIVA_LICENSE_API_BASE' ) ? constant( 'MHM_RENTIVA_LICENSE_API_BASE' ) : 'https://api.maxhandmade.com/v1';
		$url  = rtrim( $base, '/' ) . $path;
		$args = array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
				'User-Agent'   => 'MHM-Rentiva/' . ( defined( 'MHM_RENTIVA_VERSION' ) ? MHM_RENTIVA_VERSION : 'dev' ),
			),
			'timeout' => 15,
			'body'    => wp_json_encode( $body ),
			'method'  => 'POST',
		);
		$r    = wp_remote_request( $url, $args );
		if ( is_wp_error( $r ) ) {
			/* translators: %s placeholder. */
			return new WP_Error( 'license_connection', sprintf( __( 'Could not connect to license server: %s', 'mhm-rentiva' ), $r->get_error_message() ) );
		}

		$code         = wp_remote_retrieve_response_code( $r );
		$body_content = wp_remote_retrieve_body( $r );
		$json         = json_decode( $body_content, true );

		// Handle error responses (400, 500, etc.)
		if ( $code >= 400 ) {
			$error_code    = 'license_http';
			$error_message = __( 'License server error.', 'mhm-rentiva' );

			if ( is_array( $json ) ) {
				// Extract error details from API response
				$error_code    = $json['error'] ?? $error_code;
				$error_message = $json['message'] ?? $error_message;
			} else {
				// If JSON decode failed, include raw response
				/* translators: 1: %d; 2: %s. */
				$error_message = sprintf( __( 'License server returned error (HTTP %1$d): %2$s', 'mhm-rentiva' ), $code, substr( $body_content, 0, 200 ) );
			}

			return new WP_Error( $error_code, $error_message );
		}

		// Handle success responses (200-299)
		if ( $code >= 200 && $code < 300 && is_array( $json ) ) {
			return $json;
		}

		// Fallback for unexpected responses
		/* translators: %d placeholder. */
		return new WP_Error( 'license_http', sprintf( __( 'Unexpected response from license server (HTTP %d).', 'mhm-rentiva' ), $code ) );
	}

	/**
	 * Generate site hash for license validation
	 *
	 * @return string Site hash
	 */
	private function siteHash(): string {
		$payload = array(
			'home' => home_url(),
			'site' => site_url(),
			'wp'   => get_bloginfo( 'version' ),
			'php'  => PHP_VERSION,
		);
		return hash( 'sha256', wp_json_encode( $payload ) );
	}

	/**
	 * Check if site is staging environment
	 *
	 * @return bool True if staging
	 */
	private function isStaging(): bool {
		$host = wp_parse_url( home_url(), PHP_URL_HOST ) ?: '';
		foreach ( array( '.local', '.test', '.dev', '.staging', 'localhost' ) as $p ) {
			if ( $host === $p || str_ends_with( $host, $p ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Development environment check (secure automatic detection)
	 *
	 * @return bool True if development environment
	 */
	public function isDevelopmentEnvironment(): bool {
		// 1. Host check (localhost, .local, .dev, .test, .staging)
		$host        = wp_parse_url( home_url(), PHP_URL_HOST ) ?: '';
		$dev_domains = array( '.local', '.test', '.dev', '.staging' );

		// localhost check
		if ( in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
			return true;
		}

		// Local domain check
		foreach ( $dev_domains as $domain ) {
			if ( str_ends_with( $host, $domain ) ) {
				return true;
			}
		}

		// 2. Local servers like XAMPP, WAMP, MAMP (only with localhost)
		if ( in_array( $host, array( 'localhost', '127.0.0.1' ), true ) ) {
			$server_software = $_SERVER['SERVER_SOFTWARE'] ?? '';
			if (
				stripos( $server_software, 'xampp' ) !== false ||
				stripos( $server_software, 'wamp' ) !== false ||
				stripos( $server_software, 'mamp' ) !== false ||
				stripos( $server_software, 'lamp' ) !== false
			) {
				return true;
			}
		}

		// 3. Port check (only with localhost)
		if ( in_array( $host, array( 'localhost', '127.0.0.1' ), true ) ) {
			$port = wp_parse_url( home_url(), PHP_URL_PORT );
			if ( in_array( $port, array( '8080', '8081', '3000', '3001', '8000', '8001' ), true ) ) {
				return true;
			}
		}

		// 4. WordPress debug mode (only with localhost)
		if ( in_array( $host, array( 'localhost', '127.0.0.1' ), true ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return true;
		}

		// 5. WordPress development environment (only with localhost)
		if (
			in_array( $host, array( 'localhost', '127.0.0.1' ), true ) &&
			defined( 'WP_ENV' ) && in_array( constant( 'WP_ENV' ), array( 'development', 'dev', 'local' ), true )
		) {
			return true;
		}

		return false;
	}

	/**
	 * Flash message for admin notices
	 *
	 * @param string $msg Message
	 * @param bool   $ok Success status
	 */
	private function flash( string $msg, bool $ok ): void {
		$key = $ok ? 'success' : 'error';
		set_transient( 'mhm_license_notice', array( $key, $msg ), 60 );
	}

	/**
	 * Display admin notices
	 */
	public function adminNotices(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( $n = get_transient( 'mhm_license_notice' ) ) {
			delete_transient( 'mhm_license_notice' );
			$class = $n[0] === 'success' ? 'notice-success' : 'notice-error';
			echo '<div class="notice ' . esc_attr( $class ) . '"><p>' . esc_html( $n[1] ) . '</p></div>';
		}

		$o = $this->get();
		if ( ( $o['status'] ?? '' ) === 'active' && ! empty( $o['expires_at'] ) && ( (int) $o['expires_at'] - time() ) < 14 * DAY_IN_SECONDS ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Your Rentiva license will expire soon. Please renew for Pro features and updates.', 'mhm-rentiva' ) . '</p></div>';
		}
	}
	/**
	 * Get license expiry date formatted
	 *
	 * @param string $format Date format
	 * @return string Formatted date or 'Lifetime'
	 */
	public function getExpiryDate( string $format = 'd.m.Y' ): string {
		if ( ! $this->isActive() ) {
			return '-';
		}

		$o          = $this->get();
		$expires_at = $o['expires_at'] ?? null;

		if ( empty( $expires_at ) ) {
			return __( 'Lifetime', 'mhm-rentiva' );
		}

		return date_i18n( $format, (int) $expires_at );
	}
}
