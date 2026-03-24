<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Addons;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded application queries are intentional in this module.



use MHMRentiva\Admin\Addons\AddonManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AddonBooking {


	public static function register(): void {
		add_action( 'mhm_rentiva_booking_form_after_vehicle', array( self::class, 'render_addon_checkboxes' ) );
		add_filter( 'mhm_rentiva_booking_validation', array( self::class, 'validate_addon_selection' ), 10, 2 );
		add_action( 'mhm_rentiva_booking_summary_addons', array( self::class, 'render_booking_summary_addons' ) );
		add_action( 'mhm_rentiva_admin_booking_meta_addons', array( self::class, 'render_admin_booking_meta' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_addon_scripts' ) );
	}

	public static function render_addon_checkboxes(): void {
		$addons = AddonManager::get_available_addons();

		if ( empty( $addons ) ) {
			return;
		}

		echo '<div class="addon-selection-section">';
		echo '<h3>' . esc_html__( 'Additional Services', 'mhm-rentiva' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Select the additional services you need.', 'mhm-rentiva' ) . '</p>';

		echo '<div class="addon-checkboxes">';

		foreach ( $addons as $addon ) {
			$checked       = $addon['required'] ? 'checked disabled' : '';
			$required_text = $addon['required'] ? ' <span class="required">*</span>' : '';

			echo '<div class="addon-item">';
			echo '<label class="addon-checkbox-label">';
			echo '<input type="checkbox" name="selected_addons[]" value="' . esc_attr( $addon['id'] ) . '" ' . esc_attr( $checked ) . ' class="addon-checkbox" data-price="' . esc_attr( $addon['price'] ) . '">';
			echo '<span class="addon-info">';
			echo '<span class="addon-title">' . esc_html( $addon['title'] ) . wp_kses_post( $required_text ) . '</span>';
			echo '<span class="addon-price">+ ' . esc_html( self::format_addon_price( (float) $addon['price'] ) ) . '</span>';
			echo '</span>';
			if ( ! empty( $addon['description'] ) ) {
				echo '<span class="addon-description">' . esc_html( $addon['description'] ) . '</span>';
			}
			echo '</label>';
			echo '</div>';
		}

		echo '</div>';

		echo '<div class="addon-total" style="display: none;">';
		echo '<strong>' . esc_html__( 'Additional Services Total:', 'mhm-rentiva' ) . ' <span class="addon-total-amount">' . esc_html( self::format_addon_price( 0 ) ) . '</span></strong>';
		echo '</div>';

		echo '</div>';

		// Add JavaScript for dynamic total calculation
		self::enqueue_addon_scripts();
	}

	public static function validate_addon_selection( array $errors, array $data ): array {
		$selected_addons = $data['selected_addons'] ?? array();

		if ( ! is_array( $selected_addons ) ) {
			$selected_addons = array();
		}

		// Validate selected addons exist and are enabled
		$available_addons = AddonManager::get_available_addons();
		$available_ids    = array_column( $available_addons, 'id' );

		foreach ( $selected_addons as $addon_id ) {
			if ( ! in_array( (int) $addon_id, $available_ids, true ) ) {
				$errors[] = __( 'Invalid additional service selection.', 'mhm-rentiva' );
				break;
			}
		}

		// Check required addons are selected
		foreach ( $available_addons as $addon ) {
			if ( $addon['required'] && ! in_array( $addon['id'], $selected_addons, true ) ) {
				/* translators: %s: additional service title */
				$errors[] = sprintf( __( '"%s" additional service is required.', 'mhm-rentiva' ), $addon['title'] );
			}
		}

		return $errors;
	}

	public static function render_booking_summary_addons( array $booking_data ): void {
		$selected_addons = $booking_data['selected_addons'] ?? array();

		if ( empty( $selected_addons ) || ! is_array( $selected_addons ) ) {
			return;
		}

		$total = 0;

		echo '<div class="booking-summary-addons">';
		echo '<h4>' . esc_html__( 'Selected Additional Services', 'mhm-rentiva' ) . '</h4>';
		echo '<ul class="addon-list">';

		foreach ( $selected_addons as $addon_id ) {
			$addon = AddonManager::get_addon_by_id( (int) $addon_id );
			if ( $addon ) {
				$total += $addon['price'];
				echo '<li class="addon-item">';
				echo '<span class="addon-name">' . esc_html( $addon['title'] ) . '</span>';
				echo '<span class="addon-price">' . esc_html( self::format_addon_price( $addon['price'] ) ) . '</span>';
				echo '</li>';
			}
		}

		echo '</ul>';
		echo '<div class="addon-total-summary">';
		echo '<strong>' . esc_html__( 'Additional Services Total:', 'mhm-rentiva' ) . ' ' . esc_html( self::format_addon_price( $total ) ) . '</strong>';
		echo '</div>';
		echo '</div>';
	}

	public static function render_admin_booking_meta( int $booking_id ): void {
		$selected_addons = get_post_meta( $booking_id, '_mhm_selected_addons', true )
		    ?: get_post_meta( $booking_id, 'mhm_selected_addons', true );
		$addon_details   = get_post_meta( $booking_id, '_mhm_addon_details', true )
		    ?: get_post_meta( $booking_id, 'mhm_addon_details', true );
		$addon_total     = get_post_meta( $booking_id, '_mhm_addon_total', true )
		    ?: get_post_meta( $booking_id, 'mhm_addon_total', true );

		if ( empty( $selected_addons ) && empty( $addon_details ) ) {
			echo '<p>' . esc_html__( 'No additional services selected.', 'mhm-rentiva' ) . '</p>';
			return;
		}

		echo '<div class="addon-booking-meta">';
		echo '<h4>' . esc_html__( 'Additional Services', 'mhm-rentiva' ) . '</h4>';

		if ( ! empty( $addon_details ) && is_array( $addon_details ) ) {
			echo '<ul class="addon-meta-list">';
			foreach ( $addon_details as $addon ) {
				echo '<li>';
				echo '<strong>' . esc_html( $addon['title'] ) . '</strong>: ';
				echo esc_html( self::format_addon_price( $addon['price'] ) );
				echo '</li>';
			}
			echo '</ul>';

			if ( $addon_total > 0 ) {
				echo '<p><strong>' . esc_html__( 'Total:', 'mhm-rentiva' ) . '</strong> ';
				echo esc_html( self::format_addon_price( (float) $addon_total ) ) . '</p>';
			}
		} elseif ( ! empty( $selected_addons ) && is_array( $selected_addons ) ) {
			// Fallback for old format
			echo '<ul class="addon-meta-list">';
			$total = 0;
			foreach ( $selected_addons as $addon_id ) {
				$addon = AddonManager::get_addon_by_id( (int) $addon_id );
				if ( $addon ) {
					$total += $addon['price'];
					echo '<li>';
					echo '<strong>' . esc_html( $addon['title'] ) . '</strong>: ';
					echo esc_html( self::format_addon_price( $addon['price'] ) );
					echo '</li>';
				}
			}
			echo '</ul>';

			if ( $total > 0 ) {
				echo '<p><strong>' . esc_html__( 'Total:', 'mhm-rentiva' ) . '</strong> ';
				echo esc_html( self::format_addon_price( $total ) ) . '</p>';
			}
		}

		echo '</div>';
	}

	public static function get_booking_addons( int $booking_id ): array {
		$selected_addons = get_post_meta( $booking_id, '_mhm_selected_addons', true )
		    ?: get_post_meta( $booking_id, 'mhm_selected_addons', true );
		$addon_details   = get_post_meta( $booking_id, '_mhm_addon_details', true )
		    ?: get_post_meta( $booking_id, 'mhm_addon_details', true );
		$addon_total     = (float) ( get_post_meta( $booking_id, '_mhm_addon_total', true )
		    ?: get_post_meta( $booking_id, 'mhm_addon_total', true ) );

		return array(
			'selected_addons' => $selected_addons ?: array(),
			'addon_details'   => $addon_details ?: array(),
			'addon_total'     => $addon_total ?: 0.0,
		);
	}

	public static function calculate_booking_addon_total( int $booking_id ): float {
		$addons = self::get_booking_addons( $booking_id );
		return $addons['addon_total'];
	}

	public static function enqueue_addon_scripts(): void {
		// Enqueue CSS file
		wp_enqueue_style(
			'mhm-rentiva-addons',
			MHM_RENTIVA_PLUGIN_URL . 'assets/css/components/addon-booking.css',
			array(),
			MHM_RENTIVA_VERSION
		);

		// Enqueue JavaScript file
		wp_enqueue_script(
			'mhm-rentiva-addons',
			MHM_RENTIVA_PLUGIN_URL . 'assets/js/components/addon-booking.js',
			array( 'jquery' ),
			MHM_RENTIVA_VERSION,
			true
		);

		// Localize JavaScript variables
		wp_localize_script(
			'mhm-rentiva-addons',
			'mhmRentivaAddons',
			array(
				'currency'         => \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_rentiva_currency', 'USD' ),
				'currencySymbol'   => \MHMRentiva\Admin\Reports\Reports::get_currency_symbol(),
				'currencyPosition' => \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_rentiva_currency_position', 'right_space' ),
				'currencyFormat'   => array(
					'decimals'          => 2,
					'decimalSeparator'  => ',',
					'thousandSeparator' => '.',
				),
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'mhm_addon_booking_nonce' ),
			)
		);
	}

	public static function get_addon_revenue_report( \DateTime $start_date, \DateTime $end_date ): array {
		global $wpdb;

		$start = $start_date->format( 'Y-m-d H:i:s' );
		$end   = $end_date->format( 'Y-m-d H:i:s' );

		// Get addon revenue from booking meta
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT
                pm.meta_value as addon_details,
                p.post_date as booking_date
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = 'mhm_addon_details'
            AND p.post_type = 'vehicle_booking'
            AND p.post_status IN ('confirmed', 'completed')
            AND p.post_date BETWEEN %s AND %s
        ",
				$start,
				$end
			),
			ARRAY_A
		);

		$addon_stats   = array();
		$total_revenue = 0.0;

		foreach ( $results as $result ) {
			$addon_details = maybe_unserialize( $result['addon_details'] );

			if ( is_array( $addon_details ) ) {
				foreach ( $addon_details as $addon ) {
					$addon_id    = $addon['id'] ?? 0;
					$addon_title = $addon['title'] ?? 'Unknown';
					$addon_price = (float) ( $addon['price'] ?? 0 );

					if ( ! isset( $addon_stats[ $addon_id ] ) ) {
						$addon_stats[ $addon_id ] = array(
							'title'   => $addon_title,
							'count'   => 0,
							'revenue' => 0.0,
						);
					}

					++$addon_stats[ $addon_id ]['count'];
					$addon_stats[ $addon_id ]['revenue'] += $addon_price;
					$total_revenue                       += $addon_price;
				}
			}
		}

		return array(
			'addon_stats'   => $addon_stats,
			'total_revenue' => $total_revenue,
			'period'        => array(
				'start' => $start,
				'end'   => $end,
			),
		);
	}

	/**
	 * Format addon price with currency symbol and position
	 */
	private static function format_addon_price( float $price ): string {
		$symbol           = \MHMRentiva\Admin\Reports\Reports::get_currency_symbol();
		$position         = \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_rentiva_currency_position', 'right_space' );
		$formatted_amount = number_format( $price, 2, ',', '.' );

		switch ( $position ) {
			case 'left':
				return $symbol . $formatted_amount;
			case 'left_space':
				return $symbol . ' ' . $formatted_amount;
			case 'right':
				return $formatted_amount . $symbol;
			case 'right_space':
			default:
				return $formatted_amount . ' ' . $symbol;
		}
	}
}
