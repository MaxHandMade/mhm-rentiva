<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * Vehicle Comparison Template
 *
 * @package MHMRentiva
 * @since 1.0.0
 */

use MHMRentiva\Helpers\Icons;
use MHMRentiva\Admin\Core\CurrencyHelper;

// Prevent direct access
if (! defined('ABSPATH')) {
	exit;
}

// Get template data
$atts             = $atts ?? array();
$vehicles         = $vehicles ?? array();
$features         = $features ?? array();
$all_vehicles     = $all_vehicles ?? array();
$max_vehicles     = $max_vehicles ?? 3;
$has_vehicles     = $has_vehicles ?? false;
$can_add_more     = $can_add_more ?? false;
$show_add_vehicle = $show_add_vehicle ?? false;


// Layout settings
$layout       = $atts['layout'] ?? 'table';
$show_prices  = ($atts['show_prices'] ?? true) === '1' || ($atts['show_prices'] ?? true) === true;
$show_booking_buttons = in_array(
	strtolower((string) ($atts['show_booking_buttons'] ?? ($atts['show_book_button'] ?? '1'))),
	array('1', 'true', 'yes', 'on'),
	true
);
$custom_class = trim($atts['class'] ?? '');

?>

<div class="rv-vehicle-comparison rv-vehicle-comparison-container rv-layout-table" data-max-vehicles="<?php echo esc_attr($max_vehicles); ?>" data-features='<?php echo esc_attr(wp_json_encode($features)); ?>' data-all-vehicles='<?php echo esc_attr(wp_json_encode($all_vehicles)); ?>'>

	<!-- Add Vehicle Section (Gated) -->
	<?php if ($show_add_vehicle) : ?>
		<div class="rv-add-vehicle-section">
			<div class="rv-add-vehicle-form">
				<div class="rv-form-row">
					<select id="rv-add-vehicle-select" class="rv-vehicle-select">
						<option value=""><?php echo esc_html__('Select a vehicle to compare', 'mhm-rentiva'); ?></option>
						<?php foreach ($all_vehicles as $vehicle) : ?>
							<option value="<?php echo esc_attr($vehicle['id']); ?>">
								<?php echo esc_html($vehicle['title']); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<button type="button" id="rv-add-vehicle-btn" class="rv-add-vehicle-btn">
						<?php echo esc_html__('Add Vehicle', 'mhm-rentiva'); ?>
					</button>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<!-- Comparison Content -->
	<?php if ($has_vehicles) : ?>
		<div class="rv-comparison-content">

			<!-- Comparison Header -->
			<div class="rv-comparison-header">
				<h3>
					<?php
					if (! empty($atts['title'])) {
						echo esc_html($atts['title']);
					} else {
						echo esc_html__('Vehicle Comparison', 'mhm-rentiva');
					}
					?>
				</h3>
				<div class="rv-comparison-count">
					<?php
					$count = count($vehicles);
					if ($count === 1) {
						echo esc_html__('1 vehicle being compared', 'mhm-rentiva');
					} else {
						/* translators: %d: number of vehicles */
						printf(esc_html__('%d vehicles being compared', 'mhm-rentiva'), (int) $count);
					}
					?>
				</div>
			</div>

			<!-- Table Layout -->
			<?php if ($layout === 'table') : ?>
				<div class="rv-comparison-table-wrapper">
					<table class="rv-comparison-table">
						<thead>
							<tr>
								<th class="rv-feature-column"><?php echo esc_html__('Feature', 'mhm-rentiva'); ?></th>
								<?php foreach ($vehicles as $vehicle) : ?>
									<th class="rv-vehicle-column" data-vehicle-id="<?php echo esc_attr($vehicle['id']); ?>">
										<div class="rv-vehicle-header">
											<div class="rv-vehicle-image-container">
												<?php if (! empty($vehicle['image_url'])) : ?>
													<img src="<?php echo esc_url($vehicle['image_url']); ?>" alt="<?php echo esc_attr($vehicle['title']); ?>" class="rv-vehicle-image">
												<?php endif; ?>
											</div>
											<button type="button" class="rv-remove-vehicle" data-vehicle-id="<?php echo esc_attr($vehicle['id']); ?>" aria-label="<?php echo esc_attr__('Remove vehicle', 'mhm-rentiva'); ?>">
												<?php Icons::render('remove', ['class' => 'rv-icon-remove']); ?>
											</button>

											<div class="rv-vehicle-status-container">
												<?php
												$is_available = $vehicle['availability']['is_available'] ?? ($vehicle['meta']['available'] ?? true);
												$status_text  = $vehicle['availability']['text'] ?? __('Unavailable', 'mhm-rentiva');

												if (! $is_available) :
												?>
													<span class="rv-badge rv-badge--unavailable">
														<?php echo esc_html($status_text); ?>
													</span>
												<?php endif; ?>
											</div>

											<h4><?php echo esc_html($vehicle['title']); ?></h4>
											<?php
											$is_available = $vehicle['availability']['is_available'] ?? ($vehicle['meta']['available'] ?? true);
											$btn_style    = 'display: inline-block !important; color: white !important; padding: 8px 16px !important; border-radius: 6px !important; text-decoration: none !important; font-size: 13px !important; font-weight: 600 !important; margin-top: 5px !important; margin-bottom: 10px !important; text-align: center !important; width: 100% !important; max-width: 140px !important;';
											$btn_class    = 'rv-book-now-btn';
											$btn_href     = esc_url($vehicle['permalink']);
											$btn_attrs    = '';

											if (! $is_available) {
												$btn_style .= ' background: #95a5a6 !important; opacity: 0.6; pointer-events: none; cursor: not-allowed;';
												$btn_class .= ' rv-btn-disabled';
												$btn_href   = 'javascript:void(0);';
												$btn_attrs  = 'aria-disabled="true" tabindex="-1"';
											}
											?>
											<?php if ($show_booking_buttons) : ?>
												<a href="<?php echo esc_url($btn_href); ?>" class="<?php echo esc_attr($btn_class); ?>" style="<?php echo esc_attr($btn_style); ?>" <?php echo wp_kses_post($btn_attrs); ?>>
													<?php echo esc_html__('Make Reservation', 'mhm-rentiva'); ?>
												</a>
											<?php endif; ?>
											<!-- Price Display -->
											<?php
											$price = $vehicle['features']['price_per_day'] ?? 0;
											if ($show_prices && $price > 0) :
											?>
												<div class="mhm-comparison-price" style="text-align: center; font-weight: bold; font-size: 1.1em; color: #2ecc71; margin-top: 10px;">
													<?php echo esc_html(CurrencyHelper::format_price((float) $price, 0)); ?>
													<span class="price-suffix" style="font-size: 0.9em; color: #7f8c8d; font-weight: normal;">
														/ <?php echo esc_html__('day', 'mhm-rentiva'); ?>
													</span>
												</div>
											<?php endif; ?>
										</div>
									</th>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($features as $feature_key => $feature_label) : ?>
								<?php
								if ($feature_key === 'price_per_day') {
									continue;
								}
								?>
								<tr class="rv-feature-row">
									<td class="rv-feature-label"><?php echo esc_html($feature_label); ?></td>
									<?php foreach ($vehicles as $vehicle) : ?>
										<td class="rv-feature-value" data-vehicle-id="<?php echo esc_attr($vehicle['id']); ?>">
											<?php
											$value = $vehicle['features'][$feature_key] ?? 'â€“';
											// Defense-in-depth: ensure value is always a string
											if (is_array($value)) {
												$value = implode(', ', array_filter(array_map('strval', $value)));
											}
											echo '<span class="rv-feature-text">' . esc_html((string) $value) . '</span>';
											?>
										</td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<!-- Mobile List Layout -->
				<div class="rv-comparison-mobile-list">
					<?php foreach ($vehicles as $vehicle) : ?>
						<div class="rv-mobile-card-item" data-vehicle-id="<?php echo esc_attr($vehicle['id']); ?>">

							<div class="rv-mobile-card-header-wrapper">
								<div class="rv-mobile-card-image">
									<?php if (! empty($vehicle['image_url'])) : ?>
										<img src="<?php echo esc_url($vehicle['image_url']); ?>" alt="<?php echo esc_attr($vehicle['title']); ?>">
									<?php endif; ?>
								</div>

								<div class="rv-mobile-card-info">
									<h4 class="rv-mobile-title"><?php echo esc_html($vehicle['title']); ?></h4>

									<?php
									$is_available = $vehicle['availability']['is_available'] ?? ($vehicle['meta']['available'] ?? true);
									$status_text  = $vehicle['availability']['text'] ?? __('Unavailable', 'mhm-rentiva');
									if (! $is_available) :
									?>
										<span class="rv-badge rv-badge--unavailable" style="margin-bottom: 5px;">
											<?php echo esc_html($status_text); ?>
										</span>
									<?php endif; ?>

									<?php
									$price = $vehicle['features']['price_per_day'] ?? 0;
									if ($show_prices && $price > 0) :
									?>
										<div class="rv-mobile-price">
											<?php echo esc_html(CurrencyHelper::format_price((float) $price, 0)); ?>
											<span class="rv-period">/ <?php echo esc_html__('day', 'mhm-rentiva'); ?></span>
										</div>
									<?php endif; ?>
								</div>

								<button type="button" class="rv-remove-vehicle" data-vehicle-id="<?php echo esc_attr($vehicle['id']); ?>" aria-label="<?php echo esc_attr__('Remove vehicle', 'mhm-rentiva'); ?>">
									<svg class="rv-icon-remove" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 16px; height: 16px;">
										<line x1="18" y1="6" x2="6" y2="18"></line>
										<line x1="6" y1="6" x2="18" y2="18"></line>
									</svg>
								</button>
							</div>

							<button type="button" class="rv-mobile-accordion-toggle" onclick="this.parentElement.classList.toggle('active');">
								<span><?php echo esc_html__('Show Features', 'mhm-rentiva'); ?></span>
								<?php Icons::render('chevron-down', ['class' => 'rv-icon-chevron-down', 'style' => 'transition: transform 0.3s ease;']); ?>
							</button>

							<div class="rv-mobile-accordion-content">
								<div class="rv-mobile-features-list">
									<?php foreach ($features as $feature_key => $feature_label) : ?>
										<?php
										if ($feature_key === 'price_per_day') {
											continue;
										}
										?>
										<div class="rv-mobile-feature-row">
											<span class="rv-mobile-label"><?php echo esc_html($feature_label); ?></span>
											<span class="rv-mobile-value">
												<?php
												$value = $vehicle['features'][$feature_key] ?? 'â€“';
												if (is_array($value)) {
													$value = implode(', ', array_filter(array_map('strval', $value)));
												}
												echo esc_html((string) $value);
												?>
											</span>
										</div>
									<?php endforeach; ?>
								</div>

								<div class="rv-mobile-actions">
									<?php
									$btn_class = 'rv-book-now-btn rv-mobile-btn';
									$btn_href  = esc_url($vehicle['permalink']);
									$btn_attrs = '';

									if (! $is_available) {
										$btn_class .= ' rv-btn-disabled';
										$btn_href   = 'javascript:void(0);';
										$btn_attrs  = 'aria-disabled="true"';
									}
									?>
									<?php if ($show_booking_buttons) : ?>
										<a href="<?php echo esc_url($btn_href); ?>" class="<?php echo esc_attr($btn_class); ?>" <?php echo wp_kses_post($btn_attrs); ?>>
											<?php echo esc_html__('Make Reservation', 'mhm-rentiva'); ?>
										</a>
									<?php endif; ?>
								</div>
							</div>

						</div>
					<?php endforeach; ?>
				</div>

			<?php else : ?>
				<!-- Card Layout -->
				<div class="rv-comparison-cards">
					<?php foreach ($vehicles as $vehicle) : ?>
						<div class="rv-vehicle-card" data-vehicle-id="<?php echo esc_attr($vehicle['id']); ?>">
							<div class="rv-card-header">
								<?php if (! empty($vehicle['image_url'])) : ?>
									<img src="<?php echo esc_url($vehicle['image_url']); ?>" alt="<?php echo esc_attr($vehicle['title']); ?>" class="rv-card-image">
								<?php endif; ?>
								<h4><?php echo esc_html($vehicle['title']); ?></h4>
								<button type="button" class="rv-remove-vehicle" data-vehicle-id="<?php echo esc_attr($vehicle['id']); ?>" aria-label="<?php echo esc_attr__('Remove vehicle', 'mhm-rentiva'); ?>">
									<?php Icons::render('remove', ['class' => 'rv-icon-remove']); ?>
								</button>
								<?php
								$is_available = $vehicle['availability']['is_available'] ?? ($vehicle['meta']['available'] ?? true);
								$status_text  = $vehicle['availability']['text'] ?? __('Unavailable', 'mhm-rentiva');

								if (! $is_available) :
								?>
									<div style="width: 100%; text-align: center; margin-top: 5px;">
										<span class="rv-badge rv-badge--unavailable" style="display: inline-block;">
											<?php echo esc_html($status_text); ?>
										</span>
									</div>
								<?php endif; ?>
							</div>

							<div class="rv-card-features">
								<?php foreach ($features as $feature_key => $feature_label) : ?>
									<div class="rv-feature-item">
										<span class="rv-feature-label"><?php echo esc_html($feature_label); ?>:</span>
										<span class="rv-feature-value">
											<?php
											$value = $vehicle['features'][$feature_key] ?? 'â€“';
											if (is_array($value)) {
												$value = implode(', ', array_filter(array_map('strval', $value)));
											}

											if ($feature_key === 'price_per_day' && $show_prices) {
												if ($value > 0) {
													echo '<span class="rv-price">' . esc_html(CurrencyHelper::format_price((float) $value, 0)) . '</span>';
												} else {
													echo '<span class="rv-no-price">-</span>';
												}
											} else {
												echo '<span class="rv-feature-text">' . esc_html((string) $value) . '</span>';
											}
											?>
										</span>
									</div>
								<?php endforeach; ?>
							</div>

							<div class="rv-card-actions">
								<?php
								$is_available = $vehicle['availability']['is_available'] ?? ($vehicle['meta']['available'] ?? true);
								$btn_class    = 'rv-book-now-btn';
								$btn_href     = esc_url($vehicle['permalink']);
								$btn_attrs    = '';

								if (! $is_available) {
									$btn_class .= ' rv-btn-disabled';
									$btn_href   = 'javascript:void(0);';
									$btn_attrs  = 'aria-disabled="true" tabindex="-1" style="opacity: 0.6; pointer-events: none; cursor: not-allowed;"';
								}
								?>
								<?php if ($show_booking_buttons) : ?>
									<a href="<?php echo esc_url($btn_href); ?>" class="<?php echo esc_attr($btn_class); ?>" <?php echo wp_kses_post($btn_attrs); ?>>
										<?php echo esc_html__('Make Reservation', 'mhm-rentiva'); ?>
									</a>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

		</div>
	<?php else : ?>
		<!-- Threshold/Empty State Message -->
		<div class="rv-comparison-empty-state" style="text-align: center; padding: 60px 20px; background: #fff; border-radius: 12px; border: 2px dashed #e0e0e0; margin: 40px auto; max-width: 600px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
			<div class="rv-empty-icon" style="margin-bottom: 20px;">
				<?php Icons::render('table', ['class' => 'rv-icon-empty', 'width' => '64', 'height' => '64', 'style' => 'margin: 0 auto; display: block; stroke: #ddd; stroke-width: 1.5;']); ?>
			</div>
			<h4 style="margin-bottom: 12px; font-size: 1.4em; color: #333;"><?php echo esc_html__('Comparison list is ready!', 'mhm-rentiva'); ?></h4>
			<p style="color: #666; font-size: 1.1em; line-height: 1.6;"><?php echo esc_html__('Please add at least 2 vehicles to see the detailed comparison table.', 'mhm-rentiva'); ?></p>
			<?php
			$search_url = get_post_type_archive_link('vehicle') ?: home_url('/');
			?>
			<?php if ($show_booking_buttons) : ?>
				<a href="<?php echo esc_url($search_url); ?>" class="rv-book-now-btn" style="display: inline-block; margin-top: 25px; padding: 12px 30px; font-size: 1.1em; text-decoration: none; border-radius: 8px;">
					<?php echo esc_html__('Browse Vehicles', 'mhm-rentiva'); ?>
				</a>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="rv-messages">
		<div class="rv-success-message rv-hidden"></div>
		<div class="rv-error-message rv-hidden"></div>
	</div>

</div>
