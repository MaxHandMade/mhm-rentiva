<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use Exception;



/**
 * Contact Form Shortcode
 *
 * [rentiva_contact] - General contact form
 * [rentiva_contact type="booking"] - Booking inquiry form
 * [rentiva_contact type="support"] - Technical support form
 * [rentiva_contact type="feedback"] - Feedback form
 */
final class ContactForm extends AbstractShortcode {




	/**
	 * Safe sanitize text field that handles null values
	 *
	 * @param mixed $value Value to sanitize
	 * @return string
	 */
	public static function sanitize_text_field_safe($value): string
	{
		if ($value === null || $value === '') {
			return '';
		}
		return sanitize_text_field( (string) $value);
	}

	protected static function get_shortcode_tag(): string
	{
		return 'rentiva_contact';
	}

	protected static function get_template_path(): string
	{
		return 'shortcodes/contact-form';
	}

	protected static function get_default_attributes(): array
	{
		return array(
			'type'                  => 'general',     // Form type (general, booking, support, feedback)
			'title'                 => '',            // Custom title
			'description'           => '',            // Custom description
			'show_phone'            => '1',           // Show phone field
			'show_company'          => '0',           // Show company field
			'show_vehicle_selector' => '0',          // Show vehicle selector (for booking)
			'show_priority'         => '0',           // Show priority selector (for support)
			'show_attachment'       => '1',           // Show file attachment
			'redirect_url'          => '',            // Redirect after success
			'email_to'              => '',            // Custom email address
			'auto_reply'            => '1',           // Send auto reply
			'theme'                 => 'default',     // Theme (default, compact, detailed)
			'class'                 => '',            // Custom CSS class
		);
	}

	protected static function prepare_template_data(array $atts): array
	{
		return self::prepare_template_data_legacy($atts);
	}

	/**
	 * Load asset files
	 */
	protected static function enqueue_assets(array $atts = array()): void
	{
		// CSS
		wp_enqueue_style(
			'mhm-rentiva-contact-form',
			MHMRENTIVA_PLUGIN_URL . 'assets/css/frontend/contact-form.css',
			array(),
			MHMRENTIVA_VERSION
		);

		// JavaScript
		wp_enqueue_script(
			'mhm-rentiva-contact-form',
			MHMRENTIVA_PLUGIN_URL . 'assets/js/frontend/contact-form.js',
			array( 'jquery' ),
			MHMRENTIVA_VERSION,
			true
		);

		// Localize script
		self::localize_script('mhm-rentiva-contact-form');
	}

	protected static function register_ajax_handlers(): void
	{
		// AJAX handlers. (There used to be a second, standalone
		// mhmrentiva_upload_attachment endpoint here -- removed: nothing in
		// this plugin or Pro ever called it, the attachment field rides
		// along in this same submit request's multipart $_FILES instead, and
		// a publicly wp_ajax_nopriv_-reachable upload endpoint with no caller is
		// an unauthenticated input surface for no benefit. See
		// ajax_submit_contact_form()'s own $_FILES handling.)
		add_action('wp_ajax_mhmrentiva_submit_contact_form', array( self::class, 'ajax_submit_contact_form' ));
		add_action('wp_ajax_nopriv_mhmrentiva_submit_contact_form', array( self::class, 'ajax_submit_contact_form' ));
	}

	/**
	 * Legacy prepare_template_data method
	 *
	 * @param array $atts Attributes
	 * @return array
	 */
	private static function prepare_template_data_legacy(array $atts): array
	{
		$form_config = self::get_form_config( (string) ( $atts['type'] ?? 'general' ));

		// Vehicle list (for booking form)
		$vehicles = array();
		if (( $atts['show_vehicle_selector'] ?? '0' ) === '1') {
			$vehicles = self::get_vehicles();
		}

		// Priority options (for support form)
		$priorities = array();
		if (( $atts['show_priority'] ?? '0' ) === '1') {
			$priorities = self::get_priority_options();
		}

		// Email recipients
		$email_recipients = self::get_email_recipients( (string) ( $atts['type'] ?? 'general' ), (string) ( $atts['email_to'] ?? '' ));

		return array(
			'atts'             => $atts,
			'form_config'      => $form_config,
			'vehicles'         => $vehicles,
			'priorities'       => $priorities,
			'email_recipients' => $email_recipients,
			'current_user'     => wp_get_current_user(),
			'support_phone'    => (string) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhmrentiva_contact_phone', '+90 555 555 55 55'),
			'support_hours'    => (string) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhmrentiva_contact_hours', __('7/24 Support', 'mhm-rentiva')),
			'support_email'    => (string) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhmrentiva_support_email', get_option('admin_email')),
		);
	}

	private static function get_form_config(string $type): array
	{
		$configs = array(
			'general'  => array(
				'title'           => __('General Contact', 'mhm-rentiva'),
				'description'     => __('Contact us. We are happy to answer your questions.', 'mhm-rentiva'),
				'icon'            => 'dashicons-email-alt',
				'required_fields' => array( 'name', 'email', 'message' ),
				'optional_fields' => array( 'phone', 'company' ),
				'email_template'  => 'contact-general',
			),
			'booking'  => array(
				'title'           => __('Booking Inquiry', 'mhm-rentiva'),
				'description'     => __('Write to us to make a booking or get information about your existing booking.', 'mhm-rentiva'),
				'icon'            => 'dashicons-calendar-alt',
				'required_fields' => array( 'name', 'email', 'phone', 'message' ),
				'optional_fields' => array( 'vehicle_id', 'preferred_date', 'company' ),
				'email_template'  => 'contact-booking',
			),
			'support'  => array(
				'title'           => __('Technical Support', 'mhm-rentiva'),
				'description'     => __('Our support team will help you with your technical issues.', 'mhm-rentiva'),
				'icon'            => 'dashicons-sos',
				'required_fields' => array( 'name', 'email', 'priority', 'message' ),
				'optional_fields' => array( 'phone', 'company', 'attachment' ),
				'email_template'  => 'contact-support',
			),
			'feedback' => array(
				'title'           => __('Feedback', 'mhm-rentiva'),
				'description'     => __('Share your experience with us. Your feedback is valuable to us.', 'mhm-rentiva'),
				'icon'            => 'dashicons-star-filled',
				'required_fields' => array( 'name', 'email', 'rating', 'message' ),
				'optional_fields' => array( 'phone', 'company' ),
				'email_template'  => 'contact-feedback',
			),
		);

		return $configs[ $type ] ?? $configs['general'];
	}

	private static function get_vehicles(): array
	{
		$vehicles = get_posts(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);

		$vehicle_list = array();
		foreach ($vehicles as $vehicle) {
			$vehicle_list[] = array(
				'id'      => $vehicle->ID,
				'title'   => $vehicle->post_title,
				'excerpt' => wp_trim_words($vehicle->post_excerpt, 20),
			);
		}

		return $vehicle_list;
	}

	private static function get_priority_options(): array
	{
		return array(
			'low'    => array(
				'label'       => __('Low', 'mhm-rentiva'),
				'description' => __('General inquiries', 'mhm-rentiva'),
				'color'       => '#00a32a',
			),
			'medium' => array(
				'label'       => __('Medium', 'mhm-rentiva'),
				'description' => __('Important issues', 'mhm-rentiva'),
				'color'       => '#dba617',
			),
			'high'   => array(
				'label'       => __('High', 'mhm-rentiva'),
				'description' => __('Emergency cases', 'mhm-rentiva'),
				'color'       => '#d63638',
			),
		);
	}

	private static function get_email_recipients(string $type, string $custom_email = ''): array
	{
		if (! empty($custom_email)) {
			return array( $custom_email );
		}

		$default_emails = array(
			'general'  => get_option('admin_email'),
			'booking'  => get_option('mhmrentiva_booking_email', get_option('admin_email')),
			'support'  => get_option('mhmrentiva_support_email', get_option('admin_email')),
			'feedback' => get_option('mhmrentiva_feedback_email', get_option('admin_email')),
		);

		return array( $default_emails[ $type ] ?? get_option('admin_email') );
	}

	public static function ajax_submit_contact_form(): void
	{
		// Nonce check is the literal first statement -- before any $_POST/$_FILES
		// access -- so both the security guarantee and WPCS's own static analysis
		// can see it directly in this file, with no wrapper indirection to see
		// through.
		if (! check_ajax_referer('mhmrentiva_contact_form_nonce', 'nonce', false)) {
			self::ajax_error(__('Security check failed.', 'mhm-rentiva'));
			return;
		}

		try {
			// Rate limiting check. This is an unauthenticated endpoint that
			// also accepts file uploads, so the production limit is
			// deliberately tight -- 5 submissions per 5 minutes per bucket
			// (matches RateLimiter's own 'file_upload' minute budget). The
			// bucket itself is keyed off REMOTE_ADDR only, hashed -- see
			// SecurityHelper::get_client_ip()/check_rate_limit().
			$limit_time = 300; // 5 minutes
			\MHMRentiva\Admin\Core\SecurityHelper::check_rate_limit_or_die(
				'contact_form_submission',
				5,
				$limit_time,
				sprintf(
					/* translators: %d: number of minutes. */
					_n(
						'You have sent too many contact forms. Please wait %d minute.',
						'You have sent too many contact forms. Please wait %d minutes.',
						(int) ceil($limit_time / 60),
						'mhm-rentiva'
					),
					(int) ceil($limit_time / 60)
				)
			);

			// Field-by-field reads: each expected POST key is individually
			// checked and sanitized here (rather than bulk-passing the whole
			// $_POST superglobal into a helper the sniff cannot see through).
			// Sanitizer choice per field mirrors what sanitize_contact_form_data()
			// itself already applies internally (see that method below) --
			// this is deliberate double-sanitization-at-the-boundary, not a
			// change to the real business-logic sanitizers/validators.
			$post_data = array(
				'type'           => isset($_POST['type']) ? sanitize_text_field(wp_unslash( (string) $_POST['type'])) : 'general',
				'name'           => isset($_POST['name']) ? sanitize_text_field(wp_unslash( (string) $_POST['name'])) : '',
				'email'          => isset($_POST['email']) ? sanitize_email(wp_unslash( (string) $_POST['email'])) : '',
				'phone'          => isset($_POST['phone']) ? sanitize_text_field(wp_unslash( (string) $_POST['phone'])) : '',
				'company'        => isset($_POST['company']) ? sanitize_text_field(wp_unslash( (string) $_POST['company'])) : '',
				'vehicle_id'     => isset($_POST['vehicle_id']) ? absint(wp_unslash($_POST['vehicle_id'])) : 0,
				'preferred_date' => isset($_POST['preferred_date']) ? sanitize_text_field(wp_unslash( (string) $_POST['preferred_date'])) : '',
				'priority'       => isset($_POST['priority']) ? sanitize_text_field(wp_unslash( (string) $_POST['priority'])) : '',
				'rating'         => isset($_POST['rating']) ? absint(wp_unslash($_POST['rating'])) : 0,
				'message'        => isset($_POST['message']) ? sanitize_textarea_field(wp_unslash( (string) $_POST['message'])) : '',
				'attachment'     => isset($_POST['attachment']) ? sanitize_text_field(wp_unslash( (string) $_POST['attachment'])) : '',
				'auto_reply'     => isset($_POST['auto_reply']) ? sanitize_text_field(wp_unslash( (string) $_POST['auto_reply'])) : '1',
			);

			$form_data = self::sanitize_contact_form_data($post_data);

			// Handle file upload. Each leaf of the $_FILES sub-array is read and
			// sanitized/cast individually at the point of access (rather than
			// passing the raw superglobal slice through), matching the same
			// field-by-field discipline used for $_POST above.
			if (! empty($_FILES['attachment']['name'])) {
				$attachment_file = array(
					'name'     => isset($_FILES['attachment']['name']) ? sanitize_file_name(wp_unslash( (string) $_FILES['attachment']['name'])) : '',
					'type'     => isset($_FILES['attachment']['type']) ? sanitize_mime_type(wp_unslash( (string) $_FILES['attachment']['type'])) : '',
					'tmp_name' => isset($_FILES['attachment']['tmp_name']) ? sanitize_text_field(wp_unslash( (string) $_FILES['attachment']['tmp_name'])) : '',
					'error'    => isset($_FILES['attachment']['error']) ? (int) $_FILES['attachment']['error'] : UPLOAD_ERR_NO_FILE,
					'size'     => isset($_FILES['attachment']['size']) ? (int) $_FILES['attachment']['size'] : 0,
				);
				$upload_result   = self::handle_file_upload($attachment_file);

				if ($upload_result['success']) {
					$form_data['attachment'] = $upload_result['url'];
				} else {
					self::ajax_error($upload_result['message']);
					return;
				}
			}

			$validation_result = self::validate_form_data($form_data);

			if (! $validation_result['valid']) {
				self::ajax_error(
					$validation_result['message'],
					array(
						'errors' => $validation_result['errors'],
					)
				);
				return;
			}

			// Save message
			$message_id = self::save_contact_message($form_data);

			// Send email
			$email_sent = self::send_contact_email($form_data, $message_id);

			// Send auto reply
			if ($form_data['auto_reply'] === '1') {
				self::send_auto_reply($form_data);
			}

			self::ajax_success(
				array(
					'message_id' => $message_id,
					'email_sent' => $email_sent,
				),
				__('Your message has been sent successfully!', 'mhm-rentiva')
			);
		} catch (\InvalidArgumentException $e) {
			self::ajax_error($e->getMessage());
		} catch (Exception $e) {
			self::debug_log('Contact form submission error: ' . $e->getMessage());
			$debug_mode = defined('WP_DEBUG') && WP_DEBUG;
			$message    = \MHMRentiva\Admin\Core\SecurityHelper::get_safe_error_message(
				$e->getMessage(),
				$debug_mode
			);
			self::ajax_error($message);
		}
	}

	/**
	 * Script object name override
	 * The JS file expects an mhmContactForm object to be present.
	 */
	protected static function get_script_object_name(): string
	{
		return 'mhmContactForm';
	}

	/**
	 * Localized data override
	 */
	protected static function get_localized_data(): array
	{
		return array(
			'ajaxUrl'          => admin_url('admin-ajax.php'),
			'nonce'            => wp_create_nonce('mhmrentiva_contact_form_nonce'),
			'maxFileSize'      => wp_max_upload_size(),
			'allowedFileTypes' => array( 'jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx' ),
			'messages'         => array(
				'submitting'      => __('Sending...', 'mhm-rentiva'),
				'success'         => __('Your message has been sent successfully.', 'mhm-rentiva'),
				'error'           => __('An error occurred while sending message.', 'mhm-rentiva'),
				'required_fields' => __('Please fill in all required fields.', 'mhm-rentiva'),
				'confirm_reset'   => __('Are you sure you want to reset the form?', 'mhm-rentiva'),
			),
			'icons'            => array(
				'success' => \MHMRentiva\Helpers\Icons::get('success', array( 'class' => 'rv-icon-success' )),
				'warning' => \MHMRentiva\Helpers\Icons::get('warning', array( 'class' => 'rv-icon-warning' )),
			),
			'strings'          => self::get_localized_strings(),
		);
	}

	/**
	 * Localized strings override
	 */
	protected static function get_localized_strings(): array
	{
		return array(
			'submitting'        => __('Sending...', 'mhm-rentiva'),
			'sending'           => __('Sending...', 'mhm-rentiva'),
			'success'           => __('Your message has been sent successfully!', 'mhm-rentiva'),
			'error'             => __('An error occurred while sending message.', 'mhm-rentiva'),
			'validation_error'  => __('Please fill in all required fields.', 'mhm-rentiva'),
			'required_fields'   => __('Please fill in all required fields.', 'mhm-rentiva'),
			'file_too_large'    => __('File size is too large.', 'mhm-rentiva'),
			'invalid_file_type' => __('Invalid file type.', 'mhm-rentiva'),
			'loading'           => __('Loading...', 'mhm-rentiva'),
			'confirm_reset'     => __('Are you sure you want to reset the form?', 'mhm-rentiva'),
		);
	}

	/**
	 * Contact form specific sanitization
	 */
	private static function sanitize_contact_form_data(array $data): array
	{
		return array(
			'type'           => self::sanitize_text_field_safe($data['type'] ?? 'general'),
			'name'           => self::sanitize_text_field_safe($data['name'] ?? ''),
			'email'          => \MHMRentiva\Admin\Core\SecurityHelper::validate_email($data['email'] ?? ''),
			'phone'          => \MHMRentiva\Admin\Core\SecurityHelper::validate_phone($data['phone'] ?? ''),
			'company'        => self::sanitize_text_field_safe($data['company'] ?? ''),
			'vehicle_id'     => intval($data['vehicle_id'] ?? 0),
			'preferred_date' => self::sanitize_text_field_safe($data['preferred_date'] ?? ''),
			'priority'       => self::sanitize_text_field_safe($data['priority'] ?? ''),
			'rating'         => intval($data['rating'] ?? 0),
			'message'        => ( $data['message'] ?? '' ) !== null ? sanitize_textarea_field( (string) ( $data['message'] ?? '' )) : '',
			'attachment'     => self::sanitize_text_field_safe($data['attachment'] ?? ''),
			'auto_reply'     => self::sanitize_text_field_safe($data['auto_reply'] ?? '1'),
			'ip_address'     => sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? '')),
			'user_agent'     => sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? '')),
			'timestamp'      => current_time('mysql'),
		);
	}

	private static function validate_form_data(array $data): array
	{
		$errors          = array();
		$required_fields = self::get_required_fields($data['type']);

		foreach ($required_fields as $field) {
			if (empty($data[ $field ])) {
				/* translators: %s: field label. */
				$errors[ $field ] = sprintf(__('%s field is required.', 'mhm-rentiva'), self::get_field_label($field));
			}
		}

		// Email validation
		if (! empty($data['email']) && ! is_email($data['email'])) {
			$errors['email'] = __('Please enter a valid email address.', 'mhm-rentiva');
		}

		// Phone validation
		if (! empty($data['phone']) && ! preg_match('/^[\+]?[0-9\s\-\(\)]{10,}$/', $data['phone'])) {
			$errors['phone'] = __('Please enter a valid phone number.', 'mhm-rentiva');
		}

		// Rating validation
		if ($data['type'] === 'feedback' && ( $data['rating'] < 1 || $data['rating'] > 5 )) {
			$errors['rating'] = __('Please rate between 1-5.', 'mhm-rentiva');
		}

		return array(
			'valid'   => empty($errors),
			'errors'  => $errors,
			'message' => empty($errors) ? '' : __('Please fix form errors.', 'mhm-rentiva'),
		);
	}

	private static function get_required_fields(string $type): array
	{
		$configs = array(
			'general'  => array( 'name', 'email', 'message' ),
			'booking'  => array( 'name', 'email', 'phone', 'message' ),
			'support'  => array( 'name', 'email', 'priority', 'message' ),
			'feedback' => array( 'name', 'email', 'rating', 'message' ),
		);

		return $configs[ $type ] ?? $configs['general'];
	}

	private static function get_field_label(string $field): string
	{
		$labels = array(
			'name'           => __('Full Name', 'mhm-rentiva'),
			'email'          => __('Email', 'mhm-rentiva'),
			'phone'          => __('Phone', 'mhm-rentiva'),
			'company'        => __('Company', 'mhm-rentiva'),
			'vehicle_id'     => __('Vehicle', 'mhm-rentiva'),
			'preferred_date' => __('Preferred Date', 'mhm-rentiva'),
			'priority'       => __('Priority', 'mhm-rentiva'),
			'rating'         => __('Rating', 'mhm-rentiva'),
			'message'        => __('Message', 'mhm-rentiva'),
		);

		return $labels[ $field ] ?? $field;
	}

	private static function save_contact_message(array $data): int
	{
		$post_data = array(
			// 18 chars. wp_posts.post_type is varchar(20); the pre-fix literal
			// was 26 and truncated or errored on every submission. Mapped in
			// PrefixMigrationMap::POST_TYPES so the length is gate-checked.
			'post_type'    => 'mhmrentiva_contact',
			/* translators: %s: customer name. */
			'post_title'   => sprintf(__('Contact Message - %s', 'mhm-rentiva'), $data['name']),
			'post_content' => $data['message'],
			'post_status'  => 'private',
			// No author. Hardcoding 1 made whoever holds that ID the owner of
			// every contact message, and `wp_delete_user( 1, $reassign )` hands
			// ownership -- and with `post` capability mapping, read and delete
			// rights -- to the reassignment target, which can be a role far
			// below administrator.
			'post_author'  => 0,
			'meta_input'   => array(
				'_mhmrentiva_contact_type'           => $data['type'],
				'_mhmrentiva_contact_name'           => $data['name'],
				'_mhmrentiva_contact_email'          => $data['email'],
				'_mhmrentiva_contact_phone'          => $data['phone'],
				'_mhmrentiva_contact_company'        => $data['company'],
				'_mhmrentiva_contact_vehicle_id'     => $data['vehicle_id'],
				'_mhmrentiva_contact_preferred_date' => $data['preferred_date'],
				'_mhmrentiva_contact_priority'       => $data['priority'],
				'_mhmrentiva_contact_rating'         => $data['rating'],
				'_mhmrentiva_contact_attachment'     => $data['attachment'],
				'_mhmrentiva_contact_ip_address'     => $data['ip_address'],
				'_mhmrentiva_contact_user_agent'     => $data['user_agent'],
				'_mhmrentiva_contact_timestamp'      => $data['timestamp'],
				'_mhmrentiva_contact_status'         => 'new',
			),
		);

		// $wp_error = true. WordPress returns 0 -- not WP_Error -- on failure
		// unless asked, so the is_wp_error() guard alone let a failed insert
		// through: the visitor was told "Your message has been sent
		// successfully!", the notification mail went out with message id 0,
		// and nothing was ever stored for the site owner to answer.
		$message_id = wp_insert_post($post_data, true);

		if (is_wp_error($message_id) || (int) $message_id <= 0) {
			throw new Exception(esc_html__('Unable to save the message.', 'mhm-rentiva'));
		}

		return (int) $message_id;
	}

	private static function send_contact_email(array $data, int $message_id): bool
	{
		$form_config      = self::get_form_config($data['type']);
		$email_recipients = self::get_email_recipients($data['type']);

		$subject = sprintf(
			/* translators: 1: Site name, 2: Form title, 3: Message subject */
			__('[%1$s] %2$s - %3$s', 'mhm-rentiva'),
			get_bloginfo('name'),
			$form_config['title'],
			$data['name']
		);

		$message = self::build_email_message($data, $form_config, $message_id);

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
			'Reply-To: ' . $data['name'] . ' <' . $data['email'] . '>',
		);

		// Defined before the branch, not inside it. Every submission without a
		// file used to reach wp_mail() with this variable never assigned: PHP 8
		// warns and passes null, and WordPress hands that null to str_replace()
		// in pluggable.php for a deprecation on top. The mail still sent, so
		// only a debug log ever showed it -- and this is a public,
		// unauthenticated form, which is where a WordPress.org reviewer running
		// with WP_DEBUG looks first.
		$attachments = array();

		if (! empty($data['attachment'])) {
			$attachment_path = self::resolve_attachment_path($data['attachment']);
			if ($attachment_path) {
				$attachments[] = $attachment_path;
			}
		}

		return wp_mail($email_recipients, $subject, $message, $headers, $attachments);
	}

	/**
	 * Resolve an attachment URL to a local filesystem path.
	 *
	 * `$url` reaches this method as attacker-reachable free text -- it is
	 * stored straight from `sanitize_text_field()`'d POST data and is not
	 * guaranteed to be the URL `wp_handle_upload()` produced. It is
	 * resolved via `wp_upload_dir()` (baseurl -> basedir mapping) rather
	 * than string surgery on `site_url()`/`ABSPATH`, which breaks on
	 * subdirectory, multisite, and mapped-domain installs.
	 *
	 * Anything that is not verifiably inside this site's own uploads
	 * directory is rejected outright (never guessed at) to avoid SSRF/LFI:
	 * a bare filesystem path, a foreign host, or a "../" escape must all
	 * resolve to null rather than a wrong or attacker-controlled path.
	 */
	private static function resolve_attachment_path(string $url): ?string
	{
		if (empty($url)) {
			return null;
		}

		$upload_dir = wp_upload_dir();
		if (! empty($upload_dir['error'])) {
			return null;
		}

		$url_parts  = wp_parse_url(urldecode($url));
		$base_parts = wp_parse_url($upload_dir['baseurl']);

		// Require an absolute URL on this site's own uploads host. Rejects
		// bare filesystem paths (no 'host') and foreign hosts alike.
		if (
			empty($url_parts['host']) || empty($base_parts['host'])
			|| strcasecmp($url_parts['host'], $base_parts['host']) !== 0
		) {
			return null;
		}

		$base_path = isset($base_parts['path']) ? untrailingslashit($base_parts['path']) : '';
		$url_path  = $url_parts['path'] ?? '';

		if ($base_path !== '') {
			// Normal case: baseurl carries a path (e.g. /wp-content/uploads,
			// or /wp-content/uploads/sites/2 on a multisite subdirectory
			// network). Require the URL to sit under it -- this is a cheap
			// pre-filter, not the security boundary itself (that is the
			// realpath() containment check below).
			if (strpos($url_path, $base_path . '/') !== 0) {
				return null;
			}
			$relative_path = substr($url_path, strlen($base_path));
		} else {
			// Some CDN / media-offload configurations serve uploads from a
			// path-less host (e.g. baseurl "https://cdn.example.com" with
			// no path component), so there is no string prefix to check
			// here. Do NOT hard-reject a legit attachment on that config --
			// fall through with the URL's own path as the relative segment.
			// Host equality already passed above, and the realpath()
			// containment recheck below (resolved candidate must still
			// land inside realpath($basedir)) remains the real security
			// boundary regardless of this branch, so skipping the prefix
			// pre-check here does not weaken containment.
			if ($url_path === '') {
				return null;
			}
			$relative_path = $url_path;
		}

		$candidate = wp_normalize_path(untrailingslashit($upload_dir['basedir']) . $relative_path);

		// realpath() resolves symlinks/".." and doubles as the file-exists
		// check (returns false for anything missing).
		$real_base = realpath($upload_dir['basedir']);
		$real_path = realpath($candidate);

		if ($real_base === false || $real_path === false || ! is_file($real_path)) {
			return null;
		}

		$real_base = wp_normalize_path($real_base);
		$real_path = wp_normalize_path($real_path);

		if (strpos($real_path, $real_base . '/') !== 0) {
			return null;
		}

		return $real_path;
	}

	private static function build_email_message(array $data, array $form_config, int $message_id): string
	{
		$message  = '<html><body>';
		$message .= '<h2>' . esc_html($form_config['title']) . '</h2>';
		$message .= '<p><strong>' . __('Message ID:', 'mhm-rentiva') . '</strong> ' . $message_id . '</p>';
		$message .= '<p><strong>' . __('From:', 'mhm-rentiva') . '</strong> ' . esc_html($data['name']) . '</p>';
		$message .= '<p><strong>' . __('Email:', 'mhm-rentiva') . '</strong> ' . esc_html($data['email']) . '</p>';

		if (! empty($data['phone'])) {
			$message .= '<p><strong>' . __('Phone:', 'mhm-rentiva') . '</strong> ' . esc_html($data['phone']) . '</p>';
		}

		if (! empty($data['company'])) {
			$message .= '<p><strong>' . __('Company:', 'mhm-rentiva') . '</strong> ' . esc_html($data['company']) . '</p>';
		}

		if (! empty($data['vehicle_id'])) {
			$vehicle  = get_post($data['vehicle_id']);
			$message .= '<p><strong>' . __('Vehicle:', 'mhm-rentiva') . '</strong> ' . esc_html($vehicle->post_title ?? '') . '</p>';
		}

		if (! empty($data['preferred_date'])) {
			$message .= '<p><strong>' . __('Preferred Date:', 'mhm-rentiva') . '</strong> ' . esc_html($data['preferred_date']) . '</p>';
		}

		if (! empty($data['priority'])) {
			$priorities     = self::get_priority_options();
			$priority_label = $priorities[ $data['priority'] ]['label'] ?? $data['priority'];
			$message       .= '<p><strong>' . __('Priority:', 'mhm-rentiva') . '</strong> ' . esc_html($priority_label) . '</p>';
		}

		if (! empty($data['rating'])) {
			$message .= '<p><strong>' . __('Rating:', 'mhm-rentiva') . '</strong> ' . $data['rating'] . '/5 ⭐</p>';
		}

		$message .= '<h3>' . __('Message:', 'mhm-rentiva') . '</h3>';
		$message .= '<p>' . nl2br(esc_html($data['message'])) . '</p>';

		if (! empty($data['attachment'])) {
			$message .= '<p><strong>' . __('Attachment:', 'mhm-rentiva') . '</strong> <a href="' . esc_url($data['attachment']) . '">' . __('Download File', 'mhm-rentiva') . '</a></p>';
		}

		$message .= '<hr>';
		$message .= '<p><small>' . __('IP Address:', 'mhm-rentiva') . ' ' . esc_html($data['ip_address']) . '</small></p>';
		$message .= '<p><small>' . __('Sent Date:', 'mhm-rentiva') . ' ' . esc_html($data['timestamp']) . '</small></p>';
		$message .= '</body></html>';

		return $message;
	}

	private static function send_auto_reply(array $data): bool
	{
		/* translators: %s: site name. */
		$subject = sprintf(__('[%s] Your Message Received', 'mhm-rentiva'), get_bloginfo('name'));

		$message  = '<html><body>';
		$message .= '<h2>' . esc_html__('Your Message Received', 'mhm-rentiva') . '</h2>';
		/* translators: %s: customer name. */
		$message .= '<p>' . sprintf(esc_html__('Hello %s,', 'mhm-rentiva'), esc_html($data['name'])) . '</p>';
		$message .= '<p>' . __('Your message has been successfully received. We will get back to you as soon as possible.', 'mhm-rentiva') . '</p>';
		$message .= '<p>' . __('Thank you,', 'mhm-rentiva') . '<br>' . get_bloginfo('name') . '</p>';
		$message .= '</body></html>';

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
		);

		return wp_mail($data['email'], $subject, $message, $headers);
	}

	private static function handle_file_upload(array $file): array
	{
		// File size check
		if ($file['size'] > wp_max_upload_size()) {
			return array(
				'success' => false,
				'message' => __('File size is too large.', 'mhm-rentiva'),
			);
		}

		// Sanitize the client-supplied filename before trusting any part of it.
		$sanitized_name = sanitize_file_name( (string) ( $file['name'] ?? '' ));

		// File type check: never trust $file['type'] (the client-supplied MIME
		// type) -- validate the real extension via wp_check_filetype() instead.
		$allowed_types  = array( 'jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx' );
		$filetype       = wp_check_filetype($sanitized_name);
		$file_extension = ! empty($filetype['ext']) ? strtolower($filetype['ext']) : '';

		if ('' === $file_extension || ! in_array($file_extension, $allowed_types, true)) {
			return array(
				'success' => false,
				'message' => __('Invalid file type.', 'mhm-rentiva'),
			);
		}

		$file['name'] = $sanitized_name;

		// wp_handle_upload() lives in wp-admin/includes/file.php. Today the only
		// caller is the AJAX submit handler, and admin-ajax.php loads the admin
		// API before firing any hook, so the function happens to exist. That is
		// a property of the current caller, not of this method: called from a
		// shortcode, a block render or a REST route it would be a fatal, which
		// is exactly how /customers/bulk shipped broken for three months.
		require_once ABSPATH . 'wp-admin/includes/file.php';

		// This is an unauthenticated, wp_ajax_nopriv_ upload endpoint. Keeping
		// the caller's (sanitized) filename made every uploaded attachment's
		// name -- and therefore its public URL under uploads/ -- predictable
		// from the submitted form alone, with no auth required to guess it.
		// wp_handle_upload()'s unique_filename_callback replaces the name
		// with a random one before the file ever touches disk; the extension
		// (already validated above) is preserved so MIME/type sniffing on
		// download is unaffected.
		$upload = wp_handle_upload(
			$file,
			array(
				'test_form'                => false,
				'unique_filename_callback' => static function ($dir, $name, $ext) {
					unset($dir, $name);
					return wp_generate_password(20, false, false) . $ext;
				},
			)
		);

		if (isset($upload['error'])) {
			return array(
				'success' => false,
				'message' => $upload['error'],
			);
		}

		return array(
			'success' => true,
			'url'     => $upload['url'],
			'name'    => basename($upload['file']),
		);
	}
}
