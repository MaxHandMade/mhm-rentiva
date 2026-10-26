<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Search filters and batched lookup queries are intentional and bounded by pagination/ID limits.



use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Vehicle\PostType\Vehicle as PT_Vehicle;
use MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper;
use Exception;

use MHMRentiva\Admin\Core\QueryHelper;
use MHMRentiva\Admin\Core\LocationContext;
use MHMRentiva\Admin\Booking\Helpers\BookingConflicts;
use MHMRentiva\Admin\Booking\Helpers\NextFreeWindow;
use MHMRentiva\Admin\Booking\Helpers\SiteTime;
use MHMRentiva\Frontend\RenderContext;



/**
 *
 * Features:
 * - Sidebar filter system
 * - Dynamic filtering via AJAX
 * - Grid/List view options
 * - Pagination
 * - SEO-friendly URL parameters
 */
use MHMRentiva\Admin\Core\Security\VerifiedRequest;

final class SearchResults extends AbstractShortcode {

	/** Renders inside the front-end standard's shell. */
	protected static bool $rvs_shell = true;

	/**
	 * Context key for the availability injector filter.
	 */
	private const SEARCH_CONTEXT_KEY = 'mhmrentiva_search_context';

	/**
	 * Last token handed out by scoped_query().
	 */
	private static int $scope_seq = 0;


	public const SHORTCODE = 'rentiva_search_results';

	/**
	 * The facet cache (get_filter_options()); Plugin deletes it when a vehicle
	 * changes. _v3: categories joined the facets (Aşama 2 PR 3) -- one constant,
	 * so the reader and the invalidation cannot name different keys again.
	 * _v4: the rental locations left the cache (PR S); they are read fresh from
	 * LocationContext, so the facet, the chips and the "single location" rule
	 * cannot disagree for a day.
	 */
	public const FILTERS_CACHE_KEY = 'mhmrentiva_search_filters_v4';

	/**
	 * The most cars the "other pickup points in the same city" section shows.
	 */
	private const NEARBY_LIMIT = 6;

	/**
	 * The most cars the "booked for these dates" section shows.
	 */
	private const BOOKED_LIMIT = 3;

	/**
	 * Empty states that speak in the notice region (above the sections)
	 * instead of the grid.
	 */
	public const NOTICE_STATES = array( 'none_here', 'none_yet' );

	/**
	 * Public GET filter params for this shortcode's shareable/bookmarkable
	 * search-results URL (e.g.
	 * `?mhmrentiva_min_price=100&mhmrentiva_fuel_type=diesel&mhmrentiva_sort=price_asc`).
	 *
	 * Registered on WordPress's `query_vars` whitelist (see register_query_vars())
	 * so they are read via get_query_var() instead of raw $_GET.
	 *
	 * Every name carries the `mhmrentiva_` prefix (see query_var()). `query_vars`
	 * is a site-wide namespace shared with WordPress core and every other active
	 * plugin, so registering a bare `sort`, `brand` or `seats` there claims a
	 * generic global name this plugin has no business owning. The logical keys
	 * stay unprefixed everywhere inside the code -- get_text()/get_int()/
	 * get_int_array() and the templates map them through query_var(), which is
	 * the one place the prefix is applied.
	 *
	 * `page` is intentionally NOT listed here: it is already one of WordPress
	 * core's default public query vars, so it is readable via get_query_var()
	 * without any registration -- and must stay unprefixed for that reason.
	 *
	 * @var array<int, string>
	 */
	private const PUBLIC_QUERY_VARS = array(
		// No template or JS URL-builder emits this, but SearchResults::build_query()
		// does read it (it becomes the WP_Query `s` parameter) and the results
		// template prints "Results for: ...", so an externally-built
		// ?mhmrentiva_keyword= URL works today. Registered deliberately -- do not remove.
		'mhmrentiva_keyword',
		'mhmrentiva_pickup_date',
		'mhmrentiva_return_date',
		'mhmrentiva_start_date',
		'mhmrentiva_end_date',
		'mhmrentiva_min_price',
		'mhmrentiva_max_price',
		'mhmrentiva_fuel_type',
		'mhmrentiva_transmission',
		'mhmrentiva_seats',
		'mhmrentiva_brand',
		'mhmrentiva_year_min',
		'mhmrentiva_year_max',
		'mhmrentiva_mileage_max',
		'mhmrentiva_sort',
		'mhmrentiva_pickup_location',
		// Vehicle category term ids (taxonomy mhmrentiva_vehicle_category).
		'mhmrentiva_category',
	);

	/**
	 * Logical filter keys behind PUBLIC_QUERY_VARS, in the same order.
	 *
	 * Exposed so the emission side and the tests can prove that every declared
	 * whitelist entry really is query_var() of a key the read path uses -- the
	 * lock that keeps the two lists from drifting apart.
	 *
	 * @var array<int, string>
	 */
	public const FILTER_PARAMS = array(
		'keyword',
		'pickup_date',
		'return_date',
		'start_date',
		'end_date',
		'min_price',
		'max_price',
		'fuel_type',
		'transmission',
		'seats',
		'brand',
		'year_min',
		'year_max',
		'mileage_max',
		'sort',
		'pickup_location',
		'category',
	);

	/**
	 * Safe sanitize text field that handles null values
	 *
	 * @param mixed $value Value to sanitize
	 * @return string
	 */
	public static function sanitize_text_field_safe($value): string
	{
		if ($value === null || $value === '') {
			return '';
		}
		return sanitize_text_field( (string) $value);
	}

	/**
	 * Reads a public search-filter param via WordPress's query_vars whitelist.
	 *
	 * Public GET URL filter params must be registered on
	 * `query_vars` (see register_query_vars()) and read via get_query_var(),
	 * not raw $_GET. A `null` sentinel default distinguishes "param absent from
	 * the request" from "param present but empty", matching the previous
	 * `isset($_GET[$key])` semantics exactly.
	 *
	 * Takes the LOGICAL key ('min_price'); query_var() applies the wire prefix,
	 * so the ~20 call sites below stay readable and cannot forget it.
	 */
	private static function get_text(string $key, string $default = ''): string
	{
		$value = get_query_var(self::query_var($key), null);
		if (null === $value) {
			return $default;
		}

		return sanitize_text_field(wp_unslash( (string) $value));
	}

	private static function get_int(string $key, int $default = 0): int
	{
		return self::get_int_by_var(self::query_var($key), $default);
	}

	/**
	 * Reads an integer query var by its EXACT on-the-wire name, no prefixing.
	 *
	 * Only for WordPress core's own public query vars (`page`), which this plugin
	 * neither registers nor renames. Plugin params go through get_int().
	 */
	private static function get_int_by_var(string $var, int $default = 0): int
	{
		$value = get_query_var($var, null);
		if (null === $value) {
			return $default;
		}

		return (int) wp_unslash( (string) $value);
	}

	/**
	 * Get an array of integers from a registered query var (supports both single int and array).
	 */
	/**
	 * A text filter that may arrive as one value or a list (`fuel_type[]`).
	 *
	 * @return string|list<string>
	 */
	private static function get_text_or_list(string $key): string|array
	{
		$value = get_query_var(self::query_var($key), null);
		if (null === $value) {
			return '';
		}
		if (is_array($value)) {
			return array_values(array_filter(array_map(static fn($v): string => sanitize_text_field(wp_unslash( (string) $v)), $value), static fn(string $v): bool => '' !== $v));
		}

		return sanitize_text_field(wp_unslash( (string) $value));
	}

	private static function get_int_array(string $key): array
	{
		$value = get_query_var(self::query_var($key), null);
		if (null === $value) {
			return array();
		}

		$raw    = wp_unslash($value);
		$values = is_array($raw) ? $raw : array( $raw );

		return array_filter(array_map('intval', $values));
	}


	public static function register(): void
	{
		parent::register();

		// AJAX handlers
		add_action('wp_ajax_mhmrentiva_filter_results', array( self::class, 'ajax_filter_results' ));
		add_action('wp_ajax_nopriv_mhmrentiva_filter_results', array( self::class, 'ajax_filter_results' ));
	}

	/**
	 * Registers this shortcode's public search-filter params on WP's query_vars
	 * whitelist. Called by parent::register() (AbstractShortcode) on `init`.
	 */
	protected static function register_hooks(): void
	{
		add_filter('query_vars', array( self::class, 'register_query_vars' ));
	}

	/**
	 * `query_vars` filter callback -- adds PUBLIC_QUERY_VARS and returns the array.
	 *
	 * @param array<int, string> $vars
	 * @return array<int, string>
	 */
	public static function register_query_vars(array $vars): array
	{
		return array_merge($vars, self::PUBLIC_QUERY_VARS);
	}

	protected static function get_shortcode_tag(): string
	{
		return 'rentiva_search_results';
	}

	protected static function get_template_path(): string
	{
		return 'shortcodes/search-results';
	}

	protected static function get_default_attributes(): array
	{
		return array(
			'layout'               => 'grid',     // grid/list
			'show_filters'         => '1',        // 1/0
			'results_per_page'     => '12',       // Results per page
			'show_pagination'      => '1',        // 1/0
			'show_sorting'         => '1',        // 1/0
			'show_view_toggle'     => '1',        // 1/0
			'show_favorite_button' => '1',        // 1/0
			'show_compare_button'  => '1',        // 1/0
			'show_booking_button'  => '1',        // 1/0
			'show_price'           => '1',        // 1/0
			'show_title'           => '1',        // 1/0
			'show_features'        => '1',        // 1/0
			'show_rating'          => '1',        // 1/0
			'show_badges'          => '1',        // 1/0
			'default_sort'         => 'price_asc', // A get_sorting_options() key.
			'class'                => '',         // Custom CSS class
		);
	}

	protected static function prepare_template_data(array $atts): array
	{
		// Get search criteria from URL parameters
		$search_params = self::get_search_params_from_url();

		// If no explicit sort in request, apply shortcode's default_sort attribute.
		//
		// There used to be a separate $_POST['sort'] fallback here for "public search
		// forms may submit by POST". It was doing nothing: `sort` is a registered
		// public query var (see PUBLIC_QUERY_VARS) and WP::parse_request() fills
		// registered vars from $_POST as well as $_GET, so get_text() already
		// answers for a POSTed sort -- through the query_vars whitelist rather than
		// a raw superglobal read.
		$request_sort = self::get_text('sort');

		if ('' === $request_sort) {
			$search_params['sort'] = $atts['default_sort'] ?? 'relevance';
		}

		// Get search results
		$results = self::perform_search($search_params, $atts);

		// Prepare filter options
		$filter_options = self::filter_options($search_params);

		// Compact Handling: If layout is 'compact', force internal layout to 'grid' but flags 'is_compact'
		$is_compact = $atts['layout'] === 'compact';
		if ($is_compact) {
			$atts['layout'] = 'grid'; // Force grid structure for vehicle cards
		}

		$chips       = self::active_chips($search_params, $filter_options);
		$empty_state = self::empty_state($search_params, (int) $results['total'], self::section_counts($results));
		$change_url  = self::change_url($search_params);
		$single      = LocationContext::single();

		// Prepare template data
		return array(
			'atts'            => $atts,
			'is_compact'      => $is_compact,
			'search_params'   => $search_params,
			'results'         => $results,
			'filter_options'  => $filter_options,
			'pagination'      => $results['pagination'] ?? array(),
			'sorting_options' => self::get_sorting_options(null !== self::window($search_params)),
			'nonce_field'     => RenderContext::nonce_field('mhmrentiva_search_results', 'mhmrentiva_search_results_nonce'),
			'summary'         => self::summary($search_params, (int) $results['total']),
			'chips'           => $chips,
			'filter_count'    => self::filter_count($chips),
			'empty_state'     => $empty_state,
			'notice_html'     => self::notice_html($search_params, $results, (string) $empty_state, $change_url),
			'change_url'      => $change_url,
			'location_label'  => self::location_label($search_params),
			'pickup_where'    => null !== $single ? (string) $single->name : '',
			'compare'         => self::compare_band(),
		);
	}

	/**
	 * How many cars each extra section holds (0 when it is not shown).
	 *
	 * @param array<string, mixed> $results perform_search() shape.
	 * @return array{nearby: int, booked: int}
	 */
	private static function section_counts(array $results): array
	{
		return array(
			'nearby' => count( (array) ( $results['nearby'] ?? array() )),
			'booked' => count( (array) ( $results['booked'] ?? array() )),
		);
	}

	/**
	 * Where "Change" / "Change dates" go: the search page with the current
	 * window and location, so its strip opens on them. (On a page that also
	 * holds the strip the script scrolls to it instead.)
	 *
	 * @param array<string, mixed> $params Search params (logical keys).
	 */
	private static function change_url(array $params): string
	{
		$base = ShortcodeUrlManager::get_page_url('rentiva_unified_search');
		if ('' === $base) {
			$base = home_url('/');
		}
		$args = array();
		foreach (array( 'pickup_date', 'return_date', 'pickup_time' ) as $key) {
			if (isset($params[ $key ]) && is_scalar($params[ $key ]) && '' !== (string) $params[ $key ]) {
				$args[ self::query_var($key) ] = (string) $params[ $key ];
			}
		}
		$locations = (array) ( $params['pickup_location'] ?? array() );
		if (1 === count($locations)) {
			$args[ self::query_var('pickup_location') ] = (string) (int) reset($locations);
		}

		return add_query_arg(array_map('rawurlencode', $args), $base);
	}

	/**
	 * The pickup point the search is at, for the mobile summary card: the
	 * site's only one, or the one picked. '' otherwise.
	 *
	 * @param array<string, mixed> $params Search params (logical keys).
	 */
	private static function location_label(array $params): string
	{
		$single = LocationContext::single();
		if (null !== $single) {
			return (string) $single->name;
		}
		$locations = (array) ( $params['pickup_location'] ?? array() );
		if (1 !== count($locations)) {
			return '';
		}
		$location = LocationContext::find( (int) reset($locations));

		return null !== $location ? (string) $location->name : '';
	}

	/**
	 * Names of the picked pickup points, in the order picked.
	 *
	 * @param array<string, mixed> $params Search params (logical keys).
	 * @return list<string>
	 */
	private static function selected_names(array $params): array
	{
		$names = array();
		foreach ( (array) ( $params['pickup_location'] ?? array() ) as $id) {
			$location = LocationContext::find( (int) $id);
			if (null !== $location) {
				$names[] = (string) $location->name;
			}
		}

		return $names;
	}

	/**
	 * The comparison band's state: the compared vehicles, the limit, the page.
	 *
	 * @return array{items: list<array{id: int, title: string}>, max: int, url: string}
	 */
	private static function compare_band(): array
	{
		return array(
			'items' => \MHMRentiva\Admin\Services\CompareService::items(),
			'max'   => \MHMRentiva\Admin\Services\CompareService::max_items(),
			'url'   => ShortcodeUrlManager::get_page_url('rentiva_vehicle_comparison'),
		);
	}


	/**
	 * Force-enqueue runtime assets for block rendering path.
	 *
	 * Block render callbacks may return cached shortcode HTML where asset enqueue
	 * hooks are not guaranteed; this ensures toggle JS and localization are present.
	 */
	public static function ensure_runtime_assets(array $atts = array()): void
	{
		$normalized_atts = shortcode_atts(self::get_default_attributes(), $atts, self::get_shortcode_tag());
		self::enqueue_assets($normalized_atts);
	}

	/**
	 * Enqueue asset files
	 */
	protected static function enqueue_assets(array $atts = array()): void
	{
		// The page is the front-end standard's (rvs-results.css on top of the
		// shell, primitives and the card); the old search-results.css and the
		// core vehicle-card sheet are not loaded here any more (Aşama 2 PR 3).
		\MHMRentiva\Admin\Core\AssetManager::enqueue_core_js();
		\MHMRentiva\Admin\Core\AssetManager::enqueue_rvs_results_styles();
		// The mobile filter drawer is a modal dialog (shared rvs-dialog module).
		\MHMRentiva\Admin\Core\AssetManager::enqueue_rvs_dialog();

		// Search results JavaScript
		wp_enqueue_script(
			'mhm-rentiva-search-results-js',
			MHMRENTIVA_PLUGIN_URL . 'assets/js/frontend/search-results.js',
			// search-results.js calls MHMRentivaToast.* directly -- declared
			// here even though mhm-rentiva-vehicle-interactions already pulls toast in
			// transitively, so this handle stays correct even if that transitive chain
			// ever changes.
			array( 'jquery', 'mhm-rentiva-vehicle-interactions', 'mhm-rentiva-toast', 'mhm-rentiva-rvs-dialog' ),
			MHMRENTIVA_VERSION . '-' . filemtime(MHMRENTIVA_PLUGIN_DIR . 'assets/js/frontend/search-results.js'),
			true
		);

		// Ensure vehicle interaction globals are available wherever this widget
		// renders -- block-only pages, and Elementor-built ones, where
		// AssetManager::should_load_assets() cannot see the shortcode because
		// Elementor keeps the content in postmeta rather than post_content.
		//
		// This used to be a SECOND, shorter wp_localize_script() of
		// mhmrentiva_vars written out right here, without the 'i18n' sub-array.
		// wp_localize_script does not merge -- last writer wins -- so on those
		// pages vehicle-interactions.js got a payload with no strings and threw
		// `Cannot read properties of undefined (reading 'adding_compare')` on the
		// first compare click. One definition, in AssetManager, is the fix; a
		// second copy of the same 14 strings is how the bug reproduces.
		\MHMRentiva\Admin\Core\AssetManager::enqueue_vehicle_interactions();

		// Localize script
		wp_localize_script(
			'mhm-rentiva-search-results-js',
			'mhmRentivaSearchResults',
			array(
				'ajax_url'        => admin_url('admin-ajax.php'),
				// '' in an editor preview although the filter is a public read: its
				// response is cards rendered in a front context, with live
				// favourite/compare buttons and their real nonces.
				'nonce'           => \MHMRentiva\Frontend\RenderContext::script_nonce('mhmrentiva_search_results'),
				'current_url'     => home_url(add_query_arg(null, null)),
				'search_page_url' => ShortcodeUrlManager::get_page_url('rentiva_unified_search'),
				'i18n'            => array(
					'loading'                => __('Loading...', 'mhm-rentiva'),
					'no_results'             => __('No vehicles found', 'mhm-rentiva'),
					'filter_applied'         => __('Filter applied', 'mhm-rentiva'),
					'clear_filters'          => __('Clear all filters', 'mhm-rentiva'),
					'clear_all'              => __('Clear All', 'mhm-rentiva'),
					/* translators: %d: count of filters. */
					'clear_all_with_count'   => __('Clear All (%d)', 'mhm-rentiva'),
					'try_adjusting'          => __('Try adjusting your search criteria or filters.', 'mhm-rentiva'),
					'back_to_search'         => __('Back to Search', 'mhm-rentiva'),
					'added_to_favorites'     => __('Added to favorites', 'mhm-rentiva'),
					'removed_from_favorites' => __('Removed from favorites', 'mhm-rentiva'),
					'per_day'                => __('/day', 'mhm-rentiva'),
					'seats'                  => __('seats', 'mhm-rentiva'),
					'review'                 => __('review', 'mhm-rentiva'),
					'reviews'                => __('reviews', 'mhm-rentiva'),
					'view_details'           => __('View Details', 'mhm-rentiva'),
					'previous'               => __('Previous', 'mhm-rentiva'),
					'next'                   => __('Next', 'mhm-rentiva'),
					'error'                  => __('Error', 'mhm-rentiva'),
					'try_again'              => __('Try Again', 'mhm-rentiva'),
					/* translators: %d: number of vehicles. */
					'vehicle_found'          => __('%d vehicle found', 'mhm-rentiva'),
					/* translators: %d: number of vehicles. */
					'vehicles_found'         => __('%d vehicles found', 'mhm-rentiva'),
					/* translators: 1: number of vehicles picked for comparison, 2: the most that can be compared. */
					'band_one'               => __('%1$s vehicle selected · up to %2$s', 'mhm-rentiva'),
					/* translators: 1: number of vehicles picked for comparison, 2: the most that can be compared. */
					'band_many'              => __('%1$s vehicles selected · up to %2$s', 'mhm-rentiva'),
				),
				'favorite_nonce'  => \MHMRentiva\Frontend\RenderContext::script_nonce('mhmrentiva_toggle_favorite'),
				'icons'           => array(
					'heart' => \MHMRentiva\Helpers\Icons::get('heart'),
				),
			)
		);
	}

	/**
	 * Get search parameters from URL parameters
	 */
	private static function get_search_params_from_url(): array
	{
		return array(
			'keyword'         => self::get_text('keyword'),
			'pickup_date'     => self::get_text('pickup_date'),
			'return_date'     => self::get_text('return_date'),
			// The window's time, as the AJAX path reads it (m3): the page and its
			// re-render must search the same window.
			'pickup_time'     => self::get_text('pickup_time'),
			// Legacy support
			'start_date'      => self::get_text('start_date', self::get_text('pickup_date')),
			'end_date'        => self::get_text('end_date', self::get_text('return_date')),
			'min_price'       => self::get_int('min_price'),
			'max_price'       => self::get_int('max_price'),
			'fuel_type'       => self::get_text_or_list('fuel_type'),
			'transmission'    => self::get_text_or_list('transmission'),
			'seats'           => self::get_text_or_list('seats'),
			'brand'           => self::get_text_or_list('brand'),
			'year_min'        => self::get_int('year_min'),
			'year_max'        => self::get_int('year_max'),
			'mileage_max'     => self::get_int('mileage_max'),
			'sort'            => self::get_text('sort', 'relevance'),
			// `page` is one of WordPress core's own public query vars: read by its
			// exact name, never prefixed, so paginate_links()' `?page=%#%` hrefs
			// keep resolving.
			'page'            => self::get_int_by_var('page', 1),
			// Only a real choice counts: none with 0 or 1 rental location (m8).
			'pickup_location' => LocationContext::effective_selected(self::get_int_array('pickup_location')),
			'category'        => self::get_int_array('category'),
		);
	}

	/**
	 * The search: the main grid (free cars at the picked point), then -- on
	 * page 1 of a dated search only -- the free cars at the other pickup
	 * points of the same city and the matching cars booked on these dates.
	 *
	 * The booked set is computed once (BookingConflicts, the rule every
	 * booking path uses) and handed to the three queries as an ID list, so
	 * the grid's total and pages count only the cars it lists.
	 *
	 * @param array<string, mixed> $params Search params (logical keys).
	 * @param array<string, mixed> $atts   Shortcode attributes.
	 * @return array<string, mixed>
	 */
	private static function perform_search(array $params, array $atts): array
	{
		$args     = self::query_args($params, $atts);
		$window   = self::window($params);
		$selected = array_values(array_map('intval', (array) ( $params['pickup_location'] ?? array() )));
		$booked   = null === $window ? array() : BookingConflicts::booked_vehicle_ids($window[0], $window[1], self::candidate_ids());

		$query    = self::scoped_query($args, self::id_clause($booked, false) . QueryHelper::get_location_subquery($selected));
		$vehicles = self::format_vehicles($query->posts, $atts);

		$current_page = (int) ( $params['page'] ?? 1 );

		// The sections live on page 1. The booked one needs the window; the
		// same-city one also helps without dates when the picked point has no
		// car at all ("no cars there yet").
		$nearby       = array();
		$booked_items = array();
		if ($current_page <= 1) {
			if (null !== $window) {
				$nearby       = self::nearby_results($args, $atts, $booked, $selected);
				$booked_items = self::booked_matches($args, $atts, $booked, $selected, $window);
			} elseif (0 === (int) $query->found_posts) {
				$nearby = self::nearby_results($args, $atts, $booked, $selected);
			}
		}

		return array(
			'vehicles'     => $vehicles,
			'total'        => (int) $query->found_posts,
			'current_page' => $current_page,
			'per_page'     => (int) $atts['results_per_page'],
			'max_pages'    => (int) $query->max_num_pages,
			'pagination'   => array(
				'current'   => $current_page,
				'total'     => (int) $query->max_num_pages,
				'has_prev'  => $current_page > 1,
				'has_next'  => $current_page < $query->max_num_pages,
				'prev_page' => max(1, $current_page - 1),
				'next_page' => min($query->max_num_pages, $current_page + 1),
			),
			'nearby'       => $nearby,
			'booked'       => $booked_items,
		);
	}

	/**
	 * Free cars at the other pickup points of the picked points' city: the
	 * same filters, at most NEARBY_LIMIT, each with its own pickup point.
	 *
	 * @param array<string, mixed> $args     query_args().
	 * @param array<string, mixed> $atts     Shortcode attributes.
	 * @param list<int>            $booked   Booked vehicle ids for the window.
	 * @param list<int>            $selected Picked pickup points.
	 * @return list<array{vehicle: array<string, mixed>, where: string}>
	 */
	private static function nearby_results(array $args, array $atts, array $booked, array $selected): array
	{
		if (array() === $selected) {
			return array();
		}
		$same_city = LocationContext::same_city_ids($selected);
		if (array() === $same_city) {
			return array();
		}

		$query = self::scoped_query(self::section_args($args, self::NEARBY_LIMIT), self::id_clause($booked, false) . QueryHelper::get_location_subquery($same_city));
		$items = array();
		foreach (self::format_vehicles($query->posts, $atts) as $vehicle) {
			$location = LocationContext::for_vehicle( (int) ( $vehicle['id'] ?? 0 ));
			$items[]  = array(
				'vehicle' => $vehicle,
				'where'   => null !== $location ? (string) $location->name : '',
			);
		}

		return $items;
	}

	/**
	 * Cars that match the search but are booked on its dates, at the picked
	 * point: the same filters, at most BOOKED_LIMIT, each with the earliest
	 * window it is free. No query at all when nothing is booked.
	 *
	 * @param array<string, mixed> $args     query_args().
	 * @param array<string, mixed> $atts     Shortcode attributes.
	 * @param list<int>            $booked   Booked vehicle ids for the window.
	 * @param list<int>            $selected Picked pickup points.
	 * @param array{0: int, 1: int} $window  The search window.
	 * @return list<array{vehicle: array<string, mixed>, earliest: ?int, days: int}>
	 */
	private static function booked_matches(array $args, array $atts, array $booked, array $selected, array $window): array
	{
		if (array() === $booked) {
			return array();
		}

		$query    = self::scoped_query(self::section_args($args, self::BOOKED_LIMIT), self::id_clause($booked, true) . QueryHelper::get_location_subquery($selected));
		$vehicles = self::format_vehicles($query->posts, $atts);
		if (array() === $vehicles) {
			return array();
		}

		$ids      = array_map(static fn(array $vehicle): int => (int) ( $vehicle['id'] ?? 0 ), $vehicles);
		$earliest = NextFreeWindow::earliest($ids, $window[0], $window[1]);
		$days     = SiteTime::rental_days($window[0], $window[1]);

		$items = array();
		foreach ($vehicles as $vehicle) {
			$items[] = array(
				'vehicle'  => $vehicle,
				'earliest' => $earliest[ (int) ( $vehicle['id'] ?? 0 ) ] ?? null,
				'days'     => $days,
			);
		}

		return $items;
	}

	/**
	 * A section's query: the search's own arguments, first page, no count.
	 *
	 * @param array<string, mixed> $args query_args().
	 * @return array<string, mixed>
	 */
	private static function section_args(array $args, int $limit): array
	{
		$args['posts_per_page'] = $limit;
		$args['paged']          = 1;
		$args['no_found_rows']  = true;

		return $args;
	}

	/**
	 * The vehicles the search starts from, before any filter: active rental
	 * vehicles. BookingConflicts measures only these, not the whole fleet.
	 *
	 * @return list<int>
	 */
	private static function candidate_ids(): array
	{
		$args = \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::restrict_query_to_service(
			array(
				'post_type'        => PT_Vehicle::POST_TYPE,
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
				'meta_query'       => array( \MHMRentiva\Admin\Core\Utilities\MetaQueryHelper::get_active_vehicle_meta_query() ),
			),
			'rental'
		);

		return array_values(array_map('intval', get_posts($args)));
	}

	/**
	 * ` AND posts.ID IN (…)` / `NOT IN (…)`; '' for an empty NOT IN.
	 *
	 * @param list<int> $ids
	 */
	private static function id_clause(array $ids, bool $in): string
	{
		global $wpdb;

		$ids = array_values(array_filter(array_map('intval', $ids)));
		if (array() === $ids) {
			return $in ? " AND {$wpdb->posts}.ID IN (0)" : '';
		}

		if ($in) {
			return $wpdb->prepare(" AND {$wpdb->posts}.ID IN (" . implode(',', array_fill(0, count($ids), '%d')) . ')', $ids);
		}

		return $wpdb->prepare(" AND {$wpdb->posts}.ID NOT IN (" . implode(',', array_fill(0, count($ids), '%d')) . ')', $ids);
	}

	/**
	 * Runs the query with $where appended to its WHERE, for this query only:
	 * the clause is keyed to a per-call token under SEARCH_CONTEXT_KEY and
	 * removed in `finally`, so any other WP_Query in the request (a widget,
	 * a nested search) is left alone. An ID list here leaves `post__in` free for the minimum-rental
	 * guard's post__in = [0].
	 *
	 * @param array<string, mixed> $args WP_Query arguments.
	 * @param string               $where SQL appended to WHERE (ints and prepared fragments only).
	 */
	private static function scoped_query(array $args, string $where): \WP_Query
	{
		// A token per call, not a flag: a second search started while this
		// query is being built carries the key too, and must not get this
		// clause.
		$token                            = 'q' . ( ++self::$scope_seq );
		$args[ self::SEARCH_CONTEXT_KEY ] = $token;
		$args['suppress_filters']         = false;

		$filter = static function ($sql, \WP_Query $query) use ($where, $token) {
			if ($query->get(self::SEARCH_CONTEXT_KEY) !== $token) {
				return $sql;
			}

			return $sql . $where;
		};

		add_filter('posts_where', $filter, 10, 2);
		try {
			$query = new \WP_Query($args);
		} finally {
			// Mandatory Scope Isolation
			remove_filter('posts_where', $filter, 10);
		}

		return $query;
	}

	/**
	 * Card data for the query's vehicles, with their covers, metas and
	 * feature map primed in batches.
	 *
	 * @param array<int, mixed>    $posts WP_Query::$posts.
	 * @param array<string, mixed> $atts  Shortcode attributes.
	 * @return list<array<string, mixed>>
	 */
	private static function format_vehicles(array $posts, array $atts): array
	{
		$ids = array();
		foreach ($posts as $post) {
			$id = $post instanceof \WP_Post ? (int) $post->ID : (int) $post;
			if ($id > 0) {
				$ids[] = $id;
			}
		}
		if (array() === $ids) {
			return array();
		}

		// Deep Batch Priming: Optimization for N+1 Attachments and Pages
		// 1. Prime vehicles and their meta
		_prime_post_caches($ids, true, true);

		// 2. Prime the covers (attachments, their meta and uploaders) in one go;
		// reading get_post_thumbnail_id() here first would judge each cover uncached.
		\MHMRentiva\Admin\Vehicle\Meta\VehicleGallery::prime_covers($ids);
		// The cards' pickup points (vendor fallback) in one batch, not one per card.
		LocationContext::prime_vehicles($ids);

		// 3. The booking page, for the cards' URLs.
		$booking_page_id = ShortcodeUrlManager::get_page_id('rentiva_booking_form');
		if ($booking_page_id) {
			_prime_post_caches(array( (int) $booking_page_id ), true, true);
		}

		// Prime static caches for this batch
		VehicleFeatureHelper::prime_static_feature_map();

		$vehicles = array();
		foreach ($ids as $id) {
			$vehicles[] = self::format_vehicle_data($id, $atts);
		}

		return $vehicles;
	}

	/**
	 * The search's WP_Query arguments: the filters, sort and page, before
	 * availability and location (scoped_query() adds those).
	 *
	 * @param array<string, mixed> $params Search params (logical keys).
	 * @param array<string, mixed> $atts   Shortcode attributes.
	 * @return array<string, mixed>
	 */
	private static function query_args(array $params, array $atts): array
	{
		// An empty value in a list filter (`fuel_type[]=`) is no filter -- not a
		// search for vehicles whose fuel is ''.
		foreach (array( 'fuel_type', 'transmission', 'seats', 'brand' ) as $key) {
			if (isset($params[ $key ]) && is_array($params[ $key ])) {
				$params[ $key ] = array_values(array_filter(array_map('strval', $params[ $key ]), static fn(string $v): bool => '' !== $v));
			}
		}

		$args = array(
			'post_type'      => PT_Vehicle::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => (int) $atts['results_per_page'],
			'paged'          => $params['page'] ?? 1,
			'meta_query'     => array(),
		);

		// Only show active vehicles (excludes maintenance/inactive).
		$args['meta_query'][] = \MHMRentiva\Admin\Core\Utilities\MetaQueryHelper::get_active_vehicle_meta_query();

		// ⭐ Submission Integrity: Enforce minimum rental days
		$pickup_date = ! empty($params['pickup_date']) ? $params['pickup_date'] : ( ! empty($params['start_date']) ? $params['start_date'] : '' );
		$return_date = ! empty($params['return_date']) ? $params['return_date'] : ( ! empty($params['end_date']) ? $params['end_date'] : '' );

		if (! empty($pickup_date) && ! empty($return_date)) {
			$min_days = BookingForm::min_rental_days();
			$start_ts = strtotime($pickup_date);
			$end_ts   = strtotime($return_date);

			if ($start_ts && $end_ts) {
				$diff_days = round(( $end_ts - $start_ts ) / ( 60 * 60 * 24 ));
				if ($diff_days < $min_days) {
					// Logic: If user bypassed JS and sent invalid date range
					// Force an impossible condition to return no results safely
					$args['post__in'] = array( 0 );
				}
			}
		}

		// Keyword search
		if (! empty($params['keyword'])) {
			$args['s'] = $params['keyword'];
		}

		// Price range
		if ($params['min_price'] > 0 || $params['max_price'] > 0) {
			$price_query = array(
				'key'  => '_mhmrentiva_price_per_day',
				'type' => 'NUMERIC',
			);

			if ($params['min_price'] > 0 && $params['max_price'] > 0) {
				$price_query['value']   = array( $params['min_price'], $params['max_price'] );
				$price_query['compare'] = 'BETWEEN';
			} elseif ($params['min_price'] > 0) {
				$price_query['value']   = $params['min_price'];
				$price_query['compare'] = '>=';
			} elseif ($params['max_price'] > 0) {
				$price_query['value']   = $params['max_price'];
				$price_query['compare'] = '<=';
			}

			$args['meta_query'][] = $price_query;
		}

		// Fuel type (process as array)
		if (! empty($params['fuel_type'])) {
			$fuel_values          = is_array($params['fuel_type']) ? $params['fuel_type'] : array( $params['fuel_type'] );
			$args['meta_query'][] = array(
				'key'     => '_mhmrentiva_fuel_type',
				'value'   => $fuel_values,
				'compare' => 'IN',
			);
		}

		// Transmission type (process as array)
		if (! empty($params['transmission'])) {
			$transmission_values  = is_array($params['transmission']) ? $params['transmission'] : array( $params['transmission'] );
			$args['meta_query'][] = array(
				'key'     => '_mhmrentiva_transmission',
				'value'   => $transmission_values,
				'compare' => 'IN',
			);
		}

		// Seat count (process as array)
		if (! empty($params['seats'])) {
			$seats_values         = is_array($params['seats']) ? $params['seats'] : array( $params['seats'] );
			$args['meta_query'][] = array(
				'key'     => '_mhmrentiva_seats',
				'value'   => $seats_values,
				'compare' => 'IN',
			);
		}

		// Brand (process as array)
		if (! empty($params['brand'])) {
			$brand_values         = is_array($params['brand']) ? $params['brand'] : array( $params['brand'] );
			$args['meta_query'][] = array(
				'key'     => '_mhmrentiva_brand',
				'value'   => $brand_values,
				'compare' => 'IN',
			);
		}

		// Vehicle category (term ids).
		$category_ids = array_values(array_filter(array_map('intval', (array) ( $params['category'] ?? array() ))));
		if (array() !== $category_ids) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- the category filter (K4) is a taxonomy filter.
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'mhmrentiva_vehicle_category',
					'field'    => 'term_id',
					'terms'    => $category_ids,
				),
			);
		}

		// Year range
		if ($params['year_min'] > 0 || $params['year_max'] > 0) {
			$year_query = array(
				'key'  => '_mhmrentiva_year',
				'type' => 'NUMERIC',
			);

			if ($params['year_min'] > 0 && $params['year_max'] > 0) {
				$year_query['value']   = array( $params['year_min'], $params['year_max'] );
				$year_query['compare'] = 'BETWEEN';
			} elseif ($params['year_min'] > 0) {
				$year_query['value']   = $params['year_min'];
				$year_query['compare'] = '>=';
			} elseif ($params['year_max'] > 0) {
				$year_query['value']   = $params['year_max'];
				$year_query['compare'] = '<=';
			}

			$args['meta_query'][] = $year_query;
		}

		// Mileage
		if ($params['mileage_max'] > 0) {
			$args['meta_query'][] = array(
				'key'     => '_mhmrentiva_mileage',
				'value'   => $params['mileage_max'],
				'type'    => 'NUMERIC',
				'compare' => '<=',
			);
		}

		// Sorting
		//
		// price_asc/price_desc/year_desc used to set a flat top-level
		// 'meta_key' + 'orderby' =>
		// 'meta_value_num'. WP_Query's own WP_Meta_Query::parse_query_vars()
		// auto-synthesizes an unnamed clause from that flat 'meta_key' --
		// array('key' => $meta_key), no compare/value -- which already
		// requires the key to exist (INNER JOIN + WHERE meta_key = 'x', the
		// same fragment a 'compare' => 'EXISTS' clause produces). Naming that
		// same clause and referencing it from a compound 'orderby' array
		// removes the SlowDBQuery-flagged 'meta_key' literal without changing
		// which rows match. Matches the convention this codebase's own
		// RatingSortHelper::apply_sort_args() already uses for meta-based
		// sorting. 'DECIMAL(10,2)' (not 'NUMERIC'/SIGNED) on the price clause
		// preserves cents precision -- see
		// tests/Unit/Frontend/Shortcodes/SearchResultsMetaSortTest.php's
		// fractional-price case, which would fail under a truncating cast.
		switch ($params['sort']) {
			case 'price_asc':
				$args['meta_query']['price_sort'] = array(
					'key'     => '_mhmrentiva_price_per_day',
					'type'    => 'DECIMAL(10,2)',
					'compare' => 'EXISTS',
				);
				$args['orderby']                  = array( 'price_sort' => 'ASC' );
				break;
			case 'price_desc':
				$args['meta_query']['price_sort'] = array(
					'key'     => '_mhmrentiva_price_per_day',
					'type'    => 'DECIMAL(10,2)',
					'compare' => 'EXISTS',
				);
				$args['orderby']                  = array( 'price_sort' => 'DESC' );
				break;
			case 'name_asc':
				$args['orderby'] = 'title';
				$args['order']   = 'ASC';
				break;
			case 'name_desc':
				$args['orderby'] = 'title';
				$args['order']   = 'DESC';
				break;
			case 'year_desc':
				$args['meta_query']['year_sort'] = array(
					'key'     => '_mhmrentiva_year',
					'type'    => 'NUMERIC',
					'compare' => 'EXISTS',
				);
				$args['orderby']                 = array( 'year_sort' => 'DESC' );
				break;
			default: // relevance
				$args['orderby'] = 'title';
				$args['order']   = 'ASC';
				break;
		}

		// Meta query relation
		if (count($args['meta_query']) > 1) {
			$args['meta_query']['relation'] = 'AND';
		}

		/**
		 * Filters rental vehicle query arguments without coupling Lite to an
		 * optional service implementation.
		 */
		$args = \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::restrict_query_to_service($args, 'rental');
		return (array) apply_filters('mhmrentiva_vehicle_query_args', $args, 'search_results', $params, $atts);
	}

	/**
	 * Formats vehicle data using canonical VehiclesList logic
	 *
	 * @param int $vehicle_id Vehicle ID
	 * @param array $atts Shortcode attributes
	 * @return array Standardized vehicle data
	 */
	private static function format_vehicle_data(int $vehicle_id, array $atts): array
	{
		// Default atts for canonical formatting if missing.
		// Use 'large' to match VehiclesGrid / VehiclesList / FeaturedVehicles —
		// 'medium' (300px) produced visibly blurry cards in the search results.
		$defaults    = array(
			'max_features' => 5,
			'image_size'   => 'large',
			'price_format' => 'daily',
		);
		$merged_atts = wp_parse_args($atts, $defaults);

		return VehiclesList::get_vehicle_data_for_shortcode($vehicle_id, $merged_atts);
	}

	/**
	 * Gets filter options
	 */
	/**
	 * Distinct meta values across the PUBLIC vehicle catalogue.
	 *
	 * The INNER JOIN below is the whole point of this method, and the reason
	 * there is a method at all: the six facet queries that used to sit inline in
	 * get_filter_options() each scanned postmeta on the meta_key alone, with no
	 * join to posts, so they described the entire database instead of the
	 * catalogue. Written once, the restriction cannot be present in five queries
	 * and missing from the sixth.
	 *
	 * Everything interpolated here is a table name from $wpdb; the only
	 * caller-supplied value is a placeholder. Ordering is applied in PHP rather
	 * than in an interpolated ORDER BY, so the SQL stays a single literal.
	 *
	 * @param string $meta_key      Meta key to collect.
	 * @param bool   $numeric_order Sort numerically rather than lexically.
	 * @return array<int, string>
	 */
	private static function public_meta_values(string $meta_key, bool $numeric_order = false): array
	{
		global $wpdb;

		$values = (array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_value
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                     AND p.post_type = 'mhmrentiva_vehicle'
                     AND p.post_status = 'publish'
                     AND p.post_password = ''
                 WHERE pm.meta_key = %s
                 AND pm.meta_value != ''",
				$meta_key
			)
		);

		if ($numeric_order) {
			usort($values, static fn($a, $b): int => (int) $a <=> (int) $b);
		} else {
			sort($values, SORT_NATURAL | SORT_FLAG_CASE);
		}

		return $values;
	}

	/**
	 * Min/max of a numeric meta key across the PUBLIC vehicle catalogue.
	 *
	 * Same join, same reason. One DECIMAL cast serves both callers: the year
	 * facet reads the result through `(int)` and the price facet through
	 * `(float)`, so a single wide numeric type keeps this a literal query rather
	 * than an interpolated one.
	 *
	 * @param string $meta_key Meta key to measure.
	 * @param string $pattern  REGEXP guard rejecting non-numeric rows.
	 * @return array{min: float, max: float}|null Null when nothing matches.
	 */
	private static function public_meta_range(string $meta_key, string $pattern): ?array
	{
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
                     MIN(CAST(pm.meta_value AS DECIMAL(20,4))) as min_value,
                     MAX(CAST(pm.meta_value AS DECIMAL(20,4))) as max_value
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                     AND p.post_type = 'mhmrentiva_vehicle'
                     AND p.post_status = 'publish'
                     AND p.post_password = ''
                 WHERE pm.meta_key = %s
                 AND pm.meta_value != ''
                 AND pm.meta_value REGEXP %s",
				$meta_key,
				$pattern
			)
		);

		if (! is_object($row) || null === $row->min_value) {
			return null;
		}

		return array(
			'min' => (float) $row->min_value,
			'max' => (float) $row->max_value,
		);
	}

	/**
	 * Gets filter options
	 */
	private static function get_filter_options(array $current_params): array
	{
		// Caching Strategy: Search filters are global and expensive to compute.
		// We cache them for 24 hours, invalidated on vehicle save/update.
		// _v2: the sweep that restricted this facet query to the published
		// catalogue (see the publish-only get_posts() a few lines below) did
		// not bump the key, so a transient cached before deploy could keep
		// serving unpublished-vehicle facet values for up to 24h after it.
		$cache_key = self::FILTERS_CACHE_KEY;
		$cached    = get_transient($cache_key);

		if (false !== $cached && is_array($cached)) {
			// Validation: Ensure cache isn't effectively empty if it shouldn't be
			if (! empty($cached['brands']) || ! empty($cached['fuel_types'])) {
				return $cached;
			}
		}

		global $wpdb;

		/*
		 * Every facet below is rendered into the PUBLIC search sidebar and then
		 * cached for 24h in a role-agnostic transient.
		 *
		 * They used to scan the postmeta table on the meta_key alone, with no
		 * join to the posts table at all -- so they described the whole database
		 * rather than the catalogue. A draft vehicle's brand appeared in the
		 * public brand list; a private vehicle's price set the price slider's
		 * maximum; a trashed vehicle's year widened the year range. Measured on a
		 * seeded draft priced 99999, the anonymous sidebar rendered max="99999".
		 *
		 * The six near-identical queries are now two parameterised helpers, so
		 * the publish-only restriction is written down ONCE. Six copies of a rule
		 * is six chances to omit it from one of them, which is how the facets
		 * came to disagree with the search results they sit next to.
		 */

		// Fuel types - Get keys and convert to labels
		$fuel_type_keys = self::public_meta_values('_mhmrentiva_fuel_type');

		$fuel_types_map = \MHMRentiva\Admin\Vehicle\Meta\VehicleMeta::get_fuel_types();
		$fuel_types     = array();
		if (! empty($fuel_type_keys)) {
			foreach ($fuel_type_keys as $key) {
				if (isset($fuel_types_map[ $key ])) {
					$fuel_types[ $key ] = $fuel_types_map[ $key ];
				} else {
					$fuel_types[ $key ] = $key; // Fallback to key if label not found
				}
			}
		}

		// Transmission types - Get keys and convert to labels
		$transmission_keys = self::public_meta_values('_mhmrentiva_transmission');

		$transmissions_map = \MHMRentiva\Admin\Vehicle\Meta\VehicleMeta::get_transmission_types();
		$transmissions     = array();
		if (! empty($transmission_keys)) {
			foreach ($transmission_keys as $key) {
				if (isset($transmissions_map[ $key ])) {
					$transmissions[ $key ] = $transmissions_map[ $key ];
				} else {
					$transmissions[ $key ] = $key; // Fallback to key if label not found
				}
			}
		}

		// Seat counts
		$seats = self::public_meta_values('_mhmrentiva_seats', true);

		// Brands
		$brands = self::public_meta_values('_mhmrentiva_brand');

		// Year range
		$year_range = self::public_meta_range('_mhmrentiva_year', '^[0-9]+$');

		// Price range
		$price_range = self::public_meta_range('_mhmrentiva_price_per_day', '^[0-9]+(\.[0-9]+)?$');

		// Categories that hold a published vehicle (WordPress counts published
		// objects only); no counts on the page (K4).
		$categories = array();
		$terms      = get_terms(
			array(
				'taxonomy'   => 'mhmrentiva_vehicle_category',
				'hide_empty' => true,
			)
		);
		if (is_array($terms)) {
			foreach ($terms as $term) {
				if ($term instanceof \WP_Term) {
					$categories[] = array(
						'id'   => (int) $term->term_id,
						'name' => (string) $term->name,
					);
				}
			}
		}

		$data = array(
			'fuel_types'    => $fuel_types ?: array(),
			'transmissions' => $transmissions ?: array(),
			'seats'         => $seats ?: array(),
			'brands'        => $brands ?: array(),
			'categories'    => $categories,
			'year_range'    => array(
				'min' => (int) ( $year_range['min'] ?? 1990 ),
				'max' => (int) ( $year_range['max'] ?? gmdate('Y') ),
			),
			'price_range'   => array(
				'min' => (float) ( $price_range['min'] ?? 0 ),
				'max' => (float) ( $price_range['max'] ?? 10000 ),
			),
		);

		// Cache Logic: 24 Hours
		// Empty State Protection: If no brands/fuel types found, cache for only 5 minutes
		// to prevent "permanent empty state" if cache warms up before vehicles are added.
		$is_empty = empty($data['brands']) && empty($data['fuel_types']);
		$ttl      = $is_empty ? 300 : DAY_IN_SECONDS;

		set_transient($cache_key, $data, $ttl);

		return $data;
	}

	/**
	 * The facets plus the pickup points to choose from -- read fresh from
	 * LocationContext, never from the day-long facet cache (m9). A site with
	 * 0 or 1 rental location has nothing to choose: no location group.
	 *
	 * @param array<string, mixed> $current_params Search params (logical keys).
	 * @return array<string, mixed>
	 */
	private static function filter_options(array $current_params): array
	{
		$options = self::get_filter_options($current_params);
		unset($options['locations']);
		$locations            = LocationContext::rental_locations();
		$options['locations'] = count($locations) >= 2 ? $locations : array();

		return $options;
	}

	/**
	 * The results depend on every filter var and the page as well as the window.
	 *
	 * @return array<string, mixed>
	 */
	protected static function cache_request_factors(): array
	{
		$factors = parent::cache_request_factors();
		foreach (self::FILTER_PARAMS as $key) {
			$factors['filters'][ $key ] = get_query_var(self::query_var($key), null);
		}
		// The pickup time moves the window, so it changes which cars are free.
		$factors['filters']['pickup_time'] = get_query_var(self::query_var('pickup_time'), null);
		$factors['page']                   = get_query_var('page', null);

		return $factors;
	}

	/**
	 * Filters the visitor narrowed the results with (the window, the sort and
	 * the pickup point are not filters: the point is where the visitor is,
	 * and a search there must never say "remove a filter").
	 *
	 * @param array<string, mixed> $params Search params (logical keys).
	 */
	public static function has_active_filters(array $params): bool
	{
		foreach (array( 'keyword', 'fuel_type', 'transmission', 'seats', 'brand', 'category' ) as $key) {
			$value = $params[ $key ] ?? '';
			if (is_array($value) ? array() !== array_filter($value, static fn($v): bool => '' !== (string) $v && '0' !== (string) $v) : '' !== (string) $value) {
				return true;
			}
		}
		foreach (array( 'min_price', 'max_price', 'year_min', 'year_max', 'mileage_max' ) as $key) {
			if ( (int) ( $params[ $key ] ?? 0 ) > 0) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Which empty state the page shows, or null when the results need none:
	 * `not_searched` (no window: totals and availability need dates -- the
	 * catalogue still lists), `filtered_out` (filters emptied the list and
	 * nothing below matches either), `none_here` (nothing free at the picked
	 * pickup point), `no_results` (nothing free in the window) or
	 * `no_vehicles` (the catalogue is empty).
	 *
	 * A booked match or a car at another pickup point of the city is a match
	 * (M11): with one of them on the page, "remove a filter" would contradict
	 * the cars listed under it.
	 *
	 * @param array<string, mixed>              $params Search params (logical keys).
	 * @param array{nearby?: int, booked?: int} $extras Cars in the extra sections.
	 */
	public static function empty_state(array $params, int $total, array $extras = array()): ?string
	{
		if ($total <= 0) {
			$elsewhere = (int) ( $extras['nearby'] ?? 0 ) + (int) ( $extras['booked'] ?? 0 );
			if (0 === $elsewhere && self::has_active_filters($params)) {
				return 'filtered_out';
			}
			// A picked pickup point is where the visitor is: name it. Without
			// dates "nothing there yet", never "the catalogue is empty".
			$dated  = '' !== (string) ( $params['pickup_date'] ?? '' ) || '' !== (string) ( $params['return_date'] ?? '' );
			$picked = array() !== (array) ( $params['pickup_location'] ?? array() );
			if ($picked && null !== self::window($params)) {
				return 'none_here';
			}
			if ($picked && ! $dated) {
				return 'none_yet';
			}
			// No dates given and still nothing: the catalogue is empty. Dates given
			// (even a range the minimum rental refuses) ruled the cars out.
			return $dated ? 'no_results' : 'no_vehicles';
		}

		return null === self::window($params) ? 'not_searched' : null;
	}

	/**
	 * Start/end timestamps of the search window, or null without one.
	 *
	 * The return is at the pickup's own time, as on the booking form. A
	 * missing or broken time is the default pickup time -- it must not switch
	 * availability off and list every booked car as free.
	 *
	 * @param array<string, mixed> $params Search params (logical keys).
	 * @return array{0: int, 1: int}|null
	 */
	private static function window(array $params): ?array
	{
		$pickup = is_scalar($params['pickup_date'] ?? null) ? (string) $params['pickup_date'] : '';
		$return = is_scalar($params['return_date'] ?? null) ? (string) $params['return_date'] : '';
		if ('' === $pickup || '' === $return) {
			return null;
		}

		$time = is_scalar($params['pickup_time'] ?? null) ? (string) $params['pickup_time'] : '';
		foreach (array( $time, (string) apply_filters('mhmrentiva_default_pickup_time', '10:00'), '10:00' ) as $candidate) {
			if (1 === preg_match('/^\d{2}:\d{2}$/', $candidate) && null !== SiteTime::instant($pickup, $candidate)) {
				$time = $candidate;
				break;
			}
		}

		$start = SiteTime::instant($pickup, $time);
		$end   = SiteTime::instant($return, $time);

		return null === $start || null === $end || $end <= $start ? null : array( $start, $end );
	}

	/**
	 * The results header: "6 vehicles", "12 Jul – 15 Jul", 3 days. Range and
	 * days are empty without a window; days come from the booking form's count.
	 *
	 * @param array<string, mixed> $params Search params (logical keys).
	 * @return array{count: string, range: string, days: int}
	 */
	public static function summary(array $params, int $total): array
	{
		/* translators: %s: number of vehicles. */
		$count  = sprintf(_n('%s vehicle', '%s vehicles', $total, 'mhm-rentiva'), number_format_i18n($total));
		$window = self::window($params);
		if (null === $window) {
			return array(
				'count' => $count,
				'range' => '',
				'days'  => 0,
			);
		}

		return array(
			'count' => $count,
			/* translators: 1: pickup date, 2: return date (both short, e.g. "12 Jul"). */
			'range' => sprintf(__('%1$s – %2$s', 'mhm-rentiva'), wp_date('j M', $window[0]), wp_date('j M', $window[1])),
			'days'  => \MHMRentiva\Admin\Booking\Helpers\Util::rental_days($window[0], $window[1]),
		);
	}

	/**
	 * One chip per active filter value: the field it clears and its label.
	 *
	 * @param array<string, mixed> $params         Search params (logical keys).
	 * @param array<string, mixed> $filter_options get_filter_options() shape.
	 * @return list<array{name: string, value: string, label: string}>
	 */
	public static function active_chips(array $params, array $filter_options): array
	{
		$chips = array();
		$add   = static function (string $key, string $value, string $label, bool $list = true) use (&$chips): void {
			$chips[] = array(
				'name'  => self::query_var($key) . ( $list ? '[]' : '' ),
				'value' => $value,
				'label' => $label,
			);
		};

		$category_names = array();
		foreach ( (array) ( $filter_options['categories'] ?? array() ) as $category) {
			$category_names[ (int) ( $category['id'] ?? 0 ) ] = (string) ( $category['name'] ?? '' );
		}
		foreach ( (array) ( $params['category'] ?? array() ) as $id) {
			if (isset($category_names[ (int) $id ])) {
				$add('category', (string) (int) $id, $category_names[ (int) $id ]);
			}
		}

		$location_names = array();
		foreach ( (array) ( $filter_options['locations'] ?? array() ) as $location) {
			if (is_object($location) && isset($location->id, $location->name)) {
				$location_names[ (int) $location->id ] = (string) $location->name;
			}
		}
		foreach ( (array) ( $params['pickup_location'] ?? array() ) as $id) {
			if (isset($location_names[ (int) $id ])) {
				$add('pickup_location', (string) (int) $id, $location_names[ (int) $id ]);
			}
		}

		$labelled = array(
			'brand'        => null,
			'fuel_type'    => (array) ( $filter_options['fuel_types'] ?? array() ),
			'transmission' => (array) ( $filter_options['transmissions'] ?? array() ),
			'seats'        => null,
		);
		foreach ($labelled as $key => $labels) {
			foreach (array_filter( (array) ( $params[ $key ] ?? array() ), static fn($v): bool => '' !== (string) $v) as $value) {
				$value = (string) $value;
				if ('seats' === $key) {
					/* translators: %s: number of seats. */
					$label = sprintf(__('%s seats', 'mhm-rentiva'), $value);
				} else {
					$label = null !== $labels && isset($labels[ $value ]) ? (string) $labels[ $value ] : $value;
				}
				$add($key, $value, $label);
			}
		}

		$ranges = array(
			/* translators: %s: formatted minimum daily price. */
			'min_price'   => __('From %s/day', 'mhm-rentiva'),
			/* translators: %s: formatted maximum daily price. */
			'max_price'   => __('Up to %s/day', 'mhm-rentiva'),
			/* translators: %s: model year. */
			'year_min'    => __('From %s', 'mhm-rentiva'),
			/* translators: %s: model year. */
			'year_max'    => __('Up to %s', 'mhm-rentiva'),
			/* translators: %s: mileage in km. */
			'mileage_max' => __('Up to %s km', 'mhm-rentiva'),
		);
		foreach ($ranges as $key => $format) {
			$value = (int) ( $params[ $key ] ?? 0 );
			if ($value > 0) {
				$shown = in_array($key, array( 'min_price', 'max_price' ), true) ? wp_strip_all_tags( (string) \MHMRentiva\Admin\Core\CurrencyHelper::format_price($value)) : ( 'mileage_max' === $key ? number_format_i18n($value) : (string) $value );
				$add($key, (string) $value, sprintf($format, $shown), false);
			}
		}

		$keyword = (string) ( is_scalar($params['keyword'] ?? '') ? $params['keyword'] ?? '' : '' );
		if ('' !== $keyword) {
			$add('keyword', $keyword, $keyword, false);
		}

		return $chips;
	}

	/**
	 * The "Filters (N)" count: the chips minus the pickup point's, which is
	 * where the visitor is, not a filter -- "Clear" keeps it (m7).
	 *
	 * @param list<array{name: string, value: string, label: string}> $chips active_chips().
	 */
	public static function filter_count(array $chips): int
	{
		$location = self::query_var('pickup_location') . '[]';

		return count(array_filter($chips, static fn(array $chip): bool => $location !== ( $chip['name'] ?? '' )));
	}

	/**
	 * The notice region's content: the "choose dates" notice, or "nothing
	 * free at this pickup point" (with a line pointing at the sections below
	 * when the city has other points with cars). '' otherwise.
	 *
	 * @param array<string, mixed> $params  Search params (logical keys).
	 * @param array<string, mixed> $results perform_search() shape.
	 */
	public static function notice_html(array $params, array $results, string $empty_state, string $change_url): string
	{
		if ('not_searched' === $empty_state) {
			return Templates::render('partials/search/rvs-empty', array(
				'kind'       => 'not_searched',
				'change_url' => $change_url,
			), true);
		}
		if (! in_array($empty_state, self::NOTICE_STATES, true)) {
			return '';
		}

		$counts = self::section_counts($results);
		$names  = self::selected_names($params);
		$single = 1 === count($names);
		if ('none_here' === $empty_state) {
			$range = self::summary($params, 0)['range'];
			$title = $single
				/* translators: 1: pickup point name, 2: the searched dates, e.g. "7 Dec – 10 Dec". */
				? sprintf(__('No cars are free at the %1$s pickup point for %2$s.', 'mhm-rentiva'), $names[0], $range)
				/* translators: %s: the searched dates, e.g. "7 Dec – 10 Dec". */
				: sprintf(__('No cars are free at the selected pickup points for %s.', 'mhm-rentiva'), $range);
		} else {
			$title = $single
				/* translators: %s: pickup point name. */
				? sprintf(__('There are no cars at the %s pickup point yet.', 'mhm-rentiva'), $names[0])
				: __('There are no cars at the selected pickup points yet.', 'mhm-rentiva');
		}

		return Templates::render('partials/search/rvs-empty', array(
			'kind'       => $empty_state,
			'change_url' => $change_url,
			'title'      => $title,
			// The line pointing at "the other points ... and the car you looked
			// for below" is true only when both sections are there.
			'has_both'   => $counts['nearby'] > 0 && $counts['booked'] > 0,
			'has_more'   => $counts['nearby'] + $counts['booked'] > 0,
		), true);
	}

	/**
	 * The "other pickup points in the same city" section, or '' without cars.
	 *
	 * @param array<string, mixed> $params   Search params (logical keys).
	 * @param array<string, mixed> $results  perform_search() shape.
	 * @param array<string, mixed> $atts     Card toggles and layout.
	 * @param string               $instance The results block's id prefix.
	 */
	public static function nearby_html(array $params, array $results, array $atts, string $instance): string
	{
		$items = (array) ( $results['nearby'] ?? array() );
		if (array() === $items) {
			return '';
		}

		$layout = 'list' === ( $atts['layout'] ?? 'grid' ) ? 'list' : 'grid';
		$cards  = '';
		foreach ($items as $item) {
			$cards .= self::render_vehicle_card($item['vehicle'], $layout, $atts, self::card_dates($params), array( 'where' => (string) $item['where'] ));
		}

		$cities = LocationContext::cities_of( (array) ( $params['pickup_location'] ?? array() ));

		return Templates::render('partials/search/rvs-section', array(
			'kind'   => 'nearby',
			'id'     => $instance . '-nearby',
			'title'  => self::nearby_title(),
			/* translators: 1: city (several are joined into one), 2: the section's result summary, e.g. "2 vehicles · 7 Dec – 10 Dec · 3 days". */
			'sub'    => sprintf(__('%1$s · %2$s', 'mhm-rentiva'), self::join_line($cities), self::summary_text(self::summary($params, count($items)))),
			'layout' => $layout,
			'cards'  => $cards,
		), true);
	}

	/**
	 * The "booked for these dates" section, or '' without cars.
	 *
	 * @param array<string, mixed> $params   Search params (logical keys).
	 * @param array<string, mixed> $results  perform_search() shape.
	 * @param array<string, mixed> $atts     Card toggles and layout.
	 * @param string               $instance The results block's id prefix.
	 */
	public static function booked_html(array $params, array $results, array $atts, string $instance): string
	{
		$items = (array) ( $results['booked'] ?? array() );
		if (array() === $items) {
			return '';
		}

		$layout = 'list' === ( $atts['layout'] ?? 'grid' ) ? 'list' : 'grid';
		$cards  = '';
		foreach ($items as $item) {
			$cards .= self::render_vehicle_card($item['vehicle'], $layout, $atts, self::card_dates($params), array(
				'booked' => array(
					'earliest' => $item['earliest'],
					'days'     => (int) $item['days'],
				),
			));
		}

		return Templates::render('partials/search/rvs-section', array(
			'kind'   => 'booked',
			'id'     => $instance . '-booked',
			'title'  => self::booked_title($params),
			'sub'    => __('Matches your search, but not free on your dates', 'mhm-rentiva'),
			'layout' => $layout,
			'cards'  => $cards,
		), true);
	}

	private static function nearby_title(): string
	{
		return __('Other pickup points in the same city', 'mhm-rentiva');
	}

	/**
	 * "Booked for these dates at the X pickup point" when one point is picked
	 * on a site with a choice; "Booked for these dates" otherwise.
	 *
	 * @param array<string, mixed> $params Search params (logical keys).
	 */
	private static function booked_title(array $params): string
	{
		$names = self::selected_names($params);
		if (1 === count($names)) {
			/* translators: %s: pickup point name. */
			return sprintf(__('Booked for these dates at the %s pickup point', 'mhm-rentiva'), $names[0]);
		}

		return __('Booked for these dates', 'mhm-rentiva');
	}

	/**
	 * The summary partial's line as plain text: "2 vehicles · 7 Dec – 10 Dec · 3 days".
	 *
	 * @param array{count: string, range: string, days: int} $summary summary().
	 */
	private static function summary_text(array $summary): string
	{
		$parts = array( $summary['count'] );
		if ('' !== $summary['range']) {
			$parts[] = $summary['range'];
		}
		if ($summary['days'] > 0) {
			/* translators: %s: number of rental days. */
			$parts[] = sprintf(_n('%s day', '%s days', $summary['days'], 'mhm-rentiva'), number_format_i18n($summary['days']));
		}

		return self::join_line($parts);
	}

	/**
	 * Items of one line joined through a translatable format:
	 * "2 vehicles · 7 Dec – 10 Dec · 3 days", "İstanbul · Ankara".
	 *
	 * @param list<string> $parts
	 */
	private static function join_line(array $parts): string
	{
		$line = (string) array_shift($parts);
		foreach ($parts as $part) {
			/* translators: joins two items of one line, e.g. two city names or the parts of a results summary ("2 vehicles · 7 Dec – 10 Dec"). 1: the line so far, 2: the next item. */
			$line = sprintf(_x('%1$s · %2$s', 'joined items of one line', 'mhm-rentiva'), $line, $part);
		}

		return $line;
	}

	/**
	 * What the status region says after a re-render: the count, then each
	 * extra section with its count -- "2 vehicles. Booked for these dates: 1 vehicle."
	 *
	 * @param array<string, mixed> $params  Search params (logical keys).
	 * @param array<string, mixed> $results perform_search() shape.
	 */
	public static function announcement(array $params, array $results): string
	{
		$count  = self::summary($params, (int) ( $results['total'] ?? 0 ))['count'];
		$counts = self::section_counts($results);
		/* translators: %s: number of vehicles. */
		$cars = static fn(int $n): string => sprintf(_n('%s vehicle', '%s vehicles', $n, 'mhm-rentiva'), number_format_i18n($n));
		/* translators: 1: a results section's heading, e.g. "Booked for these dates"; 2: number of vehicles in it, e.g. "1 vehicle". */
		$section  = static fn(string $title, int $n): string => sprintf(__('%1$s: %2$s', 'mhm-rentiva'), $title, $cars($n));
		$sections = array();
		if ($counts['nearby'] > 0) {
			$sections[] = $section(self::nearby_title(), $counts['nearby']);
		}
		if ($counts['booked'] > 0) {
			$sections[] = $section(self::booked_title($params), $counts['booked']);
		}
		if (array() === $sections) {
			return $count;
		}

		// WordPress's own translatable list ("a and b").
		/* translators: 1: number of vehicles in the results, e.g. "2 vehicles"; 2: the extra sections with their counts, e.g. "Booked for these dates: 1 vehicle". */
		return sprintf(__('%1$s. %2$s.', 'mhm-rentiva'), $count, wp_sprintf('%l', $sections));
	}

	/**
	 * The sorts the visitor can pick, value => label.
	 *
	 * The keys are exactly the values the sort switch in perform_search() applies
	 * (relevance is its default branch). The editors' default sort controls offer
	 * the same list (spec 3A §2.5 E).
	 *
	 * @return array<string,string>
	 */
	public static function get_sorting_options(bool $dated = false): array
	{
		// With a search window the cards show the period total; price sorts
		// order by it (the daily price times the same multipliers for every car).
		return array(
			'relevance'  => __('Most Relevant', 'mhm-rentiva'),
			'price_asc'  => $dated ? __('Total price: low to high', 'mhm-rentiva') : __('Price: Low to High', 'mhm-rentiva'),
			'price_desc' => $dated ? __('Total price: high to low', 'mhm-rentiva') : __('Price: High to Low', 'mhm-rentiva'),
			'name_asc'   => __('Name: A to Z', 'mhm-rentiva'),
			'name_desc'  => __('Name: Z to A', 'mhm-rentiva'),
			'year_desc'  => __('Newest First', 'mhm-rentiva'),
		);
	}

	/**
	 * AJAX: Update filter results
	 */
	public static function ajax_filter_results(): void
	{
		check_ajax_referer('mhmrentiva_search_results', 'nonce');

		$req = VerifiedRequest::from($_POST);

		$payload = array();

		try {
			// Get filters from POST parameters.
			//
			// The refine-filters form posts the SAME field names the bookmarkable
			// search URL carries -- `.rv-filters-form` is serialized straight into
			// this payload -- so these keys go through query_var() too. Field name,
			// URL param and whitelist entry stay one string, defined in one place.
			// `page` stays bare: it is core's query var on the URL side and this
			// payload mirrors it.
			$search_params = array(
				'keyword'         => $req->text(self::query_var('keyword')),
				'pickup_date'     => $req->text(self::query_var('pickup_date')),
				'return_date'     => $req->text(self::query_var('return_date')),
				// The window's time: the search and the cards' links both use it.
				'pickup_time'     => $req->text(self::query_var('pickup_time')),
				'min_price'       => $req->int(self::query_var('min_price')),
				'max_price'       => $req->int(self::query_var('max_price')),
				'fuel_type'       => $req->textOrList(self::query_var('fuel_type')),
				'transmission'    => $req->textOrList(self::query_var('transmission')),
				'seats'           => $req->textOrList(self::query_var('seats')),
				'brand'           => $req->textOrList(self::query_var('brand')),
				'year_min'        => $req->int(self::query_var('year_min')),
				'year_max'        => $req->int(self::query_var('year_max')),
				'mileage_max'     => $req->int(self::query_var('mileage_max')),
				'sort'            => $req->text(self::query_var('sort'), 'relevance'),
				'page'            => $req->int('page', 1),
				// Only a real choice counts: none with 0 or 1 rental location (m8).
				'pickup_location' => LocationContext::effective_selected($req->intList(self::query_var('pickup_location'))),
				'category'        => $req->intList(self::query_var('category')),
			);

			$atts = array(
				'layout'               => $req->text('layout', 'grid'),
				// Clamped to the range the search widget declares (1-100). This
				// endpoint is nopriv and the value becomes posts_per_page, so an
				// unbounded page size lets any visitor ask for the whole catalogue.
				'results_per_page'     => $req->intRange('per_page', 12, 1, 100),
				'show_favorite_button' => $req->text('show_favorite_button', '1'),
				'show_compare_button'  => $req->text('show_compare_button', '1'),
				// The canonical key; search-results.js sent show_booking_btn, which
				// this handler never read, so every re-render lost the toggle.
				'show_booking_button'  => $req->text('show_booking_button', $req->text('show_booking_btn', '1')),
				'show_price'           => $req->text('show_price', '1'),
				'show_title'           => $req->text('show_title', '1'),
				'show_features'        => $req->text('show_features', '1'),
				'show_rating'          => $req->text('show_rating', '1'),
				'show_badges'          => $req->text('show_badges', '1'),
			);

			$results = self::perform_search($search_params, $atts);

			// Built inside the try because rendering can throw; ANSWERED outside
			// it, because wp_send_json_* ends the request through wp_die() and a
			// catch around it swallows that terminator and appends a second,
			// contradictory document after the first.
			$filter_options = self::filter_options($search_params);
			$total          = (int) $results['total'];
			$counts         = self::section_counts($results);
			$empty_state    = self::empty_state($search_params, $total, $counts);
			$change_url     = self::change_url($search_params);
			$chips          = self::active_chips($search_params, $filter_options);
			// The page's own id prefix, so the sections' heading ids match it.
			$instance = sanitize_key($req->text('instance', 'rvsr'));
			$instance = '' !== $instance ? $instance : 'rvsr';

			// The header, chips, empty states and sections come from the same
			// partials as the page, so a filter change cannot make the page read
			// differently. The sections exist on page 1 only: on later pages
			// their keys are empty strings.
			$payload = array(
				'html'         => self::grid_html($search_params, $results, $atts, (string) $empty_state, $change_url),
				'nearby_html'  => self::nearby_html($search_params, $results, $atts, $instance),
				'booked_html'  => self::booked_html($search_params, $results, $atts, $instance),
				'pagination'   => self::render_pagination($results['pagination'], self::page_base_url(is_scalar($req->raw('base_url')) ? (string) $req->raw('base_url') : '')),
				'summary_html' => Templates::render('partials/search/rvs-summary', array( 'summary' => self::summary($search_params, $total) ), true),
				'chips_html'   => Templates::render('partials/search/rvs-chips', array( 'chips' => $chips ), true),
				'notice_html'  => self::notice_html($search_params, $results, (string) $empty_state, $change_url),
				'meta'         => array(
					'total'          => $total,
					'max_pages'      => (int) $results['max_pages'],
					'current_page'   => (int) $results['current_page'],
					'layout'         => $atts['layout'],
					'active_filters' => self::filter_count($chips),
					'empty_state'    => $empty_state,
					'nearby'         => $counts['nearby'],
					'booked'         => $counts['booked'],
					'announce'       => self::announcement($search_params, $results),
				),
			);
		} catch (Exception $e) {
			$debug_mode = defined('WP_DEBUG') && WP_DEBUG;

			wp_send_json_error(
				array(
					'message' => \MHMRentiva\Admin\Core\SecurityHelper::get_safe_error_message(
						$e->getMessage(),
						$debug_mode
					),
				)
			);
		}

		wp_send_json_success($payload);
	}

	/**
	 * The main grid of the AJAX re-render: the cards, or the empty state.
	 * NOTICE_STATES speak in the notice region instead, above the sections.
	 *
	 * @param array<string, mixed> $params  Search params (logical keys).
	 * @param array<string, mixed> $results perform_search() shape.
	 * @param array<string, mixed> $atts    Card toggles and layout.
	 */
	private static function grid_html(array $params, array $results, array $atts, string $empty_state, string $change_url): string
	{
		if (array() !== $results['vehicles']) {
			return self::render_vehicles_list($results['vehicles'], (string) $atts['layout'], $atts, self::card_dates($params));
		}
		if (in_array($empty_state, self::NOTICE_STATES, true)) {
			return '';
		}

		return Templates::render('partials/search/rvs-empty', array(
			'kind'       => $empty_state,
			'change_url' => $change_url,
		), true);
	}

	/**
	 * Check vehicle availability
	 *
	 * @param int $vehicle_id Vehicle ID
	 * @return array
	 */
	private static function check_vehicle_availability(int $vehicle_id): array
	{
		$status       = \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_status($vehicle_id);
		$is_available = ( $status === 'active' );

		return array(
			'is_available' => $is_available,
			'status'       => $status,
			'text'         => $is_available ? __('Available', 'mhm-rentiva') : __('Out of Order', 'mhm-rentiva'),
		);
	}

	/**
	 * Renders vehicles list
	 *
	 * @param array $vehicles Vehicles list
	 * @param string $layout Layout type
	 * @return string
	 */
	private static function render_vehicles_list(array $vehicles, string $layout, array $atts = array(), array $dates = array()): string
	{
		if (empty($vehicles)) {
			// esc_html__ rather than __: this string reaches the browser two ways, and
			// only one of them escapes. The shortcode path goes out through
			// ShortcodeServiceProvider's wp_kses() dispatcher, but the AJAX path returns
			// this markup as the `html` field of a JSON response and nothing escapes it
			// on the way. A translator's markup would land in the DOM raw.
			return '<div class="rv-no-results">' . esc_html__('No vehicles found matching your criteria.', 'mhm-rentiva') . '</div>';
		}

		$html = '';
		foreach ($vehicles as $vehicle) {
			$html .= self::render_vehicle_card($vehicle, $layout, $atts, $dates);
		}

		return $html;
	}

	/**
	 * Renders single vehicle card using canonical partial
	 *
	 * The front-end standard card (templates/partials/rvs-vehicle-card.php).
	 *
	 * @param array                 $vehicle Standardized vehicle data
	 * @param string                $layout  Layout type (grid/list)
	 * @param array                 $atts    Card toggles
	 * @param array<string, string> $dates   Search context for the period total and the
	 *                                       booking link; empty = the request's query vars
	 *                                       (an AJAX re-render passes it explicitly).
	 * @param array<string, mixed>  $extra   The search sections' additions (RvsCardView::from_vehicle()):
	 *                                       `where` (the car's pickup point) or `booked`.
	 * @return string
	 */
	public static function render_vehicle_card(array $vehicle, string $layout, array $atts = array(), array $dates = array(), array $extra = array()): string
	{
		if (empty($vehicle)) {
			return '';
		}

		$card_atts = wp_parse_args(
			$atts,
			array(
				'show_favorite_button' => true,
				'show_compare_button'  => true,
				'show_booking_button'  => true,
				'show_price'           => true,
				'show_title'           => true,
				'show_features'        => true,
				'show_rating'          => true,
				'show_badges'          => true,
				// The canvas card has no brand line (grid/list/featured default it off too).
				'show_brand'           => false,
				'booking_url'          => VehiclesList::get_booking_url(),
			)
		);

		return Templates::render(
			'partials/rvs-vehicle-card',
			array(
				'vehicle' => $vehicle,
				'layout'  => $layout,
				'atts'    => $card_atts,
				'dates'   => $dates,
				'extra'   => $extra,
			),
			true
		);
	}

	/**
	 * The search window as the card reads it (logical keys, scalars only).
	 *
	 * @param array<string, mixed> $search_params
	 * @return array<string, string>
	 */
	private static function card_dates(array $search_params): array
	{
		$dates = array();
		foreach (array( 'pickup_date', 'return_date', 'pickup_time', 'return_time' ) as $key) {
			$value = $search_params[ $key ] ?? '';
			if (is_scalar($value) && '' !== (string) $value) {
				$dates[ $key ] = (string) $value;
			}
		}

		return $dates;
	}

	/**
	 * The results page the AJAX caller is on (its links' base), kept only when
	 * it is on this site; '' otherwise.
	 */
	private static function page_base_url(string $url): string
	{
		if ('' === $url) {
			return '';
		}

		// Raw, not sanitize_text_field(): that strips the %XX octets of a
		// non-ASCII slug. esc_url_raw() + wp_validate_redirect() keep it safe.
		return remove_query_arg('page', (string) wp_validate_redirect(esc_url_raw(wp_unslash($url)), ''));
	}

	/**
	 * Page buttons (44×44): previous, the pages around the current one with the
	 * ends, next. Each link carries data-rvs-page, so the script reloads that
	 * page with the current filters; the href is the no-script fallback.
	 *
	 * @param array  $pagination Pagination data (current, total).
	 * @param string $base_url   Page the links point at; '' = the current request.
	 * @return string HTML
	 */
	public static function render_pagination(array $pagination, string $base_url = ''): string
	{
		$total   = (int) ( $pagination['total'] ?? 0 );
		$current = max(1, (int) ( $pagination['current'] ?? 1 ));
		if ($total <= 1) {
			return '';
		}

		$link = static function (int $page, string $text, string $label = '') use ($base_url): string {
			$href = '' !== $base_url ? add_query_arg('page', $page, $base_url) : add_query_arg('page', $page);
			return sprintf(
				'<a class="rvs-btn rvs-btn--secondary rvs-results__page" href="%1$s" data-rvs-page="%2$d"%3$s>%4$s</a>',
				esc_url($href),
				$page,
				'' !== $label ? ' aria-label="' . esc_attr($label) . '"' : '',
				esc_html($text)
			);
		};

		$html = '';
		if ($current > 1) {
			$html .= $link($current - 1, '‹', __('Previous page', 'mhm-rentiva'));
		}
		$shown = array_unique(array_filter(array( 1, $current - 2, $current - 1, $current, $current + 1, $current + 2, $total ), static fn(int $p): bool => $p >= 1 && $p <= $total));
		sort($shown);
		$previous = 0;
		foreach ($shown as $page) {
			if ($page - $previous > 1) {
				$html .= '<span class="rvs-results__page-gap" aria-hidden="true">…</span>';
			}
			$html    .= $page === $current
				? '<span class="rvs-btn rvs-btn--primary rvs-results__page" aria-current="page">' . esc_html(number_format_i18n($page)) . '</span>'
				: $link($page, number_format_i18n($page));
			$previous = $page;
		}
		if ($current < $total) {
			$html .= $link($current + 1, '›', __('Next page', 'mhm-rentiva'));
		}

		return $html;
	}
}
