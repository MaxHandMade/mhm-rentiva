<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Account;

if (!defined('ABSPATH')) {
    exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Account Assets Manager
 *
 * CSS and JavaScript management for My Account pages
 *
 * @since 4.0.0
 */
final class AccountAssets {

	/**
	 * Load assets
	 */
	public static function enqueue(): void {
		// My Account CSS
		wp_enqueue_style(
			'mhm-rentiva-my-account',
			MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/my-account.css',
			array(),
			MHM_RENTIVA_VERSION
		);

		// My Account JavaScript
		wp_enqueue_script(
			'mhm-rentiva-my-account',
			MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/my-account.js',
			array( 'jquery' ),
			MHM_RENTIVA_VERSION,
			true
		);

		// Localize script
		wp_localize_script(
			'mhm-rentiva-my-account',
			'mhmRentivaAccount',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'mhm_rentiva_account' ),
				'toggleNonce' => wp_create_nonce( 'mhm_rentiva_toggle_favorite' ),
				'accountUrl'  => AccountController::get_account_url(),
				'i18n'        => array(
					'loading'              => __( 'Loading...', 'mhm-rentiva' ),
					'error'                => __( 'An error occurred.', 'mhm-rentiva' ),
					'success'              => __( 'Success!', 'mhm-rentiva' ),
					'confirm'              => __( 'Are you sure?', 'mhm-rentiva' ),
					'savedSuccessfully'    => __( 'Saved successfully!', 'mhm-rentiva' ),
					'deletedSuccessfully'  => __( 'Deleted successfully!', 'mhm-rentiva' ),
					'addedToFavorites'     => __( 'Added to favorites!', 'mhm-rentiva' ),
					'removedFromFavorites' => __( 'Removed from favorites!', 'mhm-rentiva' ),
					'favoritesCleared'     => __( 'All favorites cleared', 'mhm-rentiva' ),
				),
			)
		);
	}
}
