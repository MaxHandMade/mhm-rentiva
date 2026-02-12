<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\View;

use MHMRentiva\Admin\Settings\View\SettingsViewHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract Class for Settings Tab Renderers
 */
abstract class AbstractTabRenderer implements TabRendererInterface {

	use \MHMRentiva\Admin\Core\Traits\AdminHelperTrait;

	/**
	 * @param string $label Tab Label
	 * @param string $slug Tab Slug
	 */
	public function __construct(
		protected readonly string $label,
		protected readonly string $slug
	) {}

	/**
	 * @inheritDoc
	 */
	public function get_label(): string {
		return $this->label;
	}

	/**
	 * @inheritDoc
	 */
	public function get_slug(): string {
		return $this->slug;
	}

	/**
	 * Helper: Render reset button for the tab
	 */
	protected function render_reset_button(): void {
		printf(
			'<div class="mhm-tab-reset-section">
                <button type="button" class="button button-secondary mhm-reset-tab-settings" data-tab="%s">
                    <span class="dashicons dashicons-undo" style="vertical-align: middle; margin-right: 5px;"></span>
                    %s
                </button>
            </div>',
			esc_attr( $this->slug ),
			esc_html__( 'Reset This Tab', 'mhm-rentiva' )
		);
	}

	/**
	 * Helper: Render section with nested form removal
	 */
	protected function render_section_clean( string $section_name ): void {
		SettingsViewHelper::render_section_cleanly( $section_name );
	}

	/**
	 * @inheritDoc
	 */
	public function should_wrap_with_form(): bool {
		return true;
	}

	/**
	 * Helper: Get standard reset action for tab header
	 *
	 * @return array Action structure
	 */
	protected function get_standard_reset_action(): array {
		$reset_url = add_query_arg(
			array(
				'reset_defaults' => 'true',
				'tab'            => $this->slug,
				'_wpnonce'       => wp_create_nonce( 'mhm_rentiva_reset_defaults' ),
			),
			admin_url( 'admin.php?page=mhm-rentiva-settings' )
		);

		return array(
			'text'  => __( 'Reset This Tab', 'mhm-rentiva' ),
			'url'   => $reset_url,
			'class' => 'button button-link-delete',
			'icon'  => 'dashicons-undo',
			'data'  => array(
				'onclick' => "return confirm('" . esc_js( __( 'Are you sure you want to reset this tab? This cannot be undone.', 'mhm-rentiva' ) ) . "');",
			),
		);
	}

	/**
	 * @inheritDoc
	 */
	public function get_header_actions(): array {
		return array( $this->get_standard_reset_action() );
	}
}
