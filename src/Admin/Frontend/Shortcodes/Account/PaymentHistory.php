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
 * Payment History Shortcode
 */
final class PaymentHistory extends AbstractAccountShortcode {

	/** Renders inside the front-end standard's shell. */
	protected static bool $rvs_shell = true;


	protected static function get_shortcode_tag(): string {
		return 'rentiva_payment_history';
	}

	protected static function get_template_path(): string {
		return 'account/payment-history';
	}

	protected static function get_default_attributes(): array {
		return array(
			// '10' is the page size the list always had; the '20' here was
			// never read.
			'limit'               => '10',
			'hide_nav'            => false,
			'orderby'             => 'date',
			'order'               => 'desc',
			'status'              => '',
			// Today's markup: date and method shown, the title not a link.
			'show_date'           => '1',
			'show_payment_method' => '1',
			'show_booking_link'   => '0',
			'show_pagination'     => '1',
		);
	}

	protected static function prepare_template_data( array $atts ): array {
		// An editor preview always shows the fixed sample rows, never the
		// editing user's own payments.
		if ( RenderContext::current()->allows_sample() ) {
			$data                            = AccountRenderer::get_payment_history_preview_data( $atts );
			$data[ self::PREVIEW_BADGE_KEY ] = __( 'Preview: sample data. Logged-in customers see their own records.', 'mhm-rentiva' );
			return $data;
		}

		return AccountRenderer::get_payment_history_data( $atts );
	}
}
