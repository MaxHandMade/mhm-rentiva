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

$unique_id = uniqid('rv_search_full_');

// Gutenberg style and class support
$wrapper_style = isset($atts['style']) ? $atts['style'] : '';
$wrapper_class = isset($atts['class']) ? $atts['class'] : '';

// Build robust style string combining separate attributes
if (empty($wrapper_style)) {
	if (!empty($atts['minwidth'])) $wrapper_style .= 'min-width:' . $atts['minwidth'] . ';';
	if (!empty($atts['maxwidth'])) $wrapper_style .= 'max-width:' . $atts['maxwidth'] . ';';
	if (!empty($atts['height']))   $wrapper_style .= 'height:' . $atts['height'] . ';';
}

// Final cleanup: Ensure unit suffix for numeric-only dimensions (e.g., 900 -> 900px)
if (!empty($wrapper_style)) {
	$wrapper_style = preg_replace('/(width|height|min-width|max-width):\s*(\d+)(?![\w%])/', '$1:$2px', $wrapper_style);
}
?>

<div id="<?php echo esc_attr($unique_id); ?>_wrapper"
	class="rv-search-block-wrapper <?php echo esc_attr($wrapper_class); ?>"
	style="<?php echo esc_attr($wrapper_style); ?>">
	<div class="rv-search-header">
		<h3><?php echo esc_html__('Vehicle Search', 'mhm-rentiva'); ?></h3>
		<p class="rv-search-description">
			<?php echo esc_html__('Find the vehicle that suits your needs. Filter by date, price and features.', 'mhm-rentiva'); ?>
		</p>
	</div>

	<form class="rv-search-filters js-rv-search-form" id="<?php echo esc_attr($unique_id); ?>_form" method="post" data-instance-id="<?php echo esc_attr($unique_id); ?>">
		<?php echo wp_kses_post((string) $nonce_field); ?>

		<div class="rv-search-row">
			<!-- Keyword -->
			<div class="rv-search-field rv-search-keyword">
				<label for="<?php echo esc_attr($unique_id); ?>_keyword"><?php echo esc_html__('Keyword', 'mhm-rentiva'); ?></label>
				<input type="text" id="<?php echo esc_attr($unique_id); ?>_keyword" name="keyword" placeholder="<?php echo esc_attr__('Vehicle brand, model...', 'mhm-rentiva'); ?>" class="js-keyword" />
			</div>

			<?php if (($atts['show_date_picker'] ?? '1') === '1') : ?>
				<!-- Date Selection -->
				<div class="rv-search-field rv-search-dates">
					<label for="<?php echo esc_attr($unique_id); ?>_start_date"><?php echo esc_html__('Start Date', 'mhm-rentiva'); ?></label>
				<input type="date" id="<?php echo esc_attr($unique_id); ?>_start_date" name="start_date" placeholder="<?php echo esc_attr__('Start date', 'mhm-rentiva'); ?>" value="<?php echo esc_attr($form_data['pickup_date'] ?? ''); ?>" class="rv-date-input js-datepicker js-start-date" autocomplete="off" />
				</div>

				<div class="rv-search-field rv-search-dates">
					<label for="<?php echo esc_attr($unique_id); ?>_end_date"><?php echo esc_html__('End Date', 'mhm-rentiva'); ?></label>
				<input type="date" id="<?php echo esc_attr($unique_id); ?>_end_date" name="end_date" placeholder="<?php echo esc_attr__('End date', 'mhm-rentiva'); ?>" value="<?php echo esc_attr($form_data['return_date'] ?? ''); ?>" class="rv-date-input js-datepicker js-end-date" autocomplete="off" />
				</div>
			<?php endif; ?>
		</div>

		<?php if (($atts['show_price_range'] ?? '1') === '1') : ?>
			<div class="rv-search-row">
				<!-- Price Range -->
				<div class="rv-search-field rv-search-price">
					<label for="<?php echo esc_attr($unique_id); ?>_min_price"><?php echo esc_html__('Minimum Price', 'mhm-rentiva'); ?> (<?php echo esc_html(apply_filters('mhm_rentiva/currency_symbol', '₺')); ?>)</label>
					<input type="number" id="<?php echo esc_attr($unique_id); ?>_min_price" name="min_price" min="0" step="10" placeholder="0" class="js-min-price" />
				</div>

				<div class="rv-search-field rv-search-price">
					<label for="<?php echo esc_attr($unique_id); ?>_max_price"><?php echo esc_html__('Maximum Price', 'mhm-rentiva'); ?> (<?php echo esc_html(apply_filters('mhm_rentiva/currency_symbol', '₺')); ?>)</label>
					<input type="number" id="<?php echo esc_attr($unique_id); ?>_max_price" name="max_price" min="0" step="10" placeholder="1000" class="js-max-price" />
				</div>
			</div>
		<?php endif; ?>

		<div class="rv-search-row">
			<?php if (($atts['show_fuel_type'] ?? '1') === '1') : ?>
				<!-- Fuel Type -->
				<div class="rv-search-field rv-search-fuel">
					<label for="<?php echo esc_attr($unique_id); ?>_fuel_type"><?php echo esc_html__('Fuel Type', 'mhm-rentiva'); ?></label>
					<select id="<?php echo esc_attr($unique_id); ?>_fuel_type" name="fuel_type" class="js-fuel-type">
						<?php foreach ($form_data['fuel_types'] as $value => $label) : ?>
							<option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<?php if (($atts['show_transmission'] ?? '1') === '1') : ?>
				<!-- Transmission Type -->
				<div class="rv-search-field rv-search-transmission">
					<label for="<?php echo esc_attr($unique_id); ?>_transmission"><?php echo esc_html__('Transmission Type', 'mhm-rentiva'); ?></label>
					<select id="<?php echo esc_attr($unique_id); ?>_transmission" name="transmission" class="js-transmission">
						<?php foreach ($form_data['transmissions'] as $value => $label) : ?>
							<option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<?php if (($atts['show_seats'] ?? '1') === '1') : ?>
				<!-- Seat Count -->
				<div class="rv-search-field rv-search-seats">
					<label for="<?php echo esc_attr($unique_id); ?>_min_seats"><?php echo esc_html__('Minimum Seats', 'mhm-rentiva'); ?></label>
					<select id="<?php echo esc_attr($unique_id); ?>_min_seats" name="min_seats" class="js-min-seats">
						<?php foreach ($form_data['seat_options'] as $value => $label) : ?>
							<option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>
		</div>

		<div class="rv-search-actions">
			<button type="submit" class="rv-search-btn js-search-btn">
				<span class="rv-search-btn-text text"><?php echo esc_html__('Search Vehicles', 'mhm-rentiva'); ?></span>
				<span class="rv-search-btn-loading loading" style="display: none;">
					<span class="rv-spinner"></span>
					<?php echo esc_html__('Searching...', 'mhm-rentiva'); ?>
				</span>
			</button>

			<button type="button" class="rv-reset-btn js-reset-btn">
				<?php echo esc_html__('Clear', 'mhm-rentiva'); ?>
			</button>
		</div>
	</form>

	<!-- Search Results -->
	<div class="rv-search-results js-rv-search-results" style="display: none;">
		<div class="rv-results-header">
			<h4 class="rv-results-title"><?php echo esc_html__('Search Results', 'mhm-rentiva'); ?></h4>
			<div class="rv-results-count">
				<span class="js-rv-results-count">0</span> <?php echo esc_html__('vehicles found', 'mhm-rentiva'); ?>
			</div>
		</div>

		<div class="rv-results-grid js-rv-results-grid">
			<!-- Results will be loaded here -->
		</div>

		<div class="rv-results-pagination js-rv-results-pagination" style="display: none;">
			<!-- Pagination will be loaded here -->
		</div>
	</div>

	<!-- No Results Found -->
	<div class="rv-no-results js-rv-no-results" style="display: none;">
		<div class="rv-no-results-content">
			<div class="rv-no-results-icon">🚗</div>
			<h4><?php echo esc_html__('No Vehicles Found', 'mhm-rentiva'); ?></h4>
			<p><?php echo esc_html__('No vehicles found matching your criteria. Please try different search criteria.', 'mhm-rentiva'); ?></p>
			<button type="button" class="rv-reset-btn js-reset-from-no-results">
				<?php echo esc_html__('Clear Filters', 'mhm-rentiva'); ?>
			</button>
		</div>
	</div>

	<!-- Hidden inputs for redirect -->
	<?php if (! empty($form_data['redirect_url'])) : ?>
		<input type="hidden" class="js-redirect-url" value="<?php echo esc_url($form_data['redirect_url']); ?>" />
	<?php endif; ?>

	<!-- Hidden inputs for pagination -->
	<input type="hidden" class="js-current-page" value="1" />
	<input type="hidden" class="js-per-page" value="<?php echo esc_attr($atts['results_per_page'] ?? '12'); ?>" />

</div>