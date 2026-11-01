<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Frontend\Account\AccountBookingsQuery;
use MHMRentiva\Admin\Frontend\Shortcodes\Contact\RecipientToken;
use MHMRentiva\Admin\Frontend\Shortcodes\ContactForm;
use MHMRentiva\Admin\Frontend\Shortcodes\ContactFormView;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Tests\Support\RequestSimulator;
use WP_Ajax_UnitTestCase;

/**
 * PR F server rules: topics (R1, R2), own booking list (R3), consent (R6),
 * auto reply from the token only (R8, m3), honeypot (R10, m7), no HTML cache (R9).
 */
final class ContactFormSubmissionRulesTest extends WP_Ajax_UnitTestCase
{
	/** @var list<array<string,mixed>> */
	private array $mails = array();

	public function setUp(): void
	{
		parent::setUp();
		ContactForm::register();
		RequestSimulator::new_request();
		AccountBookingsQuery::reset();
		$this->mails = array();
		add_filter(
			'pre_wp_mail',
			function ($short, array $atts) {
				$this->mails[] = $atts;
				return true;
			},
			10,
			2
		);
	}

	public function tearDown(): void
	{
		remove_all_filters('pre_wp_mail');
		remove_all_filters('mhmrentiva_shortcode_html_cache_enabled');
		delete_option(SettingsCore::OPTION_NAME);
		$_POST = array();
		wp_set_current_user(0);
		AccountBookingsQuery::reset();
		parent::tearDown();
	}

	/**
	 * @param array<string,string> $fields Extra POST fields.
	 * @return array<string,mixed>
	 */
	private function submit(array $fields = array()): array
	{
		$_POST = array_merge(
			array(
				'action'  => 'mhmrentiva_submit_contact_form',
				'nonce'   => wp_create_nonce('mhmrentiva_contact_form_nonce'),
				'type'    => 'general',
				'name'    => 'Ada Visitor',
				'email'   => 'ada@example.com',
				'message' => 'Hello there',
			),
			$fields
		);
		$this->_last_response = '';
		$level                = ob_get_level();
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
		$raw     = (string) $this->_last_response;
		$cut     = strpos($raw, '}{"success"');
		$decoded = json_decode(false === $cut ? $raw : substr($raw, 0, $cut + 1), true);
		$this->assertIsArray($decoded, 'AJAX response: ' . $this->_last_response);
		return $decoded;
	}

	private function render(string $shortcode): string
	{
		RequestSimulator::new_request();
		return do_shortcode($shortcode);
	}

	private function consent_on(): void
	{
		$settings = (array) get_option(SettingsCore::OPTION_NAME, array());
		$settings['mhmrentiva_contact_consent_enabled'] = '1';
		update_option(SettingsCore::OPTION_NAME, $settings);
	}

	private function contact_count(): int
	{
		return count(
			get_posts(
				array(
					'post_type'   => 'mhmrentiva_contact',
					'post_status' => 'private',
					'numberposts' => -1,
					'fields'      => 'ids',
				)
			)
		);
	}

	public function test_booking_needs_no_phone_and_feedback_no_rating(): void
	{
		$booking = $this->submit(array( 'type' => 'booking' ));
		$this->assertTrue($booking['success'], 'R2: booking without a phone.');
		$this->assertSame('booking', get_post_meta((int) $booking['data']['message_id'], '_mhmrentiva_contact_type', true));

		$feedback = $this->submit(array( 'type' => 'feedback' ));
		$this->assertTrue($feedback['success'], 'R2: feedback without a rating.');
		$this->assertSame('feedback', get_post_meta((int) $feedback['data']['message_id'], '_mhmrentiva_contact_type', true));

		$rated = $this->submit(array( 'type' => 'feedback', 'rating' => '4' ));
		$this->assertSame('4', get_post_meta((int) $rated['data']['message_id'], '_mhmrentiva_contact_rating', true));

		$support = $this->submit(array( 'type' => 'support' ));
		$this->assertFalse($support['success'], 'R2: support still requires a priority.');
		$this->assertArrayHasKey('priority', $support['data']['errors']);

		$bad_phone = $this->submit(array( 'type' => 'booking', 'phone' => '12' ));
		$this->assertArrayHasKey('phone', $bad_phone['data']['errors'], 'A typed phone is still checked.');
	}

	public function test_field_errors_are_keyed_by_field(): void
	{
		$response = $this->submit(array( 'name' => '', 'message' => '' ));
		$this->assertFalse($response['success']);
		$this->assertArrayHasKey('name', $response['data']['errors']);
		$this->assertArrayHasKey('message', $response['data']['errors']);
	}

	public function test_list_holds_only_own_newest_twenty(): void
	{
		$user   = (int) self::factory()->user->create(array( 'role' => 'customer', 'user_email' => 'own@example.test' ));
		$other  = (int) self::factory()->user->create(array( 'role' => 'customer' ));
		$car    = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish', 'post_title' => 'Zz Fiat' ));
		$foreign = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish', 'post_date' => '2026-10-30 10:00:00' ));
		update_post_meta($foreign, MetaKeys::BOOKING_CUSTOMER_USER_ID, $other);

		$own = array();
		for ($i = 1; $i <= 22; $i++) {
			$id = (int) self::factory()->post->create(
				array(
					'post_type'   => 'mhmrentiva_booking',
					'post_status' => 'publish',
					'post_date'   => sprintf('2026-09-%02d 10:00:00', $i),
				)
			);
			update_post_meta($id, MetaKeys::BOOKING_CUSTOMER_USER_ID, $user);
			update_post_meta($id, MetaKeys::BOOKING_VEHICLE_ID, $car);
			update_post_meta($id, MetaKeys::BOOKING_PICKUP_DATE, '2026-11-02');
			update_post_meta($id, MetaKeys::BOOKING_DROPOFF_DATE, '2026-11-05');
			$own[ $i ] = $id;
		}
		wp_set_current_user($user);

		$method = new \ReflectionMethod(ContactForm::class, 'own_booking_rows');
		$method->setAccessible(true);
		$rows = $method->invoke(null);

		$this->assertCount(20, $rows);
		$this->assertSame($own[22], $rows[0]['id'], 'Newest created first.');
		$this->assertNotContains($own[1], array_column($rows, 'id'));
		$this->assertNotContains($foreign, array_column($rows, 'id'));
		$this->assertSame('Zz Fiat', $rows[0]['vehicle']);
		$this->assertStringContainsString('2026', $rows[0]['dates']);

		$html = $this->render('[rentiva_contact]');
		$this->assertStringContainsString('Zz Fiat', $html);
		$this->assertStringNotContainsString('value="' . $foreign . '"', $html);

		wp_set_current_user(0);
		AccountBookingsQuery::reset();
		$this->assertSame(array(), $method->invoke(null), 'A guest has no list.');
	}

	public function test_consent_off_changes_nothing(): void
	{
		$this->assertStringNotContainsString('name="consent"', $this->render('[rentiva_contact]'));

		$response = $this->submit(array( 'consent' => '1' ));
		$this->assertTrue($response['success']);
		$this->assertSame('', get_post_meta((int) $response['data']['message_id'], MetaKeys::CONTACT_CONSENT, true), 'A forged field writes no record.');
	}

	public function test_consent_on_is_required_and_recorded(): void
	{
		$this->consent_on();
		$this->assertStringContainsString('name="consent"', $this->render('[rentiva_contact]'));

		$before  = $this->contact_count();
		$refused = $this->submit();
		$this->assertFalse($refused['success']);
		$this->assertArrayHasKey('consent', $refused['data']['errors']);
		$this->assertSame($before, $this->contact_count());

		$ok = $this->submit(array( 'consent' => '1' ));
		$this->assertTrue($ok['success']);
		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) get_post_meta((int) $ok['data']['message_id'], MetaKeys::CONTACT_CONSENT, true));
	}

	public function test_honeypot_answers_success_and_stores_nothing(): void
	{
		$before   = $this->contact_count();
		$response = $this->submit(array( ContactFormView::HONEYPOT_FIELD => 'http://spam.example' ));

		$this->assertTrue($response['success'], 'The bot learns nothing.');
		$this->assertSame($before, $this->contact_count());
		$this->assertSame(array(), $this->mails);
	}

	public function test_an_autofilled_website_field_is_no_trap(): void
	{
		$before   = $this->contact_count();
		$response = $this->submit(array( 'website' => 'https://visitor.example' ));

		$this->assertTrue($response['success']);
		$this->assertSame($before + 1, $this->contact_count());
	}

	/**
	 * @return list<string> The addressees of the mails since the last call.
	 */
	private function take_recipients(): array
	{
		$to          = array_map(static fn(array $mail): string => implode(',', (array) $mail['to']), $this->mails);
		$this->mails = array();
		return $to;
	}

	public function test_one_auto_reply_per_address_per_hour(): void
	{
		$token = RecipientToken::issue('', 0, array( 'general' ), true);
		$admin = (string) get_option('admin_email');

		$this->submit(array( 'recipient_token' => $token ));
		$this->assertSame(array( $admin, 'ada@example.com' ), $this->take_recipients());

		$before = $this->contact_count();
		$second = $this->submit(array( 'recipient_token' => $token, 'email' => 'ADA@example.com' ));
		$this->assertTrue($second['success'], 'The visitor is told nothing different.');
		$this->assertSame(array( $admin ), $this->take_recipients(), 'Admin only; the address case changes nothing.');
		$this->assertSame($before + 1, $this->contact_count(), 'The message itself is saved.');

		$this->submit(array( 'recipient_token' => $token, 'email' => 'other@example.com' ));
		$this->assertSame(array( $admin, 'other@example.com' ), $this->take_recipients());

		$this->assertSame(1, (int) get_transient('mhmrentiva_contact_reply_' . wp_hash('ada@example.com')), 'Stored under a hash.');
		global $wpdb;
		$this->assertSame('0', (string) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", '%ada@example.com%')), 'No address in the options table.');

		$expiry = (int) get_option('_transient_timeout_mhmrentiva_contact_reply_' . wp_hash('ada@example.com'));
		if ($expiry > 0) {
			$this->assertLessThanOrEqual(time() + HOUR_IN_SECONDS, $expiry);
			$this->assertGreaterThan(time() + HOUR_IN_SECONDS - 60, $expiry);
		}

		// The hour is over: the address may get a reply again.
		delete_transient('mhmrentiva_contact_reply_' . wp_hash('ada@example.com'));
		$this->submit(array( 'recipient_token' => $token ));
		$this->assertSame(array( $admin, 'ada@example.com' ), $this->take_recipients());
	}

	public function test_a_refused_message_uses_no_reply_slot(): void
	{
		$token = RecipientToken::issue('', 0, array( 'general' ), true);

		$this->submit(array( 'recipient_token' => $token, 'message' => '' ));
		$this->assertSame(array(), $this->take_recipients());
		$this->assertFalse(get_transient('mhmrentiva_contact_reply_' . wp_hash('ada@example.com')));
	}

	public function test_auto_reply_name_is_capped_at_sixty_characters(): void
	{
		$long  = str_repeat('Ş', 60) . 'BUY-NOW';
		$token = RecipientToken::issue('', 0, array( 'general' ), true);

		$this->submit(array( 'recipient_token' => $token, 'name' => $long ));
		$this->assertCount(2, $this->mails);

		$reply = (string) $this->mails[1]['message'];
		$this->assertStringContainsString(str_repeat('Ş', 60), $reply);
		$this->assertStringNotContainsString('BUY-NOW', $reply);
		$this->assertStringContainsString('BUY-NOW', (string) $this->mails[0]['message'], 'The admin sees the name as typed.');
	}

	public function test_booking_list_query_runs_only_when_the_list_can_be_drawn(): void
	{
		$user = (int) self::factory()->user->create(array( 'role' => 'customer' ));
		wp_set_current_user($user);

		$count = 0;
		add_filter(
			'posts_pre_query',
			static function ($posts, $query) use (&$count) {
				if ('mhmrentiva_booking' === $query->get('post_type')) {
					++$count;
				}
				return $posts;
			},
			10,
			2
		);

		$this->render('[rentiva_contact type="general" show_topic_selector="0" show_booking_id_field="0"]');
		$this->assertSame(0, $count, 'A fixed form without the field runs no booking query.');

		$this->render('[rentiva_contact type="general" show_topic_selector="0" show_booking_id_field="1"]');
		$this->assertSame(1, $count);

		$this->render('[rentiva_contact]');
		$this->assertSame(2, $count);

		wp_set_current_user(0);
		$this->render('[rentiva_contact]');
		$this->assertSame(2, $count, 'A guest runs none.');
	}

	public function test_no_token_means_no_auto_reply(): void
	{
		$this->submit(array( 'auto_reply' => '1' ));
		$this->assertCount(1, $this->mails, 'm3: only the notification; a posted auto_reply is not read.');
		$this->assertNotSame(array( 'ada@example.com' ), (array) $this->mails[0]['to']);
	}

	public function test_the_token_of_a_form_decides_the_auto_reply(): void
	{
		$html = $this->render('[rentiva_contact]');
		$this->assertSame(1, preg_match('/name="recipient_token" value="([^"]+)"/', $html, $m), 'Default auto reply: the form carries a token.');
		$this->assertStringNotContainsString('name="auto_reply"', $html);

		$this->submit(array( 'recipient_token' => html_entity_decode($m[1], ENT_QUOTES) ));
		$this->assertCount(2, $this->mails);
		$this->assertSame('ada@example.com', is_array($this->mails[1]['to']) ? $this->mails[1]['to'][0] : $this->mails[1]['to']);

		$this->mails = array();
		$this->assertStringNotContainsString('name="recipient_token"', $this->render('[rentiva_contact auto_reply="0"]'), 'Off and no custom recipient: nothing to carry.');
		$this->submit();
		$this->assertCount(1, $this->mails);

		// A forged field beside a token of a form with the reply off changes nothing.
		$this->mails = array();
		$off         = RecipientToken::issue('', 0, array( 'general' ), false);
		$this->submit(array( 'recipient_token' => $off, 'auto_reply' => '1' ));
		$this->assertCount(1, $this->mails);
	}

	public function test_tampered_token_sends_no_auto_reply(): void
	{
		$token = RecipientToken::issue('', 0, array( 'general' ), true);
		$this->assertTrue(RecipientToken::allows_auto_reply($token, 'general'));
		$this->assertFalse(RecipientToken::allows_auto_reply($token, 'support'), 'Outside the set.');

		$raw                     = (string) base64_decode($token, true);
		$raw[ strlen($raw) - 1 ] = chr(ord($raw[ strlen($raw) - 1 ]) ^ 0x01);
		$this->assertFalse(RecipientToken::allows_auto_reply(base64_encode($raw), 'general'));
		$this->assertFalse(RecipientToken::allows_auto_reply('', 'general'));
	}

	public function test_form_stays_out_of_the_html_cache(): void
	{
		$enabled = new \ReflectionMethod(ContactForm::class, 'is_caching_enabled');
		$enabled->setAccessible(true);
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_true', 999);
		$this->assertFalse($enabled->invoke(null), 'R9: nonce and token carrying form.');

		$key = new \ReflectionMethod(ContactForm::class, 'get_cache_key');
		$key->setAccessible(true);
		$this->assertSame('', $key->invoke(null, array()));
	}
}
