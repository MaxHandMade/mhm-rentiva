<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

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

use MHMRentiva\Helpers\Icons;

$locations    = $locations ?? array();
$atts         = $atts ?? array();
$layout       = $atts['layout'] ?? 'horizontal';
$button_text  = $atts['button_text'] ?? '';
$show_pickup  = (bool) ($atts['show_pickup'] ?? true);
$show_dropoff = (bool) ($atts['show_dropoff'] ?? true);
$uid          = uniqid('rv_transfer_search_');

$container_class = 'rv-transfer-search mhm-premium-search mhm-premium-transfer-search rv-layout-' . esc_attr($layout);
if (! empty($atts['class'])) {
    $container_class .= ' ' . esc_attr($atts['class']);
}
?>

<div id="<?php echo esc_attr($uid); ?>" class="<?php echo esc_attr($container_class); ?>">
    <div class="mhm-tabs">
        <div role="tab" aria-selected="true" class="mhm-tab is-active">
            <?php esc_html_e('Airport Transfer', 'mhm-rentiva'); ?>
        </div>
    </div>

    <form class="rv-unified-search__form js-unified-transfer-form"
        action="<?php echo esc_url(\MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_transfer_results')); ?>"
        method="GET"
        id="mhm-transfer-search-form-<?php echo esc_attr($uid); ?>"
        data-testid="transfer-search-form">

        <div class="rv-unified-search__group mhm-form-grid">
            <?php if ($show_pickup) : ?>
                <div class="rv-unified-search__field">
                    <label class="rv-label"><?php esc_html_e('Pickup Location', 'mhm-rentiva'); ?></label>
                    <div class="rv-input-wrapper">
                        <?php Icons::render('location', array('class' => 'rv-icon-marker')); ?>
                        <select name="origin_id" id="mhm-origin" required class="rv-select" title="<?php esc_attr_e('Select Location', 'mhm-rentiva'); ?>" data-testid="transfer-origin">
                            <option value=""><?php esc_html_e('Select Location', 'mhm-rentiva'); ?></option>
                            <?php foreach ($locations as $loc) : ?>
                                <option value="<?php echo esc_attr((string) $loc->id); ?>" title="<?php echo esc_attr($loc->name); ?>"><?php echo esc_html($loc->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($show_dropoff) : ?>
                <div class="rv-unified-search__field">
                    <label class="rv-label"><?php esc_html_e('Dropoff Location', 'mhm-rentiva'); ?></label>
                    <div class="rv-input-wrapper">
                        <?php Icons::render('location', array('class' => 'rv-icon-marker')); ?>
                        <select name="destination_id" id="mhm-destination" required class="rv-select" title="<?php esc_attr_e('Select Location', 'mhm-rentiva'); ?>" data-testid="transfer-destination">
                            <option value=""><?php esc_html_e('Select Location', 'mhm-rentiva'); ?></option>
                            <?php foreach ($locations as $loc) : ?>
                                <option value="<?php echo esc_attr((string) $loc->id); ?>" title="<?php echo esc_attr($loc->name); ?>"><?php echo esc_html($loc->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="rv-unified-search__group mhm-form-grid mhm-row-date-time">
            <div class="rv-unified-search__field">
                <label class="rv-label"><?php esc_html_e('Date', 'mhm-rentiva'); ?></label>
                <div class="rv-input-wrapper">
                    <?php Icons::render('calendar', array('class' => 'rv-icon-calendar')); ?>
                    <input type="text" name="date" class="rv-input js-datepicker" placeholder="<?php esc_attr_e('Select Date', 'mhm-rentiva'); ?>" required autocomplete="off" data-testid="transfer-date">
                </div>
            </div>
            <div class="rv-unified-search__field rv-unified-search__field--time">
                <label class="rv-label"><?php esc_html_e('Time', 'mhm-rentiva'); ?></label>
                <div class="rv-input-wrapper">
                    <?php Icons::render('clock', array('class' => 'rv-icon-clock')); ?>
                    <select name="time" class="rv-select">
                        <?php for ($i = 0; $i < 24; $i++) : ?>
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

        <div class="rv-unified-search__group mhm-form-grid mhm-row-passengers">
            <div class="rv-unified-search__field">
                <label class="rv-label"><?php esc_html_e('Adults', 'mhm-rentiva'); ?></label>
                <div class="rv-input-wrapper">
                    <?php Icons::render('users', array('class' => 'rv-icon')); ?>
                    <input type="number" name="adults" value="1" min="1" class="rv-input">
                </div>
            </div>
            <div class="rv-unified-search__field">
                <label class="rv-label"><?php esc_html_e('Children', 'mhm-rentiva'); ?></label>
                <div class="rv-input-wrapper">
                    <?php Icons::render('users', array('class' => 'rv-icon')); ?>
                    <input type="number" name="children" value="0" min="0" class="rv-input">
                </div>
            </div>
        </div>

        <div class="rv-unified-search__group mhm-form-grid mhm-row-bags">
            <div class="rv-unified-search__field">
                <label class="rv-label"><?php esc_html_e('Big Bags', 'mhm-rentiva'); ?> <span class="required" style="color:red;">(*)</span></label>
                <div class="rv-input-wrapper">
                    <?php Icons::render('luggage', array('class' => 'rv-icon')); ?>
                    <input type="number" name="luggage_big" value="0" min="0" class="rv-input" required>
                </div>
            </div>
            <div class="rv-unified-search__field">
                <label class="rv-label"><?php esc_html_e('Small Bags', 'mhm-rentiva'); ?> <span class="required" style="color:red;">(*)</span></label>
                <div class="rv-input-wrapper">
                    <?php Icons::render('luggage', array('class' => 'rv-icon')); ?>
                    <input type="number" name="luggage_small" value="0" min="0" class="rv-input" required>
                </div>
            </div>
        </div>

        <div class="rv-unified-search__action">
            <button type="submit" class="rv-btn rv-btn--primary" data-testid="search-submit-transfer">
                <?php Icons::render('search', array('class' => 'rv-icon-search')); ?>
                <?php echo ! empty($button_text) ? esc_html($button_text) : esc_html__('Search Transfer', 'mhm-rentiva'); ?>
            </button>
        </div>

        <div class="rv-unified-search__luggage-info">
            <p class="rv-info-item">
                <span class="rv-bullet">*</span>
                <strong><?php esc_html_e('Small Luggage:', 'mhm-rentiva'); ?></strong>
                <?php esc_html_e('Handbag, backpack or cabin size suitcase.', 'mhm-rentiva'); ?>
            </p>
            <p class="rv-info-item">
                <span class="rv-bullet">*</span>
                <strong><?php esc_html_e('Big Luggage:', 'mhm-rentiva'); ?></strong>
                <?php esc_html_e('Medium or large check-in suitcase.', 'mhm-rentiva'); ?>
            </p>
        </div>
    </form>
</div>