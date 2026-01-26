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


	/**
	 * Register the About class
	 */
	public static function register(): void
	{
		add_action('admin_enqueue_scripts', array(self::class, 'enqueue_scripts'));
		add_action('wp_ajax_mhm_about_load_tab', array(self::class, 'ajax_load_tab'));
	}

	/**
	 * Render the About page
	 */
	public static function render_page(): void
	{
		if (! current_user_can('manage_options')) {
			return;
		}
		// Get system info from cache and set it globally
		$system_info            = SystemInfo::get_cached_system_info();
		$GLOBALS['system_info'] = $system_info;
		$features               = FeaturesTab::get_features_list();
		$changelog              = SupportTab::get_changelog();

		// Active tab
		$active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';

?>
		<div class="wrap mhm-about-wrap">
			<div class="about-header">
				<div class="header-content">
					<h1><?php esc_html_e('About MHM Rentiva', 'mhm-rentiva'); ?></h1>
					<div class="version-info">
						<span class="version-badge">v<?php echo esc_html(MHM_RENTIVA_VERSION); ?></span>
						<span class="license-badge <?php echo esc_attr(Mode::isPro() ? 'pro' : 'lite'); ?>">
							<?php echo esc_html(Mode::isPro() ? __('Pro Version', 'mhm-rentiva') : __('Lite Version', 'mhm-rentiva')); ?>
						</span>
					</div>
				</div>
				<div class="header-actions">
					<?php
					$company_website = \MHMRentiva\Admin\Settings\Core\SettingsCore::get_company_website();
					$support_email   = \MHMRentiva\Admin\Settings\Core\SettingsCore::get_support_email();
					?>
					<?php \MHMRentiva\Admin\Core\Utilities\UXHelper::render_docs_button(); ?>
					<?php
					echo wp_kses_post(
						Helpers::render_external_link(
							'mailto:' . $support_email,
							esc_html__('Support', 'mhm-rentiva'),
							array('class' => 'button button-primary')
						)
					);
					?>
				</div>
			</div>

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
				self::render_tab_content($active_tab, $system_info, $features, $changelog);
				?>
			</div>
		</div>
<?php
	}

	/**
	 * Render tab content (shared between render_page and ajax_load_tab)
	 */
	private static function render_tab_content(string $tab, array $system_info, array $features, array $changelog): void
	{
		switch ($tab) {
			case 'general':
				GeneralTab::render($system_info);
				break;
			case 'features':
				FeaturesTab::render($features);
				break;
			case 'system':
				SystemTab::render($system_info);
				break;
			case 'support':
				SupportTab::render($changelog);
				break;
			case 'developer':
				DeveloperTab::render();
				break;
		}
	}

	/**
	 * Enqueue scripts and styles
	 */
	public static function enqueue_scripts(string $hook): void
	{
		if ($hook !== 'mhm-rentiva_page_mhm-rentiva-about') {
			return;
		}

		wp_enqueue_style(
			'mhm-about-admin',
			MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/about.css',
			array(),
			MHM_RENTIVA_VERSION
		);

		wp_enqueue_script(
			'mhm-about-admin',
			MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/about.js',
			array('jquery'),
			MHM_RENTIVA_VERSION,
			true
		);

		wp_localize_script(
			'mhm-about-admin',
			'mhmAboutAdmin',
			array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce'    => wp_create_nonce('mhm_about_admin'),
				'strings'  => array(
					'loading' => esc_html__('Loading...', 'mhm-rentiva'),
					'error'   => esc_html__('An error occurred.', 'mhm-rentiva'),
				),
			)
		);
	}

	/**
	 * AJAX handler for tab loading
	 */
	public static function ajax_load_tab(): void
	{
		// Verify nonce
		if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'mhm_about_admin')) {
			wp_send_json_error(array('message' => esc_html__('Security error', 'mhm-rentiva')));
			return;
		}

		// Check user permissions
		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => esc_html__('Permission error', 'mhm-rentiva')));
			return;
		}

		$tab = sanitize_key($_POST['tab'] ?? '');

		if (empty($tab)) {
			wp_send_json_error(array('message' => esc_html__('Invalid tab', 'mhm-rentiva')));
			return;
		}

		// Start output buffering
		ob_start();

		try {
			// Get system info from cache and set it globally
			$system_info            = SystemInfo::get_cached_system_info();
			$GLOBALS['system_info'] = $system_info;
			$features               = FeaturesTab::get_features_list();
			$changelog              = SupportTab::get_changelog();

			// Render the requested tab
			if (! in_array($tab, array('general', 'features', 'system', 'support', 'developer'), true)) {
				wp_send_json_error(array('message' => esc_html__('Unknown tab', 'mhm-rentiva')));
				return;
			}

			self::render_tab_content($tab, $system_info, $features, $changelog);

			$content = ob_get_clean();

			wp_send_json_success(array('content' => $content));
		} catch (\Exception $e) {
			ob_end_clean();
			wp_send_json_error(array('message' => $e->getMessage()));
		}
	}
}
