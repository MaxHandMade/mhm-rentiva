<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * Search Results Template
 *
 * Variables:
 * - array $atts
 * - array $search_params
 * - array $results
 * - array $filter_options
 * - array $pagination
 * - array $sorting_options
 * - string $nonce_field
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}



$vehicles      = $results['vehicles'] ?? array();
$total_results = $results['total'] ?? 0;
$current_page  = $results['current_page'] ?? 1;
$max_pages     = $results['max_pages'] ?? 1;
$active_layout = in_array(( $atts['layout'] ?? 'grid' ), array( 'grid', 'list' ), true) ? (string) $atts['layout'] : 'grid';
?>

<?php
$rv_instance = function_exists('wp_unique_id') ? wp_unique_id('rvsr-') : uniqid('rvsr-', false);
?>
<div
	class="rv-search-results <?php echo esc_attr($atts['class'] ?? ''); ?> <?php echo !empty($is_compact) ? 'rv-compact-mode' : ''; ?>"
	data-rv-instance="<?php echo esc_attr($rv_instance); ?>"
	data-show-favorite-button="<?php echo esc_attr($atts['show_favorite_button'] ?? '1'); ?>"
	data-show-compare-button="<?php echo esc_attr($atts['show_compare_button'] ?? '1'); ?>"
	data-show-booking-btn="<?php echo esc_attr($atts['show_booking_btn'] ?? '1'); ?>"
	data-show-price="<?php echo esc_attr($atts['show_price'] ?? '1'); ?>"
	data-show-title="<?php echo esc_attr($atts['show_title'] ?? '1'); ?>"
	data-show-features="<?php echo esc_attr($atts['show_features'] ?? '1'); ?>"
	data-show-rating="<?php echo esc_attr($atts['show_rating'] ?? '1'); ?>"
	data-show-badges="<?php echo esc_attr($atts['show_badges'] ?? '1'); ?>"
	data-results-per-page="<?php echo esc_attr($atts['results_per_page'] ?? '12'); ?>"
	data-default-view="<?php echo esc_attr($active_layout); ?>">

	<!-- Results Header -->
	<div class="rv-results-header">
		<div class="rv-results-info">
			<h1 class="rv-results-title">
				<?php
				if ($total_results > 0) {
					printf(
						/* translators: %d: number of vehicles found. */
						esc_html(_n('%d vehicle found', '%d vehicles found', $total_results, 'mhm-rentiva')),
						(int) $total_results
					);
				} else {
					printf(
						/* translators: Shown when search returns no results. Avoid using %d here. */
						esc_html__('No vehicles found', 'mhm-rentiva'),
						0
					);
				}
				?>
			</h1>

			<?php if (! empty($search_params['keyword'])) : ?>
				<p class="rv-search-query">
					<?php
					/* translators: %s: search keyword wrapped in strong tags. */
					printf(esc_html__('Results for: %s', 'mhm-rentiva'), '<strong>' . esc_html($search_params['keyword']) . '</strong>');
					?>
				</p>
			<?php endif; ?>
		</div>

		<div class="rv-results-controls">
			<?php if (( $atts['show_sorting'] ?? '1' ) === '1') : ?>
				<!-- Sorting -->
				<div class="rv-sorting">
					<label for="rv-sort-select"><?php esc_html_e('Sort by:', 'mhm-rentiva'); ?></label>
					<select name="sort" class="rv-sort-select">
						<?php foreach ($sorting_options as $value => $label) : ?>
							<option value="<?php echo esc_attr($value); ?>" <?php selected($search_params['sort'] ?? '', $value); ?>>
								<?php echo esc_html($label); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<?php if (( $atts['show_view_toggle'] ?? '1' ) === '1') : ?>
				<!-- View Toggle -->
				<div class="rv-view-toggle">
					<button type="button" class="rv-view-btn <?php echo $active_layout === 'grid' ? 'active' : ''; ?>" data-view="grid" title="<?php esc_html_e('Grid View', 'mhm-rentiva'); ?>">
						<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
							<rect x="1" y="1" width="6" height="6" rx="1" />
							<rect x="9" y="1" width="6" height="6" rx="1" />
							<rect x="1" y="9" width="6" height="6" rx="1" />
							<rect x="9" y="9" width="6" height="6" rx="1" />
						</svg>
					</button>
					<button type="button" class="rv-view-btn <?php echo $active_layout === 'list' ? 'active' : ''; ?>" data-view="list" title="<?php esc_html_e('List View', 'mhm-rentiva'); ?>">
						<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
							<rect x="1" y="2" width="14" height="2" rx="1" />
							<rect x="1" y="7" width="14" height="2" rx="1" />
							<rect x="1" y="12" width="14" height="2" rx="1" />
						</svg>
					</button>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="rv-results-content">

		<?php if (( $atts['show_filters'] ?? '1' ) === '1') : ?>
			<!-- Sidebar Filters -->
			<div class="rv-filters-sidebar">
				<div class="rv-filters-header">
					<h3><?php esc_html_e('Filters', 'mhm-rentiva'); ?></h3>
					<button type="button" class="rv-clear-filters">
						<?php esc_html_e('Clear All', 'mhm-rentiva'); ?>
					</button>
				</div>

				<form class="rv-filters-form">
					<?php echo wp_kses_post( (string) $nonce_field); ?>

					<!-- Pickup Location -->
					<?php if (! empty($filter_options['locations'])) : ?>
						<div class="rv-filter-group">
							<h4 class="rv-filter-title"><?php esc_html_e('Pickup Location', 'mhm-rentiva'); ?></h4>
							<?php
							$locations_by_city = array();
							foreach ($filter_options['locations'] as $loc) {
								$city_key                         = $loc->city !== '' ? $loc->city : esc_html__('Other', 'mhm-rentiva');
								$locations_by_city[ $city_key ][] = $loc;
							}
							ksort($locations_by_city);
							$active_loc_ids = array_map('intval', (array) ( $search_params['pickup_location'] ?? array() ));
							?>
							<?php foreach ($locations_by_city as $city_name => $city_locs) : ?>
								<?php
								$city_open = false;
								foreach ($city_locs as $loc) {
									if (in_array( (int) $loc->id, $active_loc_ids, true )) {
										$city_open = true;
										break;
									}
								}
								?>
								<div class="rv-location-group <?php echo $city_open ? 'is-open' : ''; ?>">
									<button type="button" class="rv-location-group__toggle"
										aria-expanded="<?php echo $city_open ? 'true' : 'false'; ?>">
										<span class="rv-location-group__city"><?php echo esc_html($city_name); ?></span>
										<span class="rv-location-group__count">(<?php echo count($city_locs); ?>)</span>
										<svg class="rv-location-group__arrow" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
									</button>
									<div class="rv-location-group__options" <?php echo $city_open ? '' : 'hidden'; ?>>
										<?php foreach ($city_locs as $loc) : ?>
											<label class="rv-filter-option rv-filter-option--sub">
												<input type="checkbox" name="pickup_location[]"
													value="<?php echo esc_attr( (string) $loc->id); ?>"
													<?php checked(in_array( (int) $loc->id, $active_loc_ids, true )); ?>>
												<span class="rv-checkbox-custom"></span>
												<span class="rv-option-label"><?php echo esc_html($loc->name); ?></span>
											</label>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<!-- Price Range -->
					<div class="rv-filter-group">
						<h4 class="rv-filter-title"><?php esc_html_e('Price Range', 'mhm-rentiva'); ?></h4>
						<div class="rv-price-range">
							<div class="rv-price-inputs">
								<input type="number" name="min_price" placeholder="<?php esc_html_e('Min', 'mhm-rentiva'); ?>"
									value="<?php echo esc_attr($search_params['min_price'] ?? ''); ?>"
									min="<?php echo esc_attr($filter_options['price_range']['min']); ?>"
									max="<?php echo esc_attr($filter_options['price_range']['max']); ?>">
								<span class="rv-price-separator">-</span>
								<input type="number" name="max_price" placeholder="<?php esc_html_e('Max', 'mhm-rentiva'); ?>"
									value="<?php echo esc_attr($search_params['max_price'] ?? ''); ?>"
									min="<?php echo esc_attr($filter_options['price_range']['min']); ?>"
									max="<?php echo esc_attr($filter_options['price_range']['max']); ?>">
							</div>
							<div class="rv-price-slider" id="rv-price-slider"></div>
						</div>
					</div>

					<!-- Brand -->
					<?php if (! empty($filter_options['brands'])) : ?>
						<div class="rv-filter-group">
							<h4 class="rv-filter-title"><?php esc_html_e('Brand', 'mhm-rentiva'); ?></h4>
							<div class="rv-filter-options">
								<?php foreach ($filter_options['brands'] as $brand) : ?>
									<label class="rv-filter-option">
										<input type="checkbox" name="brand[]" value="<?php echo esc_attr($brand); ?>"
											<?php checked(in_array($brand, (array) ( $search_params['brand'] ?? array() ))); ?>>
										<span class="rv-checkbox-custom"></span>
										<span class="rv-option-label"><?php echo esc_html($brand); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>

					<!-- Fuel Type -->
					<?php if (! empty($filter_options['fuel_types'])) : ?>
						<div class="rv-filter-group">
							<h4 class="rv-filter-title"><?php esc_html_e('Fuel Type', 'mhm-rentiva'); ?></h4>
							<div class="rv-filter-options">
								<?php foreach ($filter_options['fuel_types'] as $fuel_key => $fuel_label) : ?>
									<label class="rv-filter-option">
										<input type="checkbox" name="fuel_type[]" value="<?php echo esc_attr($fuel_key); ?>"
											<?php checked(in_array($fuel_key, (array) ( $search_params['fuel_type'] ?? array() ))); ?>>
										<span class="rv-checkbox-custom"></span>
										<span class="rv-option-label"><?php echo esc_html($fuel_label); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>

					<!-- Transmission -->
					<?php if (! empty($filter_options['transmissions'])) : ?>
						<div class="rv-filter-group">
							<h4 class="rv-filter-title"><?php esc_html_e('Transmission', 'mhm-rentiva'); ?></h4>
							<div class="rv-filter-options">
								<?php foreach ($filter_options['transmissions'] as $transmission_key => $transmission_label) : ?>
									<label class="rv-filter-option">
										<input type="checkbox" name="transmission[]" value="<?php echo esc_attr($transmission_key); ?>"
											<?php checked(in_array($transmission_key, (array) ( $search_params['transmission'] ?? array() ))); ?>>
										<span class="rv-checkbox-custom"></span>
										<span class="rv-option-label"><?php echo esc_html($transmission_label); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>

					<!-- Seats -->
					<?php if (! empty($filter_options['seats'])) : ?>
						<div class="rv-filter-group">
							<h4 class="rv-filter-title"><?php esc_html_e('Seats', 'mhm-rentiva'); ?></h4>
							<div class="rv-filter-options">
								<?php foreach ($filter_options['seats'] as $seats) : ?>
									<label class="rv-filter-option">
										<input type="checkbox" name="seats[]" value="<?php echo esc_attr($seats); ?>"
											<?php checked(in_array($seats, (array) ( $search_params['seats'] ?? array() ))); ?>>
										<span class="rv-checkbox-custom"></span>
										<span class="rv-option-label"><?php echo esc_html($seats); ?> <?php esc_html_e('seats', 'mhm-rentiva'); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>

					<!-- Year Range -->
					<div class="rv-filter-group">
						<h4 class="rv-filter-title"><?php esc_html_e('Year Range', 'mhm-rentiva'); ?></h4>
						<div class="rv-year-range">
							<div class="rv-year-inputs">
								<input type="number" name="year_min" placeholder="<?php esc_html_e('Min', 'mhm-rentiva'); ?>"
									value="<?php echo esc_attr($search_params['year_min'] ?? ''); ?>"
									min="<?php echo esc_attr($filter_options['year_range']['min']); ?>"
									max="<?php echo esc_attr($filter_options['year_range']['max']); ?>">
								<span class="rv-year-separator">-</span>
								<input type="number" name="year_max" placeholder="<?php esc_html_e('Max', 'mhm-rentiva'); ?>"
									value="<?php echo esc_attr($search_params['year_max'] ?? ''); ?>"
									min="<?php echo esc_attr($filter_options['year_range']['min']); ?>"
									max="<?php echo esc_attr($filter_options['year_range']['max']); ?>">
							</div>
							<div class="rv-year-slider" id="rv-year-slider"></div>
						</div>
					</div>

					<!-- Mileage -->
					<div class="rv-filter-group">
						<h4 class="rv-filter-title"><?php esc_html_e('Maximum Mileage', 'mhm-rentiva'); ?></h4>
						<div class="rv-mileage-range">
							<input type="number" name="mileage_max" placeholder="<?php esc_html_e('Max mileage', 'mhm-rentiva'); ?>"
								value="<?php echo esc_attr($search_params['mileage_max'] ?? ''); ?>"
								min="0" step="1000">
							<span class="rv-mileage-unit"><?php esc_html_e('km', 'mhm-rentiva'); ?></span>
						</div>
					</div>
				</form>
			</div>
		<?php endif; ?>

		<!-- Results Area -->
		<div class="rv-results-main">
			<!-- Loading Indicator -->
			<div class="rv-loading-indicator" style="display: none;">
				<div class="rv-spinner"></div>
				<span><?php esc_html_e('Loading results...', 'mhm-rentiva'); ?></span>
			</div>

			<!-- Results Container -->
			<!-- PERMANENT LAYOUT WRAPPER: Holds the state (Grid/List) -->
			<div class="rv-results-content-wrapper rv-layout-<?php echo esc_attr($active_layout); ?>">

				<!-- CONTENT TARGET: AJAX updates replace content inside here -->
				<div class="rv-vehicle-grid-wrapper">
					<?php if (! empty($vehicles)) : ?>
						<?php foreach ($vehicles as $vehicle) : ?>
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template output is escaped internally at field level.
							echo \MHMRentiva\Admin\Frontend\Shortcodes\SearchResults::render_vehicle_card($vehicle, $active_layout, $atts);
							?>
						<?php endforeach; ?>
					<?php else : ?>
						<div class="rv-no-results">
							<div class="rv-no-results-icon">&#x1F697;</div>
							<h3><?php esc_html_e('No vehicles found', 'mhm-rentiva'); ?></h3>
							<p><?php esc_html_e('Try adjusting your search criteria or filters.', 'mhm-rentiva'); ?></p>
							<a href="<?php echo esc_url(\MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_search')); ?>" class="rv-back-to-search">
								<?php esc_html_e('Back to Search', 'mhm-rentiva'); ?>
							</a>
						</div>
					<?php endif; ?>
				</div>

			</div>

			<!-- Pagination -->
			<?php if (( $atts['show_pagination'] ?? '1' ) === '1' && $max_pages > 1) : ?>
				<div class="rv-pagination">
					<?php
					$pagination_args = array(
						'total'        => $max_pages,
						'current'      => $current_page,
						'format'       => '?page=%#%',
						'show_all'     => false,
						'end_size'     => 1,
						'mid_size'     => 2,
						'prev_next'    => true,
						'prev_text'    => esc_html__('Previous', 'mhm-rentiva'),
						'next_text'    => esc_html__('Next', 'mhm-rentiva'),
						'type'         => 'list',
						'add_args'     => false,
						'add_fragment' => '',
					);

					echo wp_kses_post( (string) paginate_links($pagination_args));
					?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>