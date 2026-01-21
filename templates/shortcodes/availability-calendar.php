<?php

/**
 * Availability Calendar Template
 * 
 * @var array $args Template data
 */

if (!defined('ABSPATH')) {
    exit;
}



// Get template data (variables from extract)
$atts = $atts ?? [];
$vehicle = $vehicle ?? null;
$vehicle_id = $vehicle_id ?? 0;
$vehicles_list = $vehicles_list ?? [];
$start_month = $start_month ?? wp_date('Y-m');
$months_to_show = $months_to_show ?? 3;
$availability_data = $availability_data ?? [];
$pricing_data = $pricing_data ?? [];
$current_user = $current_user ?? null;


// Shortcode parameters
$show_pricing = $atts['show_pricing'] ?? apply_filters('mhm_rentiva/availability_calendar/show_pricing', '1');
$show_seasonal_prices = $atts['show_seasonal_prices'] ?? apply_filters('mhm_rentiva/availability_calendar/show_seasonal_prices', '1');
$show_discounts = $atts['show_discounts'] ?? apply_filters('mhm_rentiva/availability_calendar/show_discounts', '1');
$show_booking_btn = $atts['show_booking_btn'] ?? apply_filters('mhm_rentiva/availability_calendar/show_booking_btn', '1');
$theme = $atts['theme'] ?? apply_filters('mhm_rentiva/availability_calendar/theme', 'default');
$class = $atts['class'] ?? '';
$integrate_pricing = $atts['integrate_pricing'] ?? apply_filters('mhm_rentiva/availability_calendar/integrate_pricing', '1');

if (!$vehicle && $vehicle_id > 0) {
    echo '<div class="rv-availability-error">' . esc_html__('Vehicle not found.', 'mhm-rentiva') . '</div>';
    return;
}


// If no vehicle is selected but vehicles exist, select the first one
if ($vehicle_id === 0 && !empty($vehicles_list)) {
    $vehicle_id = $vehicles_list[0]['id'];
    $vehicle = get_post($vehicle_id);
}

if (empty($vehicles_list)) {
    echo '<div class="rv-availability-error">' . esc_html__('No vehicles found. Please add vehicles first.', 'mhm-rentiva') . '</div>';
    return;
}
?>

<?php
// Get vehicle price
$vehicle_price = 0;
$is_available = true;
$status_text = '';

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
    $status_text = $is_available ? __("Available", "mhm-rentiva") : __("Out of Order", "mhm-rentiva");
}
?>

<div class="rv-availability-calendar rv-theme-<?php echo esc_attr($theme); ?> <?php echo esc_attr($class); ?>"
    data-vehicle-id="<?php echo esc_attr($vehicle_id); ?>"
    data-vehicle-price="<?php echo esc_attr($vehicle_price); ?>"
    data-start-month="<?php echo esc_attr($start_month); ?>"
    data-months-to-show="<?php echo esc_attr($months_to_show); ?>">

    <!-- Calendar Header -->
    </h3>
</div>

<!-- Vehicle Card -->
<?php if ($vehicle) : ?>
    <div class="rv-vehicle-card">
        <div class="rv-vehicle-card-content">
            <!-- Vehicle Image -->
            <div class="rv-vehicle-image">
                <?php
                $vehicle_image = get_the_post_thumbnail($vehicle->ID, 'medium', ['class' => 'rv-vehicle-img']);
                if ($vehicle_image) {
                    echo wp_kses_post($vehicle_image);
                } else {
                    // Get placeholder with fallback
                    $placeholder_url = \MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList::get_vehicle_image($vehicle->ID);
                    echo '<img src="' . esc_url($placeholder_url) . '" alt="' . esc_attr($vehicle->post_title) . '" class="rv-vehicle-img">';
                }
                ?>
                <!-- Rating Badge -->
                <?php
                // Get vehicle rating information
                $vehicle_rating = \MHMRentiva\Admin\Frontend\Shortcodes\VehicleRatingForm::get_vehicle_rating($vehicle->ID);
                if ($vehicle_rating['rating_count'] > 0):
                ?>
                    <div class="rv-vehicle-rating">
                        <span class="rv-rating-stars"><?php echo esc_html($vehicle_rating['stars']); ?></span>
                        <span class="rv-rating-count">(<?php echo esc_html($vehicle_rating['rating_count']); ?>)</span>
                    </div>
                <?php endif; ?>

                <!-- Availability Badge -->
                <?php if (!$is_available): ?>
                    <div class="rv-badge-wrapper" style="position: absolute; top: 10px; left: 10px; z-index: 10;">
                        <span class="rv-badge rv-badge--unavailable" style="background-color: #ef4444; color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <?php echo esc_html($status_text); ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Vehicle Details -->
            <div class="rv-vehicle-details">
                <div class="rv-vehicle-header">
                    <h4 class="rv-vehicle-title"><?php echo esc_html($vehicle->post_title); ?></h4>
                    <?php
                    $is_favorite = false;
                    if (is_user_logged_in()) {
                        $user_id = get_current_user_id();
                        $favorites = get_user_meta($user_id, 'mhm_rentiva_favorites', true);
                        if (is_array($favorites) && in_array($vehicle->ID, $favorites)) {
                            $is_favorite = true;
                        }
                    }
                    ?>
                    <button class="rv-favorite-btn <?php echo $is_favorite ? 'active' : ''; ?>" data-vehicle-id="<?php echo esc_attr($vehicle->ID); ?>" title="<?php echo esc_attr__('Add to favorites', 'mhm-rentiva'); ?>">
                        <span class="dashicons <?php echo $is_favorite ? 'dashicons-heart' : 'dashicons-heart'; ?>" style="<?php echo $is_favorite ? 'color: #e74c3c;' : ''; ?>"></span>
                    </button>
                </div>

                <!-- Vehicle Specifications -->
                <div class="rv-vehicle-specs">
                    <?php
                    $fuel_type = get_post_meta($vehicle->ID, '_mhm_rentiva_fuel_type', true);
                    $transmission = get_post_meta($vehicle->ID, '_mhm_rentiva_transmission', true);
                    $year = get_post_meta($vehicle->ID, '_mhm_rentiva_year', true);
                    $mileage = get_post_meta($vehicle->ID, '_mhm_rentiva_mileage', true);
                    $seats = get_post_meta($vehicle->ID, '_mhm_rentiva_seats', true);

                    // Mappings
                    $fuel_types = [
                        'gasoline' => __('Gasoline', 'mhm-rentiva'),
                        'petrol'   => __('Gasoline', 'mhm-rentiva'),
                        'diesel'   => __('Diesel', 'mhm-rentiva'),
                        'lpg'      => __('LPG', 'mhm-rentiva'),
                        'electric' => __('Electric', 'mhm-rentiva'),
                        'hybrid'   => __('Hybrid', 'mhm-rentiva')
                    ];
                    $transmission_types = [
                        'manual'    => __('Manual', 'mhm-rentiva'),
                        'auto'      => __('Automatic', 'mhm-rentiva'),
                        'semi_auto' => __('Semi-Automatic', 'mhm-rentiva')
                    ];

                    $display_fuel = $fuel_types[$fuel_type] ?? $fuel_type;
                    $display_transmission = $transmission_types[$transmission] ?? $transmission;
                    ?>

                    <?php if ($display_fuel) : ?>
                        <span class="rv-spec-badge"><?php echo esc_html($display_fuel); ?></span>
                    <?php endif; ?>

                    <?php if ($display_transmission) : ?>
                        <span class="rv-spec-badge"><?php echo esc_html($display_transmission); ?></span>
                    <?php endif; ?>

                    <?php if ($year) : ?>
                        <span class="rv-spec-badge"><?php echo esc_html($year); ?></span>
                    <?php endif; ?>

                    <?php if ($mileage) : ?>
                        <span class="rv-spec-badge"><?php echo esc_html($mileage); ?> <?php echo esc_html__('miles', 'mhm-rentiva'); ?></span>
                    <?php endif; ?>

                    <?php if ($seats) : ?>
                        <span class="rv-spec-badge"><?php echo esc_html($seats); ?> <?php echo esc_html__('people', 'mhm-rentiva'); ?></span>
                    <?php endif; ?>
                </div>

                <!-- Price -->
                <div class="rv-vehicle-price">
                    <?php echo esc_html(number_format($vehicle_price, 0, ',', '.')); ?> <?php echo esc_html(apply_filters('mhm_rentiva/currency_symbol', '$')); ?><?php echo esc_html(esc_html__('/day', 'mhm-rentiva')); ?>
                </div>
            </div>
        </div>

        <!-- Vehicle Change Button (if multiple vehicles) -->
        <?php if (count($vehicles_list) > 1) : ?>
            <div class="rv-vehicle-switcher">
                <button class="rv-switch-vehicle-btn" type="button" data-vehicles='<?php echo esc_attr(wp_json_encode($vehicles_list)); ?>'>
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                    <?php echo esc_html__('Change Vehicle', 'mhm-rentiva'); ?>
                </button>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>


<!-- Calendar Controls -->
<div class="rv-calendar-controls" style="<?php echo !$is_available ? 'display: none;' : ''; ?>">
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
</div>

<!-- Status Legend -->
<div class="rv-availability-legend" style="<?php echo !$is_available ? 'display: none;' : ''; ?>">
    <div class="rv-legend-item">
        <span class="rv-legend-color rv-status-available"></span>
        <span class="rv-legend-text"><?php echo esc_html__('Available', 'mhm-rentiva'); ?></span>
    </div>
    <div class="rv-legend-item">
        <span class="rv-legend-color rv-status-partial"></span>
        <span class="rv-legend-text"><?php echo esc_html__('Partially Booked', 'mhm-rentiva'); ?></span>
    </div>
    <div class="rv-legend-item">
        <span class="rv-legend-color rv-status-booked"></span>
        <span class="rv-legend-text"><?php echo esc_html__('Booked', 'mhm-rentiva'); ?></span>
    </div>
    <div class="rv-legend-item">
        <span class="rv-legend-color rv-status-maintenance"></span>
        <span class="rv-legend-text"><?php echo esc_html__('Maintenance', 'mhm-rentiva'); ?></span>
    </div>
    <?php if ($show_pricing === '1') : ?>
        <div class="rv-legend-item">
            <span class="rv-legend-color rv-status-weekend"></span>
            <span class="rv-legend-text"><?php echo esc_html__('Weekend', 'mhm-rentiva'); ?></span>
        </div>
    <?php endif; ?>
</div>

<!-- Calendar Grid -->
<div class="rv-calendar-hint" style="<?php echo !$is_available ? 'display: none;' : ''; ?>">
    <span class="dashicons dashicons-info-outline"></span>
    <?php echo esc_html__('Click on start and end dates to select your rental period.', 'mhm-rentiva'); ?>
</div>

<div class="rv-availability-grid" style="<?php echo !$is_available ? 'display: none;' : ''; ?>">
    <?php if (empty($availability_data)) : ?>
        <div class="rv-no-data-message">
            <div class="rv-no-data-content">
                <span class="dashicons dashicons-calendar-alt"></span>
                <h4><?php echo esc_html__('Calendar Data Not Found', 'mhm-rentiva'); ?></h4>
                <p><?php echo esc_html__('Please select a vehicle or check vehicle data.', 'mhm-rentiva'); ?></p>
                <?php if (!empty($vehicles_list)) : ?>
                    <div class="rv-vehicle-selector">
                        <label for="rv-availability-vehicle-select-fallback" class="rv-selector-label">
                            <?php echo esc_html__('Select Vehicle:', 'mhm-rentiva'); ?>
                        </label>
                        <select id="rv-availability-vehicle-select-fallback" class="rv-vehicle-dropdown" data-current-vehicle="0">
                            <option value=""><?php echo esc_html__('Select vehicle...', 'mhm-rentiva'); ?></option>
                            <?php foreach ($vehicles_list as $vehicle_option): ?>
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
            <!-- Month Header -->
            <div class="rv-month-header">
                <h4 class="rv-month-title">
                    <?php echo esc_html($month_data['month_name'] . ' ' . $month_data['year']); ?>
                </h4>
            </div>
            <!-- ... Weekdays and Days are handled in JS or below ... -->
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
                $first_day = date('N', strtotime($month_key . '-01')); // Monday = 1, Sunday = 7
                $first_day = $first_day == 7 ? 0 : $first_day - 1; // Sunday = 0, Monday = 0


                // Placeholder for empty days
                for ($i = 0; $i < $first_day; $i++) {
                    echo '<div class="rv-calendar-day rv-day-empty"></div>';
                }

                // Show days of the month
                foreach ($month_data['days'] as $date => $day_data) {
                    $day_classes = [
                        'rv-calendar-day',
                        'rv-day-' . $day_data['status'],
                        $day_data['is_weekend'] ? 'rv-day-weekend' : '',
                        $day_data['is_today'] ? 'rv-today' : '',
                        $day_data['is_past'] ? 'rv-past' : ''
                    ];

                    $day_classes = array_filter($day_classes);
                    $day_class = implode(' ', $day_classes);

                    // Tooltip content
                    $tooltip_content = '';
                    if (!empty($day_data['bookings'])) {
                        $booking_titles = array_column($day_data['bookings'], 'title');
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

                    echo sprintf(
                        '<div class="%s" data-date="%s" %s %s>',
                        esc_attr($day_class),
                        esc_attr($date),
                        $tooltip_content,
                        $price_info
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
                    if (!empty($day_data['bookings'])) {
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