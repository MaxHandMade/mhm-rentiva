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

// Load plugin textdomain
if (!function_exists('mhm_rentiva_load_textdomain')) {
    function mhm_rentiva_load_textdomain() {
        load_plugin_textdomain('mhm-rentiva', false, dirname(plugin_basename(__FILE__)) . '/../../languages/');
    }
    mhm_rentiva_load_textdomain();
}

// Get template variables
$atts = $atts ?? [];
$vehicles = $vehicles ?? [];
$selected_vehicle = $selected_vehicle ?? null;
$time_options = $time_options ?? [];
$guest_options = $guest_options ?? [];
$addons = $addons ?? [];

// Get customer management settings
use MHMRentiva\Admin\Settings\Core\SettingsCore;

$registration_required = SettingsCore::get('mhm_rentiva_customer_registration_required', '0');
$phone_required = SettingsCore::get('mhm_rentiva_customer_phone_required', '0');
$terms_required = SettingsCore::get('mhm_rentiva_customer_terms_required', '0');
$terms_text = SettingsCore::get('mhm_rentiva_customer_terms_text', 'I accept the terms of use and privacy policy.');
$data_consent_required = SettingsCore::get('mhm_rentiva_customer_data_consent', '0');

// Check data consent requirement for logged-in users
if (is_user_logged_in() && $data_consent_required === '1') {
    $user_id = get_current_user_id();
    $consent_given = get_user_meta($user_id, 'mhm_data_consent_given', true);
    
    if ($consent_given !== '1') {
        echo '<div class="rv-error">' . esc_html__('You must provide consent for data processing before making a booking. Please update your account settings.', 'mhm-rentiva') . '</div>';
        return;
    }
}

// Error check
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

// ⭐ Get logged in user information
$current_user = wp_get_current_user();
$is_logged_in = is_user_logged_in();
$user_name = $is_logged_in ? $current_user->display_name : '';
$user_email = $is_logged_in ? $current_user->user_email : '';
$user_phone = $is_logged_in ? get_user_meta($current_user->ID, 'mhm_rentiva_phone', true) : '';
?>

<div class="rv-booking-form-wrapper <?php echo esc_attr($class); ?>" 
     data-redirect-url="<?php echo esc_attr($redirect_url); ?>"
     <?php if (!empty($selected_vehicle['id'])): ?>data-vehicle-id="<?php echo esc_attr($selected_vehicle['id']); ?>"<?php endif; ?>>

    <?php if ($form_title): ?>
        <div class="rv-form-header">
            <h2 class="rv-form-title"><?php echo esc_html($form_title); ?></h2>
        </div>
    <?php endif; ?>

    <div class="rv-booking-form">
        <form class="rv-booking-form-content" method="post" onsubmit="return false;">
        <div class="rv-booking-form-left">
            <?php if ($show_vehicle_selector && empty($selected_vehicle)): ?>
                <!-- Vehicle Selection -->
                <div class="rv-form-section rv-vehicle-selection">
                    <h3 class="rv-section-title"><?php echo esc_html__('Vehicle Selection', 'mhm-rentiva'); ?></h3>
                    <div class="rv-field-group">
                        <label for="vehicle_id" class="rv-label">
                            <?php echo esc_html__('Select Vehicle', 'mhm-rentiva'); ?> <span class="required">*</span>
                        </label>
                        <select name="vehicle_id" id="vehicle_id" class="rv-select" required>
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
                    <div class="rv-selected-vehicle-preview" style="display: none;">
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
                        <button class="rv-vehicle-card__favorite <?php echo ($selected_vehicle['favorite'] ?? false) ? 'favorited' : ''; ?>" 
                                data-vehicle-id="<?php echo esc_attr($selected_vehicle['id']); ?>" 
                                aria-label="<?php echo ($selected_vehicle['favorite'] ?? false) ? esc_html__('Remove from favorites', 'mhm-rentiva') : esc_html__('Add to favorites', 'mhm-rentiva'); ?>">
                            <svg class="rv-heart-icon <?php echo ($selected_vehicle['favorite'] ?? false) ? 'favorited' : ''; ?>" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
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
                                                        <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/>
                                                    </svg>
                                                <?php endfor; ?>
                                                
                                                <?php if ($has_half_star): ?>
                                                    <svg class="rv-star rv-star-half" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/>
                                                    </svg>
                                                <?php endif; ?>
                                                
                                                <?php for ($i = 1; $i <= $empty_stars; $i++): ?>
                                                    <svg class="rv-star" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/>
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
                                            <span class="rv-feature-tag"><?php echo esc_html($feature); ?></span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    
                                    <!-- Additional Meta Information -->
                                    <?php if (!empty($selected_vehicle['year'])): ?>
                                        <span class="rv-feature-tag">
                                            <?php echo esc_html($selected_vehicle['year']); ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($selected_vehicle['mileage'])): ?>
                                        <span class="rv-feature-tag">
                                            <?php echo esc_html($selected_vehicle['mileage']); ?> <?php echo esc_html(esc_html__('km', 'mhm-rentiva')); ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($selected_vehicle['seats'])): ?>
                                        <span class="rv-feature-tag">
                                            <?php echo esc_html($selected_vehicle['seats']); ?> <?php echo esc_html(esc_html__('people', 'mhm-rentiva')); ?>
                                        </span>
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
                        <label for="pickup_date" class="rv-label">
                            <?php echo esc_html__('Pickup Date', 'mhm-rentiva'); ?> <span class="required">*</span>
                        </label>
                        <input type="text" 
                               id="pickup_date" 
                               name="pickup_date" 
                               class="rv-input rv-date-input" 
                               placeholder="<?php echo esc_attr__('Select pickup date', 'mhm-rentiva'); ?>"
                               value="<?php echo esc_attr($atts['start_date'] ?? ''); ?>"
                               readonly
                               required>
                    </div>
                    
                    <div class="rv-field">
                        <label for="dropoff_date" class="rv-label">
                            <?php echo esc_html__('Return Date', 'mhm-rentiva'); ?> <span class="required">*</span>
                        </label>
                        <input type="text" 
                               id="dropoff_date" 
                               name="dropoff_date" 
                               class="rv-input rv-date-input" 
                               placeholder="<?php echo esc_attr__('Select return date', 'mhm-rentiva'); ?>"
                               value="<?php echo esc_attr($atts['end_date'] ?? ''); ?>"
                               readonly
                               required>
                    </div>
                </div>

                <div class="rv-field-group rv-field-times">
                    <div class="rv-field">
                        <label for="pickup_time" class="rv-label">
                            <?php echo esc_html__('Pickup Time', 'mhm-rentiva'); ?>
                        </label>
                        <select id="pickup_time" name="pickup_time" class="rv-select">
                            <option value=""><?php echo esc_html__('Select time', 'mhm-rentiva'); ?></option>
                            <?php foreach ($time_options as $option): ?>
                                <option value="<?php echo esc_attr($option['value']); ?>">
                                    <?php echo esc_html($option['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="rv-field">
                        <label for="dropoff_time" class="rv-label">
                            <?php echo esc_html__('Return Time', 'mhm-rentiva'); ?>
                        </label>
                        <select id="dropoff_time" name="dropoff_time" class="rv-select">
                            <option value=""><?php echo esc_html__('Select time', 'mhm-rentiva'); ?></option>
                            <?php foreach ($time_options as $option): ?>
                                <option value="<?php echo esc_attr($option['value']); ?>">
                                    <?php echo esc_html($option['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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
                        <span class="rv-price-value" id="rv-daily-price">-</span>
                    </div>
                    <div class="rv-price-item">
                        <span class="rv-price-label"><?php echo esc_html__('Number of Days:', 'mhm-rentiva'); ?></span>
                        <span class="rv-price-value" id="rv-days-count">-</span>
                    </div>
                    <div class="rv-price-item">
                        <span class="rv-price-label"><?php echo esc_html__('Vehicle Total:', 'mhm-rentiva'); ?></span>
                        <span class="rv-price-value" id="rv-vehicle-total">-</span>
                    </div>
                    <div class="rv-price-item rv-addons-price" style="display: none;">
                        <span class="rv-price-label"><?php echo esc_html__('Additional Services:', 'mhm-rentiva'); ?></span>
                        <span class="rv-price-value" id="rv-addons-total">-</span>
                    </div>
                    <div class="rv-price-item rv-total-price">
                        <span class="rv-price-label"><?php echo esc_html__('Total Amount:', 'mhm-rentiva'); ?></span>
                        <span class="rv-price-value" id="rv-total-amount">-</span>
                    </div>
                    
                    <?php if ($enable_deposit): ?>
                        <div class="rv-price-item rv-deposit-summary" style="display: none;">
                            <span class="rv-price-label"><?php echo esc_html__('Deposit to Pay:', 'mhm-rentiva'); ?></span>
                            <span class="rv-price-value" id="rv-deposit-amount">-</span>
                        </div>
                        <div class="rv-price-item rv-remaining-summary" style="display: none;">
                            <span class="rv-price-label"><?php echo esc_html__('Remaining Amount:', 'mhm-rentiva'); ?></span>
                            <span class="rv-price-value" id="rv-remaining-amount">-</span>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <div class="rv-booking-form-right">
            <!-- Contact Information -->
            <div class="rv-form-section rv-contact-info">
                <h3 class="rv-section-title"><?php echo esc_html__('Contact Information', 'mhm-rentiva'); ?></h3>
                
                <div class="rv-field-group">
                    <?php if ($is_logged_in): ?>
                        <!-- ⭐ Logged in user info - Name and email only -->
                        <div class="rv-logged-in-info">
                            <div class="rv-user-details">
                                <div class="rv-user-name">
                                    <strong><?php echo esc_html($user_name); ?></strong>
                                </div>
                                <div class="rv-user-email">
                                    <?php echo esc_html($user_email); ?>
                                </div>
                            </div>
                        </div>
                        <!-- Hidden fields -->
                        <input type="hidden" id="customer_first_name" name="customer_first_name" value="<?php echo esc_attr($current_user->first_name ?: explode(' ', $user_name)[0]); ?>">
                        <input type="hidden" id="customer_last_name" name="customer_last_name" value="<?php echo esc_attr($current_user->last_name ?: (count(explode(' ', $user_name)) > 1 ? implode(' ', array_slice(explode(' ', $user_name), 1)) : '')); ?>">
                        <input type="hidden" id="customer_email" name="customer_email" value="<?php echo esc_attr($user_email); ?>">
                        <input type="hidden" id="customer_phone" name="customer_phone" value="<?php echo esc_attr($user_phone); ?>">
                    <?php else: ?>
                        <!-- Guest user manual input -->
                        <div class="rv-field-group rv-name-fields">
                            <div class="rv-field">
                                <label for="customer_first_name" class="rv-label">
                                    <?php 
                                    $first_name_label = SettingsCore::get('mhm_rentiva_text_first_name', '');
                                    $first_name_label = !empty($first_name_label) ? $first_name_label : __('First Name', 'mhm-rentiva');
                                    echo esc_html($first_name_label); 
                                    ?> <span class="required">*</span>
                                </label>
                                <input type="text" 
                                       id="customer_first_name" 
                                       name="customer_first_name" 
                                       class="rv-input" 
                                       required>
                            </div>
                            
                            <div class="rv-field">
                                <label for="customer_last_name" class="rv-label">
                                    <?php 
                                    $last_name_label = SettingsCore::get('mhm_rentiva_text_last_name', '');
                                    $last_name_label = !empty($last_name_label) ? $last_name_label : __('Last Name', 'mhm-rentiva');
                                    echo esc_html($last_name_label); 
                                    ?> <span class="required">*</span>
                                </label>
                                <input type="text" 
                                       id="customer_last_name" 
                                       name="customer_last_name" 
                                       class="rv-input" 
                                       required>
                            </div>
                        </div>
                        
                        <div class="rv-field">
                            <label for="customer_email" class="rv-label">
                                <?php 
                                $email_label = SettingsCore::get('mhm_rentiva_text_email', '');
                                $email_label = !empty($email_label) ? $email_label : __('Email', 'mhm-rentiva');
                                echo esc_html($email_label); 
                                ?> <span class="required">*</span>
                            </label>
                            <input type="email" 
                                   id="customer_email" 
                                   name="customer_email" 
                                   class="rv-input" 
                                   required>
                        </div>
                        
                        <div class="rv-field">
                            <label for="customer_phone" class="rv-label">
                                <?php 
                                $phone_label = SettingsCore::get('mhm_rentiva_text_phone', '');
                                $phone_label = !empty($phone_label) ? $phone_label : __('Phone', 'mhm-rentiva');
                                echo esc_html($phone_label); 
                                ?>
                                <?php if ($phone_required === '1'): ?><span class="required">*</span><?php endif; ?>
                            </label>
                            <input type="tel" 
                                   id="customer_phone" 
                                   name="customer_phone" 
                                   class="rv-input"
                                   <?php if ($phone_required === '1'): ?>required<?php endif; ?>>
                        </div>
                        
                        <?php if ($registration_required === '1' && !$is_logged_in): ?>
                        <div class="rv-login-prompt">
                            <p><?php echo esc_html(SettingsCore::get('mhm_rentiva_text_already_have_account', __('Already have an account?', 'mhm-rentiva'))); ?></p>
                            <a href="<?php echo esc_url(SettingsCore::get('mhm_rentiva_login_url', wp_login_url(get_permalink()))); ?>" class="rv-login-link">
                                <?php echo esc_html(SettingsCore::get('mhm_rentiva_text_login_here', __('Login here', 'mhm-rentiva'))); ?>
                            </a>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($show_payment_options && $enable_deposit): ?>
                <!-- Payment Options -->
                <div class="rv-form-section rv-payment-options">
                    <h3 class="rv-section-title"><?php echo esc_html__('Payment Options', 'mhm-rentiva'); ?></h3>
                    
                    <div class="rv-payment-type-selection">
                        <label class="rv-payment-type">
                            <input type="radio" name="payment_type" value="deposit" <?php checked($default_payment, 'deposit'); ?>>
                            <span class="rv-payment-type-label">
                                <strong><?php echo esc_html__('Deposit Payment', 'mhm-rentiva'); ?></strong>
                                <small><?php echo esc_html__('Pay deposit for booking, pay remaining amount at vehicle delivery', 'mhm-rentiva'); ?></small>
                            </span>
                        </label>
                        <label class="rv-payment-type">
                            <input type="radio" name="payment_type" value="full" <?php checked($default_payment, 'full'); ?>>
                            <span class="rv-payment-type-label">
                                <strong><?php echo esc_html__('Full Payment', 'mhm-rentiva'); ?></strong>
                                <small><?php echo esc_html__('Pay full amount now', 'mhm-rentiva'); ?></small>
                            </span>
                        </label>
                    </div>

                    <!-- Payment Method -->
                    <?php
                    // ✅ Check payment method availability from backend settings
                    // Using full class name instead of 'use' statement (template file)
                    
                    // Check which payment methods are enabled
                    $stripe_enabled = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_stripe_enabled', '0') === '1';
                    $paypal_enabled = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_paypal_enabled', '0') === '1';
                    $paytr_enabled = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_paytr_enabled', '0') === '1';
                    $offline_enabled = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_offline_enabled', '0') === '1';
                    
                    $has_online_gateway = $stripe_enabled || $paypal_enabled || $paytr_enabled;
                    $has_any_payment = $has_online_gateway || $offline_enabled;
                    
                    if ($has_any_payment):
                    ?>
                    <div class="rv-payment-methods">
                        <h4 class="rv-subsection-title"><?php echo esc_html__('Payment Method', 'mhm-rentiva'); ?></h4>
                        <div class="rv-payment-method-selection">
                            <?php 
                            $first_method = true;
                            
                            // Online Payment option (if at least one gateway is enabled)
                            if ($has_online_gateway): 
                            ?>
                            <label class="rv-payment-method">
                                <input type="radio" name="payment_method" value="online" <?php echo $first_method ? 'checked' : ''; ?>>
                                <span class="rv-payment-method-label">
                                    <strong><?php echo esc_html__('Online Payment', 'mhm-rentiva'); ?></strong>
                                    <small><?php echo esc_html__('Secure payment with credit card', 'mhm-rentiva'); ?></small>
                                </span>
                            </label>
                            <?php 
                            $first_method = false;
                            endif; 
                            
                            // Offline Payment option (if enabled)
                            if ($offline_enabled): 
                            ?>
                            <label class="rv-payment-method">
                                <input type="radio" name="payment_method" value="offline" <?php echo $first_method ? 'checked' : ''; ?>>
                                <span class="rv-payment-method-label">
                                    <strong><?php echo esc_html__('Offline Payment', 'mhm-rentiva'); ?></strong>
                                    <small><?php echo esc_html__('Cash or bank transfer payment (within 30 minutes)', 'mhm-rentiva'); ?></small>
                                </span>
                            </label>
                            <?php 
                            $first_method = false;
                            endif; 
                            ?>
                        </div>

                        <!-- Online Payment Details -->
                        <?php
                        // ✅ Show online payment gateway selection (if at least one is enabled)
                        if ($has_online_gateway):
                        ?>
                        <div class="rv-online-payment-details">
                            <h5 class="rv-payment-gateway-title"><?php echo esc_html__('Payment Gateway', 'mhm-rentiva'); ?></h5>
                            <div class="rv-payment-gateways">
                                <?php 
                                $first_gateway = true;
                                
                                // Stripe Gateway
                                if ($stripe_enabled): 
                                ?>
                                <label class="rv-payment-gateway">
                                    <input type="radio" name="payment_gateway" value="stripe" <?php echo $first_gateway ? 'checked' : ''; ?>>
                                    <span class="rv-gateway-label">
                                        <span class="rv-gateway-icon">💳</span>
                                        <span class="rv-gateway-name"><?php echo esc_html__('Stripe', 'mhm-rentiva'); ?></span>
                                    </span>
                                </label>
                                <?php 
                                $first_gateway = false;
                                endif; 
                                
                                // PayPal Gateway
                                if ($paypal_enabled): 
                                ?>
                                <label class="rv-payment-gateway">
                                    <input type="radio" name="payment_gateway" value="paypal" <?php echo $first_gateway ? 'checked' : ''; ?>>
                                    <span class="rv-gateway-label">
                                        <span class="rv-gateway-icon">🅿️</span>
                                        <span class="rv-gateway-name"><?php echo esc_html__('PayPal', 'mhm-rentiva'); ?></span>
                                    </span>
                                </label>
                                <?php 
                                $first_gateway = false;
                                endif; 
                                
                                // PayTR Gateway
                                if ($paytr_enabled): 
                                ?>
                                <label class="rv-payment-gateway">
                                    <input type="radio" name="payment_gateway" value="paytr" <?php echo $first_gateway ? 'checked' : ''; ?>>
                                    <span class="rv-gateway-label">
                                        <span class="rv-gateway-icon">🏦</span>
                                        <span class="rv-gateway-name"><?php echo esc_html__('PayTR', 'mhm-rentiva'); ?></span>
                                    </span>
                                </label>
                                <?php 
                                $first_gateway = false;
                                endif; 
                                ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="rv-payment-error">
                            <p><?php echo esc_html__('No payment methods are currently available. Please contact the administrator.', 'mhm-rentiva'); ?></p>
                        </div>
                    <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Terms & Conditions (before booking button) - ALWAYS SHOW IF REQUIRED -->
            <?php if ($terms_required === '1'): ?>
            <div class="rv-form-section rv-terms-section">
                <div class="rv-field rv-terms-checkbox">
                    <label style="display: block; margin: 10px 0; font-weight: 500;">
                        <input type="checkbox" name="terms_accepted" id="rv-terms-accepted" value="on" required style="margin-right: 8px;">
                        <?php 
                        // Replace {privacy_policy} with actual link
                        $display_text = str_replace(
                            '{privacy_policy}', 
                            '<a href="' . esc_url(get_privacy_policy_url()) . '" target="_blank" style="color: #0073aa; text-decoration: underline;">' . esc_html__('Privacy Policy', 'mhm-rentiva') . '</a>',
                            esc_html($terms_text)
                        );
                        echo $display_text;
                        ?>
                        <span style="color: #d63638; margin-left: 4px;">*</span>
                    </label>
                </div>
            </div>
            <?php endif; ?>

            <!-- Form Buttons -->
            <div class="rv-form-actions">
                <button type="button" class="rv-submit-btn rv-btn rv-btn-primary">
                    <span class="rv-btn-text"><?php 
                    $make_booking_text = SettingsCore::get('mhm_rentiva_text_make_booking', '');
                    $make_booking_text = !empty($make_booking_text) ? $make_booking_text : __('Make Booking', 'mhm-rentiva');
                    echo esc_html($make_booking_text); 
                    ?></span>
                    <span class="rv-btn-loading" style="display: none;">
                        <span class="rv-spinner"></span>
                        <?php echo esc_html(SettingsCore::get('mhm_rentiva_text_processing', __('Processing...', 'mhm-rentiva'))); ?>
                    </span>
                </button>
            </div>

            <!-- Hidden Fields -->
            <input type="hidden" name="redirect_url" value="<?php echo esc_attr($redirect_url); ?>">
        </div>
        </form>

        <!-- Messages -->
        <div class="rv-messages">
            <div class="rv-success-message" style="display: none;"></div>
            <div class="rv-error-message" style="display: none;"></div>
        </div>
    </div>
</div>

<script>
// Define JavaScript variables
window.mhmRentivaBookingForm = {
    ajax_url: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
    nonce: '<?php echo esc_js(wp_create_nonce('mhm_rentiva_booking_form_nonce')); ?>',
    depositNonce: '<?php echo esc_js(wp_create_nonce('mhm_rentiva_booking_action')); ?>',
    currency_symbol: '<?php echo esc_js(apply_filters('mhm_rentiva/currency_symbol', '₺')); ?>',
    enable_deposit: <?php echo $enable_deposit ? 'true' : 'false'; ?>,
    default_payment: '<?php echo esc_js($default_payment); ?>',
    messages: {
        loading: '<?php echo esc_js(SettingsCore::get('mhm_rentiva_text_loading', __('Loading...', 'mhm-rentiva'))); ?>',
        error: '<?php echo esc_js(SettingsCore::get('mhm_rentiva_text_error', __('An error occurred.', 'mhm-rentiva'))); ?>',
        success: '<?php echo esc_js(SettingsCore::get('mhm_rentiva_text_booking_success', __('Booking successful', 'mhm-rentiva'))); ?>',
        selectVehicle: '<?php echo esc_js(SettingsCore::get('mhm_rentiva_text_select_vehicle', __('Please select a vehicle', 'mhm-rentiva'))); ?>',
        selectDates: '<?php echo esc_js(SettingsCore::get('mhm_rentiva_text_select_dates', __('Please select dates', 'mhm-rentiva'))); ?>',
        invalidDates: '<?php echo esc_js(SettingsCore::get('mhm_rentiva_text_invalid_dates', __('Invalid date range', 'mhm-rentiva'))); ?>',
        selectPaymentType: '<?php echo esc_js(SettingsCore::get('mhm_rentiva_text_select_payment_type', __('Please select payment type', 'mhm-rentiva'))); ?>',
        selectPaymentMethod: '<?php echo esc_js(SettingsCore::get('mhm_rentiva_text_select_payment_method', __('Please select payment method', 'mhm-rentiva'))); ?>',
        calculating: '<?php echo esc_js(SettingsCore::get('mhm_rentiva_text_calculating', __('Calculating...', 'mhm-rentiva'))); ?>',
        paymentRedirect: '<?php echo esc_js(SettingsCore::get('mhm_rentiva_text_payment_redirect', __('Redirecting to payment page...', 'mhm-rentiva'))); ?>',
        paymentSuccess: '<?php echo esc_js(SettingsCore::get('mhm_rentiva_text_payment_success', __('Payment completed successfully!', 'mhm-rentiva'))); ?>',
        paymentCancelled: '<?php echo esc_js(SettingsCore::get('mhm_rentiva_text_payment_cancelled', __('Payment cancelled.', 'mhm-rentiva'))); ?>',
        popupBlocked: '<?php echo esc_js(SettingsCore::get('mhm_rentiva_text_popup_blocked', __('Popup blocked. Redirecting to payment page...', 'mhm-rentiva'))); ?>'
    },
    currency: {
        symbol: '<?php echo esc_js(apply_filters('mhm_rentiva/currency_symbol', '₺')); ?>',
        position: 'after'
    },
    locale: '<?php echo esc_js(get_locale()); ?>'
};
</script>

<?php
// Check payment status messages
$payment_status = $_GET['payment_status'] ?? '';
$booking_id = $_GET['booking_id'] ?? '';

if ($payment_status && $booking_id): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const paymentStatus = '<?php echo esc_js($payment_status); ?>';
        const bookingId = '<?php echo esc_js($booking_id); ?>';
        
        // Save payment status to localStorage (for popup)
        localStorage.setItem('mhm_rentiva_payment_status', paymentStatus);
        
        // Show page message
        const form = document.querySelector('.rv-booking-form-inner');
        if (form) {
            const messagesContainer = form.querySelector('.rv-messages');
            if (messagesContainer) {
                if (paymentStatus === 'success') {
                    messagesContainer.querySelector('.rv-success-message').innerHTML = 
                        '<?php echo esc_js(esc_html__('Payment completed successfully! Your booking is confirmed.', 'mhm-rentiva')); ?>';
                    messagesContainer.querySelector('.rv-success-message').style.display = 'block';
                    
                    // Update booking status
                    setTimeout(() => {
                        window.location.href = window.location.href.split('?')[0];
                    }, 3000);
                } else if (paymentStatus === 'cancelled') {
                    messagesContainer.querySelector('.rv-error-message').innerHTML = 
                        '<?php echo esc_js(esc_html__('Payment cancelled. Your booking is in pending status.', 'mhm-rentiva')); ?>';
                    messagesContainer.querySelector('.rv-error-message').style.display = 'block';
                }
            }
        }
    });
    </script>
<?php endif; ?>

