<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Messages\Settings;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Core\Utilities\SettingsHelper;
use MHMRentiva\Admin\Core\Utilities\ErrorHandler;
use MHMRentiva\Admin\Messages\Core\MessageUrlHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Messaging system settings
 */
final class MessagesSettings {





	const OPTION_GROUP = 'mhm_rentiva_messages';
	const OPTION_NAME  = 'mhm_rentiva_messages_settings';

	/**
	 * Initialize settings
	 */
	public static function init(): void {
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
	}

	/**
	 * Register settings
	 */
	public static function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'sanitize_callback' => array( self::class, 'sanitize_settings' ),
				'default'           => self::get_default_settings(),
			)
		);

		// Email settings section
		add_settings_section(
			'mhm_messages_email',
			__( 'Email Settings', 'mhm-rentiva' ),
			null,
			self::OPTION_GROUP
		);

		// General settings section
		add_settings_section(
			'mhm_messages_general',
			__( 'General Settings', 'mhm-rentiva' ),
			null,
			self::OPTION_GROUP
		);

		// Categories section
		add_settings_section(
			'mhm_messages_categories',
			__( 'Message Categories', 'mhm-rentiva' ),
			null,
			self::OPTION_GROUP
		);

		// Statuses section
		add_settings_section(
			'mhm_messages_statuses',
			__( 'Message Statuses', 'mhm-rentiva' ),
			null,
			self::OPTION_GROUP
		);
	}

	/**
	 * Sanitize settings
	 */
	public static function sanitize_settings( array $input ): array {
		$sanitized = array();

		// Boolean values
		$boolean_fields = array(
			'email_admin_notifications',
			'email_customer_notifications',
			'email_reply_notifications',
			'email_status_change_notifications',
			'dashboard_widget_enabled',
			'auto_reply_enabled',
		);

		foreach ( $boolean_fields as $field ) {
			$sanitized[ $field ] = isset( $input[ $field ] ) ? (bool) $input[ $field ] : false;
		}

		// Numeric values
		$sanitized['lite_messages_per_month']       = absint( $input['lite_messages_per_month'] ?? 10 );
		$sanitized['lite_messages_per_day']         = absint( $input['lite_messages_per_day'] ?? 3 );
		$sanitized['dashboard_widget_max_messages'] = absint( $input['dashboard_widget_max_messages'] ?? 5 );

		// String values
		$sanitized['admin_email'] = sanitize_email( (string) ( $input['admin_email'] ?? '' ) );
		$sanitized['from_name']   = sanitize_text_field( (string) ( $input['from_name'] ?? '' ) );
		$sanitized['from_email']  = sanitize_email( (string) ( $input['from_email'] ?? '' ) );

		// Categories and statuses
		$sanitized['categories'] = self::sanitize_categories( $input['categories'] ?? array() );
		$sanitized['statuses']   = self::sanitize_statuses( $input['statuses'] ?? array() );

		// Handle NEW category entry (from separate input field)
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified by WordPress Settings API; value is unslashed and sanitized in-place.
		$new_category_raw = isset( $_POST['mhm_new_category_entry'] ) ? wp_unslash( (string) $_POST['mhm_new_category_entry'] ) : '';
		$new_category     = '' !== $new_category_raw
			? sanitize_text_field( trim( (string) $new_category_raw ) )
			: '';

		if ( ! empty( $new_category ) ) {
			$new_key = sanitize_key( $new_category );
			// Prevent duplicates
			if ( ! isset( $sanitized['categories'][ $new_key ] ) ) {
				$sanitized['categories'][ $new_key ] = $new_category;
			}
		}

		// Handle NEW status entry (from separate input field)
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified by WordPress Settings API; value is unslashed and sanitized in-place.
		$new_status_raw = isset( $_POST['mhm_new_status_entry'] ) ? wp_unslash( (string) $_POST['mhm_new_status_entry'] ) : '';
		$new_status     = '' !== $new_status_raw
			? sanitize_text_field( trim( (string) $new_status_raw ) )
			: '';

		if ( ! empty( $new_status ) ) {
			$new_key = sanitize_key( $new_status );
			// Prevent duplicates
			if ( ! isset( $sanitized['statuses'][ $new_key ] ) ) {
				$sanitized['statuses'][ $new_key ] = $new_status;
			}
		}

		// Email templates
		// auto_reply_message removed in favor of Template System

		return $sanitized;
	}

	/**
	 * Sanitize categories
	 */
	private static function sanitize_categories( array $categories ): array {
		$sanitized = array();
		foreach ( $categories as $key => $value ) {
			// Support both array format [key => name] and nested array format [['name' => 'value']]
			if ( is_array( $value ) ) {
				$name = $value['name'] ?? $value[0] ?? '';
			} else {
				$name = $value;
			}

			if ( ! empty( trim( (string) $name ) ) ) {
				$sanitized_key               = is_string( $key ) ? sanitize_key( $key ) : sanitize_key( $name );
				$sanitized[ $sanitized_key ] = sanitize_text_field( (string) $name );
			}
		}
		return $sanitized;
	}

	/**
	 * Sanitize statuses
	 */
	private static function sanitize_statuses( array $statuses ): array {
		$sanitized = array();
		foreach ( $statuses as $key => $value ) {
			// Support both array format [key => name] and nested array format [['name' => 'value']]
			if ( is_array( $value ) ) {
				$name = $value['name'] ?? $value[0] ?? '';
			} else {
				$name = $value;
			}

			if ( ! empty( trim( (string) $name ) ) ) {
				$sanitized_key               = is_string( $key ) ? sanitize_key( $key ) : sanitize_key( $name );
				$sanitized[ $sanitized_key ] = sanitize_text_field( (string) $name );
			}
		}
		return $sanitized;
	}

	/**
	 * Default settings
	 */
	public static function get_default_settings(): array {
		return array(
			// Email settings
			'email_admin_notifications'         => true,
			'email_customer_notifications'      => true,
			'email_reply_notifications'         => true,
			'email_status_change_notifications' => true,

			// Message limits (for Lite version)
			'lite_messages_per_month'           => 10,
			'lite_messages_per_day'             => 3,

			// Message categories
			'categories'                        => array(
				'general'    => __( 'General', 'mhm-rentiva' ),
				'booking'    => __( 'Booking', 'mhm-rentiva' ),
				'payment'    => __( 'Payment', 'mhm-rentiva' ),
				'technical'  => __( 'Technical Support', 'mhm-rentiva' ),
				'complaint'  => __( 'Complaint', 'mhm-rentiva' ),
				'suggestion' => __( 'Suggestion', 'mhm-rentiva' ),
			),

			// Message statuses
			'statuses'                          => array(
				'pending'  => __( 'Pending', 'mhm-rentiva' ),
				'answered' => __( 'Answered', 'mhm-rentiva' ),
				'closed'   => __( 'Closed', 'mhm-rentiva' ),
				'urgent'   => __( 'Urgent', 'mhm-rentiva' ),
			),

			// Message priorities
			'priorities'                        => array(
				'normal' => __( 'Normal', 'mhm-rentiva' ),
				'high'   => __( 'High', 'mhm-rentiva' ),
				'urgent' => __( 'Urgent', 'mhm-rentiva' ),
			),

			// Email template settings
			'admin_email'                       => '', // Empty for global override
			'from_name'                         => '', // Empty for global override
			'from_email'                        => '', // Empty for global override
			'email_from_name'                   => '', // Empty for global override
			'email_from_email'                  => '', // Empty for global override
			'email_reply_to'                    => '', // Empty for global override

			// Dashboard widget settings
			'dashboard_widget_enabled'          => true,
			'dashboard_widget_max_messages'     => 5,

			// Auto reply settings
			'auto_reply_enabled'                => false,

			// Thread settings
			'max_thread_messages'               => 50,
			'auto_close_inactive_days'          => 30,

			// Notification settings
			'notification_sound_enabled'        => false,
			'notification_popup_enabled'        => true,

			// Security settings
			'token_expiry_hours'                => 24,
			'max_attachments_per_message'       => 3,
			'allowed_attachment_types'          => array( 'jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt' ),
			'max_attachment_size_mb'            => 5,
		);
	}

	/**
	 * Get current settings
	 */
	public static function get_settings(): array {
		$defaults = self::get_default_settings();
		$settings = get_option( self::OPTION_NAME, array() );

		return array_merge( $defaults, $settings );
	}

	/**
	 * Get specific setting
	 */
	public static function get_setting( string $key, $default = null ) {
		$settings = self::get_settings();
		return $settings[ $key ] ?? $default;
	}

	/**
	 * Save settings
	 */
	public static function save_settings( array $settings ): bool {
		$sanitized = self::sanitize_settings( $settings );
		return update_option( self::OPTION_NAME, $sanitized );
	}



	/**
	 * Get categories
	 */
	public static function get_categories(): array {
		return self::get_setting( 'categories', array() );
	}

	/**
	 * Get statuses
	 */
	public static function get_statuses(): array {
		return self::get_setting( 'statuses', array() );
	}

	/**
	 * Get priorities
	 */
	public static function get_priorities(): array {
		return self::get_setting(
			'priorities',
			array(
				'normal' => esc_html__( 'Normal', 'mhm-rentiva' ),
				'high'   => esc_html__( 'High', 'mhm-rentiva' ),
				'urgent' => esc_html__( 'Urgent', 'mhm-rentiva' ),
			)
		);
	}

	/**
	 * Check if email notifications are enabled
	 */
	public static function is_email_enabled( string $type ): bool {
		return self::get_setting( 'email_' . $type . '_notifications', true );
	}

	/**
	 * Render settings page
	 */
	public static function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'mhm-rentiva' ) );
		}

		$settings = self::get_settings();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab selector for settings UI rendering.
		$active_tab = sanitize_key( $_GET['tab'] ?? 'email' );

		?>
		<div class="wrap mhm-settings-tabs">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<!-- Tab Navigation -->
			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( MessageUrlHelper::get_messages_settings_url( 'email' ) ); ?>"
					class="nav-tab <?php echo 'email' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Email', 'mhm-rentiva' ); ?>
				</a>
				<a href="<?php echo esc_url( MessageUrlHelper::get_messages_settings_url( 'general' ) ); ?>"
					class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'General', 'mhm-rentiva' ); ?>
				</a>
				<a href="<?php echo esc_url( MessageUrlHelper::get_messages_settings_url( 'categories' ) ); ?>"
					class="nav-tab <?php echo 'categories' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Categories', 'mhm-rentiva' ); ?>
				</a>
				<a href="<?php echo esc_url( MessageUrlHelper::get_messages_settings_url( 'statuses' ) ); ?>"
					class="nav-tab <?php echo 'statuses' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Statuses', 'mhm-rentiva' ); ?>
				</a>
			</nav>

			<!-- Settings Form -->
			<form method="post" action="options.php" id="mhm-messages-settings-form">
				<?php
				settings_fields( self::OPTION_GROUP );
				?>

				<!-- Email Tab -->
				<div id="email" class="tab-content <?php echo $active_tab === 'email' ? 'active' : ''; ?>">
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Admin Email', 'mhm-rentiva' ); ?></th>
							<td>
								<input type="email" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[admin_email]"
									value="<?php echo esc_attr( $settings['admin_email'] ); ?>" class="regular-text">
								<p class="description"><?php esc_html_e( 'Email address for message notifications', 'mhm-rentiva' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Sender Name', 'mhm-rentiva' ); ?></th>
							<td>
								<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[from_name]"
									value="<?php echo esc_attr( $settings['from_name'] ); ?>" class="regular-text">
								<p class="description"><?php esc_html_e( 'Sender name to display in emails', 'mhm-rentiva' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Sender Email', 'mhm-rentiva' ); ?></th>
							<td>
								<input type="email" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[from_email]"
									value="<?php echo esc_attr( $settings['from_email'] ); ?>" class="regular-text">
								<p class="description"><?php esc_html_e( 'Email address to send emails from', 'mhm-rentiva' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Admin Notifications', 'mhm-rentiva' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[email_admin_notifications]"
										value="1" <?php checked( (bool) $settings['email_admin_notifications'] ); ?>>
									<?php esc_html_e( 'Send notification to admin when new message arrives', 'mhm-rentiva' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Customer Notifications', 'mhm-rentiva' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[email_customer_notifications]"
										value="1" <?php checked( (bool) $settings['email_customer_notifications'] ); ?>>
									<?php esc_html_e( 'Send notification to customer when reply arrives', 'mhm-rentiva' ); ?>
								</label>
							</td>
						</tr>
					</table>
				</div>

				<!-- General Tab -->
				<div id="general" class="tab-content <?php echo 'general' === $active_tab ? 'active' : ''; ?>">
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Dashboard Widget', 'mhm-rentiva' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[dashboard_widget_enabled]"
										value="1" <?php checked( (bool) $settings['dashboard_widget_enabled'] ); ?>>
									<?php esc_html_e( 'Show message widget in dashboard', 'mhm-rentiva' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Widget Max Messages', 'mhm-rentiva' ); ?></th>
							<td>
								<input type="number" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[dashboard_widget_max_messages]"
									value="<?php echo esc_attr( (string) $settings['dashboard_widget_max_messages'] ); ?>"
									min="1" max="20" class="small-text">
								<p class="description"><?php esc_html_e( 'Maximum number of messages to show in dashboard widget', 'mhm-rentiva' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Auto Reply', 'mhm-rentiva' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[auto_reply_enabled]"
										value="1" <?php checked( (bool) $settings['auto_reply_enabled'] ); ?>>
									<?php esc_html_e( 'Send automatic reply to new messages', 'mhm-rentiva' ); ?>
								</label>
							</td>
						</tr>
					</table>
				</div>

				<!-- Categories Tab -->
				<div id="categories" class="tab-content <?php echo 'categories' === $active_tab ? 'active' : ''; ?>">
					<div id="category-list">
						<?php foreach ( $settings['categories'] as $key => $name ) : ?>
							<div class="mhm-category-item">
								<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[categories][<?php echo esc_attr( (string) $key ); ?>]"
									value="<?php echo esc_attr( (string) $name ); ?>" class="category-name" required>
								<button type="button" class="remove-category-btn"><?php esc_html_e( 'Delete', 'mhm-rentiva' ); ?></button>
							</div>
						<?php endforeach; ?>
					</div>

					<div class="mhm-add-item">
						<input type="text" id="new-category-name" placeholder="<?php esc_attr_e( 'New category name', 'mhm-rentiva' ); ?>">
						<button type="button" id="add-category-btn" class="button"><?php esc_html_e( 'Add Category', 'mhm-rentiva' ); ?></button>
					</div>
				</div>

				<!-- Statuses Tab -->
				<div id="statuses" class="tab-content <?php echo 'statuses' === $active_tab ? 'active' : ''; ?>">
					<div id="status-list">
						<?php foreach ( $settings['statuses'] as $key => $name ) : ?>
							<div class="mhm-status-item">
								<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[statuses][<?php echo esc_attr( (string) $key ); ?>]"
									value="<?php echo esc_attr( (string) $name ); ?>" class="status-name" required>
								<button type="button" class="remove-status-btn"><?php esc_html_e( 'Delete', 'mhm-rentiva' ); ?></button>
							</div>
						<?php endforeach; ?>
					</div>

					<div class="mhm-add-item">
						<input type="text" id="new-status-name" placeholder="<?php esc_attr_e( 'New status name', 'mhm-rentiva' ); ?>">
						<button type="button" id="add-status-btn" class="button"><?php esc_html_e( 'Add Status', 'mhm-rentiva' ); ?></button>
					</div>
				</div>

				<?php submit_button( esc_html__( 'Save Settings', 'mhm-rentiva' ) ); ?>
			</form>
		</div>
		<?php
	}
}
