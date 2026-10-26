<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * Booking form (front-end standard, Aşama 2 PR C).
 *
 * Root `.rvs-booking`; the outer `.rvs-shell` comes from AbstractShortcode.
 * Heading, journey steps (step 1 of 3), then two columns: the form and the
 * summary `aside`. On a phone the aside is a bottom band -- total, deposit and
 * the submit button, the full summary behind a toggle.
 *
 * booking-form.js binds to the data-rvs-* hooks below, from each instance's
 * root, so two forms on one page never touch each other. Every id carries the
 * instance prefix ($uid, wp_unique_id()).
 *
 * The data comes ready from BookingForm::prepare_template_data().
 */

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Booking\Pricing\PriceDisplay;
use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Frontend\RenderContext;

$atts             = $atts ?? array();
$vehicles         = $vehicles ?? array();
$selected_vehicle = $selected_vehicle ?? null;
$time_options     = $time_options ?? array();

if (isset($validation_error) && ! empty($validation_error)) {
	echo '<div class="rvs-booking rvs-booking--error"><p class="rvs-error" role="alert">' . esc_html($validation_error) . '</p></div>';
	return;
}

$uid                   = (string) ( $uid ?? wp_unique_id('rvs-bf-') );
$show_vehicle_selector = $show_vehicle_selector ?? true;
$show_time_select      = $show_time_select ?? true;
$enable_deposit        = $enable_deposit ?? true;
$default_days          = (int) ( $default_days ?? 0 );
$heading               = (string) ( $heading ?? '' );
$steps                 = (array) ( $steps ?? array() );
$summary               = (array) ( $summary ?? array() );
$addon_options         = (array) ( $addon_options ?? array() );
$class                 = (string) ( $atts['class'] ?? '' );
$redirect_url          = (string) ( $atts['redirect_url'] ?? '' );
$pickup_location_id    = (int) ( $pickup_location_id ?? 0 );
$prefill_pickup_time   = (string) ( $prefill_pickup_time ?? '' );
$user_data             = (array) ( $user_data ?? array() );
$money_html            = PriceDisplay::allowed_html();

$make_booking_text = (string) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhmrentiva_text_make_booking', '');
$make_booking_text = '' !== $make_booking_text ? $make_booking_text : __('Make Booking', 'mhm-rentiva');
$is_preview        = RenderContext::current()->is_preview();
?>
<div class="rvs-booking<?php echo '' !== $class ? ' ' . esc_attr($class) : ''; ?>" data-rvs-booking-form data-rvs-deposit="<?php echo $enable_deposit ? '1' : '0'; ?>" data-testid="booking-form-wrapper" data-redirect-url="<?php echo esc_url($redirect_url); ?>"<?php echo $default_days > 0 ? ' data-default-days="' . esc_attr( (string) $default_days) . '"' : ''; ?><?php echo ! empty($selected_vehicle['id']) ? ' data-vehicle-id="' . esc_attr( (string) $selected_vehicle['id']) . '"' : ''; ?>>
	<div class="rvs-booking__head">
		<h2 class="rvs-booking__title" id="<?php echo esc_attr($uid . '-title'); ?>"><?php echo esc_html($heading); ?></h2>
		<?php Templates::render('partials/rvs/steps', array( 'items' => $steps )); ?>
	</div>

	<?php
	// No inline handlers: the late wp_kses() strips every on* attribute, and the
	// only submit path is booking-form.js (it prevents the native submit).
	?>
	<form class="rvs-booking__form" id="<?php echo esc_attr($uid . '-form'); ?>" method="post" aria-labelledby="<?php echo esc_attr($uid . '-title'); ?>">
		<?php if ($pickup_location_id > 0) : ?>
			<input type="hidden" name="pickup_location_id" value="<?php echo esc_attr( (string) $pickup_location_id); ?>">
		<?php endif; ?>

		<div class="rvs-booking__layout">
			<div class="rvs-booking__main">
				<?php if ($show_vehicle_selector && empty($selected_vehicle)) : ?>
					<section class="rvs-booking__section" aria-labelledby="<?php echo esc_attr($uid . '-vehicle-h'); ?>">
						<h3 class="rvs-booking__section-title" id="<?php echo esc_attr($uid . '-vehicle-h'); ?>"><?php esc_html_e('Vehicle Selection', 'mhm-rentiva'); ?></h3>
						<div class="rvs-field">
							<label for="<?php echo esc_attr($uid . '-vehicle'); ?>"><?php esc_html_e('Select Vehicle', 'mhm-rentiva'); ?></label>
							<select name="vehicle_id" id="<?php echo esc_attr($uid . '-vehicle'); ?>" class="rvs-input" data-rvs-vehicle-select required>
								<option value=""><?php esc_html_e('Select vehicle...', 'mhm-rentiva'); ?></option>
								<?php foreach ($vehicles as $vehicle) : ?>
									<option value="<?php echo esc_attr( (string) $vehicle['id']); ?>"
										data-price="<?php echo esc_attr( (string) $vehicle['price_per_day']); ?>"
										data-permalink="<?php echo esc_url( (string) ( $vehicle['permalink'] ?? '' )); ?>"
										data-category="<?php echo esc_attr( (string) ( $vehicle['category_name'] ?? '' )); ?>"
										data-image="<?php echo esc_url( (string) ( $vehicle['featured_image'] ?? '' )); ?>"
										data-features="<?php echo esc_attr( (string) wp_json_encode(array_values( (array) ( $vehicle['features'] ?? array() )))); ?>"
										data-rating="<?php echo esc_attr(number_format_i18n( (float) ( $vehicle['rating']['average'] ?? 0 ), 1)); ?>"
										data-rating-count="<?php echo esc_attr( (string) intval($vehicle['rating']['count'] ?? 0)); ?>"><?php echo esc_html( (string) $vehicle['title']); ?> (<?php echo esc_html(\MHMRentiva\Admin\Core\CurrencyHelper::format_price( (float) $vehicle['price_per_day'], 0)); ?><?php esc_html_e('/day', 'mhm-rentiva'); ?>)</option>
								<?php endforeach; ?>
							</select>
						</div>
					</section>
				<?php elseif (! empty($selected_vehicle['id'])) : ?>
					<input type="hidden" name="vehicle_id" value="<?php echo esc_attr( (string) $selected_vehicle['id']); ?>">
				<?php endif; ?>

				<section class="rvs-booking__section" aria-labelledby="<?php echo esc_attr($uid . '-dates-h'); ?>">
					<h3 class="rvs-booking__section-title" id="<?php echo esc_attr($uid . '-dates-h'); ?>"><?php esc_html_e('Date & Time Selection', 'mhm-rentiva'); ?></h3>
					<div class="rvs-booking__window">
						<div class="rvs-field">
							<label for="<?php echo esc_attr($uid . '-pickup-date'); ?>"><?php esc_html_e('Pickup Date', 'mhm-rentiva'); ?></label>
							<input type="text" id="<?php echo esc_attr($uid . '-pickup-date'); ?>" name="pickup_date" class="rvs-input rvs-booking__date" data-rvs-pickup-date data-testid="booking-pickup-date" placeholder="<?php esc_attr_e('Select date', 'mhm-rentiva'); ?>" value="<?php echo esc_attr( (string) ( $atts['start_date'] ?? '' )); ?>" autocomplete="off" readonly required>
						</div>
						<?php if ($show_time_select) : ?>
							<div class="rvs-field">
								<label for="<?php echo esc_attr($uid . '-pickup-time'); ?>"><?php esc_html_e('Pickup Time', 'mhm-rentiva'); ?></label>
								<select id="<?php echo esc_attr($uid . '-pickup-time'); ?>" name="pickup_time" class="rvs-input" data-rvs-pickup-time required>
									<option value=""><?php esc_html_e('Time', 'mhm-rentiva'); ?></option>
									<?php foreach ($time_options as $option) : ?>
										<option value="<?php echo esc_attr( (string) $option['value']); ?>"<?php selected( (string) $option['value'], $prefill_pickup_time); ?>><?php echo esc_html( (string) $option['label']); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						<?php else : ?>
							<input type="hidden" name="pickup_time" value="12:00" data-rvs-pickup-time>
						<?php endif; ?>
						<div class="rvs-field">
							<label for="<?php echo esc_attr($uid . '-dropoff-date'); ?>"><?php esc_html_e('Return Date', 'mhm-rentiva'); ?></label>
							<input type="text" id="<?php echo esc_attr($uid . '-dropoff-date'); ?>" name="dropoff_date" class="rvs-input rvs-booking__date" data-rvs-dropoff-date placeholder="<?php esc_attr_e('Select date', 'mhm-rentiva'); ?>" value="<?php echo esc_attr( (string) ( $atts['end_date'] ?? '' )); ?>" autocomplete="off" readonly required>
						</div>
						<?php if ($show_time_select) : ?>
							<div class="rvs-field">
								<label for="<?php echo esc_attr($uid . '-dropoff-time'); ?>"><?php esc_html_e('Return Time', 'mhm-rentiva'); ?></label>
								<?php // Shows the return time the server applies (= pickup time); the hidden copy is what is posted. ?>
								<select id="<?php echo esc_attr($uid . '-dropoff-time'); ?>" class="rvs-input rvs-booking__time--locked" data-rvs-dropoff-time-display aria-describedby="<?php echo esc_attr($uid . '-time-hint'); ?>" disabled>
									<option value=""><?php esc_html_e('Time', 'mhm-rentiva'); ?></option>
									<?php foreach ($time_options as $option) : ?>
										<option value="<?php echo esc_attr( (string) $option['value']); ?>"<?php selected( (string) $option['value'], $prefill_pickup_time); ?>><?php echo esc_html( (string) $option['label']); ?></option>
									<?php endforeach; ?>
								</select>
								<input type="hidden" name="dropoff_time" value="<?php echo esc_attr($prefill_pickup_time); ?>" data-rvs-dropoff-time>
							</div>
						<?php else : ?>
							<input type="hidden" name="dropoff_time" value="12:00" data-rvs-dropoff-time>
						<?php endif; ?>
					</div>
					<p class="rvs-help" id="<?php echo esc_attr($uid . '-time-hint'); ?>"><?php esc_html_e('Return time is automatically set to match pickup time.', 'mhm-rentiva'); ?></p>
					<div class="rvs-booking__availability" data-rvs-availability role="status" aria-live="polite" hidden></div>
				</section>

				<?php if (array() !== $addon_options) : ?>
					<section class="rvs-booking__section" aria-labelledby="<?php echo esc_attr($uid . '-addons-h'); ?>">
						<h3 class="rvs-booking__section-title" id="<?php echo esc_attr($uid . '-addons-h'); ?>"><?php esc_html_e('Additional Services', 'mhm-rentiva'); ?></h3>
						<div class="rvs-booking__options">
							<?php foreach ($addon_options as $option) : ?>
								<?php
								$option_id   = (int) $option['id'];
								$option_key  = $uid . '-addon-' . $option_id;
								$option_desc = (string) $option['desc'];
								?>
								<?php if ('qty' === $option['kind'] || 'locked_qty' === $option['kind']) : ?>
									<div class="rvs-option rvs-option--qty<?php echo 'locked_qty' === $option['kind'] ? ' rvs-option--locked' : ''; ?>" data-rvs-addon="<?php echo esc_attr( (string) $option_id); ?>">
										<span class="rvs-option__body" id="<?php echo esc_attr($option_key); ?>">
											<span class="rvs-option__title"><?php echo esc_html( (string) $option['title']); ?></span>
											<?php if ('' !== $option_desc) : ?>
												<span class="rvs-option__desc"><?php echo esc_html($option_desc); ?></span>
											<?php endif; ?>
										</span>
										<span class="rvs-option__price"><?php echo wp_kses( (string) $option['price_html'], $money_html); ?></span>
										<div class="rvs-stepper" role="group" aria-labelledby="<?php echo esc_attr($option_key); ?>" data-rvs-stepper="<?php echo esc_attr( (string) $option_id); ?>" data-min="<?php echo esc_attr( (string) $option['min']); ?>" data-max="<?php echo esc_attr( (string) $option['max']); ?>">
											<button type="button" data-rvs-step="-1" aria-label="<?php esc_attr_e('Decrease', 'mhm-rentiva'); ?>" disabled>&minus;</button>
											<output aria-live="polite"><?php echo esc_html( (string) $option['min']); ?></output>
											<button type="button" data-rvs-step="1" aria-label="<?php esc_attr_e('Increase', 'mhm-rentiva'); ?>">+</button>
											<input type="hidden" name="addons[]" value="<?php echo esc_attr( (string) $option_id); ?>"<?php echo 0 === (int) $option['min'] ? ' disabled' : ''; ?>>
											<input type="hidden" name="addon_qty[<?php echo esc_attr( (string) $option_id); ?>]" value="<?php echo esc_attr( (string) $option['min']); ?>"<?php echo 0 === (int) $option['min'] ? ' disabled' : ''; ?>>
										</div>
									</div>
								<?php else : ?>
									<?php $option_locked = 'locked' === $option['kind']; ?>
									<label class="rvs-option<?php echo $option_locked ? ' rvs-option--locked' : ''; ?>" data-rvs-addon="<?php echo esc_attr( (string) $option_id); ?>">
										<?php if ($option_locked) : ?>
											<?php // A disabled box is not submitted: the hidden field below carries the id. ?>
											<input type="checkbox" value="<?php echo esc_attr( (string) $option_id); ?>" checked disabled>
										<?php else : ?>
											<input type="checkbox" name="addons[]" value="<?php echo esc_attr( (string) $option_id); ?>">
										<?php endif; ?>
										<span class="rvs-option__body">
											<span class="rvs-option__title"><?php echo esc_html( (string) $option['title']); ?></span>
											<?php if ('' !== $option_desc) : ?>
												<span class="rvs-option__desc"><?php echo esc_html($option_desc); ?></span>
											<?php endif; ?>
										</span>
										<span class="rvs-option__price"><?php echo wp_kses( (string) $option['price_html'], $money_html); ?></span>
										<?php if ($option_locked) : ?>
											<input type="hidden" name="addons[]" value="<?php echo esc_attr( (string) $option_id); ?>">
										<?php endif; ?>
									</label>
								<?php endif; ?>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endif; ?>

				<div class="rvs-booking__messages" data-rvs-messages role="alert" hidden></div>
			</div>

			<aside class="rvs-booking__aside" aria-label="<?php esc_attr_e('Booking summary', 'mhm-rentiva'); ?>">
				<?php
				Templates::render('partials/selected-vehicle-summary', array(
					'summary' => $summary,
					'uid'     => $uid,
				));
				?>
				<div class="rvs-booking__bar">
					<button type="button" class="rvs-booking__toggle" data-rvs-summary-toggle aria-expanded="false" aria-controls="<?php echo esc_attr($uid . '-summary'); ?>">
						<span class="rvs-booking__bar-total">
							<strong data-rvs-total>-</strong>
							<span class="rvs-booking__bar-note"><?php esc_html_e('total', 'mhm-rentiva'); ?><span data-rvs-bar-deposit hidden></span></span>
						</span>
						<span class="rvs-sr-only" data-rvs-summary-toggle-label><?php esc_html_e('Show summary', 'mhm-rentiva'); ?></span>
					</button>
					<?php
					// Logged-in visitors: their name and contact ride along for the
					// cart; WooCommerce collects them from guests at checkout.
					if (! empty($user_data['is_logged_in'])) :
						$first_name = (string) ( $user_data['first_name'] ?? '' );
						$last_name  = (string) ( $user_data['last_name'] ?? '' );
						$user_name  = (string) ( $user_data['user_name'] ?? '' );
						if ('' === $first_name && '' !== $user_name) {
							$name_parts = explode(' ', $user_name);
							$first_name = (string) array_shift($name_parts);
							$last_name  = implode(' ', $name_parts);
						}
						?>
						<input type="hidden" name="customer_first_name" value="<?php echo esc_attr($first_name); ?>">
						<input type="hidden" name="customer_last_name" value="<?php echo esc_attr($last_name); ?>">
						<input type="hidden" name="customer_email" value="<?php echo esc_attr( (string) ( $user_data['user_email'] ?? '' )); ?>">
						<input type="hidden" name="customer_phone" value="<?php echo esc_attr( (string) ( $user_data['user_phone'] ?? '' )); ?>">
					<?php endif; ?>
					<?php // The payment type is chosen at the WooCommerce checkout (step 2). ?>
					<input type="hidden" name="payment_type" value="deposit">
					<input type="hidden" name="redirect_url" value="<?php echo esc_url($redirect_url); ?>">
					<button type="button" class="rvs-btn rvs-btn--primary rvs-btn--lg rvs-booking__submit" data-rvs-submit data-testid="booking-submit-btn"<?php echo $is_preview ? ' disabled' : ''; ?>><?php echo esc_html($make_booking_text); ?></button>
				</div>
			</aside>
		</div>
	</form>
</div>
