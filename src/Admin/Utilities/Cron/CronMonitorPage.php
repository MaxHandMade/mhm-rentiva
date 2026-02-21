<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Utilities\Cron;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Cron Monitor Admin Page (AJAX Handlers)
 */
final class CronMonitorPage
{

	public static function register(): void
	{
		add_action('wp_ajax_mhm_list_cron_jobs', array(self::class, 'ajax_list_cron_jobs'));
		add_action('wp_ajax_mhm_run_cron_job', array(self::class, 'ajax_run_cron_job'));
		add_action('wp_ajax_mhm_test_cron_jobs', array(self::class, 'ajax_test_cron_jobs'));
	}

	/**
	 * AJAX - List all cron jobs
	 */
	public static function ajax_list_cron_jobs(): void
	{
		if (! check_ajax_referer('mhm_cron_monitor', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security nonce.', 'mhm-rentiva')));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied', 'mhm-rentiva')));
		}

		$crons = CronMonitor::get_all_cron_jobs();

		wp_send_json_success(
			array(
				'crons' => $crons,
				'count' => count($crons),
			)
		);
	}

	/**
	 * AJAX - Run cron job manually
	 */
	public static function ajax_run_cron_job(): void
	{
		if (! check_ajax_referer('mhm_cron_monitor', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security nonce.', 'mhm-rentiva')));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied', 'mhm-rentiva')));
		}

		$hook = isset($_POST['hook']) ? sanitize_text_field(wp_unslash($_POST['hook'])) : '';
		$args = array();
		if (isset($_POST['args']) && is_array($_POST['args'])) {
			$args = array_map(
				'sanitize_text_field',
				wp_unslash($_POST['args'])
			);
		}

		if (empty($hook)) {
			wp_send_json_error(esc_html__('Cron hook is required', 'mhm-rentiva'));
		}

		$result = CronMonitor::run_cron_job($hook, $args);

		if ($result['success']) {
			wp_send_json_success($result);
		} else {
			wp_send_json_error($result['message']);
		}
	}

	/**
	 * AJAX - Test all cron jobs
	 */
	public static function ajax_test_cron_jobs(): void
	{
		if (! check_ajax_referer('mhm_cron_monitor', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security nonce.', 'mhm-rentiva')));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied', 'mhm-rentiva')));
		}

		$results = CronMonitor::test_all_cron_jobs();

		wp_send_json_success(
			array(
				'results' => $results,
				'count'   => count($results),
			)
		);
	}
}
