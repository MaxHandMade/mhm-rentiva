<?php

/**
 * Transfer Search Shortcode Template
 *
 * @var array $data {
 *     @var array $locations Array of location objects with id, name, type.
 * }
 */

if (! defined('ABSPATH')) {
    exit;
}

$locations = $locations ?? array();
?>

<div class="mhm-transfer-search-wrapper">
    <form id="mhm-transfer-search-form">
        <div class="mhm-transfer-form-row">
            <div class="mhm-transfer-form-group">
                <label for="mhm-origin"><?php esc_html_e('Pickup Location', 'mhm-rentiva'); ?></label>
                <select name="origin_id" id="mhm-origin" required>
                    <option value=""><?php esc_html_e('Select Location', 'mhm-rentiva'); ?></option>
                    <?php foreach ($locations as $loc) : ?>
                        <option value="<?php echo esc_attr((string) $loc->id); ?>"><?php echo esc_html($loc->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mhm-transfer-form-group">
                <label for="mhm-destination"><?php esc_html_e('Dropoff Location', 'mhm-rentiva'); ?></label>
                <select name="destination_id" id="mhm-destination" required>
                    <option value=""><?php esc_html_e('Select Location', 'mhm-rentiva'); ?></option>
                    <?php foreach ($locations as $loc) : ?>
                        <option value="<?php echo esc_attr((string) $loc->id); ?>"><?php echo esc_html($loc->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="mhm-transfer-form-row">
            <div class="mhm-transfer-form-group">
                <label for="mhm-date"><?php esc_html_e('Date', 'mhm-rentiva'); ?></label>
                <input type="date" name="date" id="mhm-date" required min="<?php echo esc_attr(gmdate('Y-m-d')); ?>">
            </div>
            <div class="mhm-transfer-form-group">
                <label for="mhm-time"><?php esc_html_e('Time', 'mhm-rentiva'); ?></label>
                <input type="time" name="time" id="mhm-time" required>
            </div>
        </div>

        <div class="mhm-transfer-form-row">
            <div class="mhm-transfer-form-group mhm-half">
                <label><?php esc_html_e('Adults', 'mhm-rentiva'); ?></label>
                <input type="number" name="adults" value="1" min="1" required>
            </div>
            <div class="mhm-transfer-form-group mhm-half">
                <label><?php esc_html_e('Children', 'mhm-rentiva'); ?></label>
                <input type="number" name="children" value="0" min="0">
            </div>
            <div class="mhm-transfer-form-group mhm-half">
                <label><?php esc_html_e('Big Bags', 'mhm-rentiva'); ?> <span style="color:red;">(*)</span></label>
                <input type="number" name="luggage_big" value="0" min="0">
            </div>
            <div class="mhm-transfer-form-group mhm-half">
                <label><?php esc_html_e('Small Bags', 'mhm-rentiva'); ?> <span style="color:red;">(*)</span></label>
                <input type="number" name="luggage_small" value="0" min="0">
            </div>
        </div>

        <div class="mhm-transfer-form-submit">
            <button type="submit" class="mhm-transfer-btn"><?php esc_html_e('Search Transfer', 'mhm-rentiva'); ?></button>
        </div>

        <div class="mhm-transfer-luggage-info mt-3" style="font-size: 0.85rem; color: #6c757d; line-height: 1.4; margin-top: 15px;">
            <p class="mb-1" style="margin-bottom: 5px;">
                <strong class="text-danger" style="color:red;">*</strong>
                <strong><?php esc_html_e('Small Luggage:', 'mhm-rentiva'); ?></strong>
                <?php esc_html_e('Handbag, backpack or cabin size suitcase.', 'mhm-rentiva'); ?>
            </p>
            <p class="mb-0" style="margin-bottom: 0;">
                <strong class="text-danger" style="color:red;">*</strong>
                <strong><?php esc_html_e('Big Luggage:', 'mhm-rentiva'); ?></strong>
                <?php esc_html_e('Medium or large check-in suitcase.', 'mhm-rentiva'); ?>
            </p>
        </div>

        <div id="mhm-transfer-loading" style="display:none; text-align:center; padding:10px;">
            <?php esc_html_e('Searching...', 'mhm-rentiva'); ?>
        </div>
    </form>

    <div id="mhm-transfer-results"></div>
</div>