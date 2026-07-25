<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\View\Tabs;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Settings\View\AbstractTabRenderer;
use MHMRentiva\Admin\REST\Settings\RESTSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renderer for the Integration Settings tab
 *
 * Manages external API connectivity, key generation, and endpoint security.
 * Ported and refactored from the legacy view system for unified aesthetics.
 */
final class IntegrationRenderer extends AbstractTabRenderer {


	public function __construct() {
		parent::__construct(
			__( 'Integration Settings', 'mhm-rentiva' ),
			'integration'
		);
	}

	/**
	 * @inheritDoc
	 */
	public function get_header_actions(): array {
		$reset_action         = $this->get_standard_reset_action();
		$reset_action['text'] = __( 'Factory Reset API', 'mhm-rentiva' );

		return array( $reset_action );
	}

	/**
	 * @inheritDoc
	 */
	public function render(): void {
		?>
		<div class="mhm-integration-page-content" style="margin-top: 25px;">
			<form method="post" action="options.php" class="mhm-settings-form mhm-integration-form" id="mhm-rest-settings-form">
				<?php
				if ( class_exists( RESTSettings::class ) ) {
					// WordPress Settings API
					settings_fields( RESTSettings::OPTION_NAME );

					// Track active tab
					echo '<input type="hidden" name="current_active_tab" value="integration">';

					// API Limit & Rules
					RESTSettings::render_settings_section();

					// Detailed management sections
					$this->render_endpoints_section();
				} else {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'REST Integration core service is missing.', 'mhm-rentiva' ) . '</p></div>';
				}
				?>

				<div class="submit-section" style="margin-top: 30px;">
					<?php submit_button( __( 'Commit API Configuration', 'mhm-rentiva' ), 'primary', 'submit', true, array( 'id' => 'mhm-save-integration-btn' ) ); ?>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders available endpoints for developer reference
	 */
	private function render_endpoints_section(): void {
		?>
		<div class="mhm-integration-section mhm-endpoints-wrapper" style="margin-top: 50px; border-top: 1px solid #eee; padding-top: 30px;">
			<h3><?php esc_html_e( 'Developer Endpoint Reference', 'mhm-rentiva' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Direct access URLs for custom integrations and headless implementations.', 'mhm-rentiva' ); ?></p>

			<div id="mhm-endpoints-list-container" class="mhm-dynamic-container" style="margin-top: 20px;">
				<div id="mhm-endpoints-list">
					<button type="button" id="mhm-refresh-endpoints-btn" class="button button-secondary">
						<span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Reveal API Directory', 'mhm-rentiva' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * This tab manages its own form logic for REST settings
	 */
	public function should_wrap_with_form(): bool {
		return false;
	}
}
