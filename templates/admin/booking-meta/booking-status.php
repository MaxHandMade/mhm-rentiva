<?php
/**
 * Booking Status Template
 * 
 * @var string $current_status
 * @var array $statuses
 * @var int $vehicle_id
 * @var string $vehicle_title
 * @var string $vehicle_link
 * @var string $customer_name
 * @var string $customer_phone
 * @var string $customer_email
 * @var string $pickup_date
 * @var string $pickup_time
 * @var string $dropoff_date
 * @var string $dropoff_time
 * @var int $guests
 * @var string $payment_method
 * @var string $notes
 * @var int $rental_days
 * @var float $total_price
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
?>

<div class="mhm-rentiva-wrap">
    <?php wp_nonce_field('mhm_rentiva_booking_meta', 'mhm_booking_meta_nonce'); ?>
    
    <!-- Status Selection -->
    <div>
        <label for="mhm_booking_status_main"><?php esc_html_e('Status', 'mhm-rentiva'); ?></label>
        <select id="mhm_booking_status_main" name="mhm_booking_status" data-current-status="<?php echo esc_attr($current_status); ?>">
            <?php foreach ($statuses as $status): ?>
                <option value="<?php echo esc_attr($status['value']); ?>" <?php selected($current_status, $status['value']); ?>>
                    <?php echo esc_html($status['label']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Booking Summary -->
    <div class="booking-summary">
        <h4><?php esc_html_e('Booking Summary', 'mhm-rentiva'); ?></h4>
        
        <?php if ($vehicle_id): ?>
            <p>
                <strong><?php esc_html_e('Vehicle:', 'mhm-rentiva'); ?></strong>
                <?php if ($vehicle_link): ?>
                    <a href="<?php echo esc_url($vehicle_link); ?>" target="_blank">
                        <?php echo esc_html($vehicle_title); ?>
                    </a>
                <?php else: ?>
                    <?php echo esc_html($vehicle_title); ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>

    <!-- Customer Information -->
    <div class="edit-section">
        <h5><?php esc_html_e('Customer Information', 'mhm-rentiva'); ?></h5>
        
        <div class="field-row">
            <div class="field-group">
                <label for="mhm_edit_customer_name"><?php esc_html_e('Customer Name', 'mhm-rentiva'); ?></label>
                <input type="text" id="mhm_edit_customer_name" name="mhm_edit_customer_name" 
                       value="<?php echo esc_attr($customer_name); ?>" class="regular-text">
            </div>
            
            <div class="field-group">
                <label for="mhm_edit_customer_phone"><?php esc_html_e('Phone', 'mhm-rentiva'); ?></label>
                <input type="text" id="mhm_edit_customer_phone" name="mhm_edit_customer_phone" 
                       value="<?php echo esc_attr($customer_phone); ?>" class="regular-text">
            </div>
        </div>
        
        <div class="field-group">
            <label for="mhm_edit_customer_email"><?php esc_html_e('Email', 'mhm-rentiva'); ?></label>
            <input type="email" id="mhm_edit_customer_email" name="mhm_edit_customer_email" 
                   value="<?php echo esc_attr($customer_email); ?>" class="regular-text">
        </div>
    </div>

    <!-- Booking Details -->
    <div class="edit-section">
        <h5><?php esc_html_e('Booking Details', 'mhm-rentiva'); ?></h5>
        
        <div class="field-row">
            <div class="field-group">
                <label for="mhm_edit_pickup_date"><?php esc_html_e('Pickup Date', 'mhm-rentiva'); ?></label>
                <input type="date" id="mhm_edit_pickup_date" name="mhm_edit_pickup_date" 
                       value="<?php echo esc_attr($pickup_date); ?>" class="regular-text">
            </div>
            
            <div class="field-group">
                <label for="mhm_edit_pickup_time"><?php esc_html_e('Pickup Time', 'mhm-rentiva'); ?></label>
                <input type="time" id="mhm_edit_pickup_time" name="mhm_edit_pickup_time" 
                       value="<?php echo esc_attr($pickup_time); ?>" class="regular-text">
            </div>
        </div>
        
        <div class="field-row">
            <div class="field-group">
                <label for="mhm_edit_dropoff_date"><?php esc_html_e('Return Date', 'mhm-rentiva'); ?></label>
                <input type="date" id="mhm_edit_dropoff_date" name="mhm_edit_dropoff_date" 
                       value="<?php echo esc_attr($dropoff_date); ?>" class="regular-text">
            </div>
            
            <div class="field-group">
                <label for="mhm_edit_dropoff_time"><?php esc_html_e('Return Time', 'mhm-rentiva'); ?></label>
                <input type="time" id="mhm_edit_dropoff_time" name="mhm_edit_dropoff_time" 
                       value="<?php echo esc_attr($dropoff_time); ?>" class="regular-text">
            </div>
        </div>
        
        <div class="field-row">
            <div class="field-group">
                <label for="mhm_edit_guests"><?php esc_html_e('Number of Guests', 'mhm-rentiva'); ?></label>
                <input type="number" id="mhm_edit_guests" name="mhm_edit_guests" 
                       value="<?php echo esc_attr($guests); ?>" min="1" max="10" class="small-text">
            </div>
            
            <div class="field-group">
                <label for="mhm_edit_payment_method"><?php esc_html_e('Payment Method', 'mhm-rentiva'); ?></label>
                <select id="mhm_edit_payment_method" name="mhm_edit_payment_method" class="regular-text">
                    <option value="offline" <?php selected($payment_method, 'offline'); ?>>
                        <?php esc_html_e('Offline', 'mhm-rentiva'); ?>
                    </option>
                    <option value="online" <?php selected($payment_method, 'online'); ?>>
                        <?php esc_html_e('Online', 'mhm-rentiva'); ?>
                    </option>
                </select>
            </div>
        </div>
        
        <div class="field-group">
            <label for="mhm_edit_notes"><?php esc_html_e('Notes', 'mhm-rentiva'); ?></label>
            <textarea id="mhm_edit_notes" name="mhm_edit_notes" rows="3" class="large-text">
                <?php echo esc_textarea($notes); ?>
            </textarea>
        </div>
    </div>

    <!-- Read-only Summary -->
    <div class="readonly-section">
        <h5><?php esc_html_e('Booking Summary', 'mhm-rentiva'); ?></h5>
        
        <?php if ($vehicle_id): ?>
            <p>
                <strong><?php esc_html_e('Vehicle:', 'mhm-rentiva'); ?></strong>
                <?php if ($vehicle_link): ?>
                    <a href="<?php echo esc_url($vehicle_link); ?>" target="_blank">
                        <?php echo esc_html($vehicle_title); ?>
                    </a>
                <?php else: ?>
                    <?php echo esc_html($vehicle_title); ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>
        
        <?php if ($rental_days > 0): ?>
            <p>
                <strong><?php esc_html_e('Days:', 'mhm-rentiva'); ?></strong> 
                <span id="mhm_rental_days_display"><?php echo esc_html((string) $rental_days); ?></span>
            </p>
        <?php endif; ?>
        
        <?php if ($total_price > 0): ?>
            <p>
                <strong><?php esc_html_e('Total:', 'mhm-rentiva'); ?></strong> 
                <span id="mhm_total_price_display"><?php echo esc_html($total_price_formatted ?? number_format_i18n($total_price, 2)); ?></span>
            </p>
        <?php endif; ?>
    </div>
</div>
