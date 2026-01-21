<?php

/**
 * Booking Form Template
 * 
 * Advanced booking form - vehicle selection, additional services, deposit system
 * 
 * @updated 2025-01-16 - AbstractShortcode integration and URL parameters
 */

if (!defined('ABSPATH')) {
    exit;
}



// Get template variables
$atts = $atts ?? [];
$vehicles = $vehicles ?? [];
$selected_vehicle = $selected_vehicle ?? null;
$time_options = $time_options ?? [];
$guest_options = $guest_options ?? [];
$addons = $addons ?? [];

// ⭐ Logic moved to Controller (BookingForm::prepare_template_data)
// Template now only receives pre-processed data

// Error check (validation error from controller)
if (isset($validation_error) && !empty($validation_error)) {
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
$show_addons = $show_addons ?? true;
$show_payment_options = $show_payment_options ?? true;
$show_vehicle_info = $show_vehicle_info ?? true;
$enable_deposit = $enable_deposit ?? true;
$default_payment = $default_payment ?? 'deposit';
$class = $atts['class'] ?? '';
$redirect_url = $atts['redirect_url'] ?? '';
$form_title = $atts['form_title'] ?? esc_html__('Booking Form', 'mhm-rentiva');

// ⭐ Get user data from controller (pre-processed)
$user_data = $user_data ?? [];
$is_logged_in = $user_data['is_logged_in'] ?? false;
$user_name = $user_data['user_name'] ?? '';
$user_email = $user_data['user_email'] ?? '';
$user_phone = $user_data['user_phone'] ?? '';

// Get customer settings from controller
$customer_settings = $customer_settings ?? [];
$registration_required = $customer_settings['registration_required'] ?? '0';
$phone_required = $customer_settings['phone_required'] ?? '0';
$terms_required = $customer_settings['terms_required'] ?? '0';
$terms_text = $customer_settings['terms_text'] ?? __('I accept the terms of use and privacy policy.', 'mhm-rentiva');

// Generate unique ID for this form instance to prevent collisions
$unique_id = uniqid('rv_booking_');
?>

<div class="rv-booking-form-wrapper <?php echo esc_attr($class); ?>"
    data-redirect-url="<?php echo esc_attr($redirect_url); ?>"
    <?php if (!empty($selected_vehicle['id'])): ?>data-vehicle-id="<?php echo esc_attr($selected_vehicle['id']); ?>" <?php endif; ?>>

    <?php if ($form_title): ?>
        <div class="rv-form-header">
            <h2 class="rv-form-title"><?php echo esc_html($form_title); ?></h2>
        </div>
    <?php endif; ?>

    <div class="rv-booking-form">
        <form class="rv-booking-form-content" method="post" onsubmit="return false;">
            <?php if ($show_vehicle_selector && empty($selected_vehicle)): ?>
                <!-- Vehicle Selection -->
                <div class="rv-form-section rv-vehicle-selection">
                    <h3 class="rv-section-title"><?php echo esc_html__('Vehicle Selection', 'mhm-rentiva'); ?></h3>
                    <div class="rv-field-group">
                        <label for="vehicle_id-<?php echo esc_attr($unique_id); ?>" class="rv-label">
                            <?php echo esc_html__('Select Vehicle', 'mhm-rentiva'); ?> <span class="required">*</span>
                        </label>
                        <select name="vehicle_id" id="vehicle_id-<?php echo esc_attr($unique_id); ?>" class="rv-select rv-vehicle-select" required>
                            <option value=""><?php echo esc_html__('Select vehicle...', 'mhm-rentiva'); ?></option>
                            <?php foreach ($vehicles as $vehicle): ?>
                                <option value="<?php echo esc_attr($vehicle['id']); ?>"
                                    data-price="<?php echo esc_attr($vehicle['price_per_day']); ?>"
                                    data-image="<?php echo esc_attr($vehicle['featured_image']); ?>">
                                    <?php echo esc_html($vehicle['title']); ?>
                                    (<?php echo esc_html(number_format($vehicle['price_per_day'], 0, ',', '.')); ?>
                                    <?php echo esc_html(apply_filters('mhm_rentiva/currency_symbol', '$')); ?><?php echo esc_html__('/day', 'mhm-rentiva'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Selected Vehicle Preview -->
                    <div class="rv-selected-vehicle-preview rv-hidden">
                        <div class="rv-vehicle-info">
                            <img class="rv-vehicle-image" src="" alt="">
                            <div class="rv-vehicle-details">
                                <h4 class="rv-vehicle-title"></h4>
                                <p class="rv-vehicle-price"></p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php elseif ($selected_vehicle): ?>
                <!-- Specific Vehicle Selected -->
                <input type="hidden" name="vehicle_id" value="<?php echo esc_attr($selected_vehicle['id']); ?>">
                <?php if ($show_vehicle_info): ?>
                    <div class="rv-form-section rv-selected-vehicle">
                        <!-- Favorite Button - Top right corner -->
                        <button type="button"
                            class="rv-vehicle-card__favorite <?php echo ($selected_vehicle['favorite'] ?? false) ? 'favorited is-favorited' : ''; ?>"
                            data-vehicle-id="<?php echo esc_attr($selected_vehicle['id']); ?>"
                            aria-label="<?php echo ($selected_vehicle['favorite'] ?? false) ? esc_html__('Remove from favorites', 'mhm-rentiva') : esc_html__('Add to favorites', 'mhm-rentiva'); ?>"
                            aria-pressed="<?php echo ($selected_vehicle['favorite'] ?? false) ? 'true' : 'false'; ?>">
                            <svg class="rv-heart-icon <?php echo ($selected_vehicle['favorite'] ?? false) ? 'favorited' : ''; ?>" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
                            </svg>
                        </button>
                        <div class="rv-vehicle-info">
                            <?php if ($selected_vehicle['image_url']): ?>
                                <div class="rv-vehicle-image-wrapper">
                                    <img class="rv-vehicle-image" src="<?php echo esc_url($selected_vehicle['image_url']); ?>"
                                        alt="<?php echo esc_attr($selected_vehicle['title']); ?>">

                                    <!-- Rating Overlay -->
                                    <?php if (isset($selected_vehicle['rating']) && is_array($selected_vehicle['rating']) && $selected_vehicle['rating']['average'] > 0): ?>
                                        <div class="rv-vehicle-card__rating-overlay">
                                            <div class="rv-stars">
                                                <?php
                                                $rating_data = $selected_vehicle['rating'];
                                                $stars = intval($rating_data['stars']);
                                                $has_half_star = $rating_data['has_half_star'] ?? false;
                                                $empty_stars = intval($rating_data['empty_stars'] ?? 0);

                                                // Filled stars
                                                for ($i = 1; $i <= $stars; $i++): ?>
                                                    <svg class="rv-star rv-star-filled" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2">
                                                        <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26" />
                                                    </svg>
                                                <?php endfor; ?>

                                                <?php if ($has_half_star): ?>
                                                    <svg class="rv-star rv-star-half" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26" />
                                                    </svg>
                                                <?php endif; ?>

                                                <?php for ($i = 1; $i <= $empty_stars; $i++): ?>
                                                    <svg class="rv-star" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26" />
                                                    </svg>
                                                <?php endfor; ?>
                                            </div>
                                            <span class="rv-rating-count">(<?php echo intval($rating_data['count']); ?>)</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="rv-vehicle-details">
                                <h4 class="rv-vehicle-title"><?php echo esc_html($selected_vehicle['title']); ?></h4>
                                <?php if ($selected_vehicle['excerpt']): ?>
                                    <p class="rv-vehicle-excerpt"><?php echo esc_html($selected_vehicle['excerpt']); ?></p>
                                <?php endif; ?>
                                <!-- All Features and Meta Information -->
                                <div class="rv-vehicle-features">
                                    <?php if (!empty($selected_vehicle['features'])): ?>
                                        <?php foreach ($selected_vehicle['features'] as $feature): ?>
                                            <div class="rv-feature-tag rv-feature-item">
                                                <?php if ($feature['icon'] === 'fuel'): ?>
                                                    <svg class="rv-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M3 2h3l2 6h3l2-6h3l2 6h3l1 6H4l1-6z" />
                                                        <path d="M6 8h12" />
                                                        <path d="M6 12h12" />
                                                    </svg>
                                                <?php elseif ($feature['icon'] === 'gear'): ?>
                                                    <svg class="rv-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <circle cx="12" cy="12" r="3" />
                                                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1 1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z" />
                                                    </svg>
                                                <?php elseif ($feature['icon'] === 'people'): ?>
                                                    <svg class="rv-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                                        <circle cx="9" cy="7" r="4" />
                                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                                                        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                                                    </svg>
                                                <?php elseif ($feature['icon'] === 'calendar'): ?>
                                                    <svg class="rv-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                                                        <line x1="16" y1="2" x2="16" y2="6" />
                                                        <line x1="8" y1="2" x2="8" y2="6" />
                                                        <line x1="3" y1="10" x2="21" y2="10" />
                                                    </svg>
                                                <?php elseif ($feature['icon'] === 'speedometer'): ?>
                                                    <svg class="rv-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <circle cx="12" cy="12" r="10" />
                                                        <path d="M12 6v6l4 2" />
                                                    </svg>
                                                <?php else: ?>
                                                    <svg class="rv-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <polyline points="20 6 9 17 4 12" />
                                                    </svg>
                                                <?php endif; ?>
                                                <span class="rv-feature-text"><?php echo esc_html($feature['text']); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <p class="rv-vehicle-price">
                                    <?php echo esc_html(number_format($selected_vehicle['price_per_day'], 0, ',', '.')); ?>
                                    <?php echo esc_html($selected_vehicle['currency_symbol']); ?><?php echo esc_html(esc_html__('/day', 'mhm-rentiva')); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Date and Time Selection -->
            <div class="rv-form-section rv-dates-times">
                <h3 class="rv-section-title"><?php echo esc_html__('Date and Time', 'mhm-rentiva'); ?></h3>

                <div class="rv-field-group rv-field-dates">
                    <div class="rv-field">
                        <label for="pickup_date-<?php echo esc_attr($unique_id); ?>" class="rv-label">
                            <?php echo esc_html__('Pickup Date', 'mhm-rentiva'); ?> <span class="required">*</span>
                        </label>
                        <input type="text"
                            id="pickup_date-<?php echo esc_attr($unique_id); ?>"
                            name="pickup_date"
                            class="rv-input rv-date-input rv-pickup-date"
                            placeholder="<?php echo esc_attr__('Select pickup date', 'mhm-rentiva'); ?>"
                            value="<?php echo esc_attr($atts['start_date'] ?? ''); ?>"
                            readonly
                            required>
                    </div>

                    <div class="rv-field">
                        <label for="dropoff_date-<?php echo esc_attr($unique_id); ?>" class="rv-label">
                            <?php echo esc_html__('Return Date', 'mhm-rentiva'); ?> <span class="required">*</span>
                        </label>
                        <input type="text"
                            id="dropoff_date-<?php echo esc_attr($unique_id); ?>"
                            name="dropoff_date"
                            class="rv-input rv-date-input rv-dropoff-date"
                            placeholder="<?php echo esc_attr__('Select return date', 'mhm-rentiva'); ?>"
                            value="<?php echo esc_attr($atts['end_date'] ?? ''); ?>"
                            readonly
                            required>
                    </div>
                </div>

                <div class="rv-field-group rv-field-times">
                    <div class="rv-field">
                        <label for="pickup_time-<?php echo esc_attr($unique_id); ?>" class="rv-label">
                            <?php echo esc_html__('Pickup Time', 'mhm-rentiva'); ?> <span class="required">*</span>
                        </label>
                        <select id="pickup_time-<?php echo esc_attr($unique_id); ?>" name="pickup_time" class="rv-select rv-pickup-time" required>
                            <option value=""><?php echo esc_html__('Select time', 'mhm-rentiva'); ?></option>
                            <?php foreach ($time_options as $option): ?>
                                <option value="<?php echo esc_attr($option['value']); ?>">
                                    <?php echo esc_html($option['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="rv-field">
                        <label for="dropoff_time-<?php echo esc_attr($unique_id); ?>" class="rv-label">
                            <?php echo esc_html__('Return Time', 'mhm-rentiva'); ?>
                        </label>
                        <select id="dropoff_time-<?php echo esc_attr($unique_id); ?>" name="dropoff_time" class="rv-select rv-select-disabled rv-dropoff-time" disabled readonly>
                            <option value=""><?php echo esc_html__('Select pickup time first', 'mhm-rentiva'); ?></option>
                            <?php foreach ($time_options as $option): ?>
                                <option value="<?php echo esc_attr($option['value']); ?>">
                                    <?php echo esc_html($option['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" id="dropoff_time_hidden-<?php echo esc_attr($unique_id); ?>" name="dropoff_time" class="rv-dropoff-time-hidden" value="">
                        <small class="rv-description rv-description-hint">
                            <?php echo esc_html__('Return time is automatically set to match pickup time.', 'mhm-rentiva'); ?>
                        </small>
                    </div>
                </div>

                <!-- Availability Status -->
                <div id="availability-status" class="rv-availability-status hidden">
                    <div class="status-message"></div>
                </div>

            </div>

            <?php if ($show_addons && !empty($addons)): ?>
                <!-- Additional Services -->
                <div class="rv-form-section rv-addons">
                    <h3 class="rv-section-title"><?php echo esc_html__('Additional Services', 'mhm-rentiva'); ?></h3>
                    <div class="rv-addons-list">
                        <?php foreach ($addons as $addon): ?>
                            <label class="rv-addon-item">
                                <input type="checkbox"
                                    name="selected_addons[]"
                                    value="<?php echo esc_attr($addon['id']); ?>"
                                    class="rv-addon-checkbox"
                                    data-price="<?php echo esc_attr($addon['price']); ?>">
                                <div class="rv-addon-content">
                                    <div class="rv-addon-header">
                                        <span class="rv-addon-title"><?php echo esc_html($addon['title']); ?></span>
                                        <span class="rv-addon-price">
                                            <?php echo esc_html(\MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::format_currency_price($addon['price'])); ?>
                                        </span>
                                    </div>
                                    <?php if ($addon['description']): ?>
                                        <p class="rv-addon-description"><?php echo esc_html($addon['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Price Calculation -->
            <div class="rv-form-section rv-price-calculation">
                <h3 class="rv-section-title"><?php echo esc_html__('Price Calculation', 'mhm-rentiva'); ?></h3>

                <div class="rv-price-breakdown">
                    <div class="rv-price-item">
                        <span class="rv-price-label"><?php echo esc_html__('Daily Price:', 'mhm-rentiva'); ?></span>
                        <span class="rv-price-value rv-daily-price" id="rv-daily-price-<?php echo esc_attr($unique_id); ?>">-</span>
                    </div>
                    <div class="rv-price-item">
                        <span class="rv-price-label"><?php echo esc_html__('Number of Days:', 'mhm-rentiva'); ?></span>
                        <span class="rv-price-value rv-days-count" id="rv-days-count-<?php echo esc_attr($unique_id); ?>">-</span>
                    </div>
                    <div class="rv-price-item rv-tax-summary" style="display: none;">
                        <span class="rv-price-label rv-tax-label" id="rv-tax-label-<?php echo esc_attr($unique_id); ?>"><?php echo esc_html__('Tax:', 'mhm-rentiva'); ?></span>
                        <span class="rv-price-value rv-tax-amount" id="rv-tax-amount-<?php echo esc_attr($unique_id); ?>">-</span>
                    </div>
                    <div class="rv-price-item rv-vehicle-total-detailed" style="display: none;">
                        <span class="rv-price-label"><?php echo esc_html__('Vehicle Total:', 'mhm-rentiva'); ?></span>
                        <span class="rv-price-value rv-vehicle-total" id="rv-vehicle-total-<?php echo esc_attr($unique_id); ?>">-</span>
                    </div>
                    <div class="rv-price-item rv-addons-price rv-hidden">
                        <span class="rv-price-label"><?php echo esc_html__('Additional Services:', 'mhm-rentiva'); ?></span>
                        <span class="rv-price-value rv-addons-total" id="rv-addons-total-<?php echo esc_attr($unique_id); ?>">-</span>
                    </div>
                    <div class="rv-price-item rv-total-price">
                        <span class="rv-price-label"><?php echo esc_html__('Total Amount:', 'mhm-rentiva'); ?></span>
                        <span class="rv-price-value rv-total-amount" id="rv-total-amount-<?php echo esc_attr($unique_id); ?>">-</span>
                    </div>

                    <?php if ($enable_deposit): ?>
                        <div class="rv-price-item rv-deposit-summary" style="display: none;">
                            <span class="rv-price-label"><?php echo esc_html__('Deposit to Pay:', 'mhm-rentiva'); ?></span>
                            <span class="rv-price-value rv-deposit-amount" id="rv-deposit-amount-<?php echo esc_attr($unique_id); ?>">-</span>
                        </div>
                        <div class="rv-price-item rv-remaining-summary" style="display: none;">
                            <span class="rv-price-label"><?php echo esc_html__('Remaining Amount:', 'mhm-rentiva'); ?></span>
                            <span class="rv-price-value rv-remaining-amount" id="rv-remaining-amount-<?php echo esc_attr($unique_id); ?>">-</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Hidden fields for logged-in users (Payment gateway will handle guest users) -->
                <?php if ($is_logged_in):
                    // Calculate names if not provided directly
                    $first_name = $user_data['first_name'] ?: '';
                    $last_name = $user_data['last_name'] ?: '';

                    if (empty($first_name) && !empty($user_name)) {
                        $parts = explode(' ', $user_name);
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

                <div class="rv-form-actions">
                    <button type="button" class="rv-submit-btn rv-btn rv-btn-primary">
                        <span class="rv-btn-text"><?php
                                                    $make_booking_text = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_text_make_booking', '');
                                                    $make_booking_text = !empty($make_booking_text) ? $make_booking_text : __('Make Booking', 'mhm-rentiva');
                                                    echo esc_html($make_booking_text);
                                                    ?></span>
                        <span class="rv-btn-loading rv-hidden">
                            <span class="rv-spinner"></span>
                            <?php echo esc_html(\MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_text_processing', __('Processing...', 'mhm-rentiva'))); ?>
                        </span>
                    </button>
                </div>
            </div>
        </form>

        <!-- Messages -->
        <div class="rv-messages">
            <div class="rv-success-message rv-hidden"></div>
            <div class="rv-error-message rv-hidden"></div>
        </div>
    </div>
</div>

<?php
// ⭐ Inline JavaScript removed - All JS is now in assets/js/frontend/booking-form.js
// Payment status handling is done via JavaScript in the external file
// Data is passed via wp_localize_script in BookingForm::enqueue_assets()
?>