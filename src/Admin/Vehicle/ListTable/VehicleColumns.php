<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\ListTable;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Vehicle admin list-table metrics/filtering intentionally use controlled meta SQL.

/**
 * Custom columns for the Vehicle admin list table.
 *
 * Registers, renders, and sorts additional vehicle columns.
 *
 * @since 4.20.0
 */
final class VehicleColumns {



	/**
	 * Safe sanitize text field that handles null values
	 */
	public static function sanitize_text_field_safe($value)
	{
		if ($value === null || $value === '') {
			return '';
		}
		return sanitize_text_field( (string) $value);
	}

	/**
	 * Register column hooks for the Vehicle list table.
	 *
	 * @since 4.20.0
	 */
	/**
	 * Filter and calendar params of the vehicles list screen, registered on
	 * WordPress's `query_vars` whitelist (see register_query_vars()) so the
	 * readers below can use get_query_var() instead of copying $_GET wholesale.
	 *
	 * Same mechanism SearchResults uses for its public filter params — the fix
	 * the accepted approach. These are display parameters of a bookmarkable
	 * admin URL: they change no state, so nonce-gating them would only break
	 * shareable filtered links, and an annotation over a raw $_GET read is not a
	 * resolution either.
	 *
	 * `mhmrentiva_month`/`mhmrentiva_year` drive the availability calendar's prev/next
	 * navigation. They carry the plugin prefix rather than the bare
	 * `month`/`year` they used to: registering an unprefixed `month` on a global
	 * whitelist would collide with any other plugin doing the same, and `year`
	 * is already one of core's own public query vars (it filters by date).
	 *
	 * @var array<int, string>
	 */
	private const PUBLIC_QUERY_VARS = array(
		'mhmrentiva_available',
		'mhmrentiva_location_filter',
		'mhmrentiva_lifecycle_filter',
		'mhmrentiva_month',
		'mhmrentiva_year',
		// View engine (Faz 2): which face of the screen is active. Same
		// bookmarkable-display-parameter reasoning as the params above.
		'mhmrentiva_view',
	);

	/**
	 * Faces this screen offers, in display order.
	 *
	 * @var array<int, string>
	 */
	private const VIEWS = array( 'list', 'cards', 'calendar' );

	/**
	 * `query_vars` filter callback.
	 *
	 * @param array<int, string> $vars Registered public query vars.
	 * @return array<int, string>
	 */
	public static function register_query_vars(array $vars): array
	{
		return array_values(array_unique(array_merge($vars, self::PUBLIC_QUERY_VARS)));
	}

	/**
	 * Read a sanitized list-screen param from the query_vars whitelist.
	 *
	 * A `null` sentinel default distinguishes "param absent from the request"
	 * from "param present but empty", matching the previous isset() semantics.
	 */
	private static function get_query_text(string $key, string $default = ''): string
	{
		$value = get_query_var($key, null);
		if (null === $value || is_array($value)) {
			return $default;
		}

		return sanitize_text_field(wp_unslash( (string) $value));
	}

	/**
	 * Read a non-negative integer list-screen param from the query_vars whitelist.
	 */
	private static function get_query_int(string $key, int $default = 0): int
	{
		$value = get_query_var($key, null);
		if (null === $value || is_array($value) || '' === $value) {
			return $default;
		}

		return absint(wp_unslash( (string) $value));
	}

	/**
	 * Whitelisted view-face getter — the ONLY way this screen's code reads
	 * `mhmrentiva_view`. Anything outside VIEWS (including an absent param)
	 * resolves to 'list', so the list-face guards below have a single safe
	 * default to reason about.
	 */
	public static function get_current_view(): string
	{
		$view = self::get_query_text('mhmrentiva_view');
		return in_array($view, self::VIEWS, true) ? $view : 'list';
	}

	public static function register(): void
	{
		add_filter('query_vars', array( self::class, 'register_query_vars' ));
		add_filter('manage_mhmrentiva_vehicle_posts_columns', array( self::class, 'columns' ));
		add_filter('list_table_primary_column', array( self::class, 'primary_column' ), 10, 2);
		add_action('manage_mhmrentiva_vehicle_posts_custom_column', array( self::class, 'render' ), 10, 2);
		add_filter('manage_edit-mhmrentiva_vehicle_sortable_columns', array( self::class, 'sortable' ));
		add_action('pre_get_posts', array( self::class, 'apply_sorting' ));
		add_action('restrict_manage_posts', array( self::class, 'availability_filter' ));
		add_action('pre_get_posts', array( self::class, 'apply_availability_filter' ));

		// Add custom columns for quick editing
		add_action('quick_edit_custom_box', array( self::class, 'quick_edit_fields' ), 10, 2);
		add_action('save_post', array( self::class, 'save_quick_edit' ));
		add_action('admin_enqueue_scripts', array( self::class, 'enqueue_scripts' ));

		// Cache clearing hooks
		add_action('save_post_mhmrentiva_vehicle', array( self::class, 'clear_vehicle_cache' ));
		add_action('delete_post', array( self::class, 'clear_vehicle_cache_on_delete' ));
		add_action('save_post_mhmrentiva_booking', array( self::class, 'clear_booking_occupancy_cache' ));

		// View-switch toggle (Faz 2): new block, no existing toolbar on this
		// screen to share with. Priority puts it ahead of the KPI band in the
		// raw admin_notices stream; actual visual order is decided by the
		// relocation JS (vehicle-list-ui.js).
		add_action('admin_notices', array( self::class, 'render_view_toggle' ), 8);

		// Add statistics cards
		add_action('admin_notices', array( self::class, 'add_vehicle_stats_cards' ));

		// Category chip strip between the KPI band and the calendar.
		add_action('admin_notices', array( self::class, 'category_chips' ), 15);

		// Calendar face (Faz 2 view engine) — replaces the old
		// add_monthly_calendar() in the same admin_notices slot.
		add_action('admin_notices', array( self::class, 'render_calendar_view' ), 20);

		// Cards face (Faz 2 view engine, Task 6) — same slot as the calendar
		// face; the two are mutually exclusive via get_current_view(), and
		// render_calendar_view() already runs the AutoCancel fallback on
		// every face load, so this method doesn't need to repeat it.
		add_action('admin_notices', array( self::class, 'render_cards_view' ), 20);
	}

	/**
	 * Define custom columns for the Vehicle list table.
	 *
	 * @param array $cols Default columns.
	 * @return array Modified columns.
	 */
	/**
	 * Whether the Location feature is available.
	 *
	 * Locations come from an add-on via the `mhmrentiva_has_locations` filter;
	 * Lite has no location list or CRUD UI of its own, so the default is false and
	 * every location affordance in this list table is withheld rather than
	 * rendered empty.
	 */
	private static function has_locations(): bool
	{
		return (bool) apply_filters('mhmrentiva_has_locations', false);
	}

	public static function columns(array $cols): array
	{
		$date = $cols['date'] ?? null;

		// The mockup's rich row identity: a custom Vehicle cell (thumbnail +
		// title + plate·category·dealer sub-line) REPLACES the native title,
		// taxonomy and comments columns and the standalone License Plate
		// column (the plate lives in the sub-line and rides as data-plate for
		// quick edit). list_table_primary_column moves the row actions here.
		unset($cols['date'], $cols['title'], $cols['comments'], $cols['taxonomy-mhmrentiva_vehicle_category']);

		$cols['mhmrentiva_vehicle'] = __('Vehicle', 'mhm-rentiva');
		if (self::has_locations()) {
			$cols['mhmrentiva_location'] = __('Location', 'mhm-rentiva');
		}
		// Seats + Transmission + Fuel consolidated into one chip cell
		// (mockup); their quick-edit fields re-anchor to this column.
		$cols['mhmrentiva_features']      = __('Features', 'mhm-rentiva');
		$cols['mhmrentiva_price_per_day'] = __('Price/Day', 'mhm-rentiva');
		// 7-day availability strip (mockup's "Bu hafta" cell).
		$cols['mhmrentiva_week']      = __('This Week', 'mhm-rentiva');
		$cols['mhmrentiva_available'] = __('Available', 'mhm-rentiva');
		$cols['mhmrentiva_lifecycle'] = __('Lifecycle', 'mhm-rentiva');
		$cols['mhmrentiva_featured']  = __('Featured', 'mhm-rentiva');

		if ($date !== null) {
			$cols['date'] = $date;
		}
		return $cols;
	}

	/**
	 * The custom Vehicle cell is the primary column: WordPress renders the
	 * row actions (Edit / Quick Edit / Trash / View) and the inline-edit
	 * data holder inside the primary column, so the native title column can
	 * go without losing them.
	 *
	 * @param string $default Default primary column.
	 * @param string $screen  Current screen ID.
	 */
	public static function primary_column(string $default, string $screen): string
	{
		return 'edit-mhmrentiva_vehicle' === $screen ? 'mhmrentiva_vehicle' : $default;
	}

	/**
	 * Render a custom column value for a vehicle row.
	 *
	 * @param string $column  Column identifier.
	 * @param int    $post_id Vehicle post ID.
	 */
	public static function render(string $column, int $post_id): void
	{
		switch ($column) {
			case 'mhmrentiva_vehicle':
				$edit_link = get_edit_post_link($post_id);
				$plate     = (string) get_post_meta($post_id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_LICENSE_PLATE, true);
				$terms     = wp_get_post_terms($post_id, \MHMRentiva\Admin\Vehicle\Taxonomies\VehicleCategory::TAXONOMY, array( 'fields' => 'names' ));
				$author_id = (int) get_post_field('post_author', $post_id);
				$author    = $author_id ? get_the_author_meta('display_name', $author_id) : '';

				$meta_parts = array_filter(
					array_merge(
						'' !== $plate ? array( $plate ) : array(),
						is_wp_error($terms) ? array() : $terms,
						'' !== $author ? array( $author ) : array()
					)
				);

				echo '<div class="rv-vhl-vehicle">';
				echo '<span class="rv-vhl-thumb">';
				if (has_post_thumbnail($post_id)) {
					echo get_the_post_thumbnail($post_id, array( 116, 80 ), array( 'class' => 'rv-vhl-thumb__img' ));
				} else {
					echo '<span class="dashicons dashicons-car"></span>';
				}
				echo '</span>';
				echo '<span class="rv-vhl-vehicle__body">';
				if ($edit_link) {
					echo '<a class="rv-vhl-vehicle__title" href="' . esc_url($edit_link) . '">' . esc_html(get_the_title($post_id)) . '</a>';
				} else {
					echo '<span class="rv-vhl-vehicle__title">' . esc_html(get_the_title($post_id)) . '</span>';
				}
				// data-plate feeds the quick-edit prefill (the standalone
				// License Plate column this sub-line replaces used to).
				echo '<span class="rv-vhl-vehicle__meta" data-plate="' . esc_attr($plate) . '">' . esc_html(implode(' · ', $meta_parts)) . '</span>';
				echo '</span>';
				echo '</div>';

				// Core prints the hidden #inline_{ID} data holder ONLY inside
				// column_title() — which never runs once 'title' leaves the
				// column set. Without it, native Quick Edit opens with EMPTY
				// title/date/status fields (saving would blank the title) and
				// Bulk Edit lists "(no title)". Print it here; the function
				// does its own capability check. (Fable review finding.)
				$vehicle_post = get_post($post_id);
				if ($vehicle_post instanceof \WP_Post && function_exists('get_inline_data')) {
					get_inline_data($vehicle_post);
				}
				break;

			case 'mhmrentiva_week':
				self::render_week_strip_markup(self::get_week_strip($post_id));
				break;

			case 'mhmrentiva_location':
				$location_id   = (int) get_post_meta($post_id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_LOCATION_ID, true);
				$location_name = '';
				// Locations come from an add-on via the filter; the default is empty,
				// so the lookup below simply finds nothing without one.
				if ($location_id > 0) {
					$locations = apply_filters('mhmrentiva_locations', array(), 'rental');
					foreach ($locations as $loc) {
						if ( (int) $loc->id === $location_id) {
							$location_name = $loc->name;
							break;
						}
					}
				}
				echo '<span data-location-id="' . esc_attr( (string) $location_id ) . '">' . ( $location_name ? esc_html( $location_name ) : '&mdash;' ) . '</span>';
				break;

			case 'mhmrentiva_price_per_day':
				$v = \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_price_per_day($post_id);
				if ($v > 0) {
					$currency_symbol = \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_symbol();
					echo esc_html(number_format_i18n($v, 0) . ' ' . $currency_symbol);
				} else {
					echo '—';
				}
				break;

			case 'mhmrentiva_features':
				$chips = array();

				$seats = (int) \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_seats($post_id);
				if ($seats > 0) {
					/* translators: %d: seat count */
					$chips[] = sprintf(_n('%d seat', '%d seats', $seats, 'mhm-rentiva'), $seats);
				}

				$trans_map = \MHMRentiva\Admin\Vehicle\Meta\VehicleMeta::get_transmission_types();
				$trans     = (string) get_post_meta($post_id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_TRANSMISSION, true);
				if (isset($trans_map[ $trans ])) {
					$chips[] = $trans_map[ $trans ];
				}

				$fuel_map = \MHMRentiva\Admin\Vehicle\Meta\VehicleMeta::get_fuel_types();
				$fuel     = (string) get_post_meta($post_id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_FUEL_TYPE, true);
				if (isset($fuel_map[ $fuel ])) {
					$chips[] = $fuel_map[ $fuel ];
				}

				// Raw values ride as data attributes — the quick-edit prefill
				// script reads THESE, not the localized chip text (scraping
				// translated labels broke silently when the columns merged).
				echo '<span class="rv-vhl-features" data-seats="' . esc_attr($seats > 0 ? (string) $seats : '') . '" data-transmission="' . esc_attr($trans) . '" data-fuel="' . esc_attr($fuel) . '">';
				if (empty($chips)) {
					echo '—';
				}
				foreach ($chips as $chip) {
					echo '<span class="rv-vhl-feature">' . esc_html($chip) . '</span>';
				}
				echo '</span>';
				break;

			case 'mhmrentiva_available':
				$v = \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_status($post_id);

				// Soft pill — the skin styles the status-* class; the old
				// per-status emoji + inline-color config is gone with them.
				$status_class = self::get_status_badge_class($v);
				$label        = \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_status_label($v);

				echo '<span class="badge vehicle-status ' . esc_attr($status_class) . '" data-status="' . esc_attr($v) . '">';
				echo esc_html($label);
				echo '</span>';
				break;

			case 'mhmrentiva_lifecycle':
				$lifecycle = \MHMRentiva\Admin\Vehicle\VehicleLifecycleStatus::get($post_id);
				$label     = \MHMRentiva\Admin\Vehicle\VehicleLifecycleStatus::get_label($lifecycle);
				$color     = \MHMRentiva\Admin\Vehicle\VehicleLifecycleStatus::get_color($lifecycle);

				// Badge family for shape; the colors stay dynamic (they come
				// from VehicleLifecycleStatus per state, already soft via the
				// 20-alpha background).
				echo '<span class="badge lifecycle-badge" style="background:' . esc_attr($color) . '20;color:' . esc_attr($color) . ';">';
				echo esc_html($label);
				echo '</span>';

				break;

			case 'mhmrentiva_featured':
				$is_featured = \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::is_featured($post_id);
				// data-featured feeds the quick-edit prefill (the old code
				// compared the cell TEXT against a translated "Yes").
				if ($is_featured) {
					echo '<span class="rv-vhl-star is-featured" data-featured="1" title="' . esc_attr__('Featured', 'mhm-rentiva') . '" aria-label="' . esc_attr__('Yes', 'mhm-rentiva') . '">&#9733;</span>';
				} else {
					echo '<span class="rv-vhl-star" data-featured="0" title="' . esc_attr__('Not featured', 'mhm-rentiva') . '" aria-label="' . esc_attr__('No', 'mhm-rentiva') . '">&#9734;</span>';
				}
				break;
		}
	}

	/**
	 * Define sortable columns for the Vehicle list table.
	 *
	 * @param array $cols Default sortable columns.
	 * @return array Modified sortable columns.
	 */
	public static function sortable(array $cols): array
	{
		// The Vehicle cell replaces the native title column; sorting by it
		// still means sorting by title.
		$cols['mhmrentiva_vehicle']       = 'title';
		$cols['mhmrentiva_price_per_day'] = 'mhmrentiva_price_per_day';
		// The consolidated Features column sorts by seat count — keeps the
		// old Seats column's sorting capability alive.
		$cols['mhmrentiva_features'] = 'mhmrentiva_seats';
		// Only sortable when the Location column is actually registered.
		if (self::has_locations()) {
			$cols['mhmrentiva_location'] = 'mhmrentiva_location';
		}
		return $cols;
	}

	public static function apply_sorting(\WP_Query $q): void
	{
		if (! is_admin() || ! $q->is_main_query()) {
			return;
		}
		if (( $q->get('post_type') ?? '' ) !== 'mhmrentiva_vehicle') {
			return;
		}
		$orderby = $q->get('orderby');
		if ($orderby === 'mhmrentiva_price_per_day') {
			$q->set('meta_key', \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_PRICE_PER_DAY);
			$q->set('orderby', 'meta_value_num');
		} elseif ($orderby === 'mhmrentiva_seats') {
			$q->set('meta_key', \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_SEATS);
			$q->set('orderby', 'meta_value_num');
		} elseif ($orderby === 'mhmrentiva_location') {
			$q->set('meta_key', \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_LOCATION_ID);
			$q->set('orderby', 'meta_value_num');
		}
	}

	public static function availability_filter(string $post_type): void
	{
		if ($post_type !== 'mhmrentiva_vehicle') {
			return;
		}

		$current = self::get_query_text('mhmrentiva_available');

		// Dynamic status values
		$status_values = self::get_vehicle_status_values();
		$legacy_values = self::get_legacy_status_values();

		echo '<select name="mhmrentiva_available" class="postform">';
		echo '  <option value="">' . esc_html__('All availability statuses', 'mhm-rentiva') . '</option>';

		// New status values
		foreach ($status_values as $value => $label) {
			echo '  <option value="' . esc_attr($value) . '"' . selected($current, $value, false) . '>' . esc_html($label) . '</option>';
		}

		// Legacy status values
		foreach ($legacy_values as $value => $label) {
			echo '<option value="' . esc_attr($value) . '"' . selected($current, $value, false) . '>' . esc_html($label) . '</option>';
		}

		echo '</select>';

		// Location filter dropdown — withheld entirely without the Location feature,
		// rather than rendered as a lone "All locations" option that filters nothing.
		if (self::has_locations()) {
			$locations   = apply_filters('mhmrentiva_locations', array(), 'rental');
			$current_loc = self::get_query_int('mhmrentiva_location_filter');
			echo '<select name="mhmrentiva_location_filter" class="postform">';
			echo '<option value="">' . esc_html__('All locations', 'mhm-rentiva') . '</option>';
			foreach ($locations as $loc) {
				$loc_id   = (int) $loc->id;
				$loc_name = (string) $loc->name;
				echo '<option value="' . esc_attr( (string) $loc_id) . '"' . selected($current_loc, $loc_id, false) . '>' . esc_html($loc_name) . '</option>';
			}
			echo '</select>';
		}

		// Lifecycle / archive filter dropdown.
		$current_lc = self::get_query_text('mhmrentiva_lifecycle_filter');
		$lc_options = array(
			''          => __('All lifecycle states', 'mhm-rentiva'),
			'active'    => __('Active', 'mhm-rentiva'),
			'paused'    => __('Paused', 'mhm-rentiva'),
			'expired'   => __('Expired', 'mhm-rentiva'),
			'withdrawn' => __('Withdrawn', 'mhm-rentiva'),
			'archive'   => __('Archive (expired + withdrawn)', 'mhm-rentiva'),
		);
		echo '<select name="mhmrentiva_lifecycle_filter" class="postform">';
		foreach ($lc_options as $value => $label) {
			echo '<option value="' . esc_attr($value) . '"' . selected($current_lc, $value, false) . '>' . esc_html($label) . '</option>';
		}
		echo '</select>';
	}

	public static function apply_availability_filter(\WP_Query $q): void
	{
		if (! is_admin() || ! $q->is_main_query()) {
			return;
		}
		if (( $q->get('post_type') ?? '' ) !== 'mhmrentiva_vehicle') {
			return;
		}

		// Permission check
		if (! current_user_can('edit_posts')) {
			return;
		}

		$meta_query = array();

		// Availability status filter
		$val = self::get_query_text('mhmrentiva_available');
		if ($val !== '') {
			// Dynamic status values validation
			$status_values  = array_keys(self::get_vehicle_status_values());
			$legacy_values  = array_keys(self::get_legacy_status_values());
			$allowed_values = array_merge($status_values, $legacy_values, array( 'inactive' ));

			if (in_array($val, $allowed_values, true)) {
				$meta_query[] = \MHMRentiva\Admin\Core\Utilities\MetaQueryHelper::get_status_meta_query($val);
			}
		}

		// Location filter
		$location_filter = self::get_query_int('mhmrentiva_location_filter');
		if ($location_filter > 0) {
			$meta_query[] = array(
				'key'     => \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_LOCATION_ID,
				'value'   => $location_filter,
				'compare' => '=',
			);
		}

		// Lifecycle / archive filter (expired + withdrawn listings).
		$lifecycle      = self::get_query_text('mhmrentiva_lifecycle_filter');
		$lifecycle_args = self::lifecycle_filter_args($lifecycle);
		if (! empty($lifecycle_args)) {
			$meta_query[] = $lifecycle_args['meta_query'][0];
			// Withdrawn vehicles are drafts; widen post_status so the archive view surfaces them.
			$q->set('post_status', $lifecycle_args['post_status']);
		}

		if (! empty($meta_query)) {
			$q->set('meta_query', $meta_query);
		}
	}

	/**
	 * Pure query-args mapper for the admin vehicle-list lifecycle/archive filter.
	 *
	 * Returns the meta_query clause + the post_status set needed to surface vehicles in a
	 * given lifecycle state. 'archive' = expired + withdrawn. Withdrawn vehicles live in
	 * post_status=draft, so every lifecycle view widens post_status to include draft.
	 *
	 * @param string $lifecycle One of: 'archive', or a VehicleLifecycleStatus value. Empty/unknown → no filter.
	 * @return array{meta_query: array<int, array<string, mixed>>, post_status: string[]}|array{}
	 */
	public static function lifecycle_filter_args(string $lifecycle): array
	{
		$visible_statuses = array( 'publish', 'pending', 'draft', 'private' );

		if ($lifecycle === 'archive') {
			return array(
				'meta_query'  => array(
					array(
						'key'     => \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_LIFECYCLE_STATUS,
						'value'   => array( \MHMRentiva\Admin\Vehicle\VehicleLifecycleStatus::EXPIRED, \MHMRentiva\Admin\Vehicle\VehicleLifecycleStatus::WITHDRAWN ),
						'compare' => 'IN',
					),
				),
				'post_status' => $visible_statuses,
			);
		}

		if (in_array($lifecycle, \MHMRentiva\Admin\Vehicle\VehicleLifecycleStatus::allowed(), true)) {
			return array(
				'meta_query'  => array(
					array(
						'key'     => \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_LIFECYCLE_STATUS,
						'value'   => $lifecycle,
						'compare' => '=',
					),
				),
				'post_status' => $visible_statuses,
			);
		}

		return array();
	}

	/**
	 * Enqueue scripts
	 */
	public static function enqueue_scripts(string $hook): void
	{
		global $post_type;

		if ($post_type === 'mhmrentiva_vehicle' && $hook === 'edit.php') {
			// Skin scope for the refined list screen.
			add_filter('admin_body_class', array( self::class, 'add_body_class' ));

			wp_enqueue_script(
				'mhm-rentiva-vehicle-quick-edit',
				MHMRENTIVA_PLUGIN_URL . 'assets/js/components/vehicle-quick-edit.js',
				array( 'jquery' ),
				\MHMRentiva\Admin\Core\AssetManager::get_file_version('assets/js/components/vehicle-quick-edit.js'),
				true
			);

			// Layout relocation (title → KPI → chips → table → calendar).
			wp_enqueue_script(
				'mhm-rentiva-vehicle-list-ui',
				MHMRENTIVA_PLUGIN_URL . 'assets/js/admin/vehicle-list-ui.js',
				array( 'jquery' ),
				\MHMRentiva\Admin\Core\AssetManager::get_file_version('assets/js/admin/vehicle-list-ui.js'),
				true
			);

			// Load statistics cards CSS
			wp_enqueue_style(
				'mhm-rentiva-stats-cards',
				MHMRENTIVA_PLUGIN_URL . 'assets/css/components/stats-cards.css',
				array(),
				\MHMRentiva\Admin\Core\AssetManager::get_file_version('assets/css/components/stats-cards.css')
			);

			wp_enqueue_style(
				'mhm-rentiva-shared-admin',
				MHMRENTIVA_PLUGIN_URL . 'src-react/shared/admin.css',
				array(),
				\MHMRentiva\Admin\Core\AssetManager::get_file_version('src-react/shared/admin.css')
			);

			// Load calendar CSS
			wp_enqueue_style(
				'mhm-rentiva-calendars',
				MHMRENTIVA_PLUGIN_URL . 'assets/css/components/calendars.css',
				array(),
				\MHMRentiva\Admin\Core\AssetManager::get_file_version('assets/css/components/calendars.css')
			);

			// Load booking calendar CSS (popup + legend styles)
			wp_enqueue_style(
				'mhm-rentiva-booking-calendar',
				MHMRENTIVA_PLUGIN_URL . 'assets/css/admin/booking-calendar.css',
				array(),
				\MHMRentiva\Admin\Core\AssetManager::get_file_version('assets/css/admin/booking-calendar.css')
			);

			// Faz 2 Task 8 skin: toggle + occupancy matrix + cards face.
			// Declared as a dependency of vehicle-list.css below so it loads
			// after the base calendar files but before the screen skin.
			wp_enqueue_style(
				'mhm-rentiva-occupancy-matrix',
				MHMRENTIVA_PLUGIN_URL . 'assets/css/admin/occupancy-matrix.css',
				array( 'mhm-rentiva-calendars', 'mhm-rentiva-booking-calendar' ),
				\MHMRentiva\Admin\Core\AssetManager::get_file_version('assets/css/admin/occupancy-matrix.css')
			);

			// Refined skin — declares EVERY stylesheet it overrides as a
			// dependency (calendar files included: .mhm-calendars lives there),
			// so load order is guaranteed rather than inherited from call order.
			wp_enqueue_style(
				'mhm-rentiva-vehicle-list',
				MHMRENTIVA_PLUGIN_URL . 'assets/css/admin/vehicle-list.css',
				array( 'mhm-rentiva-stats-cards', 'mhm-rentiva-shared-admin', 'mhm-rentiva-calendars', 'mhm-rentiva-booking-calendar', 'mhm-rentiva-occupancy-matrix' ),
				\MHMRentiva\Admin\Core\AssetManager::get_file_version('assets/css/admin/vehicle-list.css')
			);

			// The wp_add_inline_style() popup/blocked-day patch that used to
			// sit here is gone (Faz 2 Task 8): booking-calendar.css already
			// carries the full popup redesign (.mhm-popup-modal etc.), making
			// the inline copy redundant, and the blocked-day/available cursor
			// override now lives in occupancy-matrix.css, scoped to
			// .mhm-vehicle-list only (the Vehicles face is the only one with
			// enable_block_toggle => true).

			// Monthly calendar popup + quick block/unblock toggle (rendered by
			// render_calendar_view() via FleetOccupancyMatrix). Replaces the
			// former inline script block.
			wp_enqueue_script(
				'mhm-rentiva-vehicle-calendar-popup',
				MHMRENTIVA_PLUGIN_URL . 'assets/js/admin/vehicle-calendar-popup.js',
				array( 'jquery' ),
				\MHMRentiva\Admin\Core\AssetManager::get_file_version('assets/js/admin/vehicle-calendar-popup.js'),
				true
			);

			wp_localize_script(
				'mhm-rentiva-vehicle-calendar-popup',
				'mhmVehicleCalendar',
				array(
					'nonce' => wp_create_nonce( 'mhmrentiva_toggle_blocked_date' ),
					'i18n'  => array(
						'blockedTitle' => __( 'Blocked — click to open', 'mhm-rentiva' ),
						'availTitle'   => __( 'Available — click to close', 'mhm-rentiva' ),
						'toggleError'  => __( 'Could not update the day.', 'mhm-rentiva' ),
						'confirmClose' => __( 'Close this day for reservations?', 'mhm-rentiva' ),
						'confirmOpen'  => __( 'Re-open this day for reservations?', 'mhm-rentiva' ),
					),
				)
			);
		}
	}

	/**
	 * Build the 7-day availability strip for one vehicle row.
	 *
	 * Booking data comes from ONE query for the whole screen
	 * (OccupancyMapService::get_map()) — no per-row SQL; blocked days come
	 * from the vehicle's own meta via the existing accessor.
	 *
	 * @return array<int, array{class: string, label: string, title: string}>
	 */
	public static function get_week_strip(int $post_id): array
	{
		$today    = current_time('Y-m-d');
		$week_end = gmdate('Y-m-d', strtotime('+6 days', strtotime($today)));
		$map      = \MHMRentiva\Admin\Core\Utilities\OccupancyMapService::get_map($today, $week_end);
		$blocked  = \MHMRentiva\Admin\Vehicle\Meta\BlockedDatesMetaBox::get_blocked_dates($post_id);
		$base_ts  = strtotime($today);

		$strip = array();
		for ($i = 0; $i < 7; $i++) {
			$ts      = strtotime('+' . $i . ' days', $base_ts);
			$date    = gmdate('Y-m-d', $ts);
			$entries = $map[ $post_id ][ $date ] ?? array();
			$status  = \MHMRentiva\Admin\Core\Utilities\OccupancyMapService::reduce($entries);
			if (in_array($date, $blocked, true)) {
				$status = 'blocked';
			}

			switch ($status) {
				case 'blocked':
					$class = 'is-blocked';
					$text  = __('Blocked', 'mhm-rentiva');
					break;
				case 'in_progress':
					$class = 'is-active';
					$text  = \MHMRentiva\Admin\Booking\Core\Status::get_label($status);
					break;
				case 'confirmed':
					$class = 'is-confirmed';
					$text  = \MHMRentiva\Admin\Booking\Core\Status::get_label($status);
					break;
				case 'pending':
					$class = 'is-pending';
					$text  = \MHMRentiva\Admin\Booking\Core\Status::get_label($status);
					break;
				case 'completed':
					$class = 'is-completed';
					$text  = \MHMRentiva\Admin\Booking\Core\Status::get_label($status);
					break;
				default:
					$class = 'is-free';
					$text  = __('Available', 'mhm-rentiva');
			}

			$strip[] = array(
				'class' => $class,
				'label' => date_i18n('D', $ts),
				'title' => date_i18n(get_option('date_format'), $ts) . ' · ' . $text,
			);
		}

		return $strip;
	}

	/**
	 * Render the 7-day strip markup for a strip produced by get_week_strip().
	 *
	 * Shared by the list face's `mhmrentiva_week` column and the cards face
	 * (Task 6) — same `rv-vhl-week`/`rv-vhl-day` classes either way, so
	 * Task 8's CSS only has to style one shape.
	 *
	 * @param array<int, array{class: string, label: string, title: string}> $strip
	 */
	private static function render_week_strip_markup(array $strip): void
	{
		echo '<div class="rv-vhl-week">';
		foreach ($strip as $day) {
			echo '<span class="rv-vhl-week__day">';
			echo '<span class="rv-vhl-day ' . esc_attr($day['class']) . '" title="' . esc_attr($day['title']) . '"></span>';
			echo '<span class="rv-vhl-day__label">' . esc_html($day['label']) . '</span>';
			echo '</span>';
		}
		echo '</div>';
	}

	/**
	 * Status-pill CSS class for a vehicle status value. Shared by the list
	 * face's `mhmrentiva_available` column and the cards face's badge
	 * (Task 6) so both faces paint the identical class for the identical
	 * status — the mapping VehicleDataHelper::get_status() feeds is the
	 * single source of truth, this is only the display-class lookup.
	 */
	private static function get_status_badge_class(string $status): string
	{
		$status_classes = array(
			'active'      => 'status-active',
			'inactive'    => 'status-inactive',
			'maintenance' => 'status-maintenance',
		);

		return $status_classes[ $status ] ?? 'status-default';
	}

	/**
	 * Cards face (Faz 2 view engine, Task 6). Renders a card grid from the
	 * MAIN query's current page — `$wp_query->posts` already reflects
	 * category chip / search / pagination, so this adds no query of its
	 * own beyond the ONE `update_post_thumbnail_cache()` priming call
	 * below (attachment posts/meta are NOT primed by the main query the
	 * way postmeta/term data are).
	 *
	 * Registered in the SAME admin_notices slot (priority 20)
	 * render_calendar_view() holds; that method already runs the
	 * AutoCancel fallback unconditionally on every face load, so this
	 * method doesn't repeat it.
	 */
	public static function render_cards_view(): void
	{
		global $pagenow, $post_type;

		if ($pagenow !== 'edit.php' || $post_type !== 'mhmrentiva_vehicle') {
			return;
		}

		if ('cards' !== self::get_current_view()) {
			return;
		}

		global $wp_query;
		$vehicles = ( $wp_query instanceof \WP_Query ) ? $wp_query->posts : array();

		if ($wp_query instanceof \WP_Query) {
			update_post_thumbnail_cache($wp_query);
		}

		echo '<div class="rv-vhl-cards">';
		foreach ($vehicles as $vehicle) {
			self::render_vehicle_card($vehicle);
		}
		echo '</div>';
	}

	/**
	 * Render one vehicle card. Every data source here is one the list face
	 * (or its helpers) already reads — no new meta, no new query.
	 *
	 * @param \WP_Post|int $vehicle Post object or ID from $wp_query->posts.
	 */
	private static function render_vehicle_card($vehicle): void
	{
		$post = $vehicle instanceof \WP_Post ? $vehicle : get_post( (int) $vehicle );
		if (! $post instanceof \WP_Post) {
			return;
		}
		$post_id = $post->ID;

		echo '<div class="rv-vhl-card">';

		// Media: real thumbnail, or a placeholder carrying the vehicle
		// title (the list face's rich Vehicle cell falls back to a bare
		// dashicon instead — the card's larger media block reads better
		// with the title as text than an icon alone).
		echo '<div class="rv-vhl-card__media">';
		if (has_post_thumbnail($post_id)) {
			echo get_the_post_thumbnail($post, 'medium');
		} else {
			echo '<div class="rv-vhl-card__placeholder">' . esc_html(get_the_title($post_id)) . '</div>';
		}

		$status       = \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_status($post_id);
		$status_class = self::get_status_badge_class($status);
		$status_label = \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_status_label($status);
		echo '<span class="rv-vhl-card__badge ' . esc_attr($status_class) . '" data-status="' . esc_attr($status) . '">' . esc_html($status_label) . '</span>';

		if (\MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::is_featured($post_id)) {
			echo '<span class="rv-vhl-card__star" title="' . esc_attr__('Featured', 'mhm-rentiva') . '">&#9733;</span>';
		}
		echo '</div>'; // .rv-vhl-card__media

		echo '<div class="rv-vhl-card__body">';
		echo '<div class="rv-vhl-card__title">' . esc_html(get_the_title($post_id)) . '</div>';

		// Subline: plate + dealer/author display name — the same two parts
		// of the list face's Vehicle-cell sub-line, minus the category
		// names (those move into the chips row below). The list face has
		// no separate "dealer" field; it's the post author's display name.
		$plate     = (string) get_post_meta($post_id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_LICENSE_PLATE, true);
		$author_id = (int) get_post_field('post_author', $post_id);
		$author    = $author_id ? get_the_author_meta('display_name', $author_id) : '';
		$subline   = implode(' · ', array_filter( array( $plate, $author ) ));
		echo '<div class="rv-vhl-card__subline">' . esc_html($subline) . '</div>';

		echo '<div class="rv-vhl-card__chips">';
		$terms = wp_get_post_terms($post_id, \MHMRentiva\Admin\Vehicle\Taxonomies\VehicleCategory::TAXONOMY, array( 'fields' => 'names' ));
		if (! is_wp_error($terms)) {
			foreach ($terms as $term_name) {
				echo '<span class="rv-vhl-card__chip">' . esc_html($term_name) . '</span>';
			}
		}

		$seats = (int) \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_seats($post_id);
		if ($seats > 0) {
			/* translators: %d: seat count */
			echo '<span class="rv-vhl-card__chip">' . esc_html(sprintf(_n('%d seat', '%d seats', $seats, 'mhm-rentiva'), $seats)) . '</span>';
		}

		$trans_map = \MHMRentiva\Admin\Vehicle\Meta\VehicleMeta::get_transmission_types();
		$trans     = (string) get_post_meta($post_id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_TRANSMISSION, true);
		if (isset($trans_map[ $trans ])) {
			echo '<span class="rv-vhl-card__chip">' . esc_html($trans_map[ $trans ]) . '</span>';
		}
		echo '</div>'; // .rv-vhl-card__chips

		echo '<div class="rv-vhl-card__strip">';
		self::render_week_strip_markup(self::get_week_strip($post_id));
		echo '</div>';

		echo '</div>'; // .rv-vhl-card__body

		echo '<div class="rv-vhl-card__footer">';
		$daily_price = \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_price_per_day($post_id);
		echo '<span class="rv-vhl-card__price">' . esc_html(\MHMRentiva\Admin\Core\CurrencyHelper::format_price($daily_price)) . ' <span class="rv-vhl-card__price-unit">' . esc_html__('/ day', 'mhm-rentiva') . '</span></span>';

		$edit_link = get_edit_post_link($post);
		if ($edit_link) {
			echo '<a class="rv-vhl-card__edit" href="' . esc_url($edit_link) . '">' . esc_html__('Edit', 'mhm-rentiva') . '</a>';
		}
		echo '</div>'; // .rv-vhl-card__footer

		echo '</div>'; // .rv-vhl-card
	}

	/**
	 * Body class for the refined vehicle-list skin scope.
	 */
	public static function add_body_class(string $classes): string
	{
		$classes .= ' mhm-vehicle-list';

		// Faz 2 view engine: face-scoped visibility CSS keys off this class
		// (vehicle-list.css); 'list' carries no face class at all.
		$view = self::get_current_view();
		if ('cards' === $view) {
			$classes .= ' mhm-view-cards';
		} elseif ('calendar' === $view) {
			$classes .= ' mhm-view-calendar';
		}

		return $classes;
	}

	/**
	 * Segmented view-switch control (List | Cards | Calendar) — Faz 2 view
	 * engine. Markup only (`rv-view-toggle` / `rv-view-toggle__btn` /
	 * `is-active`); styling lands in Task 8. No existing toolbar block on
	 * this screen (unlike Bookings), so this is a plain standalone block —
	 * see vehicle-list-ui.js for its position in the relocated layout.
	 */
	public static function render_view_toggle(): void
	{
		global $pagenow, $post_type;

		if ($pagenow !== 'edit.php' || $post_type !== 'mhmrentiva_vehicle') {
			return;
		}

		$current = self::get_current_view();
		$faces   = array(
			'list'     => __('List', 'mhm-rentiva'),
			'cards'    => __('Cards', 'mhm-rentiva'),
			'calendar' => __('Calendar', 'mhm-rentiva'),
		);

		echo '<div class="rv-view-toggle">';
		foreach ($faces as $face => $label) {
			$url   = 'list' === $face ? remove_query_arg('mhmrentiva_view') : add_query_arg('mhmrentiva_view', $face);
			$class = 'rv-view-toggle__btn' . ( $current === $face ? ' is-active' : '' );
			printf(
				'<a class="%s" href="%s">%s</a>',
				esc_attr($class),
				esc_url($url),
				esc_html($label)
			);
		}
		echo '</div>';
	}

	/**
	 * Category chip strip — links carrying the taxonomy's own registered
	 * query var (`mhmrentiva_vehicle_category`), the same URL contract the
	 * native admin column's term links use, so filtering stays native.
	 * Terms with zero vehicles stay out of the strip.
	 */
	/**
	 * Base URL for the chip strip: this screen's edit.php PLUS the active
	 * view context.
	 *
	 * The view toggle preserves context (it calls add_query_arg() on the
	 * CURRENT URL); the chips are built from a bare base, so without this a
	 * chip click on the Cards or Calendar face dropped `mhmrentiva_view` and
	 * silently returned the user to the List face. The calendar's month/year
	 * travel with it for the same reason — filtering must not also navigate
	 * you back to the current month.
	 */
	private static function chip_base(): string
	{
		$base = admin_url('edit.php?post_type=mhmrentiva_vehicle');

		$view = self::get_current_view();
		if ('list' === $view) {
			return $base;
		}

		$base = add_query_arg('mhmrentiva_view', $view, $base);
		foreach (array( 'mhmrentiva_month', 'mhmrentiva_year' ) as $key) {
			$value = self::get_query_int($key);
			if ($value > 0) {
				$base = add_query_arg($key, $value, $base);
			}
		}

		return $base;
	}

	public static function category_chips(): void
	{
		global $pagenow, $post_type;

		if ($pagenow !== 'edit.php' || $post_type !== 'mhmrentiva_vehicle') {
			return;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => \MHMRentiva\Admin\Vehicle\Taxonomies\VehicleCategory::TAXONOMY,
				'hide_empty' => true,
			)
		);

		if (is_wp_error($terms) || empty($terms)) {
			return;
		}

		$current = (string) get_query_var(\MHMRentiva\Admin\Vehicle\Taxonomies\VehicleCategory::TAXONOMY);
		$base    = self::chip_base();

		echo '<div class="rv-vhl-chips">';

		printf(
			'<a class="rv-vhl-chip%s" href="%s">%s</a>',
			'' === $current ? ' is-active' : '',
			esc_url($base),
			esc_html__('All', 'mhm-rentiva')
		);

		foreach ($terms as $term) {
			printf(
				'<a class="rv-vhl-chip%s" href="%s">%s <span class="rv-vhl-chip__count">%d</span></a>',
				$current === $term->slug ? ' is-active' : '',
				esc_url(add_query_arg(\MHMRentiva\Admin\Vehicle\Taxonomies\VehicleCategory::TAXONOMY, $term->slug, $base)),
				esc_html($term->name),
				absint($term->count)
			);
		}

		echo '</div>';
	}

	/**
	 * Add vehicle statistics cards
	 */
	public static function add_vehicle_stats_cards(): void
	{
		global $pagenow, $post_type;

		// Show only on vehicle list page
		if ($pagenow !== 'edit.php' || $post_type !== 'mhmrentiva_vehicle') {
			return;
		}

		// Get statistics data
		$stats = self::get_vehicle_stats();
		?>
		<div class="mhm-stats-grid">
			<div class="mhm-stat-card">
				<span class="dashicons dashicons-car"></span>
				<div class="mhm-stat-card__body">
					<p class="mhm-stat-card__label"><?php esc_html_e('Total Vehicles', 'mhm-rentiva'); ?></p>
					<p class="mhm-stat-card__value"><?php echo esc_html($stats['total_vehicles']); ?></p>
					<p class="mhm-stat-card__sub"><?php echo esc_html($stats['reserved']); ?> <?php esc_html_e('reserved this month', 'mhm-rentiva'); ?></p>
				</div>
			</div>

			<div class="mhm-stat-card is-active-today">
				<span class="dashicons dashicons-admin-users"></span>
				<div class="mhm-stat-card__body">
					<p class="mhm-stat-card__label"><?php esc_html_e('Active Today', 'mhm-rentiva'); ?></p>
					<p class="mhm-stat-card__value"><?php echo esc_html($stats['active_today']); ?></p>
					<p class="mhm-stat-card__sub"><?php esc_html_e('vehicles with customers', 'mhm-rentiva'); ?></p>
				</div>
			</div>

			<div class="mhm-stat-card is-occupancy">
				<span class="dashicons dashicons-chart-bar"></span>
				<div class="mhm-stat-card__body">
					<p class="mhm-stat-card__label"><?php esc_html_e('This Month Occupancy', 'mhm-rentiva'); ?></p>
					<p class="mhm-stat-card__value"><?php echo esc_html($stats['occupancy_rate']); ?>%</p>
					<p class="mhm-stat-card__sub"><?php echo esc_html($stats['total_vehicles']); ?> <?php esc_html_e('total vehicles', 'mhm-rentiva'); ?></p>
				</div>
			</div>

			<div class="mhm-stat-card is-revenue">
				<span class="dashicons dashicons-money-alt"></span>
				<div class="mhm-stat-card__body">
					<p class="mhm-stat-card__label"><?php esc_html_e('This Month Revenue', 'mhm-rentiva'); ?></p>
					<p class="mhm-stat-card__value"><?php echo esc_html(self::format_currency( (float) ( $stats['monthly_avg_revenue'] ?? 0 ))); ?></p>
					<p class="mhm-stat-card__sub"><?php echo ( $stats['revenue_trend'] ?? 0 ) >= 0 ? '+' : ''; ?><?php echo esc_html($stats['revenue_trend'] ?? 0); ?>% <?php esc_html_e('vs last month', 'mhm-rentiva'); ?></p>
				</div>
			</div>
		</div>

		<?php
	}

	/**
	 * Get vehicle statistics data - Optimized pivot query
	 */
	private static function get_vehicle_stats(): array
	{
		global $wpdb;

		// Stats are user-independent; the shared key still matches
		// clear_vehicle_stats_cache()'s `mhmrentiva_vehicle_stats_%` pattern,
		// so the save/delete hooks that survived the cache's removal work
		// again. (A dead debug query that fetched every vehicle on every
		// list load and used nothing lived here — gone.)
		$cache_key = 'mhmrentiva_vehicle_stats_shared';
		$stats     = get_transient($cache_key);

		if ($stats !== false && is_array($stats)) {
			return $stats;
		}

		// Optimized pivot query - all statistics in single query
		$vehicle_stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
                COUNT(DISTINCT v.ID) as total_vehicles,
                COUNT(DISTINCT CASE WHEN (pm_status.meta_value = 'inactive' OR (pm_status.meta_value IS NULL AND pm_legacy.meta_value = 'passive')) THEN v.ID END) as passive,
                COUNT(DISTINCT CASE WHEN (pm_status.meta_value = 'maintenance' OR (pm_status.meta_value IS NULL AND pm_legacy.meta_value = 'maintenance')) THEN v.ID END) as maintenance,
                COUNT(DISTINCT CASE WHEN (pm_status.meta_value = 'inactive' OR (pm_status.meta_value IS NULL AND pm_legacy.meta_value = 'passive')) AND v.post_date >= %s THEN v.ID END) as passive_this_month,
                COUNT(DISTINCT CASE WHEN (pm_status.meta_value = 'maintenance' OR (pm_status.meta_value IS NULL AND pm_legacy.meta_value = 'maintenance')) AND v.post_date >= %s THEN v.ID END) as maintenance_this_month,
                COUNT(DISTINCT pm_booking.meta_value) as reserved,
                COUNT(DISTINCT CASE WHEN b.post_date >= %s THEN pm_booking.meta_value END) as reserved_this_week
             FROM {$wpdb->posts} v
             LEFT JOIN {$wpdb->postmeta} pm_status ON v.ID = pm_status.post_id AND pm_status.meta_key = %s
             LEFT JOIN {$wpdb->postmeta} pm_legacy ON v.ID = pm_legacy.post_id AND pm_legacy.meta_key = %s
             LEFT JOIN {$wpdb->posts} b ON b.post_type = %s AND b.post_status = %s
             LEFT JOIN {$wpdb->postmeta} pm_booking ON b.ID = pm_booking.post_id AND pm_booking.meta_key = %s
             WHERE v.post_type = %s AND v.post_status = %s",
				gmdate('Y-m-01'), // passive_this_month
				gmdate('Y-m-01'), // maintenance_this_month
				gmdate('Y-m-d', strtotime('-7 days')), // reserved_this_week
				\MHMRentiva\Admin\Core\MetaKeys::VEHICLE_STATUS,
				\MHMRentiva\Admin\Core\MetaKeys::VEHICLE_STATUS,
				'mhmrentiva_booking',
				'publish',
				\MHMRentiva\Admin\Core\MetaKeys::BOOKING_VEHICLE_ID,
				'mhmrentiva_vehicle',
				'publish'
			)
		);

		$total_vehicles         = (int) ( $vehicle_stats->total_vehicles ?? 0 );
		$reserved_all_time      = (int) ( $vehicle_stats->reserved ?? 0 );
		$passive                = (int) ( $vehicle_stats->passive ?? 0 );
		$maintenance            = (int) ( $vehicle_stats->maintenance ?? 0 );
		$reserved_this_week     = (int) ( $vehicle_stats->reserved_this_week ?? 0 );
		$passive_this_month     = (int) ( $vehicle_stats->passive_this_month ?? 0 );
		$maintenance_this_month = (int) ( $vehicle_stats->maintenance_this_month ?? 0 );

		// Calculate reserved vehicles for current month (similar to dashboard logic)
		$current_month_start = gmdate('Y-m-01 00:00:00');
		$current_month_end   = gmdate('Y-m-t 23:59:59');
		$month_start_ts      = strtotime($current_month_start);
		$month_end_ts        = strtotime($current_month_end);

		// Get bookings with date overlaps for current month
		// Note: We fetch all active bookings and check date overlaps in PHP
		// This ensures we catch bookings that overlap with current month regardless of when they were created
		// No user input in this query, but using esc_sql for best practice
		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT pm_vehicle.meta_value as vehicle_id,
						pm_pickup.meta_value as pickup_date,
						COALESCE(pm_return1.meta_value, pm_return2.meta_value, pm_return3.meta_value) as return_date
				 FROM {$wpdb->posts} b
				 INNER JOIN {$wpdb->postmeta} pm_vehicle ON b.ID = pm_vehicle.post_id AND pm_vehicle.meta_key = %s
				 INNER JOIN {$wpdb->postmeta} pm_pickup ON b.ID = pm_pickup.post_id AND pm_pickup.meta_key = %s
				 LEFT JOIN {$wpdb->postmeta} pm_return1 ON b.ID = pm_return1.post_id AND pm_return1.meta_key = %s
				 LEFT JOIN {$wpdb->postmeta} pm_return2 ON b.ID = pm_return2.post_id AND pm_return2.meta_key = %s
				 LEFT JOIN {$wpdb->postmeta} pm_return3 ON b.ID = pm_return3.post_id AND pm_return3.meta_key = %s
				 INNER JOIN {$wpdb->postmeta} pm_status ON b.ID = pm_status.post_id AND pm_status.meta_key = %s
				 WHERE b.post_type = %s
				 AND b.post_status = %s
				 AND pm_status.meta_value IN ('confirmed', 'active', 'pending')
				 AND pm_vehicle.meta_value IS NOT NULL AND pm_vehicle.meta_value != ''
				 AND pm_pickup.meta_value IS NOT NULL AND pm_pickup.meta_value != ''
				 AND (pm_return1.meta_value IS NOT NULL OR pm_return2.meta_value IS NOT NULL OR pm_return3.meta_value IS NOT NULL)",
				'_mhmrentiva_vehicle_id',
				'_mhmrentiva_pickup_date',
				'_mhmrentiva_return_date',
				'_mhmrentiva_dropoff_date',
				'_mhmrentiva_end_date',
				'_mhmrentiva_status',
				'mhmrentiva_booking',
				'publish'
			)
		);

		$reserved_vehicle_ids_this_month = array();
		if ($bookings) {
			foreach ($bookings as $booking) {
				$pickup_ts = strtotime($booking->pickup_date);
				$return_ts = strtotime($booking->return_date);

				if ($pickup_ts === false || $return_ts === false) {
					continue;
				}

				// Check if booking overlaps with current month
				$overlaps = ( $pickup_ts <= $month_end_ts && $return_ts >= $month_start_ts );

				if ($overlaps) {
					$reserved_vehicle_ids_this_month[] = (int) $booking->vehicle_id;
				}
			}
		}

		$reserved_this_month = count(array_unique($reserved_vehicle_ids_this_month));

		// Debug: Log vehicle stats in detail
		// Debug log removed

		// Monthly revenue: CANONICAL value from DashboardService (same number
		// the dashboard and the bookings-list band show — this used to be a
		// third, publish-only copy of the SQL). The trend needs last month's
		// revenue, which has no canonical method, so that one query stays
		// local but uses the canonical post_status scope.
		$monthly_avg_revenue = 0;
		$revenue_trend       = 0;

		if ($total_vehicles > 0) {
			$metrics               = \MHMRentiva\Admin\Utilities\Dashboard\DashboardService::get_dashboard_metrics();
			$current_month_revenue = (float) ( $metrics['monthly_revenue'] ?? 0 );

			// Last month's revenue (for trend calculation)
			$last_month_start = gmdate('Y-m-01', strtotime('-1 month'));
			$last_month_end   = gmdate('Y-m-t', strtotime('-1 month'));

			$last_month_revenue = (float) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2)))
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
                 WHERE p.post_type = %s AND p.post_status IN ('publish', 'private', 'pending')
                 AND pm.meta_key = %s
                 AND pm_status.meta_key = '_mhmrentiva_status'
                 AND pm_status.meta_value IN ('completed', 'confirmed')
                 AND p.post_date >= %s AND p.post_date <= %s",
					'mhmrentiva_booking',
					'_mhmrentiva_total_price',
					$last_month_start,
					$last_month_end . ' 23:59:59'
				)
			);

			$monthly_avg_revenue = $current_month_revenue;

			// Trend calculation
			if ($last_month_revenue > 0) {
				$revenue_trend = round(( ( $current_month_revenue - $last_month_revenue ) / $last_month_revenue ) * 100);
			} else {
				$revenue_trend = $current_month_revenue > 0 ? 100 : 0;
			}
		}
		// Occupancy rate: booked vehicle-days / (total_vehicles x elapsed days this month)
		$today             = gmdate('Y-m-d');
		$today_ts          = strtotime($today);
		$elapsed_days      = (int) gmdate('j');
		$booked_days_total = 0;

		if ($bookings && $total_vehicles > 0) {
			foreach ($bookings as $booking) {
				$pickup_ts = strtotime($booking->pickup_date);
				$return_ts = strtotime($booking->return_date);
				if ($pickup_ts === false || $return_ts === false) {
					continue;
				}
				$overlap_start = max($pickup_ts, $month_start_ts);
				$overlap_end   = min($return_ts, $today_ts);
				if ($overlap_end >= $overlap_start) {
					$booked_days_total += (int) round(( $overlap_end - $overlap_start ) / DAY_IN_SECONDS) + 1;
				}
			}
		}

		$possible_days  = $total_vehicles * max($elapsed_days, 1);
		$occupancy_rate = $possible_days > 0 ? round(( $booked_days_total / $possible_days ) * 100) : 0;

		// Active today: vehicles with a booking spanning today
		$active_today = 0;
		if ($bookings) {
			$active_vehicle_ids = array();
			foreach ($bookings as $booking) {
				$pickup_ts = strtotime($booking->pickup_date);
				$return_ts = strtotime($booking->return_date);
				if ($pickup_ts === false || $return_ts === false) {
					continue;
				}
				if ($pickup_ts <= $today_ts && $return_ts >= $today_ts) {
					$active_vehicle_ids[] = (int) $booking->vehicle_id;
				}
			}
			$active_today = count(array_unique($active_vehicle_ids));
		}

		$stats = array(
			'reserved'            => $reserved_this_month,
			'reserved_all_time'   => $reserved_all_time,
			'occupancy_rate'      => $occupancy_rate,
			'active_today'        => $active_today,
			'total_vehicles'      => $total_vehicles,
			'monthly_avg_revenue' => (float) ( $monthly_avg_revenue ?? 0 ),
			'revenue_trend'       => (float) ( $revenue_trend ?? 0 ),
		);

		set_transient($cache_key, $stats, 5 * MINUTE_IN_SECONDS);

		return $stats;
	}

	/**
	 * Calendar face (Faz 2 view engine). Replaces the old
	 * add_monthly_calendar()/get_monthly_bookings()/get_calendar_vehicles()
	 * trio: rows come straight from the main query's current page (category
	 * chip + search + native pagination already applied by WordPress), and
	 * painting is delegated to the shared FleetOccupancyMatrix renderer
	 * both this screen and the Bookings Calendar face (Task 5) use.
	 *
	 * Registered in the SAME admin_notices slot (priority 20) the old
	 * renderer held, so vehicle-list-ui.js's `.mhm-calendars` relocation
	 * still finds its target here without any JS change.
	 */
	public static function render_calendar_view(): void
	{
		global $pagenow, $post_type;

		if ($pagenow !== 'edit.php' || $post_type !== 'mhmrentiva_vehicle') {
			return;
		}

		// AutoCancel fallback, relocated from the old add_monthly_calendar():
		// must run on EVERY face of this screen (list included), not just
		// calendar, so it fires here — before the face branch below — since
		// this is the admin_notices-registered method that decides whether
		// the calendar face renders at all.
		self::maybe_run_autocancel();

		if ('calendar' !== self::get_current_view()) {
			return;
		}

		// Month/year bounds: current year ± 10, the same rule the old
		// BookingColumns::add_booking_calendar() used (both Calendar faces
		// converged on it) — the vehicles-only hardcoded 2020-2030 rule dies
		// with the old renderer.
		$current_month = self::get_query_int('mhmrentiva_month', (int) gmdate('n'));
		$current_year  = self::get_query_int('mhmrentiva_year', (int) gmdate('Y'));

		if ($current_month < 1 || $current_month > 12) {
			$current_month = (int) gmdate('n');
		}
		$this_year = (int) gmdate('Y');
		if ($current_year < ( $this_year - 10 ) || $current_year > ( $this_year + 10 )) {
			$current_year = $this_year;
		}

		global $wp_query;
		$vehicles = ( $wp_query instanceof \WP_Query ) ? $wp_query->posts : array();

		\MHMRentiva\Admin\Core\ListTable\FleetOccupancyMatrix::render(
			$vehicles,
			$current_month,
			$current_year,
			array(
				'show_plate'          => true,
				'enable_block_toggle' => true,
				'screen'              => 'vehicles',
			)
		);
	}

	/**
	 * AutoCancel fallback: expired pending bookings get cancelled here even
	 * when WP-Cron is unreliable (localhost/Docker). Throttled to once per
	 * 60 seconds via transient, exactly as the old add_monthly_calendar()
	 * did — only the trigger site moved, so it now runs on every face
	 * (list/cards/calendar) instead of accidentally only the list face.
	 */
	private static function maybe_run_autocancel(): void
	{
		if (class_exists(\MHMRentiva\Admin\PostTypes\Maintenance\AutoCancel::class)
			&& ! get_transient('mhmrentiva_autocancel_ran')
		) {
			\MHMRentiva\Admin\PostTypes\Maintenance\AutoCancel::run();
			set_transient('mhmrentiva_autocancel_ran', 1, 60);
		}
	}

	/**
	 * Add quick edit fields
	 */
	public static function quick_edit_fields(string $column_name, string $post_type): void
	{
		if ($post_type !== 'mhmrentiva_vehicle') {
			return;
		}

		static $nonce_added = false;
		if (! $nonce_added) {
			wp_nonce_field('mhmrentiva_vehicle_quick_edit', 'mhmrentiva_vehicle_quick_edit_nonce');
			$nonce_added = true;
		}

		switch ($column_name) {
			case 'mhmrentiva_vehicle':
				// The plate field re-anchors here: quick_edit_custom_box fires
				// per COLUMN, and the standalone License Plate column is gone.
				echo '<fieldset class="inline-edit-col-left">';
				echo '<div class="inline-edit-col">';
				echo '<label>';
				echo '<span class="title">' . esc_html__('License Plate', 'mhm-rentiva') . '</span>';
				echo '<input type="text" name="mhmrentiva_license_plate" class="mhmrentiva_license_plate" value="" />';
				echo '</label>';
				echo '</div>';
				echo '</fieldset>';
				break;

			case 'mhmrentiva_price_per_day':
				echo '<fieldset class="inline-edit-col-left">';
				echo '<div class="inline-edit-col">';
				echo '<label>';
				echo '<span class="title">' . esc_html__('Price/Day', 'mhm-rentiva') . '</span>';
				echo '<input type="number" name="mhmrentiva_price_per_day" class="mhmrentiva_price_per_day" value="" step="1" min="0" />';
				echo '</label>';
				echo '</div>';
				echo '</fieldset>';
				break;

			case 'mhmrentiva_features':
				// The consolidated Features column carries all three quick-edit
				// fields its source columns used to render — same field names,
				// save_quick_edit() unchanged.
				$max_seats = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhmrentiva_vehicle_max_seats', 100);
				echo '<fieldset class="inline-edit-col-left">';
				echo '<div class="inline-edit-col">';
				echo '<label>';
				echo '<span class="title">' . esc_html__('Seats', 'mhm-rentiva') . '</span>';
				echo '<input type="number" name="mhmrentiva_seats" class="mhmrentiva_seats" value="" min="1" max="' . esc_attr($max_seats) . '" />';
				echo '</label>';
				echo '</div>';
				echo '</fieldset>';

				$transmission_types = \MHMRentiva\Admin\Vehicle\Meta\VehicleMeta::get_transmission_types();
				echo '<fieldset class="inline-edit-col-left">';
				echo '<div class="inline-edit-col">';
				echo '<label>';
				echo '<span class="title">' . esc_html__('Transmission', 'mhm-rentiva') . '</span>';
				echo '<select name="mhmrentiva_transmission" class="mhmrentiva_transmission">';
				foreach ($transmission_types as $type_key => $type_label) {
					echo '<option value="' . esc_attr($type_key) . '">' . esc_html($type_label) . '</option>';
				}
				echo '</select>';
				echo '</label>';
				echo '</div>';
				echo '</fieldset>';

				$fuel_types = \MHMRentiva\Admin\Vehicle\Meta\VehicleMeta::get_fuel_types();
				echo '<fieldset class="inline-edit-col-left">';
				echo '<div class="inline-edit-col">';
				echo '<label>';
				echo '<span class="title">' . esc_html__('Fuel', 'mhm-rentiva') . '</span>';
				echo '<select name="mhmrentiva_fuel_type" class="mhmrentiva_fuel_type">';
				foreach ($fuel_types as $fuel_key => $fuel_label) {
					echo '<option value="' . esc_attr($fuel_key) . '">' . esc_html($fuel_label) . '</option>';
				}
				echo '</select>';
				echo '</label>';
				echo '</div>';
				echo '</fieldset>';
				break;

			case 'mhmrentiva_available':
				echo '<fieldset class="inline-edit-col-left">';
				echo '<div class="inline-edit-col">';
				echo '<label>';
				echo '<span class="title">' . esc_html__('Available', 'mhm-rentiva') . '</span>';
				echo '<select name="mhmrentiva_available" class="mhmrentiva_available">';

				// Dynamic status values
				$status_values = self::get_vehicle_status_values();
				foreach ($status_values as $value => $label) {
					echo '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
				}

				// Legacy values (backward compatibility)
				echo '</select>';
				echo '</label>';
				echo '</div>';
				echo '</fieldset>';
				break;

			case 'mhmrentiva_featured':
				echo '<fieldset class="inline-edit-col-left">';
				echo '<div class="inline-edit-col">';
				echo '<label class="alignleft">';
				echo '<input type="checkbox" name="mhmrentiva_featured" class="mhmrentiva_featured" value="1" />';
				echo '<span class="checkbox-title">' . esc_html__( 'Featured', 'mhm-rentiva' ) . '</span>';
				echo '</label>';
				echo '</div>';
				echo '</fieldset>';
				break;

			case 'mhmrentiva_location':
				// Defensive: WP only calls this for registered columns, and columns()
				// withholds mhmrentiva_location without the Location feature.
				if (! self::has_locations()) {
					break;
				}
				$locations = apply_filters('mhmrentiva_locations', array(), 'rental');
				echo '<fieldset class="inline-edit-col-left">';
				echo '<div class="inline-edit-col">';
				echo '<label>';
				echo '<span class="title">' . esc_html__('Location', 'mhm-rentiva') . '</span>';
				echo '<select name="mhmrentiva_location" class="mhmrentiva_location">';
				echo '<option value="0">' . esc_html__('— No Location —', 'mhm-rentiva') . '</option>';
				foreach ($locations as $loc) {
					echo '<option value="' . esc_attr( (string) (int) $loc->id) . '">' . esc_html($loc->name) . '</option>';
				}
				echo '</select>';
				echo '</label>';
				echo '</div>';
				echo '</fieldset>';
				break;
		}
	}

	/**
	 * Save quick edit data
	 */
	/**
	 * Daily price, clamped the way the full editor clamps it.
	 *
	 * A negative price is not a display problem: it flows straight into
	 * Util::total_price() and produces negative rental totals.
	 */
	private static function sanitize_price_per_day($value): float
	{
		return max(0.0, (float) $value);
	}

	/**
	 * Seat count, clamped to the same range the full editor enforces.
	 */
	private static function sanitize_seats($value): int
	{
		$max = \MHMRentiva\Admin\Vehicle\Meta\VehicleMeta::get_max_seats();

		return max(1, min($max, (int) $value));
	}

	public static function save_quick_edit(int $post_id): void
	{
		// Security: Nonce check
		$nonce = sanitize_text_field(wp_unslash($_POST['mhmrentiva_vehicle_quick_edit_nonce'] ?? ''));
		if (! wp_verify_nonce($nonce, 'mhmrentiva_vehicle_quick_edit')) {
			return;
		}

		// Permission check
		if (! current_user_can('edit_post', $post_id)) {
			return;
		}

		// Post type check
		if (get_post_type($post_id) !== 'mhmrentiva_vehicle') {
			return;
		}

		// Autosave and revision check
		if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
			return;
		}

		// Save meta values securely
		$meta_fields = array(
			'mhmrentiva_license_plate' => array(
				'key'      => '_mhmrentiva_license_plate',
				'sanitize' => 'sanitize_text_field',
			),
			// Bounds mirror VehicleMeta::sanitize_field() for the same meta keys.
			// Quick edit writes what the full editor writes, so it has to accept
			// only what the full editor accepts -- otherwise the row list is a way
			// around the editor's validation, and a negative daily price
			// multiplies into every rental total.
			'mhmrentiva_price_per_day' => array(
				'key'      => '_mhmrentiva_price_per_day',
				'sanitize' => array( self::class, 'sanitize_price_per_day' ),
			),
			'mhmrentiva_seats'         => array(
				'key'      => '_mhmrentiva_seats',
				'sanitize' => array( self::class, 'sanitize_seats' ),
			),
			'mhmrentiva_transmission'  => array(
				'key'      => '_mhmrentiva_transmission',
				'sanitize' => 'sanitize_text_field',
			),
			'mhmrentiva_fuel_type'     => array(
				'key'      => '_mhmrentiva_fuel_type',
				'sanitize' => 'sanitize_text_field',
			),
			'mhmrentiva_available'     => array(
				'key'      => \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_STATUS,
				'sanitize' => 'sanitize_text_field',
			),
		);

		foreach ($meta_fields as $field_name => $config) {
			if (! isset($_POST[ $field_name ])) {
				continue;
			}

			// map_deep() sanitizes on the same line as the read, scalar or array,
			// so the dynamic field name needs no annotation to be provably clean.
			$value = map_deep(wp_unslash($_POST[ $field_name ]), 'sanitize_text_field');

			// Array callables are this class's own clamps (price, seats); everything
			// else in the map is the plain text sanitizer.
			$sanitized_value = is_array($config['sanitize'])
				? call_user_func($config['sanitize'], $value)
				: sanitize_text_field( (string) ( $value ?: '' ));

			if ($field_name === 'mhmrentiva_available') {
				$normalized_status = self::normalize_availability($sanitized_value);
				update_post_meta($post_id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_STATUS, $normalized_status);
				continue;
			}

			if ($sanitized_value !== '' && $sanitized_value !== null) {
				update_post_meta($post_id, $config['key'], $sanitized_value);
			}
		}

		// Location — 0 means unset
		if (isset($_POST['mhmrentiva_location'])) {
			$location_id = intval(wp_unslash($_POST['mhmrentiva_location']));
			if ($location_id > 0) {
				update_post_meta($post_id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_LOCATION_ID, $location_id);
			} else {
				delete_post_meta($post_id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_LOCATION_ID);
			}
		}
		// Featured checkbox — not in $meta_fields loop because unchecked = absent from POST
		if (isset($_POST['mhmrentiva_featured']) && '1' === sanitize_text_field(wp_unslash($_POST['mhmrentiva_featured']))) {
			update_post_meta($post_id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_FEATURED, '1');
		} else {
			delete_post_meta($post_id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_FEATURED);
		}
	}



	/**
	 * Format currency (same as dashboard)
	 */
	private static function format_currency(float $amount): string
	{
		// Canonical currency formatting (WC-aware symbol/position/separators).
		return \MHMRentiva\Admin\Core\CurrencyHelper::format_price($amount, 2);
	}

	/**
	 * Get vehicle status values (dynamic)
	 */
	private static function get_vehicle_status_values(): array
	{
		return array(
			'active'      => __('Active', 'mhm-rentiva'),
			'maintenance' => __('Maintenance', 'mhm-rentiva'),
		);
	}

	/**
	 * Get legacy status values (backward compatibility)
	 */
	private static function get_legacy_status_values(): array
	{
		return array(
			'passive' => __('Passive', 'mhm-rentiva'),
		);
	}

	/**
	 * Normalize availability value (backward compatibility)
	 */
	private static function normalize_availability($value): string
	{
		$status_values = array_keys(self::get_vehicle_status_values());

		$mapping = array(
			'1'           => 'active',
			'active'      => 'active',
			'yes'         => 'active',
			'0'           => 'inactive',
			'passive'     => 'inactive',
			'inactive'    => 'inactive',
			'no'          => 'inactive',
			'maintenance' => 'maintenance',
		);

		if (isset($mapping[ $value ])) {
			return $mapping[ $value ];
		}

		// New format validation
		if (in_array($value, $status_values, true)) {
			return $value;
		}

		// Default
		return 'active';
	}

	/**
	 * Clear cache when vehicle changes
	 */
	public static function clear_vehicle_cache(int $post_id): void
	{
		if (get_post_type($post_id) === 'mhmrentiva_vehicle') {
			self::clear_vehicle_stats_cache();
		}
	}

	/**
	 * Clear cache when vehicle is deleted
	 */
	public static function clear_vehicle_cache_on_delete(int $post_id): void
	{
		if (get_post_type($post_id) === 'mhmrentiva_vehicle') {
			self::clear_vehicle_stats_cache();
		}
	}

	/**
	 * Clear the occupancy/stats cache when a BOOKING is saved.
	 *
	 * Separate from clear_vehicle_cache(): that method's
	 * `get_post_type() === 'mhmrentiva_vehicle'` guard is correct for the
	 * vehicle-save/delete hooks it also serves, but always false for a
	 * booking post — wiring `save_post_mhmrentiva_booking` to it silently
	 * skipped the invalidation. This callback needs no post-type guard: the
	 * dynamic `save_post_mhmrentiva_booking` action only ever fires for
	 * booking saves.
	 */
	public static function clear_booking_occupancy_cache(int $post_id): void
	{
		unset($post_id);
		self::clear_vehicle_stats_cache();
	}

	/**
	 * Clear all vehicle statistics caches.
	 *
	 * Pairs with the transient writer in get_vehicle_stats() (re-enabled in
	 * the Faz 1b round). Delegates to OccupancyMapService::invalidate() —
	 * the body moved there so the occupancy map service can invalidate
	 * itself without depending on this class; kept here because other code
	 * still calls this method directly.
	 */
	public static function clear_vehicle_stats_cache(): void
	{
		\MHMRentiva\Admin\Core\Utilities\OccupancyMapService::invalidate();
	}
}
