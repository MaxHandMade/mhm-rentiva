<?php

/**
 * Vehicle Comparison Template
 * 
 * @package MHMRentiva
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}



// Get template data
$atts = $atts ?? [];
$vehicles = $vehicles ?? [];
$features = $features ?? [];
$all_vehicles = $all_vehicles ?? [];
$max_vehicles = $max_vehicles ?? 3;
$has_vehicles = $has_vehicles ?? false;
$can_add_more = $can_add_more ?? false;
$show_add_vehicle = $show_add_vehicle ?? false;


// Layout settings
$layout = $atts['layout'] ?? 'table';
$show_prices = ($atts['show_prices'] ?? true) === '1' || ($atts['show_prices'] ?? true) === true;
$custom_class = trim($atts['class'] ?? '');

?>

<div class="rv-vehicle-comparison rv-vehicle-comparison-container rv-layout-table" data-max-vehicles="<?php echo esc_attr($max_vehicles); ?>" data-features='<?php echo esc_attr(wp_json_encode($features)); ?>' data-all-vehicles='<?php echo esc_attr(wp_json_encode($all_vehicles)); ?>'>

    <!-- Add Vehicle Section -->
    <div class="rv-add-vehicle-section">
        <div class="rv-add-vehicle-form">
            <div class="rv-form-row">
                <select id="rv-add-vehicle-select" class="rv-vehicle-select">
                    <option value=""><?php echo esc_html__('Select a vehicle to compare', 'mhm-rentiva'); ?></option>
                    <?php foreach ($all_vehicles as $vehicle): ?>
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

    <!-- Comparison Content -->
    <?php if ($has_vehicles): ?>
        <div class="rv-comparison-content">

            <!-- Comparison Header -->
            <div class="rv-comparison-header">
                <h3><?php echo esc_html__('Vehicle Comparison', 'mhm-rentiva'); ?></h3>
                <div class="rv-comparison-count">
                    <?php
                    $count = count($vehicles);
                    if ($count === 1) {
                        echo esc_html__('1 vehicle being compared', 'mhm-rentiva');
                    } else {
                        printf(esc_html__('%d vehicles being compared', 'mhm-rentiva'), $count);
                    }
                    ?>
                </div>
            </div>

            <!-- Table Layout -->
            <?php if ($layout === 'table'): ?>
                <div class="rv-comparison-table-wrapper">
                    <table class="rv-comparison-table">
                        <thead>
                            <tr>
                                <th class="rv-feature-column"><?php echo esc_html__('Feature', 'mhm-rentiva'); ?></th>
                                <?php foreach ($vehicles as $vehicle): ?>
                                    <th class="rv-vehicle-column">
                                        <div class="rv-vehicle-header">
                                            <div class="rv-vehicle-image-container">
                                                <?php if (!empty($vehicle['image_url'])): ?>
                                                    <img src="<?php echo esc_url($vehicle['image_url']); ?>" alt="<?php echo esc_attr($vehicle['title']); ?>" class="rv-vehicle-image">
                                                <?php endif; ?>
                                            </div>
                                            <button type="button" class="rv-remove-vehicle" data-vehicle-id="<?php echo esc_attr($vehicle['id']); ?>">
                                                <span class="dashicons dashicons-dismiss"></span>
                                            </button>

                                            <div class="rv-vehicle-status-container">
                                                <?php
                                                $is_available = $vehicle['availability']['is_available'] ?? ($vehicle['meta']['available'] ?? true);
                                                $status_text = $vehicle['availability']['text'] ?? __('Unavailable', 'mhm-rentiva');

                                                if (!$is_available):
                                                ?>
                                                    <span class="rv-badge rv-badge--unavailable">
                                                        <?php echo esc_html($status_text); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <h4><?php echo esc_html($vehicle['title']); ?></h4>
                                            <?php
                                            $is_available = $vehicle['availability']['is_available'] ?? ($vehicle['meta']['available'] ?? true);
                                            $btn_style = 'display: inline-block !important; color: white !important; padding: 8px 16px !important; border-radius: 6px !important; text-decoration: none !important; font-size: 13px !important; font-weight: 600 !important; margin-top: 5px !important; margin-bottom: 10px !important; text-align: center !important; width: 100% !important; max-width: 140px !important;';
                                            $btn_class = 'rv-book-now-btn';
                                            $btn_href = esc_url($vehicle['permalink']);
                                            $btn_attrs = '';

                                            if (!$is_available) {
                                                $btn_style .= ' background: #95a5a6 !important; opacity: 0.6; pointer-events: none; cursor: not-allowed;';
                                                $btn_class .= ' rv-btn-disabled';
                                                $btn_href = 'javascript:void(0);';
                                                $btn_attrs = 'aria-disabled="true" tabindex="-1"';
                                            }
                                            ?>
                                            <a href="<?php echo esc_url($btn_href); ?>" class="<?php echo esc_attr($btn_class); ?>" style="<?php echo esc_attr($btn_style); ?>" <?php echo wp_kses_post($btn_attrs); ?>>
                                                <?php echo esc_html__('Make Reservation', 'mhm-rentiva'); ?>
                                            </a>
                                            <!-- Price Display -->
                                            <?php
                                            $price = $vehicle['features']['price_per_day'] ?? 0;
                                            if ($show_prices && $price > 0):
                                            ?>
                                                <div class="mhm-comparison-price" style="text-align: center; font-weight: bold; font-size: 1.1em; color: #2ecc71; margin-top: 10px;">
                                                    <?php
                                                    echo esc_html(number_format($price, 0, ',', '.'));
                                                    echo ' ' . esc_html($vehicle['features']['currency_symbol'] ?? '$');
                                                    echo ' <span class="price-suffix" style="font-size: 0.9em; color: #7f8c8d; font-weight: normal;">/ ' . esc_html__('day', 'mhm-rentiva') . '</span>';
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($features as $feature_key => $feature_label): ?>
                                <?php if ($feature_key === 'price_per_day') continue; ?>
                                <tr class="rv-feature-row">
                                    <td class="rv-feature-label"><?php echo esc_html($feature_label); ?></td>
                                    <?php foreach ($vehicles as $vehicle): ?>
                                        <td class="rv-feature-value">
                                            <?php
                                            $value = $vehicle['features'][$feature_key] ?? '-';

                                            // Completely dynamic - no hardcoded fields since price is skipped
                                            echo '<span class="rv-feature-text">' . esc_html($value) . '</span>';
                                            ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile List Layout (Visible on small screens only) -->
                <div class="rv-comparison-mobile-list">
                    <?php foreach ($vehicles as $vehicle): ?>
                        <div class="rv-mobile-card-item">

                            <!-- Mobile Header: Image, Title, Badge, Toggle -->
                            <div class="rv-mobile-card-header-wrapper">
                                <div class="rv-mobile-card-image">
                                    <?php if (!empty($vehicle['image_url'])): ?>
                                        <img src="<?php echo esc_url($vehicle['image_url']); ?>" alt="<?php echo esc_attr($vehicle['title']); ?>">
                                    <?php endif; ?>
                                </div>

                                <div class="rv-mobile-card-info">
                                    <h4 class="rv-mobile-title"><?php echo esc_html($vehicle['title']); ?></h4>

                                    <?php
                                    $is_available = $vehicle['availability']['is_available'] ?? ($vehicle['meta']['available'] ?? true);
                                    $status_text = $vehicle['availability']['text'] ?? __('Unavailable', 'mhm-rentiva');
                                    if (!$is_available):
                                    ?>
                                        <span class="rv-badge rv-badge--unavailable" style="margin-bottom: 5px;">
                                            <?php echo esc_html($status_text); ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php
                                    $price = $vehicle['features']['price_per_day'] ?? 0;
                                    if ($show_prices && $price > 0):
                                    ?>
                                        <div class="rv-mobile-price">
                                            <?php echo esc_html(number_format($price, 0, ',', '.')); ?>
                                            <?php echo esc_html($vehicle['features']['currency_symbol'] ?? '$'); ?>
                                            <span class="rv-period">/ <?php echo esc_html__('day', 'mhm-rentiva'); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <button type="button" class="rv-remove-vehicle" data-vehicle-id="<?php echo esc_attr($vehicle['id']); ?>">
                                    <span class="dashicons dashicons-dismiss"></span>
                                </button>
                            </div>

                            <!-- Accordion Toggle -->
                            <button type="button" class="rv-mobile-accordion-toggle" onclick="this.parentElement.classList.toggle('active');">
                                <span><?php echo esc_html__('Show Features', 'mhm-rentiva'); ?></span>
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </button>

                            <!-- Accordion Content -->
                            <div class="rv-mobile-accordion-content">
                                <div class="rv-mobile-features-list">
                                    <?php foreach ($features as $feature_key => $feature_label): ?>
                                        <?php if ($feature_key === 'price_per_day') continue; ?>
                                        <div class="rv-mobile-feature-row">
                                            <span class="rv-mobile-label"><?php echo esc_html($feature_label); ?></span>
                                            <span class="rv-mobile-value">
                                                <?php
                                                $value = $vehicle['features'][$feature_key] ?? '-';

                                                // Dynamic text output
                                                echo esc_html($value);
                                                ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="rv-mobile-actions">
                                    <?php
                                    $btn_style = '';
                                    $btn_class = 'rv-book-now-btn rv-mobile-btn';
                                    $btn_href = esc_url($vehicle['permalink']);
                                    $btn_attrs = '';

                                    if (!$is_available) {
                                        $btn_class .= ' rv-btn-disabled';
                                        $btn_href = 'javascript:void(0);';
                                        $btn_attrs = 'aria-disabled="true"';
                                    }
                                    ?>
                                    <a href="<?php echo esc_url($btn_href); ?>" class="<?php echo esc_attr($btn_class); ?>" <?php echo wp_kses_post($btn_attrs); ?>>
                                        <?php echo esc_html__('Make Reservation', 'mhm-rentiva'); ?>
                                    </a>
                                </div>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Card Layout -->
            <?php else: ?>
                <div class="rv-comparison-cards">
                    <?php foreach ($vehicles as $vehicle): ?>
                        <div class="rv-vehicle-card" data-vehicle-id="<?php echo esc_attr($vehicle['id']); ?>">
                            <div class="rv-card-header">
                                <?php if (!empty($vehicle['image_url'])): ?>
                                    <img src="<?php echo esc_url($vehicle['image_url']); ?>" alt="<?php echo esc_attr($vehicle['title']); ?>" class="rv-card-image">
                                <?php endif; ?>
                                <h4><?php echo esc_html($vehicle['title']); ?></h4>
                                <button type="button" class="rv-remove-vehicle" data-vehicle-id="<?php echo esc_attr($vehicle['id']); ?>">
                                    <span class="dashicons dashicons-dismiss"></span>
                                </button>
                                <?php
                                $is_available = $vehicle['availability']['is_available'] ?? ($vehicle['meta']['available'] ?? true);
                                $status_text = $vehicle['availability']['text'] ?? __('Unavailable', 'mhm-rentiva');

                                if (!$is_available):
                                ?>
                                    <div style="width: 100%; text-align: center; margin-top: 5px;">
                                        <span class="rv-badge rv-badge--unavailable" style="display: inline-block;">
                                            <?php echo esc_html($status_text); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="rv-card-features">
                                <?php foreach ($features as $feature_key => $feature_label): ?>
                                    <div class="rv-feature-item">
                                        <span class="rv-feature-label"><?php echo esc_html($feature_label); ?>:</span>
                                        <span class="rv-feature-value">
                                            <?php
                                            $value = $vehicle['features'][$feature_key] ?? '-';

                                            // Special formatting for specific fields
                                            if ($feature_key === 'price_per_day' && $show_prices) {
                                                if ($value > 0) {
                                                    echo '<span class="rv-price">';
                                                    echo esc_html(number_format($value, 0, ',', '.'));
                                                    echo ' ' . esc_html($vehicle['features']['currency_symbol'] ?? '$');
                                                    echo '</span>';
                                                } else {
                                                    echo '<span class="rv-no-price">-</span>';
                                                }
                                            } else {
                                                // Completely dynamic - no hardcoded fields
                                                echo '<span class="rv-feature-text">' . esc_html($value) . '</span>';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="rv-card-actions">
                                <?php
                                $is_available = $vehicle['availability']['is_available'] ?? ($vehicle['meta']['available'] ?? true);
                                $btn_class = 'rv-book-now-btn';
                                $btn_href = esc_url($vehicle['permalink']);
                                $btn_attrs = '';

                                if (!$is_available) {
                                    $btn_class .= ' rv-btn-disabled';
                                    $btn_href = 'javascript:void(0);';
                                    $btn_attrs = 'aria-disabled="true" tabindex="-1" style="opacity: 0.6; pointer-events: none; cursor: not-allowed;"';
                                }
                                ?>
                                <a href="<?php echo esc_url($btn_href); ?>" class="<?php echo esc_attr($btn_class); ?>" <?php echo wp_kses_post($btn_attrs); ?>>
                                    <?php echo esc_html__('Make Reservation', 'mhm-rentiva'); ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    <?php endif; ?>

    <div class="rv-messages">
        <div class="rv-success-message rv-hidden"></div>
        <div class="rv-error-message rv-hidden"></div>
    </div>

</div>

<!-- JavaScript Configuration moved to separate file -->