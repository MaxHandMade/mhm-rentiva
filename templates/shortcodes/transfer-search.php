<?php

/**
 * Template: Transfer Search Form
 * 
 * Used by [rentiva_transfer_search] shortcode.
 * 
 * @var array $locations available locations
 * @var array $atts      shortcode attributes
 */

if (! defined('ABSPATH')) {
    exit;
}

$locations = $locations ?? array();
$uid = uniqid('rv_transfer_search_');
?>

<div id="<?php echo esc_attr($uid); ?>" class="rv-transfer-search-container">
    <form class="rv-unified-search__form js-unified-transfer-form"
        action="<?php echo esc_url(\MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_transfer_results')); ?>"
        method="GET"
        id="mhm-transfer-search-form-<?php echo esc_attr($uid); ?>">

        <div class="rv-unified-search__group">
            <div class="rv-unified-search__field">
                <label class="rv-label"><?php esc_html_e('Pickup Location', 'mhm-rentiva'); ?></label>
                <div class="rv-input-wrapper">
                    <span class="rv-icon-marker dashicons dashicons-location"></span>
                    <select name="origin_id" required class="rv-select" title="<?php esc_attr_e('Select Location', 'mhm-rentiva'); ?>">
                        <option value=""><?php esc_html_e('Select Location', 'mhm-rentiva'); ?></option>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?php echo esc_attr((string)$loc->id); ?>" title="<?php echo esc_attr($loc->name); ?>"><?php echo esc_html($loc->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="rv-unified-search__field">
                <label class="rv-label"><?php esc_html_e('Dropoff Location', 'mhm-rentiva'); ?></label>
                <div class="rv-input-wrapper">
                    <span class="rv-icon-marker dashicons dashicons-location"></span>
                    <select name="destination_id" required class="rv-select" title="<?php esc_attr_e('Select Location', 'mhm-rentiva'); ?>">
                        <option value=""><?php esc_html_e('Select Location', 'mhm-rentiva'); ?></option>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?php echo esc_attr((string)$loc->id); ?>" title="<?php echo esc_attr($loc->name); ?>"><?php echo esc_html($loc->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="rv-unified-search__group rv-unified-search__group--mobile-grid">
            <div class="rv-unified-search__field">
                <label class="rv-label"><?php esc_html_e('Date', 'mhm-rentiva'); ?></label>
                <div class="rv-input-wrapper">
                    <span class="rv-icon-calendar dashicons dashicons-calendar-alt"></span>
                    <input type="text" name="date" class="rv-input js-datepicker" placeholder="<?php esc_attr_e('Select Date', 'mhm-rentiva'); ?>" required autocomplete="off">
                </div>
            </div>
            <div class="rv-unified-search__field rv-unified-search__field--time">
                <label class="rv-label"><?php esc_html_e('Time', 'mhm-rentiva'); ?></label>
                <div class="rv-input-wrapper">
                    <span class="rv-icon-clock dashicons dashicons-clock"></span>
                    <select name="time" class="rv-select">
                        <?php for ($i = 0; $i < 24; $i++): ?>
                            <?php $time_hour = sprintf('%02d:00', $i); ?>
                            <option value="<?php echo esc_attr($time_hour); ?>" <?php selected($i, 10); ?>>
                                <?php echo esc_html($time_hour); ?>
                            </option>
                            <?php $time_half = sprintf('%02d:30', $i); ?>
                            <option value="<?php echo esc_attr($time_half); ?>">
                                <?php echo esc_html($time_half); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="rv-unified-search__group rv-unified-search__group--pax rv-unified-search__group--mobile-grid">
            <div class="rv-unified-search__field">
                <label class="rv-label"><?php esc_html_e('Adults', 'mhm-rentiva'); ?></label>
                <div class="rv-input-wrapper">
                    <span class="rv-icon dashicons dashicons-admin-users"></span>
                    <input type="number" name="adults" value="1" min="1" class="rv-input">
                </div>
            </div>
            <div class="rv-unified-search__field">
                <label class="rv-label"><?php esc_html_e('Children', 'mhm-rentiva'); ?></label>
                <div class="rv-input-wrapper">
                    <span class="rv-icon dashicons dashicons-admin-users"></span>
                    <input type="number" name="children" value="0" min="0" class="rv-input">
                </div>
            </div>
        </div>

        <div class="rv-unified-search__group rv-unified-search__group--luggage rv-unified-search__group--mobile-grid">
            <div class="rv-unified-search__field">
                <label class="rv-label"><?php esc_html_e('Big Bags', 'mhm-rentiva'); ?> <span class="required" style="color:red;">(*)</span></label>
                <div class="rv-input-wrapper">
                    <span class="rv-icon dashicons dashicons-portfolio"></span>
                    <input type="number" name="luggage_big" value="0" min="0" class="rv-input" required>
                </div>
            </div>
            <div class="rv-unified-search__field">
                <label class="rv-label"><?php esc_html_e('Small Bags', 'mhm-rentiva'); ?> <span class="required" style="color:red;">(*)</span></label>
                <div class="rv-input-wrapper">
                    <span class="rv-icon dashicons dashicons-portfolio"></span>
                    <input type="number" name="luggage_small" value="0" min="0" class="rv-input" required>
                </div>
            </div>
        </div>

        <div class="rv-unified-search__action">
            <button type="submit" class="rv-btn rv-btn--primary">
                <span class="rv-icon-search dashicons dashicons-search"></span>
                <?php esc_html_e('Search Transfer', 'mhm-rentiva'); ?>
            </button>
        </div>
    </form>
</div>
