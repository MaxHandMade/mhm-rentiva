<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\View\Tabs;

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
	public function render(): void {
		?>
		<div class="mhm-settings-tab-header">
			<div class="mhm-settings-title-group">
				<h2><?php echo esc_html( $this->label ); ?></h2>
				<p class="description"><?php esc_html_e( 'Manage transfer system configurations, locations, and routing rules.', 'mhm-rentiva' ); ?></p>
			</div>

			<div class="mhm-settings-header-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=mhm-rentiva-transfer-locations' ) ); ?>" class="button button-secondary">
					<span class="dashicons dashicons-location"></span>
					<?php esc_html_e( 'Manage Locations', 'mhm-rentiva' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=mhm-rentiva-transfer-routes' ) ); ?>" class="button button-secondary">
					<span class="dashicons dashicons-networking"></span>
					<?php esc_html_e( 'Manage Routes', 'mhm-rentiva' ); ?>
				</a>
				<a href="https://maxhandmade.github.io/mhm-rentiva-docs/" target="_blank" class="button button-secondary mhm-docs-btn">
					<span class="dashicons dashicons-book-alt"></span>
					<?php esc_html_e( 'Documentation', 'mhm-rentiva' ); ?>
				</a>
				<?php $this->render_reset_button(); ?>
			</div>
		</div>
		<hr class="wp-header-end">

		<?php
		if ( class_exists( TransferSettings::class ) ) {
			TransferSettings::render_settings_section();
		} else {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Transfer Settings configuration group not found.', 'mhm-rentiva' ) . '</p></div>';
		}
	}
}
