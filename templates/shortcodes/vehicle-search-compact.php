<?php

/**
 * Compact Vehicle Search Template
 * Variables:
 * - array  $atts
 * - array  $form_data
 * - string $nonce_field
 */
if (! defined('ABSPATH')) {
    exit;
}

?>

<?php
$unique_id = uniqid('rv_search_compact_');

// Gutenberg style and class support
$wrapper_style = isset($atts['style']) ? $atts['style'] : '';
$wrapper_class = isset($atts['class']) ? $atts['class'] : '';

// Build robust style string combining separate attributes
if (!empty($atts['minwidth'])) $wrapper_style .= 'min-width:' . $atts['minwidth'] . ';';
if (!empty($atts['maxwidth'])) $wrapper_style .= 'max-width:' . $atts['maxwidth'] . ';';
if (!empty($atts['height']))   $wrapper_style .= 'height:' . $atts['height'] . ';';

// Final cleanup: Ensure unit suffix for numeric-only dimensions (e.g., 900 -> 900px)
if (!empty($wrapper_style)) {
    $wrapper_style = preg_replace('/(width|height|min-width|max-width):\s*(\d+)(?![\w%])/', '$1:$2px', $wrapper_style);
}
?>

<div id="<?php echo esc_attr($unique_id); ?>_wrapper"
    class="rv-search-form-compact <?php echo esc_attr($wrapper_class); ?>"
    style="<?php echo esc_attr($wrapper_style); ?>">

    <!-- Header (Hidden in compact mode but kept for accessibility) -->
    <div class="rv-search-header-compact sr-only">
        <h3><?php echo esc_html__('Find Your Vehicle', 'mhm-rentiva'); ?></h3>
    </div>

    <!-- Added js-rv-search-form class for targeting -->
    <form class="rv-search-filters-compact js-rv-search-form" id="<?php echo esc_attr($unique_id); ?>_form" method="post" data-instance-id="<?php echo esc_attr($unique_id); ?>">
        <?php echo wp_kses_post((string) $nonce_field); ?>

        <!-- Main Search Row (Horizontal) -->
        <div class="rv-search-main-row">

            <!-- Date & Time Selection Wrapper (Combined) -->
            <?php if (($atts['show_date_picker'] ?? '1') === '1') : ?>
                <div class="rv-search-field rv-search-dates">
                    <label for="<?php echo esc_attr($unique_id); ?>_pickup_date"><?php echo esc_html__('Pickup Date', 'mhm-rentiva'); ?></label>
                    <div class="rv-datetime-wrapper">
						<input type="date" id="<?php echo esc_attr($unique_id); ?>_pickup_date" name="pickup_date" class="rv-date-input js-datepicker js-start-date js-pickup-date"
							placeholder="<?php echo esc_attr__('Pickup Date', 'mhm-rentiva'); ?>"
							value="<?php echo esc_attr($form_data['pickup_date'] ?? ''); ?>" required autocomplete="off" />

                        <!-- Time Selection -->
                        <select id="<?php echo esc_attr($unique_id); ?>_pickup_time" name="pickup_time" class="rv-time-select js-pickup-time">
                            <?php
                            $start = strtotime('00:00');
                            $end   = strtotime('23:30');
                            $default_time = $form_data['pickup_time'] ?? '10:00';
                            for ($i = $start; $i <= $end; $i += 1800) {
                                $time_val   = date('H:i', $i);
                                $time_label = date_i18n(get_option('time_format'), $i);
                                $selected   = selected($time_val, $default_time, false);
                                echo '<option value="' . esc_attr($time_val) . '" ' . $selected . '>' . esc_html($time_label) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="rv-search-field rv-search-dates">
                    <label for="<?php echo esc_attr($unique_id); ?>_return_date"><?php echo esc_html__('Return Date', 'mhm-rentiva'); ?></label>
                    <div class="rv-datetime-wrapper">
						<input type="date" id="<?php echo esc_attr($unique_id); ?>_return_date" name="return_date" class="rv-date-input js-datepicker js-end-date js-return-date"
							placeholder="<?php echo esc_attr__('Return Date', 'mhm-rentiva'); ?>"
							value="<?php echo esc_attr($form_data['return_date'] ?? ''); ?>" required autocomplete="off" />

                        <!-- Time Selection (Disabled - Mirror Pickup) -->
                        <select id="<?php echo esc_attr($unique_id); ?>_return_time" class="rv-time-select rv-time-select-disabled js-return-time" disabled>
                            <?php
                            for ($i = $start; $i <= $end; $i += 1800) {
                                $time_val   = date('H:i', $i);
                                $time_label = date_i18n(get_option('time_format'), $i);
                                $selected   = selected($time_val, $default_time, false);
                                echo '<option value="' . esc_attr($time_val) . '" ' . $selected . '>' . esc_html($time_label) . '</option>';
                            }
                            ?>
                        </select>
                        <!-- Hidden input for return time -->
                        <input type="hidden" name="return_time" class="js-return-time-hidden" value="<?php echo esc_attr($default_time); ?>" />
                    </div>
                </div>
            <?php endif; ?>

            <!-- Submit Button (Inline) -->
            <div class="rv-search-field rv-search-submit">
                <label>&nbsp;</label><!-- Spacer for alignment -->
                <button type="submit" class="rv-search-btn js-search-btn">
                    <span class="text">
                        <span class="rv-search-icon">🔍</span>
                        <span class="rv-search-btn-text"><?php echo esc_html__('Search', 'mhm-rentiva'); ?></span>
                    </span>
                    <span class="loading" style="display: none;">
                        <span class="rv-spinner"></span>
                    </span>
                </button>
            </div>

        </div><!-- /.rv-search-main-row -->

    </form>

    <!-- Hidden inputs for redirect & pagination -->
    <?php if (! empty($form_data['redirect_url'])) : ?>
        <input type="hidden" class="js-redirect-url" value="<?php echo esc_url($form_data['redirect_url']); ?>" />
    <?php endif; ?>
    <input type="hidden" class="js-layout-mode" value="compact" />

</div>