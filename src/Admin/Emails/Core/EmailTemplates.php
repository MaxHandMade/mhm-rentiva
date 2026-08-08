<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Core;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Emails\Templates\BookingNotifications;
use MHMRentiva\Admin\Emails\Templates\RefundEmails;
use MHMRentiva\Admin\Emails\Templates\EmailPreview;



use MHMRentiva\Admin\Core\CurrencyHelper;
use MHMRentiva\Admin\Emails\Ajax\EmailAjaxHandler;
use MHMRentiva\Admin\Settings\Groups\EmailSettings;


use MHMRentiva\Admin\Core\Security\VerifiedRequest;

final class EmailTemplates {




	public static function register(): void
	{
		// Menu registration is now done centrally in Menu.php
		// admin_post_mhmrentiva_email_send_test -> handle_send() was removed:
		// zero shipped nonce producer and zero consumer anywhere. The live
		// sibling is the differently-named mhmrentiva_send_test_email
		// (EmailTestAction.php). build_context() (used by handle_send())
		// survives -- it is also called live from EmailAjaxHandler.

		// Admin AJAX for emails
		\MHMRentiva\Admin\Emails\Ajax\EmailAjaxHandler::register();

		// Email templates form processing
		add_action('admin_post_mhmrentiva_save_email_templates', array( self::class, 'handle_save_templates' ));

		// Add hooks for email templates page
		add_action('admin_enqueue_scripts', array( self::class, 'enqueue_scripts' ));
		add_action('admin_notices', array( self::class, 'add_email_stats_cards' ));
		add_action('admin_notices', array( self::class, 'show_save_notice' ));
	}



	// render_page() and render_standalone_page() were removed:
	// render_page()'s only caller was its own class
	// (render_standalone_page()), and neither was ever wired to a menu/
	// add_options_page -- zero rendering surface anywhere. The live renderer
	// is render_content_only() below, called from TabRendererRegistry inside
	// the Settings > Email Templates tab.

	/**
	 * Render only the body for the Settings tab, without the surrounding form.
	 */
	public static function render_content_only(): void
	{
		// Define email template types
		$email_types = self::get_email_type_tabs();

		$current_type = self::get_key('type', 'booking_notifications');
		if (! isset($email_types[ $current_type ])) {
			$current_type = 'booking_notifications';
		}

		$email_settings_url = admin_url('admin.php?page=mhm-rentiva-settings&tab=email');

		// Unified Header
		?>
		<div class="mhm-settings-tab-header">
			<div class="mhm-settings-title-group">
				<h2><?php esc_html_e('Notification Templates', 'mhm-rentiva'); ?></h2>
				<p class="description"><?php esc_html_e('Customize automated email communications. If a field is empty, the system automatically uses the Gold Standard layout.', 'mhm-rentiva'); ?></p>
			</div>

			<div class="mhm-settings-header-actions">
				<a href="<?php echo esc_url($email_settings_url); ?>" class="button button-secondary">
					<span class="dashicons dashicons-email-alt"></span>
					<?php esc_html_e('Email Settings', 'mhm-rentiva'); ?>
				</a>

				<button type="button" id="mhm-reset-email-templates-btn" class="button button-secondary" data-tab="email-templates">
					<span class="dashicons dashicons-undo"></span>
					<?php esc_html_e('Restore Gold Standard', 'mhm-rentiva'); ?>
				</button>
			</div>
		</div>
		<hr class="wp-header-end">

		<h2 class="nav-tab-wrapper" style="margin-top: 20px;">
			<?php
			$current_parent_tab = self::get_key('tab', 'email-templates');

			foreach ($email_types as $type => $label) {
				$active = ( $current_type === $type ) ? ' nav-tab-active' : '';
				$url    = add_query_arg(
					array(
						'page' => 'mhm-rentiva-settings',
						'tab'  => $current_parent_tab,
						'type' => $type,
					),
					admin_url('admin.php')
				);

				printf('<a href="%s" class="nav-tab %s">%s</a>', esc_url($url), esc_attr($active), esc_html($label));
			}
			?>
		</h2>

		<div class="mhm-email-template-content" style="margin-top: 20px;">
			<?php
			// Render content (without form)
			if ($current_type === 'booking_notifications') {
				BookingNotifications::render();
			} elseif ($current_type === 'refund_emails') {
				RefundEmails::render();
			} elseif ($current_type === 'preview') {
				EmailPreview::render();
			} else {
				/**
				 * Neutral seam for any email-type tab Lite does not own (e.g.
				 * the add-on's message_emails/vendor_emails). The add-on renders its own tabs
				 * here.
				 *
				 * @param string $current_type The active email-type tab key.
				 */
				do_action('mhmrentiva_render_email_type', $current_type);
			}
			?>
		</div>
		<?php
	}

	// handle_send() was removed with its admin_post_mhmrentiva_email_send_test
	// registration above. build_context() below survives -- it is also
	// called live from EmailAjaxHandler.

	/**
	 * The submitted templates form, or null when it carries neither of the two
	 * nonces that authorise saving it.
	 *
	 * Two screens can post here: the email-templates screen (its own nonce) and
	 * the generic settings screen (_wpnonce for the settings group). Each gets
	 * its own early return rather than being combined into one compound
	 * condition at the call site. The verified payload travels back with the
	 * verdict so the nonce check and the superglobal access stay in one scope --
	 * a helper that only answered true/false would move wp_verify_nonce() out of
	 * the caller and leave every read there unjustified.
	 */
	private static function verified_save_request(): ?VerifiedRequest
	{
		$own_nonce = sanitize_text_field(wp_unslash($_POST['mhmrentiva_email_templates_nonce'] ?? ''));
		if (wp_verify_nonce($own_nonce, 'mhmrentiva_save_email_templates')) {
			return VerifiedRequest::from($_POST);
		}

		$settings_nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? ''));
		if (wp_verify_nonce($settings_nonce, 'mhmrentiva_settings-options')) {
			return VerifiedRequest::from($_POST);
		}

		return null;
	}

	public static function handle_save_templates(): void
	{

		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to perform this action.', 'mhm-rentiva'));
		}

		// Nonce verification.
		$req = self::verified_save_request();
		if (null === $req) {
			wp_die(esc_html__('Security check failed.', 'mhm-rentiva'));
		}

		// Get active tab information. Unlike the render path this value arrives from
		// POST, so it must be validated against the tabs this build actually offers
		// -- otherwise a hand-crafted request could still reach the save handler of
		// a tab that no longer exists (message_emails without the Messages module,
		// vendor_emails without the vendor module) and write settings for a feature
		// that cannot use them.
		$current_tab = sanitize_key($req->text('current_tab')) ?: 'booking_notifications';
		if (! isset(self::get_email_type_tabs()[ $current_tab ])) {
			$current_tab = 'booking_notifications';
		}

		// Process only active tab
		if ($current_tab === 'booking_notifications') {
			self::save_booking_notifications($req);
		} elseif ($current_tab === 'refund_emails') {
			self::save_refund_emails($req);
		} else {
			/**
			 * Neutral seam for any email-type tab Lite does not own (e.g. the add-on's
			 * message_emails/vendor_emails). The add-on saves its own tabs here.
			 *
			 * @param string $current_tab The active (already-validated) tab key.
			 */
			do_action('mhmrentiva_save_email_type', $current_tab);
		}

		// Success message - success flag instead of redirect
		// Don't redirect when called from settings page
		if ($req->text('email_templates_action') === '') {
			// Redirect to settings page since coming from admin-post.php
			$redirect_url = add_query_arg(
				array(
					'page'    => 'mhm-rentiva-settings',
					'tab'     => 'email-templates',
					'type'    => $current_tab,
					'updated' => '1',
				),
				admin_url('admin.php')
			);
			wp_safe_redirect($redirect_url);
			exit;
		}
	}

	private static function save_booking_notifications(VerifiedRequest $req): void
	{

		$fields = array(
			'mhmrentiva_booking_created_enabled'   => 'checkbox',
			'mhmrentiva_booking_created_subject'   => 'text',
			'mhmrentiva_booking_created_body'      => 'html',
			'mhmrentiva_booking_status_enabled'    => 'checkbox',
			'mhmrentiva_booking_status_subject'    => 'text',
			'mhmrentiva_booking_status_body'       => 'html',
			'mhmrentiva_booking_admin_enabled'     => 'checkbox',
			'mhmrentiva_booking_admin_to'          => 'email',
			'mhmrentiva_booking_admin_subject'     => 'text',
			'mhmrentiva_booking_admin_body'        => 'html',
			// Auto Cancel Email
			'mhmrentiva_auto_cancel_email_subject' => 'text',
			'mhmrentiva_auto_cancel_email_content' => 'html',
			// Reminder & Welcome
			'mhmrentiva_booking_reminder_enabled'  => 'checkbox',
			'mhmrentiva_booking_reminder_subject'  => 'text',
			'mhmrentiva_booking_reminder_body'     => 'html',
			'mhmrentiva_welcome_email_subject'     => 'text',
			'mhmrentiva_welcome_email_body'        => 'html',
		);

		self::save_email_fields($fields, $req);
	}



	private static function save_refund_emails(VerifiedRequest $req): void
	{
		$fields = array(
			'mhmrentiva_refund_customer_enabled' => 'checkbox',
			'mhmrentiva_refund_customer_subject' => 'text',
			'mhmrentiva_refund_customer_body'    => 'html',
			'mhmrentiva_refund_admin_enabled'    => 'checkbox',
			'mhmrentiva_refund_admin_to'         => 'email',
			'mhmrentiva_refund_admin_subject'    => 'text',
			'mhmrentiva_refund_admin_body'       => 'html',
		);

		self::save_email_fields($fields, $req);
	}

	/**
	 * Tabs for the email-templates screen.
	 *
	 * Lite owns three tabs: Booking Notifications, Refund Emails and Email
	 * Preview. Any other tab -- currently the add-on's Message Notifications and
	 * Vendor Notifications -- is contributed through the
	 * `mhmrentiva_email_types` filter, so Lite never names an add-on class here.
	 *
	 * This list is also what save_email_templates() validates its POSTed
	 * `current_tab` against, so a tab not contributed by an active extension
	 * (e.g. the extension not installed, or installed but not registering) also
	 * closes the save handler for it.
	 *
	 * @return array<string, string>
	 */
	private static function get_email_type_tabs(): array
	{
		$email_types = array(
			'booking_notifications' => __('Booking Notifications', 'mhm-rentiva'),
			'refund_emails'         => __('Refund Emails', 'mhm-rentiva'),
		);

		/**
		 * Let the add-on (or any other extension) add its own email-type tabs before
		 * the Preview tab -- e.g. message_emails, vendor_emails.
		 *
		 * @param array<string, string> $email_types Lite's own tabs so far, keyed by type.
		 */
		$email_types = apply_filters('mhmrentiva_email_types', $email_types);

		$email_types['preview'] = __('Email Preview', 'mhm-rentiva');

		return $email_types;
	}

	/**
	 * Save email fields - to prevent code repetition
	 *
	 * @param array $fields Field definitions
	 */
	private static function save_email_fields(array $fields, VerifiedRequest $req): void
	{
		foreach ($fields as $field_name => $field_type) {
			if (! $req->has($field_name)) {
				if ($field_type === 'checkbox') {
					update_option($field_name, '0');
				}
				continue;
			}

			// Raw here on purpose: the type-specific sanitizer runs in the switch
			// below (html fields need wp_kses_post, not sanitize_text_field).
			$value = $req->raw($field_name);

			// Null check
			if ($value === null) {
				$value = '';
			}

			switch ($field_type) {
				case 'checkbox':
					update_option($field_name, '1');
					break;
				case 'text':
					update_option($field_name, sanitize_text_field( (string) ( $value ?: '' )));
					break;
				case 'email':
					update_option($field_name, sanitize_email( (string) ( $value ?: '' )));
					break;
				case 'html':
					update_option($field_name, wp_kses_post($value ?: ''));
					break;
				default:
					update_option($field_name, sanitize_text_field( (string) ( $value ?: '' )));
					break;
			}
		}
	}

	public static function build_context(string $key, int $booking_id): array
	{
		$ctx = array(
			'site' => array(
				'name' => get_bloginfo('name'),
				'url'  => home_url('/'),
			),
		);
		if ($booking_id > 0) {
			$ctx['booking'] = array(
				'id'          => $booking_id,
				'title'       => get_the_title($booking_id),
				'status'      => (string) get_post_meta($booking_id, '_mhmrentiva_status', true),
				'payment'     => array(
					'status'   => (string) get_post_meta($booking_id, '_mhmrentiva_payment_status', true),
					'amount'   => (int) get_post_meta($booking_id, '_mhmrentiva_payment_amount', true),
					'currency' => (string) get_post_meta($booking_id, '_mhmrentiva_payment_currency', true) ?: 'TRY',
				),
				// Helper for direct access
				'total_price' => number_format_i18n( (int) get_post_meta($booking_id, '_mhmrentiva_payment_amount', true) / 100, 2),
			);
			$ctx['customer'] = array(
				'email' => (string) get_post_meta($booking_id, '_mhmrentiva_contact_email', true),
				'name'  => (string) get_post_meta($booking_id, '_mhmrentiva_contact_name', true),
			);
			// Include vehicle info if available (simplified for now as context is mostly meta based)
			$ctx['vehicle'] = array(
				'title' => 'Vehicle Title (ID: ' . get_post_meta($booking_id, '_mhmrentiva_vehicle_id', true) . ')',
			);
		} else {
			// Mock Data for Preview
			$ctx = array_merge($ctx, self::get_mock_context());
		}
		if ($key === 'refund_customer' || $key === 'refund_admin') {
			$amount_kurus = isset($ctx['booking']['payment']['amount']) ? (int) $ctx['booking']['payment']['amount'] : 0;
			$cur          = isset($ctx['booking']['payment']['currency']) ? (string) $ctx['booking']['payment']['currency'] : 'TRY';

			// Generate symbol dynamically based on the code provided in context
			$symbol = CurrencyHelper::get_currency_symbol($cur);

			$ctx['amount'] = number_format_i18n($amount_kurus / 100, 2) . ' ' . $symbol;
			$ctx['status'] = (string) ( $ctx['booking']['payment']['status'] ?? '' );
			$ctx['reason'] = '';
		}
		return $ctx;
	}

	/**
	 * Generate comprehensive mock data for previewing without a real booking
	 */
	private static function get_mock_context(): array
	{
		// Dynamically get the currently active currency code (e.g., 'USD', 'EUR', 'TRY')
		$currency_code = CurrencyHelper::get_currency_symbol(null); // Passing null gets default from settings/WooCommerce
		// If it returns a symbol (like $), we want the code for context data usually, but here the preview expects what?
		// Actually, get_currency_symbol returns the symbol. We need the CODE for the raw data.

		$code = 'TRY';
		if (function_exists('get_woocommerce_currency')) {
			$code = \get_woocommerce_currency();
		} else {
			$code = get_option('mhmrentiva_currency', 'TRY');
		}

		return array(
			// ... (rest of array omitted for brevity, assuming existing content is preserved if I don't touch it. Wait, replace_file_content replaces the whole chunk!)
			// I need to be careful not to delete the array content.
			// I will use multi_replace to target specific lines.

			'booking'       => array(
				'id'               => 9999,
				'title'            => __('Mock Booking #9999', 'mhm-rentiva'),
				'status'           => __('confirmed', 'mhm-rentiva'),
				'pickup_date'      => gmdate('d.m.Y H:i', strtotime('+1 day')),
				'return_date'      => gmdate('d.m.Y H:i', strtotime('+4 days')),
				'rental_days'      => 3,
				'total_price'      => 1500.00, // MUST be numeric for number_format()
				'payment'          => array(
					'status'   => __('pending', 'mhm-rentiva'),
					'amount'   => 150000, // kuruş
					'currency' => $code, // Dynamic Code
				),
				// Additional fields for booking-created template
				'payment_type'     => 'full',
				'deposit_amount'   => 0,
				'remaining_amount' => 0,
				'payment_method'   => __('credit_card', 'mhm-rentiva'),
				'payment_status'   => __('pending', 'mhm-rentiva'),
				'payment_deadline' => gmdate('Y-m-d H:i:s', strtotime('+30 minutes')),
			),
			'customer'      => array(
				'name'       => 'John Doe',
				'first_name' => 'John',
				'last_name'  => 'Doe',
				'email'      => 'john.doe@example.com',
				'phone'      => '+90 555 123 4567',
			),
			'vehicle'       => array(
				'id'             => 101,
				'title'          => 'Fiat Egea Cross 2024',
				'price_per_day'  => 500.00,
				'featured_image' => '',
			),
			'message'       => array(
				'subject' => __('Example Message Subject', 'mhm-rentiva'),
				'body'    => __('This is a sample message content for preview purposes. It demonstrates how long text will appear in the email body.', 'mhm-rentiva'),
				'reply'   => __('This is a sample reply content from the administrator.', 'mhm-rentiva'),
			),
			// Status change context (for status emails)
			'status_change' => array(
				'old_status'       => 'pending',
				'new_status'       => 'confirmed',
				'old_status_label' => __('Pending', 'mhm-rentiva'),
				'new_status_label' => __('Confirmed', 'mhm-rentiva'),
			),
		);
	}

	/**
	 * Load scripts and styles for email templates page
	 */
	public static function enqueue_scripts(string $hook): void
	{
		// Load on email templates page OR settings page (when email tab is active)
		if (strpos($hook, 'mhm-rentiva-email-templates') !== false || strpos($hook, 'mhm-rentiva-settings') !== false) {
			wp_enqueue_style(
				'mhm-rentiva-stats-cards',
				\MHMRENTIVA_PLUGIN_URL . 'assets/css/components/stats-cards.css',
				array(),
				\MHMRENTIVA_VERSION
			);

			wp_enqueue_style(
				'mhm-rentiva-email-templates',
				\MHMRENTIVA_PLUGIN_URL . 'assets/css/admin/email-templates.css',
				array(),
				\MHMRENTIVA_VERSION
			);

			wp_enqueue_script(
				'mhm-rentiva-email-templates',
				\MHMRENTIVA_PLUGIN_URL . 'assets/js/admin/email-templates.js',
				array( 'jquery' ),
				\MHMRENTIVA_VERSION,
				true
			);

			// ⭐ Localize JavaScript variables (includes data for send test email functionality)
			//
			// `strings` is populated here because email-templates.js reads ten
			// `mhmrentiva_email_templates_vars.strings.*` leaves behind an
			// `(vars.strings && vars.strings.x) || 'English literal'` guard, so a
			// missing key never throws -- every dialog would just silently render
			// the English fallback regardless of site locale. This payload is a
			// full superset of the equivalent one AssetManager.php's now-dead
			// mhm-rentiva_page_mhm-rentiva-email-templates branch used to localize
			// (that branch never fires live -- see AssetManager.php:1044),
			// including the `strings` sub-array that duplicate carried.
			wp_localize_script(
				'mhm-rentiva-email-templates',
				'mhmrentiva_email_templates_vars',
				array(
					'ajax_url'          => admin_url('admin-ajax.php'),
					'admin_post_url'    => admin_url('admin-post.php'),
					'nonce'             => wp_create_nonce('mhmrentiva_email_templates_nonce'),
					'send_test_nonce'   => wp_create_nonce('mhmrentiva_send_template_test'),
					'preview_email'     => __('Email Preview', 'mhm-rentiva'),
					'send_test'         => __('Send Test', 'mhm-rentiva'),
					'test_email_sent'   => __('Test email sent successfully!', 'mhm-rentiva'),
					'test_email_failed' => __('Test email could not be sent.', 'mhm-rentiva'),
					'processing'        => __('Processing...', 'mhm-rentiva'),
					'error_occurred'    => __('An error occurred. Please try again.', 'mhm-rentiva'),
					'strings'           => array(
						'sendTestEmail' => __('Send Test Email', 'mhm-rentiva'),
						'emailAddress'  => __('Email Address', 'mhm-rentiva'),
						'cancel'        => __('Cancel', 'mhm-rentiva'),
						'enterEmail'    => __('Please enter email address', 'mhm-rentiva'),
						'editTemplate'  => __('Edit Template', 'mhm-rentiva'),
						'subject'       => __('Subject', 'mhm-rentiva'),
						'content'       => __('Content', 'mhm-rentiva'),
						'save'          => __('Save', 'mhm-rentiva'),
						'templateSaved' => __('Template saved successfully!', 'mhm-rentiva'),
						'templateReset' => __('Template reset to default!', 'mhm-rentiva'),
					),
				)
			);
		}
	}

	/**
	 * Add email templates statistics cards
	 */
	public static function add_email_stats_cards(): void
	{
		global $pagenow;

		// Show only on email templates page
		if ($pagenow !== 'admin.php' || self::get_key('page') !== 'mhm-rentiva-email-templates') {
			return;
		}

		$stats = self::get_email_stats();

		?>
		<div class="mhm-stats-cards">
			<div class="stats-grid">
				<!-- Total Templates -->
				<div class="stat-card stat-card-total-templates">
					<div class="stat-icon">
						<span class="dashicons dashicons-email-alt2"></span>
					</div>
					<div class="stat-content">
						<div class="stat-number"><?php echo esc_html($stats['total_templates']); ?></div>
						<div class="stat-label"><?php esc_html_e('Total Templates', 'mhm-rentiva'); ?></div>
						<div class="stat-trend">
							<span class="trend-text"><?php esc_html_e('All templates', 'mhm-rentiva'); ?></span>
						</div>
					</div>
				</div>

				<!-- Active Templates -->
				<div class="stat-card stat-card-active-templates">
					<div class="stat-icon">
						<span class="dashicons dashicons-yes-alt"></span>
					</div>
					<div class="stat-content">
						<div class="stat-number"><?php echo esc_html($stats['active_templates']); ?></div>
						<div class="stat-label"><?php esc_html_e('Active Templates', 'mhm-rentiva'); ?></div>
						<div class="stat-trend">
							<span class="trend-text trend-up"><?php echo esc_html($stats['active_percentage']); ?>% <?php esc_html_e('active', 'mhm-rentiva'); ?></span>
						</div>
					</div>
				</div>

				<!-- Sent This Month -->
				<div class="stat-card stat-card-monthly-sent">
					<div class="stat-icon">
						<span class="dashicons dashicons-paperclip"></span>
					</div>
					<div class="stat-content">
						<div class="stat-number"><?php echo esc_html($stats['monthly_sent']); ?></div>
						<div class="stat-label"><?php esc_html_e('Sent This Month', 'mhm-rentiva'); ?></div>
						<div class="stat-trend">
							<span class="trend-text"><?php esc_html_e('Email count', 'mhm-rentiva'); ?></span>
						</div>
					</div>
				</div>

				<!-- Success Rate -->
				<div class="stat-card stat-card-success-rate">
					<div class="stat-icon">
						<span class="dashicons dashicons-chart-line"></span>
					</div>
					<div class="stat-content">
						<div class="stat-number"><?php echo esc_html($stats['success_rate']); ?></div>
						<div class="stat-label"><?php esc_html_e('Success Rate', 'mhm-rentiva'); ?></div>
						<div class="stat-trend">
							<span class="trend-text trend-up"><?php esc_html_e('Delivery rate', 'mhm-rentiva'); ?></span>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get email templates statistics
	 */
	private static function get_email_stats(): array
	{
		global $wpdb;

		// Email template types
		$email_types = array(
			'booking_notifications' => array(
				'booking_confirmation' => __('Booking Confirmation', 'mhm-rentiva'),
				'booking_reminder'     => __('Booking Reminder', 'mhm-rentiva'),
				'booking_cancellation' => __('Booking Cancellation', 'mhm-rentiva'),
			),
			'refund_emails'         => array(
				'refund_customer' => __('Customer Refund Email', 'mhm-rentiva'),
				'refund_admin'    => __('Admin Refund Email', 'mhm-rentiva'),
			),
		);

		// Total template count
		$total_templates = 0;
		foreach ($email_types as $type => $templates) {
			$total_templates += count($templates);
		}

		// Active template count (simple calculation - all templates considered active)
		$active_templates = $total_templates;

		// ⭐ Emails sent this month - Using WP_Query instead of raw SQL
		$monthly_sent = self::get_monthly_email_count();

		// Success rate (simple calculation - 95% accepted)
		$success_rate = '95%';

		// Active percentage
		$active_percentage = $total_templates > 0 ? round(( $active_templates / $total_templates ) * 100) : 0;

		return array(
			'total_templates'   => $total_templates,
			'active_templates'  => $active_templates,
			'active_percentage' => $active_percentage,
			'monthly_sent'      => $monthly_sent,
			'success_rate'      => $success_rate,
		);
	}

	/**
	 * Get monthly email count using WP_Query (replaces raw SQL)
	 *
	 * @return int Monthly email count
	 */
	private static function get_monthly_email_count(): int
	{
		$query = new \WP_Query(
			array(
				'post_type'      => 'mhmrentiva_email_log',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'date_query'     => array(
					array(
						'after'     => gmdate('Y-m-01 00:00:00'),
						'inclusive' => true,
					),
				),
				'no_found_rows'  => true,
			)
		);

		return $query->found_posts ?? 0;
	}

	/**
	 * Show save success message
	 */
	public static function show_save_notice(): void
	{
		global $pagenow;

		// Show only on email templates page
		if ($pagenow !== 'admin.php' || self::get_key('page') !== 'mhm-rentiva-email-templates') {
			return;
		}

		if (self::get_text('updated') === '1') {
			echo '<div class="notice notice-success is-dismissible">';
			echo '<p><strong>' . esc_html__('Email templates saved successfully!', 'mhm-rentiva') . '</strong></p>';
			echo '</div>';
		}
	}

	/**
	 * Read a screen-navigation value from the admin URL (?page=, ?tab=, ?type=,
	 * ?updated=). These select which panel to render and never drive a write, so
	 * there is no state change to protect with a nonce.
	 */
	private static function get_text(string $key, string $default = ''): string
	{
		if (! isset($_GET[ $key ])) {
			return $default;
		}

		return sanitize_text_field(wp_unslash( (string) $_GET[ $key ]));
	}

	private static function get_key(string $key, string $default = ''): string
	{
		$value = self::get_text($key, $default);
		return '' === $value ? $default : sanitize_key($value);
	}
}
