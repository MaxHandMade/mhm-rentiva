<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Core;

use MHMRentiva\Admin\Core\Utilities\Templates;
use Exception;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Enhanced Abstract Shortcode Base Class
 *
 * Enhanced common structure and methods for all shortcode classes.
 * Prevents code repetition, improves performance and ensures consistency.
 */
abstract class AbstractShortcode
{

	// Shortcode cache
	private static array $shortcode_cache = array();

	// Asset enqueue tracking
	private static array $enqueued_assets = array();

	/**
	 * Returns shortcode tag
	 *
	 * @return string Shortcode tag (e.g.: 'rentiva_booking_form')
	 */
	abstract protected static function get_shortcode_tag(): string;

	/**
	 * Returns template file path
	 *
	 * @return string Template path (e.g.: 'shortcodes/booking-form')
	 */
	abstract protected static function get_template_path(): string;

	/**
	 * Returns default attributes for template
	 *
	 * @return array Default attributes
	 */
	abstract protected static function get_default_attributes(): array;

	abstract protected static function prepare_template_data(array $atts): array;

	/**
	 * Public access to template data (for Extension Widgets)
	 *
	 * @param array $atts Attributes
	 * @return array Template data
	 */
	public static function get_data(array $atts): array
	{
		return static::prepare_template_data($atts);
	}

	/**
	 * Registers shortcode to WordPress
	 */
	public static function register(): void
	{
		$tag = static::get_shortcode_tag();

		// Cache check (additional security)
		if (isset(self::$shortcode_cache[$tag])) {
			return;
		}

		// NOTE: add_shortcode is handled by ShortcodeServiceProvider centrally.
		// This method only handles class-internal hooks and AJAX handlers.
		self::$shortcode_cache[$tag] = true;
		static::register_ajax_handlers();
		static::register_hooks();
	}

	/**
	 * Renders shortcode
	 *
	 * @param array       $atts Shortcode attributes
	 * @param string|null $content Shortcode content
	 * @return string Rendered HTML
	 */
	public static function render(array $atts = array(), ?string $content = null): string
	{
		$start_time = microtime(true);

		try {
			// Normalize attributes
			$atts = shortcode_atts(static::get_default_attributes(), $atts, static::get_shortcode_tag());

			// Performance: Cache check
			$cache_key = static::get_cache_key($atts);
			if ($cached_html = static::get_cached_html($cache_key)) {
				return $cached_html;
			}

			// Load assets (only once)
			static::enqueue_assets_once($atts);

			// Prepare template data
			$template_data = static::prepare_template_data($atts);

			// Render template
			$html = static::render_template($template_data);

			// Empty HTML check
			if (empty($html)) {
				$html = static::get_fallback_html($atts);
			}

			// Performance: Cache HTML
			static::cache_html($cache_key, $html);

			// Filter hook
			$html = apply_filters('mhm_rentiva/shortcodes/' . static::get_shortcode_tag() . '/html', $html, $atts, $content);

			// Performance logging - disabled to reduce debug log noise
			// $render_time = microtime(true) - $start_time;

			return $html;
		} catch (Exception $e) {
			return static::get_error_html($e->getMessage());
		}
	}

	/**
	 * Template rendering (can be overridden)
	 */
	protected static function render_template(array $template_data): string
	{
		return Templates::render(static::get_template_path(), $template_data, true);
	}

	/**
	 * Load assets only once
	 * 
	 * @param array $atts Shortcode attributes
	 */
	protected static function enqueue_assets_once(array $atts = []): void
	{
		$tag = static::get_shortcode_tag();

		if (isset(self::$enqueued_assets[$tag])) {
			return;
		}

		// Conditional Asset Loading check
		if (static::should_load_assets_conditionally()) {
			// Enqueue Global Notifications System (Must be here to ensure it loads even if child overrides enqueue_assets)
			wp_enqueue_style(
				'mhm-rentiva-notifications',
				MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/notifications.css',
				array(),
				MHM_RENTIVA_VERSION
			);

			static::enqueue_assets($atts);
		}

		self::$enqueued_assets[$tag] = true;
	}

	/**
	 * Conditional Asset Loading check
	 */
	protected static function should_load_assets_conditionally(): bool
	{
		// Default to true (optimize performance) but allow easy disabling via filter
		return apply_filters('mhm_rentiva_shortcode_load_assets_conditionally', true, static::get_shortcode_tag());
	}

	/**
	 * Loads CSS and JS files
	 * Can be overridden
	 * 
	 * @param array $atts Shortcode attributes
	 */
	protected static function enqueue_assets(array $atts = []): void
	{
		static::enqueue_styles($atts);
		static::enqueue_scripts($atts);
	}

	/**
	 * Loads CSS files
	 * Can be overridden
	 * 
	 * @param array $atts Shortcode attributes
	 */
	protected static function enqueue_styles(array $atts = []): void
	{
		$handle    = static::get_asset_handle();
		$css_files = static::get_css_files($atts);

		foreach ($css_files as $css_file) {
			if (static::asset_exists($css_file)) {
				wp_enqueue_style(
					$handle,
					MHM_RENTIVA_PLUGIN_URL . $css_file,
					static::get_css_dependencies(),
					MHM_RENTIVA_VERSION
				);
				break; // Load first found file
			}
		}
	}

	/**
	 * Loads JavaScript files
	 * Can be overridden
	 * 
	 * @param array $atts Shortcode attributes
	 */
	protected static function enqueue_scripts(array $atts = []): void
	{
		$handle   = static::get_asset_handle();
		$js_files = static::get_js_files($atts);

		foreach ($js_files as $js_file) {
			if (static::asset_exists($js_file)) {
				wp_enqueue_script(
					$handle,
					MHM_RENTIVA_PLUGIN_URL . $js_file,
					static::get_js_dependencies(),
					MHM_RENTIVA_VERSION,
					true
				);

				// Localize script
				static::localize_script($handle);
				break; // Load first found file
			}
		}
	}

	/**
	 * Returns CSS files (can be overridden)
	 * 
	 * @param array $atts Shortcode attributes
	 */
	protected static function get_css_files(array $atts = []): array
	{
		return array(
			static::get_assets_path() . '/css/frontend/' . static::get_css_filename(),
		);
	}

	/**
	 * Returns JS files (can be overridden)
	 * 
	 * @param array $atts Shortcode attributes
	 */
	protected static function get_js_files(array $atts = []): array
	{
		return array(
			static::get_assets_path() . '/js/frontend/' . static::get_js_filename(),
		);
	}

	/**
	 * Returns CSS dependencies (can be overridden)
	 */
	protected static function get_css_dependencies(): array
	{
		return array();
	}

	/**
	 * Returns JS dependencies (can be overridden)
	 */
	protected static function get_js_dependencies(): array
	{
		return array('jquery');
	}

	/**
	 * Provides localized data for JavaScript
	 * Can be overridden
	 */
	protected static function localize_script(string $handle): void
	{
		$script_object  = static::get_script_object_name();
		$localized_data = static::get_localized_data();

		if (! empty($localized_data)) {
			wp_localize_script($handle, $script_object, $localized_data);
		}
	}

	/**
	 * Registers WordPress hooks (can be overridden)
	 */
	protected static function register_hooks(): void
	{
		// By default, does not register any hooks
		// Child classes can override this method
	}

	/**
	 * Registers AJAX handlers
	 * Can be overridden
	 */
	protected static function register_ajax_handlers(): void
	{
		// By default, does not register any AJAX handlers
		// Child classes can override this method
	}

	/**
	 * Returns asset handle name
	 */
	protected static function get_asset_handle(): string
	{
		return 'mhm-rentiva-' . str_replace('_', '-', static::get_shortcode_tag());
	}

	/**
	 * Returns CSS filename
	 * Can be overridden
	 */
	protected static function get_css_filename(): string
	{
		return static::get_shortcode_tag() . '.css';
	}

	/**
	 * Returns JavaScript filename
	 * Can be overridden
	 */
	protected static function get_js_filename(): string
	{
		return static::get_shortcode_tag() . '.js';
	}

	/**
	 * Returns assets folder path
	 */
	protected static function get_assets_path(): string
	{
		return 'assets';
	}

	/**
	 * Returns script object name
	 * Can be overridden
	 */
	protected static function get_script_object_name(): string
	{
		$tag       = static::get_shortcode_tag();
		$clean_tag = str_replace(array('rentiva_', '_'), array('', ''), $tag);
		return 'mhmRentiva' . ucfirst($clean_tag);
	}

	/**
	 * Returns localized data
	 * Can be overridden
	 */
	protected static function get_localized_data(): array
	{
		return array(
			'ajaxUrl' => admin_url('admin-ajax.php'),
			'ajax_url' => admin_url('admin-ajax.php'),
			'restUrl' => rest_url('mhm-rentiva/v1/'),
			'nonce'   => wp_create_nonce('mhm_rentiva_' . static::get_shortcode_tag() . '_nonce'),
			'strings' => static::get_localized_strings(),
			'config'  => static::get_js_config(),
		);
	}

	/**
	 * Returns JavaScript config (can be overridden)
	 */
	protected static function get_js_config(): array
	{
		return array();
	}

	/**
	 * Returns localized strings
	 * Can be overridden
	 */
	protected static function get_localized_strings(): array
	{
		return array(
			'loading'  => __('Loading...', 'mhm-rentiva'),
			'error'    => __('An error occurred', 'mhm-rentiva'),
			'success'  => __('Success', 'mhm-rentiva'),
			'required' => __('This field is required', 'mhm-rentiva'),
			'invalid'  => __('Invalid value', 'mhm-rentiva'),
		);
	}

	/**
	 * Creates cache key with versioning support
	 */
	protected static function get_cache_key(array $atts): string
	{
		$tag = static::get_shortcode_tag();

		// Use versioning for mass invalidation (The WordPress Way)
		$version = get_transient('mhm_rv_cache_v_' . $tag);
		if (false === $version) {
			$version = (string) time();
			set_transient('mhm_rv_cache_v_' . $tag, $version, DAY_IN_SECONDS * 30);
		}

		// Include page ID, user status and other factors
		$cache_factors = array(
			'atts'     => $atts,
			'page_id'  => get_the_ID(),
			'user_id'  => get_current_user_id(),
			'is_admin' => is_admin(),
			'language' => get_locale(),
			'theme'    => get_template(),
			'v'        => $version,
		);

		return 'mhm_shc_' . substr(md5($tag), 0, 8) . '_' . md5(serialize($cache_factors));
	}

	/**
	 * Gets HTML from cache
	 */
	protected static function get_cached_html(string $cache_key): ?string
	{
		if (! static::is_caching_enabled()) {
			return null;
		}

		return get_transient($cache_key);
	}

	/**
	 * HTML'i cache'e kaydeder
	 */
	protected static function cache_html(string $cache_key, string $html): void
	{
		if (! static::is_caching_enabled()) {
			return;
		}

		set_transient($cache_key, $html, static::get_cache_ttl());
	}

	/**
	 * Cache aktif mi kontrol eder
	 */
	protected static function is_caching_enabled(): bool
	{
		return defined('WP_CACHE') && WP_CACHE && ! is_user_logged_in();
	}

	/**
	 * Returns cache TTL (in seconds)
	 */
	protected static function get_cache_ttl(): int
	{
		return 5 * MINUTE_IN_SECONDS; // 5 minutes (shorter duration)
	}

	/**
	 * Checks if file exists
	 */
	protected static function asset_exists(string $file_path): bool
	{
		$full_path = MHM_RENTIVA_PLUGIN_PATH . $file_path;
		return file_exists($full_path);
	}

	/**
	 * Returns fallback HTML (if template not found)
	 * Can be overridden
	 */
	protected static function get_fallback_html(array $atts): string
	{
		return sprintf(
			'<div class="mhm-rentiva-shortcode-error">
                <p>%s</p>
                <small>Shortcode: %s | Template: %s</small>
            </div>',
			__('Template file not found.', 'mhm-rentiva'),
			static::get_shortcode_tag(),
			static::get_template_path()
		);
	}

	/**
	 * Returns error HTML
	 * Can be overridden
	 */
	protected static function get_error_html(string $error_message): string
	{
		if (! current_user_can('manage_options')) {
			return '<div class="mhm-rentiva-shortcode-error"><p>' .
				__('An error occurred while loading shortcode.', 'mhm-rentiva') .
				'</p></div>';
		}

		return sprintf(
			'<div class="mhm-rentiva-shortcode-error">
                <p>%s</p>
                <small>Error: %s | Shortcode: %s</small>
            </div>',
			__('An error occurred while loading shortcode.', 'mhm-rentiva'),
			esc_html($error_message),
			static::get_shortcode_tag()
		);
	}

	/**
	 * Nonce verification helper
	 */
	protected static function verify_nonce(string $nonce, string $action = ''): bool
	{
		if (empty($action)) {
			$action = 'mhm_rentiva_' . static::get_shortcode_tag() . '_nonce';
		}

		return wp_verify_nonce($nonce, $action);
	}

	/**
	 * AJAX response helper - Success
	 */
	protected static function ajax_success(array $data = array(), string $message = ''): void
	{
		$response = $data;

		if (! empty($message)) {
			$response['message'] = $message;
		}

		wp_send_json_success($response);
	}

	/**
	 * AJAX response helper - Error
	 */
	protected static function ajax_error(string $message = '', array $data = array()): void
	{
		$response = $data;

		if (! empty($message)) {
			$response['message'] = $message;
		}

		wp_send_json_error($response);
	}

	/**
	 * Form data sanitization helper
	 */
	protected static function sanitize_form_data(array $data): array
	{
		$sanitized = array();

		foreach ($data as $key => $value) {
			// Move null check to the beginning
			if ($value === null) {
				$sanitized[$key] = '';
				continue;
			}

			if (is_array($value)) {
				$sanitized[$key] = static::sanitize_form_data($value);
			} elseif (is_email($value ?: '')) {
				$sanitized[$key] = sanitize_email((string) ($value ?: ''));
			} elseif (is_numeric($value)) {
				$sanitized[$key] = is_float($value) ? floatval($value) : intval($value);
			} elseif (is_string($value)) {
				$sanitized[$key] = sanitize_text_field((string) $value);
			} else {
				$sanitized[$key] = $value;
			}
		}

		return $sanitized;
	}

	/**
	 * Debug helper
	 */
	protected static function debug_log(string $message, array $data = array()): void
	{
		if (! static::is_debug_enabled()) {
			return;
		}

		$log_message = '[' . static::get_shortcode_tag() . '] ' . $message;

		if (! empty($data)) {
			$log_message .= ' | Data: ' . wp_json_encode($data);
		}
	}

	/**
	 * Checks if debug is enabled
	 */
	protected static function is_debug_enabled(): bool
	{
		return defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;
	}

	/**
	 * Shortcode usage statistics
	 */
	public static function get_usage_stats(): array
	{
		global $wpdb;

		$shortcode_tag = static::get_shortcode_tag();

		// Count shortcode usage in post content
		$usage_count = $wpdb->get_var(
			$wpdb->prepare(
				"
            SELECT COUNT(*)
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
            AND (post_content LIKE %s OR post_excerpt LIKE %s)
        ",
				'%[' . $shortcode_tag . '%',
				'%[' . $shortcode_tag . '%'
			)
		);

		return array(
			'shortcode_tag' => $shortcode_tag,
			'usage_count'   => (int) $usage_count,
			'template_path' => static::get_template_path(),
			'css_files'     => array_filter(static::get_css_files(), array(static::class, 'asset_exists')),
			'js_files'      => array_filter(static::get_js_files(), array(static::class, 'asset_exists')),
			'cache_enabled' => static::is_caching_enabled(),
			'cache_ttl'     => static::get_cache_ttl(),
		);
	}

	/**
	 * Shortcode cleanup (cache invalidation)
	 */
	public static function cleanup(): void
	{
		$tag = static::get_shortcode_tag();

		// Invalidate all caches for this shortcode by deleting the version key
		// This is the standard WP way to mass-invalidate transients without direct SQL
		if (static::is_caching_enabled()) {
			delete_transient('mhm_rv_cache_v_' . $tag);
		}

		// Cache tracking cleanup
		unset(self::$shortcode_cache[$tag]);
		unset(self::$enqueued_assets[$tag]);
	}
}
