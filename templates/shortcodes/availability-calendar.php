<?php

/**
 * Availability Calendar Template
 *
 * @var array $args Template data
 */

if (! defined('ABSPATH')) {
	exit;
}



// Get template data (variables from extract)
$atts              = $atts ?? array();
$vehicle           = $vehicle ?? null;
$vehicle_id        = $vehicle_id ?? 0;
$vehicles_list     = $vehicles_list ?? array();
$start_month       = $start_month ?? wp_date('Y-m');
$months_to_show    = $months_to_show ?? 3;
$availability_data = $availability_data ?? array();
$pricing_data      = $pricing_data ?? array();
$current_user      = $current_user ?? null;


// Shortcode parameters
$show_pricing         = $atts['show_pricing'] ?? apply_filters('mhm_rentiva/availability_calendar/show_pricing', '1');
$show_seasonal_prices = $atts['show_seasonal_prices'] ?? apply_filters('mhm_rentiva/availability_calendar/show_seasonal_prices', '1');
$show_discounts       = $atts['show_discounts'] ?? apply_filters('mhm_rentiva/availability_calendar/show_discounts', '1');
$show_booking_btn     = $atts['show_booking_btn'] ?? apply_filters('mhm_rentiva/availability_calendar/show_booking_btn', '1');
$theme                = $atts['theme'] ?? apply_filters('mhm_rentiva/availability_calendar/theme', 'default');
$class                = $atts['class'] ?? '';
$integrate_pricing    = $atts['integrate_pricing'] ?? apply_filters('mhm_rentiva/availability_calendar/integrate_pricing', '1');

// If vehicle_id is provided, get the vehicle object
if ($vehicle_id > 0 && ! $vehicle) {
	$vehicle = get_post($vehicle_id);
}

if (! $vehicle && $vehicle_id > 0) {
	echo '<div class="rv-availability-error show-error">' . esc_html__('Vehicle not found.', 'mhm-rentiva') . '</div>';
	return;
}

// If no vehicle is selected but vehicles exist, select the first one
if ($vehicle_id === 0 && ! empty($vehicles_list)) {
	$vehicle_id = $vehicles_list[0]['id'];
	$vehicle    = get_post($vehicle_id);
}

if (empty($vehicles_list)) {
	echo '<div class="rv-availability-error show-error">' . esc_html__('No vehicles found. Please add vehicles first.', 'mhm-rentiva') . '</div>';
	return;
}
?>

<?php
// Get vehicle price
$vehicle_price = 0;
$is_available  = true;
$status_text   = '';

if ($vehicle_id > 0) {
	$vehicle_price = floatval(get_post_meta($vehicle_id, '_mhm_rentiva_price_per_day', true) ?: 0);

	// Check Status
	$status = get_post_meta($vehicle_id, '_mhm_vehicle_status', true);
	if (empty($status)) {
		$old_availability = get_post_meta($vehicle_id, '_mhm_vehicle_availability', true);
		if ($old_availability === '0' || $old_availability === 'passive' || $old_availability === 'inactive') {
			$status = 'inactive';
		} elseif ($old_availability === '1' || $old_availability === 'active') {
			$status = 'active';
		} elseif ($old_availability === 'maintenance') {
			$status = 'maintenance';
		} else {
			$status = 'active'; // Default
		}
	}

	$is_available = ($status === 'active');
	$status_text  = $is_available ? __('Available', 'mhm-rentiva') : __('Out of Order', 'mhm-rentiva');
}
?>

<div class="rv-availability-calendar rv-theme-<?php echo esc_attr($theme); ?> <?php echo esc_attr($class); ?>"
	data-vehicle-id="<?php echo esc_attr($vehicle_id); ?>"
	data-vehicle-price="<?php echo esc_attr($vehicle_price); ?>"
	data-start-month="<?php echo esc_attr($start_month); ?>"
	data-months-to-show="<?php echo esc_attr($months_to_show); ?>">

	<!-- Calendar Header -->


	<!-- High-Fidelity Vehicle Card (Consolidated UI Migration) -->
	<?php if ($selected_vehicle) : ?>
		<div class="rv-vehicle-card-hifi-wrapper">
			<div class="rv-vehicle-card-hifi-modern">
				<!-- Favorite Button - Top right corner -->
				<button type="button"
					class="rv-vcal-favorite-btn <?php echo ($selected_vehicle['favorite'] ?? false) ? 'favorited is-favorited' : ''; ?>"
					data-vehicle-id="<?php echo esc_attr($selected_vehicle['id']); ?>"
					aria-label="<?php echo ($selected_vehicle['favorite'] ?? false) ? esc_html__('Remove from favorites', 'mhm-rentiva') : esc_html__('Add to favorites', 'mhm-rentiva'); ?>"
					aria-pressed="<?php echo ($selected_vehicle['favorite'] ?? false) ? 'true' : 'false'; ?>">
					<svg class="rv-heart-icon <?php echo ($selected_vehicle['favorite'] ?? false) ? 'favorited' : ''; ?>" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
					</svg>
				</button>

				<div class="rv-vehicle-info">
					<?php if ($selected_vehicle['image_url']) : ?>
						<div class="rv-vehicle-image-wrapper">
							<img class="rv-vehicle-image" src="<?php echo esc_url($selected_vehicle['image_url']); ?>" alt="<?php echo esc_attr($selected_vehicle['title']); ?>">
							<?php if (isset($selected_vehicle['availability']) && ! $selected_vehicle['availability']['is_available']) : ?>
								<div class="rv-vehicle-status-badge rv-status-unavailable">
									<?php echo esc_html($selected_vehicle['availability']['text']); ?>
								</div>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<div class="rv-vehicle-details">
						<h4 class="rv-vehicle-title"><?php echo esc_html($selected_vehicle['title']); ?></h4>
						<?php if ($selected_vehicle['excerpt']) : ?>
							<p class="rv-vehicle-excerpt"><?php echo esc_html($selected_vehicle['excerpt']); ?></p>
						<?php endif; ?>

						<!-- All Features and Meta Information -->
						<div class="rv-vehicle-features">
							<?php if (! empty($selected_vehicle['features'])) : ?>
								<?php foreach (array_slice($selected_vehicle['features'], 0, 4) as $feature) : ?>
									<div class="rv-feature-item">
										<?php echo $feature['svg']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
										?>
										<span class="rv-feature-text"><?php echo esc_html($feature['text']); ?></span>
									</div>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>

						<!-- Rating -->
						<?php if (isset($selected_vehicle['rating']) && is_array($selected_vehicle['rating']) && $selected_vehicle['rating']['average'] > 0) : ?>
							<div class="rv-vehicle-rating-block" style="display: flex; align-items: center; gap: 8px; margin-top: 12px; margin-bottom: 5px;">
								<div class="rv-stars" style="display: flex; align-items: center; gap: 2px;">
									<?php
									$rating_avg = (float)$selected_vehicle['rating']['average'];
									for ($i = 1; $i <= 5; $i++) :
										$is_filled = $i <= floor($rating_avg);
										$is_half = ($i == ceil($rating_avg)) && ($rating_avg - floor($rating_avg) >= 0.5);
										$fill_color = $is_filled || $is_half ? '#fbbf24' : '#cbd5e1';
										$stroke_color = $is_filled || $is_half ? '#d97706' : '#cbd5e1';
									?>
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="<?php echo esc_attr($stroke_color); ?>" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="display: block; width: 16px; height: 16px;">
											<?php if ($is_half) : ?>
												<defs>
													<linearGradient id="halfStar-cal-<?php echo esc_attr($selected_vehicle['id']); ?>-<?php echo esc_attr($i); ?>">
														<stop offset="50%" stop-color="#fbbf24" />
														<stop offset="50%" stop-color="#cbd5e1" />
													</linearGradient>
												</defs>
												<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" fill="url(#halfStar-cal-<?php echo esc_attr($selected_vehicle['id']); ?>-<?php echo esc_attr($i); ?>)" stroke="none" />
											<?php else : ?>
												<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" fill="<?php echo esc_attr($fill_color); ?>" />
											<?php endif; ?>
										</svg>
									<?php endfor; ?>
								</div>
								<span class="rv-rating-count" style="color: #64748b; font-size: 0.875rem;">(<?php echo intval($selected_vehicle['rating']['count']); ?>)</span>
							</div>
						<?php endif; ?>

						<div class="rv-vehicle-price-container">
							<p class="rv-vehicle-price">
								<?php echo esc_html($selected_vehicle['formatted_price']); ?>
								<?php echo esc_html($selected_vehicle['currency_symbol']); ?><?php echo esc_html(esc_html__('/day', 'mhm-rentiva')); ?>
							</p>
						</div>
					</div>
				</div>

				<!-- Vehicle Switcher -->
				<?php if (count($vehicles_list) > 1) : ?>
					<div class="rv-vehicle-switcher-modern">
						<button class="rv-switch-vehicle-btn" type="button" data-vehicles='<?php echo esc_attr(wp_json_encode($vehicles_list)); ?>'>
							<span class="dashicons dashicons-arrow-down-alt2"></span>
							<?php echo esc_html__('Change Vehicle', 'mhm-rentiva'); ?>
						</button>
					</div>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>


	<!-- Status Legend (Top per image) -->
	<div class="rv-availability-legend rv-legend-modern" style="<?php echo ! $is_available ? 'display: none;' : ''; ?>">
		<div class="rv-legend-item">
			<span class="rv-legend-color rv-status-available"></span>
			<span class="rv-legend-text"><?php esc_html_e('Available', 'mhm-rentiva'); ?></span>
		</div>
		<div class="rv-legend-item">
			<span class="rv-legend-color rv-status-partial"></span>
			<span class="rv-legend-text"><?php esc_html_e('Partially Reserved', 'mhm-rentiva'); ?></span>
		</div>
		<div class="rv-legend-item">
			<span class="rv-legend-color rv-status-booked"></span>
			<span class="rv-legend-text"><?php esc_html_e('Reserved', 'mhm-rentiva'); ?></span>
		</div>
	</div>

	<!-- Calendar Grid Hint -->
	<div class="rv-calendar-hint" style="<?php echo ! $is_available ? 'display: none;' : ''; ?>">
		<span class="dashicons dashicons-info-outline"></span>
		<?php echo esc_html__('Click on start and end dates to select your rental period.', 'mhm-rentiva'); ?>
	</div>

	<!-- Calendar Controls (Month & Navigation) -->
	<div class="rv-calendar-controls" style="<?php echo ! $is_available ? 'display: none;' : ''; ?>">
		<button type="button" class="rv-control-btn rv-prev-months" data-action="prev">
			<span class="dashicons dashicons-arrow-left-alt2"></span>
			<?php echo esc_html__('Previous', 'mhm-rentiva'); ?>
		</button>

		<div class="rv-month-display">
			<span class="rv-month-name"><?php echo esc_html(wp_date('F Y', strtotime($start_month))); ?></span>
		</div>

		<button type="button" class="rv-control-btn rv-next-months" data-action="next">
			<?php echo esc_html__('Next', 'mhm-rentiva'); ?>
			<span class="dashicons dashicons-arrow-right-alt2"></span>
		</button>
	</div>

	<div class="rv-availability-grid" style="<?php echo ! $is_available ? 'display: none;' : ''; ?>">
		<?php if (empty($availability_data)) : ?>
			<div class="rv-no-data-message">
				<div class="rv-no-data-content">
					<span class="dashicons dashicons-calendar-alt"></span>
					<h4><?php echo esc_html__('Calendar Data Not Found', 'mhm-rentiva'); ?></h4>
					<p><?php echo esc_html__('Please select a vehicle or check vehicle data.', 'mhm-rentiva'); ?></p>
					<?php if (! empty($vehicles_list)) : ?>
						<div class="rv-vehicle-selector">
							<label for="rv-availability-vehicle-select-fallback" class="rv-selector-label">
								<?php echo esc_html__('Select Vehicle:', 'mhm-rentiva'); ?>
							</label>
							<select id="rv-availability-vehicle-select-fallback" class="rv-vehicle-dropdown" data-current-vehicle="0">
								<option value=""><?php echo esc_html__('Select vehicle...', 'mhm-rentiva'); ?></option>
								<?php foreach ($vehicles_list as $vehicle_option) : ?>
									<option value="<?php echo esc_attr($vehicle_option['id']); ?>">
										<?php echo esc_html($vehicle_option['title']); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					<?php endif; ?>
				</div>
			</div>
		<?php else : ?>
			<?php $month_key = array_key_first($availability_data); ?>
			<?php $month_data = $availability_data[$month_key]; ?>
			<div class="rv-month-container" data-month="<?php echo esc_attr($month_key); ?>">
				<!-- Weekday Headers -->
				<div class="rv-calendar-weekdays">
					<div class="rv-weekday"><?php echo esc_html__('Mon', 'mhm-rentiva'); ?></div>
					<div class="rv-weekday"><?php echo esc_html__('Tue', 'mhm-rentiva'); ?></div>
					<div class="rv-weekday"><?php echo esc_html__('Wed', 'mhm-rentiva'); ?></div>
					<div class="rv-weekday"><?php echo esc_html__('Thu', 'mhm-rentiva'); ?></div>
					<div class="rv-weekday"><?php echo esc_html__('Fri', 'mhm-rentiva'); ?></div>
					<div class="rv-weekday"><?php echo esc_html__('Sat', 'mhm-rentiva'); ?></div>
					<div class="rv-weekday"><?php echo esc_html__('Sun', 'mhm-rentiva'); ?></div>
				</div>

				<!-- Calendar Days -->
				<div class="rv-calendar-days">
					<?php
					// Find which day of the week the first day of the month is
					$first_day = (int) gmdate('N', strtotime((string) $month_key . '-01')); // Monday = 1, Sunday = 7
					$first_day = $first_day === 7 ? 0 : $first_day; // Adjusted for simplified loop below if needed, but keeping current logic:
					$first_day = ($first_day === 7) ? 0 : $first_day - 1; // Monday = 0, Sunday = 6 actually?
					// Wait, standard WP calendar logic: Sunday is 0 if needed, but here it says Monday=0.
					// Let's stick closer to core but fix date() -> gmdate()
					$first_day = (int) gmdate('N', strtotime($month_key . '-01'));
					$first_day = ($first_day === 7) ? 0 : $first_day - 1;


					// Placeholder for empty days
					for ($i = 0; $i < $first_day; $i++) {
						echo '<div class="rv-calendar-day rv-day-empty"></div>';
					}

					// Show days of the month
					foreach ($month_data['days'] as $date => $day_data) {
						$day_classes = array(
							'rv-calendar-day',
							'rv-day-' . $day_data['status'],
							$day_data['is_weekend'] ? 'rv-day-weekend' : '',
							$day_data['is_today'] ? 'rv-today' : '',
							$day_data['is_past'] ? 'rv-past' : '',
						);

						$day_classes = array_filter($day_classes);
						$day_class   = implode(' ', $day_classes);

						// Tooltip content
						$tooltip_content = '';
						if (! empty($day_data['bookings'])) {
							$booking_titles  = array_column($day_data['bookings'], 'title');
							$tooltip_content = 'data-tooltip="' . esc_attr(implode(', ', $booking_titles)) . '"';
						}

						// Price information
						$price_info = '';
						if ($show_pricing === '1' && isset($pricing_data[$month_key]['days'][$date])) {
							$price_data = $pricing_data[$month_key]['days'][$date];
							$price_info = 'data-price="' . esc_attr($price_data['day_price']) . '"';
							if ($price_data['has_discount']) {
								$price_info .= ' data-discount="' . esc_attr($price_data['discount_amount']) . '"';
							}
						}

						printf(
							'<div class="%s" data-date="%s" %s %s>',
							esc_attr($day_class),
							esc_attr((string) $date),
							wp_kses_post((string) $tooltip_content),
							wp_kses_post((string) $price_info)
						);

						echo '<span class="rv-day-number">' . esc_html($day_data['day_number']) . '</span>';

						// Price indicator
						if ($show_pricing === '1' && isset($pricing_data[$month_key]['days'][$date])) {
							$price_data = $pricing_data[$month_key]['days'][$date];
							echo '<div class="rv-day-price">';
							echo '<span class="rv-price-amount">' . esc_html(number_format($price_data['day_price'], 0, ',', '.')) . ' ' . esc_html(apply_filters('mhm_rentiva/currency_symbol', '₺')) . '</span>';
							if ($price_data['has_discount']) {
								echo '<span class="rv-discount-badge">%' . esc_html(round(($price_data['discount_amount'] / $price_data['base_price']) * 100)) . '</span>';
							}
							echo '</div>';
						}

						// Booking status indicator
						if (! empty($day_data['bookings'])) {
							echo '<div class="rv-day-indicators">';
							foreach ($day_data['bookings'] as $booking) {
								echo '<span class="rv-booking-indicator rv-day-' . esc_attr($booking['status']) . '" title="' . esc_attr($booking['title']) . '"></span>';
							}
							echo '</div>';
						}

						echo '</div>';
					}
					?>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<div class="rv-calendar-unavailable-message" style="text-align: center; padding: 40px; background: #fff; border: 1px solid #ddd; border-radius: 8px; margin-top: 20px; <?php echo $is_available ? 'display: none;' : ''; ?>">
		<div style="font-size: 48px; margin-bottom: 20px;">🚫</div>
		<h3 style="color: #e74c3c; margin-bottom: 10px;"><?php echo esc_html__('Vehicle Unavailable', 'mhm-rentiva'); ?></h3>
		<p><?php echo esc_html__('This vehicle is currently out of order and cannot be booked. Please choose another vehicle.', 'mhm-rentiva'); ?></p>
		<?php if (count($vehicles_list) > 1) : ?>
			<button class="rv-switch-vehicle-btn rv-btn rv-btn-primary" type="button" data-vehicles='<?php echo esc_attr(wp_json_encode($vehicles_list)); ?>' style="margin-top: 20px;">
				<?php echo esc_html__('Choose Another Vehicle', 'mhm-rentiva'); ?>
			</button>
		<?php endif; ?>
	</div>

	<!-- Selected Date Information -->
	<div class="rv-selected-dates rv-hidden">
		<div class="rv-selected-info">
			<h4><?php echo esc_html__('Selected Dates', 'mhm-rentiva'); ?></h4>
			<div class="rv-date-range">
				<span class="rv-start-date"></span>
				<span class="rv-date-separator"> - </span>
				<span class="rv-end-date"></span>
			</div>
			<div class="rv-date-format-info">
				<?php echo esc_html__('Format:', 'mhm-rentiva'); ?> <?php echo esc_html(get_option('date_format', 'd.m.Y')); ?>
			</div>
			<div class="rv-date-details">
				<div class="rv-total-days">
					<span class="rv-label"><?php echo esc_html__('Total Days:', 'mhm-rentiva'); ?></span>
					<span class="rv-value rv-days-count"></span>
				</div>
				<?php if ($show_pricing === '1') : ?>
					<div class="rv-total-price">
						<span class="rv-label"><?php echo esc_html__('Total Price:', 'mhm-rentiva'); ?></span>
						<span class="rv-value rv-price-total"></span>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<?php if ($show_booking_btn === '1') : ?>
			<div class="rv-booking-actions">
				<button type="button" class="rv-button rv-button-primary rv-book-now-btn" disabled>
					<span class="dashicons dashicons-calendar-alt"></span>
					<?php echo esc_html__('Make Reservation', 'mhm-rentiva'); ?>
				</button>
			</div>
		<?php endif; ?>
	</div>

	<!-- Loading Indicator -->
	<div class="rv-availability-loading rv-hidden">
		<div class="rv-loading-spinner"></div>
		<span class="rv-loading-text"><?php echo esc_html__('Loading...', 'mhm-rentiva'); ?></span>
	</div>

	<!-- Error Message -->
	<div class="rv-availability-error rv-hidden">
		<span class="rv-error-text"></span>
	</div>

</div>

<!-- JavaScript variables are now loaded via wp_localize_script -->