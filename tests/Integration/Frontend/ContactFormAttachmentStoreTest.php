<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Frontend;

use MHMRentiva\Admin\ContactMessages\ContactAttachmentStore;
use MHMRentiva\Admin\Frontend\Shortcodes\ContactForm;
use MHMRentiva\Tests\Support\ContactAttachmentFixtures;
use MHMRentiva\Tests\Support\SandboxesUploads;
use WP_UnitTestCase;

/**
 * `ContactForm::process_submission()` -- validate first, store the file only
 * after the form itself passed, e-mail it under the visitor's own name, and
 * never leave an orphaned file behind when the record cannot be saved (R-4).
 */
final class ContactFormAttachmentStoreTest extends WP_UnitTestCase
{
	use ContactAttachmentFixtures;
	use SandboxesUploads;

	protected function setUp(): void
	{
		parent::setUp();
		$this->sandbox_uploads();
		reset_phpmailer_instance();
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		$this->remove_sandbox();
	}

	private function submit(array $post, ?string $kind = null, string $name = 'offer.pdf'): array
	{
		$file = null;
		if (null !== $kind) {
			$tmp  = $this->fixture($this->sandbox . '/in', $kind);
			$file = array( 'name' => $name, 'type' => 'application/pdf', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => (int) filesize($tmp) );
		}
		$m = new \ReflectionMethod(ContactForm::class, 'process_submission');
		$m->setAccessible(true);
		return $m->invoke(null, array_merge(array( 'type' => 'general', 'name' => 'Ada', 'email' => 'ada@example.com', 'message' => 'Hi', 'auto_reply' => '0' ), $post), $file, $this->sideload());
	}

	private function stored_files(): array
	{
		$root = ContactAttachmentStore::root();
		return array_values(preg_grep(ContactAttachmentStore::NAME_PATTERN, (array) scandir((string) $root)));
	}

	public function test_an_attachment_is_stored_privately_and_recorded(): void
	{
		$r  = $this->submit(array(), 'pdf', 'Teklif.pdf');
		$this->assertTrue($r['ok']);
		$record = ContactAttachmentStore::record($r['message_id']);
		$this->assertSame('Teklif.pdf', $record['name']);
		$this->assertSame($record['file'], get_post_meta($r['message_id'], ContactAttachmentStore::FILE_META, true));
		$this->assertNotNull(ContactAttachmentStore::path($record));
	}

	/** Review Focus 1 (R-4). */
	public function test_a_submission_failing_validation_stores_no_file(): void
	{
		$r = $this->submit(array( 'name' => '' ), 'pdf');
		$this->assertFalse($r['ok']);
		$this->assertSame(array(), $this->stored_files());
	}

	public function test_a_failed_save_discards_the_stored_file(): void
	{
		add_filter('wp_insert_post_empty_content', '__return_true');
		try {
			$this->submit(array(), 'pdf');
			$this->fail('save_contact_message() must throw when the insert fails');
		} catch (\Exception $e) {
			$this->assertSame(array(), $this->stored_files());
		}
	}

	public function test_refused_content_is_reported_and_nothing_is_saved(): void
	{
		$before = (int) wp_count_posts('mhmrentiva_contact')->private;
		$r      = $this->submit(array(), 'zip', 'x.pdf');
		$this->assertSame(array( 'ok' => false, 'message' => 'Invalid file type.' ), $r);
		$this->assertSame($before, (int) wp_count_posts('mhmrentiva_contact')->private);
	}

	/**
	 * PR #77 bot comment: ContactForm's own pre-upload size check only sees
	 * the tmp file BEFORE the move; a prefilter can enlarge it afterwards.
	 * handle_file_upload() must pass its cap through to store_upload() so the
	 * stored file is checked too.
	 */
	public function test_a_prefilter_enlarging_the_file_past_the_cap_is_refused(): void
	{
		add_filter('mhmrentiva_contact_attachment_max_bytes', static fn() => 200);
		$big    = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n" . str_repeat('X', 300) . "\n%%EOF\n";
		$filter = static function (array $file) use ($big): array {
			file_put_contents($file['tmp_name'], $big);
			return $file;
		};
		add_filter('wp_handle_sideload_prefilter', $filter);
		try {
			$r = $this->submit(array(), 'pdf', 'a.pdf');
		} finally {
			remove_filter('wp_handle_sideload_prefilter', $filter);
			remove_all_filters('mhmrentiva_contact_attachment_max_bytes');
		}

		$this->assertFalse($r['ok']);
		// Caught inside store_upload() after the move, not by handle_file_upload()'s
		// own pre-upload size check (that one already passed, on the small fixture).
		$this->assertSame('The file could not be uploaded.', $r['message']);
		$this->assertSame(array(), $this->stored_files());
	}

	public function test_the_admin_email_attaches_the_file_under_its_original_name_and_carries_no_link(): void
	{
		$r    = $this->submit(array(), 'pdf', 'Teklif şartname.pdf');
		$mail = tests_retrieve_phpmailer_instance();
		$att  = $mail->getAttachments();
		$this->assertCount(1, $att);
		$this->assertSame(sanitize_file_name('Teklif şartname.pdf'), $att[0][2]); // PHPMailer: [0] path, [2] name
		$this->assertStringNotContainsString('Download File', $mail->get_sent()->body);
		$this->assertStringNotContainsString('/uploads/', $mail->get_sent()->body);
	}

	public function test_a_submission_without_a_file_writes_no_token_meta(): void
	{
		$r = $this->submit(array());
		$this->assertTrue($r['ok']);
		$this->assertSame('', get_post_meta($r['message_id'], ContactAttachmentStore::FILE_META, true));
		$this->assertNull(ContactAttachmentStore::record($r['message_id']));
	}

	/**
	 * Spec §9 Dilim 2 grep gate, as a test so CI keeps it. The form itself
	 * delegates every file write to ContactAttachmentStore -- that class is
	 * the only caller of wp_handle_upload() (spec §4, Plugin Check forbids
	 * move_uploaded_file(), user decision 2026-09-28).
	 */
	public function test_the_contact_form_never_calls_wp_handle_upload(): void
	{
		$src = (string) file_get_contents(dirname(__DIR__, 3) . '/src/Admin/Frontend/Shortcodes/ContactForm.php');
		$this->assertStringNotContainsString('wp_handle_upload', $src);
	}
}
