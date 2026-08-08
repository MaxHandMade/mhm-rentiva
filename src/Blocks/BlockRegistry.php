<?php
declare(strict_types=1);

namespace MHMRentiva\Blocks;

use MHMRentiva\Admin\Core\AssetManager;
use MHMRentiva\Helpers\Html;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Centralized Block Registry for MHM Rentiva
 *
 * Manages registration and rendering of all Gutenberg blocks.
 * Blocks are mapped to corresponding shortcodes to ensure consistency.
 *
 * @package MHMRentiva\Blocks
 * @since 4.6.6
 */
class BlockRegistry {











	/**
	 * Runtime cache for resolved asset versions.
	 *
	 * @var array<string,string>
	 */
	private static array $asset_version_cache = array();

	/**
	 * List of blocks to register
	 *
	 * Key: block-slug (used in assets/blocks/slug/)
	 * Value: Configuration array
	 */
	private static array $blocks = array(
		'unified-search'        => array(
			'tag'   => 'rentiva_unified_search',
			'title' => 'Unified Search Widget',
			'css'   => array( 'unified-search.css', 'datepicker-custom.css' ),
		),
		'search-results'        => array(
			'tag'   => 'rentiva_search_results',
			'title' => 'Search Results',
			'css'   => 'search-results.css',
			'deps'  => array( 'mhm-rentiva-vehicle-card-css' ),
		),
		'vehicle-comparison'    => array(
			'tag'   => 'rentiva_vehicle_comparison',
			'title' => 'Vehicle Comparison',
			'css'   => 'vehicle-comparison.css',
			'deps'  => array( 'mhm-rentiva-vehicle-card-css' ),
		),
		'testimonials'          => array(
			'tag'   => 'rentiva_testimonials',
			'title' => 'Testimonials',
			'css'   => 'testimonials.css',
		),
		'availability-calendar' => array(
			'tag'   => 'rentiva_availability_calendar',
			'title' => 'Availability Calendar',
			'css'   => array( 'availability-calendar.css', 'datepicker-custom.css' ),
		),
		'vehicle-details'       => array(
			'tag'   => 'rentiva_vehicle_details',
			'title' => 'Vehicle Details',
			'css'   => 'vehicle-details.css',
		),
		'vehicles-grid'         => array(
			'tag'   => 'rentiva_vehicles_grid',
			'title' => 'Vehicles Grid',
			'css'   => 'vehicles-grid.css',
			'deps'  => array( 'mhm-rentiva-vehicle-card-css' ),
		),
		'vehicles-list'         => array(
			'tag'   => 'rentiva_vehicles_list',
			'title' => 'Vehicles List',
			'css'   => 'vehicles-list.css',
			'deps'  => array( 'mhm-rentiva-vehicle-card-css' ),
		),
		'featured-vehicles'     => array(
			'tag'   => 'rentiva_featured_vehicles',
			'title' => 'Featured Vehicles',
			'css'   => 'featured-vehicles.css',
			'deps'  => array( 'mhm-rentiva-vehicle-card-css' ),
		),
		'contact'               => array(
			'tag'   => 'rentiva_contact',
			'title' => 'Contact Form',
			'css'   => 'contact-form.css',
		),
		'vehicle-rating-form'   => array(
			'tag'   => 'rentiva_vehicle_rating_form',
			'title' => 'Vehicle Rating Form',
			'css'   => 'vehicle-rating-form.css',
		),
		'my-bookings'           => array(
			'tag'   => 'rentiva_my_bookings',
			'title' => 'My Bookings',
			'css'   => 'my-account.css',
		),
		'my-favorites'          => array(
			'tag'   => 'rentiva_my_favorites',
			'title' => 'My Favorites',
			'css'   => 'my-account.css',
			'deps'  => array( 'mhm-rentiva-vehicle-card-css' ),
		),
		'payment-history'       => array(
			'tag'   => 'rentiva_payment_history',
			'title' => 'Payment History',
			'css'   => 'my-account.css',
		),
		'booking-form'          => array(
			'tag'   => 'rentiva_booking_form',
			'title' => 'Booking Form',
			'css'   => array( 'booking-form.css', 'datepicker-custom.css' ),
		),

		'user-dashboard'        => array(
			'tag'   => 'rentiva_user_dashboard',
			'title' => 'User Dashboard',
			'css'   => 'user-dashboard.css',
		),
	);

	/**
	 * Initialize the registry
	 *
	 * @return void
	 */
	public static function init(): void
	{
		add_action('init', array( self::class, 'register_blocks' ));

		// Assets that should load in both frontend and editor (iframe)
		add_action('enqueue_block_assets', array( self::class, 'enqueue_block_assets' ));

		// Assets for the block editor shell (UI, sidebar, etc.)
		add_action('enqueue_block_editor_assets', array( self::class, 'enqueue_editor_assets' ));

		// Bug Fix 1: Add type="module" to search block script
		add_filter('script_loader_tag', array( self::class, 'add_module_type_to_search_block' ), 10, 3);
	}

	/**
	 * Enqueue assets for both frontend and editor iframe
	 */
	public static function enqueue_block_assets(): void
	{
		// Ensure core variables are available inside the editor iframe AND frontend
		wp_enqueue_style('mhm-rentiva-css-variables');
		wp_enqueue_style(
			'mhm-rentiva-golden-ratio-contract',
			MHMRENTIVA_PLUGIN_URL . 'assets/css/core/golden-ratio-contract.css',
			array( 'mhm-rentiva-css-variables' ),
			self::get_asset_version('assets/css/core/golden-ratio-contract.css')
		);

		// Note: 'mhm-rentiva-datepicker-custom' and 'mhm-vehicle-card-css' are now loaded
		// conditionally by the specific blocks/shortcodes that need them.

		// Apply editor styles for better iframe coverage
		if (is_admin()) {
			add_editor_style(MHMRENTIVA_PLUGIN_URL . 'assets/css/core/css-variables.css');
			add_editor_style(MHMRENTIVA_PLUGIN_URL . 'assets/css/core/golden-ratio-contract.css');
			// We might still want datepicker styles in the editor for UX if they use it
			add_editor_style(MHMRENTIVA_PLUGIN_URL . 'assets/css/frontend/datepicker-custom.css');
		}
	}

	/**
	 * Enqueue block editor specific assets (Shell/UI)
	 */
	public static function enqueue_editor_assets(): void
	{
		// Silence JQMIGRATE & React DevTools warnings
		// Ensure dependencies are enqueued first
		wp_enqueue_script('jquery-migrate');
		wp_add_inline_script('jquery-migrate', 'window.jQuery.migrateMute = true;', 'before');

		wp_enqueue_script('wp-edit-post');
		wp_add_inline_script('wp-edit-post', 'window.__REACT_DEVTOOLS_GLOBAL_HOOK__ = { isDisabled: true };', 'before');

		// This will load in BOTH the parent and the iframe if registered correctly
		wp_enqueue_style(
			'mhm-rentiva-block-editor-fixes',
			MHMRENTIVA_PLUGIN_URL . 'assets/css/editor/block-editor-fixes.css',
			array(),
			self::get_asset_version('assets/css/editor/block-editor-fixes.css')
		);

		wp_enqueue_style(
			'mhm-rentiva-datepicker-custom-editor',
			MHMRENTIVA_PLUGIN_URL . 'assets/css/frontend/datepicker-custom.css',
			array(),
			MHMRENTIVA_VERSION
		);

		wp_enqueue_script(
			'mhm-rentiva-block-editor-fixes-js',
			MHMRENTIVA_PLUGIN_URL . 'assets/js/editor/block-editor-fixes.js',
			array( 'jquery', 'jquery-ui-datepicker', 'wp-blocks', 'wp-element', 'wp-data', 'wp-editor' ),
			self::get_asset_version('assets/js/editor/block-editor-fixes.js'),
			true
		);

		// Localize for the editor fixes
		wp_localize_script(
			'mhm-rentiva-block-editor-fixes-js',
			'mhmRentivaSearch',
			array(
				'ajax_url'           => admin_url('admin-ajax.php'),
				'datepicker_options' => self::get_datepicker_localization(),
				'global_settings'    => \MHMRentiva\Admin\Settings\Core\SettingsCore::get_all(),
			)
		);
	}

	/**
	 * Get datepicker localization data (Duplicated from VehicleSearch for editor access)
	 */
	private static function get_datepicker_localization(): array
	{
		return array(
			'dateFormat'      => 'yy-mm-dd', // Basic fallback
			'minDate'         => 0,
			'showButtonPanel' => true,
			'closeText'       => __('Close', 'mhm-rentiva'),
			'currentText'     => __('Today', 'mhm-rentiva'),
			'monthNames'      => array( __('January', 'mhm-rentiva'), __('February', 'mhm-rentiva'), __('March', 'mhm-rentiva'), __('April', 'mhm-rentiva'), __('May', 'mhm-rentiva'), __('June', 'mhm-rentiva'), __('July', 'mhm-rentiva'), __('August', 'mhm-rentiva'), __('September', 'mhm-rentiva'), __('October', 'mhm-rentiva'), __('November', 'mhm-rentiva'), __('December', 'mhm-rentiva') ),
			'dayNamesMin'     => array( __('Su', 'mhm-rentiva'), __('Mo', 'mhm-rentiva'), __('Tu', 'mhm-rentiva'), __('We', 'mhm-rentiva'), __('Th', 'mhm-rentiva'), __('Fr', 'mhm-rentiva'), __('Sa', 'mhm-rentiva') ),
		);
	}

	/**
	 * Add type="module" to MHM Rentiva block scripts
	 *
	 * @param string $tag Script tag HTML.
	 * @param string $handle Script handle.
	 * @param string $src Script source URL.
	 * @return string Modified script tag.
	 */
	public static function add_module_type_to_search_block(string $tag, string $handle, string $src): string // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	{
		// NO-OP: We have refactored blocks to use global `wp` variables (IIFE) instead of ES modules.
		// This avoids "Failed to resolve module specifier" errors in environments without a build step.
		return $tag;
	}

	/**
	 * The full block configuration, open for extension.
	 *
	 * Lite ships its own blocks only. The add-on (or any other consumer)
	 * contributes its blocks by hooking `mhmrentiva_blocks` -- Lite carries no
	 * knowledge of the add-on's block slugs, classes, or feature names. A
	 * contributor is responsible for its own presence/registration gating
	 * inside its filter callback: the filter simply returns whatever the
	 * callback decides to admit.
	 *
	 * An entry may carry `base_url`/`base_dir` when its `assets/blocks/<slug>/`
	 * files (block.json, editor script) live outside this plugin; both default to
	 * this plugin's own constants below so Lite's own blocks need not specify them.
	 *
	 * @return array<string, array<string, mixed>> Slug => block config.
	 */
	private static function get_block_config(): array
	{
		return (array) apply_filters( 'mhmrentiva_blocks', self::$blocks );
	}

	/**
	 * Fully-qualified names of every block this plugin registers -- including
	 * any contributed via the `mhmrentiva_blocks` filter (e.g. the Pro add-on).
	 *
	 * Single source of truth for "is this block one of ours": callers outside
	 * this class (AssetManager::should_load_assets(), via
	 * content_has_registered_block() below) MUST read this instead of keeping
	 * a second copy of the slug list, which would silently drift as blocks
	 * are added to or removed from $blocks/the filter.
	 *
	 * @return string[] e.g. array('mhm-rentiva/vehicles-grid', ...).
	 */
	public static function get_block_names(): array
	{
		$names = array();
		foreach (array_keys(self::get_block_config()) as $slug) {
			$names[] = 'mhm-rentiva/' . $slug;
		}
		return $names;
	}

	/**
	 * Whether raw block content references at least one of this plugin's own
	 * registered blocks, anywhere in the block tree: top level, nested inside
	 * a wrapper block (e.g. Group/Columns), or one level inside a reusable
	 * block / synced pattern (`core/block` ref).
	 *
	 * Written for AssetManager::should_load_assets(), which used to grep
	 * post_content for the literal '[rentiva_' shortcode tag only -- block
	 * markup (`<!-- wp:mhm-rentiva/vehicles-grid /-->`) never contains that
	 * substring, so a page built purely with this plugin's Gutenberg blocks
	 * (no shortcode anywhere on the page) never enqueued
	 * 'mhm-rentiva-vehicle-interactions': favourite/compare buttons rendered
	 * with correct server-side state and a clean console, but did nothing on
	 * click.
	 *
	 * Goes a step further than core's own has_block(), which explicitly does
	 * NOT check synced patterns/reusable blocks (see its docblock) -- this
	 * resolves one level of `core/block` ref by fetching the referenced
	 * wp_block post's own content, bounded by $depth so a pattern that (validly
	 * or via a cyclical reference) nests deeper simply stops being detected
	 * rather than recursing unboundedly.
	 *
	 * What this does NOT resolve: `core/template-part`. Template parts are a
	 * block-theme (FSE) concept; walking them needs the block-template
	 * machinery (get_block_templates()), a materially bigger surface for a
	 * theme mode this plugin's documented/supported theme (Astra, classic)
	 * does not use today. A site that needs it can still force loading via
	 * the existing `mhmrentiva_force_load_assets` filter.
	 *
	 * @param string $content Raw post_content (or a referenced reusable-block
	 *                        post's own content, when called recursively).
	 * @param int    $depth   Recursion guard for core/block refs -- external
	 *                        callers always omit this and get 0.
	 * @return bool
	 */
	public static function content_has_registered_block(string $content, int $depth = 0): bool
	{
		// has_blocks() also accepts a raw content string directly (not just a
		// post/ID) and is a cheap substring probe ('<!-- wp:') before paying
		// for a full parse_blocks() -- the overwhelming majority of requests
		// (any page without ANY block on it at all) bail out right here.
		if ($depth > 3 || ! has_blocks($content)) {
			return false;
		}

		$names = array_flip(self::get_block_names());

		return self::block_list_has_registered_block(parse_blocks($content), $names, $depth);
	}

	/**
	 * Recursive walk of a parse_blocks() tree (or an innerBlocks subtree).
	 *
	 * @param array<int,array<string,mixed>> $blocks parse_blocks() output.
	 * @param array<string,int>              $names  Flipped get_block_names(), for O(1) lookup.
	 * @param int                             $depth  Current core/block recursion depth.
	 */
	private static function block_list_has_registered_block(array $blocks, array $names, int $depth): bool
	{
		foreach ($blocks as $block) {
			$name = (string) ( $block['blockName'] ?? '' );

			if ('' !== $name && isset($names[ $name ])) {
				return true;
			}

			// Reusable block / synced pattern: the markup lives in the
			// referenced wp_block post's OWN content, not in innerBlocks here.
			if ('core/block' === $name && ! empty($block['attrs']['ref'])) {
				$referenced = get_post( (int) $block['attrs']['ref']);
				if ($referenced instanceof \WP_Post
					&& self::content_has_registered_block( (string) $referenced->post_content, $depth + 1)
				) {
					return true;
				}
			}

			if (! empty($block['innerBlocks'])
				&& self::block_list_has_registered_block($block['innerBlocks'], $names, $depth)
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Register all defined blocks with their dependencies
	 *
	 * @return void
	 */
	public static function register_blocks(): void
	{
		// Core styles are owned by AssetManager::register_core_styles(), which
		// is the single registration point for the shared handles.
		//
		// This used to re-register `mhm-css-variables` and `mhm-core-css`
		// here with EMPTY deps. Because this method runs on `init` -- long
		// before wp_enqueue_scripts -- that registration won the race and
		// silently stripped the `mhm-rentiva-fonts` dependency, so the
		// bundled webfont never loaded on the front end. Calling the owner
		// directly (it is idempotent) keeps the correct deps no matter which
		// `init` callback happens to run first.
		AssetManager::register_core_styles();

		foreach (self::get_block_config() as $slug => $config) {
			// A contributor's `assets/blocks/<slug>/` (block.json, editor script)
			// AND its block-level CSS (below) may live outside this plugin -- e.g.
			// the add-on's own blocks ship from the add-on. Default to Lite's own
			// constants so Lite's blocks are unaffected. Block-level CSS used to
			// resolve from THIS plugin's URL regardless of base_url, on the theory
			// that a contributor's stylesheets stayed Lite-hosted; that stopped
			// being true once the add-on's own CSS carve-out moved those files out of
			// Lite, so CSS now honours base_url exactly like the editor script does.
			$base_url = $config['base_url'] ?? MHMRENTIVA_PLUGIN_URL;
			$base_dir = $config['base_dir'] ?? MHMRENTIVA_PLUGIN_DIR;

			$script_handle = 'mhm-rentiva-block-' . $slug . '-editor';

			// 1. Register Editor Script (Shared requirements)
			wp_register_script(
				$script_handle,
				$base_url . 'assets/blocks/' . $slug . '/index.js',
				array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-server-side-render', 'wp-block-editor' ),
				self::get_asset_version('assets/blocks/' . $slug . '/index.js', $base_dir),
				true
			);

			// 2. Register Block Styles (Support String or Array)
			$style_handles = array();
			$css_files     = is_array($config['css']) ? $config['css'] : array( $config['css'] );

			foreach ($css_files as $index => $css_file) {
				// SHARED ASSET CHECK: Use consistent handles for common files to prevent duplicates
				if ($css_file === 'datepicker-custom.css') {
					$style_handle = 'mhm-rentiva-datepicker-custom';
				} elseif ($css_file === 'vehicle-card.css') {
					$style_handle = 'mhm-vehicle-card-css';
				} elseif (isset($config['tag']) && $index === 0) {
					// Use Shortcode Tag driven handle if available to ensure parity with AbstractShortcode
					$style_handle = 'mhm-' . str_replace('_', '-', $config['tag']);
				} else {
					// Fallback to block slug
					$style_handle = ( count($css_files) === 1 )
						? 'mhm-rentiva-block-' . $slug . '-style'
						: 'mhm-rentiva-block-' . $slug . '-style-' . $index;
				}

				// Merge dependencies: Core variables + Specific block deps
				$deps = array_merge(array( 'mhm-rentiva-css-variables' ), $config['deps'] ?? array());

				// Only register if not already registered (especially for shared handles)
				if (! wp_style_is($style_handle, 'registered')) {
					$path = ( strpos($css_file, 'assets/') === 0 ) ? $css_file : 'assets/css/frontend/' . $css_file;
					wp_register_style(
						$style_handle,
						$base_url . $path,
						$deps,
						self::get_asset_version($path, $base_dir)
					);
				}

				$style_handles[] = $style_handle;
			}

			// 3. Register Block Type via block.json
			register_block_type(
				$base_dir . 'assets/blocks/' . $slug . '/block.json',
				array(
					'render_callback' => array( self::class, 'render_callback' ),
					'editor_script'   => $script_handle,
					'style'           => $style_handles,
				)
			);
		}
	}

	/**
	 * Master Render Callback for all MHM Rentiva dynamic blocks
	 *
	 * This method automatically maps the block attributes to the corresponding shortcode,
	 * ensuring that Gutenberg blocks and shortcodes always share the same logic.
	 *
	 * CAM normalization contract in this path:
	 * - Input attributes may arrive as camelCase (editor controls) or alias keys.
	 * - CanonicalAttributeMapper resolves aliases via AllowlistRegistry + KeyNormalizer.
	 * - Result is canonical snake_case attribute keys for shortcode execution.
	 *
	 * This keeps block rendering and shortcode rendering semantically identical.
	 *
	 * @param array $attributes Block attributes from editor.
	 * @param string $content Inner block content (if any).
	 * @param \WP_Block $block The block instance.
	 * @return string Rendered HTML.
	 */
	public static function render_callback(array $attributes, string $content, \WP_Block $block): string
	{
		// Extract the slug from the block name (e.g., mhm-rentiva/search -> search)
		$slug = str_replace('mhm-rentiva/', '', $block->name);

		// Resolve through the FILTERED map, not self::$blocks directly: an
		// add-on-contributed block (e.g. transfer-results) has no entry in Lite's
		// own array any more, only in the `mhmrentiva_blocks` filter result.
		// Reading self::$blocks here would make every add-on block render empty.
		$blocks = self::get_block_config();

		if (! isset($blocks[ $slug ])) {
			return '';
		}

		$config = $blocks[ $slug ];

		// EXPLICIT FRONTEND ENQUEUE: Declaration of block-level CSS dependencies
		// This ensures visual parity on the frontend for dynamic blocks which sometimes
		// fail to trigger automatic enqueueing in deep template structures.
		if (isset($config['css'])) {
			$css_files = (array) $config['css'];
			foreach ($css_files as $index => $css_file) {
				// Calculate handle based on the same logic as init()
				if ($css_file === 'datepicker-custom.css') {
					$style_handle = 'mhm-rentiva-datepicker-custom';
				} elseif ($css_file === 'vehicle-card.css') {
					$style_handle = 'mhm-vehicle-card-css';
				} elseif (isset($config['tag']) && $index === 0) {
					// Use Shortcode Tag driven handle if available to ensure parity with AbstractShortcode
					$style_handle = 'mhm-' . str_replace('_', '-', $config['tag']);
				} else {
					// Fallback to block slug
					$style_handle = ( count($css_files) === 1 )
						? 'mhm-rentiva-block-' . $slug . '-style'
						: 'mhm-rentiva-block-' . $slug . '-style-' . $index;
				}

				wp_enqueue_style($style_handle);
			}
		}

		// Additional dependency enqueueing from the 'deps' config
		if (isset($config['deps'])) {
			foreach ( (array) $config['deps'] as $dep_handle) {
				wp_enqueue_style($dep_handle);
			}
		}

		$tag       = $config['tag'];
		$min_width = ! empty($attributes['minWidth'])
			? self::sanitize_css_dimension($attributes['minWidth'])
			: '';
		$max_width = ! empty($attributes['maxWidth'])
			? self::sanitize_css_dimension($attributes['maxWidth'])
			: '';
		$height    = ! empty($attributes['height'])
			? self::sanitize_css_dimension($attributes['height'])
			: '';

		// Guard against double mapping (especially when called recursively or via blocks-in-shortcodes)
		if (! empty($attributes['_canonical'])) {
			$mapped_attributes = $attributes;
		} else {
			// 1. Extract Wrapper Logic (Dimensions) before CAM drops unknown attributes
			$style_parts = array();
			if ('' !== $min_width) {
				$style_parts[] = "min-width:$min_width";
			}
			if ('' !== $max_width) {
				$style_parts[] = "max-width:$max_width";
			}
			if ('' !== $height) {
				$style_parts[] = "height:$height";
			}

			if (! empty($style_parts)) {
				$attributes['style'] = implode(';', $style_parts) . ';';
			}

			// 2. Canonical Attribute Mapping (Registry + KeyNormalizer driven)
			//    This is the one-way normalization step from block payload to
			//    shortcode canonical attributes used by downstream rendering.
			$mapped_attributes               = \MHMRentiva\Core\Attribute\CanonicalAttributeMapper::map($tag, $attributes);
			$mapped_attributes['_canonical'] = true;
		}

		// Ensure Search Results runtime JS/localization is always present for block instances.
		if ($tag === 'rentiva_search_results' && class_exists(\MHMRentiva\Admin\Frontend\Shortcodes\SearchResults::class)) {
			\MHMRentiva\Admin\Frontend\Shortcodes\SearchResults::ensure_runtime_assets($mapped_attributes);
		}

		$shortcode_attrs_string = self::attributes_to_string($mapped_attributes);

		$shortcode_content = do_shortcode('[' . $tag . ' ' . $shortcode_attrs_string . ']');

		// Prepare wrapper attributes to ensure dimensions are applied to the container
		$wrapper_args   = array();
		$wrapper_styles = array();

		if ('' !== $max_width) {
			$wrapper_styles[] = "max-width:$max_width";
			$wrapper_styles[] = 'width:100%';
			$wrapper_styles[] = 'margin-left:auto';
			$wrapper_styles[] = 'margin-right:auto';
		}

		if ('' !== $min_width) {
			$wrapper_styles[] = "min-width:$min_width";
		}

		if ('' !== $height) {
			$wrapper_styles[] = "height:$height";
		}

		if (! empty($wrapper_styles)) {
			$wrapper_args['style'] = implode(';', $wrapper_styles);
		}

		// get_block_wrapper_attributes() is WP core and esc_attr()'s its own
		// output already, so only the content needs wrapping here. The
		// add_filter/remove_filter pair around this wp_kses() call widens
		// wp_kses()'s CSS-property filter for the few inline `style` values the
		// allowlist alone can't cover — see Html::allow_inline_style_props()'s
		// docblock for why, and for why this is inlined here rather than
		// called through Html::kses().
		add_filter('safe_style_css', array( Html::class, 'allow_inline_style_props' ));
		try {
			$escaped_content = wp_kses($shortcode_content, Html::allowed_markup());
		} finally {
			remove_filter('safe_style_css', array( Html::class, 'allow_inline_style_props' ));
		}

		return sprintf(
			'<div %s>%s</div>',
			get_block_wrapper_attributes($wrapper_args),
			$escaped_content
		);
	}


	/**
	 * Convert attributes array to shortcode string
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	private static function attributes_to_string(array $attributes): string
	{
		$out = '';
		foreach ($attributes as $key => $value) {
			if (is_bool($value)) {
				$value = $value ? '1' : '0';
			}
			if (is_scalar($value)) {
				$out .= sprintf('%s="%s" ', esc_attr($key), esc_attr( (string) $value));
			}
		}
		return trim($out);
	}

	/**
	 * Normalize an untrusted block dimension to a single safe CSS value.
	 *
	 * Unitless numbers keep the historical pixels behavior. Common CSS length
	 * units and intrinsic sizing keywords pass through; functions, declaration
	 * separators, and other CSS syntax are rejected rather than escaped.
	 *
	 * @param mixed $value Raw block attribute value.
	 * @return string Safe CSS dimension, or an empty string when invalid.
	 */
	private static function sanitize_css_dimension($value): string
	{
		if (! is_scalar($value)) {
			return '';
		}

		$value = strtolower(trim( (string) $value));
		if (preg_match('/\A(?:\d+(?:\.\d+)?|\.\d+)\z/', $value)) {
			return $value . 'px';
		}

		$number = '(?:\d+(?:\.\d+)?|\.\d+)';
		$unit   = '(?:%|px|em|rem|vw|vh|vmin|vmax|svw|svh|lvw|lvh|dvw|dvh|ch|ex|cm|mm|q|in|pt|pc)';
		if (preg_match('/\A(?:' . $number . $unit . '|auto|min-content|max-content|fit-content|none)\z/', $value)) {
			return $value;
		}

		return '';
	}

	/**
	 * Resolve stable file version for asset URLs.
	 *
	 * @param string      $relative_path Asset path relative to $base_dir.
	 * @param string|null $base_dir      Root the path is relative to. Defaults to
	 *                                   this plugin's own dir; a contributor whose
	 *                                   assets live elsewhere (e.g. an add-on) passes its
	 *                                   own base_dir so filemtime() checks the file
	 *                                   that actually exists.
	 */
	private static function get_asset_version(string $relative_path, ?string $base_dir = null): string
	{
		$base_dir  = $base_dir ?? MHMRENTIVA_PLUGIN_DIR;
		$cache_key = $base_dir . $relative_path;

		if (isset(self::$asset_version_cache[ $cache_key ])) {
			return self::$asset_version_cache[ $cache_key ];
		}

		$full_path = $base_dir . $relative_path;
		if (file_exists($full_path)) {
			$filemtime = filemtime($full_path);
			if (false !== $filemtime) {
				$version                                 = (string) $filemtime;
				self::$asset_version_cache[ $cache_key ] = $version;
				return $version;
			}
		}

		self::$asset_version_cache[ $cache_key ] = MHMRENTIVA_VERSION;
		return MHMRENTIVA_VERSION;
	}
}
