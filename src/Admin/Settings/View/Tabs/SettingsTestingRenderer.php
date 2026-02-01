<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\View\Tabs;

use MHMRentiva\Admin\Settings\View\AbstractTabRenderer;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Renderer for the Settings Testing tab
 *
 * Provides an interface to run diagnostic tests on current configuration.
 * Uses AJAX for real-time reporting.
 */
final class SettingsTestingRenderer extends AbstractTabRenderer
{

	public function __construct()
	{
		parent::__construct(
			__('Settings Testing', 'mhm-rentiva'),
			'testing'
		);
	}

	/**
	 * @inheritDoc
	 */
	public function get_header_actions(): array
	{
		return array(
			array(
				'text'  => __('Run All Diagnostics', 'mhm-rentiva'),
				'url'   => '#',
				'class' => 'button button-primary',
				'id'    => 'mhm-run-tests',
				'icon'  => 'dashicons-performance',
			),
			array(
				'text'  => __('Clear Results', 'mhm-rentiva'),
				'url'   => '#',
				'class' => 'button button-secondary',
				'id'    => 'mhm-clear-tests',
				'icon'  => 'dashicons-trash',
			),
		);
	}

	/**
	 * @inheritDoc
	 */
	public function render(): void
	{
		if (! class_exists('\MHMRentiva\Admin\Settings\Testing\SettingsTester')) {
			echo '<div class="notice notice-error"><p>' . esc_html__('Diagnostic testing engine not found.', 'mhm-rentiva') . '</p></div>';
			return;
		}

		$this->enqueue_testing_assets();

?>
		<div class="mhm-settings-testing-container" style="margin-top: 20px;">
			<div id="mhm-test-results" class="mhm-test-results-area" style="display: none;"></div>
		</div>
<?php
	}

	/**
	 * Enqueue assets for diagnostic testing
	 */
	private function enqueue_testing_assets(): void
	{
		$version = defined('MHM_RENTIVA_VERSION') ? (string) MHM_RENTIVA_VERSION : '1.0.0';
		$url     = defined('MHM_RENTIVA_PLUGIN_URL') ? (string) MHM_RENTIVA_PLUGIN_URL : '';

		wp_enqueue_script(
			'mhm-settings-testing',
			esc_url($url . 'assets/js/admin/settings-testing.js'),
			array('jquery'),
			$version,
			true
		);

		wp_localize_script(
			'mhm-settings-testing',
			'mhm_settings_testing',
			array(
				'nonce'        => wp_create_nonce('mhm_settings_test_nonce'),
				'run_text'     => __('Run All Diagnostics', 'mhm-rentiva'),
				'running_text' => __('Analyzing System...', 'mhm-rentiva'),
				'error_text'   => __('Diagnostic failed to complete.', 'mhm-rentiva'),
			)
		);
	}

	/**
	 * Diagnostic data is handled via the separate tester class
	 */
	public function should_wrap_with_form(): bool
	{
		return false;
	}
}
