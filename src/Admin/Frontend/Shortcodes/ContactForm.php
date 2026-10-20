<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\ContactMessages\ContactAttachmentStore;
use MHMRentiva\Admin\Customers\CustomerIdentity;
use MHMRentiva\Admin\Frontend\Shortcodes\Contact\RecipientToken;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Frontend\RenderContext;
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
			'show_booking_id_field' => '0',           // Show booking number field
			'show_company_info'     => '1',           // Show the contact information box
			'redirect_url'          => '',            // Redirect after success (same site only)
			'success_message'       => '',            // Replaces the success text when set
			'email_to'              => '',            // Custom recipient (see Contact\RecipientToken)
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
			array( 'mhm-rentiva-css-variables' ),
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
		$type         = self::normalize_type( (string) ( $atts['type'] ?? 'general' ));
		$atts['type'] = $type;
		$form_config  = self::get_form_config($type);

		// A field the type requires is always rendered: its show_* toggle only applies
		// where the field is optional, so no setting yields a form the server refuses.
		$required = self::get_required_fields($type);
		if (in_array('phone', $required, true)) {
			$atts['show_phone'] = '1';
		}
		if (in_array('priority', $required, true)) {
			$atts['show_priority'] = '1';
		}

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

		// The custom recipient never reaches the page as an address: it travels as an
		// encrypted payload bound to the source post and is re-checked on submit.
		// A preview prints no token and issues none (RecipientToken::issue() has side effects).
		$email_to        = trim( (string) ( $atts['email_to'] ?? '' ));
		$recipient_token = ( '' !== $email_to && ! RenderContext::current()->is_preview() )
			? RecipientToken::issue($email_to, self::resolve_source_post_id(), $type)
			: '';

		// Same site only; anything else means "no redirect" (not admin_url()).
		$redirect_url = (string) wp_validate_redirect(esc_url_raw( (string) ( $atts['redirect_url'] ?? '' )), '');

		return array(
			'atts'            => $atts,
			'form_config'     => $form_config,
			'vehicles'        => $vehicles,
			'priorities'      => $priorities,
			'recipient_token' => $recipient_token,
			'redirect_url'    => $redirect_url,
			'current_user'    => wp_get_current_user(),
			'support_phone'   => (string) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhmrentiva_contact_phone', '+90 555 555 55 55'),
			'support_hours'   => (string) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhmrentiva_contact_hours', __('7/24 Support', 'mhm-rentiva')),
			'support_email'   => self::support_email(),
		);
	}

	/**
	 * The post the form is placed on, for the recipient payload's binding.
	 *
	 * Inside an Elementor render it is the document being rendered (for a Theme
	 * Builder template: the template, not the post it is shown on); otherwise the
	 * displayed post -- for a block or shortcode in a sidebar that is the post on
	 * screen, whose author then has to qualify (the safe side). 0 when there is none.
	 */
	public static function resolve_source_post_id(): int
	{
		// Elementor fills Plugin::$instance->documents on init; before that (or without
		// Elementor) there is no document to ask.
		$elementor = class_exists('\Elementor\Plugin') ? \Elementor\Plugin::$instance : null;
		$documents = null !== $elementor ? $elementor->documents : null;
		if ($documents instanceof \Elementor\Core\Documents_Manager) {
			$document = $documents->get_current();
			if (null !== $document) {
				return (int) $document->get_main_id();
			}
		}

		$id = get_the_ID();
		return false === $id ? 0 : (int) $id;
	}

	private static function normalize_type(string $type): string
	{
		return in_array($type, ContactMessagePostType::TYPES, true) ? $type : 'general';
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

	/**
	 * The General tab's Support Email: shown in the contact box and the
	 * recipient of "support" messages, so both read it from one place.
	 *
	 * The General tab writes it into the settings array. Before 4.5.3 the field
	 * was a standalone option, which the 6.0.0 prefix migration renamed to
	 * `mhmrentiva_support_email` (PrefixMigrationMap) -- that row is used only
	 * while the settings array has never held the key.
	 */
	private static function support_email(): string
	{
		$key   = 'mhmrentiva_support_email';
		$email = \MHMRentiva\Admin\Settings\Core\SettingsCore::has($key)
			? (string) \MHMRentiva\Admin\Settings\Core\SettingsCore::get($key, '')
			: (string) get_option($key, '');

		return is_email($email) ? $email : (string) get_option('admin_email');
	}

	private static function get_email_recipients(string $type, string $custom_email = ''): array
	{
		if (! empty($custom_email)) {
			return array( $custom_email );
		}

		$default_emails = array(
			'general'  => get_option('admin_email'),
			'booking'  => get_option('mhmrentiva_booking_email', get_option('admin_email')),
			'support'  => self::support_email(),
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
				'type'            => isset($_POST['type']) ? sanitize_text_field(wp_unslash( (string) $_POST['type'])) : 'general',
				'name'            => isset($_POST['name']) ? sanitize_text_field(wp_unslash( (string) $_POST['name'])) : '',
				'email'           => isset($_POST['email']) ? sanitize_email(wp_unslash( (string) $_POST['email'])) : '',
				'phone'           => isset($_POST['phone']) ? sanitize_text_field(wp_unslash( (string) $_POST['phone'])) : '',
				'company'         => isset($_POST['company']) ? sanitize_text_field(wp_unslash( (string) $_POST['company'])) : '',
				'vehicle_id'      => isset($_POST['vehicle_id']) ? absint(wp_unslash($_POST['vehicle_id'])) : 0,
				'preferred_date'  => isset($_POST['preferred_date']) ? sanitize_text_field(wp_unslash( (string) $_POST['preferred_date'])) : '',
				'priority'        => isset($_POST['priority']) ? sanitize_text_field(wp_unslash( (string) $_POST['priority'])) : '',
				'rating'          => isset($_POST['rating']) ? absint(wp_unslash($_POST['rating'])) : 0,
				'message'         => isset($_POST['message']) ? sanitize_textarea_field(wp_unslash( (string) $_POST['message'])) : '',
				// Never from the request: before 4.4.0 a typed URL landed here
				// and became a link in the admin screen and a path fed to the
				// admin e-mail. Only handle_file_upload() below sets it.
				'attachment'      => '',
				'auto_reply'      => isset($_POST['auto_reply']) ? sanitize_text_field(wp_unslash( (string) $_POST['auto_reply'])) : '1',
				'booking_id'      => isset($_POST['booking_id']) ? absint(wp_unslash($_POST['booking_id'])) : 0,
				// Opaque: only RecipientToken::resolve() gives it meaning. A plain
				// address posted here (or as email_to) is never used as a recipient.
				'recipient_token' => isset($_POST['recipient_token']) ? sanitize_text_field(wp_unslash( (string) $_POST['recipient_token'])) : '',
			);

			$attachment_file = null;
			if (! empty($_FILES['attachment']['name'])) {
				$attachment_file = array(
					// The five field-by-field sanitized leaves -- today's :321-327, unchanged.
					'name'     => isset($_FILES['attachment']['name']) ? sanitize_file_name(wp_unslash( (string) $_FILES['attachment']['name'])) : '',
					'type'     => isset($_FILES['attachment']['type']) ? sanitize_mime_type(wp_unslash( (string) $_FILES['attachment']['type'])) : '',
					'tmp_name' => isset($_FILES['attachment']['tmp_name']) ? sanitize_text_field(wp_unslash( (string) $_FILES['attachment']['tmp_name'])) : '',
					'error'    => isset($_FILES['attachment']['error']) ? (int) $_FILES['attachment']['error'] : UPLOAD_ERR_NO_FILE,
					'size'     => isset($_FILES['attachment']['size']) ? (int) $_FILES['attachment']['size'] : 0,
				);
			}

			$result = self::process_submission($post_data, $attachment_file);
			if (! $result['ok']) {
				self::ajax_error($result['message'], isset($result['errors']) ? array( 'errors' => $result['errors'] ) : array());
				return;
			}

			self::ajax_success(
				array(
					'message_id' => $result['message_id'],
					'email_sent' => $result['email_sent'],
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
	 * Validate, store the file, save, mail -- in that order (R-4). The file is
	 * written only after the form itself passed, and removed again if the
	 * record cannot be saved, so a refused submission never leaves a file.
	 *
	 * @param array<string,mixed>      $post_data Field-by-field sanitized request.
	 * @param array<string,mixed>|null $file      The $_FILES leaf set, or null.
	 * @param callable|null            $handler   Test seam, see ContactAttachmentStore::store_upload().
	 * @return array<string,mixed>
	 */
	private static function process_submission(array $post_data, ?array $file, ?callable $handler = null): array
	{
		$form_data  = self::sanitize_contact_form_data($post_data);
		$validation = self::validate_form_data($form_data);
		if (! $validation['valid']) {
			return array(
				'ok'      => false,
				'message' => $validation['message'],
				'errors'  => $validation['errors'],
			);
		}

		// After sanitization, so the payload is checked against the normalized type.
		$form_data['recipient'] = '' !== $form_data['recipient_token']
			? (string) RecipientToken::resolve($form_data['recipient_token'], $form_data['type'])
			: '';

		// Linked only to the signed-in renter's own booking. The answer to the
		// visitor is the same either way, so the field reveals nothing.
		$form_data['linked_booking_id'] = self::own_booking_id($form_data['booking_id']);

		$record = null;
		if (null !== $file) {
			$upload = self::handle_file_upload($file, $handler);
			if (! $upload['success']) {
				return array(
					'ok'      => false,
					'message' => $upload['message'],
				);
			}
			$record                  = $upload['record'];
			$form_data['attachment'] = $record;
		}

		try {
			$message_id = self::save_contact_message($form_data);
		} catch (Exception $e) {
			if (null !== $record) {
				ContactAttachmentStore::discard($record);
			}
			throw $e;
		}

		$email_sent = self::send_contact_email($form_data, $message_id);
		if ('1' === $form_data['auto_reply']) {
			self::send_auto_reply($form_data);
		}

		return array(
			'ok'         => true,
			'message_id' => $message_id,
			'email_sent' => $email_sent,
		);
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
			'nonce'            => \MHMRentiva\Frontend\RenderContext::script_nonce('mhmrentiva_contact_form_nonce'),
			'maxFileSize'      => self::max_attachment_bytes(),
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
	public const MAX_ATTACHMENT_BYTES = 5242880; // 5 MB

	private static function sanitize_contact_form_data(array $data): array
	{
		return array(
			'type'            => in_array( (string) ( $data['type'] ?? '' ), ContactMessagePostType::TYPES, true ) ? (string) $data['type'] : 'general',
			'name'            => self::sanitize_text_field_safe($data['name'] ?? ''),
			'email'           => \MHMRentiva\Admin\Core\SecurityHelper::validate_email($data['email'] ?? ''),
			'phone'           => \MHMRentiva\Admin\Core\SecurityHelper::validate_phone($data['phone'] ?? ''),
			'company'         => self::sanitize_text_field_safe($data['company'] ?? ''),
			'vehicle_id'      => self::vehicle_id_or_zero( (int) ( $data['vehicle_id'] ?? 0 )),
			'preferred_date'  => self::date_or_empty( (string) ( $data['preferred_date'] ?? '' )),
			'priority'        => in_array( (string) ( $data['priority'] ?? '' ), ContactMessagePostType::PRIORITIES, true ) ? (string) $data['priority'] : '',
			'rating'          => max(0, min(5, (int) ( $data['rating'] ?? 0 ))),
			'message'         => ( $data['message'] ?? '' ) !== null ? sanitize_textarea_field( (string) ( $data['message'] ?? '' )) : '',
			'attachment'      => '',
			'auto_reply'      => self::sanitize_text_field_safe($data['auto_reply'] ?? '1'),
			'booking_id'      => absint($data['booking_id'] ?? 0),
			'recipient_token' => self::sanitize_text_field_safe($data['recipient_token'] ?? ''),
			'ip_address'      => sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? '')),
			'user_agent'      => sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? '')),
			'timestamp'       => current_time('mysql'),
		);
	}

	/**
	 * The booking number when it is the signed-in user's own booking, else 0.
	 * Guests are never linked to any record.
	 */
	private static function own_booking_id(int $booking_id): int
	{
		$user = wp_get_current_user();
		if ($booking_id <= 0 || $user->ID <= 0) {
			return 0;
		}

		// One post by ID against the house ownership rule (user ID or account
		// e-mail), read directly so no query filter can widen the answer.
		return CustomerIdentity::booking_is_owned_by($booking_id, (int) $user->ID, (string) $user->user_email) ? $booking_id : 0;
	}

	private static function vehicle_id_or_zero(int $id): int
	{
		return $id > 0 && 'mhmrentiva_vehicle' === get_post_type($id) ? $id : 0;
	}

	private static function date_or_empty(string $value): string
	{
		$d = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
		return ( $d && $d->format('Y-m-d') === $value ) ? $value : '';
	}

	/**
	 * The cap for an anonymous upload: 5 MB, filterable, never above PHP's
	 * own limit -- and, on a multisite network with upload space checks on,
	 * never above the network's own per-file quota either (Fable M2): core's
	 * check_upload_size prefilter enforces that quota on this path too, so
	 * a cap that ignores it would advertise a size the network will refuse.
	 * Public: the template needs it to show the real cap (Fable M3).
	 */
	public static function max_attachment_bytes(): int
	{
		$cap = min(
			(int) apply_filters('mhmrentiva_contact_attachment_max_bytes', self::MAX_ATTACHMENT_BYTES),
			(int) wp_max_upload_size()
		);
		if (is_multisite() && ! get_site_option('upload_space_check_disabled')) {
			$cap = min($cap, KB_IN_BYTES * (int) get_site_option('fileupload_maxk', 1500));
		}
		return max(1, $cap);
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

		return self::drop_priority_without_options($configs[ $type ] ?? $configs['general'], self::get_priority_options());
	}

	/**
	 * With no priority to choose from, priority is neither required nor (through
	 * prepare_template_data_legacy()) rendered, so the form stays submittable.
	 * Unreachable today -- get_priority_options() is hard-coded -- but kept so a
	 * future empty list cannot recreate an unsubmittable support form.
	 *
	 * @param string[]            $fields     Required fields for the type.
	 * @param array<string,mixed> $priorities Priority options.
	 * @return string[]
	 */
	private static function drop_priority_without_options(array $fields, array $priorities): array
	{
		if (array() === $priorities) {
			return array_values(array_diff($fields, array( 'priority' )));
		}
		return $fields;
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
				'_mhmrentiva_contact_ip_address'     => $data['ip_address'],
				'_mhmrentiva_contact_user_agent'     => $data['user_agent'],
				'_mhmrentiva_contact_timestamp'      => $data['timestamp'],
				'_mhmrentiva_contact_status'         => 'new',
			),
		);

		if (( $data['linked_booking_id'] ?? 0 ) > 0) {
			$post_data['meta_input']['_mhmrentiva_contact_booking_id'] = (int) $data['linked_booking_id'];
		}

		if (is_array($data['attachment']) && array() !== $data['attachment']) {
			$post_data['meta_input'][ ContactAttachmentStore::META_KEY ]  = $data['attachment'];
			$post_data['meta_input'][ ContactAttachmentStore::FILE_META ] = $data['attachment']['file'];
		}

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
		$email_recipients = self::get_email_recipients($data['type'], (string) ( $data['recipient'] ?? '' ));

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
		if (is_array($data['attachment'] ?? null) && array() !== $data['attachment']) {
			$path = ContactAttachmentStore::path($data['attachment']);
			if (null !== $path) {
				// Keyed by the visitor's own file name; the stored name is random (spec §4).
				$attachments[ (string) $data['attachment']['name'] ] = $path;
			}
		}

		return wp_mail($email_recipients, $subject, $message, $headers, $attachments);
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

		if (! empty($data['booking_id'])) {
			// The number as typed (absint); linked to the record only when it is the sender's own.
			$message .= '<p><strong>' . esc_html__('Booking ID:', 'mhm-rentiva') . '</strong> #' . (int) $data['booking_id'] . '</p>';
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

		if (is_array($data['attachment'] ?? null) && '' !== (string) ( $data['attachment']['name'] ?? '' )) {
			$message .= '<p><strong>' . esc_html__('Attachment:', 'mhm-rentiva') . '</strong> ' . esc_html( (string) $data['attachment']['name']) . '</p>';
		}

		$message .= '<hr>';
		$message .= '<p><small>' . __('Sent Date:', 'mhm-rentiva') . ' ' . esc_html($data['timestamp'] ?? '') . '</small></p>';
		$message .= '<p><a href="' . esc_url(ContactMessagePostType::admin_detail_url($message_id)) . '">' . esc_html__('Open in the admin panel', 'mhm-rentiva') . '</a></p>';
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

	private static function handle_file_upload(array $file, ?callable $handler = null): array
	{
		if (UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE )) {
			return array(
				'success' => false,
				'message' => __('The file could not be uploaded.', 'mhm-rentiva'),
			);
		}

		$size = (int) ( $file['size'] ?? 0 );
		$tmp  = (string) ( $file['tmp_name'] ?? '' );
		if ($size <= 0) {
			return array(
				'success' => false,
				'message' => __('The file is empty.', 'mhm-rentiva'),
			);
		}
		if ($size > self::max_attachment_bytes() || ( is_file($tmp) && filesize($tmp) > self::max_attachment_bytes() )) {
			return array(
				'success' => false,
				'message' => __('File size is too large.', 'mhm-rentiva'),
			);
		}

		$record = ContactAttachmentStore::store_upload($tmp, (string) ( $file['name'] ?? '' ), $handler, self::max_attachment_bytes());
		if (is_wp_error($record)) {
			return array(
				'success' => false,
				'message' => $record->get_error_message(),
			);
		}

		return array(
			'success' => true,
			'record'  => $record,
		);
	}
}
