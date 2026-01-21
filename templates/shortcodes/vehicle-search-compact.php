<?php

/**
 * Compact Vehicle Search Form Template
 * 
 * Variables:
 * - array $atts
 * - array $form_data
 * - string $nonce_field
 */
if (!defined('ABSPATH')) {
    exit;
}


?>

<div class="rv-search-form-compact <?php echo esc_attr($atts['class'] ?? ''); ?>" id="rv-search-form-compact">
    <div class="rv-search-header-compact">
        <h3><?php echo esc_html__('Find Your Perfect Vehicle', 'mhm-rentiva'); ?></h3>
    </div>

    <?php
    $search_results_url = \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_search_results');
    // Fallback to current page if search results page not found
    if (empty($search_results_url) || $search_results_url === home_url('/')) {
        $search_results_url = get_permalink(); // Use current page as fallback
    }
    ?>
    <form class="rv-search-filters-compact" id="rv-search-filters-compact" method="get" action="<?php echo esc_url($search_results_url); ?>">
        <div class="rv-search-main-row">
            <!-- Keyword -->
            <div class="rv-search-field rv-search-keyword">
                <label for="rv-keyword"><?php echo esc_html__('Vehicle Name', 'mhm-rentiva'); ?></label>
                <input type="text" id="rv-keyword" name="keyword" placeholder="<?php echo esc_attr__('Search vehicles...', 'mhm-rentiva'); ?>" value="<?php echo esc_attr($_GET['keyword'] ?? ''); ?>" />
            </div>

            <!-- Pickup Date & Time -->
            <div class="rv-search-field rv-search-datetime">
                <label for="rv-pickup-date"><?php echo esc_html__('Pickup Date & Time', 'mhm-rentiva'); ?></label>
                <div class="rv-datetime-wrapper">
                    <input type="text" id="rv-pickup-date" name="pickup_date" class="rv-date-input" placeholder="<?php echo esc_attr__('Date', 'mhm-rentiva'); ?>" value="<?php echo esc_attr($_GET['pickup_date'] ?? ''); ?>" readonly />
                    <select name="pickup_time" id="rv-pickup-time" class="rv-time-select">
                        <?php
                        $selected_pickup = $_GET['pickup_time'] ?? '10:00';
                        for ($i = 0; $i < 24; $i++) {
                            foreach (['00', '30'] as $min) {
                                $time = sprintf('%02d:%s', $i, $min);
                                echo '<option value="' . $time . '" ' . selected($selected_pickup, $time, false) . '>' . $time . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
            </div>

            <!-- Return Date & Time -->
            <div class="rv-search-field rv-search-datetime">
                <label for="rv-return-date"><?php echo esc_html__('Return Date & Time', 'mhm-rentiva'); ?></label>
                <div class="rv-datetime-wrapper">
                    <input type="text" id="rv-return-date" name="return_date" class="rv-date-input" placeholder="<?php echo esc_attr__('Date', 'mhm-rentiva'); ?>" value="<?php echo esc_attr($_GET['return_date'] ?? ''); ?>" readonly />
                    <select id="rv-return-time" class="rv-time-select rv-time-select-disabled" disabled readonly>
                        <option value=""><?php echo esc_html__('Select pickup time first', 'mhm-rentiva'); ?></option>
                        <?php
                        $selected_return = $_GET['return_time'] ?? '';
                        $selected_pickup = $_GET['pickup_time'] ?? '';
                        // If pickup time is set, use it as default for return time
                        if (!$selected_return && $selected_pickup) {
                            $selected_return = $selected_pickup;
                        }
                        for ($i = 0; $i < 24; $i++) {
                            foreach (['00', '30'] as $min) {
                                $time = sprintf('%02d:%s', $i, $min);
                                echo '<option value="' . $time . '" ' . selected($selected_return, $time, false) . '>' . $time . '</option>';
                            }
                        }
                        ?>
                    </select>
                    <input type="hidden" id="rv-return-time-hidden" name="return_time" value="<?php echo esc_attr($selected_return); ?>" />
                </div>
            </div>

            <!-- Search Button -->
            <div class="rv-search-field rv-search-submit">
                <button type="submit" class="rv-search-btn">
                    <span class="rv-search-icon">🔍</span>
                    <?php echo esc_html__('Search', 'mhm-rentiva'); ?>
                </button>
            </div>
        </div>
        <!-- Advanced Filters (Collapsible) -->
        <div class="rv-advanced-filters-compact">
            <button type="button" class="rv-toggle-filters-compact" id="rv-toggle-filters-compact">
                <span class="rv-toggle-text"><?php echo esc_html__('Advanced Filters', 'mhm-rentiva'); ?></span>
                <span class="rv-toggle-icon">▼</span>
            </button>
            <div class="rv-advanced-content-compact" id="rv-advanced-content-compact" style="display: none;">
                <div class="rv-advanced-row-compact">
                    <?php if (($atts['show_price_range'] ?? '1') === '1'): ?>
                        <!-- Price Range -->
                        <div class="rv-search-field rv-search-price">
                            <label for="rv-min-price"><?php echo esc_html__('Min Price', 'mhm-rentiva'); ?></label>
                            <input type="number" id="rv-min-price" name="min_price" min="0" step="10" placeholder="0" value="<?php echo esc_attr($_GET['min_price'] ?? ''); ?>" />
                        </div>
                        <div class="rv-search-field rv-search-price">
                            <label for="rv-max-price"><?php echo esc_html__('Max Price', 'mhm-rentiva'); ?></label>
                            <input type="number" id="rv-max-price" name="max_price" min="0" step="10" placeholder="10000" value="<?php echo esc_attr($_GET['max_price'] ?? ''); ?>" />
                        </div>
                    <?php endif; ?>
                    <?php if (($atts['show_fuel_type'] ?? '1') === '1'): ?>
                        <!-- Fuel Type -->
                        <div class="rv-search-field rv-search-fuel">
                            <label for="rv-fuel-type"><?php echo esc_html__('Fuel Type', 'mhm-rentiva'); ?></label>
                            <select id="rv-fuel-type" name="fuel_type">
                                <option value=""><?php echo esc_html__('All Fuel Types', 'mhm-rentiva'); ?></option>
                                <?php foreach (($form_data['fuel_types'] ?? []) as $key => $fuel_type): ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($_GET['fuel_type'] ?? '', $key); ?>>
                                        <?php echo esc_html($fuel_type); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <?php if (($atts['show_transmission'] ?? '1') === '1'): ?>
                        <!-- Transmission -->
                        <div class="rv-search-field rv-search-transmission">
                            <label for="rv-transmission"><?php echo esc_html__('Transmission', 'mhm-rentiva'); ?></label>
                            <select id="rv-transmission" name="transmission">
                                <option value=""><?php echo esc_html__('All Transmissions', 'mhm-rentiva'); ?></option>
                                <?php foreach (($form_data['transmissions'] ?? []) as $key => $transmission): ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($_GET['transmission'] ?? '', $key); ?>>
                                        <?php echo esc_html($transmission); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <?php if (($atts['show_seats'] ?? '1') === '1'): ?>
                        <!-- Seats -->
                        <div class="rv-search-field rv-search-seats">
                            <label for="rv-seats"><?php echo esc_html__('Seats', 'mhm-rentiva'); ?></label>
                            <select id="rv-seats" name="seats">
                                <option value=""><?php echo esc_html__('All Seats', 'mhm-rentiva'); ?></option>
                                <?php foreach (($form_data['seat_options'] ?? []) as $key => $seats): ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($_GET['seats'] ?? '', $key); ?>>
                                        <?php echo esc_html($seats); ?> <?php echo esc_html__('seats', 'mhm-rentiva'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </form>
</div>