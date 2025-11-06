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

// Load plugin textdomain
if (!function_exists('mhm_rentiva_load_textdomain')) {
    function mhm_rentiva_load_textdomain() {
        load_plugin_textdomain('mhm-rentiva', false, dirname(plugin_basename(__FILE__)) . '/../../languages/');
    }
    mhm_rentiva_load_textdomain();
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
                                                <button type="button" class="rv-remove-vehicle" data-vehicle-id="<?php echo esc_attr($vehicle['id']); ?>">
                                                    <span class="dashicons dashicons-dismiss"></span>
                                                </button>
                                            </div>
                                            <h4><?php echo esc_html($vehicle['title']); ?></h4>
                                            <a href="<?php echo esc_url($vehicle['permalink']); ?>" class="rv-book-now-btn" style="display: inline-block !important; background: #27ae60 !important; color: white !important; padding: 8px 16px !important; border-radius: 6px !important; text-decoration: none !important; font-size: 13px !important; font-weight: 600 !important; margin-top: 5px !important; margin-bottom: 10px !important; text-align: center !important; width: 100% !important; max-width: 140px !important;">
                                                <?php echo esc_html__('Make Reservation', 'mhm-rentiva'); ?>
                                            </a>
                                        </div>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($features as $feature_key => $feature_label): ?>
                                <tr class="rv-feature-row">
                                    <td class="rv-feature-label"><?php echo esc_html($feature_label); ?></td>
                                    <?php foreach ($vehicles as $vehicle): ?>
                                        <td class="rv-feature-value">
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
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
                                <a href="<?php echo esc_url($vehicle['permalink']); ?>" class="rv-book-now-btn">
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
