<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * Contact Form Template
 *
 * Templates::include_template_with_vars() hands each template var over as its own
 * variable ($atts, $vehicles, ...). There is no $args here: reading $args['atts']
 * used to overwrite every setting with an empty array (spec 3A §1).
 *
 * @var array         $atts            Shortcode attributes (canonical, with defaults).
 * @var array         $vehicles        Vehicle list for the selector.
 * @var array         $priorities      Priority options.
 * @var string        $recipient_token Encrypted email_to payload, '' when unset.
 * @var string        $redirect_url    Validated same-site redirect, '' for none.
 * @var WP_User|null  $current_user    Current user.
 * @var string        $support_email   Contact box e-mail.
 * @var string        $support_phone   Contact box phone.
 * @var string        $support_hours   Contact box hours.
 */

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Helpers\Icons;



// Template data (unpacked by Templates::include_template_with_vars()).
$atts            = isset($atts) && is_array($atts) ? $atts : array();
$vehicles        = isset($vehicles) && is_array($vehicles) ? $vehicles : array();
$priorities      = isset($priorities) && is_array($priorities) ? $priorities : array();
$recipient_token = isset($recipient_token) ? (string) $recipient_token : '';
$redirect_url    = isset($redirect_url) ? (string) $redirect_url : '';
$current_user    = $current_user ?? null;

// Shortcode parameters
$type                  = $atts['type'] ?? 'general';
$show_phone            = $atts['show_phone'] ?? '1';
$show_company          = $atts['show_company'] ?? '0';
$show_vehicle_selector = $atts['show_vehicle_selector'] ?? '0';
$show_priority         = $atts['show_priority'] ?? '0';
$show_attachment       = $atts['show_attachment'] ?? '1';
$show_booking_id_field = $atts['show_booking_id_field'] ?? '0';
$show_company_info     = $atts['show_company_info'] ?? '1';
$form_title            = (string) ( $atts['title'] ?? '' );
$form_description      = (string) ( $atts['description'] ?? '' );
$success_message       = (string) ( $atts['success_message'] ?? '' );
$theme                 = $atts['theme'] ?? 'default';
$class                 = $atts['class'] ?? '';
$unique_id             = uniqid('rv_contact_');
?>

<div class="rv-contact-form rv-theme-<?php echo esc_attr($theme); ?> <?php echo esc_attr($class); ?>"
	data-form-type="<?php echo esc_attr($type); ?>"
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

	<?php if ('' !== $form_title || '' !== $form_description) : ?>
		<div class="rv-contact-header">
			<?php if ('' !== $form_title) : ?>
				<h3 class="rv-contact-title"><?php echo esc_html($form_title); ?></h3>
			<?php endif; ?>
			<?php if ('' !== $form_description) : ?>
				<p class="rv-contact-description"><?php echo esc_html($form_description); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="rv-contact-form-container">
		<form id="rv-contact-form" class="rv-form" enctype="multipart/form-data">
			<?php wp_nonce_field('mhmrentiva_contact_nonce', 'rv_contact_nonce'); ?>

			<input type="hidden" name="type" value="<?php echo esc_attr($type); ?>">
			<input type="hidden" name="auto_reply" value="<?php echo esc_attr($atts['auto_reply'] ?? '1'); ?>">
			<?php if ('' !== $recipient_token) : ?>
				<input type="hidden" name="recipient_token" value="<?php echo esc_attr($recipient_token); ?>">
			<?php endif; ?>

			<div class="rv-form-row">
				<div class="rv-form-group">
					<label for="rv-contact-name-<?php echo esc_attr($unique_id); ?>" class="rv-form-label">
						<?php Icons::render('users'); ?>
						<?php echo esc_html__('Full Name', 'mhm-rentiva'); ?> <span class="rv-required">*</span>
					</label>
					<input type="text" id="rv-contact-name-<?php echo esc_attr($unique_id); ?>" name="name" class="rv-form-input"
						value="<?php echo esc_attr($current_user->display_name ?? ''); ?>" required>
				</div>

				<div class="rv-form-group">
					<label for="rv-contact-email-<?php echo esc_attr($unique_id); ?>" class="rv-form-label">
						<?php Icons::render('email'); ?>
						<?php echo esc_html__('Email', 'mhm-rentiva'); ?> <span class="rv-required">*</span>
					</label>
					<input type="email" id="rv-contact-email-<?php echo esc_attr($unique_id); ?>" name="email" class="rv-form-input"
						value="<?php echo esc_attr($current_user->user_email ?? ''); ?>" required>
				</div>
			</div>

			<?php if ($show_phone === '1' || $show_company === '1') : ?>
				<div class="rv-form-row">
					<?php if ($show_phone === '1') : ?>
					<div class="rv-form-group">
						<label for="rv-contact-phone-<?php echo esc_attr($unique_id); ?>" class="rv-form-label">
							<?php Icons::render('phone'); ?>
							<?php echo esc_html__('Phone', 'mhm-rentiva'); ?>
							<?php
							if ($type === 'booking') :
								?>
								<span class="rv-required">*</span><?php endif; ?>
						</label>
						<input type="tel" id="rv-contact-phone-<?php echo esc_attr($unique_id); ?>" name="phone" class="rv-form-input"
							placeholder="<?php echo esc_attr__('+90 5XX XXX XX XX', 'mhm-rentiva'); ?>">
					</div>
					<?php endif; ?>

					<?php if ($show_company === '1') : ?>
						<div class="rv-form-group">
							<label for="rv-contact-company-<?php echo esc_attr($unique_id); ?>" class="rv-form-label">
								<?php Icons::render('building'); ?>
								<?php echo esc_html__('Company', 'mhm-rentiva'); ?>
							</label>
							<input type="text" id="rv-contact-company-<?php echo esc_attr($unique_id); ?>" name="company" class="rv-form-input"
								placeholder="<?php echo esc_attr__('Company name', 'mhm-rentiva'); ?>">
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ($show_vehicle_selector === '1' && ! empty($vehicles)) : ?>
				<div class="rv-form-group">
					<label for="rv-contact-vehicle-<?php echo esc_attr($unique_id); ?>" class="rv-form-label">
						<?php Icons::render('car'); ?>
						<?php echo esc_html__('Vehicle', 'mhm-rentiva'); ?>
					</label>
					<select id="rv-contact-vehicle-<?php echo esc_attr($unique_id); ?>" name="vehicle_id" class="rv-form-select">
						<option value=""><?php echo esc_html__('Select vehicle...', 'mhm-rentiva'); ?></option>
						<?php foreach ($vehicles as $vehicle) : ?>
							<option value="<?php echo esc_attr($vehicle['id']); ?>">
								<?php echo esc_html($vehicle['title']); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<?php if ($show_booking_id_field === '1') : ?>
				<div class="rv-form-group">
					<label for="rv-contact-booking-id-<?php echo esc_attr($unique_id); ?>" class="rv-form-label">
						<?php Icons::render('calendar'); ?>
						<?php echo esc_html__('Booking ID', 'mhm-rentiva'); ?>
					</label>
					<input type="number" id="rv-contact-booking-id-<?php echo esc_attr($unique_id); ?>" name="booking_id" class="rv-form-input" min="1" step="1" inputmode="numeric">
				</div>
			<?php endif; ?>

			<?php if ($type === 'booking') : ?>
				<div class="rv-form-group">
					<label for="rv-contact-preferred-date-<?php echo esc_attr($unique_id); ?>" class="rv-form-label">
						<?php Icons::render('calendar'); ?>
						<?php echo esc_html__('Preferred Date', 'mhm-rentiva'); ?>
					</label>
					<input type="date" id="rv-contact-preferred-date-<?php echo esc_attr($unique_id); ?>" name="preferred_date" class="rv-form-input">
				</div>
			<?php endif; ?>

			<?php if ($show_priority === '1' && ! empty($priorities)) : ?>
				<div class="rv-form-group">
					<label for="rv-contact-priority-<?php echo esc_attr($unique_id); ?>" class="rv-form-label">
						<?php Icons::render('flag'); ?>
						<?php echo esc_html__('Priority', 'mhm-rentiva'); ?> <span class="rv-required">*</span>
					</label>
					<select id="rv-contact-priority-<?php echo esc_attr($unique_id); ?>" name="priority" class="rv-form-select" required>
						<option value=""><?php echo esc_html__('Select priority...', 'mhm-rentiva'); ?></option>
						<?php foreach ($priorities as $key => $priority) : ?>
							<option value="<?php echo esc_attr($key); ?>"
								data-color="<?php echo esc_attr($priority['color']); ?>">
								<?php echo esc_html($priority['label']); ?> - <?php echo esc_html($priority['description']); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<?php if ($type === 'feedback') : ?>
				<div class="rv-form-group">
					<label class="rv-form-label">
						<?php Icons::render('star'); ?>
						<?php echo esc_html__('Rating', 'mhm-rentiva'); ?> <span class="rv-required">*</span>
					</label>
					<div class="rv-rating-container">
						<?php for ($i = 1; $i <= 5; $i++) : ?>
							<label class="rv-rating-star">
								<input type="radio" name="rating" value="<?php echo esc_attr($i); ?>" required>
								<?php Icons::render('star'); ?>
							</label>
						<?php endfor; ?>
						<span class="rv-rating-label"><?php echo esc_html__('Rate us', 'mhm-rentiva'); ?></span>
					</div>
				</div>
			<?php endif; ?>

			<div class="rv-form-group">
				<label for="rv-contact-message-<?php echo esc_attr($unique_id); ?>" class="rv-form-label">
					<?php Icons::render('edit'); ?>
					<?php echo esc_html__('Message', 'mhm-rentiva'); ?> <span class="rv-required">*</span>
				</label>
				<textarea id="rv-contact-message-<?php echo esc_attr($unique_id); ?>" name="message" class="rv-form-textarea" rows="6"
					placeholder="<?php echo esc_attr__('Write your message here...', 'mhm-rentiva'); ?>" required></textarea>
			</div>

			<?php if ($show_attachment === '1') : ?>
				<div class="rv-form-group">
					<label for="rv-contact-attachment-<?php echo esc_attr($unique_id); ?>" class="rv-form-label">
						<?php Icons::render('attachment'); ?>
						<?php echo esc_html__('Attachment', 'mhm-rentiva'); ?>
					</label>
					<div class="rv-file-upload-container">
						<input type="file" id="rv-contact-attachment-<?php echo esc_attr($unique_id); ?>" name="attachment" class="rv-file-input"
							accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx">
						<label for="rv-contact-attachment-<?php echo esc_attr($unique_id); ?>" class="rv-file-upload-label">
							<?php Icons::render('upload'); ?>
							<?php echo esc_html__('Choose File', 'mhm-rentiva'); ?>
						</label>
						<div class="rv-file-info" style="display: none;">
							<span class="rv-file-name"></span>
							<button type="button" class="rv-file-remove">
								<?php Icons::render('remove'); ?>
							</button>
						</div>
					</div>
					<small class="rv-file-help">
						<?php echo esc_html__('Supported formats: JPG, PNG, GIF, PDF, DOC, DOCX (Max: ', 'mhm-rentiva'); ?>
						<?php echo esc_html(size_format(\MHMRentiva\Admin\Frontend\Shortcodes\ContactForm::max_attachment_bytes())); ?>)
					</small>
				</div>
			<?php endif; ?>

			<div class="rv-form-actions">
				<button type="submit" class="rv-button rv-button-primary rv-submit-button">
					<?php Icons::render('email'); ?>
					<?php echo esc_html__('Send Message', 'mhm-rentiva'); ?>
				</button>
				<button type="button" class="rv-button rv-button-secondary rv-reset-button">
					<?php Icons::render('refresh'); ?>
					<?php echo esc_html__('Reset', 'mhm-rentiva'); ?>
				</button>
			</div>
		</form>
	</div>

	<!-- Loading State -->
	<div class="rv-contact-loading" style="display: none;">
		<div class="rv-loading-spinner"></div>
		<p><?php echo esc_html__('Sending...', 'mhm-rentiva'); ?></p>
	</div>

	<!-- Success Message -->
	<div class="rv-contact-success" style="display: none;">
		<div class="rv-success-content">
			<?php Icons::render('success'); ?>
			<h4><?php echo esc_html__('Your Message Has Been Sent!', 'mhm-rentiva'); ?></h4>
			<p class="rv-success-message"></p>
			<div class="rv-success-actions">
				<button type="button" class="rv-button rv-button-primary rv-new-message-button">
					<?php echo esc_html__('Send New Message', 'mhm-rentiva'); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- Error Messages -->
	<div class="rv-contact-error" style="display: none;">
		<div class="rv-error-content">
			<?php Icons::render('warning'); ?>
			<h4><?php echo esc_html__('Error Occurred!', 'mhm-rentiva'); ?></h4>
			<p class="rv-error-message"></p>
			<ul class="rv-error-list"></ul>
		</div>
	</div>

	<?php if ($show_company_info === '1') : ?>
	<!-- Contact Info -->
	<div class="rv-contact-info">
		<h4><?php echo esc_html__('Contact Information', 'mhm-rentiva'); ?></h4>
		<div class="rv-contact-details">
			<div class="rv-contact-item">
				<?php Icons::render('email'); ?>
				<span><?php echo esc_html($support_email ?? ''); ?></span>
			</div>
			<div class="rv-contact-item">
				<?php Icons::render('phone'); ?>
				<span><?php echo esc_html($support_phone ?? ''); ?></span>
			</div>
			<div class="rv-contact-item">
				<?php Icons::render('clock'); ?>
				<span><?php echo esc_html($support_hours ?? ''); ?></span>
			</div>
		</div>
	</div>
	<?php endif; ?>
</div>

<?php
// ⭐ JavaScript localization removed - ContactForm Controller handles asset loading
// Assets are enqueued via ContactForm::enqueue_assets() method
// Localized data is provided via ContactForm::get_localized_strings() method
?>