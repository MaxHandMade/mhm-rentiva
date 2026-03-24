<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * My Account - Messages Template
 *
 * @var WP_User $user
 * @var string $customer_email
 * @var string $customer_name
 * @var array $navigation
 */

if (! defined('ABSPATH')) {
	exit;
}



// Get message categories and priorities
$categories = array();
$priorities = array();
if (class_exists(\MHMRentiva\Admin\Messages\Settings\MessagesSettings::class)) {
	$categories = \MHMRentiva\Admin\Messages\Settings\MessagesSettings::get_categories();
	$priorities = \MHMRentiva\Admin\Messages\Settings\MessagesSettings::get_priorities();
}

// Ensure variables are set (from template data)
$user           = $data['user'] ?? wp_get_current_user();
$customer_email = $data['customer_email'] ?? ($user->user_email ?? '');
$customer_name  = $data['customer_name'] ?? ($user->display_name ?? $user->user_login ?? '');
$navigation     = $data['navigation'] ?? array();

// REST API URL - use helper for consistency
$rest_url   = \MHMRentiva\Admin\Messages\Core\MessageUrlHelper::get_rest_url();
$rest_nonce = wp_create_nonce('wp_rest');

$wrapper_class = 'mhm-rentiva-account-page';
if (empty($navigation)) {
	$wrapper_class .= ' mhm-integrated';
}
?>

<div class="<?php echo esc_attr($wrapper_class); ?>">

	<!-- Account Navigation -->
	<?php if (! empty($navigation)) : ?>
		<?php echo wp_kses_post(\MHMRentiva\Admin\Core\Utilities\Templates::render('account/navigation', array('navigation' => $navigation), true)); ?>
	<?php endif; ?>

	<!-- Messages Content -->
	<div class="mhm-account-content">
		<div class="mhm-messages-section">

			<!-- Header -->
			<div class="section-header">
				<h2><?php esc_html_e('Messages', 'mhm-rentiva'); ?></h2>
				<button type="button" id="new-message-btn" class="btn btn-primary">
					<?php esc_html_e('New Message', 'mhm-rentiva'); ?>
				</button>
			</div>

			<!-- Messages List -->
			<div id="messages-list" class="messages-list">
				<div class="loading"><?php esc_html_e('Loading messages...', 'mhm-rentiva'); ?></div>
			</div>

			<!-- Message Thread View (Hidden by default) -->
			<div id="message-thread" class="message-thread hidden">
				<div class="thread-header">
					<button type="button" class="back-to-list btn btn-secondary">
						â† <?php esc_html_e('Back to Messages', 'mhm-rentiva'); ?>
					</button>
					<h3 id="thread-subject"></h3>
				</div>
				<div id="thread-messages" class="thread-messages"></div>
				<div id="thread-reply" class="thread-reply hidden">
					<form id="reply-form">
						<div class="form-group">
							<label for="reply-message"><?php esc_html_e('Your Reply:', 'mhm-rentiva'); ?></label>
							<textarea id="reply-message" name="message" rows="4" required></textarea>
						</div>
						<div class="form-actions">
							<button type="submit" class="btn btn-primary">
								<?php esc_html_e('Send Reply', 'mhm-rentiva'); ?>
							</button>
							<button type="button" class="btn btn-secondary cancel-reply">
								<?php esc_html_e('Cancel', 'mhm-rentiva'); ?>
							</button>
						</div>
					</form>
				</div>
			</div>

			<!-- New Message Form (Hidden by default) -->
			<div id="new-message-form" class="new-message-form hidden">
				<div class="form-header">
					<h4><?php esc_html_e('Send New Message', 'mhm-rentiva'); ?></h4>
					<button type="button" class="close-form">&times;</button>
				</div>
				<form id="send-message-form">
					<div class="form-group">
						<label for="message-category"><?php esc_html_e('Category:', 'mhm-rentiva'); ?></label>
						<select id="message-category" name="category" required>
							<?php if (! empty($categories)) : ?>
								<?php foreach ($categories as $key => $label) : ?>
									<option value="<?php echo esc_attr($key); ?>">
										<?php echo esc_html($label); ?>
									</option>
								<?php endforeach; ?>
							<?php else : ?>
								<option value="general"><?php esc_html_e('General', 'mhm-rentiva'); ?></option>
								<option value="support"><?php esc_html_e('Support', 'mhm-rentiva'); ?></option>
								<option value="billing"><?php esc_html_e('Billing', 'mhm-rentiva'); ?></option>
								<option value="technical"><?php esc_html_e('Technical', 'mhm-rentiva'); ?></option>
							<?php endif; ?>
						</select>
					</div>

					<div class="form-group">
						<label for="message-subject"><?php esc_html_e('Subject:', 'mhm-rentiva'); ?></label>
						<input type="text" id="message-subject" name="subject" class="regular-text" required>
					</div>

					<div class="form-group">
						<label for="message-content"><?php esc_html_e('Your Message:', 'mhm-rentiva'); ?></label>
						<textarea id="message-content" name="message" rows="6" required></textarea>
					</div>

					<div class="form-group">
						<label for="message-priority"><?php esc_html_e('Priority:', 'mhm-rentiva'); ?></label>
						<select id="message-priority" name="priority" required>
							<?php if (! empty($priorities)) : ?>
								<?php foreach ($priorities as $key => $label) : ?>
									<option value="<?php echo esc_attr($key); ?>" <?php selected($key, 'normal'); ?>>
										<?php echo esc_html($label); ?>
									</option>
								<?php endforeach; ?>
							<?php else : ?>
								<option value="normal"><?php esc_html_e('Normal', 'mhm-rentiva'); ?></option>
								<option value="high"><?php esc_html_e('High', 'mhm-rentiva'); ?></option>
								<option value="urgent"><?php esc_html_e('Urgent', 'mhm-rentiva'); ?></option>
							<?php endif; ?>
						</select>
					</div>

					<div class="form-group">
						<label for="message-booking"><?php esc_html_e('Booking Association (Optional):', 'mhm-rentiva'); ?></label>
						<select id="message-booking" name="booking_id">
							<option value=""><?php esc_html_e('Select booking', 'mhm-rentiva'); ?></option>
							<!-- Will be populated via AJAX if needed -->
						</select>
					</div>

					<div class="form-actions">
						<button type="submit" class="btn btn-primary">
							<?php esc_html_e('Send Message', 'mhm-rentiva'); ?>
						</button>
						<button type="button" class="btn btn-secondary close-form">
							<?php esc_html_e('Cancel', 'mhm-rentiva'); ?>
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>