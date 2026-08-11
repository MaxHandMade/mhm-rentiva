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
	);

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
		add_action('save_post_mhmrentiva_booking', array( self::class, 'clear_vehicle_cache' ));

		// Add statistics cards
		add_action('admin_notices', array( self::class, 'add_vehicle_stats_cards' ));

		// Category chip strip between the KPI band and the calendar.
		add_action('admin_notices', array( self::class, 'category_chips' ), 15);

		// Add monthly reservation calendar
		add_action('admin_notices', array( self::class, 'add_monthly_calendar' ), 20);
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
				$strip = self::get_week_strip($post_id);

				echo '<div class="rv-vhl-week">';
				foreach ($strip as $day) {
					echo '<span class="rv-vhl-week__day">';
					echo '<span class="rv-vhl-day ' . esc_attr($day['class']) . '" title="' . esc_attr($day['title']) . '"></span>';
					echo '<span class="rv-vhl-day__label">' . esc_html($day['label']) . '</span>';
					echo '</span>';
				}
				echo '</div>';
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
				$status_classes = array(
					'active'      => 'status-active',
					'inactive'    => 'status-inactive',
					'maintenance' => 'status-maintenance',
				);
				$status_class   = $status_classes[ $v ] ?? 'status-default';
				$label          = \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_status_label($v);

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

			// Refined skin — declares EVERY stylesheet it overrides as a
			// dependency (calendar files included: .mhm-calendars lives there),
			// so load order is guaranteed rather than inherited from call order.
			wp_enqueue_style(
				'mhm-rentiva-vehicle-list',
				MHMRENTIVA_PLUGIN_URL . 'assets/css/admin/vehicle-list.css',
				array( 'mhm-rentiva-stats-cards', 'mhm-rentiva-shared-admin', 'mhm-rentiva-calendars', 'mhm-rentiva-booking-calendar' ),
				\MHMRentiva\Admin\Core\AssetManager::get_file_version('assets/css/admin/vehicle-list.css')
			);

			// Inline critical popup styles — guarantees correct rendering regardless of cache
			wp_add_inline_style( 'mhm-rentiva-booking-calendar', '
				#mhm-booking-popup { position:fixed; top:0; left:0; width:100%; height:100%; z-index:99999; display:none; }
				#mhm-booking-popup .mhm-popup-overlay { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.6); cursor:pointer; }
				#mhm-booking-popup .mhm-popup-content { position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; width:560px; max-width:calc(100vw - 40px); max-height:90vh; border-radius:12px; box-shadow:0 20px 60px rgba(0,0,0,.25); overflow:hidden; display:flex; flex-direction:column; z-index:100000; box-sizing:border-box; }
				#mhm-booking-popup .mhm-popup-footer { padding:16px 24px; border-top:1px solid #e2e8f0; background:#f8fafc; display:flex; justify-content:flex-end; gap:10px; flex-shrink:0; box-sizing:border-box; }
				#mhm-booking-popup .mhm-popup-footer .button { box-sizing:border-box; }
				.calendar-table .day-cell.available, .calendar-table .day-cell.blocked-day { cursor: pointer; }
				/* Blocked days are now clickable (quick unblock); the component CSS sets
				   pointer-events:none + cursor:not-allowed for the informational red stripe,
				   so re-enable interaction on this admin calendar. */
				.calendar-table td.day-cell.blocked-day { pointer-events: auto !important; cursor: pointer !important; }
			' );

			// Monthly calendar popup + quick block/unblock toggle (rendered by
			// add_monthly_calendar()). Replaces the former inline script block.
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
	 * Per-request cache for the 7-day strip booking map.
	 *
	 * @var array<int, array<string, string>>|null vehicle_id => [Y-m-d => status]
	 */
	private static ?array $week_bookings_map = null;

	/**
	 * Build the 7-day availability strip for one vehicle row.
	 *
	 * Booking data comes from ONE query for the whole screen (see
	 * get_week_bookings_map()) — no per-row SQL; blocked days come from the
	 * vehicle's own meta via the existing accessor.
	 *
	 * @return array<int, array{class: string, label: string, title: string}>
	 */
	public static function get_week_strip(int $post_id): array
	{
		$map     = self::get_week_bookings_map();
		$blocked = \MHMRentiva\Admin\Vehicle\Meta\BlockedDatesMetaBox::get_blocked_dates($post_id);
		$base_ts = strtotime(current_time('Y-m-d'));

		$strip = array();
		for ($i = 0; $i < 7; $i++) {
			$ts     = strtotime('+' . $i . ' days', $base_ts);
			$date   = gmdate('Y-m-d', $ts);
			$status = $map[ $post_id ][ $date ] ?? '';
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
	 * One query for every strip on the screen: bookings overlapping the next
	 * 7 days, mapped vehicle_id => day => strongest status. The window is a
	 * week, so the unfiltered result set is small; filtering per vehicle
	 * happens in PHP — keeps the SQL free of dynamic IN() lists.
	 *
	 * @return array<int, array<string, string>>
	 */
	private static function get_week_bookings_map(): array
	{
		if (null !== self::$week_bookings_map) {
			return self::$week_bookings_map;
		}

		global $wpdb;

		$start = current_time('Y-m-d');
		$end   = gmdate('Y-m-d', strtotime('+6 days', strtotime($start)));

		// Short-lived transient: the scan below walks the FULL booking
		// history (postmeta values are unindexed), so it must not run on
		// every list load. The key sits under the same
		// `mhmrentiva_vehicle_stats_%` pattern clear_vehicle_stats_cache()
		// deletes, so booking/vehicle saves invalidate it immediately.
		$cache_key = 'mhmrentiva_vehicle_stats_weekmap_' . $start;
		$cached    = get_transient($cache_key);
		if (is_array($cached)) {
			self::$week_bookings_map = $cached;
			return $cached;
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COALESCE(NULLIF(pm_v1.meta_value, ''), pm_v2.meta_value) AS vehicle_id,
                        COALESCE(NULLIF(pm_p1.meta_value, ''), pm_p2.meta_value) AS pickup_date,
                        COALESCE(NULLIF(pm_d1.meta_value, ''), pm_d2.meta_value, pm_d3.meta_value) AS dropoff_date,
                        pm_s.meta_value AS status
                FROM {$wpdb->posts} b
                INNER JOIN {$wpdb->postmeta} pm_s ON b.ID = pm_s.post_id AND pm_s.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} pm_v1 ON b.ID = pm_v1.post_id AND pm_v1.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} pm_v2 ON b.ID = pm_v2.post_id AND pm_v2.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} pm_p1 ON b.ID = pm_p1.post_id AND pm_p1.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} pm_p2 ON b.ID = pm_p2.post_id AND pm_p2.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} pm_d1 ON b.ID = pm_d1.post_id AND pm_d1.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} pm_d2 ON b.ID = pm_d2.post_id AND pm_d2.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} pm_d3 ON b.ID = pm_d3.post_id AND pm_d3.meta_key = %s
                WHERE b.post_type = %s
                AND b.post_status IN ('publish', 'private', 'pending')
                AND pm_s.meta_value IN ('pending', 'confirmed', 'in_progress', 'completed')
                HAVING vehicle_id IS NOT NULL AND pickup_date IS NOT NULL AND dropoff_date IS NOT NULL
                AND pickup_date <= %s AND dropoff_date >= %s",
				'_mhmrentiva_status',
				'_mhmrentiva_vehicle_id',
				'_mhmrentiva_booking_vehicle_id',
				'_mhmrentiva_pickup_date',
				'_mhmrentiva_booking_pickup_date',
				'_mhmrentiva_dropoff_date',
				'_mhmrentiva_return_date',
				'_mhmrentiva_end_date',
				'mhmrentiva_booking',
				$end,
				$start
			)
		);

		$precedence = array(
			'completed'   => 1,
			'pending'     => 2,
			'confirmed'   => 3,
			'in_progress' => 4,
		);

		$map      = array();
		$start_ts = strtotime($start);
		$end_ts   = strtotime($end);

		foreach ( (array) $rows as $row) {
			$vehicle_id = (int) $row->vehicle_id;
			$pickup_ts  = strtotime( (string) $row->pickup_date);
			$dropoff_ts = strtotime( (string) $row->dropoff_date);
			if ($vehicle_id <= 0 || false === $pickup_ts || false === $dropoff_ts) {
				continue;
			}

			$from = max($pickup_ts, $start_ts);
			$to   = min($dropoff_ts, $end_ts);
			for ($ts = $from; $ts <= $to; $ts += DAY_IN_SECONDS) {
				$day      = gmdate('Y-m-d', $ts);
				$existing = $map[ $vehicle_id ][ $day ] ?? '';
				if ('' === $existing || ( $precedence[ $row->status ] ?? 0 ) > ( $precedence[ $existing ] ?? 0 )) {
					$map[ $vehicle_id ][ $day ] = (string) $row->status;
				}
			}
		}

		set_transient($cache_key, $map, 5 * MINUTE_IN_SECONDS);

		self::$week_bookings_map = $map;
		return $map;
	}

	/**
	 * Body class for the refined vehicle-list skin scope.
	 */
	public static function add_body_class(string $classes): string
	{
		return $classes . ' mhm-vehicle-list';
	}

	/**
	 * Category chip strip — links carrying the taxonomy's own registered
	 * query var (`mhmrentiva_vehicle_category`), the same URL contract the
	 * native admin column's term links use, so filtering stays native.
	 * Terms with zero vehicles stay out of the strip.
	 */
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
		$base    = admin_url('edit.php?post_type=mhmrentiva_vehicle');

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
	 * Add monthly reservation calendar
	 */
	public static function add_monthly_calendar(): void
	{
		global $pagenow, $post_type;

		// Show only on vehicle list page
		if ($pagenow !== 'edit.php' || $post_type !== 'mhmrentiva_vehicle') {
			return;
		}

		$current_month = (int) gmdate('n');
		$current_year  = (int) gmdate('Y');

		$month = self::get_query_int('mhmrentiva_month');
		if ($month >= 1 && $month <= 12) {
			$current_month = $month;
		}

		$year = self::get_query_int('mhmrentiva_year');
		if ($year >= 2020 && $year <= 2030) {
			$current_year = $year;
		}

		// Dynamic month names (i18n supported)
		$month_names = array(
			1  => __('January', 'mhm-rentiva'),
			2  => __('February', 'mhm-rentiva'),
			3  => __('March', 'mhm-rentiva'),
			4  => __('April', 'mhm-rentiva'),
			5  => __('May', 'mhm-rentiva'),
			6  => __('June', 'mhm-rentiva'),
			7  => __('July', 'mhm-rentiva'),
			8  => __('August', 'mhm-rentiva'),
			9  => __('September', 'mhm-rentiva'),
			10 => __('October', 'mhm-rentiva'),
			11 => __('November', 'mhm-rentiva'),
			12 => __('December', 'mhm-rentiva'),
		);

		// Trigger auto-cancel on calendar load so expired pending bookings are cleaned
		// even when WP-Cron is unreliable (localhost / Docker environments).
		// Rate-limited to once per 60 seconds to avoid overhead on rapid refreshes.
		if (class_exists(\MHMRentiva\Admin\PostTypes\Maintenance\AutoCancel::class)
			&& ! get_transient('mhmrentiva_autocancel_ran')
		) {
			\MHMRentiva\Admin\PostTypes\Maintenance\AutoCancel::run();
			set_transient('mhmrentiva_autocancel_ran', 1, 60);
		}

		// Get vehicles
		$vehicles = self::get_calendar_vehicles();

		// Get reservation data
		$bookings = self::get_monthly_bookings($current_month, $current_year);

		?>
		<div class="mhm-calendars">
			<!-- Calendar Header -->
			<div class="calendar-header">
				<h2><?php esc_html_e('Monthly Booking Calendar', 'mhm-rentiva'); ?></h2>

				<!-- Month Navigation -->
				<div class="calendar-navigation">
					<?php
					$prev_month = $current_month == 1 ? 12 : $current_month - 1;
					$prev_year  = $current_month == 1 ? $current_year - 1 : $current_year;
					$next_month = $current_month == 12 ? 1 : $current_month + 1;
					$next_year  = $current_month == 12 ? $current_year + 1 : $current_year;
					?>

					<a href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'mhmrentiva_month' => $prev_month,
								'mhmrentiva_year'  => $prev_year,
							)
						)
					);
					?>
								"
						class="calendar-nav-btn prev-btn" data-action="prev">
						<span class="dashicons dashicons-arrow-left-alt2"></span>
						<?php echo esc_html($month_names[ $prev_month ]); ?>
					</a>

					<div class="calendar-current">
						<strong><?php echo esc_html($month_names[ $current_month ] . ' ' . $current_year); ?></strong>
					</div>

					<a href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'mhmrentiva_month' => $next_month,
								'mhmrentiva_year'  => $next_year,
							)
						)
					);
					?>
								"
						class="calendar-nav-btn next-btn" data-action="next">
						<?php echo esc_html($month_names[ $next_month ]); ?>
						<span class="dashicons dashicons-arrow-right-alt2"></span>
					</a>
				</div>
			</div>

			<!-- Calendar Table -->
			<div class="calendar-container">
				<div class="calendar-table-wrapper">
					<table class="calendar-table">
						<thead>
							<tr>
								<th class="vehicle-column"><?php esc_html_e('Vehicles', 'mhm-rentiva'); ?></th>
								<?php
								// Create days of the month
								$calendar_date = new \DateTimeImmutable(
									sprintf('%04d-%02d-01', (int) $current_year, (int) $current_month),
									new \DateTimeZone('UTC')
								);
								$days_in_month = (int) $calendar_date->format('t');
								for ($day = 1; $day <= $days_in_month; $day++) {
									echo '<th class="day-header">' . esc_html($day) . '</th>';
								}
								?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($vehicles as $vehicle) : ?>
								<tr>
									<td class="vehicle-info">
										<div class="vehicle-name"><?php echo esc_html($vehicle['title']); ?></div>
										<div class="vehicle-plate"><?php echo esc_html($vehicle['plate']); ?></div>
									</td>
									<?php
									// Check reservation status for each day
									for ($day = 1; $day <= $days_in_month; $day++) {
										$date = sprintf('%04d-%02d-%02d', $current_year, $current_month, $day);

										// Blocked day check — takes priority over booking logic
										$date_str   = gmdate( 'Y-m-d', mktime( 0, 0, 0, $current_month, $day, $current_year ) );
										$is_blocked = in_array( $date_str, $vehicle['blocked_dates'] ?? array(), true );

										if ( $is_blocked ) {
											echo '<td class="day-cell blocked-day" data-vehicle-id="' . esc_attr( (string) $vehicle['id'] ) . '" data-date="' . esc_attr( $date_str ) . '" title="' . esc_attr__( 'Blocked — click to open', 'mhm-rentiva' ) . '"></td>';
											continue;
										}

										$is_booked = isset($bookings[ $vehicle['id'] ][ $date ]);

										$class = $is_booked ? 'day-cell booked' : 'day-cell available';

										if ($is_booked) {
											$booking_data = $bookings[ $vehicle['id'] ][ $date ];
											/* translators: %s: customer name. */
											$title = sprintf(esc_attr__('Reserved: %s', 'mhm-rentiva'), $booking_data['customer_name']);

											// Status-based color system
											$status        = $booking_data['status'] ?? 'pending';
											$status_colors = array(
												'pending' => 'status-pending',      // 🟡 Yellow
												'confirmed' => 'status-confirmed',  // 🟢 Green
												'completed' => 'status-completed',  // 🔵 Blue
												'cancelled' => 'status-cancelled',   // 🔴 Red
											);

											$status_class = $status_colors[ $status ] ?? 'status-pending';
											$class        = 'day-cell booked ' . $status_class;

											// Get translated status label
											$status_label = \MHMRentiva\Admin\Booking\Core\Status::get_label($status);

											// Data attributes for popup. Values are passed raw and escaped by
											// Html::echo_data_attributes() as each one is written out.
											$data_attrs = array(
												'booking-id' => $booking_data['booking_id'],
												'customer-name' => $booking_data['customer_name'],
												'customer-email' => $booking_data['customer_email'],
												'customer-phone' => $booking_data['customer_phone'],
												'total-price' => $booking_data['total_price'],
												'status'   => $booking_data['status'],
												'status-label' => $status_label,
												'start-date' => $booking_data['start_date'],
												'end-date' => $booking_data['end_date'],
												'start-time' => $booking_data['start_time'] ?? '',
												'end-time' => $booking_data['end_time'] ?? '',
												'created-date' => $booking_data['created_date'],
											);

											echo '<td class="' . esc_attr($class) . '" title="' . esc_attr($title) . '"';
											\MHMRentiva\Helpers\Html::echo_data_attributes($data_attrs);
											echo ' data-booking-popup>';
										} else {
											$title = __('Available — click to close', 'mhm-rentiva');
											echo '<td class="' . esc_attr($class) . '" data-vehicle-id="' . esc_attr( (string) $vehicle['id'] ) . '" data-date="' . esc_attr( $date_str ) . '" title="' . esc_attr($title) . '">';
										}

										echo $is_booked ? '<span class="dashicons dashicons-calendar-alt booking-icon"></span>' : '';
										echo '</td>';
									}
									?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>

			<!-- Status Color Information -->
			<div class="calendar-legend">
				<h4><?php esc_html_e('Status Legend', 'mhm-rentiva'); ?></h4>
				<div class="legend-items">
					<div class="legend-item">
						<span class="legend-color status-pending"></span>
						<span class="legend-label"><?php esc_html_e('Pending', 'mhm-rentiva'); ?></span>
					</div>
					<div class="legend-item">
						<span class="legend-color status-confirmed"></span>
						<span class="legend-label"><?php esc_html_e('Confirmed', 'mhm-rentiva'); ?></span>
					</div>
					<div class="legend-item">
						<span class="legend-color status-completed"></span>
						<span class="legend-label"><?php esc_html_e('Completed', 'mhm-rentiva'); ?></span>
					</div>
					<div class="legend-item">
						<span class="legend-color legend-blocked-day"></span>
						<span class="legend-label"><?php esc_html_e( 'Blocked Day', 'mhm-rentiva' ); ?></span>
					</div>
				</div>
			</div>
		</div>

		<!-- Booking Popup Modal -->
		<div id="mhm-booking-popup" class="mhm-popup-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="mhm-popup-title">
			<div class="mhm-popup-overlay"></div>
			<div class="mhm-popup-content">
				<div class="mhm-popup-header">
					<div class="mhm-popup-header-left">
						<span class="dashicons dashicons-calendar-alt mhm-popup-header-icon"></span>
						<div>
							<h3 id="mhm-popup-title"><?php esc_html_e('Booking Details', 'mhm-rentiva'); ?></h3>
							<span class="mhm-popup-booking-id"></span>
						</div>
					</div>
					<div class="mhm-popup-header-right">
						<span id="popup-status-badge" class="mhm-popup-status-badge"></span>
						<button class="mhm-popup-close" type="button" aria-label="<?php esc_attr_e('Close', 'mhm-rentiva'); ?>">
							<span class="dashicons dashicons-no-alt"></span>
						</button>
					</div>
				</div>

				<div class="mhm-popup-body">
					<!-- Customer Section -->
					<div class="mhm-popup-section">
						<div class="mhm-popup-section-title">
							<span class="dashicons dashicons-admin-users"></span>
							<?php esc_html_e('Customer', 'mhm-rentiva'); ?>
						</div>
						<div class="booking-info-grid">
							<div class="info-item">
								<label><?php esc_html_e('Name', 'mhm-rentiva'); ?></label>
								<span id="popup-customer-name">—</span>
							</div>
							<div class="info-item">
								<label><?php esc_html_e('Email', 'mhm-rentiva'); ?></label>
								<span id="popup-customer-email">—</span>
							</div>
							<div class="info-item">
								<label><?php esc_html_e('Phone', 'mhm-rentiva'); ?></label>
								<span id="popup-customer-phone">—</span>
							</div>
						</div>
					</div>

					<!-- Date & Time Section -->
					<div class="mhm-popup-section">
						<div class="mhm-popup-section-title">
							<span class="dashicons dashicons-clock"></span>
							<?php esc_html_e('Date & Time', 'mhm-rentiva'); ?>
						</div>
						<div class="booking-info-grid booking-info-grid--dates">
							<div class="info-item">
								<label><?php esc_html_e('Pickup', 'mhm-rentiva'); ?></label>
								<span id="popup-start-date" class="info-date">—</span>
								<span id="popup-start-time" class="info-time">—</span>
							</div>
							<div class="info-item">
								<label><?php esc_html_e('Return', 'mhm-rentiva'); ?></label>
								<span id="popup-end-date" class="info-date">—</span>
								<span id="popup-end-time" class="info-time">—</span>
							</div>
						</div>
					</div>

					<!-- Booking Info Section -->
					<div class="mhm-popup-section mhm-popup-section--last">
						<div class="mhm-popup-section-title">
							<span class="dashicons dashicons-tickets-alt"></span>
							<?php esc_html_e('Booking Info', 'mhm-rentiva'); ?>
						</div>
						<div class="booking-info-grid">
							<div class="info-item">
								<label><?php esc_html_e('Total Price', 'mhm-rentiva'); ?></label>
								<span id="popup-total-price" class="info-price">—</span>
							</div>
							<div class="info-item">
								<label><?php esc_html_e('Created', 'mhm-rentiva'); ?></label>
								<span id="popup-created-date">—</span>
							</div>
						</div>
					</div>
				</div>

				<div class="mhm-popup-footer">
					<button class="button button-primary mhm-popup-edit-btn" id="popup-edit-booking" type="button">
						<span class="dashicons dashicons-edit"></span>
						<?php esc_html_e('Edit Booking', 'mhm-rentiva'); ?>
					</button>
				</div>
			</div>
		</div>

		<?php
		// Popup + day-toggle behavior is enqueued as assets/js/admin/vehicle-calendar-popup.js.
	}

	/**
	 * Get vehicles for calendar
	 */
	private static function get_calendar_vehicles(): array
	{
		global $wpdb;

		$vehicles = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, pm.meta_value as plate
             FROM {$wpdb->posts} p 
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
             WHERE p.post_type = %s AND p.post_status = %s
             ORDER BY p.post_title ASC",
				'_mhmrentiva_license_plate',
				'mhmrentiva_vehicle',
				'publish'
			)
		);

		$result = array();
		foreach ($vehicles as $vehicle) {
			$result[] = array(
				'id'            => $vehicle->ID,
				'title'         => $vehicle->post_title,
				'plate'         => $vehicle->plate ?: '—',
				'blocked_dates' => \MHMRentiva\Admin\Vehicle\Meta\BlockedDatesMetaBox::get_blocked_dates( (int) $vehicle->ID ),
			);
		}

		return $result;
	}

	/**
	 * Get monthly reservation data
	 */
	private static function get_monthly_bookings(int $month, int $year): array
	{
		global $wpdb;

		$month_date    = new \DateTimeImmutable(
			sprintf('%04d-%02d-01', (int) $year, (int) $month),
			new \DateTimeZone('UTC')
		);
		$days_in_month = (int) $month_date->format('t');
		$start_date    = sprintf('%04d-%02d-01', (int) $year, (int) $month);
		$end_date      = sprintf('%04d-%02d-%02d', (int) $year, (int) $month, $days_in_month);

		// Use same meta keys as dashboard - detailed data for popup
		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT
                p.ID as booking_id,
                p.post_title as booking_title,
                pm_vehicle.meta_value as vehicle_id,
                pm_start.meta_value as start_date,
                pm_end.meta_value as end_date,
                pm_start_time.meta_value as start_time,
                pm_end_time.meta_value as end_time,
                pm_customer.meta_value as customer_name,
                pm_customer_email.meta_value as customer_email,
                pm_customer_phone.meta_value as customer_phone,
                pm_total_price.meta_value as total_price,
                pm_status.meta_value as status,
                p.post_date as created_date
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_vehicle ON p.ID = pm_vehicle.post_id
                AND pm_vehicle.meta_key = '_mhmrentiva_vehicle_id'
            LEFT JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id
                AND pm_start.meta_key = '_mhmrentiva_pickup_date'
            LEFT JOIN {$wpdb->postmeta} pm_end ON p.ID = pm_end.post_id
                AND pm_end.meta_key = '_mhmrentiva_dropoff_date'
            LEFT JOIN {$wpdb->postmeta} pm_start_time ON p.ID = pm_start_time.post_id
                AND pm_start_time.meta_key = '_mhmrentiva_start_time'
            LEFT JOIN {$wpdb->postmeta} pm_end_time ON p.ID = pm_end_time.post_id
                AND pm_end_time.meta_key = '_mhmrentiva_end_time'
            LEFT JOIN {$wpdb->postmeta} pm_customer ON p.ID = pm_customer.post_id
                AND pm_customer.meta_key = '_mhmrentiva_customer_name'
            LEFT JOIN {$wpdb->postmeta} pm_customer_email ON p.ID = pm_customer_email.post_id
                AND pm_customer_email.meta_key = '_mhmrentiva_customer_email'
            LEFT JOIN {$wpdb->postmeta} pm_customer_phone ON p.ID = pm_customer_phone.post_id
                AND pm_customer_phone.meta_key = '_mhmrentiva_customer_phone'
            LEFT JOIN {$wpdb->postmeta} pm_total_price ON p.ID = pm_total_price.post_id
                AND pm_total_price.meta_key = '_mhmrentiva_total_price'
            LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
                AND pm_status.meta_key = '_mhmrentiva_status'
            LEFT JOIN {$wpdb->postmeta} pm_deadline ON p.ID = pm_deadline.post_id
                AND pm_deadline.meta_key = '_mhmrentiva_payment_deadline'
            WHERE p.post_type = 'mhmrentiva_booking'
                AND p.post_status = 'publish'
                AND pm_start.meta_value <= %s
                AND pm_end.meta_value >= %s
                AND pm_vehicle.meta_value IS NOT NULL
                AND pm_status.meta_value IN ('pending_payment', 'pending', 'confirmed', 'in_progress', 'completed')
                AND (
                    pm_status.meta_value NOT IN ('pending_payment', 'pending') OR
                    pm_deadline.meta_value IS NULL OR
                    pm_deadline.meta_value = '' OR
                    pm_deadline.meta_value > %s
                )
        ",
				$end_date,
				$start_date,
				current_time('mysql', 1)
			)
		);

		$result = array();
		foreach ($bookings as $booking) {
			if (! $booking->vehicle_id || ! $booking->start_date || ! $booking->end_date) {
				continue;
			}

			// Get customer info using BookingQueryHelper (handles WooCommerce & WordPress integration)
			$customer_info = array();
			if (class_exists('\\MHMRentiva\\Admin\\Core\\Utilities\\BookingQueryHelper')) {
				$customer_info = \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::getBookingCustomerInfo( (int) $booking->booking_id);
			}

			// Build customer name from first_name and last_name
			$customer_name = '';
			if (! empty($customer_info['first_name']) && ! empty($customer_info['last_name'])) {
				$customer_name = trim($customer_info['first_name'] . ' ' . $customer_info['last_name']);
			} elseif (! empty($customer_info['first_name'])) {
				$customer_name = $customer_info['first_name'];
			} elseif (! empty($customer_info['last_name'])) {
				$customer_name = $customer_info['last_name'];
			}

			// Fallback to SQL result if BookingQueryHelper didn't find anything
			if (empty($customer_name)) {
				$customer_name = $booking->customer_name ?: '';
			}

			// Use customer info from BookingQueryHelper (prioritizes WooCommerce/WordPress data)
			$customer_email = ! empty($customer_info['email']) ? $customer_info['email'] : ( $booking->customer_email ?: '' );
			$customer_phone = ! empty($customer_info['phone']) ? $customer_info['phone'] : ( $booking->customer_phone ?: '' );

			// Normalize date format
			$start_date = self::normalize_date($booking->start_date);
			$end_date   = self::normalize_date($booking->end_date);

			if (! $start_date || ! $end_date) {
				continue;
			}

			// Mark each day in the date range
			$current = new \DateTime($start_date);
			$end     = new \DateTime($end_date);

			while ($current <= $end) {
				$date                                    = $current->format('Y-m-d');
				$result[ $booking->vehicle_id ][ $date ] = array(
					'customer_name'  => $customer_name ?: __('Reserved', 'mhm-rentiva'),
					'booking_id'     => $booking->booking_id,
					'booking_title'  => $booking->booking_title,
					'customer_email' => $customer_email,
					'customer_phone' => $customer_phone,
					'total_price'    => $booking->total_price,
					'status'         => $booking->status,
					'start_date'     => $start_date,
					'end_date'       => $end_date,
					'start_time'     => $booking->start_time ?: '',
					'end_time'       => $booking->end_time ?: '',
					'created_date'   => $booking->created_date,
				);
				$current->add(new \DateInterval('P1D'));
			}
		}

		return $result;
	}

	/**
	 * Normalize date to YYYY-MM-DD format
	 */
	private static function normalize_date(string $date): ?string
	{
		if (empty($date)) {
			return null;
		}

		// If already in YYYY-MM-DD format
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
			return $date;
		}

		// If in DD.MM.YYYY format
		if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $date)) {
			$parts = explode('.', $date);
			return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
		}

		// Try other formats
		$timestamp = strtotime($date);
		if ($timestamp !== false) {
			return gmdate('Y-m-d', $timestamp);
		}

		return null;
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
	 * Clear all vehicle statistics caches.
	 *
	 * Pairs with the transient writer in get_vehicle_stats() (re-enabled in
	 * the Faz 1b round). Known limit shared with the rest of the codebase:
	 * raw option-table DELETEs do not reach an external object cache backing
	 * transients — the 5-minute TTL bounds the staleness there; a dedicated
	 * class sweep is tracked separately.
	 */
	public static function clear_vehicle_stats_cache(): void
	{
		global $wpdb;

		// Clear vehicle stats caches for all users
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_mhmrentiva_vehicle_stats_%'
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_timeout_mhmrentiva_vehicle_stats_%'
			)
		);
	}
}
