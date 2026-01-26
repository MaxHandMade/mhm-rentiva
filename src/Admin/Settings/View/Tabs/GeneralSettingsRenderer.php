<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\View\Tabs;

use MHMRentiva\Admin\Settings\View\AbstractTabRenderer;
use MHMRentiva\Admin\Settings\Groups\GeneralSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renderer for the General Settings tab
 *
 * Manages core plugin settings such as currency, logo, and general info.
 * Standardized with consistent header and reset actions.
 */
final class GeneralSettingsRenderer extends AbstractTabRenderer {

	public function __construct() {
		parent::__construct(
			__( 'General Settings', 'mhm-rentiva' ),
			'general'
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
				<p class="description"><?php esc_html_e( 'Configure core plugin identity and system-wide formatting.', 'mhm-rentiva' ); ?></p>
			</div>

			<div class="mhm-settings-header-actions">
				<?php \MHMRentiva\Admin\Core\Utilities\UXHelper::render_docs_button(); ?>
				<?php $this->render_reset_button(); ?>
			</div>
		</div>
		<hr class="wp-header-end">

		<?php
		if ( class_exists( GeneralSettings::class ) ) {
			GeneralSettings::render_settings_section();
		} else {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'General Settings configuration group not found.', 'mhm-rentiva' ) . '</p></div>';
		}
	}
}
