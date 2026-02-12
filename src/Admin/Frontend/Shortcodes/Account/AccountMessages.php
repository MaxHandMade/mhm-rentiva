<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Account;

use MHMRentiva\Admin\Frontend\Account\AccountRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Account Messages Shortcode
 */
final class AccountMessages extends AbstractAccountShortcode {


	protected static function get_shortcode_tag(): string {
		return 'rentiva_messages';
	}

	protected static function get_template_path(): string {
		return 'account/messages';
	}

	protected static function get_default_attributes(): array {
		return array(
			'hide_nav' => false,
		);
	}

	protected static function prepare_template_data( array $atts ): array {
		$template_data = AccountRenderer::get_messages_data( $atts );

		// If error is returned by AccountRenderer, we should handle it.
		// However, AbstractShortcode expects an array to pass to the template.
		// If there's an error, we might want to return it as a data key.
		return $template_data;
	}

	protected static function enqueue_assets( array $atts = array() ): void {
		parent::enqueue_assets();

		// Account Messages JS is handled within AccountRenderer::get_messages_data currently.
		// But it's better to keep it here if we want to follow AbstractShortcode pattern.
		// However, AccountRenderer::get_messages_data already enqueues it.
	}
}
