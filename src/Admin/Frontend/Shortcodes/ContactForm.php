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
final class ContactForm extends AbstractShortcode
{



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
		return sanitize_text_field((string) $value);
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
			MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/contact-form.css',
			array(),
			MHM_RENTIVA_VERSION
		);

		// JavaScript
		wp_enqueue_script(
			'mhm-rentiva-contact-form',
			MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/contact-form.js',
			array('jquery'),
			MHM_RENTIVA_VERSION,
			true
		);

		// Localize script
		self::localize_script('mhm-rentiva-contact-form');
	}

	protected static function register_ajax_handlers(): void
	{
		// AJAX handlers
		add_action('wp_ajax_mhm_rentiva_submit_contact_form', array(self::class, 'ajax_submit_contact_form'));
		add_action('wp_ajax_nopriv_mhm_rentiva_submit_contact_form', array(self::class, 'ajax_submit_contact_form'));

		add_action('wp_ajax_mhm_rentiva_upload_attachment', array(self::class, 'ajax_upload_attachment'));
		add_action('wp_ajax_nopriv_mhm_rentiva_upload_attachment', array(self::class, 'ajax_upload_attachment'));
	}

	/**
	 * Legacy prepare_template_data method
	 *
	 * @param array $atts Attributes
	 * @return array
	 */
	private static function prepare_template_data_legacy(array $atts): array
	{
		$form_config = self::get_form_config((string) ($atts['type'] ?? 'general'));

		// Vehicle list (for booking form)
		$vehicles = array();
		if (($atts['show_vehicle_selector'] ?? '0') === '1') {
			$vehicles = self::get_vehicles();
		}

		// Priority options (for support form)
		$priorities = array();
		if (($atts['show_priority'] ?? '0') === '1') {
			$priorities = self::get_priority_options();
		}

		// Email recipients
		$email_recipients = self::get_email_recipients((string) ($atts['type'] ?? 'general'), (string) ($atts['email_to'] ?? ''));

		return array(
			'atts'             => $atts,
			'form_config'      => $form_config,
			'vehicles'         => $vehicles,
			'priorities'       => $priorities,
			'email_recipients' => $email_recipients,
			'current_user'     => wp_get_current_user(),
			'support_phone'    => (string) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_contact_phone', '+90 555 555 55 55'),
			'support_hours'    => (string) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_contact_hours', __('7/24 Support', 'mhm-rentiva')),
			'support_email'    => (string) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_support_email', get_option('admin_email')),
		);
	}

	private static function get_form_config(string $type): array
	{
		$configs = array(
			'general'  => array(
				'title'           => __('General Contact', 'mhm-rentiva'),
				'description'     => __('Contact us. We are happy to answer your questions.', 'mhm-rentiva'),
				'icon'            => 'dashicons-email-alt',
				'required_fields' => array('name', 'email', 'message'),
				'optional_fields' => array('phone', 'company'),
				'email_template'  => 'contact-general',
			),
			'booking'  => array(
				'title'           => __('Booking Inquiry', 'mhm-rentiva'),
				'description'     => __('Write to us to make a booking or get information about your existing booking.', 'mhm-rentiva'),
				'icon'            => 'dashicons-calendar-alt',
				'required_fields' => array('name', 'email', 'phone', 'message'),
				'optional_fields' => array('vehicle_id', 'preferred_date', 'company'),
				'email_template'  => 'contact-booking',
			),
			'support'  => array(
				'title'           => __('Technical Support', 'mhm-rentiva'),
				'description'     => __('Our support team will help you with your technical issues.', 'mhm-rentiva'),
				'icon'            => 'dashicons-sos',
				'required_fields' => array('name', 'email', 'priority', 'message'),
				'optional_fields' => array('phone', 'company', 'attachment'),
				'email_template'  => 'contact-support',
			),
			'feedback' => array(
				'title'           => __('Feedback', 'mhm-rentiva'),
				'description'     => __('Share your experience with us. Your feedback is valuable to us.', 'mhm-rentiva'),
				'icon'            => 'dashicons-star-filled',
				'required_fields' => array('name', 'email', 'rating', 'message'),
				'optional_fields' => array('phone', 'company'),
				'email_template'  => 'contact-feedback',
			),
		);

		return $configs[$type] ?? $configs['general'];
	}

	private static function get_vehicles(): array
	{
		$vehicles = get_posts(
			array(
				'post_type'   => 'vehicle',
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
			return array($custom_email);
		}

		$default_emails = array(
			'general'  => get_option('admin_email'),
			'booking'  => get_option('mhm_rentiva_booking_email', get_option('admin_email')),
			'support'  => get_option('mhm_rentiva_support_email', get_option('admin_email')),
			'feedback' => get_option('mhm_rentiva_feedback_email', get_option('admin_email')),
		);

		return array($default_emails[$type] ?? get_option('admin_email'));
	}

	public static function ajax_submit_contact_form(): void
	{
		try {
			// Security checks
			\MHMRentiva\Admin\Core\SecurityHelper::verify_ajax_request_or_die(
				'mhm_rentiva_contact_form_nonce',
				'read',
				__('Security check failed.', 'mhm-rentiva')
			);

			// Rate limiting check
			$limit_time = 300; // 5 minutes
			\MHMRentiva\Admin\Core\SecurityHelper::check_rate_limit_or_die(
				'contact_form_submission',
				50, // 50 requests (Increased for testing)
				$limit_time,
				/* translators: %d: number of minutes. */
				sprintf(__('You have sent too many contact forms. Please wait %d minutes.', 'mhm-rentiva'), (int) ceil($limit_time / 60))
			);

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is already verified by verify_ajax_request_or_die() above.
			$form_data = self::sanitize_contact_form_data(wp_unslash(isset($_POST) && is_array($_POST) ? $_POST : array()));

			// Handle file upload
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is already verified by verify_ajax_request_or_die() above.
			if (! empty($_FILES['attachment']['name'])) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is already verified by verify_ajax_request_or_die(); file array is validated in handle_file_upload().
				$upload_result = self::handle_file_upload($_FILES['attachment']);

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

	public static function ajax_upload_attachment(): void
	{
		try {
			// Nonce check
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is validated via verify_nonce() in this condition.
			if (! self::verify_nonce(isset($_POST['nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['nonce'])) : '')) {
				self::ajax_error(__('Security check failed.', 'mhm-rentiva'));
				return;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is validated via verify_nonce() above.
			if (! isset($_FILES['attachment'])) {
				self::ajax_error(__('File not found.', 'mhm-rentiva'));
				return;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is validated via verify_nonce() above; file array is validated in handle_file_upload().
			$file          = $_FILES['attachment'];
			$upload_result = self::handle_file_upload($file);

			if ($upload_result['success']) {
				self::ajax_success(
					array(
						'file_url'  => $upload_result['url'],
						'file_name' => $upload_result['name'],
					),
					__('File uploaded successfully.', 'mhm-rentiva')
				);
			} else {
				self::ajax_error($upload_result['message']);
			}
		} catch (Exception $e) {
			self::ajax_error(__('An error occurred while uploading file.', 'mhm-rentiva'));
		}
	}

	/**
	 * Script object name override
	 * JS dosyası mhmContactForm objesini bekliyor
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
			'nonce'            => wp_create_nonce('mhm_rentiva_contact_form_nonce'),
			'maxFileSize'      => wp_max_upload_size(),
			'allowedFileTypes' => array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'),
			'messages'         => array(
				'submitting'      => __('Sending...', 'mhm-rentiva'),
				'success'         => __('Your message has been sent successfully.', 'mhm-rentiva'),
				'error'           => __('An error occurred while sending message.', 'mhm-rentiva'),
				'required_fields' => __('Please fill in all required fields.', 'mhm-rentiva'),
				'confirm_reset'   => __('Are you sure you want to reset the form?', 'mhm-rentiva'),
			),
			'icons'            => array(
				'success' => \MHMRentiva\Helpers\Icons::get('success', array('class' => 'rv-icon-success')),
				'warning' => \MHMRentiva\Helpers\Icons::get('warning', array('class' => 'rv-icon-warning')),
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
			'message'        => ($data['message'] ?? '') !== null ? sanitize_textarea_field((string) ($data['message'] ?? '')) : '',
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
			if (empty($data[$field])) {
				/* translators: %s: field label. */
				$errors[$field] = sprintf(__('%s field is required.', 'mhm-rentiva'), self::get_field_label($field));
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
		if ($data['type'] === 'feedback' && ($data['rating'] < 1 || $data['rating'] > 5)) {
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
			'general'  => array('name', 'email', 'message'),
			'booking'  => array('name', 'email', 'phone', 'message'),
			'support'  => array('name', 'email', 'priority', 'message'),
			'feedback' => array('name', 'email', 'rating', 'message'),
		);

		return $configs[$type] ?? $configs['general'];
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

		return $labels[$field] ?? $field;
	}

	private static function save_contact_message(array $data): int
	{
		$post_data = array(
			'post_type'    => 'mhm_contact_message',
			/* translators: %s: customer name. */
			'post_title'   => sprintf(__('Contact Message - %s', 'mhm-rentiva'), $data['name']),
			'post_content' => $data['message'],
			'post_status'  => 'private',
			'post_author'  => 1,
			'meta_input'   => array(
				'_contact_type'           => $data['type'],
				'_contact_name'           => $data['name'],
				'_contact_email'          => $data['email'],
				'_contact_phone'          => $data['phone'],
				'_contact_company'        => $data['company'],
				'_contact_vehicle_id'     => $data['vehicle_id'],
				'_contact_preferred_date' => $data['preferred_date'],
				'_contact_priority'       => $data['priority'],
				'_contact_rating'         => $data['rating'],
				'_contact_attachment'     => $data['attachment'],
				'_contact_ip_address'     => $data['ip_address'],
				'_contact_user_agent'     => $data['user_agent'],
				'_contact_timestamp'      => $data['timestamp'],
				'_contact_status'         => 'new',
			),
		);

		$message_id = wp_insert_post($post_data);

		if (is_wp_error($message_id)) {
			throw new Exception(esc_html__('Unable to save the message.', 'mhm-rentiva'));
		}

		return $message_id;
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

		if (! empty($data['attachment'])) {
			$attachment_path = self::resolve_attachment_path($data['attachment']);
			if ($attachment_path) {
				$attachments[] = $attachment_path;
			}
		}

		return wp_mail($email_recipients, $subject, $message, $headers, $attachments);
	}

	/**
	 * Resolve attachment URL to local file path using multiple strategies
	 */
	private static function resolve_attachment_path(string $url): ?string
	{
		if (empty($url)) {
			return null;
		}

		$strategies = array();
		$upload_dir = wp_upload_dir();

		// Strategy 1: Direct Upload Dir Replacement
		$strategies[] = function () use ($url, $upload_dir) {
			return str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);
		};

		// Strategy 2: Site URL -> ABSPATH Replacement
		$strategies[] = function () use ($url) {
			return str_replace(site_url(), ABSPATH, $url);
		};

		// Strategy 3: Path Component Replacement (Robust for XAMPP/Localhost)
		$strategies[] = function () use ($url, $upload_dir) {
			$url_path  = wp_parse_url($url, PHP_URL_PATH);
			$base_path = wp_parse_url($upload_dir['baseurl'], PHP_URL_PATH);

			if ($url_path && $base_path && strpos($url_path, $base_path) !== false) {
				return str_replace($base_path, $upload_dir['basedir'], $url_path);
			}
			return null;
		};

		foreach ($strategies as $strategy) {
			$path = $strategy();
			if ($path) {
				// Formatting: Normalize slashes and decode functionality
				$clean_path = wp_normalize_path(urldecode($path));
				if (file_exists($clean_path)) {
					return $clean_path;
				}

				// Try without decoding just in case
				$raw_path = wp_normalize_path($path);
				if (file_exists($raw_path)) {
					return $raw_path;
				}
			}
		}

		return null;
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
			$priority_label = $priorities[$data['priority']]['label'] ?? $data['priority'];
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

		// File type check
		$allowed_types  = array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx');
		$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

		if (! in_array($file_extension, $allowed_types)) {
			return array(
				'success' => false,
				'message' => __('Invalid file type.', 'mhm-rentiva'),
			);
		}

		// WordPress upload fonksiyonunu kullan
		$upload = wp_handle_upload($file, array('test_form' => false));

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
