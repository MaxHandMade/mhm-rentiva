<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Account;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Frontend\Account\AccountRenderer;
use MHMRentiva\Frontend\RenderContext;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * My Bookings Shortcode
 */
final class MyBookings extends AbstractAccountShortcode {


	protected static function get_shortcode_tag(): string {
		return 'rentiva_my_bookings';
	}

	protected static function get_template_path(): string {
		return 'account/bookings';
	}

	protected static function get_default_attributes(): array {
		return array(
			'limit'              => '10',
			'status'             => '',
			'order'              => 'DESC',
			'hide_nav'           => false,
			// Spec 3A Task 7a: these gate markup that was unconditional, so on by default.
			'show_image'         => '1',
			'show_booking_dates' => '1',
			'show_status_toggle' => '1',
			'show_price'         => '1',
			'show_pagination'    => '1',
		);
	}

	protected static function prepare_template_data( array $atts ): array {
		// An editor preview always shows the fixed sample rows, never the
		// editing user's own bookings (spec 3B-2a §2.1, account class).
		if ( RenderContext::current()->allows_sample() ) {
			$data                            = AccountRenderer::get_bookings_preview_data( $atts );
			$data[ self::PREVIEW_BADGE_KEY ] = __( 'Preview: sample data. Logged-in customers see their own records.', 'mhm-rentiva' );
			return $data;
		}

		return AccountRenderer::get_bookings_data( $atts );
	}

	protected static function enqueue_assets( array $atts = array() ): void {
		parent::enqueue_assets();

		wp_enqueue_style(
			'mhm-rentiva-bookings-page',
			MHMRENTIVA_PLUGIN_URL . 'assets/css/frontend/bookings-page.css',
			array( 'mhm-rentiva-css-variables' ),
			MHMRENTIVA_VERSION
		);
	}
}
