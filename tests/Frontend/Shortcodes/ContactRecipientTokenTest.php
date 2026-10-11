<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\Contact\RecipientToken;
use MHMRentiva\Admin\Frontend\Shortcodes\ContactForm;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\SiteClock;
use WP_Ajax_UnitTestCase;

/**
 * Slice 3A Task 1, spec §2.5 (a)-(d) + R3: the per-form recipient (`email_to`).
 *
 * The submit AJAX sees only the POST. A plain address there would be an open
 * relay, so the address travels as a sodium secretbox payload {email, post_id,
 * type} bound to the source post; the server re-checks the post, its author's
 * `edit_others_posts` and the type on every submission. Every failure falls
 * back to the global recipient and is announced (hook + error log).
 */
final class ContactRecipientTokenTest extends WP_Ajax_UnitTestCase
{
	use SiteClock;

	private const CUSTOM = 'sales-desk@example.com';

	/** @var list<string> */
	private array $reasons = array();

	/** @var list<array<string,mixed>> */
	private array $mails = array();

	private string $log_file = '';

	private string $log_before = '';

	private int $editor = 0;

	private int $author = 0;

	public function setUp(): void
	{
		parent::setUp();
		ContactForm::register();
		RequestSimulator::new_request();
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);

		$this->editor = (int) self::factory()->user->create(array( 'role' => 'editor' ));
		$this->author = (int) self::factory()->user->create(array( 'role' => 'author' ));

		$this->reasons = array();
		add_action(
			'mhmrentiva_contact_recipient_fallback',
			function (string $reason): void {
				$this->reasons[] = $reason;
			}
		);

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

		$this->log_file   = (string) wp_tempnam('rv-recipient-log');
		$this->log_before = (string) ini_get('error_log');
		ini_set('error_log', $this->log_file); // phpcs:ignore WordPress.PHP.IniSet.Risky -- test captures the fallback log line.
	}

	public function tearDown(): void
	{
		ini_set('error_log', $this->log_before); // phpcs:ignore WordPress.PHP.IniSet.Risky -- restore.
		if ('' !== $this->log_file && file_exists($this->log_file)) {
			unlink($this->log_file); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test temp file.
		}
		remove_all_filters('pre_wp_mail');
		$_POST           = array();
		$GLOBALS['post'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test cleanup.
		wp_set_current_user(0);
		$this->release_site_clock();
		parent::tearDown();
	}

	/**
	 * A page by $user that carries the form with the custom recipient (the address
	 * has to stay on the source post for the payload to vouch for it).
	 */
	private function page_by(int $user, ?string $content = null): int
	{
		return (int) self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_author'  => $user,
				// wp_insert_post() unslashes: a block comment's \u0026 would lose its backslash.
				'post_content' => wp_slash($content ?? '[rentiva_contact email_to="' . self::CUSTOM . '"]'),
			)
		);
	}

	/**
	 * The contact block's markup with $email as its emailTo attribute.
	 */
	private static function contact_block(string $email): string
	{
		return serialize_block(
			array(
				'blockName'    => 'mhm-rentiva/contact',
				'attrs'        => array( 'emailTo' => $email ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
	}

	/**
	 * Whether a refused payload reached the PHP log, as the logging rule says it should.
	 */
	private function assert_log_matches_debug_log(string $reason): void
	{
		$log = (string) file_get_contents($this->log_file); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test temp file.
		if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
			$this->assertStringContainsString($reason, $log, 'With WP_DEBUG_LOG the fallback is written to the error log.');
		} else {
			$this->assertSame('', $log, 'Without WP_DEBUG_LOG a refused payload writes nothing to the PHP log.');
		}
	}

	/**
	 * Render the form on $post_id (the visitor's view) and return its recipient token.
	 */
	private function token_from_render(int $post_id, string $atts = ''): string
	{
		// The displayed post, as get_the_ID() sees it (null: no post, e.g. an archive).
		$GLOBALS['post'] = $post_id > 0 ? get_post($post_id) : null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test sets the displayed post.
		RequestSimulator::new_request();
		$html = do_shortcode('[rentiva_contact email_to="' . self::CUSTOM . '"' . $atts . ']');
		$this->assertStringNotContainsString(self::CUSTOM, $html, 'The address is never printed in the page source.');
		$this->assertSame(1, preg_match('/name="recipient_token" value="([^"]+)"/', $html, $m), 'A recipient token is rendered when email_to is set.');
		return html_entity_decode($m[1], ENT_QUOTES);
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
				'name'    => 'Ada Visitor',
				'email'   => 'ada@example.com',
				'message' => 'Hello there',
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

	private function notification_to(): array
	{
		$this->assertNotEmpty($this->mails, 'The notification mail was sent.');
		return (array) $this->mails[0]['to'];
	}

	private function assert_fell_back(string $reason): void
	{
		$this->assertContains($reason, $this->reasons, 'The fallback hook fired with its reason.');
		$this->assert_log_matches_debug_log($reason);
	}

	public function test_author_with_edit_others_posts_gets_custom_recipient(): void
	{
		$token    = $this->token_from_render($this->page_by($this->editor));
		$response = $this->submit(array( 'recipient_token' => $token ));

		$this->assertTrue($response['success']);
		$this->assertSame(array( self::CUSTOM ), $this->notification_to());
		$this->assertSame(array(), $this->reasons, 'No fallback on the happy path.');
	}

	public function test_plain_email_to_post_is_ignored(): void
	{
		$response = $this->submit(
			array(
				'email_to'        => 'attacker@evil.example',
				'recipient_email' => 'attacker@evil.example',
				'recipient_token' => 'attacker@evil.example',
			)
		);

		$this->assertTrue($response['success'], 'The message itself still goes through.');
		$this->assertSame(array( get_option('admin_email') ), $this->notification_to());
		$this->assert_fell_back('invalid_base64');
	}

	public function test_tampered_payload_falls_back(): void
	{
		$token = RecipientToken::issue(self::CUSTOM, $this->page_by($this->editor), 'general');
		$raw   = base64_decode($token, true);
		$this->assertIsString($raw);
		$raw[ strlen($raw) - 1 ] = chr(ord($raw[ strlen($raw) - 1 ]) ^ 0x01);

		$this->assertNull(RecipientToken::resolve(base64_encode($raw), 'general'));
		$this->assert_fell_back('auth_failed');

		$response = $this->submit(array( 'recipient_token' => base64_encode($raw) ));
		$this->assertTrue($response['success']);
		$this->assertSame(array( get_option('admin_email') ), $this->notification_to());
	}

	public function test_short_or_invalid_base64_falls_back_without_exception(): void
	{
		$this->assertNull(RecipientToken::resolve('%%%not base64%%%', 'general'));
		$this->assertContains('invalid_base64', $this->reasons);

		$this->assertNull(RecipientToken::resolve(base64_encode('short'), 'general'));
		$this->assertContains('too_short', $this->reasons);

		$this->assertNull(RecipientToken::resolve(base64_encode(str_repeat("\0", SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES)), 'general'));
		$this->assertContains('auth_failed', $this->reasons);

		$this->assertNull(RecipientToken::resolve('', 'general'));

		// The message is still delivered: the exception is swallowed, never turned into an error response.
		$response = $this->submit(array( 'recipient_token' => base64_encode('short') ));
		$this->assertTrue($response['success'], 'AJAX response: ' . $this->_last_response);
		$this->assertSame(array( get_option('admin_email') ), $this->notification_to());
		$this->assert_fell_back('too_short');
	}

	public function test_demoted_author_falls_back_with_hook(): void
	{
		$token = $this->token_from_render($this->page_by($this->editor));
		( new \WP_User($this->editor) )->set_role('author');

		$response = $this->submit(array( 'recipient_token' => $token ));
		$this->assertTrue($response['success']);
		$this->assertSame(array( get_option('admin_email') ), $this->notification_to());
		$this->assert_fell_back('author_not_allowed');
	}

	public function test_deleted_post_falls_back(): void
	{
		$page  = $this->page_by($this->editor);
		$token = RecipientToken::issue(self::CUSTOM, $page, 'general');
		$this->assertSame(self::CUSTOM, RecipientToken::resolve($token, 'general'));

		wp_trash_post($page);
		$this->assertNull(RecipientToken::resolve($token, 'general'), 'A trashed post no longer vouches for the address.');

		wp_delete_post($page, true);
		$this->assertNull(RecipientToken::resolve($token, 'general'));
		$this->assert_fell_back('missing_post');

		// post_id 0 must not fall through to get_post()'s "current global post".
		$GLOBALS['post'] = get_post($this->page_by($this->editor)); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test sets the displayed post.
		$this->assertNull(RecipientToken::resolve(RecipientToken::issue(self::CUSTOM, 0, 'general'), 'general'));
	}

	public function test_type_mismatch_falls_back(): void
	{
		$page    = $this->page_by($this->editor);
		$booking = RecipientToken::issue(self::CUSTOM, $page, 'booking');

		$this->assertSame(self::CUSTOM, RecipientToken::resolve($booking, 'booking'));
		$this->assertNull(RecipientToken::resolve($booking, 'general'), 'A booking form token does not carry a general form.');
		$this->assert_fell_back('type_mismatch');

		$this->reasons = array();
		$this->assertNull(RecipientToken::resolve(RecipientToken::issue(self::CUSTOM, $page, 'evil'), 'evil'));
		$this->assertContains('type_mismatch', $this->reasons, 'A type outside ContactMessagePostType::TYPES is refused.');

		// End to end: the token rendered for a booking form, posted as a general form.
		$this->reasons = array();
		$token         = $this->token_from_render($page, ' type="booking"');
		$this->submit(array( 'recipient_token' => $token ));
		$this->assertSame(array( get_option('admin_email') ), $this->notification_to());
		$this->assert_fell_back('type_mismatch');
	}

	/**
	 * Review Focus 5: a page cached two days ago still carries a working payload.
	 *
	 * SiteClock moves the site's clock (gmt_offset), not PHP's time(); the real
	 * guarantee is structural and asserted as such: the payload holds exactly
	 * {email, post_id, type} and RecipientToken reads no clock at all.
	 */
	public function test_recipient_payload_has_no_expiry(): void
	{
		$page  = $this->page_by($this->editor);
		$token = RecipientToken::issue(self::CUSTOM, $page, 'general');

		$raw   = (string) base64_decode($token, true);
		$plain = sodium_crypto_secretbox_open(
			substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
			substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
			hash('sha256', wp_salt('auth'), true)
		);
		$this->assertIsString($plain);
		$payload = json_decode($plain, true);
		$this->assertSame(array( 'email', 'post_id', 'type' ), array_keys($payload), 'No issue time, no expiry.');

		$src = (string) file_get_contents(MHMRENTIVA_PLUGIN_DIR . 'src/Admin/Frontend/Shortcodes/Contact/RecipientToken.php'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source.
		$this->assertDoesNotMatchRegularExpression('/\b(time|microtime|current_time|wp_date|date|gmdate|strtotime)\s*\(/', $src, 'The resolver reads no clock.');

		$this->pin_site_hour(((int) gmdate('G') + 12) % 24);
		add_filter('pre_option_gmt_offset', static fn() => 48, 20);
		$this->assertSame(self::CUSTOM, RecipientToken::resolve($token, 'general'), 'Still valid with the site clock two days ahead.');
		$this->assertSame(array(), $this->reasons);
	}

	public function test_widget_sidebar_shortcode_uses_global_recipient(): void
	{
		// A shortcode/block in a sidebar resolves to the displayed post: here a page by an
		// author without edit_others_posts, so the address is never used (safe side).
		$token = $this->token_from_render($this->page_by($this->author));
		$this->submit(array( 'recipient_token' => $token ));
		$this->assertSame(array( get_option('admin_email') ), $this->notification_to());
		$this->assert_fell_back('author_not_allowed');

		// No displayed post at all (a search page): no source post, global recipient.
		$this->mails   = array();
		$this->reasons = array();
		$token         = $this->token_from_render(0);
		$this->submit(array( 'recipient_token' => $token ));
		$this->assertSame(array( get_option('admin_email') ), $this->notification_to());
		$this->assert_fell_back('missing_post');
	}

	/**
	 * Spec §2.5 (c): removing `email_to` from the source post revokes the payload a
	 * cached page still carries (final audit, Important 2).
	 */
	public function test_removed_email_to_revokes_token(): void
	{
		// Shortcode text.
		$page  = $this->page_by($this->editor);
		$token = $this->token_from_render($page);
		$this->assertSame(self::CUSTOM, RecipientToken::resolve($token, 'general'));

		wp_update_post(array( 'ID' => $page, 'post_content' => '[rentiva_contact]' ));
		$this->assertNull(RecipientToken::resolve($token, 'general'), 'email_to removed from the shortcode.');
		$this->assert_fell_back('recipient_removed');

		$response = $this->submit(array( 'recipient_token' => $token ));
		$this->assertTrue($response['success']);
		$this->assertSame(array( get_option('admin_email') ), $this->notification_to());

		// Block markup: the address sits JSON-encoded in the block comment.
		$this->reasons = array();
		$page          = $this->page_by($this->editor, self::contact_block(self::CUSTOM));
		$token         = RecipientToken::issue(self::CUSTOM, $page, 'general');
		$this->assertSame(self::CUSTOM, RecipientToken::resolve($token, 'general'), 'The block attribute vouches for the address.');

		wp_update_post(array( 'ID' => $page, 'post_content' => '<!-- wp:mhm-rentiva/contact /-->' ));
		$this->assertNull(RecipientToken::resolve($token, 'general'), 'emailTo removed from the block.');
		$this->assert_fell_back('recipient_removed');

		// Another address on the page does not vouch for this one.
		$this->reasons = array();
		wp_update_post(array( 'ID' => $page, 'post_content' => '[rentiva_contact email_to="other-desk@example.com"]' ));
		$this->assertNull(RecipientToken::resolve($token, 'general'));
		$this->assert_fell_back('recipient_removed');
	}

	/**
	 * An address with a character the block comment escapes (& as \u0026) is still
	 * found on the source post: saved by a user with unfiltered_html (stored as
	 * \u0026) and without it (kses runs over block attributes: \u0026amp;).
	 */
	public function test_block_encoded_address_still_vouches(): void
	{
		$email = 'sales&desk@example.com';
		$this->assertNotFalse(is_email($email));
		$block = self::contact_block($email);
		$this->assertStringNotContainsString($email, $block, 'Precondition: the block comment escapes the address.');

		$page = $this->page_by($this->editor, $block);
		$this->assertStringContainsString('\u0026amp;', get_post($page)->post_content, 'Precondition: saved through kses.');
		$this->assertSame($email, RecipientToken::resolve(RecipientToken::issue($email, $page, 'general'), 'general'));

		// kses is installed on set_current_user from the *current user's* unfiltered_html
		// (not the post author's); multisite needs super admin for it, so grant it first.
		$admin = (int) self::factory()->user->create(array( 'role' => 'administrator' ));
		if (is_multisite()) {
			grant_super_admin($admin);
		}
		wp_set_current_user($admin);
		$page = $this->page_by($this->editor, $block);
		$this->assertStringContainsString('sales\u0026desk', get_post($page)->post_content, 'Precondition: saved unfiltered.');
		$this->assertSame($email, RecipientToken::resolve(RecipientToken::issue($email, $page, 'general'), 'general'));
		$this->assertSame(array(), $this->reasons);
	}

	/**
	 * An Elementor document keeps its widgets in `_elementor_data` (JSON), not in
	 * post_content: that is where the address has to remain.
	 */
	public function test_removed_email_to_revokes_elementor_document_token(): void
	{
		$page = $this->page_by($this->editor, '');
		$data = static function (array $settings): string {
			return (string) wp_json_encode(
				array(
					array(
						'id'         => 'a1b2c3d',
						'elType'     => 'widget',
						'widgetType' => 'rv-contact-form',
						'settings'   => $settings,
						'elements'   => array(),
					),
				)
			);
		};
		update_post_meta($page, '_elementor_edit_mode', 'builder');
		update_post_meta($page, '_elementor_data', wp_slash($data(array( 'email_to' => self::CUSTOM ))));

		$token = RecipientToken::issue(self::CUSTOM, $page, 'general');
		$this->assertSame(self::CUSTOM, RecipientToken::resolve($token, 'general'), 'The Elementor widget setting vouches for the address.');
		$this->assertSame(array(), $this->reasons);

		update_post_meta($page, '_elementor_data', wp_slash($data(array())));
		$this->assertNull(RecipientToken::resolve($token, 'general'), 'email_to removed from the Elementor widget.');
		$this->assert_fell_back('recipient_removed');
	}

	/**
	 * Hygiene (final audit, Minor 3): anyone holding the public AJAX nonce can post
	 * refused payloads; without WP_DEBUG_LOG they must not reach the PHP log. The
	 * hook still fires every time.
	 */
	public function test_refused_payload_logs_only_with_debug_log(): void
	{
		for ($i = 0; $i < 3; $i++) {
			$this->assertNull(RecipientToken::resolve('%%%not base64%%%', 'general'));
		}
		$this->assertSame(array( 'invalid_base64', 'invalid_base64', 'invalid_base64' ), $this->reasons);
		$this->assert_log_matches_debug_log('invalid_base64');
	}

	public function test_elementor_document_is_the_source_post(): void
	{
		if (! class_exists('\Elementor\Plugin') || ! isset(\Elementor\Plugin::$instance->documents)) {
			$this->markTestSkipped('Elementor is not loaded.');
		}
		$template = $this->page_by($this->editor);
		$shown    = $this->page_by($this->author);
		$GLOBALS['post'] = get_post($shown); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test sets the displayed post.

		$documents = \Elementor\Plugin::$instance->documents;
		$document  = $documents->get($template);
		$this->assertNotEmpty($document);
		$documents->switch_to_document($document);
		try {
			$this->assertSame($template, ContactForm::resolve_source_post_id(), 'Inside an Elementor document, the document is the source post.');
		} finally {
			$documents->restore_document();
		}
		$this->assertSame($shown, ContactForm::resolve_source_post_id(), 'Outside it, the displayed post.');
	}
}
