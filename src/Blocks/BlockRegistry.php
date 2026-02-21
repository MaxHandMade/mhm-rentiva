<?php

declare(strict_types=1);

namespace MHMRentiva\Blocks;

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
			'deps'  => array( 'mhm-vehicle-card-css' ),
		),
		'transfer-results'      => array(
			'tag'   => 'rentiva_transfer_results',
			'title' => 'Transfer Search Results',
			'css'   => 'transfer-results.css',
			'deps'  => array( 'mhm-vehicle-card-css' ),
		),
		'vehicle-comparison'    => array(
			'tag'   => 'rentiva_vehicle_comparison',
			'title' => 'Vehicle Comparison',
			'css'   => 'vehicle-comparison.css',
			'deps'  => array( 'mhm-vehicle-card-css' ),
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
		'booking-confirmation'  => array(
			'tag'   => 'rentiva_booking_confirmation',
			'title' => 'Booking Confirmation',
			'css'   => 'booking-confirmation.css',
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
			'deps'  => array( 'mhm-vehicle-card-css' ),
		),
		'vehicles-list'         => array(
			'tag'   => 'rentiva_vehicles_list',
			'title' => 'Vehicles List',
			'css'   => 'vehicles-list.css',
			'deps'  => array( 'mhm-vehicle-card-css' ),
		),
		'featured-vehicles'     => array(
			'tag'   => 'rentiva_featured_vehicles',
			'title' => 'Featured Vehicles',
			'css'   => 'featured-vehicles.css',
			'deps'  => array( 'mhm-vehicle-card-css' ),
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
			'deps'  => array( 'mhm-vehicle-card-css' ),
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

		'messages'              => array(
			'tag'   => 'rentiva_messages',
			'title' => 'Customer Messages',
			'css'   => 'customer-messages.css',
		),
		'transfer-search'       => array(
			'tag'   => 'rentiva_transfer_search',
			'title' => 'Transfer Search',
			'css'   => 'transfer.css',
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
		wp_enqueue_style('mhm-css-variables');
		wp_enqueue_style(
			'mhm-golden-ratio-contract',
			MHM_RENTIVA_PLUGIN_URL . 'assets/css/core/golden-ratio-contract.css',
			array( 'mhm-css-variables' ),
			self::get_asset_version('assets/css/core/golden-ratio-contract.css')
		);

		// Note: 'mhm-rentiva-datepicker-custom' and 'mhm-vehicle-card-css' are now loaded
		// conditionally by the specific blocks/shortcodes that need them.

		// Apply editor styles for better iframe coverage
		if (is_admin()) {
			add_editor_style(MHM_RENTIVA_PLUGIN_URL . 'assets/css/core/css-variables.css');
			add_editor_style(MHM_RENTIVA_PLUGIN_URL . 'assets/css/core/golden-ratio-contract.css');
			// We might still want datepicker styles in the editor for UX if they use it
			add_editor_style(MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/datepicker-custom.css');
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
			MHM_RENTIVA_PLUGIN_URL . 'assets/css/editor/block-editor-fixes.css',
			array(),
			self::get_asset_version('assets/css/editor/block-editor-fixes.css')
		);

		wp_enqueue_style(
			'mhm-rentiva-datepicker-custom-editor',
			MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/datepicker-custom.css',
			array(),
			MHM_RENTIVA_VERSION
		);

		wp_enqueue_script(
			'mhm-rentiva-block-editor-fixes-js',
			MHM_RENTIVA_PLUGIN_URL . 'assets/js/editor/block-editor-fixes.js',
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
	 * Register all defined blocks with their dependencies
	 *
	 * @return void
	 */
	public static function register_blocks(): void
	{
		// Core Styles Registration (Unify handles with AssetManager)
		if (! wp_style_is('mhm-css-variables', 'registered')) {
			wp_register_style(
				'mhm-css-variables',
				MHM_RENTIVA_PLUGIN_URL . 'assets/css/core/css-variables.css',
				array(),
				MHM_RENTIVA_VERSION
			);
		}

		if (! wp_style_is('mhm-core-css', 'registered')) {
			wp_register_style(
				'mhm-core-css',
				MHM_RENTIVA_PLUGIN_URL . 'assets/css/core/core.css',
				array( 'mhm-css-variables' ),
				MHM_RENTIVA_VERSION
			);
		}

		foreach (self::$blocks as $slug => $config) {
			$script_handle = 'mhm-rentiva-block-' . $slug . '-editor';

			// 1. Register Editor Script (Shared requirements)
			// 1. Register Editor Script (Shared requirements)
			wp_register_script(
				$script_handle,
				MHM_RENTIVA_PLUGIN_URL . 'assets/blocks/' . $slug . '/index.js',
				array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-server-side-render', 'wp-block-editor' ),
				self::get_asset_version('assets/blocks/' . $slug . '/index.js'),
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
					$style_handle = 'mhm-rentiva-' . str_replace('_', '-', $config['tag']);
				} else {
					// Fallback to block slug
					$style_handle = ( count($css_files) === 1 )
						? 'mhm-rentiva-block-' . $slug . '-style'
						: 'mhm-rentiva-block-' . $slug . '-style-' . $index;
				}

				// Merge dependencies: Core variables + Specific block deps
				$deps = array_merge(array( 'mhm-css-variables' ), $config['deps'] ?? array());

				// Only register if not already registered (especially for shared handles)
				if (! wp_style_is($style_handle, 'registered')) {
					$path = ( strpos($css_file, 'assets/') === 0 ) ? $css_file : 'assets/css/frontend/' . $css_file;
					wp_register_style(
						$style_handle,
						MHM_RENTIVA_PLUGIN_URL . $path,
						$deps,
						MHM_RENTIVA_VERSION
					);
				}

				$style_handles[] = $style_handle;
			}

			// 3. Register Block Type via block.json
			register_block_type(
				MHM_RENTIVA_PLUGIN_DIR . 'assets/blocks/' . $slug . '/block.json',
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
	 * @param array $attributes Block attributes from editor.
	 * @param string $content Inner block content (if any).
	 * @param \WP_Block $block The block instance.
	 * @return string Rendered HTML.
	 */
	public static function render_callback(array $attributes, string $content, \WP_Block $block): string
	{
		// Extract the slug from the block name (e.g., mhm-rentiva/search -> search)
		$slug = str_replace('mhm-rentiva/', '', $block->name);

		if (! isset(self::$blocks[ $slug ])) {
			return '';
		}

		// EXPLICIT FRONTEND ENQUEUE: Declaration of block-level CSS dependencies
		// This ensures visual parity on the frontend for dynamic blocks which sometimes
		// fail to trigger automatic enqueueing in deep template structures.
		if (isset(self::$blocks[ $slug ]['css'])) {
			$css_files = (array) self::$blocks[ $slug ]['css'];
			foreach ($css_files as $index => $css_file) {
				// Calculate handle based on the same logic as init()
				if ($css_file === 'datepicker-custom.css') {
					$style_handle = 'mhm-rentiva-datepicker-custom';
				} elseif ($css_file === 'vehicle-card.css') {
					$style_handle = 'mhm-vehicle-card-css';
				} elseif (isset(self::$blocks[ $slug ]['tag']) && $index === 0) {
					// Use Shortcode Tag driven handle if available to ensure parity with AbstractShortcode
					$style_handle = 'mhm-rentiva-' . str_replace('_', '-', self::$blocks[ $slug ]['tag']);
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
		if (isset(self::$blocks[ $slug ]['deps'])) {
			foreach ( (array) self::$blocks[ $slug ]['deps'] as $dep_handle) {
				wp_enqueue_style($dep_handle);
			}
		}

		$config = self::$blocks[ $slug ];
		$tag    = $config['tag'];

		// Guard against double mapping (especially when called recursively or via blocks-in-shortcodes)
		if (! empty($attributes['_canonical'])) {
			$mapped_attributes = $attributes;
		} else {
			// 1. Extract Wrapper Logic (Dimensions) before CAM drops unknown attributes
			$style_parts = array();
			if (! empty($attributes['minWidth'])) {
				$val = $attributes['minWidth'];
				if (is_numeric($val)) {
					$val .= 'px';
				}
				$style_parts[] = "min-width:$val";
			}
			if (! empty($attributes['maxWidth'])) {
				$val = $attributes['maxWidth'];
				if (is_numeric($val)) {
					$val .= 'px';
				}
				$style_parts[] = "max-width:$val";
			}
			if (! empty($attributes['height'])) {
				$val = $attributes['height'];
				if (is_numeric($val)) {
					$val .= 'px';
				}
				$style_parts[] = "height:$val";
			}

			if (! empty($style_parts)) {
				$attributes['style'] = implode(';', $style_parts) . ';';
			}

			// 2. Canonical Attribute Mapping (Registry Driven)
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

		if (! empty($attributes['maxWidth'])) {
			$val = $attributes['maxWidth'];
			if (is_numeric($val)) {
				$val .= 'px';
			}
			$wrapper_styles[] = "max-width:$val";
			$wrapper_styles[] = 'width:100%';
			$wrapper_styles[] = 'margin-left:auto';
			$wrapper_styles[] = 'margin-right:auto';
		}

		if (! empty($attributes['minWidth'])) {
			$val = $attributes['minWidth'];
			if (is_numeric($val)) {
				$val .= 'px';
			}
			$wrapper_styles[] = "min-width:$val";
		}

		if (! empty($attributes['height'])) {
			$val = $attributes['height'];
			if (is_numeric($val)) {
				$val .= 'px';
			}
			$wrapper_styles[] = "height:$val";
		}

		if (! empty($wrapper_styles)) {
			$wrapper_args['style'] = implode(';', $wrapper_styles);
		}

		// Return wrapped content with proper block wrapper attributes (FSE support)
		return sprintf(
			'<div %s>%s</div>',
			get_block_wrapper_attributes($wrapper_args),
			$shortcode_content
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
	 * Resolve stable file version for asset URLs.
	 *
	 * @param string $relative_path Asset path relative to plugin root.
	 */
	private static function get_asset_version(string $relative_path): string
	{
		if (isset(self::$asset_version_cache[ $relative_path ])) {
			return self::$asset_version_cache[ $relative_path ];
		}

		$full_path = MHM_RENTIVA_PLUGIN_DIR . $relative_path;
		if (file_exists($full_path)) {
			$filemtime = filemtime($full_path);
			if (false !== $filemtime) {
				$version                                     = (string) $filemtime;
				self::$asset_version_cache[ $relative_path ] = $version;
				return $version;
			}
		}

		self::$asset_version_cache[ $relative_path ] = MHM_RENTIVA_VERSION;
		return MHM_RENTIVA_VERSION;
	}
}
