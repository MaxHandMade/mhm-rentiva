<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\About;

use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\About\SystemInfo;
use MHMRentiva\Admin\About\Helpers;
use MHMRentiva\Admin\About\Tabs\GeneralTab;
use MHMRentiva\Admin\About\Tabs\FeaturesTab;
use MHMRentiva\Admin\About\Tabs\SystemTab;
use MHMRentiva\Admin\About\Tabs\SupportTab;
use MHMRentiva\Admin\About\Tabs\DeveloperTab;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * About page main class
 */
final class About
{
	use \MHMRentiva\Admin\Core\Traits\AdminHelperTrait;


	/**
	 * Registers the About class hooks.
	 *
	 * @return void
	 */
	public static function register(): void
	{
		add_action('admin_enqueue_scripts', array(self::class, 'enqueue_scripts'));
		add_action('wp_ajax_mhm_rentiva_about_load_tab', array(self::class, 'ajax_load_tab'));
	}

	/**
	 * Renders the About page content.
	 *
	 * @return void
	 */
	public function render_page(): void
	{
		if (! current_user_can('manage_options')) {
			return;
		}

		// Determine the active tab from the URL or default to 'general'.
		$active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'general';

?>
		<div class="wrap mhm-rentiva-about-wrap">
			<?php
			$company_website = \MHMRentiva\Admin\Settings\Core\SettingsCore::get_company_website();
			$support_email   = \MHMRentiva\Admin\Settings\Core\SettingsCore::get_support_email();

			$title = sprintf(
				'%s <span class="version-badge">v%s</span> <span class="license-badge %s">%s</span>',
				esc_html__('About MHM Rentiva', 'mhm-rentiva'),
				MHM_RENTIVA_VERSION,
				Mode::isPro() ? 'pro' : 'lite',
				Mode::isPro() ? esc_html__('Pro', 'mhm-rentiva') : esc_html__('Lite', 'mhm-rentiva')
			);

			$this->render_admin_header(
				$title,
				array(
					array(
						'type' => 'documentation',
						'url'  => \MHMRentiva\Admin\Core\Utilities\UXHelper::get_docs_url(),
					),
					array(
						'text'   => __('Support', 'mhm-rentiva'),
						'url'    => 'mailto:' . esc_attr($support_email),
						'class'  => 'button button-secondary',
						'icon'   => 'dashicons-sos',
						'target' => '_blank',
					),
					array(
						'text'  => __('Settings', 'mhm-rentiva'),
						'url'   => admin_url('admin.php?page=mhm-rentiva-settings'),
						'class' => 'button button-primary',
						'icon'  => 'dashicons-admin-settings',
					),
				)
			);
			?>

			<div class="nav-tab-wrapper">
				<a href="<?php echo esc_url(add_query_arg('tab', 'general')); ?>"
					class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e('General Information', 'mhm-rentiva'); ?>
				</a>
				<a href="<?php echo esc_url(add_query_arg('tab', 'features')); ?>"
					class="nav-tab <?php echo 'features' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e('Features', 'mhm-rentiva'); ?>
				</a>
				<a href="<?php echo esc_url(add_query_arg('tab', 'system')); ?>"
					class="nav-tab <?php echo 'system' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e('System Information', 'mhm-rentiva'); ?>
				</a>
				<a href="<?php echo esc_url(add_query_arg('tab', 'support')); ?>"
					class="nav-tab <?php echo 'support' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e('Support', 'mhm-rentiva'); ?>
				</a>
				<a href="<?php echo esc_url(add_query_arg('tab', 'developer')); ?>"
					class="nav-tab <?php echo 'developer' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e('Developer', 'mhm-rentiva'); ?>
				</a>
			</div>

			<div class="tab-content">
				<?php
				try {
					static::render_tab_content_lazy($active_tab);
				} catch (\Throwable $e) {
					$this->show_error_message(sprintf(
						/* translators: %s error message */
						esc_html__('Error loading tab content: %s', 'mhm-rentiva'),
						$e->getMessage()
					));
				}
				?>
			</div>
		</div>
<?php
	}

	/**
	 * Renders the content for a specific tab with lazy loading.
	 *
	 * @param string $tab The identifier for the tab to render.
	 * @return void
	 */
	private static function render_tab_content_lazy(string $tab): void
	{
		switch ($tab) {
			case 'general':
				$system_info = SystemInfo::get_cached_system_info();
				GeneralTab::render($system_info);
				break;
			case 'features':
				$features = FeaturesTab::get_features_list();
				FeaturesTab::render($features);
				break;
			case 'system':
				$system_info = SystemInfo::get_cached_system_info();
				SystemTab::render($system_info);
				break;
			case 'support':
				$changelog = SupportTab::get_changelog();
				SupportTab::render($changelog);
				break;
			case 'developer':
				DeveloperTab::render();
				break;
			default:
				echo '<p>' . esc_html__('Invalid tab selected.', 'mhm-rentiva') . '</p>';
				break;
		}
	}

	/**
	 * Enqueues admin scripts and styles for the About page.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public static function enqueue_scripts(string $hook): void
	{
		if ('mhm-rentiva_page_mhm-rentiva-about' !== $hook) {
			return;
		}

		wp_enqueue_style(
			'mhm-rentiva-about-admin',
			MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/about.css',
			array(),
			MHM_RENTIVA_VERSION . '.1'
		);

		wp_enqueue_script(
			'mhm-rentiva-about-admin',
			MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/about.js',
			array('jquery'),
			MHM_RENTIVA_VERSION . '.1',
			true
		);

		wp_localize_script(
			'mhm-rentiva-about-admin',
			'mhmRentivaAboutAdmin',
			array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce'    => wp_create_nonce('mhm_rentiva_about_admin_nonce'),
				'strings'  => array(
					'loading' => esc_html__('Loading...', 'mhm-rentiva'),
					'error'   => esc_html__('An error occurred.', 'mhm-rentiva'),
				),
			)
		);
	}

	/**
	 * Handles AJAX requests for loading tab content.
	 *
	 * @return void
	 */
	public static function ajax_load_tab(): void
	{
		// Verify nonce for security.
		if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'mhm_rentiva_about_admin_nonce')) {
			wp_send_json_error(array('message' => esc_html__('Security check failed.', 'mhm-rentiva')));
			return;
		}

		// Check user permissions.
		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => esc_html__('Permission denied.', 'mhm-rentiva')));
			return;
		}

		$tab = sanitize_key(wp_unslash($_POST['tab'] ?? ''));

		if (empty($tab)) {
			wp_send_json_error(array('message' => esc_html__('Invalid tab specified.', 'mhm-rentiva')));
			return;
		}

		// Start output buffering to capture tab content.
		ob_start();

		try {
			// Validate and render the requested tab.
			$allowed_tabs = array('general', 'features', 'system', 'support', 'developer');
			if (! in_array($tab, $allowed_tabs, true)) {
				wp_send_json_error(array('message' => esc_html__('Unknown tab requested.', 'mhm-rentiva')));
				return;
			}

			// Data is fetched lazily inside the render method to improve performance
			self::render_tab_content_lazy($tab);

			$content = ob_get_clean();

			wp_send_json_success(array('content' => $content));
		} catch (\Exception $e) {
			ob_end_clean();
			wp_send_json_error(array('message' => esc_html__('An unexpected error occurred: ', 'mhm-rentiva') . $e->getMessage()));
		}
	}
}
