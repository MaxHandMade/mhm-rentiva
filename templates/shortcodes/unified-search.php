<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * Template: Unified Search Widget
 *
	 * @var array  $locations            Location options for select dropdowns
 * @var string $wrapper_id           Unique ID for the wrapper element
 * @var bool   $show_rental_tab      Whether to show the rental tab
 * @var bool   $show_location_select Whether to show location dropdowns
 * @var bool   $show_time_select     Whether to show time selection
 * @var bool   $show_date_picker     Whether to show date picker
 * @var bool   $show_dropoff_location Whether to show drop-off location field
 * @var string $filter_categories    Comma-separated category IDs to filter
 * @var string $layout               Layout: 'horizontal', 'vertical'
 */
if (! defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Helpers\Icons;

$uid       = $wrapper_id;
$locations = $locations ?? array();

// On-the-wire names for the params this GET form puts on the search-results
// URL. Built through SearchResults::query_var() rather than typed as prefixed
// literals, so the submitted names cannot drift from the `query_vars`
// whitelist SearchResults/BookingForm read them back through.
$mhmrentiva_qv = static function (string $logical): string {
    return \MHMRentiva\Admin\Frontend\Shortcodes\SearchResults::query_var($logical);
};

// Visibility flags (default to true if not set)
$show_rental_tab       = $show_rental_tab ?? true;
$show_location_select  = $show_location_select ?? true;
$show_time_select      = $show_time_select ?? true;
$show_date_picker      = $show_date_picker ?? true;
$show_dropoff_location = $show_dropoff_location ?? true;
$fields_required       = $fields_required ?? true;
// When fields_required is false, no field should be required — override location_required too.
$location_required = $fields_required ? ( $location_required ?? true ) : false;
?>

<div id="<?php echo esc_attr($uid); ?>" class="rv-unified-search rv-unified-search--<?php echo esc_attr($layout ?? 'horizontal'); ?>" data-testid="unified-search">

    <!-- 1. Tabs Header (Show if the rental tab is enabled) -->
    <?php if ($show_rental_tab) : ?>
        <div class="rv-unified-search__header">
            <div class="rv-unified-search__tabs" role="tablist">
                <button class="rv-unified-search__tab is-active"
                    data-target="rental"
                    data-testid="tab-rental"
                    role="tab"
                    aria-controls="<?php echo esc_attr($uid); ?>_panel_rental"
                    aria-selected="true">
                    <?php Icons::render('car', [ 'class' => 'rv-icon' ]); ?>
                    <?php esc_html_e('Rent a Car', 'mhm-rentiva'); ?>
                </button>
            </div>
        </div>
    <?php endif; ?>

    <!-- 2. Content Body -->
    <div class="rv-unified-search__body">

        <!-- PANEL: CAR RENTAL -->
        <div class="rv-unified-search__panel is-active"
            id="<?php echo esc_attr($uid); ?>_panel_rental"
            role="tabpanel">

            <form class="rv-unified-search__form" action="<?php echo esc_url(\MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_search_results')); ?>" method="GET" data-testid="rental-search-form">

                <!-- Locations -->
                <?php if ($show_location_select) : ?>
                    <div class="rv-unified-search__group">
                        <div class="rv-unified-search__field">
                            <label class="rv-label"><?php esc_html_e('Pick-up', 'mhm-rentiva'); ?></label>
                            <div class="rv-input-wrapper">
                                <?php Icons::render('location', [ 'class' => 'rv-icon-marker' ]); ?>
                                <select name="<?php echo esc_attr($mhmrentiva_qv('pickup_location')); ?>" class="rv-select" <?php echo $location_required ? 'required' : ''; ?> title="<?php esc_attr_e('Select Location', 'mhm-rentiva'); ?>" data-testid="search-pickup-location">
                                    <option value=""><?php esc_html_e('City, Airport, or Hotel', 'mhm-rentiva'); ?></option>
                                    <?php foreach ($locations as $loc) : ?>
                                        <option value="<?php echo esc_attr( (string) $loc->id); ?>" title="<?php echo esc_attr($loc->name); ?>"><?php echo esc_html($loc->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <?php if ($show_dropoff_location) : ?>
                            <div class="rv-unified-search__field">
                                <label class="rv-label"><?php esc_html_e('Drop-off', 'mhm-rentiva'); ?></label>
                                <div class="rv-input-wrapper">
                                    <?php Icons::render('location', [ 'class' => 'rv-icon-marker' ]); ?>
                                    <select name="dropoff_location" class="rv-select" title="<?php esc_attr_e('Select Location', 'mhm-rentiva'); ?>" data-testid="search-dropoff-location">
                                        <option value=""><?php esc_html_e('Same as Pick-up', 'mhm-rentiva'); ?></option>
                                        <?php foreach ($locations as $loc) : ?>
                                            <option value="<?php echo esc_attr( (string) $loc->id); ?>" title="<?php echo esc_attr($loc->name); ?>"><?php echo esc_html($loc->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="dropoff_location" value="">
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Dates -->
                <div class="rv-unified-search__group rv-unified-search__group--mobile-grid mhm-row-date-time">
                    <!-- Pickup Date -->
                    <?php if ($show_date_picker) : ?>
                        <div class="rv-unified-search__field">
                            <label class="rv-label"><?php esc_html_e('Pick-up Date', 'mhm-rentiva'); ?></label>
                            <div class="rv-input-wrapper">
                                <?php Icons::render('calendar', [ 'class' => 'rv-icon-calendar' ]); ?>
                                <input type="text" name="<?php echo esc_attr($mhmrentiva_qv('pickup_date')); ?>" class="rv-input js-datepicker" placeholder="<?php esc_attr_e('Select Date', 'mhm-rentiva'); ?>" <?php echo $fields_required ? 'required' : ''; ?> autocomplete="off" data-testid="search-pickup-date">
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Pickup Time -->
                    <?php if ($show_time_select) : ?>
                        <div class="rv-unified-search__field rv-unified-search__field--time">
                            <label class="rv-label"><?php esc_html_e('Time', 'mhm-rentiva'); ?></label>
                            <div class="rv-input-wrapper">
                                <?php Icons::render('clock', [ 'class' => 'rv-icon-clock' ]); ?>
                                <select name="<?php echo esc_attr($mhmrentiva_qv('pickup_time')); ?>" class="rv-select">
                                    <?php for ($i = 0; $i < 24; $i++) : ?>
                                        <?php $pickup_hour = sprintf('%02d:00', $i); ?>
                                        <option value="<?php echo esc_attr($pickup_hour); ?>" <?php selected($i, 10); ?>>
                                            <?php echo esc_html($pickup_hour); ?>
                                        </option>
                                        <?php $pickup_half = sprintf('%02d:30', $i); ?>
                                        <option value="<?php echo esc_attr($pickup_half); ?>">
                                            <?php echo esc_html($pickup_half); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="rv-unified-search__group rv-unified-search__group--mobile-grid mhm-row-date-time">
                    <!-- Return Date -->
                    <?php if ($show_date_picker) : ?>
                        <div class="rv-unified-search__field">
                            <label class="rv-label"><?php esc_html_e('Return Date', 'mhm-rentiva'); ?></label>
                            <div class="rv-input-wrapper">
                                <?php Icons::render('calendar', [ 'class' => 'rv-icon-calendar' ]); ?>
                                <input type="text" name="<?php echo esc_attr($mhmrentiva_qv('return_date')); ?>" class="rv-input js-datepicker" placeholder="<?php esc_attr_e('Select Date', 'mhm-rentiva'); ?>" <?php echo $fields_required ? 'required' : ''; ?> autocomplete="off" data-testid="search-return-date">
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Return Time -->
                    <?php if ($show_time_select) : ?>
                        <div class="rv-unified-search__field rv-unified-search__field--time">
                            <label class="rv-label"><?php esc_html_e('Time', 'mhm-rentiva'); ?></label>
                            <div class="rv-input-wrapper is-locked">
                                <?php Icons::render('lock', [ 'class' => 'rv-icon-lock' ]); ?>
                                <select name="return_time_display" class="rv-select" disabled>
                                    <?php for ($i = 0; $i < 24; $i++) : ?>
                                        <?php $return_hour = sprintf('%02d:00', $i); ?>
                                        <option value="<?php echo esc_attr($return_hour); ?>" <?php selected($i, 10); ?>>
                                            <?php echo esc_html($return_hour); ?>
                                        </option>
                                        <?php $return_half = sprintf('%02d:30', $i); ?>
                                        <option value="<?php echo esc_attr($return_half); ?>">
                                            <?php echo esc_html($return_half); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                <input type="hidden" name="<?php echo esc_attr($mhmrentiva_qv('return_time')); ?>" value="10:00">
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Action -->
                <div class="rv-unified-search__action">
                    <button type="submit" class="rv-btn rv-btn--primary" data-testid="search-submit-rental">
                        <?php Icons::render('search', [ 'class' => 'rv-icon-search' ]); ?>
                        <?php esc_html_e('Search', 'mhm-rentiva'); ?>
                    </button>
                </div>

            </form>
        </div>

    </div>
</div>
