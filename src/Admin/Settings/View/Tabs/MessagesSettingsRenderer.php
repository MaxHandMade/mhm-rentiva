<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\View\Tabs;

use MHMRentiva\Admin\Settings\View\AbstractTabRenderer;
use MHMRentiva\Admin\Messages\Settings\MessagesSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renderer for the Messages Settings tab
 *
 * Manages conversation rules, categories, and communication statuses.
 * Refactored for modular sub-tab navigation and standardized UI.
 */
final class MessagesSettingsRenderer extends AbstractTabRenderer {


	public function __construct() {
		parent::__construct(
			__( 'Messages Settings', 'mhm-rentiva' ),
			'messages'
		);
	}

	/**
	 * @inheritDoc
	 */
	public function get_header_actions(): array {
		return array(
			array(
				'text'  => __( 'Reset Conversations', 'mhm-rentiva' ),
				'url'   => '#',
				'class' => 'button button-secondary mhm-reset-messages-btn',
				'icon'  => 'dashicons-image-rotate',
			),
			$this->get_standard_reset_action(),
		);
	}

	/**
	 * @inheritDoc
	 */
	public function render(): void {
		if ( ! class_exists( MessagesSettings::class ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Messaging configuration service not found.', 'mhm-rentiva' ) . '</p></div>';
			return;
		}

		// Initialize Messaging Settings
		MessagesSettings::init();

		$settings      = MessagesSettings::get_settings();
		$active_subtab = isset( $_GET['subtab'] ) ? sanitize_key( wp_unslash( $_GET['subtab'] ) ) : 'email';

		$this->enqueue_messages_assets();

		?>
		<div class="mhm-messages-settings-container">
			<!-- Internal Sub-navigation -->
			<div class="nav-tab-wrapper mhm-messages-subtabs">
				<?php
				$tabs = array(
					'email'      => __( 'Notifications', 'mhm-rentiva' ),
					'general'    => __( 'System Rules', 'mhm-rentiva' ),
					'categories' => __( 'Categories', 'mhm-rentiva' ),
					'statuses'   => __( 'Workflow Statuses', 'mhm-rentiva' ),
				);
				foreach ( $tabs as $slug => $label ) {
					$class = ( $active_subtab === $slug ) ? 'nav-tab-active active' : '';
					printf(
						'<a href="%s" class="nav-tab mhm-subtab %s">%s</a>',
						esc_url( add_query_arg( 'subtab', $slug ) ),
						esc_attr( $class ),
						esc_html( $label )
					);
				}
				?>
			</div>

			<form method="post" action="options.php" class="mhm-settings-form mhm-tabbed-form" id="mhm-messages-settings-form">
				<?php
				settings_fields( MessagesSettings::OPTION_GROUP );

				// Maintain context after save
				$referer = add_query_arg(
					array(
						'page'   => 'mhm-rentiva-settings',
						'tab'    => 'messages',
						'subtab' => $active_subtab,
					),
					admin_url( 'admin.php' )
				);
				echo '<input type="hidden" name="_wp_http_referer" value="' . esc_attr( $referer ) . '">';
				?>

				<div class="mhm-subtab-container">
					<!-- Notifications Tab -->
					<div id="messages-email" class="mhm-subtab-content <?php echo $active_subtab === 'email' ? 'active' : ''; ?>">
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="admin_email"><?php esc_html_e( 'Management Email', 'mhm-rentiva' ); ?></label></th>
								<td>
									<input type="email" id="admin_email" name="<?php echo esc_attr( MessagesSettings::OPTION_NAME ); ?>[admin_email]" value="<?php echo esc_attr( $settings['admin_email'] ?? '' ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" class="regular-text">
									<p class="description"><?php esc_html_e( 'Destination address for staff alerts.', 'mhm-rentiva' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="from_name"><?php esc_html_e( 'Outgoing Name', 'mhm-rentiva' ); ?></label></th>
								<td>
									<input type="text" id="from_name" name="<?php echo esc_attr( MessagesSettings::OPTION_NAME ); ?>[from_name]" value="<?php echo esc_attr( $settings['from_name'] ?? '' ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" class="regular-text">
									<p class="description"><?php esc_html_e( 'Identified sender for message notifications.', 'mhm-rentiva' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"></th>
								<td>
									<label><input type="checkbox" name="<?php echo esc_attr( MessagesSettings::OPTION_NAME ); ?>[email_admin_notifications]" value="1" <?php checked( $settings['email_admin_notifications'] ?? false, true ); ?>> <?php esc_html_e( 'Alert staff on new client messages', 'mhm-rentiva' ); ?></label><br>
									<label><input type="checkbox" name="<?php echo esc_attr( MessagesSettings::OPTION_NAME ); ?>[email_customer_notifications]" value="1" <?php checked( $settings['email_customer_notifications'] ?? false, true ); ?>> <?php esc_html_e( 'Notify clients on staff replies', 'mhm-rentiva' ); ?></label>
								</td>
							</tr>
						</table>
					</div>

					<!-- System Rules Tab -->
					<div id="messages-general" class="mhm-subtab-content <?php echo $active_subtab === 'general' ? 'active' : ''; ?>">
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Dashboard Visibility', 'mhm-rentiva' ); ?></th>
								<td>
									<label><input type="checkbox" name="<?php echo esc_attr( MessagesSettings::OPTION_NAME ); ?>[dashboard_widget_enabled]" value="1" <?php checked( $settings['dashboard_widget_enabled'] ?? false, true ); ?>> <?php esc_html_e( 'Enable main dashboard message widget', 'mhm-rentiva' ); ?></label>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="dashboard_widget_max_messages"><?php esc_html_e( 'Widget Capacity', 'mhm-rentiva' ); ?></label></th>
								<td>
									<input type="number" id="dashboard_widget_max_messages" name="<?php echo esc_attr( MessagesSettings::OPTION_NAME ); ?>[dashboard_widget_max_messages]" value="<?php echo esc_attr( (string) ( $settings['dashboard_widget_max_messages'] ?? 5 ) ); ?>" min="1" max="20" class="small-text">
									<p class="description"><?php esc_html_e( 'Max entries to reveal in dashboard list.', 'mhm-rentiva' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Auto-Responder', 'mhm-rentiva' ); ?></th>
								<td>
									<label><input type="checkbox" name="<?php echo esc_attr( MessagesSettings::OPTION_NAME ); ?>[auto_reply_enabled]" value="1" <?php checked( $settings['auto_reply_enabled'] ?? false, true ); ?>> <?php esc_html_e( 'Send automated acknowledgment to clients', 'mhm-rentiva' ); ?></label>
								</td>
							</tr>
						</table>
					</div>

					<!-- Categories & Statuses (List Views) -->
					<div id="messages-categories" class="mhm-subtab-content <?php echo $active_subtab === 'categories' ? 'active' : ''; ?>">
						<div id="category-list" class="mhm-dynamic-list">
							<?php foreach ( ( $settings['categories'] ?? array() ) as $key => $name ) : ?>
								<div class="mhm-list-item">
									<input type="text" name="<?php echo esc_attr( MessagesSettings::OPTION_NAME ); ?>[categories][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $name ); ?>" class="regular-text" required>
									<button type="button" class="button remove-category-btn dashicons-before dashicons-dismiss"></button>
								</div>
							<?php endforeach; ?>
						</div>
						<div class="mhm-add-row" style="margin-top: 20px; padding: 15px; background: #fdfdfd; border: 1px dashed #ccc;">
							<input type="text" id="new-category-name" name="mhm_new_category_entry" class="regular-text" placeholder="<?php esc_attr_e( 'Identify new message category...', 'mhm-rentiva' ); ?>">
							<button type="button" id="add-category-btn" class="button button-primary"><?php esc_html_e( 'Add Category', 'mhm-rentiva' ); ?></button>
						</div>
					</div>

					<div id="messages-statuses" class="mhm-subtab-content <?php echo $active_subtab === 'statuses' ? 'active' : ''; ?>">
						<div id="status-list" class="mhm-dynamic-list">
							<?php foreach ( ( $settings['statuses'] ?? array() ) as $key => $name ) : ?>
								<div class="mhm-list-item">
									<input type="text" name="<?php echo esc_attr( MessagesSettings::OPTION_NAME ); ?>[statuses][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $name ); ?>" class="regular-text" required>
									<button type="button" class="button remove-status-btn dashicons-before dashicons-dismiss"></button>
								</div>
							<?php endforeach; ?>
						</div>
						<div class="mhm-add-row" style="margin-top: 20px; padding: 15px; background: #fdfdfd; border: 1px dashed #ccc;">
							<input type="text" id="new-status-name" name="mhm_new_status_entry" class="regular-text" placeholder="<?php esc_attr_e( 'Define new workflow stage...', 'mhm-rentiva' ); ?>">
							<button type="button" id="add-status-btn" class="button button-primary"><?php esc_html_e( 'Add Status', 'mhm-rentiva' ); ?></button>
						</div>
					</div>
				</div>

				<div class="submit-section" style="margin-top: 30px;">
					<?php submit_button( __( 'Save Communication Settings', 'mhm-rentiva' ), 'primary', 'submit', true, array( 'id' => 'mhm-save-messages-btn' ) ); ?>
				</div>
			</form>
		</div>
		<?php
	}

	private function enqueue_messages_assets(): void {
		$version = defined( 'MHM_RENTIVA_VERSION' ) ? (string) MHM_RENTIVA_VERSION : '1.0.0';
		$url     = defined( 'MHM_RENTIVA_PLUGIN_URL' ) ? (string) MHM_RENTIVA_PLUGIN_URL : '';

		wp_enqueue_style( 'mhm-messages-settings', esc_url( $url . 'assets/css/admin/messages-settings.css' ), array(), $version );
		wp_enqueue_script( 'mhm-messages-settings', esc_url( $url . 'assets/js/admin/messages-settings.js' ), array( 'jquery' ), $version, true );

		wp_localize_script(
			'mhm-messages-settings',
			'mhmMessagesSettings',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'mhm_messages_settings' ),
				'resetNonce' => wp_create_nonce( 'mhm_rentiva_settings_nonce' ),
				'strings'    => array(
					'enterCategoryName'     => __( 'Name required.', 'mhm-rentiva' ),
					'confirmDeleteCategory' => __( 'Permanent deletion of this category?', 'mhm-rentiva' ),
					'enterStatusName'       => __( 'Status title required.', 'mhm-rentiva' ),
					'confirmDeleteStatus'   => __( 'Permanent deletion of this status?', 'mhm-rentiva' ),
					'saving'                => __( 'Updating...', 'mhm-rentiva' ),
				),
			)
		);
	}

	public function should_wrap_with_form(): bool {
		return false;
	}
}
