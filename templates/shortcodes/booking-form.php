<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic HTML is rendered by internal template layer with localized escaping.

/**
 * Booking Form Template
 *
 * Advanced booking form - vehicle selection, additional services, deposit system
 *
 * @updated 2025-01-16 - AbstractShortcode integration and URL parameters
 */

if (! defined('ABSPATH')) {
	exit;
}


// Get template variables
$atts             = $atts ?? array();
$vehicles         = $vehicles ?? array();
$selected_vehicle = $selected_vehicle ?? null;
$time_options     = $time_options ?? array();
$guest_options    = $guest_options ?? array();
$addons           = $addons ?? array();

// â­ Logic moved to Controller (BookingForm::prepare_template_data)
// Template now only receives pre-processed data

// Error check (validation error from controller)
if (isset($validation_error) && ! empty($validation_error)) {
	echo '<div class="rv-error">' . esc_html($validation_error) . '</div>';
	return;
}

// Error check (general error)
if (isset($error)) {
	echo '<div class="rv-error">' . esc_html($error) . '</div>';
	return;
}

// Get shortcode properties
$show_vehicle_selector = $show_vehicle_selector ?? true;
$show_addons           = $show_addons ?? true;
$show_payment_options  = $show_payment_options ?? true;
$show_vehicle_info     = $show_vehicle_info ?? true;
$show_time_select      = $show_time_select ?? true;
$enable_deposit        = $enable_deposit ?? true;
$default_payment       = $default_payment ?? 'deposit';
$class                 = $atts['class'] ?? '';
$redirect_url          = $atts['redirect_url'] ?? '';

// Location pre-fill variables
$pickup_location_id   = (int) ( $pickup_location_id ?? 0 );
$pickup_location_name = (string) ( $pickup_location_name ?? '' );
$prefill_pickup_time  = (string) ( $prefill_pickup_time ?? '' );

// â­ Get user data from controller (pre-processed)
$user_data    = $user_data ?? array();
$is_logged_in = $user_data['is_logged_in'] ?? false;
$user_name    = $user_data['user_name'] ?? '';
$user_email   = $user_data['user_email'] ?? '';
$user_phone   = $user_data['user_phone'] ?? '';

// Get customer settings from controller
$customer_settings       = $customer_settings ?? array();
$registration_required   = $customer_settings['registration_required'] ?? '0';
$phone_required          = $customer_settings['phone_required'] ?? '0';
$terms_required          = $customer_settings['terms_required'] ?? '0';
$terms_text              = $customer_settings['terms_text'] ?? __('I accept the terms of use and privacy policy.', 'mhm-rentiva');
$has_preselected_vehicle = ! empty($selected_vehicle['id']);

// Generate unique ID for this form instance to prevent collisions
$unique_id = uniqid('rv_booking_');
?>

<div class="rv-booking-form-wrapper mhm-rentiva-booking-form alignwide <?php echo esc_attr($class); ?>"
	data-testid="booking-form-wrapper"
	data-redirect-url="<?php echo esc_attr($redirect_url); ?>"
	<?php
	if (! empty($selected_vehicle['id'])) :
		?>
	data-vehicle-id="<?php echo esc_attr($selected_vehicle['id']); ?>" <?php endif; ?>>

	<div class="rv-booking-form">
		<form class="rv-booking-form-content rv-checkout-layout" id="rv-booking-form-<?php echo esc_attr($unique_id); ?>" method="post" onsubmit="return false;">
			<?php if ($pickup_location_id > 0) : ?>
				<input type="hidden" name="pickup_location_id" value="<?php echo esc_attr( (string) $pickup_location_id); ?>">
			<?php endif; ?>

				<!-- Left Column: Vehicle Summary -->
				<aside class="rv-checkout-sidebar rv-checkout-vehicle <?php echo esc_attr($has_preselected_vehicle ? '' : 'rv-hidden'); ?>" id="rv-checkout-vehicle-wrapper-<?php echo esc_attr($unique_id); ?>">
				<?php if ($selected_vehicle && $show_vehicle_info) : ?>
					<?php
					// Case A: Pre-rendered Vehicle Summary (Detail Page Context)
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo \MHMRentiva\Admin\Core\Utilities\Templates::render('partials/selected-vehicle-summary', array(
						'vehicle' => $selected_vehicle,
						'atts'    => $atts,
					), true);
					?>
				<?php else : ?>
					<!-- Case B: JS Dynamically populated Summary (Generic Shortcode Context) -->
						<div class="rv-selected-vehicle-preview rv-selected-vehicle rv-card rv-vehicle-summary rv-hidden">
							<div class="rv-sv__media">
								<img class="rv-sv__img rv-vehicle-image" src="" alt="">
								<button type="button" class="rv-sv__favorite rv-sv__favorite-placeholder" aria-label="<?php echo esc_attr__('Add to favorites', 'mhm-rentiva'); ?>">
									<span class="rv-heart-icon">&#10084;</span>
								</button>
								<div class="rv-sv__rating-inline rv-sv__rating-overlay rv-hidden">
									<span class="rv-sv__rating-star" aria-hidden="true">&#9733;</span>
									<span class="rv-sv__rating-value">4.8</span>
									<span class="rv-sv__rating-count"><?php echo esc_html__('(120 reviews)', 'mhm-rentiva'); ?></span>
								</div>
							</div>
							<div class="rv-sv__content">
								<div class="rv-sv__top">
									<div class="rv-sv__category rv-vehicle-category"></div>
								</div>
								<div class="rv-sv__title-wrap">
									<h4 class="rv-sv__title rv-vehicle-title"></h4>
								</div>
							<div class="rv-sv__meta">
								<span class="rv-sv__chip rv-chip">Automatic</span>
								<span class="rv-sv__chip rv-chip">Petrol</span>
								<span class="rv-sv__chip rv-chip">4 Seats</span>
							</div>
						</div>
					</div>
				<?php endif; ?>
			</aside>

				<!-- Left Column: Main Checkout Steps -->
				<div class="rv-form-main-content rv-checkout-main">
				<?php if ($show_vehicle_selector && empty($selected_vehicle)) : ?>
					<!-- Vehicle Selection -->
					<div class="rv-form-section rv-vehicle-selection">
						<h3 class="rv-section-title"><?php echo esc_html__('Vehicle Selection', 'mhm-rentiva'); ?></h3>
						<div class="rv-field-group">
							<label for="vehicle_id-<?php echo esc_attr($unique_id); ?>" class="rv-label">
								<?php echo esc_html__('Select Vehicle', 'mhm-rentiva'); ?> <span class="required">*</span>
							</label>
							<select name="vehicle_id" id="vehicle_id-<?php echo esc_attr($unique_id); ?>" class="rv-select rv-vehicle-select" required>
								<option value=""><?php echo esc_html__('Select vehicle...', 'mhm-rentiva'); ?></option>
								<?php foreach ($vehicles as $vehicle) : ?>
									<option value="<?php echo esc_attr($vehicle['id']); ?>"
										data-price="<?php echo esc_attr($vehicle['price_per_day']); ?>"
										data-category="<?php echo esc_attr($vehicle['category_name'] ?? $vehicle['category'] ?? ''); ?>"
										data-image="<?php echo esc_attr($vehicle['featured_image']); ?>"
										data-features="<?php echo esc_attr(wp_json_encode($vehicle['features'] ?? [])); ?>">
										<?php echo esc_html($vehicle['title']); ?>
										(<?php echo esc_html(\MHMRentiva\Admin\Core\CurrencyHelper::format_price( (float) $vehicle['price_per_day'], 0)); ?><?php echo esc_html__('/day', 'mhm-rentiva'); ?>)
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<!-- Selected Vehicle Preview Moved to Sidebar -->
					</div>
				<?php elseif ($selected_vehicle) : ?>
					<!-- Hidden ID Input (Summary has been moved to rv-checkout-vehicle sidebar) -->
					<input type="hidden" name="vehicle_id" value="<?php echo esc_attr($selected_vehicle['id']); ?>">
				<?php endif; ?>

				<!-- Date, Time and Location Section -->
				<div class="rv-form-section rv-dates-times rv-card">
					<div class="rv-card-header">
						<span class="rv-icon rv-icon-calendar"></span>
						<h3 class="rv-section-title"><?php echo esc_html__('Date & Time Selection', 'mhm-rentiva'); ?></h3>
					</div>
					<div class="rv-card-body">

						<div class="rv-bf-datetime-table">

							<!-- Pickup Row -->
							<div class="rv-bf-datetime-row rv-bf-datetime-row--pickup">
								<div class="rv-bf-datetime-rowhead">
									<span class="rv-bf-datetime-rowtitle"><?php esc_html_e('VEHICLE PICKUP', 'mhm-rentiva'); ?></span>
									<?php if ($pickup_location_id > 0 && $pickup_location_name !== '') : ?>
										<div class="rv-bf-datetime-loc">
											<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
											<span><?php echo esc_html($pickup_location_name); ?></span>
										</div>
									<?php endif; ?>
								</div>
								<div class="rv-bf-datetime-cell rv-bf-datetime-cell--date">
									<input type="text"
										id="pickup_date-<?php echo esc_attr($unique_id); ?>"
										name="pickup_date"
										data-testid="booking-pickup-date"
										class="rv-input-ghost rv-date-input rv-pickup-date"
										placeholder="<?php echo esc_attr__('Select date', 'mhm-rentiva'); ?>"
										value="<?php echo esc_attr($atts['start_date'] ?? ''); ?>"
										readonly
										required>
									<span class="rv-bf-datetime-cal-icon" aria-hidden="true">
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
									</span>
								</div>
								<div class="rv-bf-datetime-cell rv-bf-datetime-cell--time">
									<span class="rv-bf-datetime-clock-icon" aria-hidden="true">
										<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
									</span>
									<?php if ($show_time_select) : ?>
										<select id="pickup_time-<?php echo esc_attr($unique_id); ?>" name="pickup_time" class="rv-select-ghost rv-pickup-time rv-select" required>
											<option value=""><?php echo esc_html__('Time', 'mhm-rentiva'); ?></option>
											<?php foreach ($time_options as $option) : ?>
												<option value="<?php echo esc_attr($option['value']); ?>" <?php selected($option['value'], $prefill_pickup_time); ?>>
													<?php echo esc_html($option['label']); ?>
												</option>
											<?php endforeach; ?>
										</select>
									<?php else : ?>
										<input type="hidden" name="pickup_time" value="12:00">
									<?php endif; ?>
								</div>
							</div>

							<!-- Return Row -->
							<div class="rv-bf-datetime-row rv-bf-datetime-row--return">
								<div class="rv-bf-datetime-rowhead">
									<span class="rv-bf-datetime-rowtitle">
										<?php esc_html_e('VEHICLE RETURN', 'mhm-rentiva'); ?>
										<span class="rv-info-icon-wrapper" title="<?php echo esc_attr__('Return time is automatically set to match pickup time.', 'mhm-rentiva'); ?>">
											<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
										</span>
									</span>
									<?php if ($pickup_location_id > 0 && $pickup_location_name !== '') : ?>
										<div class="rv-bf-datetime-loc rv-bf-datetime-loc--same">
											<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
											<span><?php esc_html_e('Same Pickup Location', 'mhm-rentiva'); ?></span>
										</div>
									<?php endif; ?>
								</div>
								<div class="rv-bf-datetime-cell rv-bf-datetime-cell--date">
									<input type="text"
										id="dropoff_date-<?php echo esc_attr($unique_id); ?>"
										name="dropoff_date"
										class="rv-input-ghost rv-date-input rv-dropoff-date"
										placeholder="<?php echo esc_attr__('Select date', 'mhm-rentiva'); ?>"
										value="<?php echo esc_attr($atts['end_date'] ?? ''); ?>"
										readonly
										required>
									<span class="rv-bf-datetime-cal-icon" aria-hidden="true">
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
									</span>
								</div>
								<div class="rv-bf-datetime-cell rv-bf-datetime-cell--time">
									<span class="rv-bf-datetime-clock-icon" aria-hidden="true">
										<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
									</span>
									<?php if ($show_time_select) : ?>
										<select id="dropoff_time-<?php echo esc_attr($unique_id); ?>" name="dropoff_time" class="rv-select-ghost rv-select-disabled rv-dropoff-time rv-select" disabled readonly>
											<option value=""><?php echo esc_html__('Time', 'mhm-rentiva'); ?></option>
											<?php foreach ($time_options as $option) : ?>
												<option value="<?php echo esc_attr($option['value']); ?>">
													<?php echo esc_html($option['label']); ?>
												</option>
											<?php endforeach; ?>
										</select>
									<?php else : ?>
										<input type="hidden" name="dropoff_time" value="12:00">
									<?php endif; ?>
								</div>
							</div>

						</div><!-- /.rv-bf-datetime-table -->

						<?php if ($show_time_select) : ?>
						<input type="hidden" id="dropoff_time_hidden-<?php echo esc_attr($unique_id); ?>" name="dropoff_time" class="rv-dropoff-time-hidden" value="">
						<?php endif; ?>
						<small class="rv-description-hint-premium">
							<?php echo esc_html__('Return time is automatically set to match pickup time.', 'mhm-rentiva'); ?>
						</small>

						<!-- Availability Status -->
						<div id="availability-status" class="rv-availability-status hidden">
							<div class="status-message"></div>
						</div>

					</div> <!-- rv-card-body -->
				</div>

				<?php if ($show_addons && ! empty($addons)) : ?>
					<!-- Additional Services -->
					<div class="rv-form-section rv-addons rv-card">
						<div class="rv-card-header">
							<span class="rv-icon rv-icon-addons"></span>
							<h3 class="rv-section-title"><?php echo esc_html__('Additional Services', 'mhm-rentiva'); ?></h3>
						</div>
						<div class="rv-card-body rv-addons-list">
							<?php foreach ($addons as $addon) : ?>
								<label class="rv-addon-item">
									<div class="rv-addon-icon-circle">
										<svg class="rv-addon-generic-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
											<path d="M12 3L4 7v6c0 5 3.4 8.7 8 10 4.6-1.3 8-5 8-10V7l-8-4z"></path>
											<path d="M9.5 12.5l1.8 1.8 3.7-3.7"></path>
										</svg>
									</div>
									<div class="rv-addon-content">
										<div class="rv-addon-title"><?php echo esc_html($addon['title']); ?></div>
										<?php if ($addon['description']) : ?>
											<div class="rv-addon-description"><?php echo esc_html($addon['description']); ?></div>
										<?php endif; ?>
									</div>
									<div class="rv-addon-actions">
										<div class="rv-addon-price">
											<?php echo esc_html(\MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::format_currency_price($addon['price'])); ?>
										</div>
										<input type="checkbox"
											name="selected_addons[]"
											value="<?php echo esc_attr($addon['id']); ?>"
											class="rv-addon-checkbox"
											data-price="<?php echo esc_attr($addon['price']); ?>">
									</div>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

				</div> <!-- .rv-checkout-main -->

				<!-- Right Column: Sticky Payment Summary -->
				<aside class="rv-checkout-summary">
					<!-- Price Calculation (Payment Summary Card) -->
					<div class="rv-form-section rv-price-calculation rv-card" data-testid="booking-price-summary">
					<div class="rv-card-header">
						<span class="rv-icon rv-icon-receipt"></span>
						<h3 class="rv-section-title"><?php echo esc_html__('Payment Summary', 'mhm-rentiva'); ?></h3>
					</div>

					<!-- Card Body for line items -->
					<div class="rv-card-body rv-price-breakdown">
						<div class="rv-price-item">
							<span class="rv-price-label"><?php echo esc_html__('Daily Price:', 'mhm-rentiva'); ?></span>
							<span class="rv-price-value rv-daily-price" id="rv-daily-price-<?php echo esc_attr($unique_id); ?>">-</span>
						</div>
						<div class="rv-price-item">
							<span class="rv-price-label"><?php echo esc_html__('Number of Days:', 'mhm-rentiva'); ?></span>
							<span class="rv-price-value rv-days-count" id="rv-days-count-<?php echo esc_attr($unique_id); ?>">-</span>
						</div>
						<div class="rv-price-item rv-weekend-summary rv-hidden">
							<span class="rv-price-label"><?php echo esc_html__('Weekend Difference:', 'mhm-rentiva'); ?></span>
							<span class="rv-price-value rv-weekend-diff-amount" id="rv-weekend-diff-amount-<?php echo esc_attr($unique_id); ?>">-</span>
						</div>
						<div class="rv-price-item rv-tax-summary rv-hidden">
							<span class="rv-price-label rv-tax-label" id="rv-tax-label-<?php echo esc_attr($unique_id); ?>"><?php echo esc_html__('Tax:', 'mhm-rentiva'); ?></span>
							<span class="rv-price-value rv-tax-amount" id="rv-tax-amount-<?php echo esc_attr($unique_id); ?>">-</span>
						</div>
						<div class="rv-price-item rv-vehicle-total-detailed rv-hidden">
							<span class="rv-price-label"><?php echo esc_html__('Vehicle Total:', 'mhm-rentiva'); ?></span>
							<span class="rv-price-value rv-vehicle-total" id="rv-vehicle-total-<?php echo esc_attr($unique_id); ?>">-</span>
						</div>
						<div class="rv-price-item rv-addons-price rv-hidden">
							<span class="rv-price-label"><?php echo esc_html__('Additional Services:', 'mhm-rentiva'); ?></span>
							<span class="rv-price-value rv-addons-total" id="rv-addons-total-<?php echo esc_attr($unique_id); ?>">-</span>
						</div>
						<div class="rv-price-item rv-total-price">
							<span class="rv-price-label"><?php echo esc_html__('Total Amount:', 'mhm-rentiva'); ?></span>
							<span class="rv-price-value rv-total-amount rv-price-large" id="rv-total-amount-<?php echo esc_attr($unique_id); ?>" data-testid="booking-total-price">-</span>
						</div>

						<?php if ($enable_deposit) : ?>
							<div class="rv-price-item rv-deposit-summary rv-hidden">
								<span class="rv-price-label"><?php echo esc_html__('Deposit to Pay:', 'mhm-rentiva'); ?></span>
								<span class="rv-price-value rv-deposit-amount" id="rv-deposit-amount-<?php echo esc_attr($unique_id); ?>" data-testid="booking-deposit-amount">-</span>
							</div>
							<div class="rv-price-item rv-remaining-summary rv-hidden">
								<span class="rv-price-label"><?php echo esc_html__('Remaining Amount:', 'mhm-rentiva'); ?></span>
								<span class="rv-price-value rv-remaining-amount" id="rv-remaining-amount-<?php echo esc_attr($unique_id); ?>">-</span>
							</div>
						<?php endif; ?>
					</div>

					<!-- Hidden fields for logged-in users (Payment gateway will handle guest users) -->
					<?php
					if ($is_logged_in) :
						// Calculate names if not provided directly
						$first_name = $user_data['first_name'] ?: '';
						$last_name  = $user_data['last_name'] ?: '';

						if (empty($first_name) && ! empty($user_name)) {
							$parts      = explode(' ', $user_name);
							$first_name = $parts[0];
							if (count($parts) > 1) {
								$last_name = implode(' ', array_slice($parts, 1));
							}
						}
						?>
						<input type="hidden" id="customer_first_name" name="customer_first_name" class="rv-customer-first-name" value="<?php echo esc_attr($first_name); ?>">
						<input type="hidden" id="customer_last_name" name="customer_last_name" class="rv-customer-last-name" value="<?php echo esc_attr($last_name); ?>">
						<input type="hidden" id="customer_email" name="customer_email" class="rv-customer-email" value="<?php echo esc_attr($user_email); ?>">
						<input type="hidden" id="customer_phone" name="customer_phone" class="rv-customer-phone" value="<?php echo esc_attr($user_phone); ?>">
					<?php endif; ?>

					<?php
					// Payment type selection moved to WooCommerce checkout page
					// Default to deposit payment
					?>
					<input type="hidden" name="payment_type" value="deposit">
					<input type="hidden" name="redirect_url" value="<?php echo esc_attr($redirect_url); ?>">

					<!-- Form Buttons -->

					<div class="rv-card-footer rv-form-actions">
						<button type="button" class="rv-submit-btn rv-btn rv-btn-primary rv-cta-primary" data-testid="booking-submit-btn">
							<span class="rv-btn-text">
								<?php
								$make_booking_text = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_text_make_booking', '');
								$make_booking_text = ! empty($make_booking_text) ? $make_booking_text : __('Make Booking', 'mhm-rentiva');
								echo esc_html($make_booking_text);
								?>
							</span>
							<span class="rv-btn-loading rv-hidden">
								<span class="rv-spinner"></span>
								<?php echo esc_html(\MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_text_processing', __('Processing...', 'mhm-rentiva'))); ?>
							</span>
						</button>
						<p class="rv-payment-disclaimer">
							<?php echo esc_html__('By clicking the booking button, you accept booking terms and pricing details.', 'mhm-rentiva'); ?>
						</p>
					</div>
					</div>
				</aside> <!-- .rv-checkout-summary -->
			</form>

		<!-- Messages -->
		<div class="rv-messages">
			<div class="rv-success-message rv-hidden"></div>
			<div class="rv-error-message rv-hidden"></div>
		</div>
	</div>
</div>

<?php
// â­ Inline JavaScript removed - All JS is now in assets/js/frontend/booking-form.js
// Payment status handling is done via JavaScript in the external file
// Data is passed via wp_localize_script in BookingForm::enqueue_assets()
?>
