<?php
/**
 * Vehicle Meta Template
 * 
 * @package MHMRentiva
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Template variables are passed from VehicleMeta::prepare_template_data()
// $post, $available, $features, $equipment, $available_details, $available_features, $available_equipment, $removed_details, $custom_details, $saved_order, $saved_features_order, $saved_equipment_order, $detail_values
?>

<div class="mhm-vehicle-meta-container">
    <!-- Nonce field -->
    <?php wp_nonce_field('vehicle_mhm_rentiva_vehicle_details_nonce', 'vehicle_mhm_rentiva_vehicle_details_nonce'); ?>

    <div class="mhm-meta-content">
        <!-- Vehicle Details Section -->
        <div class="mhm-section">
            <div class="mhm-section-header">
                <h3><?php esc_html_e('VEHICLE DETAILS', 'mhm-rentiva'); ?></h3>
                <p><?php esc_html_e('Enter the basic information of the vehicle', 'mhm-rentiva'); ?></p>
            </div>

            <div class="mhm-details-grid" id="details-grid">
                <!-- Availability Status - First box -->
                <div class="mhm-detail-item availability-status" data-detail-key="availability">
                    <div class="mhm-detail-content">
                        <label class="mhm-detail-label"><?php esc_html_e('Availability Status', 'mhm-rentiva'); ?></label>
                        <select id="_mhm_vehicle_status" name="_mhm_vehicle_status" class="mhm-availability-dropdown">
                            <option value="active" <?php selected($available, 'active'); ?> data-icon="✅"><?php esc_html_e('Active', 'mhm-rentiva'); ?></option>
                            <option value="maintenance" <?php selected($available, 'maintenance'); ?> data-icon="🔧"><?php esc_html_e('Maintenance', 'mhm-rentiva'); ?></option>
                        </select>
                    </div>
                </div>
                
                <?php
                // Auto-sync saved order with available details
                if ($saved_order && is_array($saved_order)) {
                    // Keep only existing details
                    $synced_order = [];
                    foreach ($saved_order as $key) {
                        if (isset($available_details[$key])) {
                            $synced_order[] = $key;
                        }
                    }
                    
                    // Add missing details
                    foreach ($available_details as $key => $label) {
                        if (!in_array($key, $synced_order)) {
                            $synced_order[] = $key;
                        }
                    }
                    
                    // Save synchronized order
                    if ($synced_order !== $saved_order) {
                        update_post_meta($post->ID, '_mhm_details_order', $synced_order);
                        $saved_order = $synced_order;
                    }
                    
                    // Render according to order
                    foreach ($saved_order as $key) {
                        if (!isset($available_details[$key])) continue;
                        if ($key === 'availability') continue; // Availability status already rendered
                        
                        $label = $available_details[$key];
                        $value = $detail_values[$key] ?? ''; // Use prepared value (no N+1 query)
                        
                        echo '<div class="mhm-detail-item" data-detail-key="' . esc_attr($key) . '">';
                        echo '<div class="mhm-detail-content">';
                        echo '<label class="mhm-detail-label">' . esc_html($label) . '</label>';
                        
                        if ($key === 'price_per_day') {
                            echo '<input type="number" id="mhm_rentiva_' . esc_attr($key) . '" name="mhm_rentiva_' . esc_attr($key) . '" value="' . esc_attr($value) . '" min="0" step="1" placeholder="0" class="mhm-detail-input" />';
                            echo '<span class="mhm-detail-unit">' . esc_html(\MHMRentiva\Admin\Reports\Reports::get_currency_symbol()) . '</span>';
                        } elseif ($key === 'seats') {
                            // ⭐ Get max seats from settings (default: 100)
                            $max_seats = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_max_seats', 100);
                            echo '<input type="number" id="mhm_rentiva_' . esc_attr($key) . '" name="mhm_rentiva_' . esc_attr($key) . '" value="' . esc_attr($value ?: '5') . '" min="1" max="' . esc_attr($max_seats) . '" placeholder="5" class="mhm-detail-input" />';
                            echo '<span class="mhm-detail-unit">' . esc_html__('Person', 'mhm-rentiva') . '</span>';
                        } elseif ($key === 'doors') {
                            // ⭐ Get max doors from settings (default: 20)
                            $max_doors = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_max_doors', 20);
                            echo '<input type="number" id="mhm_rentiva_' . esc_attr($key) . '" name="mhm_rentiva_' . esc_attr($key) . '" value="' . esc_attr($value ?: '4') . '" min="2" max="' . esc_attr($max_doors) . '" placeholder="4" class="mhm-detail-input" />';
                            echo '<span class="mhm-detail-unit">' . esc_html__('Pieces', 'mhm-rentiva') . '</span>';
                        } elseif ($key === 'engine_size') {
                            // ⭐ Get engine size limits from settings (default: 0.0-20.0L)
                            $min_engine = (float) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_min_engine_size', 0.0);
                            $max_engine = (float) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_max_engine_size', 20.0);
                            echo '<input type="number" id="mhm_rentiva_' . esc_attr($key) . '" name="mhm_rentiva_' . esc_attr($key) . '" value="' . esc_attr($value) . '" min="' . esc_attr($min_engine) . '" max="' . esc_attr($max_engine) . '" step="0.1" placeholder="1.6" class="mhm-detail-input" />';
                            echo '<span class="mhm-detail-unit">' . esc_html__('L', 'mhm-rentiva') . '</span>';
                        } elseif ($key === 'transmission') {
                            // ⭐ Get transmission types dynamically
                            $transmission_types = \MHMRentiva\Admin\Vehicle\Meta\VehicleMeta::get_transmission_types();
                            echo '<select id="mhm_rentiva_' . esc_attr($key) . '" name="mhm_rentiva_' . esc_attr($key) . '" class="mhm-detail-select">';
                            foreach ($transmission_types as $type_key => $type_label) {
                                echo '<option value="' . esc_attr($type_key) . '"' . selected($value, $type_key, false) . '>' . esc_html($type_label) . '</option>';
                            }
                            echo '</select>';
                        } elseif ($key === 'fuel_type') {
                            // ⭐ Get fuel types dynamically
                            $fuel_types = \MHMRentiva\Admin\Vehicle\Meta\VehicleMeta::get_fuel_types();
                            echo '<select id="mhm_rentiva_' . esc_attr($key) . '" name="mhm_rentiva_' . esc_attr($key) . '" class="mhm-detail-select">';
                            foreach ($fuel_types as $fuel_key => $fuel_label) {
                                echo '<option value="' . esc_attr($fuel_key) . '"' . selected($value, $fuel_key, false) . '>' . esc_html($fuel_label) . '</option>';
                            }
                            echo '</select>';
                        } else {
                            echo '<input type="text" id="mhm_rentiva_' . esc_attr($key) . '" name="mhm_rentiva_' . esc_attr($key) . '" value="' . esc_attr($value) . '" placeholder="' . esc_attr($label) . '" class="mhm-detail-input" />';
                        }
                        
                        echo '</div>';
                        echo '</div>';
                    }
                } else {
                    // Fallback: render according to available_details order if saved_order doesn't exist
                    foreach ($available_details as $key => $label) {
                        if ($key === 'availability') continue; // Availability status already rendered
                        
                        $value = $detail_values[$key] ?? ''; // Use prepared value
                        
                        echo '<div class="mhm-detail-item" data-detail-key="' . esc_attr($key) . '">';
                        echo '<div class="mhm-detail-content">';
                        echo '<label class="mhm-detail-label">' . esc_html($label) . '</label>';
                        
                        if ($key === 'price_per_day') {
                            echo '<input type="number" id="mhm_rentiva_' . esc_attr($key) . '" name="mhm_rentiva_' . esc_attr($key) . '" value="' . esc_attr($value) . '" min="0" step="1" placeholder="0" class="mhm-detail-input" />';
                            echo '<span class="mhm-detail-unit">' . esc_html(\MHMRentiva\Admin\Reports\Reports::get_currency_symbol()) . '</span>';
                        } elseif ($key === 'seats') {
                            echo '<input type="number" id="mhm_rentiva_' . esc_attr($key) . '" name="mhm_rentiva_' . esc_attr($key) . '" value="' . esc_attr($value ?: '5') . '" min="1" max="20" placeholder="5" class="mhm-detail-input" />';
                            echo '<span class="mhm-detail-unit">' . esc_html__('Person', 'mhm-rentiva') . '</span>';
                        } elseif ($key === 'doors') {
                            echo '<input type="number" id="mhm_rentiva_' . esc_attr($key) . '" name="mhm_rentiva_' . esc_attr($key) . '" value="' . esc_attr($value ?: '4') . '" min="2" max="8" placeholder="4" class="mhm-detail-input" />';
                            echo '<span class="mhm-detail-unit">' . esc_html__('Pieces', 'mhm-rentiva') . '</span>';
                        } elseif ($key === 'engine_size') {
                            echo '<input type="number" id="mhm_rentiva_' . esc_attr($key) . '" name="mhm_rentiva_' . esc_attr($key) . '" value="' . esc_attr($value) . '" min="0.8" max="8.0" step="0.1" placeholder="1.6" class="mhm-detail-input" />';
                            echo '<span class="mhm-detail-unit">' . esc_html__('L', 'mhm-rentiva') . '</span>';
                        } elseif ($key === 'transmission') {
                            echo '<select id="mhm_rentiva_' . esc_attr($key) . '" name="mhm_rentiva_' . esc_attr($key) . '" class="mhm-detail-select">';
                            echo '<option value="auto"' . selected($value, 'auto', false) . '>' . esc_html__('Automatic', 'mhm-rentiva') . '</option>';
                            echo '<option value="manual"' . selected($value, 'manual', false) . '>' . esc_html__('Manual', 'mhm-rentiva') . '</option>';
                            echo '</select>';
                        } elseif ($key === 'fuel_type') {
                            echo '<select id="mhm_rentiva_' . esc_attr($key) . '" name="mhm_rentiva_' . esc_attr($key) . '" class="mhm-detail-select">';
                            echo '<option value="petrol"' . selected($value, 'petrol', false) . '>' . esc_html__('Petrol', 'mhm-rentiva') . '</option>';
                            echo '<option value="diesel"' . selected($value, 'diesel', false) . '>' . esc_html__('Diesel', 'mhm-rentiva') . '</option>';
                            echo '<option value="hybrid"' . selected($value, 'hybrid', false) . '>' . esc_html__('Hybrid', 'mhm-rentiva') . '</option>';
                            echo '<option value="electric"' . selected($value, 'electric', false) . '>' . esc_html__('Electric', 'mhm-rentiva') . '</option>';
                            echo '</select>';
                        } else {
                            echo '<input type="text" id="mhm_rentiva_' . esc_attr($key) . '" name="mhm_rentiva_' . esc_attr($key) . '" value="' . esc_attr($value) . '" placeholder="' . esc_attr($label) . '" class="mhm-detail-input" />';
                        }
                        
                        echo '</div>';
                        echo '</div>';
                    }
                }
                ?>
                
            </div>
        </div>

        <!-- Vehicle Features Section -->
        <div class="mhm-section">
            <div class="mhm-section-header">
                <h3><?php esc_html_e('VEHICLE FEATURES', 'mhm-rentiva'); ?></h3>
                <p><?php esc_html_e('Select the features of the vehicle', 'mhm-rentiva'); ?></p>
            </div>

            <div class="mhm-features-grid" id="features-grid">
                <?php
                // Check saved features order
                if ($saved_features_order && is_array($saved_features_order)) {
                    // Keep only existing features
                    $synced_features_order = [];
                    foreach ($saved_features_order as $key) {
                        if (isset($available_features[$key])) {
                            $synced_features_order[] = $key;
                        }
                    }
                    
                    // Add missing features
                    foreach ($available_features as $key => $label) {
                        if (!in_array($key, $synced_features_order)) {
                            $synced_features_order[] = $key;
                        }
                    }
                    
                    // Save synchronized order
                    if ($synced_features_order !== $saved_features_order) {
                        update_post_meta($post->ID, '_mhm_features_order', $synced_features_order);
                        $saved_features_order = $synced_features_order;
                    }
                    
                    // Render according to order
                    foreach ($saved_features_order as $key) {
                        if (!isset($available_features[$key])) continue;
                        
                        $label = $available_features[$key];
                        $checked = in_array($key, $features) ? 'checked' : '';
                        
                        echo '<div class="mhm-feature-item" data-feature-key="' . esc_attr($key) . '">';
                        echo '<label class="mhm-feature-label">';
                        echo '<input type="checkbox" name="mhm_rentiva_features[]" value="' . esc_attr($key) . '" ' . $checked . ' />';
                        echo '<span>' . esc_html($label) . '</span>';
                        echo '</label>';
                        echo '</div>';
                    }
                } else {
                    // Fallback: render according to available_features order if saved_order doesn't exist
                    foreach ($available_features as $key => $label) {
                        $checked = in_array($key, $features) ? 'checked' : '';
                        
                        echo '<div class="mhm-feature-item" data-feature-key="' . esc_attr($key) . '">';
                        echo '<label class="mhm-feature-label">';
                        echo '<input type="checkbox" name="mhm_rentiva_features[]" value="' . esc_attr($key) . '" ' . $checked . ' />';
                        echo '<span>' . esc_html($label) . '</span>';
                        echo '</label>';
                        echo '</div>';
                    }
                }
                ?>
            </div>
        </div>

        <!-- Vehicle Equipment Section -->
        <div class="mhm-section">
            <div class="mhm-section-header">
                <h3><?php esc_html_e('VEHICLE EQUIPMENT', 'mhm-rentiva'); ?></h3>
                <p><?php esc_html_e('Select the equipment of the vehicle', 'mhm-rentiva'); ?></p>
            </div>

            <div class="mhm-equipment-grid" id="equipment-grid">
                <?php
                // Check saved equipment order
                if ($saved_equipment_order && is_array($saved_equipment_order)) {
                    // Keep only existing equipment
                    $synced_equipment_order = [];
                    foreach ($saved_equipment_order as $key) {
                        if (isset($available_equipment[$key])) {
                            $synced_equipment_order[] = $key;
                        }
                    }
                    
                    // Add missing equipment
                    foreach ($available_equipment as $key => $label) {
                        if (!in_array($key, $synced_equipment_order)) {
                            $synced_equipment_order[] = $key;
                        }
                    }
                    
                    // Save synchronized order
                    if ($synced_equipment_order !== $saved_equipment_order) {
                        update_post_meta($post->ID, '_mhm_equipment_order', $synced_equipment_order);
                        $saved_equipment_order = $synced_equipment_order;
                    }
                    
                    // Render according to order
                    foreach ($saved_equipment_order as $key) {
                        if (!isset($available_equipment[$key])) continue;
                        
                        $label = $available_equipment[$key];
                        $checked = in_array($key, $equipment) ? 'checked' : '';
                        
                        echo '<div class="mhm-equipment-item" data-equipment-key="' . esc_attr($key) . '">';
                        echo '<label class="mhm-equipment-label">';
                        echo '<input type="checkbox" name="mhm_rentiva_equipment[]" value="' . esc_attr($key) . '" ' . $checked . ' />';
                        echo '<span>' . esc_html($label) . '</span>';
                        echo '</label>';
                        echo '</div>';
                    }
                } else {
                    // Fallback: render according to available_equipment order if saved_order doesn't exist
                    foreach ($available_equipment as $key => $label) {
                        $checked = in_array($key, $equipment) ? 'checked' : '';
                        
                        echo '<div class="mhm-equipment-item" data-equipment-key="' . esc_attr($key) . '">';
                        echo '<label class="mhm-equipment-label">';
                        echo '<input type="checkbox" name="mhm_rentiva_equipment[]" value="' . esc_attr($key) . '" ' . $checked . ' />';
                        echo '<span>' . esc_html($label) . '</span>';
                        echo '</label>';
                        echo '</div>';
                    }
                }
                ?>
            </div>
        </div>
        
    </div>
</div>

<!-- Send JavaScript data -->
<script type="text/javascript">
window.availableVehicleDetails = <?php echo json_encode($available_details); ?>;
window.availableVehicleFeatures = <?php echo json_encode($available_features); ?>;
window.availableVehicleEquipment = <?php echo json_encode($available_equipment); ?>;
</script>