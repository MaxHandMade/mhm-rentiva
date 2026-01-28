<?php

/**
 * Default template: Vehicle search form
 * Variables:
 * - array  $atts
 * - array  $form_data
 * - string $nonce_field
 */
if (! defined('ABSPATH')) {
	exit;
}


?>

<div class="rv-search-form <?php echo esc_attr($atts['class'] ?? ''); ?>" id="rv-search-form">
	<div class="rv-search-header">
		<div class="rv-search-header">
			<h3><?php echo esc_html__('Vehicle Search', 'mhm-rentiva'); ?></h3>
			<p class="rv-search-description">
				<?php echo esc_html__('Find the vehicle that suits your needs. Filter by date, price and features.', 'mhm-rentiva'); ?>
			</p>
		</div>

		<form class="rv-search-filters" id="rv-search-filters" method="post">
			<?php echo wp_kses_post((string) $nonce_field); ?>

			<div class="rv-search-row">
				<!-- Keyword -->
				<div class="rv-search-field rv-search-keyword">
					<label for="rv-keyword"><?php echo esc_html__('Keyword', 'mhm-rentiva'); ?></label>
					<input type="text" id="rv-keyword" name="keyword" placeholder="<?php echo esc_attr__('Vehicle brand, model...', 'mhm-rentiva'); ?>" />
				</div>

				<?php if (($atts['show_date_picker'] ?? '1') === '1') : ?>
					<!-- Date Selection -->
					<div class="rv-search-field rv-search-dates">
						<label for="rv-start-date"><?php echo esc_html__('Start Date', 'mhm-rentiva'); ?></label>
						<input type="text" id="rv-start-date" name="start_date" placeholder="<?php echo esc_attr__('Start date', 'mhm-rentiva'); ?>" value="<?php echo esc_attr($form_data['pickup_date_formatted'] ?? ''); ?>" readonly />
					</div>

					<div class="rv-search-field rv-search-dates">
						<label for="rv-end-date"><?php echo esc_html__('End Date', 'mhm-rentiva'); ?></label>
						<input type="text" id="rv-end-date" name="end_date" placeholder="<?php echo esc_attr__('End date', 'mhm-rentiva'); ?>" value="<?php echo esc_attr($form_data['return_date_formatted'] ?? ''); ?>" readonly />
					</div>
				<?php endif; ?>
			</div>

			<div class="rv-search-row">
				<?php if (($atts['show_price_range'] ?? '1') === '1') : ?>
					<!-- Price Range -->
					<div class="rv-search-field rv-search-price">
						<label for="rv-min-price"><?php echo esc_html__('Minimum Price', 'mhm-rentiva'); ?> (<?php echo esc_html(apply_filters('mhm_rentiva/currency_symbol', '₺')); ?>)</label>
						<input type="number" id="rv-min-price" name="min_price" min="0" step="10" placeholder="0" />
					</div>

					<div class="rv-search-field rv-search-price">
						<label for="rv-max-price"><?php echo esc_html__('Maximum Price', 'mhm-rentiva'); ?> (<?php echo esc_html(apply_filters('mhm_rentiva/currency_symbol', '₺')); ?>)</label>
						<input type="number" id="rv-max-price" name="max_price" min="0" step="10" placeholder="1000" />
					</div>
				<?php endif; ?>

			</div>

			<div class="rv-search-row">
				<?php if (($atts['show_fuel_type'] ?? '1') === '1') : ?>
					<!-- Fuel Type -->
					<div class="rv-search-field rv-search-fuel">
						<label for="rv-fuel-type"><?php echo esc_html__('Fuel Type', 'mhm-rentiva'); ?></label>
						<select id="rv-fuel-type" name="fuel_type">
							<?php foreach ($form_data['fuel_types'] as $value => $label) : ?>
								<option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endif; ?>

				<?php if (($atts['show_transmission'] ?? '1') === '1') : ?>
					<!-- Transmission Type -->
					<div class="rv-search-field rv-search-transmission">
						<label for="rv-transmission"><?php echo esc_html__('Transmission Type', 'mhm-rentiva'); ?></label>
						<select id="rv-transmission" name="transmission">
							<?php foreach ($form_data['transmissions'] as $value => $label) : ?>
								<option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endif; ?>

				<?php if (($atts['show_seats'] ?? '1') === '1') : ?>
					<!-- Seat Count -->
					<div class="rv-search-field rv-search-seats">
						<label for="rv-min-seats"><?php echo esc_html__('Minimum Seats', 'mhm-rentiva'); ?></label>
						<select id="rv-min-seats" name="min_seats">
							<?php foreach ($form_data['seat_options'] as $value => $label) : ?>
								<option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endif; ?>
			</div>

			<div class="rv-search-actions">
				<button type="submit" class="rv-search-btn" id="rv-search-btn">
					<span class="rv-search-btn-text"><?php echo esc_html__('Search Vehicles', 'mhm-rentiva'); ?></span>
					<span class="rv-search-btn-loading" style="display: none;">
						<span class="rv-spinner"></span>
						<?php echo esc_html__('Searching...', 'mhm-rentiva'); ?>
					</span>
				</button>

				<button type="button" class="rv-reset-btn" id="rv-reset-btn">
					<?php echo esc_html__('Clear', 'mhm-rentiva'); ?>
				</button>
			</div>
		</form>

		<!-- Search Results -->
		<div class="rv-search-results" id="rv-search-results" style="display: none;">
			<div class="rv-results-header">
				<h4 class="rv-results-title"><?php echo esc_html__('Search Results', 'mhm-rentiva'); ?></h4>
				<div class="rv-results-count">
					<span id="rv-results-count">0</span> <?php echo esc_html__('vehicles found', 'mhm-rentiva'); ?>
				</div>
			</div>

			<div class="rv-results-grid" id="rv-results-grid">
				<!-- Results will be loaded here -->
			</div>

			<div class="rv-results-pagination" id="rv-results-pagination" style="display: none;">
				<!-- Pagination will be loaded here -->
			</div>
		</div>

		<!-- No Results Found -->
		<div class="rv-no-results" id="rv-no-results" style="display: none;">
			<div class="rv-no-results-content">
				<div class="rv-no-results-icon">🚗</div>
				<h4><?php echo esc_html__('No Vehicles Found', 'mhm-rentiva'); ?></h4>
				<p><?php echo esc_html__('No vehicles found matching your criteria. Please try different search criteria.', 'mhm-rentiva'); ?></p>
				<button type="button" class="rv-reset-btn" id="rv-reset-from-no-results">
					<?php echo esc_html__('Clear Filters', 'mhm-rentiva'); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- Hidden inputs for redirect -->
	<?php if (! empty($form_data['redirect_url'])) : ?>
		<input type="hidden" id="rv-redirect-url" value="<?php echo esc_url($form_data['redirect_url']); ?>" />
	<?php endif; ?>

	<!-- Hidden inputs for pagination -->
	<input type="hidden" id="rv-current-page" value="1" />
	<input type="hidden" id="rv-per-page" value="<?php echo esc_attr($atts['results_per_page'] ?? '12'); ?>" />