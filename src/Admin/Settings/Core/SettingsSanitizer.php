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
		$current_values = (array) \get_option( 'mhmrentiva_settings', array() );
		$defaults       = SettingsCore::get_defaults();

		if ( ! \is_array( $input ) ) {
			return $current_values;
		}

		// 2. Recursive null cleanup to prevent PHP 8.x strlen null errors
		self::clean_recursive( $input );

		$out         = $current_values;
		$current_tab = $input['current_active_tab'] ?? '';

		// 2b. Extension-owned tabs must not PERSIST when no extension registered
		// them. The render layer already shows a placeholder instead of the form
		// (Transfer / Vendor Marketplace), but a forged or replayed POST could
		// still reach this sanitizer. Fail closed: for a tab that belongs to an
		// extension whose registration is absent, return the untouched current
		// values (a no-op save) so no extension-owned setting is written.
		// Transfer is a whole-add-on extension point; vendor-marketplace has its
		// own registration key. Messages saves through its own gated handler, not
		// here.
		// Registration state comes from SettingsCore::settings_tabs() (Task A6
		// seam inversion): Lite's own default is an empty array, so a missing key
		// -- exactly the "no extension registered this tab" state -- is treated
		// as absent via empty(), not skipped.
		$extension_only_tabs = array( 'transfer', 'vendor-marketplace' );
		if ( in_array( $current_tab, $extension_only_tabs, true ) && empty( SettingsCore::settings_tabs()[ $current_tab ] ) ) {
			return $current_values;
		}

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
			// Two different callers land here and they need opposite treatment.
			//
			// A programmatic update -- SettingsCore's dark-mode toggle, a WP-CLI
			// write -- arrives through `update_option()` with no
			// `current_active_tab` at all, carrying the whole settings array it
			// just modified. Dropping it would discard the change.
			//
			// A form POST always carries the tab, so a tab name this match does
			// not recognise is a typo, a renamed tab or a forged field. Returning
			// `$input` for that case wrote the raw request into the option: every
			// declared bound, enum and text sanitizer in this plugin lives inside
			// the arms above, so an unrecognised tab bypassed all of them at once.
			//
			// The add-on's own tabs are unaffected either way: they are dispatched
			// by the filter below, and an extension-owned tab whose extension is
			// absent was already refused earlier in this method.
			default              => '' === $current_tab
				? self::sanitize_programmatic_update( $input, $current_values )
				: array(),
		};

		// 3b. Extensible per-tab dispatch (Task A6 seam inversion). Lite's match
		// above only knows its own tabs; 'vendor-marketplace' now falls to the
		// fallback ($input, unsanitized) until an extension's SettingsExtensions
		// subscribes and supplies its own sanitizer for that tab. Harmless when
		// nothing subscribes: the write itself is already blocked above (step 2b)
		// for an extension-owned tab with no registered extension, so this filter
		// only ever reaches a real subscriber once that gate has already passed.
		$sanitized_batch = apply_filters( 'mhmrentiva_sanitize_settings_tab', $sanitized_batch, $current_tab, $input, $defaults );

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

		if ( isset( $input['mhmrentiva_currency'] ) ) {
			$out['mhmrentiva_currency'] = strtoupper( substr( self::safe_text( $input['mhmrentiva_currency'] ), 0, 4 ) );
		}

		$fields = array( 'mhmrentiva_currency_position', 'mhmrentiva_date_format', 'mhmrentiva_time_format' );
		foreach ( $fields as $field ) {
			if ( isset( $input[ $field ] ) ) {
				$out[ $field ] = self::safe_text( $input[ $field ] );
			}
		}

		if ( isset( $input['mhmrentiva_dark_mode'] ) ) {
			$out['mhmrentiva_dark_mode'] = self::sanitize_dark_mode_option( $input['mhmrentiva_dark_mode'] );
		}

		return array_merge(
			$out,
			self::sanitize_site_info_settings( $input, $defaults ),
			self::sanitize_datetime_settings( $input, $defaults )
		);
	}

	/**
	 * A write that did not come from a settings form.
	 *
	 * Only keys the plugin already knows are accepted -- anything the caller
	 * invented is dropped -- and scalars go through WordPress's text sanitizer.
	 * That is deliberately weaker than a tab's own branch, which knows each
	 * field's type and bounds; it is the strongest thing available when the
	 * caller has not said which screen the values came from, and it is strictly
	 * stronger than the previous behaviour of writing the array untouched.
	 *
	 * @param array<string, mixed> $input
	 * @param array<string, mixed> $current
	 * @return array<string, mixed>
	 */
	private static function sanitize_programmatic_update( array $input, array $current ): array {
		$known = array_keys( array_merge( SettingsCore::get_defaults(), $current ) );
		$out   = array();

		foreach ( $input as $key => $value ) {
			if ( ! in_array( $key, $known, true ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$out[ $key ] = $value;
				continue;
			}

			$out[ $key ] = is_scalar( $value ) ? \sanitize_text_field( (string) $value ) : '';
		}

		return $out;
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
		if ( isset( $input['mhmrentiva_booking_payment_deadline_minutes'] ) && 'booking' === $tab ) {
			$out['mhmrentiva_booking_payment_deadline_minutes'] = self::clamp_value( (int) $input['mhmrentiva_booking_payment_deadline_minutes'], 0, 1440 );
		}

		if ( isset( $input['mhmrentiva_booking_payment_gateway_timeout_minutes'] ) ) {
			$out['mhmrentiva_booking_payment_gateway_timeout_minutes'] = self::clamp_value( (int) $input['mhmrentiva_booking_payment_gateway_timeout_minutes'], 0, 60 );
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
	 * Sanitize standalone addon option array (`mhmrentiva_addon_settings`).
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
			'mhmrentiva_cache_enabled'           => self::get_bool( $input, 'mhmrentiva_cache_enabled' ),
			// Bounds mirror the number_field() declaration in CoreSettings; the
			// browser enforces them, a crafted POST does not.
			'mhmrentiva_cache_default_ttl'       => self::clamp_value( floatval( $input['mhmrentiva_cache_default_ttl'] ?? 1.0 ), 0.5, 24.0 ),
			'mhmrentiva_cache_lists_ttl'         => self::get_int( $input, 'mhmrentiva_cache_lists_ttl', 5, 1, 60 ),
			'mhmrentiva_cache_reports_ttl'       => self::get_int( $input, 'mhmrentiva_cache_reports_ttl', 15, 1, 1440 ),

			// Maintenance.
			'mhmrentiva_log_level'               => self::validate_enum( $input['mhmrentiva_log_level'] ?? '', array( 'error', 'warning', 'info', 'debug' ), 'error' ),
			'mhmrentiva_log_cleanup_enabled'     => self::get_bool( $input, 'mhmrentiva_log_cleanup_enabled' ),
			'mhmrentiva_log_retention_days'      => self::get_int( $input, 'mhmrentiva_log_retention_days', 30, 1, 365 ),
			'mhmrentiva_debug_mode'              => self::get_bool( $input, 'mhmrentiva_debug_mode' ),
			'mhmrentiva_clean_data_on_uninstall' => self::get_bool( $input, 'mhmrentiva_clean_data_on_uninstall' ),
		);
	}

	private static function sanitize_vehicle_management_settings( array $input, array $defaults ): array {
		$url_base = \sanitize_title( $input['mhmrentiva_vehicle_url_base'] ?? ( $defaults['mhmrentiva_vehicle_url_base'] ?? 'vehicle' ) );
		$out      = array(
			'mhmrentiva_vehicle_url_base'             => $url_base ?: 'vehicle',
			// Both fields declare max="100" in VehicleManagementSettings. The lower
			// bound stays at 0.1 rather than the declared 0: these multiply a price,
			// and a zero multiplier makes every rental free.
			'mhmrentiva_vehicle_base_price'           => self::clamp_value( floatval( $input['mhmrentiva_vehicle_base_price'] ?? ( $defaults['mhmrentiva_vehicle_base_price'] ?? 1.0 ) ), 0.1, 100.0 ),
			'mhmrentiva_vehicle_weekend_multiplier'   => self::clamp_value( floatval( $input['mhmrentiva_vehicle_weekend_multiplier'] ?? ( $defaults['mhmrentiva_vehicle_weekend_multiplier'] ?? 1.0 ) ), 0.1, 100.0 ),
			'mhmrentiva_vehicle_tax_inclusive'        => self::get_bool( $input, 'mhmrentiva_vehicle_tax_inclusive' ),
			'mhmrentiva_vehicle_tax_rate'             => self::clamp_value( floatval( $input['mhmrentiva_vehicle_tax_rate'] ?? 0 ), 0, 100 ),
			// `..._cards_per_page` and `..._default_sort` are NOT listed here even
			// though their names begin `vehicle_`: they render on the Frontend tab,
			// not this one. Naming a key in a tab's branch writes it with its
			// default whenever the form does not contain it, so listing them made
			// every Vehicle-tab save silently reset two fields the administrator
			// had set on another screen. Same trap the card/detail field guard
			// below already documents.
			'mhmrentiva_vehicle_min_rental_days'      => self::get_int( $input, 'mhmrentiva_vehicle_min_rental_days', 1, 1, 365 ),
			'mhmrentiva_vehicle_max_rental_days'      => self::get_int( $input, 'mhmrentiva_vehicle_max_rental_days', 365, 1, 365 ),
			'mhmrentiva_vehicle_advance_booking_days' => self::get_int( $input, 'mhmrentiva_vehicle_advance_booking_days', 365, 1, 365 ),
			'mhmrentiva_vehicle_allow_same_day'       => self::get_bool( $input, 'mhmrentiva_vehicle_allow_same_day' ),
		);

		// Only overwrite card/detail field selections when the key is explicitly present in the POST.
		// If absent (e.g. all checkboxes unchecked or form submitted from a different section),
		// omit it so array_merge() preserves the existing DB value instead of clearing it.
		if ( isset( $input['mhmrentiva_vehicle_card_fields'] ) ) {
			$out['mhmrentiva_vehicle_card_fields'] = VehicleFeatureHelper::sanitize_card_field_selection( $input['mhmrentiva_vehicle_card_fields'] );
		}
		if ( isset( $input['mhmrentiva_vehicle_detail_fields'] ) ) {
			$out['mhmrentiva_vehicle_detail_fields'] = VehicleFeatureHelper::sanitize_card_field_selection( $input['mhmrentiva_vehicle_detail_fields'] );
		}

		return $out;
	}

	private static function sanitize_vehicle_pricing_settings( array $input, array $defaults ): array {
		$current_settings = (array) \get_option( 'mhmrentiva_settings', array() );
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
				// Keys are internal season slugs (spring/summer/autumn/winter,
				// see VehiclePricingSettings::get_default_settings()) used to
				// look up $current_pricing['seasonal_multipliers'][ $key ] --
				// sanitize_key() to prevent an attacker-controlled dirty key
				// (e.g. '<script>x') from persisting markup into the option
				// (WP.org T4 #6 -- same defect class as VehicleSettings.php).
				foreach ( $in['seasonal_multipliers'] as $key => $season ) {
					$safe_key = \sanitize_key( (string) $key );
					if ( '' === $safe_key ) {
						continue;
					}
					if ( isset( $season['multiplier'] ) ) {
						// Declared min="0.1" max="5.0" on the seasonal-multiplier input.
						// Unclamped this accepted a negative value, which multiplies
						// straight into the rental price.
						$current_pricing['seasonal_multipliers'][ $safe_key ]['multiplier'] = self::clamp_value( floatval( $season['multiplier'] ), 0.1, 5.0 );
					}
				}
			}

			if ( isset( $in['discount_options'] ) && \is_array( $in['discount_options'] ) ) {
				// Keys are internal discount slugs (weekly/monthly/early_booking/
				// loyalty, see VehiclePricingSettings::get_default_settings()) --
				// same key-sanitization rationale as seasonal_multipliers above.
				foreach ( $in['discount_options'] as $key => $discount ) {
					$safe_key = \sanitize_key( (string) $key );
					if ( '' === $safe_key ) {
						continue;
					}
					$current_pricing['discount_options'][ $safe_key ] = array(
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
			'mhmrentiva_booking_cancellation_deadline_hours' => self::get_int( $input, 'mhmrentiva_booking_cancellation_deadline_hours', 24, 1, 168 ),
			'mhmrentiva_booking_payment_deadline_minutes' => self::get_int( $input, 'mhmrentiva_booking_payment_deadline_minutes', 30, 0, 1440 ),
			'mhmrentiva_booking_auto_cancel_enabled'      => self::get_bool( $input, 'mhmrentiva_booking_auto_cancel_enabled' ),
			'mhmrentiva_booking_buffer_time'              => self::get_int( $input, 'mhmrentiva_booking_buffer_time', 60, 0, 1440 ),
			'mhmrentiva_booking_send_confirmation_emails' => self::get_bool( $input, 'mhmrentiva_booking_send_confirmation_emails' ),
			'mhmrentiva_booking_send_reminder_emails'     => self::get_bool( $input, 'mhmrentiva_booking_send_reminder_emails' ),
			'mhmrentiva_booking_admin_notifications'      => self::get_bool( $input, 'mhmrentiva_booking_admin_notifications' ),
			'mhmrentiva_send_auto_cancel_email'           => self::get_bool( $input, 'mhmrentiva_send_auto_cancel_email' ),
			'mhmrentiva_default_rental_days'              => self::get_int( $input, 'mhmrentiva_default_rental_days', 1, 1, 365 ),
		);
	}

	private static function sanitize_customer_management_settings( array $input, array $defaults ): array {
		return array();
	}

	private static function sanitize_email_brand_settings( array $input, array $defaults ): array {
		$from_name = self::safe_text( $input['mhmrentiva_email_from_name'] ?? \get_bloginfo( 'name' ) );
		// Fix: Prevent boolean 'true' casted to '1' from being saved as sender name
		if ( '1' === $from_name || '0' === $from_name ) {
			$from_name = \get_bloginfo( 'name' );
		}

		$from_address = SettingsHelper::sanitize_field( $input['mhmrentiva_email_from_address'] ?? \get_option( 'admin_email' ), 'email' );
		// Fix: Prevent invalid emails or boolean casts
		if ( '1' === $from_address || ! \is_email( $from_address ) ) {
			$from_address = \get_option( 'admin_email' );
		}

		return array(
			'mhmrentiva_email_from_name'     => $from_name,
			'mhmrentiva_email_from_address'  => $from_address,
			'mhmrentiva_brand_name'          => self::safe_text( $input['mhmrentiva_brand_name'] ?? \get_bloginfo( 'name' ) ),
			'mhmrentiva_brand_logo_url'      => \esc_url_raw( $input['mhmrentiva_brand_logo_url'] ?? '' ),
			'mhmrentiva_email_header_image'  => \esc_url_raw( $input['mhmrentiva_email_header_image'] ?? '' ),
			'mhmrentiva_email_primary_color' => \sanitize_hex_color( $input['mhmrentiva_email_primary_color'] ?? '#1e88e5' ),
			'mhmrentiva_email_base_color'    => \sanitize_hex_color( $input['mhmrentiva_email_base_color'] ?? '#667eea' ),
			'mhmrentiva_email_footer_text'   => SettingsHelper::sanitize_field( $input['mhmrentiva_email_footer_text'] ?? '', 'textarea' ),
		);
	}

	private static function sanitize_email_sending_settings( array $input, array $defaults ): array {
		return array(
			'mhmrentiva_email_reply_to'                 => SettingsHelper::sanitize_field( $input['mhmrentiva_email_reply_to'] ?? \get_option( 'admin_email' ), 'email' ),
			'mhmrentiva_email_send_enabled'             => self::get_bool( $input, 'mhmrentiva_email_send_enabled' ),
			'mhmrentiva_email_test_mode'                => self::get_bool( $input, 'mhmrentiva_email_test_mode' ),
			'mhmrentiva_email_test_address'             => SettingsHelper::sanitize_field( $input['mhmrentiva_email_test_address'] ?? \get_option( 'admin_email' ), 'email' ),
			'mhmrentiva_email_template_path'            => self::safe_text( $input['mhmrentiva_email_template_path'] ?? 'mhm-rentiva/emails/' ),
			'mhmrentiva_email_auto_send'                => self::get_bool( $input, 'mhmrentiva_email_auto_send' ),
			'mhmrentiva_email_log_enabled'              => self::get_bool( $input, 'mhmrentiva_email_log_enabled' ),
			'mhmrentiva_email_log_retention_days'       => self::get_int( $input, 'mhmrentiva_email_log_retention_days', 30, 1, 365 ),
			'mhmrentiva_customer_welcome_email'         => self::get_bool( $input, 'mhmrentiva_customer_welcome_email' ),
			'mhmrentiva_customer_booking_notifications' => self::get_bool( $input, 'mhmrentiva_customer_booking_notifications' ),
		);
	}

	private static function sanitize_offline_settings( array $input, array $defaults ): array {
		return array(
			'mhmrentiva_offline_instructions' => SettingsHelper::sanitize_field( $input['mhmrentiva_offline_instructions'] ?? '', 'textarea' ),
			'mhmrentiva_offline_accounts'     => \wp_kses_post( $input['mhmrentiva_offline_accounts'] ?? '' ),
		);
	}

	private static function sanitize_frontend_settings( array $input, array $defaults ): array {
		$out   = array(
			'mhmrentiva_vehicle_cards_per_page' => self::get_int( $input, 'mhmrentiva_vehicle_cards_per_page', 12, 1, 50 ),
			'mhmrentiva_vehicle_default_sort'   => self::validate_enum( $input['mhmrentiva_vehicle_default_sort'] ?? '', array( 'price_asc', 'price_desc', 'name_asc', 'name_desc', 'year_desc', 'year_asc' ), 'price_asc' ),
		);
		$slugs = array( 'mhmrentiva_endpoint_bookings', 'mhmrentiva_endpoint_favorites', 'mhmrentiva_endpoint_payment_history', 'mhmrentiva_endpoint_messages', 'mhmrentiva_endpoint_edit_account' );
		foreach ( $slugs as $s ) {
			$out[ $s ] = \sanitize_title( $input[ $s ] ?? ( $defaults[ $s ] ?? '' ) );
		}

		$urls = array( 'mhmrentiva_booking_url', 'mhmrentiva_login_url', 'mhmrentiva_register_url', 'mhmrentiva_vehicles_list_url', 'mhmrentiva_search_url', 'mhmrentiva_contact_url' );
		foreach ( $urls as $u ) {
			$out[ $u ] = self::safe_text( $input[ $u ] ?? '' );
		}

		$texts = array(
			'mhmrentiva_text_book_now',
			'mhmrentiva_text_view_details',
			'mhmrentiva_text_make_booking',
			'mhmrentiva_text_cancel_booking',
			'mhmrentiva_text_added_to_favorites',
			'mhmrentiva_text_removed_from_favorites',
			'mhmrentiva_text_login_here',
			'mhmrentiva_text_processing',
			'mhmrentiva_text_loading',
			'mhmrentiva_text_error',
			'mhmrentiva_text_booking_success',
			'mhmrentiva_text_first_name',
			'mhmrentiva_text_last_name',
			'mhmrentiva_text_email',
			'mhmrentiva_text_phone',
			'mhmrentiva_text_select_vehicle',
			'mhmrentiva_text_select_dates',
			'mhmrentiva_text_invalid_dates',
			'mhmrentiva_text_select_payment_type',
			'mhmrentiva_text_select_payment_method',
			'mhmrentiva_text_calculating',
			'mhmrentiva_text_payment_redirect',
			'mhmrentiva_text_payment_success',
			'mhmrentiva_text_payment_cancelled',
			'mhmrentiva_text_popup_blocked',
			'mhmrentiva_text_view_dashboard',
			'mhmrentiva_text_back_to_bookings',
			'mhmrentiva_text_already_have_account',
		);
		foreach ( $texts as $t ) {
			if ( isset( $input[ $t ] ) ) {
				$out[ $t ] = self::safe_text( $input[ $t ] );
			}
		}

		$out['mhmrentiva_text_login_required'] = \sanitize_textarea_field( $input['mhmrentiva_text_login_required'] ?? '' );

		return $out;
	}

	private static function sanitize_comments_settings( array $input, array $current ): array {
		if ( ! isset( $input['mhmrentiva_comments_settings'] ) || ! \is_array( $input['mhmrentiva_comments_settings'] ) ) {
			return array();
		}

		$in = $input['mhmrentiva_comments_settings'];

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
			'mhmrentiva_addon_require_confirmation'    => self::get_bool( $input, 'mhmrentiva_addon_require_confirmation' ),
			'mhmrentiva_addon_show_prices_in_calendar' => self::get_bool( $input, 'mhmrentiva_addon_show_prices_in_calendar' ),
			'mhmrentiva_addon_display_order'           => self::validate_enum( $input['mhmrentiva_addon_display_order'] ?? '', array( 'menu_order', 'title', 'price_asc', 'price_desc', 'date_created' ), 'menu_order' ),
		);
	}

	/**
	 * Sanitize Transfer Settings
	 */
	private static function sanitize_transfer_settings( array $input, array $defaults ): array {
		return array(
			'mhmrentiva_transfer_deposit_type' => self::validate_enum( $input['mhmrentiva_transfer_deposit_type'] ?? '', array( 'full_payment', 'percentage' ), 'full_payment' ),
			'mhmrentiva_transfer_deposit_rate' => self::get_int( $input, 'mhmrentiva_transfer_deposit_rate', 20, 1, 100 ),
			'mhmrentiva_transfer_custom_types' => \sanitize_textarea_field( $input['mhmrentiva_transfer_custom_types'] ?? '' ),
		);
	}

	private static function sanitize_site_info_settings( array $input, array $defaults ): array {
		return array(
			'mhmrentiva_brand_name'    => self::safe_text( $input['mhmrentiva_brand_name'] ?? \get_bloginfo( 'name' ) ),
			'mhmrentiva_site_url'      => \esc_url_raw( $input['mhmrentiva_site_url'] ?? \get_option( 'siteurl' ) ),
			'mhmrentiva_home_url'      => \esc_url_raw( $input['mhmrentiva_home_url'] ?? \get_option( 'home' ) ),
			'mhmrentiva_admin_email'   => SettingsHelper::sanitize_field( $input['mhmrentiva_admin_email'] ?? \get_option( 'admin_email' ), 'email' ),
			'mhmrentiva_site_language' => self::safe_text( $input['mhmrentiva_site_language'] ?? \get_locale() ),
			'mhmrentiva_timezone'      => self::safe_text( $input['mhmrentiva_timezone'] ?? \wp_timezone_string() ),
			'mhmrentiva_support_email' => SettingsHelper::sanitize_field( $input['mhmrentiva_support_email'] ?? '', 'email' ),
			'mhmrentiva_contact_phone' => self::safe_text( $input['mhmrentiva_contact_phone'] ?? '' ),
			'mhmrentiva_contact_hours' => self::safe_text( $input['mhmrentiva_contact_hours'] ?? '' ),
		);
	}

	private static function sanitize_datetime_settings( array $input, array $defaults ): array {
		return array(
			'mhmrentiva_time_format'   => self::safe_text( $input['mhmrentiva_time_format'] ?? 'H:i' ),
			'mhmrentiva_start_of_week' => self::get_int( $input, 'mhmrentiva_start_of_week', 1, 0, 6 ),
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
}
