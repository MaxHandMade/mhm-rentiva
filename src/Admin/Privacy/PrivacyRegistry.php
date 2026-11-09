<?php
/**
 * Registers Rentiva's exporters, erasers and policy text with WordPress.
 *
 * @package MHMRentiva
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tools → Export / Erase Personal Data, and Settings → Privacy's policy guide
 * (5a spec T1, T8). Every key starts with `mhm-rentiva-`; Pro registers its
 * own under `mhm-rentiva-pro-`.
 */
final class PrivacyRegistry {

	/**
	 * Hook everything. Safe to call more than once.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( self::class, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( self::class, 'erasers' ) );
		add_action( 'admin_init', array( PolicyText::class, 'add' ) );
	}

	/**
	 * @param array<string, array<string, mixed>> $exporters Registered exporters.
	 * @return array<string, array<string, mixed>>
	 */
	public static function exporters( $exporters ): array {
		$exporters = is_array( $exporters ) ? $exporters : array();

		$exporters[ BookingExporter::GROUP_ID ]      = array(
			'exporter_friendly_name' => __( 'MHM Rentiva bookings', 'mhm-rentiva' ),
			'callback'               => array( BookingExporter::class, 'export' ),
		);
		$exporters[ ContactPersonalData::GROUP_ID ]  = array(
			'exporter_friendly_name' => __( 'MHM Rentiva contact messages', 'mhm-rentiva' ),
			'callback'               => array( ContactPersonalData::class, 'export' ),
		);
		$exporters[ EmailLogPersonalData::GROUP_ID ] = array(
			'exporter_friendly_name' => __( 'MHM Rentiva e-mail log', 'mhm-rentiva' ),
			'callback'               => array( EmailLogPersonalData::class, 'export' ),
		);
		$exporters[ AccountPersonalData::GROUP_ID ]  = array(
			'exporter_friendly_name' => __( 'MHM Rentiva account details', 'mhm-rentiva' ),
			'callback'               => array( AccountPersonalData::class, 'export' ),
		);

		return $exporters;
	}

	/**
	 * @param array<string, array<string, mixed>> $erasers Registered erasers.
	 * @return array<string, array<string, mixed>>
	 */
	public static function erasers( $erasers ): array {
		$erasers = is_array( $erasers ) ? $erasers : array();

		$erasers[ BookingExporter::GROUP_ID ]      = array(
			'eraser_friendly_name' => __( 'MHM Rentiva bookings', 'mhm-rentiva' ),
			'callback'             => array( BookingEraser::class, 'erase' ),
		);
		$erasers[ ContactPersonalData::GROUP_ID ]  = array(
			'eraser_friendly_name' => __( 'MHM Rentiva contact messages', 'mhm-rentiva' ),
			'callback'             => array( ContactPersonalData::class, 'erase' ),
		);
		$erasers[ EmailLogPersonalData::GROUP_ID ] = array(
			'eraser_friendly_name' => __( 'MHM Rentiva e-mail log', 'mhm-rentiva' ),
			'callback'             => array( EmailLogPersonalData::class, 'erase' ),
		);
		$erasers[ AccountPersonalData::GROUP_ID ]  = array(
			'eraser_friendly_name' => __( 'MHM Rentiva account details', 'mhm-rentiva' ),
			'callback'             => array( AccountPersonalData::class, 'erase' ),
		);
		$erasers['mhm-rentiva-app-log']            = array(
			'eraser_friendly_name' => __( 'MHM Rentiva application log', 'mhm-rentiva' ),
			'callback'             => array( AppLogPersonalData::class, 'erase' ),
		);

		return $erasers;
	}
}
