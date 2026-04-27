<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Licensing;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Legacy/extensible hook names kept for backward compatibility.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Mode {

	// Gateways


	// Pro-only features
	public const FEATURE_EXPORT      = 'export';
	public const FEATURE_REPORTS_ADV = 'reports_adv';
	public const FEATURE_MESSAGES    = 'messages';

	/**
	 * Check if Pro version is active
	 *
	 * @return bool True if Pro
	 */
	public static function isPro(): bool {
		return LicenseManager::instance()->isActive();
	}

	/**
	 * Check if Lite version is active
	 *
	 * @return bool True if Lite
	 */
	public static function isLite(): bool {
		return ! self::isPro();
	}

	/**
	 * Check if feature is enabled
	 *
	 * @param string $feature Feature name
	 * @return bool True if enabled
	 */
	public static function featureEnabled( string $feature ): bool {
		if ( self::isPro() ) {
			return true;
		}

		switch ( $feature ) {
			case self::FEATURE_EXPORT: // Export is now available for Lite (CSV only)
				return true;
			case self::FEATURE_REPORTS_ADV:
			case self::FEATURE_MESSAGES:
				return false;
		}

		return false;
	}

	/**
	 * Check if Vendor/Payout module can be used.
	 *
	 * V4.30.0+ — Gated on the `vendor_marketplace` feature flag in the
	 * server-issued feature token. Vendor + Payout are bundled in the same
	 * Pro tier, so they share the marketplace flag.
	 */
	public static function canUseVendorPayout(): bool {
		return self::featureGranted('vendor_marketplace');
	}

	/**
	 * Check if Messages module can be used.
	 */
	public static function canUseMessages(): bool {
		return self::featureGranted('messaging');
	}

	/**
	 * Check if advanced reports module can be used.
	 */
	public static function canUseAdvancedReports(): bool {
		return self::featureGranted('advanced_reports');
	}

	/**
	 * Check if Vendor Marketplace module can be used.
	 */
	public static function canUseVendorMarketplace(): bool {
		return self::featureGranted('vendor_marketplace');
	}

	/**
	 * Check if expanded export formats (XLSX, PDF) module can be used.
	 *
	 * Lite users have CSV/JSON access via direct format check in callers;
	 * this gate covers Pro-only formats.
	 *
	 * Server-side allowlist: 'export' key added in mhm-license-server v1.11.2.
	 */
	public static function canUseExport(): bool {
		return self::featureGranted( 'export' );
	}

	/**
	 * Pro feature gate (v4.31.0+) that requires a valid RSA-signed feature
	 * token from `mhm-license-server` v1.10.0+. Strict enforcement — there
	 * is no legacy `isPro()`-only fallback any more.
	 *
	 * The v4.30.x design left a hole: when `FEATURE_TOKEN_KEY` was unset on
	 * the customer's wp-config (the zero-config deploy default), the gate
	 * fell through to `isPro()`, which a cracked binary could trivially
	 * patch. v4.31.0 closes that by requiring a token whose RSA signature
	 * verifies against the embedded public key — public keys cannot mint,
	 * so source-edit attacks cannot forge a token even with a real license.
	 *
	 * Defense-in-depth boundary: this single private method is the only
	 * gate. A `Mode::featureGranted() { return true; }` patch defeats every
	 * `canUse*()` call. Closing that requires inline RSA verify per gate
	 * (DRY trade-off, deferred to a future release).
	 */
	private static function featureGranted( string $feature ): bool {
		// Hard gate: license must be locally active.
		if ( ! self::isPro() ) {
			return false;
		}

		// Dev-mode bypass — production-safe via double guard:
		//   1. WP_DEBUG=true (Hostinger production defaults to false)
		//   2. MHM_RENTIVA_DEV_PRO=true (opt-in constant)
		// Filter `mhm_rentiva_dev_pro_bypass` exists for testability since
		// PHP defines cannot be undone within a single process.
		$dev_pro_default = ( defined( 'WP_DEBUG' ) && WP_DEBUG
							&& defined( 'MHM_RENTIVA_DEV_PRO' ) && MHM_RENTIVA_DEV_PRO );
		/**
		 * Filter whether the dev-mode Pro bypass is active.
		 *
		 * Default: requires WP_DEBUG=true AND MHM_RENTIVA_DEV_PRO=true.
		 * Tests inject this filter to exercise both branches without touching
		 * PHP defines.
		 *
		 * @param bool $dev_pro_default Default bypass decision based on constants.
		 */
		$dev_pro_active = (bool) apply_filters( 'mhm_rentiva_dev_pro_bypass', $dev_pro_default );
		if ( $dev_pro_active ) {
			return true;
		}

		$licenseManager = LicenseManager::instance();
		$token          = $licenseManager->getFeatureToken();
		if ( $token === '' ) {
			// No token in storage — either talking to a legacy server, or a
			// cracked binary patched `isActive()`. Fail closed.
			return false;
		}

		$verifier = new FeatureTokenVerifier();
		if ( ! $verifier->verify( $token, $licenseManager->getSiteHash() ) ) {
			return false;
		}

		return $verifier->hasFeature( $token, $feature );
	}
	// ... (skip until get_pro_features_list)
	/**
	 * Get Pro-only features list
	 *
	 * @return array List of Pro features
	 */
	public static function get_pro_features_list(): array {
		return array(
			__( 'Advanced Reporting', 'mhm-rentiva' ),
			__( 'Messaging System', 'mhm-rentiva' ),
			__( 'Vendor & Payout', 'mhm-rentiva' ),
			__( 'Full REST API Access', 'mhm-rentiva' ),
			__( 'Expanded Export Formats', 'mhm-rentiva' ),
			__( 'Unlimited Date Range for Reports', 'mhm-rentiva' ),
			__( 'Unlimited Report Rows', 'mhm-rentiva' ),
			__( 'Email Notifications', 'mhm-rentiva' ),
			__( 'GDPR Compliance Tools', 'mhm-rentiva' ),
		);
	}

	/**
	 * Get maximum vehicles allowed
	 *
	 * @return int Maximum vehicles
	 */
	public static function maxVehicles(): int {
		return self::isPro() ? PHP_INT_MAX : (int) apply_filters( 'mhm_rentiva_lite_max_vehicles', 5 );
	}

	/**
	 * Get maximum bookings allowed
	 *
	 * @return int Maximum bookings
	 */
	public static function maxBookings(): int {
		return self::isPro() ? PHP_INT_MAX : (int) apply_filters( 'mhm_rentiva_lite_max_bookings', 50 );
	}

	/**
	 * Get maximum customers allowed
	 *
	 * @return int Maximum customers
	 */
	public static function maxCustomers(): int {
		return self::isPro() ? PHP_INT_MAX : (int) apply_filters( 'mhm_rentiva_lite_max_customers', 10 );
	}

	/**
	 * Get maximum addon services allowed
	 *
	 * @return int Maximum addons
	 */
	public static function maxAddons(): int {
		return self::isPro() ? PHP_INT_MAX : (int) apply_filters( 'mhm_rentiva_lite_max_addons', 4 );
	}

	/**
	 * Get maximum report range days
	 *
	 * @return int Maximum days
	 */
	public static function reportsMaxRangeDays(): int {
		return self::isPro() ? PHP_INT_MAX : (int) apply_filters( 'mhm_rentiva_lite_reports_max_days', 30 );
	}

	/**
	 * Get maximum report rows
	 *
	 * @return int Maximum rows
	 */
	public static function reportsMaxRows(): int {
		return self::isPro() ? PHP_INT_MAX : (int) apply_filters( 'mhm_rentiva_lite_reports_max_rows', 500 );
	}

	/**
	 * Get maximum transfer routes allowed
	 *
	 * @return int Maximum routes
	 */
	public static function maxTransferRoutes(): int {
		return self::isPro() ? PHP_INT_MAX : (int) apply_filters( 'mhm_rentiva_lite_max_transfer_routes', 3 );
	}

	/**
	 * Get maximum gallery images per vehicle
	 *
	 * @return int Maximum images
	 */
	public static function maxGalleryImages(): int {
		return self::isPro() ? PHP_INT_MAX : (int) apply_filters( 'mhm_rentiva_lite_max_gallery_images', 5 );
	}

	/**
	 * Get allowed payment gateways
	 *
	 * @return array Allowed gateways
	 */
	public static function allowedGateways(): array {
		$allowed = array();
		return apply_filters( 'mhm_rentiva_allowed_gateways', $allowed, self::isPro() );
	}

	/**
	 * Global is_pro() function - for use throughout the project
	 *
	 * @return bool True if Pro
	 */
	public static function is_pro(): bool {
		return self::isPro();
	}

	/**
	 * Get centralized Lite vs Pro comparison table data
	 * This is the single source of truth for feature comparison across all admin pages
	 *
	 * @return array Comparison data with 'name', 'lite', 'pro' keys
	 */
	public static function get_comparison_table_data(): array {
		return array(
			array(
				'name' => __( 'Maximum Vehicles', 'mhm-rentiva' ),
				'lite' => sprintf(
					/* translators: %d: maximum number of vehicles allowed in Lite version. */
					__( '%d vehicles', 'mhm-rentiva' ),
					(int) apply_filters( 'mhm_rentiva_lite_max_vehicles', 5 )
				),
				'pro'  => __( 'Unlimited', 'mhm-rentiva' ),
			),
			array(
				'name' => __( 'Maximum Bookings', 'mhm-rentiva' ),
				'lite' => sprintf(
					/* translators: %d: maximum number of bookings allowed in Lite version. */
					__( '%d bookings', 'mhm-rentiva' ),
					(int) apply_filters( 'mhm_rentiva_lite_max_bookings', 50 )
				),
				'pro'  => __( 'Unlimited', 'mhm-rentiva' ),
			),
			array(
				'name' => __( 'Maximum Customers', 'mhm-rentiva' ),
				'lite' => sprintf(
					/* translators: %d: maximum number of customers allowed in Lite version. */
					__( '%d customers', 'mhm-rentiva' ),
					(int) apply_filters( 'mhm_rentiva_lite_max_customers', 10 )
				),
				'pro'  => __( 'Unlimited', 'mhm-rentiva' ),
			),
			array(
				'name' => __( 'Maximum Addons', 'mhm-rentiva' ),
				'lite' => sprintf(
					/* translators: %d: maximum number of addon services allowed in Lite version. */
					__( '%d addon services', 'mhm-rentiva' ),
					(int) apply_filters( 'mhm_rentiva_lite_max_addons', 4 )
				),
				'pro'  => __( 'Unlimited', 'mhm-rentiva' ),
			),
			array(
				'name' => __( 'VIP Transfer Routes', 'mhm-rentiva' ),
				'lite' => sprintf(
					/* translators: %d: maximum number of transfer routes allowed in Lite version. */
					__( '%d routes', 'mhm-rentiva' ),
					(int) apply_filters( 'mhm_rentiva_lite_max_transfer_routes', 3 )
				),
				'pro'  => __( 'Unlimited', 'mhm-rentiva' ),
			),
			array(
				'name' => __( 'Gallery Images', 'mhm-rentiva' ),
				'lite' => sprintf(
					/* translators: %d: maximum number of gallery images allowed per vehicle in Lite version. */
					__( '%d images/vehicle', 'mhm-rentiva' ),
					(int) apply_filters( 'mhm_rentiva_lite_max_gallery_images', 5 )
				),
				'pro'  => __( 'Unlimited', 'mhm-rentiva' ),
			),
			array(
				'name' => __( 'Report Date Range', 'mhm-rentiva' ),
				'lite' => sprintf(
					/* translators: %d: maximum number of days for report range in Lite version. */
					__( '%d days', 'mhm-rentiva' ),
					(int) apply_filters( 'mhm_rentiva_lite_reports_max_days', 30 )
				),
				'pro'  => __( 'Unlimited', 'mhm-rentiva' ),
			),
			array(
				'name' => __( 'Report Rows', 'mhm-rentiva' ),
				'lite' => sprintf(
					/* translators: %d: maximum number of rows for reports in Lite version. */
					__( '%d rows', 'mhm-rentiva' ),
					(int) apply_filters( 'mhm_rentiva_lite_reports_max_rows', 500 )
				),
				'pro'  => __( 'Unlimited', 'mhm-rentiva' ),
			),
			array(
				'name' => __( 'Payment Gateways', 'mhm-rentiva' ),
				'lite' => __( 'WooCommerce', 'mhm-rentiva' ),
				'pro'  => __( 'WooCommerce', 'mhm-rentiva' ),
			),
			array(
				'name' => __( 'Export Formats', 'mhm-rentiva' ),
				'lite' => esc_html__( 'CSV only', 'mhm-rentiva' ),
				'pro'  => esc_html__( 'CSV, JSON, Excel, XML, PDF', 'mhm-rentiva' ),
			),
			array(
				'id'             => 'advanced_reports',
				'name'           => __( 'Advanced Reports', 'mhm-rentiva' ),
				'lite'           => __( 'Not available', 'mhm-rentiva' ),
				'lite_icon'      => '❌',
				'pro'            => __( 'Available', 'mhm-rentiva' ),
				'pro_icon'       => '✅',
				'lite_available' => false,
				'pro_available'  => true,
			),
			array(
				'id'             => 'messaging_system',
				'name'           => __( 'Messaging System', 'mhm-rentiva' ),
				'lite'           => __( 'Not available', 'mhm-rentiva' ),
				'lite_icon'      => '❌',
				'pro'            => __( 'Available', 'mhm-rentiva' ),
				'pro_icon'       => '✅',
				'lite_available' => false,
				'pro_available'  => true,
			),
			array(
				'id'             => 'vendor_payout',
				'name'           => __( 'Vendor & Payout', 'mhm-rentiva' ),
				'lite'           => __( 'Not available', 'mhm-rentiva' ),
				'lite_icon'      => '❌',
				'pro'            => __( 'Available', 'mhm-rentiva' ),
				'pro_icon'       => '✅',
				'lite_available' => false,
				'pro_available'  => true,
			),
			array(
				'name' => __( 'REST API Access', 'mhm-rentiva' ),
				'lite' => __( 'Limited', 'mhm-rentiva' ),
				'pro'  => __( 'Full REST API', 'mhm-rentiva' ),
			),
		);
	}



	/**
	 * Helper to render comparison table HTML
	 *
	 * @param bool $compact Whether to use compact mode (no icons, shorter text)
	 * @return void
	 */
	public static function render_comparison_table( bool $compact = false ): void {
		$features = self::get_comparison_table_data();

		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Feature', 'mhm-rentiva' ) . '</th>';
		echo '<th>' . esc_html__( 'Lite', 'mhm-rentiva' ) . '</th>';
		echo '<th>' . esc_html__( 'Pro', 'mhm-rentiva' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		foreach ( $features as $feature ) {
			$lite_display = isset( $feature['lite_icon'] ) && ! $compact
				? $feature['lite_icon'] . ' ' . $feature['lite']
				: $feature['lite'];

			$pro_display = isset( $feature['pro_icon'] ) && ! $compact
				? $feature['pro_icon'] . ' ' . $feature['pro']
				: $feature['pro'];

			echo '<tr>';
			echo '<td><strong>' . esc_html( $feature['name'] ) . '</strong></td>';
			echo '<td>' . esc_html( $lite_display ) . '</td>';
			echo '<td><strong>' . esc_html( $pro_display ) . '</strong></td>';
			echo '</tr>';
		}

		echo '</tbody>';
		echo '</table>';
	}
}
