<?php

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
if (! defined('ABSPATH')) {
	exit;
}



$vehicles      = $results['vehicles'] ?? array();
$total_results = $results['total'] ?? 0;
$current_page  = $results['current_page'] ?? 1;
$max_pages     = $results['max_pages'] ?? 1;
?>

<div class="rv-search-results <?php echo esc_attr($atts['class'] ?? ''); ?>" id="rv-search-results">

	<!-- Results Header -->
	<div class="rv-results-header">
		<div class="rv-results-info">
			<h1 class="rv-results-title">
				<?php
				if ($total_results > 0) {
					printf(
						/* translators: %d placeholder. */
						esc_html(_n('%d vehicle found', '%d vehicles found', $total_results, 'mhm-rentiva')),
						esc_html(number_format_i18n($total_results))
					);
				} else {
					esc_html_e('No vehicles found', 'mhm-rentiva');
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
			<?php if (($atts['show_sorting'] ?? '1') === '1') : ?>
				<!-- Sorting -->
				<div class="rv-sorting">
					<label for="rv-sort-select"><?php esc_html_e('Sort by:', 'mhm-rentiva'); ?></label>
					<select id="rv-sort-select" name="sort" class="rv-sort-select">
						<?php foreach ($sorting_options as $value => $label) : ?>
							<option value="<?php echo esc_attr($value); ?>" <?php selected($search_params['sort'] ?? '', $value); ?>>
								<?php echo esc_html($label); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<?php if (($atts['show_view_toggle'] ?? '1') === '1') : ?>
				<!-- View Toggle -->
				<div class="rv-view-toggle">
					<button type="button" class="rv-view-btn <?php echo ($atts['layout'] ?? 'grid') === 'grid' ? 'active' : ''; ?>" data-view="grid" title="<?php esc_html_e('Grid View', 'mhm-rentiva'); ?>">
						<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
							<rect x="1" y="1" width="6" height="6" rx="1" />
							<rect x="9" y="1" width="6" height="6" rx="1" />
							<rect x="1" y="9" width="6" height="6" rx="1" />
							<rect x="9" y="9" width="6" height="6" rx="1" />
						</svg>
					</button>
					<button type="button" class="rv-view-btn <?php echo ($atts['layout'] ?? 'grid') === 'list' ? 'active' : ''; ?>" data-view="list" title="<?php esc_html_e('List View', 'mhm-rentiva'); ?>">
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

		<?php if (($atts['show_filters'] ?? '1') === '1') : ?>
			<!-- Sidebar Filters -->
			<div class="rv-filters-sidebar" id="rv-filters-sidebar">
				<div class="rv-filters-header">
					<h3><?php esc_html_e('Filters', 'mhm-rentiva'); ?></h3>
					<button type="button" class="rv-clear-filters" id="rv-clear-filters">
						<?php esc_html_e('Clear All', 'mhm-rentiva'); ?>
					</button>
				</div>

				<form class="rv-filters-form" id="rv-filters-form">
					<?php echo wp_kses_post((string) $nonce_field); ?>

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
											<?php checked(in_array($brand, (array) ($search_params['brand'] ?? array()))); ?>>
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
											<?php checked(in_array($fuel_key, (array) ($search_params['fuel_type'] ?? array()))); ?>>
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
											<?php checked(in_array($transmission_key, (array) ($search_params['transmission'] ?? array()))); ?>>
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
											<?php checked(in_array($seats, (array) ($search_params['seats'] ?? array()))); ?>>
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
			<div class="rv-loading-indicator" id="rv-loading-indicator" style="display: none;">
				<div class="rv-spinner"></div>
				<span><?php esc_html_e('Loading results...', 'mhm-rentiva'); ?></span>
			</div>

			<!-- Results Container -->
			<!-- PERMANENT LAYOUT WRAPPER: Holds the state (Grid/List) -->
			<div id="rv-results-layout-container" class="rv-layout-<?php echo esc_attr($atts['layout'] ?? 'grid'); ?>">

				<!-- CONTENT TARGET: AJAX updates replace content inside here -->
				<div id="rv-results-grid-content" class="rv-vehicle-grid-wrapper">
					<?php if (! empty($vehicles)) : ?>
						<?php foreach ($vehicles as $vehicle) : ?>
							<?php echo wp_kses_post(\MHMRentiva\Admin\Frontend\Shortcodes\SearchResults::render_vehicle_card($vehicle, $atts['layout'] ?? 'grid')); ?>
						<?php endforeach; ?>
					<?php else : ?>
						<div class="rv-no-results">
							<div class="rv-no-results-icon">🚗</div>
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
			<?php if (($atts['show_pagination'] ?? '1') === '1' && $max_pages > 1) : ?>
				<div class="rv-pagination" id="rv-pagination">
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

					echo wp_kses_post((string) paginate_links($pagination_args));
					?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>