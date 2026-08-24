<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Core;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded application queries are intentional in this module.




use MHMRentiva\Admin\Core\Utilities\Templates;
use Exception;



/**
 * Enhanced Abstract Shortcode Base Class
 *
 * Enhanced common structure and methods for all shortcode classes.
 * Prevents code repetition, improves performance and ensures consistency.
 */
abstract class AbstractShortcode {

	/**
	 * Prefix every public query var this plugin puts on WordPress's global
	 * `query_vars` whitelist must carry.
	 *
	 * `query_vars` is a site-wide namespace shared with core and every other
	 * plugin, so a bare `sort`/`brand`/`seats` registered there is a collision
	 * waiting to happen (and a WP.org submission finding). The prefix keeps the
	 * plugin's names in its own corner of that namespace.
	 */
	public const QUERY_VAR_PREFIX = 'mhmrentiva_';

	/**
	 * Maps a logical filter key to the name it carries on the wire.
	 *
	 * This is the single mapping point between the readable keys used inside the
	 * code (`min_price`, `pickup_date`, ...) and the prefixed names that appear
	 * in URLs, in `name=` attributes and on the `query_vars` whitelist. Both the
	 * emission side (templates) and the read side (get_text()/get_int()/...) go
	 * through it, so the two cannot drift apart.
	 *
	 * WordPress core's own public query vars -- `page` above all -- are NOT
	 * passed through here: they belong to core, are already registered, and must
	 * stay unprefixed.
	 *
	 * @param string $logical Logical filter key, e.g. 'min_price'.
	 * @return string On-the-wire query-var name, e.g. 'mhmrentiva_min_price'.
	 */
	public static function query_var(string $logical): string
	{
		return self::QUERY_VAR_PREFIX . $logical;
	}

	// Shortcode cache
	private static array $shortcode_cache = array();

	// Asset enqueue tracking
	private static array $enqueued_assets = array();

	/**
	 * Reset asset enqueue tracking.
	 *
	 * FOR TESTING ONLY — clears the per-tag enqueue cache so tests can isolate renders.
	 *
	 * @internal
	 */
	public static function reset_enqueued_assets_for_tests(): void
	{
		self::$enqueued_assets = array();
	}

	/**
	 * Clears the register()-once cache. Test seam.
	 *
	 * Registration is idempotent by design: the first register() call per tag sets
	 * $shortcode_cache and every later call returns early. That is correct at
	 * runtime (one boot, one registration) but it makes register() untestable
	 * more than once per PHP process -- and WP_UnitTestCase rolls $wp_filter back
	 * between tests, so the hooks the first call added are GONE while the cache
	 * still says "already registered". A test asserting register()'s side effects
	 * then sees nothing.
	 *
	 * That trap was hidden while unregistered seams leaked: the boot registered
	 * them for everyone, so the hooks sat in WP_UnitTestCase's baseline and
	 * survived every rollback. Closing the leak exposed it.
	 */
	public static function reset_shortcode_cache_for_tests(): void
	{
		self::$shortcode_cache = array();
	}

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
		if (isset(self::$shortcode_cache[ $tag ])) {
			return;
		}

		// NOTE: add_shortcode is handled by ShortcodeServiceProvider centrally.
		// This method only handles class-internal hooks and AJAX handlers.
		self::$shortcode_cache[ $tag ] = true;
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
			$tag = static::get_shortcode_tag();

			// 1. Attribute normalization
			// Canonical payloads come from BlockRegistry/CAM and must not be truncated by shortcode_atts.
			if (empty($atts['_canonical'])) {
				$atts = shortcode_atts(static::get_default_attributes(), $atts, $tag);
				$atts = \MHMRentiva\Core\Attribute\CanonicalAttributeMapper::map($tag, $atts);
			}

			// Performance: Cache check
			$cache_key   = static::get_cache_key($atts);
			$cached_html = static::get_cached_html($cache_key);
			if ($cached_html) {
				return $cached_html;
			}

			// Load assets (only once)
			static::enqueue_assets_once($atts);

			// Prepare template data
			$template_data = static::prepare_template_data($atts);

			// Internal guard should not leak into template layer.
			unset($template_data['atts']['_canonical']);

			// Render template
			$html = static::render_template($template_data);

			// Empty HTML check
			if (empty($html)) {
				$html = static::get_fallback_html($atts);
			}

			// Core WordPress WPAutoP protection:
			// Strip newlines and tabs from the final shortcode output to prevent
			// wpautop() from injecting <br> or <p> tags into grid/flex layouts when
			// the block editor evaluates patterns.
			// CRITICAL: This MUST happen BEFORE caching, so that the cached HTML
			// is already clean. Otherwise, cache hits bypass this stripping.
			$html = str_replace(array( "\r\n", "\r", "\n", "\t" ), '', $html);

			// Performance: Cache HTML (now stores the already-minified output)
			static::cache_html($cache_key, $html);

			// Filter hook
			// Both halves move together. The sweep could only reach the literal in
			// hook-call position; this trailing fragment is a separate string and
			// would have left the name half-converted --
			// 'mhmrentiva_shortcodes_rentiva_vehicles/html' -- which still carries
			// the slash the whole change exists to remove, and would have looked
			// renamed at a glance.
			$html = apply_filters('mhmrentiva_shortcodes_' . $tag . '_html', $html, $atts, $content);

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
	protected static function enqueue_assets_once(array $atts = array()): void
	{
		$tag = static::get_shortcode_tag();

		if (isset(self::$enqueued_assets[ $tag ])) {
			return;
		}

		// Conditional Asset Loading check
		if (static::should_load_assets_conditionally()) {
			// Enqueue Global Notifications System (Must be here to ensure it loads even if child overrides enqueue_assets)
			wp_enqueue_style(
				'mhm-rentiva-notifications',
				MHMRENTIVA_PLUGIN_URL . 'assets/css/frontend/notifications.css',
				array(),
				MHMRENTIVA_VERSION . '.toast2'
			);

			static::enqueue_assets($atts);
		}

		self::$enqueued_assets[ $tag ] = true;
	}

	/**
	 * Conditional Asset Loading check
	 */
	protected static function should_load_assets_conditionally(): bool
	{
		// Default to true (optimize performance) but allow easy disabling via filter
		return apply_filters('mhmrentiva_shortcode_load_assets_conditionally', true, static::get_shortcode_tag());
	}

	/**
	 * Loads CSS and JS files
	 * Can be overridden
	 *
	 * @param array $atts Shortcode attributes
	 */
	protected static function enqueue_assets(array $atts = array()): void
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
	protected static function enqueue_styles(array $atts = array()): void
	{
		$base_handle = static::get_asset_handle();
		$css_files   = static::get_css_files($atts);

		$i = 0;
		foreach ($css_files as $handle => $css_file) {
			if (static::asset_exists($css_file)) {
				// Use key as handle if it's a string, otherwise generate one
				$final_handle = is_string($handle) ? $handle : ( ( count($css_files) === 1 ) ? $base_handle : $base_handle . '-' . $i );

				wp_enqueue_style(
					$final_handle,
					MHMRENTIVA_PLUGIN_URL . $css_file,
					static::get_css_dependencies(),
					static::get_asset_version($css_file)
				);
			}
			++$i;
		}
	}

	/**
	 * Loads JavaScript files
	 * Can be overridden
	 *
	 * @param array $atts Shortcode attributes
	 */
	protected static function enqueue_scripts(array $atts = array()): void
	{
		// Any shortcode that names vehicle-interactions.js in its dependencies is
		// rendering the favourite/compare buttons, so it needs that script's
		// mhmrentiva_vars payload -- which AssetManager::enqueue_frontend_assets()
		// only emits when should_load_assets() finds '[rentiva_' in
		// $post->post_content, i.e. never on an Elementor-built page (Elementor
		// keeps the content in the _elementor_data postmeta). Asking here, from
		// the shared enqueue path, covers FeaturedVehicles and VehiclesList and
		// anything that declares the dependency later, without a fifth hand-copied
		// call site to keep in sync. The two classes that override enqueue_assets()
		// outright -- VehiclesGrid and SearchResults -- never reach this method and
		// call AssetManager::enqueue_vehicle_interactions() themselves.
		//
		// Deliberately BEFORE the loop below, not inside it: FeaturedVehicles in
		// its default 'grid' layout returns no JS files at all, so nothing would
		// pull the dependency in, yet its cards still carry the buttons.
		if (in_array('mhm-rentiva-vehicle-interactions', static::get_js_dependencies(), true)) {
			\MHMRentiva\Admin\Core\AssetManager::enqueue_vehicle_interactions();
		}

		$base_handle = static::get_asset_handle();
		$js_files    = static::get_js_files($atts);

		$i = 0;
		foreach ($js_files as $handle => $js_file) {
			if (static::asset_exists($js_file)) {
				// Use key as handle if it's a string, otherwise generate one
				$final_handle = is_string($handle) ? $handle : ( ( count($js_files) === 1 ) ? $base_handle : $base_handle . '-' . $i );

				wp_enqueue_script(
					$final_handle,
					MHMRENTIVA_PLUGIN_URL . $js_file,
					static::get_js_dependencies(),
					static::get_asset_version($js_file),
					true
				);

				// Localize script (usually for the main/base script)
				if ($i === 0 || count($js_files) === 1) {
					static::localize_script($final_handle);
				}
			}
			++$i;
		}
	}

	/**
	 * Get file version based on file modification time
	 * Falls back to plugin version if file doesn't exist
	 *
	 * @param string $file_path Relative path to file (e.g., 'assets/js/frontend/file.js')
	 * @return string Version string
	 */
	protected static function get_asset_version(string $file_path): string
	{
		$full_path = MHMRENTIVA_PLUGIN_PATH . $file_path;
		if (file_exists($full_path)) {
			return (string) filemtime($full_path);
		}
		return defined('MHMRENTIVA_VERSION') ? (string) MHMRENTIVA_VERSION : '1.0.0';
	}

	/**
	 * Returns CSS files (can be overridden)
	 *
	 * @param array $atts Shortcode attributes
	 */
	protected static function get_css_files(array $atts = array()): array
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
	protected static function get_js_files(array $atts = array()): array
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
		return array( 'jquery', 'mhm-rentiva-toast' );
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
		// The tag already begins `rentiva_`, so only `mhm-` is added -- prefixing
		// with the full `mhm-rentiva-` would yield `mhm-rentiva-rentiva-…`.
		return 'mhm-' . str_replace('_', '-', static::get_shortcode_tag());
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
		$clean_tag = str_replace(array( 'rentiva_', '_' ), array( '', '' ), $tag);
		return 'mhmRentiva' . ucfirst($clean_tag);
	}

	/**
	 * Returns localized data
	 * Can be overridden
	 */
	protected static function get_localized_data(): array
	{
		return array(
			'ajaxUrl'  => admin_url('admin-ajax.php'),
			'ajax_url' => admin_url('admin-ajax.php'),
			'restUrl'  => rest_url('mhm-rentiva/v1/'),
			'nonce'    => wp_create_nonce('mhmrentiva_' . static::get_shortcode_tag() . '_nonce'),
			'strings'  => static::get_localized_strings(),
			'config'   => static::get_js_config(),
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
		$version = get_transient('mhmrentiva_rv_cache_v_' . $tag);
		if (false === $version) {
			$version = (string) time();
			set_transient('mhmrentiva_rv_cache_v_' . $tag, $version, DAY_IN_SECONDS * 30);
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

		return 'mhmrentiva_shc_' . substr(md5($tag), 0, 8) . '_' . md5(serialize($cache_factors));
	}

	/**
	 * Gets HTML from cache
	 */
	protected static function get_cached_html(string $cache_key): ?string
	{
		if (! static::is_caching_enabled()) {
			return null;
		}

		$result = get_transient($cache_key);
		return $result === false ? null : $result;
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
		$full_path = MHMRENTIVA_PLUGIN_PATH . $file_path;
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
			$action = 'mhmrentiva_' . static::get_shortcode_tag() . '_nonce';
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
				$sanitized[ $key ] = '';
				continue;
			}

			if (is_array($value)) {
				$sanitized[ $key ] = static::sanitize_form_data($value);
			} elseif (is_email($value ?: '')) {
				$sanitized[ $key ] = sanitize_email( (string) ( $value ?: '' ));
			} elseif (is_numeric($value)) {
				$sanitized[ $key ] = is_float($value) ? floatval($value) : intval($value);
			} elseif (is_string($value)) {
				$sanitized[ $key ] = sanitize_text_field( (string) $value);
			} else {
				$sanitized[ $key ] = $value;
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
			'css_files'     => array_filter(static::get_css_files(), array( static::class, 'asset_exists' )),
			'js_files'      => array_filter(static::get_js_files(), array( static::class, 'asset_exists' )),
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
			delete_transient('mhmrentiva_rv_cache_v_' . $tag);
		}

		// Cache tracking cleanup
		unset(self::$shortcode_cache[ $tag ]);
		unset(self::$enqueued_assets[ $tag ]);
	}
}
