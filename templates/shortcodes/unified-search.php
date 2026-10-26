<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * Template: search strip (front-end standard, Aşama 2 PR 3).
 *
 * One row on wide screens -- pickup location · pickup · return · Search --
 * stacked on phones. Lite has one service (rental), so the strip has no tabs
 * (the old show_rental_tab setting was removed with them).
 *
 * @var array  $locations             Location options (id, name).
 * @var string $wrapper_id            Unique id prefix for this strip.
 * @var bool   $show_location_select  Whether to show the location select.
 * @var string $pickup_where          The single rental location's name ('' unless exactly one).
 * @var bool   $show_time_select      Whether to show the time selects.
 * @var bool   $show_date_picker      Whether to show the date fields.
 * @var bool   $show_dropoff_location Whether to show the drop-off select.
 * @var bool   $fields_required       Whether the date fields are required.
 * @var bool   $location_required     Whether the location select is required.
 * @var string $form_action           Where the form submits (resolved redirect_page).
 * @var string $layout                'horizontal' or 'vertical'.
 * @var array  $values                Prefilled values: pickup_date, return_date, pickup_time, pickup_location.
 */
if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Frontend\RenderContext;

$uid       = (string) $wrapper_id;
$locations = $locations ?? array();
$values    = $values ?? array();

// On-the-wire names, built through SearchResults::query_var() so they cannot
// drift from the `query_vars` whitelist SearchResults/BookingForm read back.
$mhmrentiva_qv = static function (string $logical): string {
	return \MHMRentiva\Admin\Frontend\Shortcodes\SearchResults::query_var($logical);
};

$show_location_select  = $show_location_select ?? true;
$pickup_where          = (string) ( $pickup_where ?? '' );
$show_time_select      = $show_time_select ?? true;
$show_date_picker      = $show_date_picker ?? true;
$show_dropoff_location = $show_dropoff_location ?? false;
$fields_required       = $fields_required ?? true;
// When fields_required is false, no field is required -- the location included.
$location_required = $fields_required ? ( $location_required ?? true ) : false;
$is_preview        = RenderContext::current()->is_preview();
$picked_time       = (string) ( $values['pickup_time'] ?? '10:00' );

$time_options = static function (string $selected): void {
	for ($i = 0; $i < 24; $i++) {
		foreach (array( '00', '30' ) as $minute) {
			$time = sprintf('%02d:%s', $i, $minute);
			printf('<option value="%1$s"%2$s>%1$s</option>', esc_attr($time), selected($selected, $time, false));
		}
	}
};
?>

<div id="<?php echo esc_attr($uid); ?>" class="rvs-search rvs-search--<?php echo esc_attr($layout ?? 'horizontal'); ?><?php echo $show_location_select ? '' : ' rvs-search--no-location'; ?><?php echo $show_date_picker ? '' : ' rvs-search--no-dates'; ?>" data-rvs-search data-testid="unified-search">
	<form class="rvs-search__form" role="search" aria-label="<?php esc_attr_e('Search vehicles', 'mhm-rentiva'); ?>"<?php echo $is_preview ? '' : ' action="' . esc_url($form_action ?? \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_search_results')) . '"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped with esc_url(). ?> method="GET" data-testid="rental-search-form">

		<?php if ($show_location_select) : ?>
			<div class="rvs-field rvs-search__field rvs-search__field--location">
				<label for="<?php echo esc_attr($uid); ?>-location"><?php esc_html_e('Pickup location', 'mhm-rentiva'); ?></label>
				<select id="<?php echo esc_attr($uid); ?>-location" class="rvs-input rvs-input--lg" name="<?php echo esc_attr($mhmrentiva_qv('pickup_location')); ?>"<?php echo $location_required ? ' required' : ''; ?> data-rvs-pickup-location data-testid="search-pickup-location">
					<option value=""><?php esc_html_e('City, airport or hotel', 'mhm-rentiva'); ?></option>
					<?php foreach ($locations as $loc) : ?>
						<option value="<?php echo esc_attr( (string) $loc->id); ?>"<?php selected( (string) ( $values['pickup_location'] ?? '' ), (string) $loc->id); ?>><?php echo esc_html($loc->name); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		<?php endif; ?>

		<?php if ($show_date_picker) : ?>
			<div class="rvs-field rvs-search__field">
				<label for="<?php echo esc_attr($uid); ?>-pickup-date"><?php esc_html_e('Pickup', 'mhm-rentiva'); ?></label>
				<div class="rvs-search__when">
					<input id="<?php echo esc_attr($uid); ?>-pickup-date" type="text" class="rvs-input rvs-input--lg js-datepicker" name="<?php echo esc_attr($mhmrentiva_qv('pickup_date')); ?>" value="<?php echo esc_attr( (string) ( $values['pickup_date'] ?? '' )); ?>" placeholder="<?php esc_attr_e('Select date', 'mhm-rentiva'); ?>"<?php echo $fields_required ? ' required' : ''; ?> autocomplete="off" data-rvs-pickup-date data-testid="search-pickup-date">
					<?php if ($show_time_select) : ?>
						<label class="rvs-sr-only" for="<?php echo esc_attr($uid); ?>-pickup-time"><?php esc_html_e('Pickup time', 'mhm-rentiva'); ?></label>
						<select id="<?php echo esc_attr($uid); ?>-pickup-time" class="rvs-input rvs-input--lg rvs-search__time" name="<?php echo esc_attr($mhmrentiva_qv('pickup_time')); ?>" data-rvs-pickup-time>
							<?php $time_options($picked_time); ?>
						</select>
					<?php endif; ?>
				</div>
			</div>

			<div class="rvs-field rvs-search__field">
				<label for="<?php echo esc_attr($uid); ?>-return-date"><?php echo esc_html_x('Return', 'search strip: the return date field', 'mhm-rentiva'); ?></label>
				<div class="rvs-search__when">
					<input id="<?php echo esc_attr($uid); ?>-return-date" type="text" class="rvs-input rvs-input--lg js-datepicker" name="<?php echo esc_attr($mhmrentiva_qv('return_date')); ?>" value="<?php echo esc_attr( (string) ( $values['return_date'] ?? '' )); ?>" placeholder="<?php esc_attr_e('Select date', 'mhm-rentiva'); ?>"<?php echo $fields_required ? ' required' : ''; ?> autocomplete="off" data-rvs-return-date data-testid="search-return-date">
					<?php if ($show_time_select) : ?>
						<?php // Return time follows the pickup time (the booking counts whole days). ?>
						<span class="rvs-input rvs-input--lg rvs-search__time rvs-search__time--locked" data-rvs-return-time-display title="<?php esc_attr_e('Return time follows the pickup time', 'mhm-rentiva'); ?>"><?php echo esc_html($picked_time); ?></span>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
		<input type="hidden" name="<?php echo esc_attr($mhmrentiva_qv('return_time')); ?>" value="<?php echo esc_attr($picked_time); ?>" data-rvs-return-time>

		<button type="submit" class="rvs-btn rvs-btn--primary rvs-btn--lg rvs-search__submit" data-testid="search-submit-rental"<?php echo $is_preview ? ' disabled' : ''; ?>><?php esc_html_e('Search', 'mhm-rentiva'); ?></button>

		<?php if ('' !== $pickup_where) : ?>
			<p class="rvs-search__where" data-rvs-pickup-where><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false"><path d="M12 21s-7-6.3-7-11a7 7 0 0 1 14 0c0 4.7-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg><span>
			<?php
			/* translators: %s: the site's only pickup point. */
			printf(esc_html__('Pickup and return: %s', 'mhm-rentiva'), '<strong>' . esc_html($pickup_where) . '</strong>');
			?>
			</span></p>
		<?php endif; ?>

		<?php if ($show_location_select && $show_dropoff_location) : ?>
			<div class="rvs-field rvs-search__field rvs-search__field--dropoff">
				<label for="<?php echo esc_attr($uid); ?>-dropoff"><?php esc_html_e('Drop-off', 'mhm-rentiva'); ?></label>
				<?php // Mirrors the pickup location until drop-off elsewhere ships (unified-search.js). ?>
				<select id="<?php echo esc_attr($uid); ?>-dropoff" class="rvs-input rvs-input--lg" data-rvs-dropoff disabled data-testid="search-dropoff-location">
					<option value=""><?php esc_html_e('Same as pickup', 'mhm-rentiva'); ?></option>
					<?php foreach ($locations as $loc) : ?>
						<option value="<?php echo esc_attr( (string) $loc->id); ?>"><?php echo esc_html($loc->name); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="hidden" name="dropoff_location" value="" data-rvs-dropoff-value>
			</div>
		<?php endif; ?>
	</form>
</div>
