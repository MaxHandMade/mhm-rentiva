<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\View\Tabs;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Settings\View\AbstractTabRenderer;
use MHMRentiva\Admin\Settings\Groups\TransferSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renderer for the Transfer Settings tab
 */
final class TransferSettingsRenderer extends AbstractTabRenderer {


	public function __construct() {
		parent::__construct(
			__( 'Transfer Settings', 'mhm-rentiva' ),
			'transfer'
		);
	}

	/**
	 * @inheritDoc
	 */
	public function get_header_actions(): array {
		return array(
			array(
				'text'  => __( 'Manage Locations', 'mhm-rentiva' ),
				'url'   => admin_url( 'admin.php?page=mhm-rentiva-transfer-locations' ),
				'class' => 'button button-secondary',
				'icon'  => 'dashicons-location',
			),
			array(
				'text'  => __( 'Manage Routes', 'mhm-rentiva' ),
				'url'   => admin_url( 'admin.php?page=mhm-rentiva-transfer-routes' ),
				'class' => 'button button-secondary',
				'icon'  => 'dashicons-networking',
			),
			$this->get_standard_reset_action(),
		);
	}

	/**
	 * @inheritDoc
	 */
	public function render(): void {
		if ( class_exists( TransferSettings::class ) ) {
			TransferSettings::render_settings_section();
		} else {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Transfer Settings configuration group not found.', 'mhm-rentiva' ) . '</p></div>';
		}
	}
}
