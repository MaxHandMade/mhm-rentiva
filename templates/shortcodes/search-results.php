<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * Search results -- the front-end standard page (Aşama 2 PR 3).
 *
 * Variables:
 * - array  $atts
 * - array  $search_params   Logical keys (SearchResults::FILTER_PARAMS + page).
 * - array  $results         perform_search() shape.
 * - array  $filter_options  get_filter_options() shape.
 * - array  $sorting_options value => label.
 * - string $nonce_field
 * - array  $summary         SearchResults::results_summary() (count '' when only the sections list cars).
 * - string $apply_label     SearchResults::apply_label_html(): the drawer's apply label, escaped.
 * - array  $chips           SearchResults::active_chips().
 * - string|null $empty_state SearchResults::empty_state().
 * - string $change_url      Where "Change" sends the visitor.
 * - string $location_label  The pickup point the search is at (mobile card), or ''.
 * - string $pickup_where    The site's only pickup point ('' unless exactly one).
 * - int    $filter_count    Active filters, the pickup point not counted.
 * - string $notice_html     The status region's notice (SearchResults::notice_html()).
 * - array  $compare         Comparison band: items (id, title), max, url.
 *
 * JS hooks are data-rvs-* attributes (search-results.js); the data-show-*
 * attributes carry the card toggles into the AJAX re-render.
 */

declare(strict_types=1);

use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Frontend\Shortcodes\SearchResults;
use MHMRentiva\Helpers\Html;

if (! defined('ABSPATH')) {
	exit;
}

$vehicles      = $results['vehicles'] ?? array();
$active_layout = in_array(( $atts['layout'] ?? 'grid' ), array( 'grid', 'list' ), true) ? (string) $atts['layout'] : 'grid';
$show_filters  = ( $atts['show_filters'] ?? '1' ) === '1';
$rv_instance   = function_exists('wp_unique_id') ? wp_unique_id('rvsr-') : uniqid('rvsr-', false);
$filters_id    = $rv_instance . '-filters';
$compare       = $compare ?? array(
	'items' => array(),
	'max'   => 3,
	'url'   => '',
);

// On-the-wire names for the public search-filter params, through the same
// SearchResults::query_var() the reader side uses.
$mhmrentiva_qv = static function (string $logical): string {
	return SearchResults::query_var($logical);
};

$selected = static function (string $key) use ($search_params): array {
	$value = $search_params[ $key ] ?? array();
	return array_map('strval', is_array($value) ? $value : ( '' === (string) $value ? array() : array( $value ) ));
};

/**
 * One fieldset of checkboxes.
 *
 * @param array<string, string> $options value => label.
 */
$check_group = static function (string $key, string $legend, array $options) use ($mhmrentiva_qv, $selected): void {
	if (array() === $options) {
		return;
	}
	$picked = $selected($key);
	?>
	<fieldset class="rvs-results__group">
		<legend class="rvs-kicker"><?php echo esc_html($legend); ?></legend>
		<?php foreach ($options as $value => $label) : ?>
			<label class="rvs-check">
				<input type="checkbox" name="<?php echo esc_attr($mhmrentiva_qv($key)); ?>[]" value="<?php echo esc_attr( (string) $value); ?>"<?php checked(in_array( (string) $value, $picked, true)); ?>>
				<span><?php echo esc_html( (string) $label); ?></span>
			</label>
		<?php endforeach; ?>
	</fieldset>
	<?php
};

$range_group = static function (string $legend, string $min_key, string $max_key, array $bounds, string $step = '1') use ($mhmrentiva_qv, $search_params, $rv_instance): void {
	$min_id = $rv_instance . '-' . $min_key;
	$max_id = $rv_instance . '-' . $max_key;
	?>
	<fieldset class="rvs-results__group">
		<legend class="rvs-kicker"><?php echo esc_html($legend); ?></legend>
		<div class="rvs-results__range">
			<label class="rvs-sr-only" for="<?php echo esc_attr($min_id); ?>"><?php esc_html_e('Min', 'mhm-rentiva'); ?></label>
			<input id="<?php echo esc_attr($min_id); ?>" class="rvs-input" type="number" inputmode="numeric" name="<?php echo esc_attr($mhmrentiva_qv($min_key)); ?>" placeholder="<?php esc_attr_e('Min', 'mhm-rentiva'); ?>" value="<?php echo ! empty($search_params[ $min_key ]) ? esc_attr( (string) $search_params[ $min_key ]) : ''; ?>" min="<?php echo esc_attr( (string) ( $bounds['min'] ?? 0 )); ?>" max="<?php echo esc_attr( (string) ( $bounds['max'] ?? '' )); ?>" step="<?php echo esc_attr($step); ?>">
			<span aria-hidden="true">–</span>
			<label class="rvs-sr-only" for="<?php echo esc_attr($max_id); ?>"><?php esc_html_e('Max', 'mhm-rentiva'); ?></label>
			<input id="<?php echo esc_attr($max_id); ?>" class="rvs-input" type="number" inputmode="numeric" name="<?php echo esc_attr($mhmrentiva_qv($max_key)); ?>" placeholder="<?php esc_attr_e('Max', 'mhm-rentiva'); ?>" value="<?php echo ! empty($search_params[ $max_key ]) ? esc_attr( (string) $search_params[ $max_key ]) : ''; ?>" min="<?php echo esc_attr( (string) ( $bounds['min'] ?? 0 )); ?>" max="<?php echo esc_attr( (string) ( $bounds['max'] ?? '' )); ?>" step="<?php echo esc_attr($step); ?>">
		</div>
	</fieldset>
	<?php
};
?>
<div
	class="rvs-results <?php echo esc_attr($atts['class'] ?? ''); ?><?php echo ! empty($is_compact) ? ' rvs-results--compact' : ''; ?>"
	data-rvs-results
	data-testid="search-results"
	data-rv-instance="<?php echo esc_attr($rv_instance); ?>"
	data-show-favorite-button="<?php echo esc_attr($atts['show_favorite_button'] ?? '1'); ?>"
	data-show-compare-button="<?php echo esc_attr($atts['show_compare_button'] ?? '1'); ?>"
	data-show-booking-btn="<?php echo esc_attr( (string) ( $atts['show_booking_button'] ?? ( $atts['show_booking_btn'] ?? '1' ) )); ?>"
	data-show-price="<?php echo esc_attr($atts['show_price'] ?? '1'); ?>"
	data-show-title="<?php echo esc_attr($atts['show_title'] ?? '1'); ?>"
	data-show-features="<?php echo esc_attr($atts['show_features'] ?? '1'); ?>"
	data-show-rating="<?php echo esc_attr($atts['show_rating'] ?? '1'); ?>"
	data-show-badges="<?php echo esc_attr($atts['show_badges'] ?? '1'); ?>"
	data-results-per-page="<?php echo esc_attr($atts['results_per_page'] ?? '12'); ?>"
	data-default-view="<?php echo esc_attr($active_layout); ?>"
	data-default-sort="<?php echo esc_attr( (string) ( $search_params['sort'] ?? '' )); ?>">

	<?php
	// The search -- window and keyword -- travels with every AJAX request
	// (filters, sort, pages), with or without the filter column: admin-ajax
	// parses no query vars, and without it a re-render dropped the
	// availability constraint and the cards' period total.
	$rvs_window = array(
		'pickup_date' => (string) ( $search_params['pickup_date'] ?? '' ),
		'return_date' => (string) ( $search_params['return_date'] ?? '' ),
		'pickup_time' => (string) ( $search_params['pickup_time'] ?? '' ),
		'keyword'     => (string) ( is_scalar($search_params['keyword'] ?? '') ? $search_params['keyword'] ?? '' : '' ),
	);
	?>
	<form class="rvs-results__window" data-rvs-window hidden>
		<?php foreach ($rvs_window as $rvs_key => $rvs_value) : ?>
			<?php if ('' !== $rvs_value) : ?>
				<input type="hidden" name="<?php echo esc_attr($mhmrentiva_qv($rvs_key)); ?>" value="<?php echo esc_attr($rvs_value); ?>">
			<?php endif; ?>
		<?php endforeach; ?>
		<?php
		// Without the filter column the URL's filters ride here, so sort and
		// pages keep them (the chips name every active filter value).
		if (! $show_filters) :
			foreach ($chips as $rvs_chip) :
				if ($mhmrentiva_qv('keyword') === $rvs_chip['name']) {
					continue;
				}
				?>
				<input type="hidden" name="<?php echo esc_attr($rvs_chip['name']); ?>" value="<?php echo esc_attr($rvs_chip['value']); ?>">
				<?php
			endforeach;
		endif;
		?>
	</form>

	<div class="rvs-results__mobilebar">
		<a class="rvs-results__summary-card" href="<?php echo esc_url($change_url); ?>" data-rvs-change>
			<span class="rvs-results__summary-text">
				<?php $rvs_card_title = '' !== $location_label ? $location_label : (string) $summary['count']; ?>
				<?php if ('' !== $rvs_card_title) : ?>
					<strong><?php echo esc_html($rvs_card_title); ?></strong>
				<?php endif; ?>
				<span><?php echo esc_html('' !== $summary['range'] ? $summary['range'] : __('No dates selected', 'mhm-rentiva')); ?></span>
			</span>
			<span class="rvs-results__change"><?php esc_html_e('Change', 'mhm-rentiva'); ?></span>
		</a>
		<?php if ($show_filters) : ?>
			<button type="button" class="rvs-btn rvs-btn--secondary rvs-results__filters-open" data-rvs-drawer-open aria-controls="<?php echo esc_attr($filters_id); ?>" aria-expanded="false">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
				<span>
				<?php
				/* translators: %s: number of active filters. */
				printf(esc_html__('Filters (%s)', 'mhm-rentiva'), '<span data-rvs-filter-count>' . esc_html( (string) ( $filter_count ?? count($chips) )) . '</span>'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the count is escaped above.
				?>
				</span>
			</button>
		<?php endif; ?>
	</div>

	<div class="rvs-results__layout">
		<?php if ($show_filters) : ?>
			<aside id="<?php echo esc_attr($filters_id); ?>" class="rvs-results__filters" aria-labelledby="<?php echo esc_attr($filters_id); ?>-title" data-rvs-drawer>
				<div class="rvs-results__filters-head">
					<h2 class="rvs-results__filters-title" id="<?php echo esc_attr($filters_id); ?>-title"><?php esc_html_e('Filter', 'mhm-rentiva'); ?></h2>
					<button type="button" class="rvs-btn rvs-btn--ghost rvs-results__clear" data-rvs-clear><?php esc_html_e('Clear', 'mhm-rentiva'); ?></button>
					<button type="button" class="rvs-btn rvs-btn--icon rvs-results__drawer-close" data-rvs-drawer-close aria-label="<?php esc_attr_e('Close filters', 'mhm-rentiva'); ?>">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>
					</button>
				</div>

				<form class="rvs-results__form" data-rvs-filters>
					<?php echo wp_kses_post( (string) $nonce_field); ?>

					<?php
					$category_options = array();
					foreach ( (array) ( $filter_options['categories'] ?? array() ) as $category) {
						$category_options[ (string) $category['id'] ] = (string) $category['name'];
					}
					$check_group('category', __('Category', 'mhm-rentiva'), $category_options);

					if (! empty($filter_options['locations'])) {
						$location_options = array();
						foreach ($filter_options['locations'] as $loc) {
							$city                                  = isset($loc->city) && '' !== (string) $loc->city ? (string) $loc->city : '';
							$location_options[ (string) $loc->id ] = '' !== $city ? $loc->name . ' · ' . $city : (string) $loc->name;
						}
						asort($location_options, SORT_NATURAL | SORT_FLAG_CASE);
						$check_group('pickup_location', __('Pickup location', 'mhm-rentiva'), $location_options);
					}

					$check_group('transmission', __('Transmission', 'mhm-rentiva'), (array) ( $filter_options['transmissions'] ?? array() ));
					$check_group('fuel_type', __('Fuel', 'mhm-rentiva'), (array) ( $filter_options['fuel_types'] ?? array() ));
					$range_group(__('Daily price', 'mhm-rentiva'), 'min_price', 'max_price', (array) ( $filter_options['price_range'] ?? array() ));

					$seat_options = array();
					foreach ( (array) ( $filter_options['seats'] ?? array() ) as $seats) {
						/* translators: %s: number of seats. */
						$seat_options[ (string) $seats ] = sprintf(__('%s seats', 'mhm-rentiva'), $seats);
					}
					$check_group('seats', __('Seats', 'mhm-rentiva'), $seat_options);

					$brand_options = array();
					foreach ( (array) ( $filter_options['brands'] ?? array() ) as $brand) {
						$brand_options[ (string) $brand ] = (string) $brand;
					}
					$check_group('brand', __('Brand', 'mhm-rentiva'), $brand_options);

					$range_group(__('Model year', 'mhm-rentiva'), 'year_min', 'year_max', (array) ( $filter_options['year_range'] ?? array() ));
					$mileage_id = $rv_instance . '-mileage_max';
					?>
					<fieldset class="rvs-results__group">
						<legend class="rvs-kicker"><?php esc_html_e('Maximum mileage', 'mhm-rentiva'); ?></legend>
						<div class="rvs-results__range">
							<label class="rvs-sr-only" for="<?php echo esc_attr($mileage_id); ?>"><?php esc_html_e('Maximum mileage', 'mhm-rentiva'); ?></label>
							<input id="<?php echo esc_attr($mileage_id); ?>" class="rvs-input" type="number" inputmode="numeric" name="<?php echo esc_attr($mhmrentiva_qv('mileage_max')); ?>" placeholder="<?php esc_attr_e('Max', 'mhm-rentiva'); ?>" value="<?php echo ! empty($search_params['mileage_max']) ? esc_attr( (string) $search_params['mileage_max']) : ''; ?>" min="0" step="1000">
							<span><?php esc_html_e('km', 'mhm-rentiva'); ?></span>
						</div>
					</fieldset>
				</form>

				<div class="rvs-results__drawer-foot">
					<button type="button" class="rvs-btn rvs-btn--ghost" data-rvs-clear><?php esc_html_e('Clear', 'mhm-rentiva'); ?></button>
					<button type="button" class="rvs-btn rvs-btn--primary rvs-results__apply" data-rvs-drawer-apply>
						<span data-rvs-apply-label><?php Html::echo_markup( (string) ( $apply_label ?? SearchResults::apply_label_html( (array) $results) )); ?></span>
					</button>
				</div>
			</aside>
		<?php endif; ?>

		<section class="rvs-results__main" aria-label="<?php esc_attr_e('Results', 'mhm-rentiva'); ?>">
			<div class="rvs-results__head">
				<p class="rvs-results__summary" data-rvs-summary><?php Html::echo_markup(Templates::render('partials/search/rvs-summary', array( 'summary' => $summary ), true)); ?></p>
				<div class="rvs-results__tools">
					<?php if (( $atts['show_sorting'] ?? '1' ) === '1') : ?>
						<label class="rvs-results__sort-label" for="<?php echo esc_attr($rv_instance); ?>-sort"><?php esc_html_e('Sort', 'mhm-rentiva'); ?></label>
						<select id="<?php echo esc_attr($rv_instance); ?>-sort" class="rvs-input rvs-results__sort" name="<?php echo esc_attr($mhmrentiva_qv('sort')); ?>" data-rvs-sort>
							<?php foreach ($sorting_options as $value => $label) : ?>
								<option value="<?php echo esc_attr($value); ?>"<?php selected($search_params['sort'] ?? '', $value); ?>><?php echo esc_html($label); ?></option>
							<?php endforeach; ?>
						</select>
					<?php endif; ?>
					<?php if (( $atts['show_view_toggle'] ?? '1' ) === '1') : ?>
						<div class="rvs-results__view" role="group" aria-label="<?php esc_attr_e('View', 'mhm-rentiva'); ?>">
							<button type="button" class="rvs-btn rvs-btn--icon rvs-results__view-btn" data-rvs-view="grid" aria-pressed="<?php echo 'grid' === $active_layout ? 'true' : 'false'; ?>" aria-label="<?php esc_attr_e('Grid view', 'mhm-rentiva'); ?>">
								<svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" focusable="false"><rect x="1" y="1" width="6" height="6"/><rect x="9" y="1" width="6" height="6"/><rect x="1" y="9" width="6" height="6"/><rect x="9" y="9" width="6" height="6"/></svg>
							</button>
							<button type="button" class="rvs-btn rvs-btn--icon rvs-results__view-btn" data-rvs-view="list" aria-pressed="<?php echo 'list' === $active_layout ? 'true' : 'false'; ?>" aria-label="<?php esc_attr_e('List view', 'mhm-rentiva'); ?>">
								<svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" focusable="false"><rect x="1" y="2" width="14" height="2"/><rect x="1" y="7" width="14" height="2"/><rect x="1" y="12" width="14" height="2"/></svg>
							</button>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<?php if ('' !== (string) ( $pickup_where ?? '' )) : ?>
				<p class="rvs-results__where" data-rvs-pickup-where><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false"><path d="M12 21s-7-6.3-7-11a7 7 0 0 1 14 0c0 4.7-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg><span>
				<?php
				/* translators: %s: the site's only pickup point. */
				printf(esc_html__('Pickup and return: %s', 'mhm-rentiva'), '<strong>' . esc_html( (string) $pickup_where) . '</strong>');
				?>
				</span></p>
			<?php endif; ?>

			<div class="rvs-results__chips" data-rvs-chips aria-label="<?php esc_attr_e('Active filters', 'mhm-rentiva'); ?>"><?php Html::echo_markup(Templates::render('partials/search/rvs-chips', array( 'chips' => $chips ), true)); ?></div>

			<?php
			// The one live region, in the DOM from the start: a re-render replaces
			// its content (the notice and a short sentence naming the sections and
			// their counts), never the node, so screen readers keep listening.
			?>
			<div class="rvs-results__notice" data-rvs-notice role="status"><?php Html::echo_markup( (string) ( $notice_html ?? '' )); ?></div>

			<div class="<?php echo 'list' === $active_layout ? 'rvs-card-list' : 'rvs-card-grid'; ?> rvs-results__grid" data-rvs-grid>
				<?php if (array() !== $vehicles) : ?>
					<?php foreach ($vehicles as $vehicle) : ?>
						<?php Html::echo_markup(SearchResults::render_vehicle_card($vehicle, $active_layout, $atts)); ?>
					<?php endforeach; ?>
				<?php elseif (! in_array($empty_state, SearchResults::NOTICE_STATES, true)) : ?>
					<?php
                    Html::echo_markup(Templates::render('partials/search/rvs-empty', array(
						'kind'       => (string) $empty_state,
						'change_url' => $change_url,
					), true));
					?>
				<?php endif; ?>
			</div>

			<?php if (( $atts['show_pagination'] ?? '1' ) === '1') : ?>
				<nav class="rvs-results__pagination" data-rvs-pagination aria-label="<?php esc_attr_e('Pages', 'mhm-rentiva'); ?>"><?php Html::echo_markup(SearchResults::render_pagination($results['pagination'] ?? array())); ?></nav>
			<?php endif; ?>

			<?php
			// Page 1 of a dated search: the city's other pickup points, then the
			// matching cars booked on these dates. Outside the totals and pages.
			$rvs_section_atts = array_merge($atts, array( 'layout' => $active_layout ));
			?>
			<div class="rvs-results__nearby" data-rvs-nearby><?php Html::echo_markup(SearchResults::nearby_html($search_params, $results, $rvs_section_atts, $rv_instance)); ?></div>
			<div class="rvs-results__booked" data-rvs-booked><?php Html::echo_markup(SearchResults::booked_html($search_params, $results, $rvs_section_atts, $rv_instance)); ?></div>
		</section>
	</div>

	<?php if (( $atts['show_compare_button'] ?? '1' ) === '1') : ?>
		<div class="rvs-results__band" role="region" aria-label="<?php esc_attr_e('Comparison', 'mhm-rentiva'); ?>" data-rvs-compare-band data-count="<?php echo esc_attr( (string) count($compare['items'])); ?>" data-max="<?php echo esc_attr( (string) $compare['max']); ?>">
			<div class="rvs-results__band-inner">
				<span class="rvs-results__band-count" data-rvs-band-count>
					<?php
					/* translators: 1: number of vehicles picked for comparison, 2: the most that can be compared. */
					echo esc_html(sprintf(_n('%1$s vehicle selected · up to %2$s', '%1$s vehicles selected · up to %2$s', max(1, count($compare['items'])), 'mhm-rentiva'), number_format_i18n(count($compare['items'])), number_format_i18n( (int) $compare['max'])));
					?>
				</span>
				<span class="rvs-results__band-names" data-rvs-band-names><?php echo esc_html(implode(', ', wp_list_pluck($compare['items'], 'title'))); ?></span>
				<?php if ('' !== (string) $compare['url']) : ?>
					<a class="rvs-btn rvs-results__band-link" href="<?php echo esc_url($compare['url']); ?>"><?php esc_html_e('Compare', 'mhm-rentiva'); ?></a>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>
</div>
