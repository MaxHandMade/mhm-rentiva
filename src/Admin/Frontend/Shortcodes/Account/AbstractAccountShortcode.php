<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Account;

use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base class for all Account shortcodes
 */
abstract class AbstractAccountShortcode extends AbstractShortcode {


	/**
	 * Common assets for account pages
	 */
	protected static function enqueue_assets( array $atts = array() ): void {
		// Main account CSS
		wp_enqueue_style(
			'mhm-rentiva-my-account',
			MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/my-account.css',
			array(),
			MHM_RENTIVA_VERSION
		);

		// Core vehicle card CSS (required for favorites page and any page showing vehicles)
		wp_enqueue_style(
			'mhm-vehicle-card-css',
			MHM_RENTIVA_PLUGIN_URL . 'assets/css/core/vehicle-card.css',
			array(),
			MHM_RENTIVA_VERSION
		);

		// Account JS
		wp_enqueue_script(
			'mhm-rentiva-my-account',
			MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/my-account.js',
			array( 'jquery' ),
			MHM_RENTIVA_VERSION,
			true
		);

		wp_localize_script(
			'mhm-rentiva-my-account',
			'mhmRentivaAccount',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'restUrl'     => rest_url( 'mhm-rentiva/v1/' ),
				'nonce'       => wp_create_nonce( 'mhm_rentiva_account' ),
				'uploadNonce' => wp_create_nonce( 'mhm_rentiva_upload_receipt' ),
				'restNonce'   => wp_create_nonce( 'wp_rest' ),
				'i18n'        => array(
					'loading'                => __( 'Loading...', 'mhm-rentiva' ),
					'error'                  => __( 'An error occurred.', 'mhm-rentiva' ),
					'success'                => __( 'Success!', 'mhm-rentiva' ),
					'confirm'                => __( 'Are you sure?', 'mhm-rentiva' ),
					'uploading'              => __( 'Uploading...', 'mhm-rentiva' ),
					'upload_success'         => __( 'Receipt uploaded successfully.', 'mhm-rentiva' ),
					'upload_error'           => __( 'Receipt upload failed.', 'mhm-rentiva' ),
					'savedSuccessfully'      => __( 'Account details saved successfully.', 'mhm-rentiva' ),
					'removedFromFavorites'   => __( 'Vehicle removed from favorites.', 'mhm-rentiva' ),
					'addedToFavorites'       => __( 'Vehicle added to favorites.', 'mhm-rentiva' ),
					'favoritesCleared'       => __( 'All favorites cleared.', 'mhm-rentiva' ),
					'passwords_do_not_match' => __( 'Passwords do not match.', 'mhm-rentiva' ),
					'cancel_changes_confirm' => __( 'Are you sure you want to cancel changes?', 'mhm-rentiva' ),
					'no_favorites'           => __( 'No favorite vehicles yet.', 'mhm-rentiva' ),
					'login_required'         => __( 'You can add vehicles to favorites using the heart icon.', 'mhm-rentiva' ),
					'cancel_booking'         => __( 'Cancel Booking', 'mhm-rentiva' ),
					'save_changes'           => __( 'Save Changes', 'mhm-rentiva' ),
					'favorites_count_single' => __( 'vehicle in your favorites', 'mhm-rentiva' ),
					'favorites_count_plural' => __( 'vehicles in your favorites', 'mhm-rentiva' ),
				),
			)
		);
	}
}
