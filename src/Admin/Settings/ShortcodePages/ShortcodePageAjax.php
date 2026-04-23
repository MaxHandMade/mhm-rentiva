<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\ShortcodePages;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Shortcode page AJAX handlers intentionally run bounded config/query lookups.



use MHMRentiva\Admin\Core\ShortcodeUrlManager;



/**
 * Shortcode Page AJAX Handlers
 */
final class ShortcodePageAjax {




	public function __construct(
		private readonly ShortcodePageActions $actions
	) {}

	/**
	 * AJAX: Clear cache
	 */
	public function clear_cache(): void
	{
		if (! check_ajax_referer(\MHMRentiva\Admin\Settings\ShortcodePages::ACTION_CLEAR_CACHE, 'nonce', false)) {
			wp_send_json_error(array( 'message' => __('Invalid security nonce.', 'mhm-rentiva') ));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array( 'message' => esc_html__('You do not have permission.', 'mhm-rentiva') ));
		}

		try {
			ShortcodeUrlManager::clear_cache();
			wp_send_json_success(array( 'message' => esc_html__('Cache cleared successfully.', 'mhm-rentiva') ));
		} catch (\Throwable $e) {
			wp_send_json_error(array( 'message' => esc_html__('Error while clearing cache: ', 'mhm-rentiva') . esc_html($e->getMessage()) ));
		}
	}

	/**
	 * AJAX: Create shortcode page
	 */
	public function create_page(): void
	{
		if (! check_ajax_referer(\MHMRentiva\Admin\Settings\ShortcodePages::ACTION_CREATE_PAGE, 'nonce', false)) {
			wp_send_json_error(array( 'message' => __('Invalid security nonce.', 'mhm-rentiva') ));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array( 'message' => esc_html__('You do not have permission for this action.', 'mhm-rentiva') ));
		}

		// Apply wp_unslash before sanitization as per WP standards
		$shortcode = sanitize_text_field(wp_unslash($_POST['shortcode'] ?? ''));

		if (empty($shortcode)) {
			wp_send_json_error(array( 'message' => esc_html__('Shortcode not specified.', 'mhm-rentiva') ));
		}

		$page_id = $this->actions->create_page($shortcode);

		if ($page_id) {
			ShortcodeUrlManager::clear_cache();

			wp_send_json_success(
				array(
					'message'  => esc_html__('Page created successfully.', 'mhm-rentiva'),
					'page_id'  => (int) $page_id,
					'edit_url' => get_edit_post_link($page_id, 'raw'),
					'view_url' => esc_url(get_permalink($page_id)),
				)
			);
		}

		wp_send_json_error(array( 'message' => esc_html__('Error occurred while creating page.', 'mhm-rentiva') ));
	}

	/**
	 * AJAX: Delete shortcode page
	 */
	public function delete_page(): void
	{
		if (! check_ajax_referer(\MHMRentiva\Admin\Settings\ShortcodePages::ACTION_DELETE_PAGE, 'nonce', false)) {
			wp_send_json_error(array( 'message' => __('Invalid security nonce.', 'mhm-rentiva') ));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array( 'message' => esc_html__('You do not have permission for this action.', 'mhm-rentiva') ));
		}

		// Apply wp_unslash and absint
		$page_id = absint(wp_unslash($_POST['page_id'] ?? 0));

		if ($page_id <= 0) {
			wp_send_json_error(array( 'message' => esc_html__('Invalid page ID.', 'mhm-rentiva') ));
		}

		if ($this->actions->delete_page($page_id)) {
			wp_send_json_success(
				array(
					'message' => esc_html__('Page successfully moved to trash.', 'mhm-rentiva'),
					'page_id' => (int) $page_id,
				)
			);
		}

		wp_send_json_error(array( 'message' => esc_html__('Error occurred while removing page.', 'mhm-rentiva') ));
	}

	/**
	 * AJAX: Debug shortcode search
	 */
	public function debug_search(): void
	{
		if (! check_ajax_referer(\MHMRentiva\Admin\Settings\ShortcodePages::ACTION_DEBUG_SEARCH, 'nonce', false)) {
			wp_send_json_error(array( 'message' => __('Invalid security nonce.', 'mhm-rentiva') ));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array( 'message' => esc_html__('You do not have permission for this action.', 'mhm-rentiva') ));
		}

		global $wpdb;

		// Optimize SQL query performance: Select only needed columns and use index-friendly WHERE
		// Search for anything likely to contain a shortcode [
		$all_pages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_content FROM {$wpdb->posts} 
                 WHERE post_type = %s 
                 AND post_status = %s
                 AND post_content LIKE %s
                 ORDER BY post_date DESC",
				'page',
				'publish',
				'%[%'
			)
		);

		$debug_info = array();
		$config     = $this->actions->get_config();
		$shortcodes = array_keys($config);

		foreach ($all_pages as $page) {
			$found = array();
			foreach ($shortcodes as $s) {
				// Use regex for more accurate detection in content
				if (preg_match('/\[' . preg_quote($s, '/') . '(\]| |=)/', (string) $page->post_content)) {
					$found[] = $s;
				}
			}

			if (! empty($found)) {
				$debug_info[] = array(
					'id'              => (int) $page->ID,
					'title'           => esc_html($page->post_title),
					'shortcodes'      => $found,
					'url'             => esc_url(get_permalink($page->ID)),
					'content_preview' => esc_html(mb_substr(wp_strip_all_tags( (string) $page->post_content), 0, 200)) . '...',
				);
			}
		}

		wp_send_json_success(
			array(
				/* translators: %d: number of pages found */
				'message' => sprintf(esc_html__('%d pages found.', 'mhm-rentiva'), (int) count($debug_info)),
				'pages'   => $debug_info,
			)
		);
	}

	/**
	 * AJAX: Reset shortcode pages
	 */
	public function reset_pages(): void
	{
		if (! check_ajax_referer(\MHMRentiva\Admin\Settings\ShortcodePages::ACTION_RESET_PAGES, 'nonce', false)) {
			wp_send_json_error(array( 'message' => __('Invalid security nonce.', 'mhm-rentiva') ));
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array( 'message' => esc_html__('You do not have permission for this action.', 'mhm-rentiva') ));
		}

		if ($this->actions->reset_pages()) {
			wp_send_json_success(
				array(
					'message' => esc_html__('All shortcode pages have been deleted and system returned to default settings.', 'mhm-rentiva'),
				)
			);
		}

		wp_send_json_error(array( 'message' => esc_html__('Error occurred while resetting pages.', 'mhm-rentiva') ));
	}
}
