<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core;

use MHMRentiva\Admin\Addons\AddonScreen;
use MHMRentiva\Admin\Core\Helpers\Sanitizer;
use MHMRentiva\Admin\Core\Security\VerifiedRequest;
use MHMRentiva\Admin\Emails\Core\EmailTemplates;
use MHMRentiva\Admin\Emails\PostTypes\EmailLog;
use MHMRentiva\Admin\Frontend\Account\AccountController;
use MHMRentiva\Admin\Frontend\Shortcodes\ContactForm;
use MHMRentiva\Admin\Settings\Comments\CommentsSettings;
use MHMRentiva\Admin\Settings\Core\SettingsHelper;
use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use MHMRentiva\Admin\Utilities\Dashboard\DashboardPage;
use MHMRentiva\Admin\Vehicle\Meta\BlockedDatesMetaBox;
use MHMRentiva\Admin\Vehicle\Meta\VehicleGallery;
use MHMRentiva\Admin\Vehicle\Meta\VehicleMeta;
use MHMRentiva\Admin\Vehicle\Settings\VehicleSettings;
use MHMRentiva\Core\Attribute\Transformers;
use WP_UnitTestCase;

/**
 * "%10 indirim" is how Turkish writes ten per cent. Core's text sanitizers take
 * "%10" for an encoded byte and drop it, so text a person wrote goes through
 * Sanitizer::line() / Sanitizer::lines() instead.
 */
final class PercentInWrittenTextTest extends WP_UnitTestCase
{
	private const LINE  = 'Erken rezervasyona %10 indirim, %20ye varan';
	private const LINES = "Haftalıkta %10 indirim\nAylıkta %25";

	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
		$this->vehicle_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
		));
	}

	public function tearDown(): void
	{
		$_POST = array();
		delete_option('mhmrentiva_custom_features');
		parent::tearDown();
	}

	/**
	 * @param mixed ...$args
	 * @return mixed
	 */
	private static function call(string $class, string $method, ...$args)
	{
		$m = new \ReflectionMethod($class, $method);
		$m->setAccessible(true);

		return $m->invoke(null, ...$args);
	}

	public function test_line_and_lines_keep_the_text_as_written(): void
	{
		$this->assertSame(self::LINE, Sanitizer::line(self::LINE));
		$this->assertSame(self::LINES, Sanitizer::lines(self::LINES));
		$this->assertSame('a b c', Sanitizer::line(" a\r\n b\t c "));
		$this->assertSame('', Sanitizer::line(null));
		$this->assertSame('', Sanitizer::lines(array( 'x' )));
		$this->assertSame('7', Sanitizer::line(7));
	}

	/**
	 * Everything the core sanitizers do besides the percent rule still happens.
	 *
	 * @dataProvider markup
	 */
	public function test_markup_is_removed_as_core_removes_it(string $raw): void
	{
		$this->assertSame(sanitize_text_field($raw), Sanitizer::line($raw));
		$this->assertSame(sanitize_textarea_field($raw), Sanitizer::lines($raw));
	}

	/** @return array<string, array{string}> */
	public static function markup(): array
	{
		return array(
			'script'         => array( "<script>alert('x')</script>Merhaba" ),
			'style'          => array( '<style>b{}</style>Merhaba' ),
			'attribute'      => array( '<img src=x onerror=alert(1)>Merhaba' ),
			'open tag'       => array( 'a < b ve <b' ),
			'tag over lines' => array( "<\nimg src=x onerror=alert(1)>\nson" ),
			'nested'         => array( '<scr<script>ipt>alert(1)</scr</script>ipt>' ),
			'plain lines'    => array( "bir\n\niki\t üç  " ),
			'bad utf8'       => array( "a\xC3\x28b" ),
		);
	}

	public function test_an_encoded_tag_stays_characters(): void
	{
		$this->assertSame('%3Cscript%3E', esc_html(Sanitizer::line('%3Cscript%3E')));
	}

	public function test_verified_request(): void
	{
		$req = VerifiedRequest::from(wp_slash(array( 'a' => self::LINE . ' "x"', 'b' => self::LINES, 'c' => array( '1' ) )));

		$this->assertSame(self::LINE . ' "x"', $req->line('a'));
		$this->assertSame(self::LINES, $req->textarea('b'));
		$this->assertSame('yok', $req->line('c', 'yok'));
		$this->assertSame('yok', $req->line('d', 'yok'));
	}

	public function test_additional_service(): void
	{
		$result = AddonScreen::quick_create(array(
			'nonce'       => wp_create_nonce(AddonScreen::NONCE_ACTION),
			'title'       => self::LINE,
			'description' => self::LINES,
			'price'       => '5',
		));

		$post = get_post($result['addon_id']);
		$this->assertSame(self::LINE, $post->post_title);
		$this->assertSame(self::LINES, $post->post_excerpt);
	}

	public function test_settings_tabs(): void
	{
		$booking = SettingsSanitizer::sanitize(array(
			'current_active_tab'                       => 'booking',
			'mhmrentiva_booking_security_deposit_note' => self::LINES,
			'mhmrentiva_booking_fuel_policy_note'      => self::LINES,
			'mhmrentiva_rental_agreement_text'         => self::LINES,
		));
		$this->assertSame(self::LINES, $booking['mhmrentiva_booking_security_deposit_note']);
		$this->assertSame(self::LINES, $booking['mhmrentiva_booking_fuel_policy_note']);
		$this->assertSame(self::LINES, $booking['mhmrentiva_rental_agreement_text']);

		$url      = 'https://example.org/ara%C3%A7-kirala/';
		$frontend = SettingsSanitizer::sanitize(array(
			'current_active_tab'             => 'frontend',
			'mhmrentiva_text_book_now'       => self::LINE,
			'mhmrentiva_text_login_required' => self::LINES,
			'mhmrentiva_booking_url'         => $url,
		));
		$this->assertSame(self::LINE, $frontend['mhmrentiva_text_book_now']);
		$this->assertSame(self::LINES, $frontend['mhmrentiva_text_login_required']);
		$this->assertSame($url, $frontend['mhmrentiva_booking_url'], 'An encoded address keeps its letters.');

		$this->assertSame(self::LINE, SettingsSanitizer::safe_text(self::LINE));
		$this->assertSame(self::LINE, SettingsHelper::sanitize_field(self::LINE));
		$this->assertSame(self::LINES, SettingsHelper::sanitize_field(self::LINES, 'textarea'));
	}

	public function test_transfer_types_and_a_write_that_names_no_tab(): void
	{
		$transfer = self::call(SettingsSanitizer::class, 'sanitize_transfer_settings', array( 'mhmrentiva_transfer_custom_types' => self::LINES ), array());
		$this->assertSame(self::LINES, $transfer['mhmrentiva_transfer_custom_types']);

		$out = self::call(
			SettingsSanitizer::class,
			'sanitize_programmatic_update',
			array( 'mhmrentiva_rental_agreement_text' => self::LINES, 'mhmrentiva_email_from_name' => self::LINE ),
			array( 'mhmrentiva_rental_agreement_text' => '', 'mhmrentiva_email_from_name' => '' )
		);
		$this->assertSame(self::LINES, $out['mhmrentiva_rental_agreement_text']);
		$this->assertSame(self::LINE, $out['mhmrentiva_email_from_name']);
	}

	public function test_spam_words(): void
	{
		$this->assertSame(array( '%50 indirim' ), self::call(CommentsSettings::class, 'sanitize_value', '%50 indirim', array(), 'spam_words'));
		$this->assertSame(self::LINE, self::call(CommentsSettings::class, 'sanitize_value', self::LINE, '', 'any'));
	}

	public function test_blocked_date_note(): void
	{
		$_POST = wp_slash(array(
			'mhmrentiva_blocked_dates_nonce'  => wp_create_nonce('mhmrentiva_blocked_dates_save'),
			'_mhmrentiva_blocked_dates'       => '["2099-01-04"]',
			'_mhmrentiva_blocked_dates_notes' => wp_json_encode(array( '2099-01-04' => 'a < b, ' . self::LINES )),
		));
		BlockedDatesMetaBox::save($this->vehicle_id);

		$this->assertSame(
			array( '2099-01-04' => Sanitizer::lines('a < b, ' . self::LINES) ),
			BlockedDatesMetaBox::get_blocked_notes($this->vehicle_id)
		);

		$notes = self::call(
			BlockedDatesMetaBox::class,
			'parse_notes_from_payload',
			VerifiedRequest::from(wp_slash(array( 'notes' => wp_json_encode(array( '2099-01-04' => self::LINES )) ))),
			array( '2099-01-04' )
		);
		$this->assertSame(array( '2099-01-04' => self::LINES ), $notes);
	}

	public function test_gallery_texts(): void
	{
		$image = self::factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg', $this->vehicle_id);
		$_POST = wp_slash(array(
			'mhmrentiva_gallery_images_nonce' => wp_create_nonce('mhmrentiva_gallery_images'),
			'mhmrentiva_gallery_images'       => wp_json_encode(array( array( 'id' => $image, 'url' => 'x', 'alt' => self::LINE, 'title' => '<b>' . self::LINE . '</b>' ) )),
		));
		VehicleGallery::save_gallery_images($this->vehicle_id);

		$saved = json_decode((string) get_post_meta($this->vehicle_id, '_mhmrentiva_gallery_images', true), true);
		$this->assertSame(self::LINE, $saved[0]['alt']);
		$this->assertSame(self::LINE, $saved[0]['title']);

		$saved = json_decode(VehicleGallery::sanitize_gallery_images(array( array( 'id' => $image, 'url' => '', 'alt' => self::LINE, 'title' => self::LINE ) )), true);
		$this->assertSame(array( self::LINE, self::LINE ), array( $saved[0]['alt'], $saved[0]['title'] ));
	}

	public function test_vehicle_fields(): void
	{
		update_option('mhmrentiva_custom_features', array( 'custom_1' => 'Kampanya' ));
		$_POST = wp_slash(array(
			'mhmrentiva_vehicle_meta_nonce' => wp_create_nonce('mhmrentiva_vehicle_meta_action'),
			'mhmrentiva_color'              => 'Mavi, %10 metalik',
			'mhmrentiva_custom_1'           => self::LINE,
			'mhmrentiva_custom_details'     => array( 'k' => array( 'label' => self::LINE, 'value' => self::LINE ) ),
		));
		VehicleMeta::save_meta($this->vehicle_id, get_post($this->vehicle_id));

		$this->assertSame('Mavi, %10 metalik', get_post_meta($this->vehicle_id, '_mhmrentiva_color', true));
		$this->assertSame(self::LINE, get_post_meta($this->vehicle_id, '_mhmrentiva_custom_1', true));
		$this->assertSame(
			array( 'k' => array( 'label' => self::LINE, 'value' => self::LINE ) ),
			get_post_meta($this->vehicle_id, '_mhmrentiva_custom_details', true)
		);
		$this->assertSame(array( 'custom_1' => self::LINE ), VehicleSettings::sanitize_array_option(array( 'custom_1' => self::LINE )));
	}

	public function test_email_texts(): void
	{
		self::call(
			EmailTemplates::class,
			'save_email_fields',
			array( 'mhmrentiva_test_subject' => 'text', 'mhmrentiva_test_other' => 'other' ),
			VerifiedRequest::from(array( 'mhmrentiva_test_subject' => self::LINE, 'mhmrentiva_test_other' => self::LINE ))
		);
		$this->assertSame(self::LINE, get_option('mhmrentiva_test_subject'));
		$this->assertSame(self::LINE, get_option('mhmrentiva_test_other'));

		add_filter('pre_option_mhmrentiva_settings', static fn (): array => array( 'mhmrentiva_email_log_enabled' => '1' ));
		EmailLog::handle_email_sent('any', 'a@example.org', true, self::LINE, array());
		$log = get_posts(array( 'post_type' => EmailLog::TYPE, 'post_status' => 'any', 'numberposts' => 1 ));
		$this->assertSame(self::LINE, get_post_meta($log[0]->ID, '_mhmrentiva_email_subject', true));
	}

	public function test_contact_message(): void
	{
		$out = self::call(ContactForm::class, 'sanitize_contact_form_data', array( 'message' => self::LINES, 'company' => self::LINE, 'email' => 'a@example.org' ));

		$this->assertSame(self::LINES, $out['message']);
		$this->assertSame(self::LINE, $out['company']);
	}

	public function test_shortcode_and_dashboard_texts(): void
	{
		$this->assertSame(self::LINE, Transformers::transform(self::LINE, 'string'));

		add_filter('mhmrentiva_dashboard_quick_actions', static fn (): array => array( array( 'label' => self::LINE, 'href' => 'https://example.org/' ) ));
		$this->assertSame(self::LINE, DashboardPage::get_extra_quick_actions()[0]['label']);
	}

	public function test_account_address_and_custom_field_label(): void
	{
		add_filter('wp_doing_ajax', '__return_true');
		add_filter(
			'wp_die_ajax_handler',
			static fn () => static function (): void {
				throw new \RuntimeException('sent');
			}
		);

		$_POST = wp_slash(array( 'nonce' => wp_create_nonce('mhmrentiva_account'), 'address' => self::LINES ));
		$this->run_ajax(array( AccountController::class, 'ajax_update_account' ));
		$this->assertSame(self::LINES, get_user_meta(get_current_user_id(), 'mhmrentiva_address', true));

		$_POST = wp_slash(array( 'nonce' => wp_create_nonce('vehicle_settings_nonce'), 'field_label' => self::LINE, 'field_type' => 'features' ));
		$this->run_ajax(array( VehicleSettings::class, 'ajax_add_custom_field' ));
		$this->assertContains(self::LINE, (array) get_option('mhmrentiva_custom_features'));
	}

	private function run_ajax(callable $handler): void
	{
		ob_start();
		try {
			$handler();
		} catch (\RuntimeException $e) {
			$this->assertSame('sent', $e->getMessage());
		} finally {
			ob_end_clean();
		}
	}

	/**
	 * No multi-line field goes back to the core sanitizer, in the code or in a
	 * template. Read as tokens, so a comment that names it does not count.
	 */
	public function test_no_code_calls_the_core_textarea_sanitizer(): void
	{
		$found = array();
		$seen  = 0;
		foreach (array( 'src', 'templates' ) as $dir) {
			$files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(MHMRENTIVA_PLUGIN_DIR . $dir, \FilesystemIterator::SKIP_DOTS));
			foreach ($files as $file) {
				if ('php' !== $file->getExtension()) {
					continue;
				}
				++$seen;
				foreach (token_get_all((string) file_get_contents($file->getPathname())) as $token) {
					if (is_array($token)
						&& in_array($token[0], array( T_STRING, T_NAME_FULLY_QUALIFIED, T_CONSTANT_ENCAPSED_STRING ), true)
						&& 'sanitize_textarea_field' === trim($token[1], '\\\'"')) {
						$found[] = $file->getPathname() . ':' . $token[2];
					}
				}
			}
		}

		$this->assertGreaterThan(300, $seen, 'The scan reads the tree.');
		$this->assertSame(array(), $found);
	}

	/**
	 * Fields read inside a handler that no test above reaches.
	 *
	 * @dataProvider handler_reads
	 */
	public function test_handler_reads_the_field_as_written(string $file, string $read): void
	{
		$this->assertStringContainsString($read, (string) file_get_contents(MHMRENTIVA_PLUGIN_DIR . $file));
	}

	/** @return array<string, array{string, string}> */
	public static function handler_reads(): array
	{
		return array(
			'customer e-mail subject' => array( 'src/Admin/Booking/Meta/BookingMeta.php', "->line('email_subject')" ),
			'manual booking notes'    => array( 'src/Admin/Booking/Meta/ManualBookingMetaBox.php', "->textarea('notes')" ),
			'cancellation reason'     => array( 'src/Admin/Frontend/Account/AccountController.php', "->textarea('reason')" ),
			'new customer address'    => array( 'src/Admin/Customers/AddCustomerPage.php', "->textarea( 'customer_address' )" ),
			'customer address'        => array( 'src/Admin/Customers/CustomersPage.php', "->textarea('customer_address')" ),
			'contact form message'    => array( 'src/Admin/Frontend/Shortcodes/ContactForm.php', "->textarea('message')" ),
			'contact form company'    => array( 'src/Admin/Frontend/Shortcodes/ContactForm.php', "->line('company')" ),
			'wizard sender name'      => array( 'src/Admin/Setup/SetupWizard.php', "->line('sender_name')" ),
			'custom field options'    => array( 'src/Admin/Vehicle/Settings/VehicleSettings.php', "->line( 'options' )" ),
			'search keyword'          => array( 'src/Admin/Frontend/Shortcodes/SearchResults.php', "->line(self::query_var('keyword'))" ),
		);
	}
}
