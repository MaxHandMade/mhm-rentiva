<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * Contact form (`Iletisim`, `Iletisim-Mobil`; PR F).
 *
 * Templates::include_template_with_vars() hands each template var over as its own
 * variable. Fields that only some topics have carry data-rvs-topics, and
 * contact-form.js toggles them; a hidden field is also disabled, so it is not sent.
 * The script adds field errors and the success box.
 *
 * @var array         $atts            Shortcode attributes (canonical, with defaults).
 * @var array         $vehicles        Vehicle list for the selector.
 * @var array         $priorities      Priority options.
 * @var string        $recipient_token Encrypted payload, '' when unset.
 * @var string        $redirect_url    Validated same-site redirect, '' for none.
 * @var WP_User|null  $current_user    Current user.
 * @var array         $view            ContactFormView::build().
 */

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Frontend\RenderContext;
use MHMRentiva\Helpers\Icons;

$atts            = isset($atts) && is_array($atts) ? $atts : array();
$vehicles        = isset($vehicles) && is_array($vehicles) ? $vehicles : array();
$priorities      = isset($priorities) && is_array($priorities) ? $priorities : array();
$recipient_token = isset($recipient_token) ? (string) $recipient_token : '';
$redirect_url    = isset($redirect_url) ? (string) $redirect_url : '';
$current_user    = $current_user ?? null;
$view            = isset($view) && is_array($view) ? $view : array();

$selected_topic   = (string) ( $view['selected'] ?? ( $atts['type'] ?? 'general' ) );
$fields_by_topic  = (array) ( $view['fields'] ?? array() );
$topic_rows       = (array) ( $view['topics'] ?? array() );
$ids              = (array) ( $view['ids'] ?? array() );
$booking_mode     = (string) ( $view['booking_field']['mode'] ?? '' );
$attachment       = (array) ( $view['attachment'] ?? array() );
$sidebar          = (array) ( $view['sidebar'] ?? array() );
$form_title       = (string) ( $atts['title'] ?? '' );
$form_description = (string) ( $atts['description'] ?? '' );
$response_note    = (string) ( $view['response_note'] ?? '' );
$success_message  = (string) ( $atts['success_message'] ?? '' );
$theme            = (string) ( $atts['theme'] ?? 'default' );
$extra_class      = (string) ( $atts['class'] ?? '' );

// The topics that have one field, or that require it.
$topics_of = static function (string $field, bool $required_only = false) use ($fields_by_topic): array {
	$found = array();
	foreach ($fields_by_topic as $topic => $fields) {
		if (isset($fields[ $field ]) && ( ! $required_only || $fields[ $field ] )) {
			$found[] = (string) $topic;
		}
	}
	return $found;
};

$has_field = static fn(string $field): bool => array() !== $topics_of($field);

// A field of some topics only: its wrapper names them, and starts hidden on the others.
$starts_hidden = static fn(string $field): bool => ! in_array($selected_topic, $topics_of($field), true);
$topic_attrs   = static function (string $field) use ($topics_of, $fields_by_topic, $starts_hidden): void {
	$topics = $topics_of($field);
	if (count($topics) === count($fields_by_topic)) {
		return;
	}
	printf(' data-rvs-topics="%s"', esc_attr(implode(' ', $topics)));
	if ($starts_hidden($field)) {
		echo ' hidden';
	}
};
$disabled_attr = static function (string $field) use ($starts_hidden): void {
	if ($starts_hidden($field)) {
		echo ' disabled';
	}
};

$priority_required = in_array($selected_topic, $topics_of('priority', true), true);
$wrapper_classes   = trim('rvs-contact rvs-contact--' . $theme . ' ' . $extra_class);
?>
<div class="<?php echo esc_attr($wrapper_classes); ?>" data-rvs-contact data-form-type="<?php echo esc_attr($selected_topic); ?>"
	<?php
	// Read by contact-form.js (dataset.successMessage / dataset.redirectUrl); printed only when set.
	if ('' !== $success_message) {
		printf(' data-success-message="%s"', esc_attr($success_message));
	}
	if ('' !== $redirect_url) {
		printf(' data-redirect-url="%s"', esc_url($redirect_url));
	}
	?>
>
	<?php if ('' !== $form_title || '' !== $form_description || '' !== $response_note) : ?>
		<div class="rvs-contact__header">
			<?php if ('' !== $form_title) : ?>
				<h2 class="rvs-contact__title"><?php echo esc_html($form_title); ?></h2>
			<?php endif; ?>
			<?php if ('' !== $form_description) : ?>
				<p class="rvs-contact__desc"><?php echo esc_html($form_description); ?></p>
			<?php endif; ?>
			<?php if ('' !== $response_note) : ?>
				<p class="rvs-contact__note"><?php echo esc_html($response_note); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="rvs-contact__body">
		<div class="rvs-contact__main">
			<div id="<?php echo esc_attr($ids['status'] ?? ''); ?>" class="rvs-contact__status" role="status"></div>

			<form id="<?php echo esc_attr($ids['form'] ?? ''); ?>" class="rvs-contact__form" enctype="multipart/form-data" novalidate aria-label="<?php echo esc_attr__('Contact form', 'mhm-rentiva'); ?>">
				<?php if ('' !== $recipient_token) : ?>
					<input type="hidden" name="recipient_token" value="<?php echo esc_attr($recipient_token); ?>">
				<?php endif; ?>

				<?php if (! empty($view['selector'])) : ?>
					<fieldset class="rvs-field">
						<legend><?php echo esc_html__('Topic', 'mhm-rentiva'); ?></legend>
						<div class="rvs-seg rvs-seg--choice">
							<?php foreach ($topic_rows as $topic_row) : ?>
								<label><input type="radio" name="type" value="<?php echo esc_attr($topic_row['value']); ?>"<?php checked($selected_topic, $topic_row['value']); ?>><span><?php echo esc_html($topic_row['label']); ?></span></label>
							<?php endforeach; ?>
						</div>
					</fieldset>
				<?php else : ?>
					<input type="hidden" name="type" value="<?php echo esc_attr($selected_topic); ?>">
				<?php endif; ?>

				<div class="rvs-contact__grid">
					<div class="rvs-field" data-rvs-field>
						<label for="<?php echo esc_attr($ids['name'] ?? ''); ?>"><?php echo esc_html__('Full name', 'mhm-rentiva'); ?></label>
						<input type="text" id="<?php echo esc_attr($ids['name'] ?? ''); ?>" name="name" class="rvs-input" autocomplete="name" required value="<?php echo esc_attr($current_user->display_name ?? ''); ?>">
					</div>

					<div class="rvs-field" data-rvs-field>
						<label for="<?php echo esc_attr($ids['email'] ?? ''); ?>"><?php echo esc_html__('Email', 'mhm-rentiva'); ?></label>
						<input type="email" id="<?php echo esc_attr($ids['email'] ?? ''); ?>" name="email" class="rvs-input" autocomplete="email" required value="<?php echo esc_attr($current_user->user_email ?? ''); ?>">
					</div>

					<?php if ($has_field('phone')) : ?>
						<div class="rvs-field" data-rvs-field>
							<label for="<?php echo esc_attr($ids['phone'] ?? ''); ?>"><?php echo esc_html__('Phone (optional)', 'mhm-rentiva'); ?></label>
							<input type="tel" id="<?php echo esc_attr($ids['phone'] ?? ''); ?>" name="phone" class="rvs-input" autocomplete="tel">
						</div>
					<?php endif; ?>

					<?php if ($has_field('company')) : ?>
						<div class="rvs-field" data-rvs-field>
							<label for="<?php echo esc_attr($ids['company'] ?? ''); ?>"><?php echo esc_html__('Company (optional)', 'mhm-rentiva'); ?></label>
							<input type="text" id="<?php echo esc_attr($ids['company'] ?? ''); ?>" name="company" class="rvs-input" autocomplete="organization">
						</div>
					<?php endif; ?>

					<?php if ($has_field('vehicle_id')) : ?>
						<div class="rvs-field" data-rvs-field>
							<label for="<?php echo esc_attr($ids['vehicle'] ?? ''); ?>"><?php echo esc_html__('Vehicle (optional)', 'mhm-rentiva'); ?></label>
							<select id="<?php echo esc_attr($ids['vehicle'] ?? ''); ?>" name="vehicle_id" class="rvs-input">
								<option value=""><?php echo esc_html__('Choose a vehicle', 'mhm-rentiva'); ?></option>
								<?php foreach ($vehicles as $vehicle) : ?>
									<option value="<?php echo esc_attr( (string) $vehicle['id']); ?>"><?php echo esc_html($vehicle['title']); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					<?php endif; ?>

					<?php if ('list' === $booking_mode) : ?>
						<div class="rvs-field" data-rvs-field<?php $topic_attrs('booking_id'); ?>>
							<label for="<?php echo esc_attr($ids['booking'] ?? ''); ?>"><?php echo esc_html__('Booking (optional)', 'mhm-rentiva'); ?></label>
							<select id="<?php echo esc_attr($ids['booking'] ?? ''); ?>" name="booking_id" class="rvs-input" aria-describedby="<?php echo esc_attr( ( $ids['booking'] ?? '' ) . '-help'); ?>"<?php $disabled_attr('booking_id'); ?>>
								<option value=""><?php echo esc_html__('Choose a booking', 'mhm-rentiva'); ?></option>
								<?php foreach ( (array) ( $view['bookings'] ?? array() ) as $booking_option ) : ?>
									<option value="<?php echo esc_attr( (string) $booking_option['id']); ?>"><?php echo esc_html($booking_option['label']); ?></option>
								<?php endforeach; ?>
							</select>
							<span id="<?php echo esc_attr( ( $ids['booking'] ?? '' ) . '-help'); ?>" class="rvs-help"><?php echo esc_html__('You are signed in, so the list shows only your own bookings.', 'mhm-rentiva'); ?></span>
						</div>
					<?php elseif ('number' === $booking_mode) : ?>
						<div class="rvs-field" data-rvs-field<?php $topic_attrs('booking_id'); ?>>
							<label for="<?php echo esc_attr($ids['booking'] ?? ''); ?>"><?php echo esc_html__('Booking number (optional)', 'mhm-rentiva'); ?></label>
							<input type="number" id="<?php echo esc_attr($ids['booking'] ?? ''); ?>" name="booking_id" class="rvs-input" min="1" step="1" inputmode="numeric"<?php $disabled_attr('booking_id'); ?>>
						</div>
					<?php endif; ?>

					<?php if ($has_field('preferred_date')) : ?>
						<div class="rvs-field" data-rvs-field<?php $topic_attrs('preferred_date'); ?>>
							<label for="<?php echo esc_attr($ids['preferred_date'] ?? ''); ?>"><?php echo esc_html__('Preferred date (optional)', 'mhm-rentiva'); ?></label>
							<input type="date" id="<?php echo esc_attr($ids['preferred_date'] ?? ''); ?>" name="preferred_date" class="rvs-input"<?php $disabled_attr('preferred_date'); ?>>
						</div>
					<?php endif; ?>

					<?php if ($has_field('priority') && array() !== $priorities) : ?>
						<div class="rvs-field" data-rvs-field<?php $topic_attrs('priority'); ?> data-rvs-required-topics="<?php echo esc_attr(implode(' ', $topics_of('priority', true))); ?>">
							<label for="<?php echo esc_attr($ids['priority'] ?? ''); ?>" data-rvs-label-required="<?php echo esc_attr__('Priority', 'mhm-rentiva'); ?>" data-rvs-label-optional="<?php echo esc_attr__('Priority (optional)', 'mhm-rentiva'); ?>"><?php echo $priority_required ? esc_html__('Priority', 'mhm-rentiva') : esc_html__('Priority (optional)', 'mhm-rentiva'); ?></label>
							<select id="<?php echo esc_attr($ids['priority'] ?? ''); ?>" name="priority" class="rvs-input"<?php echo $priority_required ? ' required' : ''; ?><?php $disabled_attr('priority'); ?>>
								<option value=""><?php echo esc_html__('Choose a priority', 'mhm-rentiva'); ?></option>
								<?php foreach ($priorities as $priority_key => $priority) : ?>
									<option value="<?php echo esc_attr($priority_key); ?>"><?php echo esc_html($priority['label']); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					<?php endif; ?>
				</div>

				<?php if ($has_field('rating')) : ?>
					<fieldset class="rvs-field" data-rvs-field<?php $topic_attrs('rating'); ?>>
						<legend><?php echo esc_html__('Rating (optional)', 'mhm-rentiva'); ?></legend>
						<?php
						Templates::render(
							'partials/rvs/star-input',
							array( 'disabled' => $starts_hidden('rating') )
						);
						?>
					</fieldset>
				<?php endif; ?>

				<div class="rvs-field" data-rvs-field>
					<label for="<?php echo esc_attr($ids['message'] ?? ''); ?>"><?php echo esc_html__('Your message', 'mhm-rentiva'); ?></label>
					<textarea id="<?php echo esc_attr($ids['message'] ?? ''); ?>" name="message" class="rvs-input rvs-contact__message" rows="6" required></textarea>
				</div>

				<?php if ($has_field('attachment')) : ?>
					<div class="rvs-field" data-rvs-field>
						<label for="<?php echo esc_attr($ids['attachment'] ?? ''); ?>"><?php echo esc_html__('File (optional)', 'mhm-rentiva'); ?></label>
						<div class="rvs-file">
							<input type="file" id="<?php echo esc_attr($ids['attachment'] ?? ''); ?>" name="attachment" class="rvs-file__input" accept="<?php echo esc_attr( (string) ( $attachment['accept'] ?? '' )); ?>" aria-describedby="<?php echo esc_attr( ( $ids['attachment'] ?? '' ) . '-help'); ?>">
							<button type="button" class="rvs-btn rvs-btn--ghost" data-rvs-file-remove hidden><?php echo esc_html__('Remove file', 'mhm-rentiva'); ?></button>
						</div>
						<span id="<?php echo esc_attr( ( $ids['attachment'] ?? '' ) . '-help'); ?>" class="rvs-help"><?php echo esc_html( (string) ( $attachment['help'] ?? '' )); ?></span>
					</div>
				<?php endif; ?>

				<?php if (! empty($view['consent']['enabled'])) : ?>
					<div class="rvs-contact__consent" data-rvs-field>
						<label class="rvs-check">
							<input type="checkbox" id="<?php echo esc_attr($ids['consent'] ?? ''); ?>" name="consent" value="1" required>
							<span>
								<?php
								echo wp_kses(
									(string) $view['consent']['html'],
									array(
										'a' => array(
											'href'   => true,
											'target' => true,
											'rel'    => true,
										),
									)
								);
								?>
							</span>
						</label>
					</div>
				<?php endif; ?>

				<div class="rvs-sr-only" aria-hidden="true">
					<label for="<?php echo esc_attr($ids['honeypot'] ?? ''); ?>"><?php echo esc_html__('Leave this field blank', 'mhm-rentiva'); ?></label>
					<input type="text" id="<?php echo esc_attr($ids['honeypot'] ?? ''); ?>" name="<?php echo esc_attr( (string) ( $view['honeypot'] ?? '' )); ?>" tabindex="-1" autocomplete="off">
				</div>

				<div class="rvs-contact__actions">
					<button type="submit" class="rvs-btn rvs-btn--primary rvs-btn--lg"<?php echo RenderContext::current()->is_preview() ? ' disabled' : ''; ?>><?php echo esc_html__('Send', 'mhm-rentiva'); ?></button>
				</div>
			</form>
		</div>

		<?php if (array() !== $sidebar) : ?>
			<aside class="rvs-contact__info" aria-label="<?php echo esc_attr__('Contact information', 'mhm-rentiva'); ?>">
				<?php foreach ($sidebar as $info_row) : ?>
					<div class="rvs-contact__info-row">
						<h2><?php echo esc_html($info_row['label']); ?></h2>
						<p>
							<?php if ('' !== $info_row['href']) : ?>
								<a class="rvs-contact__info-link" href="<?php echo esc_url($info_row['href'], array( 'tel', 'mailto' )); ?>"><?php echo esc_html($info_row['value']); ?></a>
							<?php else : ?>
								<?php echo esc_html($info_row['value']); ?>
							<?php endif; ?>
						</p>
						<?php if ('' !== $info_row['note']) : ?>
							<p class="rvs-contact__info-note"><?php echo esc_html($info_row['note']); ?></p>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</aside>
		<?php endif; ?>
	</div>
</div>
