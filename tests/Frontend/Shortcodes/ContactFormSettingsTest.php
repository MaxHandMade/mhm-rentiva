<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\ContactForm;
use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Tests\Support\RenderNormalizer;
use MHMRentiva\Tests\Support\RequestSimulator;
use WP_Ajax_UnitTestCase;

/**
 * Slice 3A Task 1: the contact form's settings reach its template.
 *
 * Before: templates/shortcodes/contact-form.php read `$args['atts']`, but
 * Templates::include_template_with_vars() unpacks each template var on its own,
 * so `$args` was never defined and every setting was overwritten with an empty
 * array -- every form rendered as "general" (spec 3A §1, §2.2 exception 1).
 */
final class ContactFormSettingsTest extends WP_Ajax_UnitTestCase
{
	public function setUp(): void
	{
		parent::setUp();
		ContactForm::register();
		RequestSimulator::new_request();
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
	}

	public function tearDown(): void
	{
		remove_all_filters('pre_wp_mail');
		unset($_POST['nonce'], $_POST['name'], $_POST['email'], $_POST['message'], $_POST['type'], $_POST['booking_id'], $_POST['auto_reply']);
		wp_set_current_user(0);
		parent::tearDown();
	}

	private function render(string $shortcode): string
	{
		RequestSimulator::new_request();
		return do_shortcode($shortcode);
	}

	private function render_block(array $attrs): string
	{
		RequestSimulator::new_request();
		return render_block(
			array(
				'blockName'    => 'mhm-rentiva/contact',
				'attrs'        => $attrs,
				'innerHTML'    => '',
				'innerContent' => array(),
				'innerBlocks'  => array(),
			)
		);
	}

	public function test_atts_reach_the_template(): void
	{
		$booking = $this->render('[rentiva_contact type="booking"]');
		$this->assertStringContainsString('data-form-type="booking"', $booking);
		$this->assertStringContainsString('name="preferred_date"', $booking, 'type=booking renders the booking form (spec §2.2 exception 1).');
		$this->assertStringContainsString('<input type="hidden" name="type" value="booking">', $booking);

		$feedback = $this->render('[rentiva_contact type="feedback"]');
		$this->assertStringContainsString('name="rating"', $feedback);

		$general = $this->render('[rentiva_contact]');
		$this->assertStringContainsString('data-form-type="general"', $general);
		$this->assertStringNotContainsString('name="preferred_date"', $general);
		$this->assertStringContainsString('name="phone"', $general, 'Default unchanged: the phone field shows.');
		$this->assertStringContainsString('name="attachment"', $general, 'Default unchanged: the attachment field shows.');
		$this->assertStringContainsString('rv-contact-info', $general, 'Default unchanged: the contact information box shows.');

		$this->assertStringNotContainsString('name="phone"', $this->render('[rentiva_contact show_phone="0"]'));
		$this->assertStringNotContainsString('name="attachment"', $this->render('[rentiva_contact show_attachment="0"]'));
		$this->assertStringContainsString('rv-theme-compact', $this->render('[rentiva_contact theme="compact"]'));
		$this->assertStringContainsString('name="priority"', $this->render('[rentiva_contact show_priority="1"]'));
		$this->assertStringContainsString('<input type="hidden" name="auto_reply" value="0">', $this->render('[rentiva_contact auto_reply="0"]'));
		$this->assertStringNotContainsString('rv-contact-info', $this->render('[rentiva_contact show_company_info="0"]'));

		// The company field no longer hides behind the phone row.
		$company = $this->render('[rentiva_contact show_phone="0" show_company="1"]');
		$this->assertStringContainsString('name="company"', $company);
		$this->assertStringNotContainsString('name="phone"', $company);

		self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish', 'post_title' => 'Zz Probe Car' ));
		$this->assertStringContainsString('Zz Probe Car', $this->render('[rentiva_contact show_vehicle_selector="1"]'));

		$booking_id_on = $this->render('[rentiva_contact show_booking_id_field="1"]');
		$this->assertStringContainsString('name="booking_id"', $booking_id_on);
		$this->assertStringNotContainsString('name="booking_id"', $general);

		// The block path: its attributes reach the same template.
		$block = $this->render_block(array( 'type' => 'support', 'showPhone' => false, 'showVehicleSelector' => true, 'showBookingIdField' => true ));
		$this->assertStringContainsString('data-form-type="support"', $block);
		$this->assertStringNotContainsString('name="phone"', $block);
		$this->assertStringContainsString('name="vehicle_id"', $block);
		$this->assertStringContainsString('name="booking_id"', $block);
	}

	public function test_title_and_description_render_when_set(): void
	{
		$html = $this->render('[rentiva_contact title="Write to us" description="We answer within a day."]');
		$this->assertMatchesRegularExpression('#<h3 class="rv-contact-title">\s*Write to us\s*</h3>#', $html);
		$this->assertMatchesRegularExpression('#<p class="rv-contact-description">\s*We answer within a day.\s*</p>#', $html);

		$only_title = $this->render('[rentiva_contact title="Only a title"]');
		$this->assertStringContainsString('rv-contact-title', $only_title);
		$this->assertStringNotContainsString('rv-contact-description', $only_title);

		$default = $this->render('[rentiva_contact]');
		$this->assertStringNotContainsString('rv-contact-header', $default, 'Rule 1: an untouched form gets no header.');
	}

	public function test_success_message_is_a_data_attribute(): void
	{
		$html = $this->render('[rentiva_contact success_message=\'Thanks & "welcome"\']');
		$this->assertStringContainsString('data-success-message="Thanks &amp; &quot;welcome&quot;"', $html);
		$this->assertStringNotContainsString('data-success-message', $this->render('[rentiva_contact]'), 'Rule 1: nothing is added when unset.');

		$js = (string) file_get_contents(MHMRENTIVA_PLUGIN_DIR . 'assets/js/frontend/contact-form.js'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local asset.
		$this->assertStringNotContainsString('innerHTML', $js, 'No markup is built from strings in the contact form script.');
		$this->assertStringContainsString('dataset.successMessage', $js);
		$this->assertMatchesRegularExpression('/\.textContent\s*=\s*message/', $js, 'The message (custom or from the response) is set as text.');
	}

	public function test_redirect_url_off_site_is_dropped(): void
	{
		$off = $this->render('[rentiva_contact redirect_url="https://evil.example/landing"]');
		$this->assertStringNotContainsString('evil.example', $off);
		$this->assertStringNotContainsString('data-redirect-url', $off, 'wp_validate_redirect( $url, \'\' ): no redirect, not admin_url().');

		$js_scheme = $this->render('[rentiva_contact redirect_url="javascript:alert(1)"]');
		$this->assertStringNotContainsString('data-redirect-url', $js_scheme);

		$same_site = home_url('/thank-you/');
		$on        = $this->render('[rentiva_contact redirect_url="' . $same_site . '"]');
		$this->assertStringContainsString('data-redirect-url="' . esc_url($same_site) . '"', $on);
		$this->assertStringNotContainsString('data-redirect-url', $this->render('[rentiva_contact]'));

		$js = (string) file_get_contents(MHMRENTIVA_PLUGIN_DIR . 'assets/js/frontend/contact-form.js'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local asset.
		$this->assertStringContainsString('dataset.redirectUrl', $js);
	}

	public function test_booking_id_response_is_identical_for_match_and_miss(): void
	{
		$user = (int) self::factory()->user->create(array( 'role' => 'subscriber', 'user_email' => 'renter@example.com' ));
		$own  = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));
		update_post_meta($own, MetaKeys::BOOKING_CUSTOMER_USER_ID, $user);
		$other_user = (int) self::factory()->user->create(array( 'role' => 'subscriber' ));
		$other      = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));
		update_post_meta($other, MetaKeys::BOOKING_CUSTOMER_USER_ID, $other_user);

		$mails = array();
		add_filter(
			'pre_wp_mail',
			static function ($short, array $atts) use (&$mails) {
				$mails[] = $atts;
				return true;
			},
			10,
			2
		);

		wp_set_current_user($user);
		$match = $this->submit(array( 'booking_id' => (string) $own ));
		$miss  = $this->submit(array( 'booking_id' => '999999' ));
		$foreign = $this->submit(array( 'booking_id' => (string) $other ));

		$this->assertTrue($match['success']);
		$this->assertSame($this->without_id($match), $this->without_id($miss), 'No enumeration oracle: a real and an unknown number answer the same.');
		$this->assertSame($this->without_id($match), $this->without_id($foreign));

		$this->assertSame($own, (int) get_post_meta((int) $match['data']['message_id'], '_mhmrentiva_contact_booking_id', true), 'The renter\'s own booking is linked.');
		$this->assertSame('', get_post_meta((int) $miss['data']['message_id'], '_mhmrentiva_contact_booking_id', true));
		$this->assertSame('', get_post_meta((int) $foreign['data']['message_id'], '_mhmrentiva_contact_booking_id', true), 'Someone else\'s booking is never linked.');
		$this->assertStringContainsString('#999999', (string) $mails[2]['message'], 'An unlinked number still reaches the e-mail as typed.');

		wp_set_current_user(0);
		$guest = $this->submit(array( 'booking_id' => (string) $own ));
		$this->assertSame($this->without_id($match), $this->without_id($guest));
		$this->assertSame('', get_post_meta((int) $guest['data']['message_id'], '_mhmrentiva_contact_booking_id', true), 'A guest submission is never linked to a record.');
		$this->assertStringContainsString('#' . $own, (string) $mails[6]['message'], 'The guest\'s number reaches the e-mail as typed (absint).');
	}

	public function test_removed_attributes_in_shortcode_text_are_ignored(): void
	{
		$notices = array();
		set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- test captures notices.
			static function (int $no, string $str) use (&$notices): bool {
				$notices[] = $str;
				return true;
			}
		);
		try {
			$legacy  = $this->render('[rentiva_contact show_map="1" recipient_email="x@y.z" show_social_links="1" subject_prefix="ZZ" show_subject_field="1" show_phone_field="0" show_vehicle_select="1"]');
			$default = $this->render('[rentiva_contact]');
			$block   = $this->render_block(array( 'showMap' => true, 'recipientEmail' => 'x@y.z', 'showSocialLinks' => true, 'subjectPrefix' => 'ZZ', 'showSubjectField' => true, 'showPhoneField' => false, 'showVehicleSelect' => true ));
			$block_default = $this->render_block(array());
		} finally {
			restore_error_handler();
		}

		$this->assertSame(array(), $notices, 'No notice or warning for a removed attribute.');
		$this->assertSame(RenderNormalizer::normalize($default), RenderNormalizer::normalize($legacy), 'Removed attributes are ignored, as today.');
		$this->assertSame(RenderNormalizer::normalize($block_default), RenderNormalizer::normalize($block));
		$this->assertStringNotContainsString('recipient_token', $legacy, 'recipient_email does not change the recipient.');
		$this->assertStringNotContainsString('x@y.z', $legacy . $block);
	}

	/**
	 * @param array<string,string> $fields Extra POST fields.
	 * @return array<string,mixed>
	 */
	private function submit(array $fields): array
	{
		$_POST = array_merge(
			array(
				'action'  => 'mhmrentiva_submit_contact_form',
				'nonce'   => wp_create_nonce('mhmrentiva_contact_form_nonce'),
				'type'    => 'general',
				'name'    => 'Ada Renter',
				'email'   => 'ada@example.com',
				'message' => 'About my booking',
			),
			$fields
		);
		$this->_last_response = '';
		// The handler's catch (Exception) also catches the harness's die exception and
		// answers again; that second die closes one more buffer than _handleAjax opened.
		$level = ob_get_level();
		ob_start();
		try {
			$this->_handleAjax('mhmrentiva_submit_contact_form');
		} catch (\WPAjaxDieContinueException $e) {
			unset($e);
		} finally {
			while (ob_get_level() > $level) {
				ob_end_clean();
			}
		}
		// wp_send_json()'s die is an exception under WP_Ajax_UnitTestCase, which the
		// handler's own catch (Exception) answers a second time; the first JSON is the real one.
		$raw     = (string) $this->_last_response;
		$cut     = strpos($raw, '}{"success"');
		$decoded = json_decode(false === $cut ? $raw : substr($raw, 0, $cut + 1), true);
		$this->assertIsArray($decoded, 'AJAX response: ' . $this->_last_response);
		return $decoded;
	}

	/**
	 * @param array<string,mixed> $response Decoded response.
	 * @return array<string,mixed>
	 */
	private function without_id(array $response): array
	{
		unset($response['data']['message_id']);
		return $response;
	}
}
