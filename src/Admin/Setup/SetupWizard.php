<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Setup;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Core\CurrencyHelper;
use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Licensing\LicenseManager;
use MHMRentiva\Admin\Settings\Core\SettingsCore;



final class SetupWizard
{
	use \MHMRentiva\Admin\Core\Traits\AdminHelperTrait;



	private const PAGE_SLUG        = 'mhm-rentiva-setup';
	private const OPTION_COMPLETED = 'mhm_rentiva_setup_completed';
	private const OPTION_REDIRECT  = 'mhm_rentiva_setup_redirect';

	public static function register(): void
	{
		add_action('admin_init', array(self::class, 'maybe_redirect'));
		add_action('admin_notices', array(self::class, 'show_permalink_notice'));

		add_action('admin_post_mhm_rentiva_setup_save_license', array(self::class, 'handle_save_license'));
		add_action('admin_post_mhm_rentiva_setup_create_pages', array(self::class, 'handle_create_pages'));
		add_action('admin_post_mhm_rentiva_setup_save_email', array(self::class, 'handle_save_email'));
		add_action('admin_post_mhm_rentiva_setup_save_frontend', array(self::class, 'handle_save_frontend'));
		add_action('admin_post_mhm_rentiva_setup_finish', array(self::class, 'handle_finish'));
		add_action('admin_post_mhm_rentiva_setup_skip', array(self::class, 'handle_skip'));
		add_action('admin_post_mhm_rentiva_dismiss_permalink_notice', array(self::class, 'handle_dismiss_permalink_notice'));
	}

	public static function register_menu(): void
	{
		add_submenu_page(
			'mhm-rentiva',
			__('Setup Wizard', 'mhm-rentiva'),
			__('Setup Wizard', 'mhm-rentiva'),
			'manage_options',
			self::PAGE_SLUG,
			array(self::class, 'render_page')
		);
	}

	public function render_page(): void
	{
		if (! current_user_can('manage_options')) {
			return;
		}

		self::print_styles();
		$current_step = self::get_current_step();
		$steps        = self::get_steps();

		$license_manager = class_exists(LicenseManager::class) ? LicenseManager::instance() : null;
		$license_data    = $license_manager ? $license_manager->get() : array();
		$license_context = array(
			'key'               => $license_manager ? $license_manager->getKey() : get_option('mhm_rentiva_license_key', ''),
			'expires_at'        => isset($license_data['expires_at']) ? (int) $license_data['expires_at'] : null,
			'plan'              => $license_data['plan'] ?? '',
			'status'            => $license_data['status'] ?? 'inactive',
			'activation_id'     => $license_data['activation_id'] ?? '',
			'is_active'         => $license_manager ? $license_manager->isActive() : ! empty(get_option('mhm_rentiva_license_key', '')),
			'is_dev_env'        => $license_manager ? $license_manager->isDevelopmentEnvironment() : false,
			'dev_mode_disabled' => (bool) get_option('mhm_rentiva_disable_dev_mode', false),
		);

		$buttons = array(
			array(
				'text'   => esc_html__('Documentation', 'mhm-rentiva'),
				'url'    => \MHMRentiva\Admin\Core\Utilities\UXHelper::get_docs_url(),
				'class'  => 'button button-secondary',
				'icon'   => 'dashicons-book-alt',
				'target' => '_blank',
			),
		);

		echo '<div class="wrap mhm-setup-wrapper">';
		$this->render_admin_header((string) get_admin_page_title(), $buttons);

		echo '<p>' . esc_html__('Follow the steps below to prepare Rentiva on a fresh WordPress installation. You can re-open this wizard later from the MHM Rentiva menu.', 'mhm-rentiva') . '</p>';

		self::render_step_navigation($steps, $current_step);
?>
		<div class="mhm-setup-step">
			<?php
			switch ($current_step) {
				case 'system':
					self::render_step_system();
					break;
				case 'license':
					self::render_step_license($license_context);
					break;
				case 'pages':
					self::render_step_pages();
					break;
				case 'email':
					self::render_step_email();
					break;
				case 'frontend':
					self::render_step_frontend();
					break;
				case 'demo':
					self::render_step_demo();
					break;
				case 'summary':
				default:
					self::render_step_summary();
					break;
			}
			?>
		</div>
		</div>
	<?php
	}

	private static function render_step_navigation(array $steps, string $current_step): void
	{
		echo '<ol class="mhm-setup-steps">';
		foreach ($steps as $key => $label) {
			$classes = array();
			if ($key === $current_step) {
				$classes[] = 'current';
			}
			if (self::is_step_completed($key)) {
				$classes[] = 'completed';
			}
			$class_attr = implode(' ', $classes);
			printf(
				'<li class="%1$s"><a href="%2$s">%3$s</a></li>',
				esc_attr($class_attr),
				esc_url(self::step_url($key)),
				esc_html($label)
			);
		}
		echo '</ol>';
	}

	private static function render_step_system(): void
	{
		$checks = self::get_system_checks();
	?>
		<h2><?php esc_html_e('Step 1: System Requirements', 'mhm-rentiva'); ?></h2>
		<p><?php esc_html_e('We scanned your WordPress environment to ensure Rentiva can run reliably. Resolve any item marked as required before continuing.', 'mhm-rentiva'); ?></p>
		<table class="widefat striped mhm-system-table">
			<thead>
				<tr>
					<th><?php esc_html_e('Requirement', 'mhm-rentiva'); ?></th>
					<th><?php esc_html_e('Current Value', 'mhm-rentiva'); ?></th>
					<th><?php esc_html_e('Recommended', 'mhm-rentiva'); ?></th>
					<th><?php esc_html_e('Status', 'mhm-rentiva'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($checks as $check) : ?>
					<tr>
						<td><?php echo esc_html($check['label']); ?></td>
						<td><?php echo esc_html($check['current']); ?></td>
						<td><?php echo esc_html($check['expected']); ?></td>
						<td>
							<?php
							echo self::format_status_badge($check['status']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
							?>
							<?php if (! empty($check['message'])) : ?>
								<div class="mhm-system-note"><?php echo esc_html($check['message']); ?></div>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<div class="mhm-step-actions">
			<a class="button button-large align-left" href="<?php echo esc_url(self::skip_url()); ?>"><?php esc_html_e('Skip wizard', 'mhm-rentiva'); ?></a>
			<a class="button button-primary button-large" href="<?php echo esc_url(self::step_url('license')); ?>"><?php esc_html_e('Continue to License', 'mhm-rentiva'); ?></a>
		</div>
	<?php
	}

	private static function render_step_license(array $license): void
	{
		$license_key  = (string) ($license['key'] ?? '');
		$is_active    = (bool) ($license['is_active'] ?? false);
		$expires_at   = $license['expires_at'] ?? null;
		$plan         = $license['plan'] ?: __('Unknown', 'mhm-rentiva');
		$status       = $license['status'] ?? 'inactive';
		$license_page = admin_url('admin.php?page=mhm-rentiva-license');
		$dev_env      = (bool) ($license['is_dev_env'] ?? false);
		$dev_disabled = (bool) ($license['dev_mode_disabled'] ?? false);
		$dev_allowed  = $dev_env && ! $dev_disabled;

	?>
		<h2><?php esc_html_e('Step 2: License Activation', 'mhm-rentiva'); ?></h2>
		<p><?php esc_html_e('Activate your license to unlock Pro features (online payments, unlimited vehicles, advanced export and more).', 'mhm-rentiva'); ?></p>

		<?php
		// ⭐ Show error messages if any
		$error_code = self::get_text('error');
		if ($error_code !== '') {
			$error_message = rawurldecode(self::get_text('message'));

			$error_text = '';
			switch ($error_code) {
				case 'empty_key':
					$error_text = __('Please enter a license key.', 'mhm-rentiva');
					break;
				case 'license_activation_failed':
					$error_text = $error_message ?: __('License activation failed. Please check your key and try again.', 'mhm-rentiva');
					break;
				case 'invalid_key':
					$error_text = __('Invalid license key. Please check your key and try again.', 'mhm-rentiva');
					break;
				case 'already_activated':
					$error_text = __('This license is already activated on another site. Maximum 1 activation allowed.', 'mhm-rentiva');
					break;
				default:
					$error_text = $error_message ?: __('License activation failed. Please try again.', 'mhm-rentiva');
			}

			echo '<div class="notice notice-error inline"><p>' . esc_html($error_text) . '</p></div>';
		}

		// ⭐ Show success message if license was activated
		if (self::get_text('license') === 'activated') {
			echo '<div class="notice notice-success inline"><p>' . esc_html__('License activated successfully!', 'mhm-rentiva') . '</p></div>';
		}
		?>

		<?php if ($is_active) : ?>
			<div class="mhm-license-card mhm-license-card--active">
				<div class="mhm-license-card__status"><?php esc_html_e('✅ Pro license active on this site', 'mhm-rentiva'); ?></div>
				<div class="mhm-license-grid">
					<div>
						<div class="mhm-license-label"><?php esc_html_e('License Key', 'mhm-rentiva'); ?></div>
						<code class="mhm-license-code"><?php echo esc_html($license_key); ?></code>
					</div>
					<div>
						<div class="mhm-license-label"><?php esc_html_e('Plan', 'mhm-rentiva'); ?></div>
						<span><?php echo esc_html(ucfirst($plan)); ?></span>
					</div>
					<div>
						<div class="mhm-license-label"><?php esc_html_e('Expires At', 'mhm-rentiva'); ?></div>
						<span><?php echo esc_html(self::format_license_expiration($expires_at)); ?></span>
					</div>
				</div>
				<p class="description">
					<?php esc_html_e('Need to deactivate or change the key? Use the License page from the main menu.', 'mhm-rentiva'); ?>
				</p>
			</div>
			<div class="mhm-step-actions">
				<a class="button button-secondary button-large align-left" href="<?php echo esc_url(self::step_url('system')); ?>">&larr; <?php esc_html_e('Back', 'mhm-rentiva'); ?></a>
				<a class="button button-secondary" href="<?php echo esc_url($license_page); ?>" target="_blank"><?php esc_html_e('Open License Page', 'mhm-rentiva'); ?></a>
				<a class="button button-primary button-large" href="<?php echo esc_url(self::step_url('pages')); ?>"><?php esc_html_e('Continue to Required Pages', 'mhm-rentiva'); ?></a>
			</div>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
				<?php wp_nonce_field('mhm_rentiva_setup_license'); ?>
				<input type="hidden" name="action" value="mhm_rentiva_setup_save_license">
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e('License Key', 'mhm-rentiva'); ?></th>
						<td>
							<input type="text" name="license_key" class="regular-text" value="<?php echo esc_attr($license_key); ?>" placeholder="XXXX-XXXX-XXXX-XXXX" />
							<p class="description"><?php esc_html_e('Paste the key from your purchase receipt or customer dashboard.', 'mhm-rentiva'); ?></p>
						</td>
					</tr>
				</table>
				<div class="mhm-step-actions">
					<a class="button button-secondary button-large align-left" href="<?php echo esc_url(self::step_url('system')); ?>">&larr; <?php esc_html_e('Back', 'mhm-rentiva'); ?></a>
					<a class="button button-link" href="<?php echo esc_url(self::step_url('pages')); ?>"><?php esc_html_e('Skip for now', 'mhm-rentiva'); ?></a>
					<button type="submit" class="button button-primary button-large"><?php esc_html_e('Activate & Continue', 'mhm-rentiva'); ?></button>
				</div>
			</form>
		<?php endif; ?>

		<?php if ($dev_allowed) : ?>
			<div class="notice notice-info inline">
				<p>
					<?php esc_html_e('Developer mode detected: this local/staging domain can test the plugin without a license. Activate a license before going live to keep Pro features enabled.', 'mhm-rentiva'); ?>
				</p>
			</div>
		<?php elseif ($dev_env && $dev_disabled) : ?>
			<div class="notice notice-warning inline">
				<p>
					<?php esc_html_e('Developer mode has been disabled for this installation. Activate a valid license key to continue using Pro features.', 'mhm-rentiva'); ?>
				</p>
			</div>
		<?php endif; ?>

		<?php if (! $is_active) : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: License page URL */
					esc_html__('Already have an active license? Review it on the %s.', 'mhm-rentiva'),
					'<a href="' . esc_url($license_page) . '" target="_blank">' . esc_html__('License page', 'mhm-rentiva') . '</a>'
				);
				?>
			</p>
		<?php endif; ?>
	<?php
	}

	private static function render_step_pages(): void
	{
		$required_pages = self::get_required_pages();
	?>
		<h2><?php esc_html_e('Step 3: Required Pages', 'mhm-rentiva'); ?></h2>
		<p><?php esc_html_e('Rentiva uses dedicated WordPress pages for booking, confirmation and customer account screens. Create missing pages automatically or link existing ones.', 'mhm-rentiva'); ?></p>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e('Page', 'mhm-rentiva'); ?></th>
					<th><?php esc_html_e('Shortcode', 'mhm-rentiva'); ?></th>
					<th><?php esc_html_e('Status', 'mhm-rentiva'); ?></th>
					<th><?php esc_html_e('Recommended URL', 'mhm-rentiva'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($required_pages as $page) : ?>
					<?php
					$page_id = self::locate_shortcode_page($page['shortcode']);
					$status  = $page_id ? __('Present', 'mhm-rentiva') : __('Missing', 'mhm-rentiva');
					?>
					<tr>
						<td><?php echo esc_html($page['label']); ?></td>
						<td><code><?php echo esc_html($page['shortcode']); ?></code></td>
						<td>
							<?php
							if ($page_id) {
								printf(
									'<span class="status-present">%1$s</span> <a href="%2$s" target="_blank">%3$s</a>',
									esc_html($status),
									esc_url(get_edit_post_link($page_id)),
									esc_html__('Edit', 'mhm-rentiva')
								);
							} else {
								echo '<span class="status-missing">' . esc_html($status) . '</span>';
							}
							?>
						</td>
						<td><code><?php echo esc_html($page['recommended_url']); ?></code></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:20px;">
			<?php wp_nonce_field('mhm_rentiva_setup_pages'); ?>
			<input type="hidden" name="action" value="mhm_rentiva_setup_create_pages">
			<?php submit_button(__('Create Missing Pages', 'mhm-rentiva')); ?>
			<a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mhm-rentiva-shortcode-pages')); ?>" target="_blank">
				<?php esc_html_e('Open Shortcode Pages', 'mhm-rentiva'); ?>
			</a>
		</form>
		<div class="mhm-step-actions">
			<a class="button button-secondary button-large align-left" href="<?php echo esc_url(self::step_url('license')); ?>">&larr; <?php esc_html_e('Back', 'mhm-rentiva'); ?></a>
			<a class="button button-primary button-large" href="<?php echo esc_url(self::step_url('email')); ?>"><?php esc_html_e('Continue to Email', 'mhm-rentiva'); ?></a>
		</div>
	<?php
	}

	private static function render_step_email(): void
	{
		$sender_name   = SettingsCore::get('mhm_rentiva_email_from_name', get_bloginfo('name'));
		$sender_email  = SettingsCore::get('mhm_rentiva_email_from_address', get_option('admin_email'));
		$reply_address = SettingsCore::get('mhm_rentiva_email_reply_to', get_option('admin_email'));
		$test_mode     = SettingsCore::get('mhm_rentiva_email_test_mode', '0');
		$test_address  = SettingsCore::get('mhm_rentiva_email_test_address', get_option('admin_email'));
		$send_enabled  = SettingsCore::get('mhm_rentiva_email_send_enabled', '1');
		$auto_enabled  = SettingsCore::get('mhm_rentiva_email_auto_send', '1');
		$log_enabled   = SettingsCore::get('mhm_rentiva_email_log_enabled', '1');
	?>
		<h2><?php esc_html_e('Step 4: Email & Notifications', 'mhm-rentiva'); ?></h2>
		<p><?php esc_html_e('Configure the sender information and enable automatic notifications for bookings.', 'mhm-rentiva'); ?></p>

		<div class="mhm-wizard-notice notice notice-info inline" style="padding: 15px; background-color: #f0f6fc; border-left: 4px solid #72aee6; margin-bottom: 20px;">
			<h3 style="margin: 0 0 10px 0; font-size: 1.1em; font-weight: 600;"><?php esc_html_e('📧 Important: Email Delivery Security', 'mhm-rentiva'); ?></h3>
			<p style="margin-bottom: 10px;">
				<?php esc_html_e('The default WordPress email system can be unreliable depending on server configuration, causing your emails to fall into Spam/Junk folders.', 'mhm-rentiva'); ?>
			</p>
			<p>
				<?php esc_html_e('For uninterrupted communication and delivery of booking notifications, please install and configure an SMTP Plugin.', 'mhm-rentiva'); ?>
			</p>
			<p style="margin-top: 10px; font-style: italic;">
				<strong><?php esc_html_e('Recommended Plugins:', 'mhm-rentiva'); ?></strong>
				<a href="https://wordpress.org/plugins/wp-mail-smtp/" target="_blank">WP Mail SMTP</a> <?php esc_html_e('or', 'mhm-rentiva'); ?>
				<a href="https://wordpress.org/plugins/fluent-smtp/" target="_blank">Fluent SMTP</a>
			</p>
		</div>

		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<?php wp_nonce_field('mhm_rentiva_setup_email'); ?>
			<input type="hidden" name="action" value="mhm_rentiva_setup_save_email">
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e('Sender Name', 'mhm-rentiva'); ?></th>
					<td><input type="text" name="sender_name" class="regular-text" value="<?php echo esc_attr($sender_name); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Sender Email', 'mhm-rentiva'); ?></th>
					<td><input type="email" name="sender_email" class="regular-text" value="<?php echo esc_attr($sender_email); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Reply-To Address', 'mhm-rentiva'); ?></th>
					<td><input type="email" name="reply_address" class="regular-text" value="<?php echo esc_attr($reply_address); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Test Mode', 'mhm-rentiva'); ?></th>
					<td>
						<label>
							<input type="checkbox" name="test_mode" value="1" <?php checked($test_mode, '1'); ?> />
							<?php esc_html_e('Enable test mode (emails will be sent only to the test address)', 'mhm-rentiva'); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Test Email Address', 'mhm-rentiva'); ?></th>
					<td><input type="email" name="test_address" class="regular-text" value="<?php echo esc_attr($test_address); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Automation', 'mhm-rentiva'); ?></th>
					<td>
						<label><input type="checkbox" name="send_enabled" value="1" <?php checked($send_enabled, '1'); ?> /> <?php esc_html_e('Email sending enabled', 'mhm-rentiva'); ?></label><br>
						<label><input type="checkbox" name="auto_enabled" value="1" <?php checked($auto_enabled, '1'); ?> /> <?php esc_html_e('Automatic email sending enabled', 'mhm-rentiva'); ?></label><br>
						<label><input type="checkbox" name="log_enabled" value="1" <?php checked($log_enabled, '1'); ?> /> <?php esc_html_e('Log emails in the email log post type', 'mhm-rentiva'); ?></label>
					</td>
				</tr>
			</table>
			<div class="mhm-step-actions">
				<a class="button button-secondary button-large align-left" href="<?php echo esc_url(self::step_url('pages')); ?>">&larr; <?php esc_html_e('Back', 'mhm-rentiva'); ?></a>
				<button type="submit" class="button button-primary button-large"><?php esc_html_e('Save & Continue', 'mhm-rentiva'); ?></button>
			</div>
		</form>
	<?php
	}

	private static function render_step_frontend(): void
	{
		// Check if WooCommerce is active and use its currency
		if (class_exists('WooCommerce')) {
			$currency                = get_woocommerce_currency();
			$is_woocommerce_currency = true;
		} else {
			$currency                = SettingsCore::get('mhm_rentiva_currency', 'USD');
			$is_woocommerce_currency = false;
		}

		$currency_position = SettingsCore::get('mhm_rentiva_currency_position', 'right_space');
		$currencies        = CurrencyHelper::get_currency_list_for_dropdown();

		// Get currency symbol for position examples
		$currency_symbol = CurrencyHelper::get_currency_symbol($currency);

		$positions         = array(
			/* translators: %s: currency symbol */
			'left'        => sprintf(__('Left (%s100)', 'mhm-rentiva'), $currency_symbol),
			/* translators: %s: currency symbol */
			'left_space'  => sprintf(__('Left Space (%s 100)', 'mhm-rentiva'), $currency_symbol),
			/* translators: %s: currency symbol */
			'right'       => sprintf(__('Right (100%s)', 'mhm-rentiva'), $currency_symbol),
			/* translators: %s: currency symbol */
			'right_space' => sprintf(__('Right Space (100 %s)', 'mhm-rentiva'), $currency_symbol),
		);
		$default_days      = (int) SettingsCore::get('mhm_rentiva_default_rental_days', 1);
		$min_days          = (int) SettingsCore::get('mhm_rentiva_vehicle_min_rental_days', 1);
		$max_days          = (int) SettingsCore::get('mhm_rentiva_vehicle_max_rental_days', 30);
		$show_features     = SettingsCore::get('mhm_rentiva_vehicle_show_features', '1');
		$show_availability = SettingsCore::get('mhm_rentiva_vehicle_show_availability', '1');
	?>
		<h2><?php esc_html_e('Step 5: Frontend & Display', 'mhm-rentiva'); ?></h2>
		<p><?php esc_html_e('Fine tune the visible defaults that appear on booking forms and vehicle cards.', 'mhm-rentiva'); ?></p>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<?php wp_nonce_field('mhm_rentiva_setup_frontend'); ?>
			<input type="hidden" name="action" value="mhm_rentiva_setup_save_frontend">
			<table class="form-table">
				<tr>
					<th><?php esc_html_e('Currency', 'mhm-rentiva'); ?></th>
					<td>
						<?php if ($is_woocommerce_currency) : ?>
							<p class="description">
								<strong><?php esc_html_e('Managed by WooCommerce:', 'mhm-rentiva'); ?></strong>
								<?php echo esc_html($currencies[$currency] ?? $currency); ?>
							</p>
							<p class="description">
								<?php
								printf(
									/* translators: %s: link to WooCommerce settings */
									esc_html__('To change the currency, please visit %s.', 'mhm-rentiva'),
									'<a href="' . esc_url(admin_url('admin.php?page=wc-settings')) . '" target="_blank">' . esc_html__('WooCommerce Settings', 'mhm-rentiva') . '</a>'
								);
								?>
							</p>
						<?php else : ?>
							<select name="currency">
								<?php foreach ($currencies as $code => $label) : ?>
									<option value="<?php echo esc_attr($code); ?>" <?php selected($currency, $code); ?>>
										<?php echo esc_html($label); ?>
									</option>
								<?php endforeach; ?>
							</select>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e('Currency Position', 'mhm-rentiva'); ?></th>
					<td>
						<?php if ($is_woocommerce_currency) : ?>
							<?php
							$wc_pos    = get_option('woocommerce_currency_pos');
							$pos_label = $positions[$wc_pos] ?? $wc_pos;
							?>
							<p class="description">
								<strong><?php esc_html_e('Managed by WooCommerce:', 'mhm-rentiva'); ?></strong>
								<?php echo esc_html($pos_label); ?>
							</p>
							<p class="description">
								<?php
								printf(
									/* translators: %s: link to WooCommerce settings */
									esc_html__('To change the position, please visit %s.', 'mhm-rentiva'),
									'<a href="' . esc_url(admin_url('admin.php?page=wc-settings')) . '" target="_blank">' . esc_html__('WooCommerce Settings', 'mhm-rentiva') . '</a>'
								);
								?>
							</p>
						<?php else : ?>
							<select name="currency_position">
								<?php foreach ($positions as $pos => $label) : ?>
									<option value="<?php echo esc_attr($pos); ?>" <?php selected($currency_position, $pos); ?>>
										<?php echo esc_html($label); ?>
									</option>
								<?php endforeach; ?>
							</select>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e('Default Rental Days', 'mhm-rentiva'); ?></th>
					<td><input type="number" name="default_days" min="1" max="30" value="<?php echo esc_attr($default_days); ?>" /></td>
				</tr>
				<tr>
					<th><?php esc_html_e('Minimum Rental Days', 'mhm-rentiva'); ?></th>
					<td><input type="number" name="min_days" min="1" max="365" value="<?php echo esc_attr($min_days); ?>" /></td>
				</tr>
				<tr>
					<th><?php esc_html_e('Maximum Rental Days', 'mhm-rentiva'); ?></th>
					<td><input type="number" name="max_days" min="<?php echo esc_attr(max(1, $min_days)); ?>" max="365" value="<?php echo esc_attr($max_days); ?>" /></td>
				</tr>
				<tr>
					<th><?php esc_html_e('Vehicle Cards', 'mhm-rentiva'); ?></th>
					<td>
						<label><input type="checkbox" name="show_features" value="1" <?php checked($show_features, '1'); ?> /> <?php esc_html_e('Show feature badges', 'mhm-rentiva'); ?></label><br>
						<label><input type="checkbox" name="show_availability" value="1" <?php checked($show_availability, '1'); ?> /> <?php esc_html_e('Show availability badge', 'mhm-rentiva'); ?></label>
					</td>
				</tr>
			</table>
			<div class="mhm-step-actions">
				<a class="button button-secondary button-large align-left" href="<?php echo esc_url(self::step_url('email')); ?>">&larr; <?php esc_html_e('Back', 'mhm-rentiva'); ?></a>
				<button type="submit" class="button button-primary button-large"><?php esc_html_e('Save & Continue', 'mhm-rentiva'); ?></button>
			</div>
		</form>
	<?php
	}

	private static function render_step_demo(): void
	{
		$is_active  = \MHMRentiva\Admin\Testing\DemoNoticeManager::is_demo_active();
		$seed_steps    = \MHMRentiva\Admin\Testing\DemoAjaxHandler::get_seed_steps();
		$cleanup_steps = \MHMRentiva\Admin\Testing\DemoAjaxHandler::get_cleanup_steps();
		$nonce         = \MHMRentiva\Admin\Testing\DemoAjaxHandler::get_nonce();
	?>
		<h2><?php esc_html_e( 'Demo Data', 'mhm-rentiva' ); ?></h2>
		<p><?php esc_html_e( 'Load sample vehicles, customers, bookings, add-ons, transfer points and messages so you can explore every feature without entering real data.', 'mhm-rentiva' ); ?></p>

		<?php if ( $is_active ) : ?>
			<div class="notice notice-warning inline" style="margin: 0 0 16px;">
				<p><strong><?php esc_html_e( '⚠️ Demo data is currently active.', 'mhm-rentiva' ); ?></strong>
				<?php esc_html_e( 'Use "Clean Up" to remove all demo data before going live.', 'mhm-rentiva' ); ?></p>
			</div>
		<?php endif; ?>

		<div id="mhm-demo-seed-wrap" style="max-width:640px;">
			<div id="mhm-demo-progress-bar" style="display:none; margin-bottom:16px;">
				<div style="background:#e0e0e0; border-radius:4px; height:12px; overflow:hidden;">
					<div id="mhm-demo-progress-fill" style="background:#2271b1; height:100%; width:0; transition:width .3s;"></div>
				</div>
				<p id="mhm-demo-progress-label" style="margin:6px 0 0; font-size:13px; color:#555;"></p>
			</div>
			<div id="mhm-demo-result" style="display:none; margin-bottom:16px;" class="notice notice-success inline">
				<p id="mhm-demo-result-msg"></p>
			</div>
			<div id="mhm-demo-error" style="display:none; margin-bottom:16px;" class="notice notice-error inline">
				<p id="mhm-demo-error-msg"></p>
			</div>

			<p style="display:flex; gap:10px; flex-wrap:wrap;">
				<button id="mhm-btn-seed" class="button button-primary button-large">
					<?php esc_html_e( 'Load Demo Data', 'mhm-rentiva' ); ?>
				</button>
				<?php if ( $is_active ) : ?>
				<button id="mhm-btn-cleanup" class="button button-secondary button-large" style="color:#b32d2e; border-color:#b32d2e;">
					<?php esc_html_e( 'Clean Up Demo Data', 'mhm-rentiva' ); ?>
				</button>
				<?php endif; ?>
			</p>
		</div>

		<script>
		(function() {
			var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var nonce   = <?php echo wp_json_encode( $nonce ); ?>;
			var seedSteps    = <?php echo wp_json_encode( array_keys( $seed_steps ) ); ?>;
			var cleanupSteps = <?php echo wp_json_encode( array_keys( $cleanup_steps ) ); ?>;

			function setProgress(pct, label) {
				document.getElementById('mhm-demo-progress-bar').style.display = 'block';
				document.getElementById('mhm-demo-progress-fill').style.width  = pct + '%';
				document.getElementById('mhm-demo-progress-label').textContent = label;
			}
			function showResult(msg) {
				var el = document.getElementById('mhm-demo-result');
				el.style.display = 'block';
				document.getElementById('mhm-demo-result-msg').textContent = msg;
			}
			function showError(msg) {
				var el = document.getElementById('mhm-demo-error');
				el.style.display = 'block';
				document.getElementById('mhm-demo-error-msg').textContent = msg;
			}
			function clearFeedback() {
				document.getElementById('mhm-demo-progress-bar').style.display  = 'none';
				document.getElementById('mhm-demo-result').style.display         = 'none';
				document.getElementById('mhm-demo-error').style.display          = 'none';
				document.getElementById('mhm-demo-progress-fill').style.width    = '0';
			}

			function runSteps(steps, action, onDone) {
				var i = 0;
				function next() {
					if (i >= steps.length) { onDone(); return; }
					var step = steps[i++];
					var fd   = new FormData();
					fd.append('action', action);
					fd.append('nonce',  nonce);
					fd.append('step',   step);
					fetch(ajaxUrl, { method: 'POST', body: fd })
						.then(function(r) { return r.json(); })
						.then(function(data) {
							if (data.success) {
								setProgress(data.data.progress, data.data.message);
								next();
							} else {
								showError(data.data && data.data.message ? data.data.message : 'Error during step: ' + step);
							}
						})
						.catch(function() { showError('Network error during step: ' + step); });
				}
				next();
			}

			var btnSeed = document.getElementById('mhm-btn-seed');
			if (btnSeed) {
				btnSeed.addEventListener('click', function() {
					clearFeedback();
					btnSeed.disabled = true;
					runSteps(seedSteps, 'mhm_rentiva_demo_seed', function() {
						showResult(<?php echo wp_json_encode( __( 'Demo data loaded successfully! Refresh the page to see the cleanup button.', 'mhm-rentiva' ) ); ?>);
						btnSeed.disabled = false;
					});
				});
			}

			var btnCleanup = document.getElementById('mhm-btn-cleanup');
			if (btnCleanup) {
				btnCleanup.addEventListener('click', function() {
					clearFeedback();
					btnCleanup.disabled = true;
					runSteps(cleanupSteps, 'mhm_rentiva_demo_cleanup', function() {
						showResult(<?php echo wp_json_encode( __( 'Demo data removed. Refresh the page to continue.', 'mhm-rentiva' ) ); ?>);
						btnCleanup.disabled = false;
					});
				});
			}
		})();
		</script>

		<div class="mhm-step-actions" style="margin-top:24px;">
			<a class="button button-secondary button-large align-left" href="<?php echo esc_url( self::step_url( 'frontend' ) ); ?>">&larr; <?php esc_html_e( 'Back', 'mhm-rentiva' ); ?></a>
			<a class="button button-primary button-large" href="<?php echo esc_url( self::step_url( 'summary' ) ); ?>"><?php esc_html_e( 'Continue to Summary', 'mhm-rentiva' ); ?></a>
		</div>
	<?php
	}

	private static function render_step_summary(): void
	{
		$steps     = self::get_steps();
		$completed = get_option(self::OPTION_COMPLETED, '0') === '1';
	?>
		<h2><?php esc_html_e('Final Step: Summary & Tests', 'mhm-rentiva'); ?></h2>
		<p><?php esc_html_e('Review the checklist below and run a quick booking to confirm everything is ready.', 'mhm-rentiva'); ?></p>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e('Step', 'mhm-rentiva'); ?></th>
					<th><?php esc_html_e('Status', 'mhm-rentiva'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($steps as $slug => $label) : ?>
					<tr>
						<td><?php echo esc_html($label); ?></td>
						<td>
							<?php
							echo self::format_status_badge(self::is_step_completed($slug) ? 'ok' : 'warning'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<div class="mhm-summary-actions">
			<a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mhm-rentiva-settings')); ?>"><?php esc_html_e('Open Settings', 'mhm-rentiva'); ?></a>
			<a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mhm-rentiva-shortcode-pages')); ?>"><?php esc_html_e('Review Shortcode Pages', 'mhm-rentiva'); ?></a>
			<a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mhm-rentiva-settings&tab=email-templates')); ?>"><?php esc_html_e('Send Test Email', 'mhm-rentiva'); ?></a>
		</div>

		<?php
		// Check if permalink structure is set to plain (not SEO-friendly)
		$permalink_structure = get_option('permalink_structure');
		$is_plain_permalink  = empty($permalink_structure);

		if ($is_plain_permalink || $permalink_structure === '') :
		?>
			<div class="notice notice-warning inline" style="margin: 20px 0;">
				<p>
					<strong><?php esc_html_e('⚠️ Important: Permalink Settings', 'mhm-rentiva'); ?></strong><br>
					<?php esc_html_e('Your WordPress permalink structure is set to "Plain". For the frontend pages to work correctly, please update your permalink settings to a SEO-friendly structure (e.g., "Post name").', 'mhm-rentiva'); ?>
				</p>
				<p>
					<a class="button button-primary" href="<?php echo esc_url(admin_url('options-permalink.php')); ?>" target="_blank">
						<?php esc_html_e('Open Permalink Settings', 'mhm-rentiva'); ?>
					</a>
					<span class="description" style="margin-left: 10px;">
						<?php esc_html_e('After updating, click "Save Changes" to refresh permalinks.', 'mhm-rentiva'); ?>
					</span>
				</p>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<?php wp_nonce_field('mhm_rentiva_setup_finish'); ?>
			<input type="hidden" name="action" value="mhm_rentiva_setup_finish">
			<?php
			if ($completed) {
				echo '<p>' . esc_html__('Setup wizard was completed previously. You can still finish again to return to the dashboard.', 'mhm-rentiva') . '</p>';
			}
			?>
			<div class="mhm-step-actions">
				<a class="button button-secondary button-large align-left" href="<?php echo esc_url(self::step_url('demo')); ?>">&larr; <?php esc_html_e('Back', 'mhm-rentiva'); ?></a>
				<a class="button button-secondary button-large" href="<?php echo esc_url(admin_url('admin.php?page=mhm-rentiva-dashboard')); ?>"><?php esc_html_e('Go to Dashboard', 'mhm-rentiva'); ?></a>
				<button type="submit" class="button button-primary button-large"><?php esc_html_e('Complete Setup', 'mhm-rentiva'); ?></button>
			</div>
		</form>
	<?php
	}

	public static function handle_save_license(): void
	{
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You are not allowed to perform this action.', 'mhm-rentiva'));
		}
		check_admin_referer('mhm_rentiva_setup_license');
		$key = isset($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : '';

		if (empty($key)) {
			wp_safe_redirect(self::step_url('license', array('error' => 'empty_key')));
			exit;
		}

		// ⭐ Use LicenseManager to activate license (same as License Admin page)
		$license_manager = class_exists(LicenseManager::class) ? LicenseManager::instance() : null;
		if ($license_manager) {
			$result = $license_manager->activate($key);

			if (is_wp_error($result)) {
				$error_code = $result->get_error_code();
				wp_safe_redirect(
					self::step_url(
						'license',
						array(
							'error'   => $error_code,
							'message' => urlencode($result->get_error_message()),
						)
					)
				);
				exit;
			}

			// Success - redirect to next step
			wp_safe_redirect(
				self::step_url(
					'pages',
					array(
						'updated' => '1',
						'license' => 'activated',
					)
				)
			);
			exit;
		} else {
			// Fallback: just save the key if LicenseManager is not available
			update_option('mhm_rentiva_license_key', $key);
			wp_safe_redirect(self::step_url('pages', array('updated' => '1')));
			exit;
		}
	}

	public static function handle_create_pages(): void
	{
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You are not allowed to perform this action.', 'mhm-rentiva'));
		}
		check_admin_referer('mhm_rentiva_setup_pages');

		$created = 0;
		foreach (self::get_required_pages() as $page) {
			if (! self::locate_shortcode_page($page['shortcode'])) {
				$new_id = self::create_page($page);
				if ($new_id) {
					++$created;
					ShortcodeUrlManager::clear_cache($page['shortcode']);
				}
			}
		}

		wp_safe_redirect(self::step_url('pages', array('created' => (string) $created)));
		exit;
	}

	public static function handle_save_email(): void
	{
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You are not allowed to perform this action.', 'mhm-rentiva'));
		}
		check_admin_referer('mhm_rentiva_setup_email');

		$settings                                   = get_option('mhm_rentiva_settings', array());
		$settings['mhm_rentiva_email_from_name']    = sanitize_text_field(wp_unslash($_POST['sender_name'] ?? ''));
		$settings['mhm_rentiva_email_from_address'] = sanitize_email(wp_unslash($_POST['sender_email'] ?? ''));
		$settings['mhm_rentiva_email_reply_to']     = sanitize_email(wp_unslash($_POST['reply_address'] ?? ''));
		$settings['mhm_rentiva_email_test_mode']    = isset($_POST['test_mode']) ? '1' : '0';
		$settings['mhm_rentiva_email_test_address'] = sanitize_email(wp_unslash($_POST['test_address'] ?? ''));
		$settings['mhm_rentiva_email_send_enabled'] = isset($_POST['send_enabled']) ? '1' : '0';
		$settings['mhm_rentiva_email_auto_send']    = isset($_POST['auto_enabled']) ? '1' : '0';
		$settings['mhm_rentiva_email_log_enabled']  = isset($_POST['log_enabled']) ? '1' : '0';
		update_option('mhm_rentiva_settings', $settings);

		wp_safe_redirect(self::step_url('frontend', array('saved' => '1')));
		exit;
	}

	public static function handle_save_frontend(): void
	{
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You are not allowed to perform this action.', 'mhm-rentiva'));
		}
		check_admin_referer('mhm_rentiva_setup_frontend');

		$settings                         = get_option('mhm_rentiva_settings', array());
		$settings['mhm_rentiva_currency'] = sanitize_text_field(wp_unslash($_POST['currency'] ?? 'USD'));

		if (class_exists('WooCommerce')) {
			$settings['mhm_rentiva_currency_position'] = get_option('woocommerce_currency_pos', 'right_space');
		} else {
			$currency_position                         = sanitize_text_field(wp_unslash($_POST['currency_position'] ?? 'right_space'));
			$allowed_positions                         = array('left', 'left_space', 'right', 'right_space');
			$settings['mhm_rentiva_currency_position'] = in_array($currency_position, $allowed_positions, true) ? $currency_position : 'right_space';
		}

		$default_days = self::post_int('default_days', 1);
		$min_days     = self::post_int('min_days', 1);
		$max_days     = self::post_int('max_days', 30);

		$settings['mhm_rentiva_default_rental_days']     = (string) max(1, min(30, $default_days));
		$settings['mhm_rentiva_vehicle_min_rental_days'] = (string) max(1, min(365, $min_days));
		$settings['mhm_rentiva_vehicle_max_rental_days'] = (string) max((int) $settings['mhm_rentiva_vehicle_min_rental_days'], min(365, $max_days));

		$settings['mhm_rentiva_vehicle_show_features']     = isset($_POST['show_features']) ? '1' : '0';
		$settings['mhm_rentiva_vehicle_show_availability'] = isset($_POST['show_availability']) ? '1' : '0';

		update_option('mhm_rentiva_settings', $settings);

		wp_safe_redirect(self::step_url('summary', array('saved' => '1')));
		exit;
	}

	public static function handle_finish(): void
	{
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You are not allowed to perform this action.', 'mhm-rentiva'));
		}
		check_admin_referer('mhm_rentiva_setup_finish');

		update_option(self::OPTION_COMPLETED, '1');
		delete_option(self::OPTION_REDIRECT);

		// Check permalink structure and set a transient notice if needed
		$permalink_structure = get_option('permalink_structure');
		if (empty($permalink_structure)) {
			set_transient('mhm_rentiva_permalink_notice', '1', HOUR_IN_SECONDS);
		}

		wp_safe_redirect(admin_url('admin.php?page=mhm-rentiva-dashboard'));
		exit;
	}

	public static function handle_skip(): void
	{
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You are not allowed to perform this action.', 'mhm-rentiva'));
		}
		check_admin_referer('mhm_rentiva_setup_skip');
		update_option(self::OPTION_COMPLETED, '1');
		delete_option(self::OPTION_REDIRECT);
		wp_safe_redirect(admin_url('admin.php?page=mhm-rentiva-dashboard'));
		exit;
	}

	public static function handle_dismiss_permalink_notice(): void
	{
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You are not allowed to perform this action.', 'mhm-rentiva'));
		}
		check_admin_referer('mhm_rentiva_dismiss_permalink_notice');
		delete_transient('mhm_rentiva_permalink_notice');
		wp_safe_redirect(admin_url('admin.php?page=mhm-rentiva-dashboard'));
		exit;
	}

	public static function maybe_redirect(): void
	{
		if (! is_admin() || ! current_user_can('manage_options')) {
			return;
		}

		if (defined('DOING_AJAX') && DOING_AJAX) {
			return;
		}

		$should_redirect = get_option(self::OPTION_REDIRECT, '0') === '1';
		$completed       = get_option(self::OPTION_COMPLETED, '0') === '1';

		if ($should_redirect && ! $completed) {
			delete_option(self::OPTION_REDIRECT);
			if (! self::is_wizard_page()) {
				wp_safe_redirect(self::step_url('system'));
				exit;
			}
		}
	}

	public static function show_permalink_notice(): void
	{
		if (! current_user_can('manage_options')) {
			return;
		}

		// Only show notice if transient is set (after setup completion)
		if (! get_transient('mhm_rentiva_permalink_notice')) {
			return;
		}

		// Check if permalink structure is still plain
		$permalink_structure = get_option('permalink_structure');
		if (! empty($permalink_structure)) {
			// Permalink is already set, delete transient
			delete_transient('mhm_rentiva_permalink_notice');
			return;
		}

		// Show notice only on MHM Rentiva pages
		$screen = get_current_screen();
		if (! $screen || strpos($screen->id, 'mhm-rentiva') === false) {
			return;
		}

		$permalink_url = admin_url('options-permalink.php');
	?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<strong><?php esc_html_e('⚠️ Important: Permalink Settings Required', 'mhm-rentiva'); ?></strong>
			</p>
			<p>
				<?php esc_html_e('Your WordPress permalink structure is set to "Plain". For the frontend pages (booking form, account pages, etc.) to work correctly, please update your permalink settings to a SEO-friendly structure.', 'mhm-rentiva'); ?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url($permalink_url); ?>">
					<?php esc_html_e('Open Permalink Settings', 'mhm-rentiva'); ?>
				</a>
				<a class="button button-link" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=mhm_rentiva_dismiss_permalink_notice'), 'mhm_rentiva_dismiss_permalink_notice')); ?>">
					<?php esc_html_e('Dismiss', 'mhm-rentiva'); ?>
				</a>
			</p>
		</div>
	<?php
	}

	private static function is_wizard_page(): bool
	{
		return self::get_text('page') === self::PAGE_SLUG;
	}

	private static function get_steps(): array
	{
		return array(
			'system'   => __('System Check', 'mhm-rentiva'),
			'license'  => __('License', 'mhm-rentiva'),
			'pages'    => __('Required Pages', 'mhm-rentiva'),
			'email'    => __('Email Settings', 'mhm-rentiva'),
			'frontend' => __('Frontend & Display', 'mhm-rentiva'),
			'demo'     => __('Demo Data', 'mhm-rentiva'),
			'summary'  => __('Summary & Tests', 'mhm-rentiva'),
		);
	}

	private static function get_current_step(): string
	{
		$steps     = array_keys(self::get_steps());
		$requested = self::get_key('step');
		if ($requested && in_array($requested, $steps, true)) {
			return $requested;
		}
		return $steps[0];
	}

	private static function get_text(string $key, string $default = ''): string
	{
		$raw = filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW, FILTER_NULL_ON_FAILURE);
		if ($raw === null || $raw === false) {
			return $default;
		}

		return sanitize_text_field((string) $raw);
	}

	private static function get_key(string $key, string $default = ''): string
	{
		$value = self::get_text($key, $default);
		return $value === '' ? $default : sanitize_key($value);
	}

	private static function post_text(string $key, string $default = ''): string
	{
		$raw = filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW, FILTER_NULL_ON_FAILURE);
		if ($raw === null || $raw === false) {
			return $default;
		}

		return sanitize_text_field((string) $raw);
	}

	private static function post_int(string $key, int $default = 0): int
	{
		$value = self::post_text($key, '');
		return $value === '' ? $default : (int) $value;
	}

	private static function step_url(string $step, array $args = array()): string
	{
		$url = add_query_arg(
			array_merge(
				array(
					'page' => self::PAGE_SLUG,
					'step' => $step,
				),
				$args
			),
			admin_url('admin.php')
		);
		return $url;
	}

	private static function skip_url(): string
	{
		return wp_nonce_url(
			admin_url('admin-post.php?action=mhm_rentiva_setup_skip'),
			'mhm_rentiva_setup_skip'
		);
	}

	private static function get_required_pages(): array
	{
		$pages = array(
			array(
				'label'           => __('Booking Form', 'mhm-rentiva'),
				'shortcode'       => 'rentiva_booking_form',
				'recommended_url' => '/rentiva/booking-form/',
			),
			array(
				'label'           => __('Unified Search', 'mhm-rentiva'),
				'shortcode'       => 'rentiva_unified_search',
				'recommended_url' => '/rentiva/search/',
			),
			array(
				'label'           => __('Search Results', 'mhm-rentiva'),
				'shortcode'       => 'rentiva_search_results',
				'recommended_url' => '/rentiva/search-results/',
			),
			array(
				'label'           => __('Vehicle Details', 'mhm-rentiva'),
				'shortcode'       => 'rentiva_vehicle_details',
				'recommended_url' => '/rentiva/vehicle/',
			),
			array(
				'label'           => __('Vehicles List', 'mhm-rentiva'),
				'shortcode'       => 'rentiva_vehicles_list',
				'recommended_url' => '/rentiva/vehicles/',
			),
			array(
				'label'           => __('Contact Form', 'mhm-rentiva'),
				'shortcode'       => 'rentiva_contact',
				'recommended_url' => '/rentiva/contact/',
			),
		);

		return $pages;
	}

	private static function locate_shortcode_page(string $shortcode)
	{
		if (! class_exists(ShortcodeUrlManager::class)) {
			return null;
		}

		return ShortcodeUrlManager::get_page_id($shortcode);
	}

	private static function create_page(array $page): ?int
	{
		$content  = '[' . $page['shortcode'] . ']';
		$existing = self::locate_shortcode_page($page['shortcode']);
		if ($existing) {
			return $existing;
		}

		$post_id = wp_insert_post(
			array(
				'post_title'   => $page['label'],
				'post_name'    => sanitize_title($page['label']),
				'post_content' => $content,
				'post_status'  => 'publish',
				'post_type'    => 'page',
			)
		);

		if (is_wp_error($post_id)) {
			return null;
		}

		return (int) $post_id;
	}

	private static function print_styles(): void
	{
		static $printed = false;
		if ($printed) {
			return;
		}
		$printed = true;
	?>
		<style>
			.mhm-setup-steps {
				display: flex;
				gap: 12px;
				flex-wrap: wrap;
				list-style: none;
				padding-left: 0;
				margin-bottom: 20px;
			}

			.mhm-setup-steps li {
				padding: 6px 14px;
				border-radius: 20px;
				background: #f6f7f7;
				border: 1px solid #dcdcdc;
				color: #1d2327;
				transition: all 0.2s;
			}

			.mhm-setup-steps li.current {
				background: #2271b1;
				color: #fff;
				border-color: #2271b1;
				box-shadow: none;
			}

			.mhm-setup-steps li.completed {
				border-color: #dcdcdc;
				box-shadow: none;
			}

			.mhm-setup-steps li a {
				color: inherit;
				text-decoration: none;
			}

			.mhm-system-table .mhm-system-note {
				font-size: 12px;
				margin-top: 4px;
				color: #646970;
			}

			.mhm-status {
				display: inline-flex;
				align-items: center;
				gap: 6px;
				padding: 2px 8px;
				border-radius: 999px;
				font-size: 12px;
			}

			.mhm-status-ok {
				background: #d5f2e3;
				color: #1d7a46;
			}

			.mhm-status-warning {
				background: #fff4ce;
				color: #7a5b00;
			}

			.mhm-status-fail {
				background: #fdeaea;
				color: #a12622;
			}

			.mhm-step-actions {
				margin-top: 32px;
				padding-top: 24px;
				border-top: 1px solid #f0f0f1;
				display: flex;
				justify-content: flex-end;
				gap: 12px;
				flex-wrap: wrap;
				align-items: center;
			}

			.mhm-step-actions .align-left {
				margin-right: auto;
			}

			.mhm-summary-actions {
				display: flex;
				gap: 10px;
				margin: 16px 0;
				flex-wrap: wrap;
			}

			.mhm-license-card {
				border: 1px solid #c3c4c7;
				border-radius: 8px;
				background: #fff;
				padding: 16px 20px;
				margin-bottom: 16px;
			}

			.mhm-license-card--active {
				border-color: #3ab27b;
				box-shadow: 0 0 0 1px rgba(58, 178, 123, 0.2);
			}

			.mhm-license-card__status {
				font-weight: 600;
				margin-bottom: 12px;
			}

			.mhm-license-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
				gap: 12px 24px;
				margin-bottom: 8px;
			}

			.mhm-license-label {
				font-size: 12px;
				text-transform: uppercase;
				letter-spacing: 0.04em;
				color: #646970;
			}

			.mhm-license-code {
				display: inline-block;
				font-size: 14px;
				padding: 4px 8px;
				background: #f6f7f7;
				border-radius: 4px;
				margin-top: 4px;
			}
		</style>
<?php
	}

	private static function get_system_checks(): array
	{
		global $wpdb;

		$php_ok     = version_compare(PHP_VERSION, '7.4', '>=');
		$wp_version = get_bloginfo('version');
		$wp_ok      = version_compare($wp_version, '6.0', '>=');
		$db_version = $wpdb instanceof \wpdb ? $wpdb->db_version() : __('Unknown', 'mhm-rentiva');
		$db_version = $wpdb instanceof \wpdb ? $wpdb->db_version() : __('Unknown', 'mhm-rentiva');
		$memory_mb  = self::memory_limit_mb();

		$checks = array(
			array(
				'label'    => __('WooCommerce', 'mhm-rentiva'),
				'current'  => class_exists('WooCommerce') ? __('Installed', 'mhm-rentiva') : __('Missing', 'mhm-rentiva'),
				'expected' => __('Active', 'mhm-rentiva'),
				'status'   => class_exists('WooCommerce') ? 'ok' : 'fail',
				'message'  => class_exists('WooCommerce') ? '' : __('WooCommerce is required for payments and account management.', 'mhm-rentiva'),
			),
			array(
				'label'    => __('PHP Version', 'mhm-rentiva'),
				'current'  => PHP_VERSION,
				'expected' => '7.4+',
				'status'   => $php_ok ? 'ok' : 'fail',
				'message'  => $php_ok ? '' : __('Update PHP to 7.4 or higher.', 'mhm-rentiva'),
			),
			array(
				'label'    => __('WordPress Version', 'mhm-rentiva'),
				'current'  => $wp_version,
				'expected' => '6.0+',
				'status'   => $wp_ok ? 'ok' : 'fail',
				'message'  => $wp_ok ? '' : __('Please update WordPress to the latest version.', 'mhm-rentiva'),
			),
			array(
				'label'    => __('Database Version', 'mhm-rentiva'),
				'current'  => (string) $db_version,
				'expected' => __('MySQL 5.7+ / MariaDB 10.3+', 'mhm-rentiva'),
				'status'   => 'ok',
				'message'  => '',
			),
			array(
				'label'    => __('PHP Memory Limit', 'mhm-rentiva'),
				'current'  => $memory_mb >= 9999 ? __('Unlimited', 'mhm-rentiva') : sprintf('%d MB', $memory_mb),
				'expected' => __('256 MB recommended', 'mhm-rentiva'),
				'status'   => $memory_mb >= 256 ? 'ok' : ($memory_mb >= 128 ? 'warning' : 'fail'),
				'message'  => $memory_mb >= 256 ? '' : sprintf(
					/* translators: %s: link to wp-config.php documentation */
					__('Add %s to your wp-config.php file to increase memory.', 'mhm-rentiva'),
					"<code>define('WP_MEMORY_LIMIT', '256M');</code>"
				),
			),
		);

		$max_execution = (int) ini_get('max_execution_time');
		$checks[]      = array(
			'label'    => __('PHP max_execution_time', 'mhm-rentiva'),
			'current'  => $max_execution ? sprintf('%d s', $max_execution) : __('Unlimited', 'mhm-rentiva'),
			'expected' => __('60s+', 'mhm-rentiva'),
			'status'   => $max_execution >= 60 || $max_execution === 0 ? 'ok' : 'warning',
			'message'  => $max_execution >= 60 || $max_execution === 0 ? '' : __('Increase max_execution_time to 60 seconds for large imports.', 'mhm-rentiva'),
		);

		$https    = is_ssl() || (defined('FORCE_SSL_ADMIN') && constant('FORCE_SSL_ADMIN'));
		$checks[] = array(
			'label'    => __('HTTPS / SSL', 'mhm-rentiva'),
			'current'  => $https ? __('Enabled', 'mhm-rentiva') : __('Not detected', 'mhm-rentiva'),
			'expected' => __('Valid SSL certificate', 'mhm-rentiva'),
			'status'   => $https ? 'ok' : 'warning',
			'message'  => $https ? '' : __('Install an SSL certificate to secure customer data.', 'mhm-rentiva'),
		);

		$cron_enabled = ! defined('DISABLE_WP_CRON') || ! constant('DISABLE_WP_CRON');
		$checks[]     = array(
			'label'    => __('WP-Cron', 'mhm-rentiva'),
			'current'  => $cron_enabled ? __('Active', 'mhm-rentiva') : __('Disabled', 'mhm-rentiva'),
			'expected' => __('Required for scheduled emails & jobs', 'mhm-rentiva'),
			'status'   => $cron_enabled ? 'ok' : 'warning',
			'message'  => $cron_enabled ? '' : __('Enable WP-Cron or set a real cron job for reminders.', 'mhm-rentiva'),
		);

		$email_layer = self::is_smtp_layer_detected();
		$checks[]    = array(
			'label'    => __('Email Delivery', 'mhm-rentiva'),
			'current'  => $email_layer ? __('SMTP/Provider detected', 'mhm-rentiva') : __('Default wp_mail()', 'mhm-rentiva'),
			'expected' => __('SMTP provider recommended', 'mhm-rentiva'),
			'status'   => $email_layer ? 'ok' : 'warning',
			'message'  => $email_layer ? '' : __('Configure SMTP (WP Mail SMTP, Post SMTP, etc.) for reliable delivery.', 'mhm-rentiva'),
		);

		return $checks;
	}

	private static function format_license_expiration(?int $timestamp): string
	{
		if (empty($timestamp)) {
			return __('Unknown', 'mhm-rentiva');
		}

		$date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);

		if ($timestamp >= time()) {
			$remaining = human_time_diff(time(), $timestamp);
			/* translators: 1: %1$s; 2: %2$s. */
			return sprintf(__('Expires %1$s (%2$s remaining)', 'mhm-rentiva'), $date, $remaining);
		}

		$since = human_time_diff($timestamp, time());
		/* translators: 1: %1$s; 2: %2$s. */
		return sprintf(__('Expired %1$s (%2$s ago)', 'mhm-rentiva'), $date, $since);
	}

	private static function format_status_badge(string $status): string
	{
		$labels = array(
			'ok'      => __('Ready', 'mhm-rentiva'),
			'warning' => __('Warning', 'mhm-rentiva'),
			'fail'    => __('Required', 'mhm-rentiva'),
		);
		$text   = $labels[$status] ?? __('Unknown', 'mhm-rentiva');

		return sprintf(
			'<span class="mhm-status mhm-status-%1$s">%2$s</span>',
			esc_attr($status),
			esc_html($text)
		);
	}

	/**
	 * Get PHP memory limit in megabytes
	 *
	 * Priority:
	 * 1. PHP ini_get('memory_limit') - the actual server limit
	 * 2. WP_MEMORY_LIMIT constant - WordPress internal limit (fallback)
	 *
	 * Special handling:
	 * - "-1" means unlimited memory (returns PHP_INT_MAX equivalent in MB)
	 * - Empty or invalid values fall back to WP_MEMORY_LIMIT
	 *
	 * @return int Memory limit in MB
	 */
	private static function memory_limit_mb(): int
	{
		// 1. Get PHP's actual memory limit (this is the real server limit)
		$php_limit = ini_get('memory_limit');

		// 2. Handle unlimited memory (-1)
		if ($php_limit === '-1') {
			return 9999; // Return a high value to indicate unlimited
		}

		// 3. Try to parse the PHP limit first
		$bytes = 0;
		if (!empty($php_limit)) {
			if (function_exists('wp_convert_hr_to_bytes')) {
				$bytes = wp_convert_hr_to_bytes($php_limit);
			} else {
				$bytes = self::parse_size_to_bytes($php_limit);
			}
		}

		// 4. Fallback to WP_MEMORY_LIMIT if PHP limit is invalid or zero
		if ($bytes <= 0 && defined('WP_MEMORY_LIMIT')) {
			$wp_limit = constant('WP_MEMORY_LIMIT');
			if ($wp_limit === '-1') {
				return 9999;
			}
			if (function_exists('wp_convert_hr_to_bytes')) {
				$bytes = wp_convert_hr_to_bytes($wp_limit);
			} else {
				$bytes = self::parse_size_to_bytes($wp_limit);
			}
		}

		// 5. Return 0 if still invalid
		if ($bytes <= 0) {
			return 0;
		}

		return (int) floor($bytes / 1048576); // Convert to MB
	}

	/**
	 * Parse human readable size string to bytes
	 *
	 * @param string $size Size string like "256M", "512K", "1G"
	 * @return int Size in bytes
	 */
	private static function parse_size_to_bytes(string $size): int
	{
		$size = trim($size);
		if (empty($size)) {
			return 0;
		}

		$last = strtoupper(substr($size, -1));
		$value = (int) $size;

		switch ($last) {
			case 'G':
				$value *= 1024;
				// fall through
			case 'M':
				$value *= 1024;
				// fall through
			case 'K':
				$value *= 1024;
		}

		return $value;
	}

	private static function are_system_checks_ok(): bool
	{
		foreach (self::get_system_checks() as $check) {
			if ($check['status'] === 'fail') {
				return false;
			}
		}
		return true;
	}

	private static function is_step_completed(string $step): bool
	{
		switch ($step) {
			case 'system':
				return self::are_system_checks_ok();
			case 'license':
				if (class_exists(LicenseManager::class)) {
					return LicenseManager::instance()->isActive();
				}
				return (bool) get_option('mhm_rentiva_license_key', '');
			case 'pages':
				return self::are_required_pages_present();
			case 'email':
				return SettingsCore::get('mhm_rentiva_email_send_enabled', '0') === '1'
					&& (bool) SettingsCore::get('mhm_rentiva_email_from_address', '');
			case 'payments':
				return self::is_payment_ready();
			case 'frontend':
				return (bool) SettingsCore::get('mhm_rentiva_currency', 'USD');
			case 'summary':
				return get_option(self::OPTION_COMPLETED, '0') === '1';
			default:
				return false;
		}
	}

	private static function are_required_pages_present(): bool
	{
		foreach (self::get_required_pages() as $page) {
			if (! self::locate_shortcode_page($page['shortcode'])) {
				return false;
			}
		}
		return true;
	}

	private static function is_payment_ready(): bool
	{
		$settings = get_option('mhm_rentiva_settings', array());
		$gateways = array(

			'0', // ⭐ Offline payment removed - WooCommerce handles all payments
		);

		return in_array('1', $gateways, true);
	}

	private static function is_smtp_layer_detected(): bool
	{
		$hooks = array('phpmailer_init', 'wp_mail', 'pre_wp_mail');
		foreach ($hooks as $hook) {
			if (has_filter($hook)) {
				return true;
			}
		}

		$constants = array(
			'WP_MAIL_SMTP_VERSION',
			'FLUENTMAIL_PLUGIN_VERSION',
			'FLUENTMAIL_VERSION',
			'POST_SMTP_VER',
			'POSTSMTP_VER',
			'SENDGRID_VERSION',
			'MAILBANK_PLUGIN_VERSION',
		);
		foreach ($constants as $constant) {
			if (defined($constant)) {
				return true;
			}
		}

		$actions = array(
			'wp_mail_smtp_init',
			'fluentmail_loaded',
			'post_smtp_init',
			'mailbank_loaded',
		);
		foreach ($actions as $action) {
			if (did_action($action)) {
				return true;
			}
		}

		$classes = array(
			'FluentMail\\App\\Plugin',
			'FluentMail\\App\\Services\\Mailer',
			'Postman',
			'PostmanWpMailBinder',
			'Mail_Bank',
			'Sendgrid_For_WordPress',
		);
		foreach ($classes as $class) {
			if (class_exists($class)) {
				return true;
			}
		}

		if (function_exists('wp_mail_smtp')) {
			return true;
		}

		return false;
	}
}
