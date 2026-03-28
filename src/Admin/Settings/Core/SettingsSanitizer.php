<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Core;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SettingsSanitizer
 *
 * Handles sanitization and validation of all plugin settings.
 * Refactored for PHP 8.2+ and WordPress Coding Standards.
 *
 * @package MHMRentiva\Admin\Settings\Core
 */
final class SettingsSanitizer {



	/**
	 * Entry point for sanitizing settings based on tab context.
	 *
	 * @param mixed $input
	 * @return array
	 */
	public static function sanitize( mixed $input ): array {
		// 1. Initialize with current DB values to preserve untargeted settings.
		$current_values = (array) \get_option( 'mhm_rentiva_settings', array() );
		$defaults       = SettingsCore::get_defaults();

		if ( ! \is_array( $input ) ) {
			return $current_values;
		}

		// 2. Recursive null cleanup to prevent PHP 8.x strlen null errors
		self::clean_recursive( $input );

		$out         = $current_values;
		$current_tab = $input['current_active_tab'] ?? '';

		// 3. Contextual Sanitization via Match (PHP 8.0+)
		$sanitized_batch = match ( $current_tab ) {
			'general'     => self::process_general_tab( $input, $defaults ),
			'vehicle'     => array_merge(
				self::sanitize_vehicle_management_settings( $input, $defaults ),
				self::sanitize_vehicle_pricing_settings( $input, $defaults ),
				self::sanitize_comparison_settings( $input, $current_values )
			),
			'booking'     => self::sanitize_booking_settings( $input, $defaults ),
			'customer'    => self::sanitize_customer_management_settings( $input, $defaults ),
			'email'       => array_merge(
				self::sanitize_email_brand_settings( $input, $defaults ),
				self::sanitize_email_sending_settings( $input, $defaults )
			),
			'payment'     => self::sanitize_offline_settings( $input, $defaults ),
			'system'      => self::sanitize_system_settings( $input, $defaults ),
			'frontend'    => self::sanitize_frontend_settings( $input, $defaults ),
			'transfer'    => self::sanitize_transfer_settings( $input, $defaults ),
			'comments'           => self::sanitize_comments_settings( $input, $current_values ),
			'addons'             => self::sanitize_addon_settings( $input, $defaults ),
			'vendor-marketplace' => self::sanitize_vendor_marketplace_settings( $input, $defaults ),
			default              => $input, // Fallback for programmatic updates
		};

		$out = array_merge( $out, $sanitized_batch );

		// 4. Global Special Handling (Numeric constraints)
		$out = self::apply_global_constraints( $input, $out, $current_tab );

		// 5. Cache Management
		if ( class_exists( '\MHMRentiva\Admin\Core\Utilities\CacheManager' ) ) {
			\MHMRentiva\Admin\Core\Utilities\CacheManager::clear_settings_cache();
		}

		return $out;
	}

	/**
	 * Helper to process General Tab with specific logic
	 */
	private static function process_general_tab( array $input, array $defaults ): array {
		$out = array();

		if ( isset( $input['mhm_rentiva_currency'] ) ) {
			$out['mhm_rentiva_currency'] = strtoupper( substr( self::safe_text( $input['mhm_rentiva_currency'] ), 0, 4 ) );
		}

		$fields = array( 'mhm_rentiva_currency_position', 'mhm_rentiva_date_format', 'mhm_rentiva_time_format' );
		foreach ( $fields as $field ) {
			if ( isset( $input[ $field ] ) ) {
				$out[ $field ] = self::safe_text( $input[ $field ] );
			}
		}

		if ( isset( $input['mhm_rentiva_dark_mode'] ) ) {
			$out['mhm_rentiva_dark_mode'] = self::sanitize_dark_mode_option( $input['mhm_rentiva_dark_mode'] );
		}

		return array_merge(
			$out,
			self::sanitize_site_info_settings( $input, $defaults ),
			self::sanitize_datetime_settings( $input, $defaults )
		);
	}

	/**
	 * Apply constraints to specific keys globally.
	 *
	 * @param array  $input Input data.
	 * @param array  $out   Sanitized output.
	 * @param string $tab   Current tab.
	 * @return array Modified output.
	 */
	private static function apply_global_constraints( array $input, array $out, string $tab ): array {
		if ( isset( $input['mhm_rentiva_booking_payment_deadline_minutes'] ) && 'booking' === $tab ) {
			$out['mhm_rentiva_booking_payment_deadline_minutes'] = self::clamp_value( (int) $input['mhm_rentiva_booking_payment_deadline_minutes'], 0, 1440 );
		}

		if ( isset( $input['mhm_rentiva_booking_payment_gateway_timeout_minutes'] ) ) {
			$out['mhm_rentiva_booking_payment_gateway_timeout_minutes'] = self::clamp_value( (int) $input['mhm_rentiva_booking_payment_gateway_timeout_minutes'], 0, 60 );
		}

		return $out;
	}

	/**
	 * Safe sanitization for text fields (Handles NULL and non-string types)
	 */
	public static function safe_text( mixed $value ): string {
		if ( null === $value || '' === $value || \is_array( $value ) ) {
			return '';
		}
		return \sanitize_text_field( (string) $value );
	}

	/**
	 * Sanitize dark mode option to canonical values.
	 *
	 * @param mixed  $value   Raw value.
	 * @param string $default Default canonical mode.
	 * @return string
	 */
	public static function sanitize_dark_mode_option( mixed $value, string $default = 'auto' ): string {
		$normalized = strtolower( self::safe_text( $value ) );

		if ( '' === $normalized ) {
			return $default;
		}

		if ( in_array( $normalized, array( 'dark', '1', 'on', 'yes', 'true' ), true ) ) {
			return 'dark';
		}

		if ( in_array( $normalized, array( 'light', '0', 'off', 'no', 'false' ), true ) ) {
			return 'light';
		}

		if ( 'auto' === $normalized ) {
			return 'auto';
		}

		return $default;
	}

	/**
	 * Recursive cleanup for arrays
	 */
	private static function clean_recursive( array &$array ): void {
		foreach ( $array as &$value ) {
			if ( null === $value ) {
				$value = '';
			} elseif ( \is_array( $value ) ) {
				self::clean_recursive( $value );
			}
		}
	}

	/**
	 * Standard Boolean Helper
	 */
	private static function get_bool( array $input, string $key, bool $default = false ): string {
		$val = isset( $input[ $key ] ) ? $input[ $key ] : null;
		if ( $val === '1' || $val === 1 || $val === true || $val === 'on' ) {
			return '1';
		}
		return '0';
	}

	/**
	 * Sanitize standalone addon option array (`mhm_rentiva_addon_settings`).
	 *
	 * @param mixed $input Raw option payload.
	 * @return array<string,string>
	 */
	public static function sanitize_addon_settings_option( mixed $input ): array {
		$defaults = \MHMRentiva\Admin\Addons\AddonSettings::defaults();

		if ( ! \is_array( $input ) ) {
			$input = array();
		}

		$to_bool_string = static function ( mixed $value ): string {
			if ( $value === '1' || $value === 1 || $value === true || $value === 'on' || $value === 'yes' ) {
				return '1';
			}
			return '0';
		};

		$display_order  = isset( $input['display_order'] ) ? self::safe_text( $input['display_order'] ) : '';
		$allowed_orders = array( 'price_asc', 'price_desc', 'name_asc', 'name_desc', 'menu_order' );

		return array(
			'system_enabled' => $to_bool_string( $input['system_enabled'] ?? null ),
			'show_prices'    => $to_bool_string( $input['show_prices'] ?? null ),
			'allow_multiple' => $to_bool_string( $input['allow_multiple'] ?? null ),
			'display_order'  => self::validate_enum( $display_order, $allowed_orders, (string) $defaults['display_order'] ),
		);
	}

	/**
	 * Standard Integer Helper with clamping.
	 *
	 * @param array  $input   Input data.
	 * @param string $key     Setting key.
	 * @param int    $default Default value.
	 * @param int    $min     Minimum value.
	 * @param int    $max     Maximum value.
	 * @return int Clamped integer value.
	 */
	private static function get_int( array $input, string $key, int $default, int $min, int $max ): int {
		return isset( $input[ $key ] ) ? self::clamp_value( (int) $input[ $key ], $min, $max ) : $default;
	}

	/**
	 * Validate value against allowed list
	 */
	private static function validate_enum( string $value, array $allowed, string $default ): string {
		return \in_array( $value, $allowed, true ) ? $value : $default;
	}

	/**
	 * Sanitize System & Security Settings.
	 *
	 * @param array $input    Input data.
	 * @param array $defaults Default values.
	 * @return array Sanitized settings.
	 */
	private static function sanitize_system_settings( array $input, array $defaults ): array {
		return array(
			// Cache Settings.
			'mhm_rentiva_cache_enabled'                  => self::get_bool( $input, 'mhm_rentiva_cache_enabled' ),
			'mhm_rentiva_cache_default_ttl'              => max( 0.5, floatval( $input['mhm_rentiva_cache_default_ttl'] ?? 1.0 ) ),
			'mhm_rentiva_cache_lists_ttl'                => self::get_int( $input, 'mhm_rentiva_cache_lists_ttl', 5, 1, 60 ),
			'mhm_rentiva_cache_reports_ttl'              => self::get_int( $input, 'mhm_rentiva_cache_reports_ttl', 15, 1, 1440 ),
			'mhm_rentiva_cache_charts_ttl'               => self::get_int( $input, 'mhm_rentiva_cache_charts_ttl', 10, 1, 1440 ),
			'mhm_rentiva_wp_meta_query_limit'            => self::get_int( $input, 'mhm_rentiva_wp_meta_query_limit', 5, 1, 50 ),

			// IP Control.
			'mhm_rentiva_ip_whitelist_enabled'           => self::get_bool( $input, 'mhm_rentiva_ip_whitelist_enabled' ),
			'mhm_rentiva_ip_whitelist'                   => \sanitize_textarea_field( $input['mhm_rentiva_ip_whitelist'] ?? '' ),
			'mhm_rentiva_ip_blacklist_enabled'           => self::get_bool( $input, 'mhm_rentiva_ip_blacklist_enabled' ),
			'mhm_rentiva_ip_blacklist'                   => \sanitize_textarea_field( $input['mhm_rentiva_ip_blacklist'] ?? '' ),
			'mhm_rentiva_country_restriction_enabled'    => self::get_bool( $input, 'mhm_rentiva_country_restriction_enabled' ),
			'mhm_rentiva_allowed_countries'              => strtoupper( self::safe_text( $input['mhm_rentiva_allowed_countries'] ?? '' ) ),

			// Security Hardening.
			'mhm_rentiva_brute_force_protection'         => self::get_bool( $input, 'mhm_rentiva_brute_force_protection' ),
			'mhm_rentiva_max_login_attempts'             => self::get_int( $input, 'mhm_rentiva_max_login_attempts', 5, 3, 20 ),
			'mhm_rentiva_login_lockout_duration'         => self::get_int( $input, 'mhm_rentiva_login_lockout_duration', 30, 5, 1440 ),
			'mhm_rentiva_sql_injection_protection'       => self::get_bool( $input, 'mhm_rentiva_sql_injection_protection' ),
			'mhm_rentiva_xss_protection'                 => self::get_bool( $input, 'mhm_rentiva_xss_protection' ),
			'mhm_rentiva_csrf_protection'                => self::get_bool( $input, 'mhm_rentiva_csrf_protection' ),

			// Rate Limiting.
			'mhm_rentiva_rate_limit_enabled'             => self::get_bool( $input, 'mhm_rentiva_rate_limit_enabled' ),
			'mhm_rentiva_rate_limit_block_duration'      => self::get_int( $input, 'mhm_rentiva_rate_limit_block_duration', 15, 1, 1440 ),
			'mhm_rentiva_rate_limit_requests_per_minute' => self::get_int( $input, 'mhm_rentiva_rate_limit_requests_per_minute', 60, 10, 1000 ),
			'mhm_rentiva_rate_limit_booking_per_minute'  => self::get_int( $input, 'mhm_rentiva_rate_limit_booking_per_minute', 5, 1, 100 ),
			'mhm_rentiva_rate_limit_payment_per_minute'  => self::get_int( $input, 'mhm_rentiva_rate_limit_payment_per_minute', 3, 1, 50 ),

			// Maintenance.
			'mhm_rentiva_log_level'                      => self::validate_enum( $input['mhm_rentiva_log_level'] ?? '', array( 'error', 'warning', 'info', 'debug' ), 'error' ),
			'mhm_rentiva_log_cleanup_enabled'            => self::get_bool( $input, 'mhm_rentiva_log_cleanup_enabled' ),
			'mhm_rentiva_log_retention_days'             => self::get_int( $input, 'mhm_rentiva_log_retention_days', 30, 1, 365 ),
			'mhm_rentiva_debug_mode'                     => self::get_bool( $input, 'mhm_rentiva_debug_mode' ),
			'mhm_rentiva_clean_data_on_uninstall'        => self::get_bool( $input, 'mhm_rentiva_clean_data_on_uninstall' ),
		);
	}

	private static function sanitize_vehicle_management_settings( array $input, array $defaults ): array {
		$url_base = \sanitize_title( $input['mhm_rentiva_vehicle_url_base'] ?? ( $defaults['mhm_rentiva_vehicle_url_base'] ?? 'vehicle' ) );
		$out = array(
			'mhm_rentiva_vehicle_url_base'             => $url_base ?: 'vehicle',
			'mhm_rentiva_vehicle_base_price'           => max( 0.1, floatval( $input['mhm_rentiva_vehicle_base_price'] ?? ( $defaults['mhm_rentiva_vehicle_base_price'] ?? 1.0 ) ) ),
			'mhm_rentiva_vehicle_weekend_multiplier'   => max( 0.1, floatval( $input['mhm_rentiva_vehicle_weekend_multiplier'] ?? ( $defaults['mhm_rentiva_vehicle_weekend_multiplier'] ?? 1.0 ) ) ),
			'mhm_rentiva_vehicle_tax_inclusive'        => self::get_bool( $input, 'mhm_rentiva_vehicle_tax_inclusive' ),
			'mhm_rentiva_vehicle_tax_rate'             => self::clamp_value( floatval( $input['mhm_rentiva_vehicle_tax_rate'] ?? 0 ), 0, 100 ),
			'mhm_rentiva_vehicle_cards_per_page'       => self::get_int( $input, 'mhm_rentiva_vehicle_cards_per_page', 12, 1, 100 ),
			'mhm_rentiva_vehicle_default_sort'         => self::validate_enum( $input['mhm_rentiva_vehicle_default_sort'] ?? '', array( 'price_asc', 'price_desc', 'name_asc', 'name_desc', 'year_desc', 'year_asc' ), 'price_asc' ),
			'mhm_rentiva_vehicle_min_rental_days'      => self::get_int( $input, 'mhm_rentiva_vehicle_min_rental_days', 1, 1, 365 ),
			'mhm_rentiva_vehicle_max_rental_days'      => self::get_int( $input, 'mhm_rentiva_vehicle_max_rental_days', 365, 1, 365 ),
			'mhm_rentiva_vehicle_advance_booking_days' => self::get_int( $input, 'mhm_rentiva_vehicle_advance_booking_days', 365, 1, 365 ),
			'mhm_rentiva_vehicle_allow_same_day'       => self::get_bool( $input, 'mhm_rentiva_vehicle_allow_same_day' ),
		);

		// Only overwrite card/detail field selections when the key is explicitly present in the POST.
		// If absent (e.g. all checkboxes unchecked or form submitted from a different section),
		// omit it so array_merge() preserves the existing DB value instead of clearing it.
		if ( isset( $input['mhm_rentiva_vehicle_card_fields'] ) ) {
			$out['mhm_rentiva_vehicle_card_fields'] = VehicleFeatureHelper::sanitize_card_field_selection( $input['mhm_rentiva_vehicle_card_fields'] );
		}
		if ( isset( $input['mhm_rentiva_vehicle_detail_fields'] ) ) {
			$out['mhm_rentiva_vehicle_detail_fields'] = VehicleFeatureHelper::sanitize_card_field_selection( $input['mhm_rentiva_vehicle_detail_fields'] );
		}

		return $out;
	}

	private static function sanitize_vehicle_pricing_settings( array $input, array $defaults ): array {
		$current_settings = (array) \get_option( 'mhm_rentiva_settings', array() );
		$current_pricing  = $current_settings['vehicle_pricing'] ?? ( $defaults['vehicle_pricing'] ?? array() );

		if ( isset( $input['vehicle_pricing'] ) && \is_array( $input['vehicle_pricing'] ) ) {
			$in = $input['vehicle_pricing'];

			if ( isset( $in['deposit_settings'] ) && \is_array( $in['deposit_settings'] ) ) {
				$dep                                 = $in['deposit_settings'];
				$current_pricing['deposit_settings'] = array(
					'enable_deposit'          => (bool) ( $dep['enable_deposit'] ?? false ),
					'deposit_type'            => self::safe_text( $dep['deposit_type'] ?? 'both' ),
					'allow_no_deposit'        => (bool) ( $dep['allow_no_deposit'] ?? true ),
					'required_for_booking'    => (bool) ( $dep['required_for_booking'] ?? false ),
					'show_deposit_in_listing' => (bool) ( $dep['show_deposit_in_listing'] ?? true ),
					'show_deposit_in_detail'  => (bool) ( $dep['show_deposit_in_detail'] ?? true ),
					'deposit_refund_policy'   => SettingsHelper::sanitize_field( $dep['deposit_refund_policy'] ?? '', 'textarea' ),
					'deposit_payment_methods' => \is_array( $dep['deposit_payment_methods'] ?? null )
						? array_map( array( self::class, 'safe_text' ), $dep['deposit_payment_methods'] )
						: array( 'credit_card', 'cash', 'bank_transfer' ),
				);
			}

			if ( isset( $in['seasonal_multipliers'] ) && \is_array( $in['seasonal_multipliers'] ) ) {
				foreach ( $in['seasonal_multipliers'] as $key => $season ) {
					if ( isset( $season['multiplier'] ) ) {
						$current_pricing['seasonal_multipliers'][ $key ]['multiplier'] = floatval( $season['multiplier'] );
					}
				}
			}

			if ( isset( $in['discount_options'] ) && \is_array( $in['discount_options'] ) ) {
				foreach ( $in['discount_options'] as $key => $discount ) {
					$current_pricing['discount_options'][ $key ] = array(
						'enabled'          => (bool) ( $discount['enabled'] ?? false ),
						'min_days'         => \absint( $discount['min_days'] ?? 0 ),
						'advance_days'     => \absint( $discount['advance_days'] ?? 0 ),
						'discount_percent' => self::clamp_value( \absint( $discount['discount_percent'] ?? 0 ), 0, 100 ),
					);
				}
			}
		}

		return array( 'vehicle_pricing' => $current_pricing );
	}

	private static function sanitize_booking_settings( array $input, array $defaults ): array {
		return array(
			'mhm_rentiva_booking_cancellation_deadline_hours' => self::get_int( $input, 'mhm_rentiva_booking_cancellation_deadline_hours', 24, 1, 168 ),
			'mhm_rentiva_booking_payment_deadline_minutes' => self::get_int( $input, 'mhm_rentiva_booking_payment_deadline_minutes', 30, 0, 1440 ),
			'mhm_rentiva_booking_auto_cancel_enabled'      => self::get_bool( $input, 'mhm_rentiva_booking_auto_cancel_enabled' ),
			'mhm_rentiva_booking_buffer_time'              => self::get_int( $input, 'mhm_rentiva_booking_buffer_time', 60, 0, 1440 ),
			'mhm_rentiva_booking_send_confirmation_emails' => self::get_bool( $input, 'mhm_rentiva_booking_send_confirmation_emails' ),
			'mhm_rentiva_booking_send_reminder_emails'     => self::get_bool( $input, 'mhm_rentiva_booking_send_reminder_emails' ),
			'mhm_rentiva_booking_admin_notifications'      => self::get_bool( $input, 'mhm_rentiva_booking_admin_notifications' ),
			'mhm_rentiva_send_auto_cancel_email'           => self::get_bool( $input, 'mhm_rentiva_send_auto_cancel_email' ),
			'mhm_rentiva_default_rental_days'              => self::get_int( $input, 'mhm_rentiva_default_rental_days', 1, 1, 365 ),
		);
	}

	private static function sanitize_customer_management_settings( array $input, array $defaults ): array {
		return array();
	}

	private static function sanitize_email_brand_settings( array $input, array $defaults ): array {
		$from_name = self::safe_text( $input['mhm_rentiva_email_from_name'] ?? \get_bloginfo( 'name' ) );
		// Fix: Prevent boolean 'true' casted to '1' from being saved as sender name
		if ( '1' === $from_name || '0' === $from_name ) {
			$from_name = \get_bloginfo( 'name' );
		}

		$from_address = SettingsHelper::sanitize_field( $input['mhm_rentiva_email_from_address'] ?? \get_option( 'admin_email' ), 'email' );
		// Fix: Prevent invalid emails or boolean casts
		if ( '1' === $from_address || ! \is_email( $from_address ) ) {
			$from_address = \get_option( 'admin_email' );
		}

		return array(
			'mhm_rentiva_email_from_name'     => $from_name,
			'mhm_rentiva_email_from_address'  => $from_address,
			'mhm_rentiva_brand_name'          => self::safe_text( $input['mhm_rentiva_brand_name'] ?? \get_bloginfo( 'name' ) ),
			'mhm_rentiva_brand_logo_url'      => \esc_url_raw( $input['mhm_rentiva_brand_logo_url'] ?? '' ),
			'mhm_rentiva_email_header_image'  => \esc_url_raw( $input['mhm_rentiva_email_header_image'] ?? '' ),
			'mhm_rentiva_email_primary_color' => \sanitize_hex_color( $input['mhm_rentiva_email_primary_color'] ?? '#1e88e5' ),
			'mhm_rentiva_email_base_color'    => \sanitize_hex_color( $input['mhm_rentiva_email_base_color'] ?? '#667eea' ),
			'mhm_rentiva_email_footer_text'   => SettingsHelper::sanitize_field( $input['mhm_rentiva_email_footer_text'] ?? '', 'textarea' ),
		);
	}

	private static function sanitize_email_sending_settings( array $input, array $defaults ): array {
		return array(
			'mhm_rentiva_email_reply_to'           => SettingsHelper::sanitize_field( $input['mhm_rentiva_email_reply_to'] ?? \get_option( 'admin_email' ), 'email' ),
			'mhm_rentiva_email_send_enabled'       => self::get_bool( $input, 'mhm_rentiva_email_send_enabled' ),
			'mhm_rentiva_email_test_mode'          => self::get_bool( $input, 'mhm_rentiva_email_test_mode' ),
			'mhm_rentiva_email_test_address'       => SettingsHelper::sanitize_field( $input['mhm_rentiva_email_test_address'] ?? \get_option( 'admin_email' ), 'email' ),
			'mhm_rentiva_email_template_path'      => self::safe_text( $input['mhm_rentiva_email_template_path'] ?? 'mhm-rentiva/emails/' ),
			'mhm_rentiva_email_auto_send'          => self::get_bool( $input, 'mhm_rentiva_email_auto_send' ),
			'mhm_rentiva_email_log_enabled'        => self::get_bool( $input, 'mhm_rentiva_email_log_enabled' ),
			'mhm_rentiva_email_log_retention_days'       => self::get_int( $input, 'mhm_rentiva_email_log_retention_days', 30, 1, 365 ),
			'mhm_rentiva_customer_welcome_email'         => self::get_bool( $input, 'mhm_rentiva_customer_welcome_email' ),
			'mhm_rentiva_customer_booking_notifications' => self::get_bool( $input, 'mhm_rentiva_customer_booking_notifications' ),
		);
	}

	private static function sanitize_offline_settings( array $input, array $defaults ): array {
		return array(
			'mhm_rentiva_offline_instructions' => SettingsHelper::sanitize_field( $input['mhm_rentiva_offline_instructions'] ?? '', 'textarea' ),
			'mhm_rentiva_offline_accounts'     => \wp_kses_post( $input['mhm_rentiva_offline_accounts'] ?? '' ),
		);
	}

	private static function sanitize_frontend_settings( array $input, array $defaults ): array {
		$out   = array(
			'mhm_rentiva_vehicle_cards_per_page' => self::get_int( $input, 'mhm_rentiva_vehicle_cards_per_page', 12, 1, 50 ),
			'mhm_rentiva_vehicle_default_sort'   => self::validate_enum( $input['mhm_rentiva_vehicle_default_sort'] ?? '', array( 'price_asc', 'price_desc', 'name_asc', 'name_desc', 'year_desc', 'year_asc' ), 'price_asc' ),
		);
		$slugs = array( 'mhm_rentiva_endpoint_bookings', 'mhm_rentiva_endpoint_favorites', 'mhm_rentiva_endpoint_payment_history', 'mhm_rentiva_endpoint_messages', 'mhm_rentiva_endpoint_edit_account' );
		foreach ( $slugs as $s ) {
			$out[ $s ] = \sanitize_title( $input[ $s ] ?? ( $defaults[ $s ] ?? '' ) );
		}

		$urls = array( 'mhm_rentiva_booking_url', 'mhm_rentiva_login_url', 'mhm_rentiva_register_url', 'mhm_rentiva_vehicles_list_url', 'mhm_rentiva_search_url', 'mhm_rentiva_contact_url' );
		foreach ( $urls as $u ) {
			$out[ $u ] = self::safe_text( $input[ $u ] ?? '' );
		}

		$texts = array(
			'mhm_rentiva_text_book_now',
			'mhm_rentiva_text_view_details',
			'mhm_rentiva_text_make_booking',
			'mhm_rentiva_text_cancel_booking',
			'mhm_rentiva_text_added_to_favorites',
			'mhm_rentiva_text_removed_from_favorites',
			'mhm_rentiva_text_login_here',
			'mhm_rentiva_text_processing',
			'mhm_rentiva_text_loading',
			'mhm_rentiva_text_error',
			'mhm_rentiva_text_booking_success',
			'mhm_rentiva_text_first_name',
			'mhm_rentiva_text_last_name',
			'mhm_rentiva_text_email',
			'mhm_rentiva_text_phone',
			'mhm_rentiva_text_select_vehicle',
			'mhm_rentiva_text_select_dates',
			'mhm_rentiva_text_invalid_dates',
			'mhm_rentiva_text_select_payment_type',
			'mhm_rentiva_text_select_payment_method',
			'mhm_rentiva_text_calculating',
			'mhm_rentiva_text_payment_redirect',
			'mhm_rentiva_text_payment_success',
			'mhm_rentiva_text_payment_cancelled',
			'mhm_rentiva_text_popup_blocked',
			'mhm_rentiva_text_view_dashboard',
			'mhm_rentiva_text_back_to_bookings',
			'mhm_rentiva_text_already_have_account',
		);
		foreach ( $texts as $t ) {
			if ( isset( $input[ $t ] ) ) {
				$out[ $t ] = self::safe_text( $input[ $t ] );
			}
		}

		$out['mhm_rentiva_text_login_required'] = \sanitize_textarea_field( $input['mhm_rentiva_text_login_required'] ?? '' );

		return $out;
	}

	private static function sanitize_comments_settings( array $input, array $current ): array {
		if ( ! isset( $input['mhm_rentiva_comments_settings'] ) || ! \is_array( $input['mhm_rentiva_comments_settings'] ) ) {
			return array();
		}

		$in = $input['mhm_rentiva_comments_settings'];

		// Process spam_words - can be textarea (comma-separated) or array
		$spam_words_raw = $in['spam_protection']['spam_words'] ?? '';
		if ( \is_string( $spam_words_raw ) ) {
			$spam_words = array_filter( array_map( 'trim', explode( ',', $spam_words_raw ) ) );
		} elseif ( \is_array( $spam_words_raw ) ) {
			$spam_words = array_map( array( self::class, 'safe_text' ), $spam_words_raw );
		} else {
			$spam_words = array();
		}

		return array(
			'comments_approval'        => array(
				'auto_approve'         => (bool) ( $in['approval']['auto_approve'] ?? false ),
				'require_login'        => (bool) ( $in['approval']['require_login'] ?? true ),
				'allow_guest_comments' => (bool) ( $in['approval']['allow_guest_comments'] ?? false ),
				'moderation_required'  => (bool) ( $in['approval']['moderation_required'] ?? true ),
				'admin_notification'   => (bool) ( $in['approval']['admin_notification'] ?? true ),
			),
			'comments_limits'          => array(
				'comments_per_page'        => self::clamp_value( (int) ( $in['limits']['comments_per_page'] ?? 10 ), 1, 100 ),
				'max_comments_per_user'    => self::clamp_value( (int) ( $in['limits']['max_comments_per_user'] ?? 0 ), 0, 100 ),
				'max_comments_per_vehicle' => self::clamp_value( (int) ( $in['limits']['max_comments_per_vehicle'] ?? 0 ), 0, 1000 ),
				'comment_length_min'       => self::clamp_value( (int) ( $in['limits']['comment_length_min'] ?? 5 ), 1, 1000 ),
				'comment_length_max'       => self::clamp_value( (int) ( $in['limits']['comment_length_max'] ?? 1000 ), 10, 5000 ),
				'rating_required'          => (bool) ( $in['limits']['rating_required'] ?? true ),
			),
			'comments_display'         => array(
				'show_ratings'        => (bool) ( $in['display']['show_ratings'] ?? true ),
				'show_avatars'        => (bool) ( $in['display']['show_avatars'] ?? true ),
				'show_dates'          => (bool) ( $in['display']['show_dates'] ?? true ),
				'show_edit_buttons'   => (bool) ( $in['display']['show_edit_buttons'] ?? true ),
				'show_delete_buttons' => (bool) ( $in['display']['show_delete_buttons'] ?? true ),
				'allow_editing'       => (bool) ( $in['display']['allow_editing'] ?? true ),
				'allow_deletion'      => (bool) ( $in['display']['allow_deletion'] ?? true ),
				'edit_time_limit'     => self::clamp_value( (int) ( $in['display']['edit_time_limit'] ?? 24 ), 0, 168 ),
				'sort_order'          => self::validate_enum( $in['display']['sort_order'] ?? 'newest', array( 'newest', 'oldest', 'highest_rated', 'lowest_rated' ), 'newest' ),
				'pagination'          => (bool) ( $in['display']['pagination'] ?? true ),
			),
			'comments_spam_protection' => array(
				'enabled'             => (bool) ( $in['spam_protection']['enabled'] ?? true ),
				'rate_limiting'       => array(
					'enabled'         => (bool) ( $in['spam_protection']['rate_limiting']['enabled'] ?? true ),
					'time_window'     => self::clamp_value( (int) ( $in['spam_protection']['rate_limiting']['time_window'] ?? 1 ), 1, 60 ),
					'max_attempts'    => self::clamp_value( (int) ( $in['spam_protection']['rate_limiting']['max_attempts'] ?? 1 ), 1, 10 ),
					'cooldown_period' => self::clamp_value( (int) ( $in['spam_protection']['rate_limiting']['cooldown_period'] ?? 10 ), 1, 60 ),
				),
				'duplicate_detection' => array(
					'enabled'        => (bool) ( $in['spam_protection']['duplicate_detection']['enabled'] ?? true ),
					'time_window'    => self::clamp_value( (int) ( $in['spam_protection']['duplicate_detection']['time_window'] ?? 1 ), 1, 60 ),
					'max_duplicates' => self::clamp_value( (int) ( $in['spam_protection']['duplicate_detection']['max_duplicates'] ?? 1 ), 0, 10 ),
					'check_content'  => (bool) ( $in['spam_protection']['duplicate_detection']['check_content'] ?? true ),
				),
				'spam_words'          => $spam_words,
			),
			'comments_notifications'   => array(
				'admin_new_comment'     => (bool) ( $in['notifications']['admin_new_comment'] ?? true ),
				'admin_comment_edited'  => (bool) ( $in['notifications']['admin_comment_edited'] ?? true ),
				'admin_comment_deleted' => (bool) ( $in['notifications']['admin_comment_deleted'] ?? true ),
				'user_comment_approved' => (bool) ( $in['notifications']['user_comment_approved'] ?? true ),
				'user_comment_rejected' => (bool) ( $in['notifications']['user_comment_rejected'] ?? true ),
			),
			'comments_cache'           => array(
				'enabled'          => (bool) ( $in['cache']['enabled'] ?? true ),
				'duration'         => self::clamp_value( (int) ( $in['cache']['duration'] ?? 15 ), 1, 1440 ),
				'clear_on_comment' => (bool) ( $in['cache']['clear_on_comment'] ?? true ),
				'clear_on_edit'    => (bool) ( $in['cache']['clear_on_edit'] ?? true ),
				'clear_on_delete'  => (bool) ( $in['cache']['clear_on_delete'] ?? true ),
			),
		);
	}

	private static function sanitize_comparison_settings( array $input, array $current ): array {
		if ( ! isset( $input['comparison_fields'] ) || ! \is_array( $input['comparison_fields'] ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $input['comparison_fields'] as $cat => $fields ) {
			$cat_key = \sanitize_key( (string) $cat );
			if ( $cat_key && \is_array( $fields ) ) {
				$sanitized[ $cat_key ] = array_map( '\sanitize_key', $fields );
			}
		}
		return array( 'comparison_fields' => $sanitized );
	}

	/**
	 * Sanitize Addon Settings
	 */
	private static function sanitize_addon_settings( array $input, array $defaults ): array {
		return array(
			'mhm_rentiva_addon_require_confirmation'    => self::get_bool( $input, 'mhm_rentiva_addon_require_confirmation' ),
			'mhm_rentiva_addon_show_prices_in_calendar' => self::get_bool( $input, 'mhm_rentiva_addon_show_prices_in_calendar' ),
			'mhm_rentiva_addon_display_order'           => self::validate_enum( $input['mhm_rentiva_addon_display_order'] ?? '', array( 'menu_order', 'title', 'price_asc', 'price_desc', 'date_created' ), 'menu_order' ),
		);
	}

	/**
	 * Sanitize Transfer Settings
	 */
	private static function sanitize_transfer_settings( array $input, array $defaults ): array {
		return array(
			'mhm_transfer_deposit_type' => self::validate_enum( $input['mhm_transfer_deposit_type'] ?? '', array( 'full_payment', 'percentage' ), 'full_payment' ),
			'mhm_transfer_deposit_rate' => self::get_int( $input, 'mhm_transfer_deposit_rate', 20, 1, 100 ),
			'mhm_transfer_custom_types' => \sanitize_textarea_field( $input['mhm_transfer_custom_types'] ?? '' ),
		);
	}

	private static function sanitize_site_info_settings( array $input, array $defaults ): array {
		return array(
			'mhm_rentiva_brand_name'    => self::safe_text( $input['mhm_rentiva_brand_name'] ?? \get_bloginfo( 'name' ) ),
			'mhm_rentiva_site_url'      => \esc_url_raw( $input['mhm_rentiva_site_url'] ?? \get_option( 'siteurl' ) ),
			'mhm_rentiva_home_url'      => \esc_url_raw( $input['mhm_rentiva_home_url'] ?? \get_option( 'home' ) ),
			'mhm_rentiva_admin_email'   => SettingsHelper::sanitize_field( $input['mhm_rentiva_admin_email'] ?? \get_option( 'admin_email' ), 'email' ),
			'mhm_rentiva_site_language' => self::safe_text( $input['mhm_rentiva_site_language'] ?? \get_locale() ),
			'mhm_rentiva_timezone'      => self::safe_text( $input['mhm_rentiva_timezone'] ?? \wp_timezone_string() ),
			'mhm_rentiva_support_email' => SettingsHelper::sanitize_field( $input['mhm_rentiva_support_email'] ?? '', 'email' ),
			'mhm_rentiva_contact_phone' => self::safe_text( $input['mhm_rentiva_contact_phone'] ?? '' ),
			'mhm_rentiva_contact_hours' => self::safe_text( $input['mhm_rentiva_contact_hours'] ?? '' ),
		);
	}

	private static function sanitize_datetime_settings( array $input, array $defaults ): array {
		return array(
			'mhm_rentiva_time_format'   => self::safe_text( $input['mhm_rentiva_time_format'] ?? 'H:i' ),
			'mhm_rentiva_start_of_week' => self::get_int( $input, 'mhm_rentiva_start_of_week', 1, 0, 6 ),
		);
	}

	/**
	 * Clamps a value between a minimum and maximum.
	 *
	 * @param int|float $value Input value.
	 * @param int|float $min   Minimum value.
	 * @param int|float $max   Maximum value.
	 * @return int|float Clamped value.
	 */
	private static function clamp_value( $value, $min, $max ) {
		return max( $min, min( $max, $value ) );
	}

	/**
	 * Sanitize Vendor Marketplace Settings (Pro feature).
	 */
	private static function sanitize_vendor_marketplace_settings( array $input, array $defaults ): array {
		return array(
			// Listing & Duration
			'vendor_listing_duration_days'         => self::get_int( $input, 'vendor_listing_duration_days', 90, 1, 365 ),
			'vendor_expiry_warning_first_days'     => self::get_int( $input, 'vendor_expiry_warning_first_days', 10, 1, 60 ),
			'vendor_expiry_warning_second_days'    => self::get_int( $input, 'vendor_expiry_warning_second_days', 3, 1, 30 ),
			'vendor_expiry_grace_days'             => self::get_int( $input, 'vendor_expiry_grace_days', 7, 0, 30 ),
			'vendor_withdrawal_cooldown_days'      => self::get_int( $input, 'vendor_withdrawal_cooldown_days', 7, 0, 90 ),
			'vendor_max_pauses_per_month'          => self::get_int( $input, 'vendor_max_pauses_per_month', 2, 1, 10 ),
			'vendor_max_pause_duration_days'       => self::get_int( $input, 'vendor_max_pause_duration_days', 30, 1, 180 ),

			// Penalty
			'vendor_penalty_tier2_rate'            => self::get_int( $input, 'vendor_penalty_tier2_rate', 10, 0, 100 ),
			'vendor_penalty_tier3_rate'            => self::get_int( $input, 'vendor_penalty_tier3_rate', 25, 0, 100 ),
			'vendor_penalty_rolling_window_months' => self::get_int( $input, 'vendor_penalty_rolling_window_months', 12, 1, 36 ),

			// Anti-Gaming
			'vendor_anti_gaming_block_days'        => self::get_int( $input, 'vendor_anti_gaming_block_days', 30, 1, 180 ),

			// Reliability Score
			'vendor_score_cancel_penalty'          => self::get_int( $input, 'vendor_score_cancel_penalty', 5, 0, 50 ),
			'vendor_score_withdrawal_penalty'      => self::get_int( $input, 'vendor_score_withdrawal_penalty', 10, 0, 50 ),
			'vendor_score_pause_penalty'           => self::get_int( $input, 'vendor_score_pause_penalty', 2, 0, 20 ),
			'vendor_score_completion_bonus'        => self::get_int( $input, 'vendor_score_completion_bonus', 5, 0, 20 ),
			'vendor_score_max_completion_bonus'    => self::get_int( $input, 'vendor_score_max_completion_bonus', 20, 0, 100 ),
		);
	}
}
