<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Account;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Core\AssetManager;
use MHMRentiva\Admin\Frontend\Account\AccountController;
use MHMRentiva\Admin\Frontend\Account\AccountSurface;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base class for all Account shortcodes
 */
abstract class AbstractAccountShortcode extends AbstractShortcode {


	/**
	 * Common assets for account pages.
	 *
	 * Lite's own account shortcodes are the redesigned `rvs-` screens and load
	 * that layer only. Every other subclass -- the add-on's messages and
	 * vendor bookings -- keeps the old account stylesheets
	 * (AccountSurface::classify_shortcode()).
	 */
	protected static function enqueue_assets( array $atts = array() ): void {
		if ( AccountSurface::RVS === AccountSurface::classify_shortcode( static::get_shortcode_tag() ) ) {
			AssetManager::enqueue_rvs_account();
		} else {
			AccountController::enqueue_legacy_account_styles();
		}

		AccountController::enqueue_account_script();
	}
}
