<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Unified search intentionally composes bounded vehicle filters and lookup queries.



use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Core\Assets\DatepickerAssets;



/**
 * Unified Search Shortcode
 *
 * Rental vehicle search widget.
 *
 * @since 4.0.0
 */
final class UnifiedSearch extends AbstractShortcode {


	/**
	 * @return string Shortcode tag.
	 */
	protected static function get_shortcode_tag(): string
	{
		return 'rentiva_unified_search';
	}

	/**
	 * @return string Path to template.
	 */
	protected static function get_template_path(): string
	{
		return 'shortcodes/unified-search';
	}

	/**
	 * Define default attributes.
	 */
	protected static function get_default_attributes(): array
	{
		return array(
			// Visibility controls (boolean as string for shortcode compatibility)
			'show_rental_tab'       => 'default',
			'show_location_select'  => 'default',
			'show_time_select'      => 'default',
			'show_date_picker'      => 'default',
			'show_dropoff_location' => 'default',
			'location_required'     => 'default', // Whether pickup_location select is required
			'fields_required'       => 'default', // Whether date fields are required (false = browse all vehicles)

			// Where the form submits: a page id or a same-site URL; 'default' is the
			// search results page (see form_action()).
			'redirect_page'         => 'default',

			// Layout & Styling
			// 'horizontal' is the base layout; 'vertical' stacks the fields. A third
			// value, 'compact', was documented for years but never had a stylesheet
			// behind it, so it rendered as plain horizontal -- it is no longer offered.
			// A `layout` twin was removed (spec 3A): search_layout always won it on the
			// block and Elementor surfaces.
			'search_layout'         => 'horizontal', // 'horizontal', 'vertical'
			'class'                 => '',
		);
	}

	/**
	 * Prepare data for the template.
	 */
	protected static function prepare_template_data(array $atts): array
	{
		// Locations are requested for the rental search form.
		$locations = apply_filters('mhmrentiva_search_locations', array(), 'rental');

		// Resolve initial visibility
		$show_rental = self::resolve_bool($atts['show_rental_tab'], 'mhmrentiva_show_rental_tab', true);

		$layout = ! empty($atts['search_layout']) ? (string) $atts['search_layout'] : 'horizontal';

		return array(
			'locations'             => $locations,
			'wrapper_id'            => uniqid('rv_unified_'),
			'nonce'                 => wp_create_nonce('mhmrentiva_unified_search'),

			// Visibility controls
			'show_rental_tab'       => $show_rental,
			// Never show a location select with nothing to select: the setting
			// defaults to true, so without this the Lite rental form would render an
			// empty (and, when location_required, unsubmittable) picker.
			'show_location_select'  => $locations !== array()
				&& self::resolve_bool($atts['show_location_select'], 'mhmrentiva_enable_location_select', true),
			'show_time_select'      => self::resolve_bool($atts['show_time_select'], 'mhmrentiva_enable_time_select', true),
			'show_date_picker'      => self::resolve_bool($atts['show_date_picker'], 'mhmrentiva_enable_date_picker', true),
			// Off unless a page asks for it: no booking, price or record reads a
			// drop-off location yet, so the field would promise what nothing does.
			'show_dropoff_location' => self::resolve_bool($atts['show_dropoff_location'], 'mhmrentiva_enable_dropoff', false),
			'location_required'     => self::resolve_bool($atts['location_required'], 'mhmrentiva_location_required', true),
			'fields_required'       => self::resolve_bool($atts['fields_required'], 'mhmrentiva_fields_required', true),

			'form_action'           => self::form_action(self::resolve_default(self::page_id_or_empty($atts['redirect_page']), 'mhmrentiva_search_results_page')),
			'layout'                => $layout,
		);
	}

	/**
	 * The rental form's action URL for a resolved redirect_page value.
	 *
	 * A page id becomes that page's permalink when the page is published and
	 * public; a URL is kept only when it is on this site (wp_validate_redirect()).
	 * Anything else -- not set, another site, a missing or draft page -- keeps the
	 * search results page the form has always submitted to.
	 *
	 * @param mixed $redirect Resolved redirect_page (attribute, else global setting).
	 */
	private static function form_action(mixed $redirect): string
	{
		$fallback = \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_search_results');
		$redirect = is_scalar($redirect) ? trim( (string) $redirect) : '';
		if ('' === $redirect) {
			return $fallback;
		}

		if (ctype_digit($redirect)) {
			// get_post(0) returns the global post: 0 is not a page id.
			$page = (int) $redirect > 0 ? get_post( (int) $redirect) : null;
			if ($page instanceof \WP_Post && 'publish' === $page->post_status && is_post_type_viewable($page->post_type)) {
				return (string) get_permalink($page);
			}
			return $fallback;
		}

		$url = wp_validate_redirect(esc_url_raw($redirect), '');
		return '' !== $url ? $url : $fallback;
	}

	/**
	 * A redirect_page attribute of 0 means "not set": it falls through to the
	 * global setting exactly like an empty value does.
	 *
	 * @param mixed $value Raw redirect_page attribute.
	 */
	private static function page_id_or_empty(mixed $value): mixed
	{
		if (is_scalar($value) && ctype_digit(trim( (string) $value)) && 0 === (int) $value) {
			return '';
		}
		return $value;
	}

	/**
	 * Enqueue specific assets.
	 */
	protected static function enqueue_assets(array $atts = array()): void
	{
		// Base unified-search styles (layout and component foundations).
		wp_enqueue_style(
			'mhm-rentiva-unified-search-base',
			MHMRENTIVA_PLUGIN_URL . 'assets/css/frontend/unified-search.css',
			array( 'mhm-rentiva-css-variables' ),
			MHMRENTIVA_VERSION
		);

		$search_deps = array( 'jquery', 'jquery-ui-datepicker' );

		wp_enqueue_script(
			'mhm-rentiva-unified-search',
			MHMRENTIVA_PLUGIN_URL . 'assets/js/frontend/unified-search.js',
			$search_deps,
			MHMRENTIVA_VERSION,
			true
		);

		// Ensure datepicker assets are loaded via centralized helper
		DatepickerAssets::enqueue();

		// Consolidate Localize script with combined data.
		wp_localize_script(
			'mhm-rentiva-unified-search',
			'mhmUnifiedSearch',
			array(
				'restUrl'   => get_rest_url(null, 'mhm-rentiva/v1/locations'),
				'restNonce' => \MHMRentiva\Frontend\RenderContext::script_nonce('wp_rest'),
				'settings'  => array(
					'minRentalDays'     => BookingForm::min_rental_days(),
					'defaultRentalDays' => (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhmrentiva_default_rental_days', 1),
				),
			)
		);
	}

	/**
	 * Resolve attribute value: If 'default', fetch from Global Settings.
	 * Priority: Attribute > SettingsCore (if exists) > Fallback
	 */
	private static function resolve_default(mixed $value, string $global_key, mixed $fallback = null): mixed
	{
		// 1. Attribute Priority (if not 'default' and not empty)
		if ('default' !== $value && '' !== $value && null !== $value) {
			return $value;
		}

		// 2. SettingsCore Priority (if exists)
		if (\MHMRentiva\Admin\Settings\Core\SettingsCore::has($global_key)) {
			$global_val = \MHMRentiva\Admin\Settings\Core\SettingsCore::get($global_key);
			if (null !== $global_val && '' !== $global_val) {
				return $global_val;
			}
		}

		// 3. Fallback priority
		return $fallback;
	}

	/**
	 * Resolve boolean attribute with default fallback.
	 * Priority: Attribute > SettingsCore (if exists) > Fallback
	 */
	private static function resolve_bool(mixed $value, string $global_key, bool $default_val = false): bool
	{
		// 1. Attribute Priority (if not 'default' and not empty)
		if ('default' !== $value && '' !== $value && null !== $value) {
			return filter_var($value, FILTER_VALIDATE_BOOLEAN);
		}

		// 2. SettingsCore Priority (if exists)
		if (\MHMRentiva\Admin\Settings\Core\SettingsCore::has($global_key)) {
			$global_val = \MHMRentiva\Admin\Settings\Core\SettingsCore::get($global_key);
			if (null !== $global_val && '' !== $global_val) {
				return filter_var($global_val, FILTER_VALIDATE_BOOLEAN);
			}
		}

		// 3. Fallback priority
		return $default_val;
	}
}
