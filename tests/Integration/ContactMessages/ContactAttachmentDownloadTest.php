<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\ContactMessages;

use MHMRentiva\Admin\ContactMessages\ContactAttachmentDownload;
use MHMRentiva\Admin\ContactMessages\ContactAttachmentStore;
use MHMRentiva\Tests\Support\ContactAttachmentFixtures;
use MHMRentiva\Tests\Support\SandboxesUploads;
use WP_UnitTestCase;

final class ContactAttachmentDownloadTest extends WP_UnitTestCase
{
	use ContactAttachmentFixtures;
	use SandboxesUploads;

	private int $admin = 0;
	private int $id    = 0;

	public function setUp(): void
	{
		parent::setUp();
		$this->sandbox_uploads();
		$this->admin = (int) self::factory()->user->create(array( 'role' => 'administrator' ));
		$record      = ContactAttachmentStore::store_upload($this->fixture($this->sandbox . '/in', 'pdf'), 'offer.pdf', $this->sideload());
		$this->id    = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_contact', 'post_status' => 'private' ));
		ContactAttachmentStore::attach($this->id, $record);
	}

	public function tearDown(): void
	{
		unset($_GET['id'], $_REQUEST['id'], $_REQUEST['_wpnonce']);
		wp_set_current_user(0);
		parent::tearDown();
		$this->remove_sandbox();
	}

	private function request(int $id, string $nonce): void
	{
		$_GET['id']           = (string) $id;
		$_REQUEST['id']       = (string) $id;
		$_REQUEST['_wpnonce'] = $nonce;
	}

	private static bool $shutdownGuardRegistered = false;
	private static bool $expectingDeath           = false;

	/**
	 * handle() always ends in exit -- wp_die() throws WPDieException in tests,
	 * but a guard that fails to die falls through to the real exit at the end
	 * of handle(). exit() has status 0 and PHPUnit cannot catch it, so a run
	 * that hits that path stops mid-suite with no failure line and exit code
	 * 0 -- a broken guard would read as green. PHP still runs registered
	 * shutdown functions on exit(), and exit() called from inside one of them
	 * is what sets the process's final exit status, so a shutdown function
	 * that finds $expectingDeath still true (the finally below never ran)
	 * turns that silent exit(0) into a reported exit(1).
	 */
	private function call_handle_expecting_death(): void
	{
		if (! self::$shutdownGuardRegistered) {
			self::$shutdownGuardRegistered = true;
			register_shutdown_function(static function (): void {
				if (self::$expectingDeath) {
					fwrite(STDERR, "ContactAttachmentDownload::handle() reached exit inside a test: a guard that should have died did not\n");
					exit(1);
				}
			});
		}

		self::$expectingDeath = true;
		try {
			ContactAttachmentDownload::handle();
		} finally {
			self::$expectingDeath = false;
		}
	}

	public function test_a_request_without_a_valid_nonce_dies(): void
	{
		wp_set_current_user($this->admin);
		$this->request($this->id, 'nope');
		$this->expectException(\WPDieException::class);
		$this->call_handle_expecting_death();
	}

	public function test_a_user_without_manage_options_dies_unauthorized(): void
	{
		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'editor' )));
		$this->request($this->id, wp_create_nonce(ContactAttachmentDownload::nonce_action($this->id)));
		$this->expectException(\WPDieException::class);
		$this->expectExceptionMessage('Unauthorized.');
		$this->call_handle_expecting_death();
	}

	public function test_another_post_types_id_dies_not_found(): void
	{
		wp_set_current_user($this->admin);
		$booking = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking' ));
		ContactAttachmentStore::attach($booking, ContactAttachmentStore::record($this->id));
		$this->request($booking, wp_create_nonce(ContactAttachmentDownload::nonce_action($booking)));
		$this->expectException(\WPDieException::class);
		$this->expectExceptionMessage('File not available');
		$this->call_handle_expecting_death();
	}

	public function test_a_nonce_for_one_message_does_not_open_another(): void
	{
		wp_set_current_user($this->admin);
		$other = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_contact', 'post_status' => 'private' ));
		// $other carries a real, resolvable record: if nonce_action() ignored
		// $id the request would clear the nonce and capability checks and die
		// later at "File not available" instead -- this attachment makes that
		// failure mode reach resolve()/serve() so the test still catches it.
		ContactAttachmentStore::attach($other, ContactAttachmentStore::record($this->id));
		$this->request($other, wp_create_nonce(ContactAttachmentDownload::nonce_action($this->id)));
		$this->expectException(\WPDieException::class);
		// check_admin_referer()'s failure text (wp_nonce_ays(), non-log-out
		// action), measured against this container's WordPress -- not the
		// later-stage "File not available"/"Unauthorized." messages.
		$this->expectExceptionMessage('The link you followed has expired.');
		$this->call_handle_expecting_death();
	}

	public function test_serve_sends_the_verified_type_attachment_disposition_and_no_cache(): void
	{
		$headers = array();
		$file    = ContactAttachmentDownload::resolve($this->id);
		ob_start();
		ContactAttachmentDownload::serve($file, static function (string $h) use (&$headers): void {
			$headers[] = $h;
		}, true);
		$body = (string) ob_get_clean();

		$this->assertStringStartsWith('%PDF-', $body);
		$this->assertContains('Content-Type: application/pdf', $headers);
		$this->assertContains('X-Content-Type-Options: nosniff', $headers);
		$this->assertContains('Cache-Control: no-store, private', $headers);
		$this->assertContains('Content-Length: ' . strlen($body), $headers);
		$this->assertStringStartsWith('Content-Disposition: attachment; ', implode("\n", preg_grep('/^Content-Disposition/', $headers)));
	}

	public function test_serve_omits_content_length_when_output_is_compressed(): void
	{
		$headers = array();
		ob_start();
		ContactAttachmentDownload::serve(ContactAttachmentDownload::resolve($this->id), static function (string $h) use (&$headers): void {
			$headers[] = $h;
		}, false);
		ob_end_clean();
		$this->assertSame(array(), preg_grep('/^Content-Length/', $headers));
	}

	/**
	 * M1: the extracted seam behaves like the inline loop it replaced when
	 * every buffer is removable -- it is unconditional, so it also drains
	 * the buffer PHPUnit itself opened around this test (TestCase::runBare()
	 * -> startOutputBuffering()), which is the correct production behavior
	 * (handle() must clear everything ahead of it, not just its own). The
	 * final loop restores that level so PHPUnit's own end-of-test bookkeeping
	 * (stopOutputBuffering()) still finds a buffer to close.
	 */
	public function test_drain_output_buffers_removes_every_removable_buffer_and_allows_content_length(): void
	{
		$level_before = ob_get_level();
		ob_start();
		echo 'stray-a';
		ob_start();
		echo 'stray-b';

		$can_send_length = ContactAttachmentDownload::drain_output_buffers();

		$this->assertTrue($can_send_length);
		$this->assertSame(0, ob_get_level(), 'every removable buffer, including the two this test opened, must be gone');

		while (ob_get_level() < $level_before) {
			ob_start();
		}
	}

	/**
	 * M1: a buffer opened without PHP_OUTPUT_HANDLER_REMOVABLE (the zlib
	 * output-compression handler is one; so is a plugin's own ob_start() that
	 * omits the flag) cannot be popped by ob_end_clean() for the rest of the
	 * PHP process -- measured against this exact PHP build below, mirroring
	 * the audit's own php -r probe.
	 *
	 * Run out of process, never inline in this test: PHPUnit wraps every
	 * test in its own ob_start()/ob_end_clean() pair
	 * (TestCase::stopOutputBuffering()), and that pair's own cleanup loop
	 * (`while (ob_get_level() >= $level) { ob_end_clean(); }`) would spin
	 * forever against a level it can never remove -- hanging the whole
	 * suite, not just failing one test. A bare `php -r` child process pays
	 * for that irreversibility itself and simply exits.
	 */
	public function test_drain_output_buffers_clears_bytes_from_a_buffer_it_cannot_remove(): void
	{
		$source = dirname(__DIR__, 3) . '/src/Admin/ContactMessages/ContactAttachmentDownload.php';
		$this->assertFileExists($source);

		$script = 'define("ABSPATH", __DIR__ . "/"); require ' . var_export($source, true) . ';'
			. 'ob_start(null, 0, PHP_OUTPUT_HANDLER_STDFLAGS ^ PHP_OUTPUT_HANDLER_REMOVABLE);'
			. 'echo "P";'
			. '$level = ob_get_level();'
			. '$ok = \\MHMRentiva\\Admin\\ContactMessages\\ContactAttachmentDownload::drain_output_buffers();'
			. 'fwrite(STDOUT, json_encode(array('
			. '"level_before_drain" => $level,'
			. '"can_send_length" => $ok,'
			. '"level_after_drain" => ob_get_level(),'
			. '"surviving_body" => ob_get_contents(),'
			. ')));';

		$process = proc_open(
			array( PHP_BINARY, '-r', $script ),
			array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ),
			$pipes
		);
		$this->assertIsResource($process, 'could not spawn the isolated probe process');
		fclose($pipes[0]);
		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$exit_code = proc_close($process);

		$this->assertSame(0, $exit_code, "the isolated probe process failed: {$stderr}");
		$result = json_decode( (string) $stdout, true);
		$this->assertIsArray($result, "unexpected probe output: {$stdout}");
		$this->assertSame(1, $result['level_before_drain'], 'the probe must have exactly one buffer open before draining');
		$this->assertFalse($result['can_send_length'], 'a surviving buffer means Content-Length must not be sent');
		$this->assertSame(1, $result['level_after_drain'], 'the non-removable buffer itself is still there -- it cannot be removed, only cleared');
		$this->assertSame('', $result['surviving_body'], 'the stray byte must be discarded from the surviving buffer');
	}

	/** Review Focus 3. */
	public function test_disposition_keeps_quotes_out_and_the_real_name_in_filename_star(): void
	{
		$d = ContactAttachmentDownload::disposition('teklif "son" şartname.pdf');
		$this->assertMatchesRegularExpression('/^attachment; filename="[A-Za-z0-9._-]+"; filename\*=UTF-8\'\'/', $d);
		$this->assertStringContainsString("filename*=UTF-8''" . rawurlencode('teklif "son" şartname.pdf'), $d);
		$this->assertStringContainsString('.pdf"', $d);

		// A CR/LF in the name must not reach the header raw (response splitting).
		$injected = ContactAttachmentDownload::disposition("evil\r\nX-Injected: 1.pdf");
		$this->assertStringNotContainsString("\r", $injected);
		$this->assertStringNotContainsString("\n", $injected);
	}

	/** End to end: the upload path sanitizes the name first. */
	public function test_an_uploaded_turkish_name_reaches_the_header_sanitized(): void
	{
		$record = ContactAttachmentStore::store_upload($this->fixture($this->sandbox . '/in', 'pdf'), 'Teklif "son" şartname.pdf', $this->sideload());
		$id     = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_contact', 'post_status' => 'private' ));
		ContactAttachmentStore::attach($id, $record);
		$name = ContactAttachmentDownload::resolve($id)['name'];
		$this->assertSame(sanitize_file_name('Teklif "son" şartname.pdf'), $name);
		$this->assertStringContainsString("filename*=UTF-8''" . rawurlencode($name), ContactAttachmentDownload::disposition($name));
		$this->assertStringNotContainsString('"son"', ContactAttachmentDownload::disposition($name));
	}

	/** Review Focus 5. */
	public function test_a_tampered_record_is_not_served(): void
	{
		update_post_meta($this->id, ContactAttachmentStore::META_KEY, array( 'file' => '../../wp-config', 'name' => 'a.pdf', 'mime' => 'application/pdf', 'size' => 1 ));
		$this->assertNull(ContactAttachmentDownload::resolve($this->id));
	}

	/** R-10: must be usable as an href verbatim -- no &amp;. */
	public function test_download_url_is_a_raw_admin_post_url_with_a_record_bound_nonce(): void
	{
		wp_set_current_user($this->admin);
		$url = ContactAttachmentStore::download_url($this->id);
		$this->assertStringNotContainsString('&amp;', $url);
		parse_str( (string) wp_parse_url($url, PHP_URL_QUERY), $q);
		$this->assertSame(ContactAttachmentDownload::ACTION, $q['action']);
		$this->assertSame( (string) $this->id, $q['id']);
		$this->assertSame(1, wp_verify_nonce($q['_wpnonce'], ContactAttachmentDownload::nonce_action($this->id)));
	}

	public function test_the_action_is_registered_for_logged_in_users_only(): void
	{
		$this->assertNotFalse(has_action('admin_post_' . ContactAttachmentDownload::ACTION));
		$this->assertFalse(has_action('admin_post_nopriv_' . ContactAttachmentDownload::ACTION));
	}
}
