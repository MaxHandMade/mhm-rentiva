<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\ContactMessages;

use MHMRentiva\Admin\ContactMessages\ContactAttachmentStore;
use MHMRentiva\Tests\Support\ContactAttachmentFixtures;
use MHMRentiva\Tests\Support\SandboxesUploads;
use WP_UnitTestCase;

final class ContactAttachmentStoreTest extends WP_UnitTestCase
{
	use ContactAttachmentFixtures;
	use SandboxesUploads;

	private string $in = '';

	protected function setUp(): void
	{
		parent::setUp();
		$this->in = $this->sandbox_uploads() . '/in';
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		$this->remove_sandbox();
	}

	private function upload(string $kind, string $name): array
	{
		$r = ContactAttachmentStore::store_upload($this->fixture($this->in, $kind), $name, $this->sideload());
		$this->assertIsArray($r, is_wp_error($r) ? $r->get_error_message() : '');
		return $r;
	}

	private function contact_with(array $record): int
	{
		$id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_contact', 'post_status' => 'private' ));
		ContactAttachmentStore::attach($id, $record);
		return $id;
	}

	public function test_default_root_is_the_contact_folder_under_the_private_parent(): void
	{
		$base = $this->sandbox; // set by sandbox_uploads() in setUp
		remove_all_filters(ContactAttachmentStore::ROOT_FILTER);
		$this->assertSame($base . '/mhm-rentiva-private/contact', wp_normalize_path(ContactAttachmentStore::default_root()));
	}

	public function test_root_writes_guards_and_never_rewrites_the_parents(): void
	{
		$base = $this->sandbox;
		remove_all_filters(ContactAttachmentStore::ROOT_FILTER);
		wp_mkdir_p($base . '/mhm-rentiva-private');
		file_put_contents($base . '/mhm-rentiva-private/.htaccess', 'OWNED BY THE ADD-ON');

		$root = ContactAttachmentStore::root();

		$this->assertIsString($root);
		$this->assertSame('OWNED BY THE ADD-ON', file_get_contents($base . '/mhm-rentiva-private/.htaccess'));
		$this->assertFileExists($base . '/mhm-rentiva-private/index.php');
		$ht = (string) file_get_contents($root . '/.htaccess');
		$this->assertStringContainsString('<IfModule mod_authz_core.c>', $ht);
		$this->assertStringContainsString('Require all denied', $ht);
		$this->assertStringContainsString('<IfModule !mod_authz_core.c>', $ht);
		$this->assertStringContainsString('ForceType application/octet-stream', $ht);
		$this->assertStringContainsString('Header set X-Content-Type-Options nosniff', $ht);
		$this->assertFileExists($root . '/index.php');
	}

	public function test_an_upload_lands_under_a_random_extensionless_name(): void
	{
		$r = $this->upload('pdf', 'Teklif şartname.pdf');
		$this->assertMatchesRegularExpression(ContactAttachmentStore::NAME_PATTERN, $r['file']);
		$this->assertSame('application/pdf', $r['mime']);
		$this->assertSame(sanitize_file_name('Teklif şartname.pdf'), $r['name']);
		$this->assertFileExists(ContactAttachmentStore::path($r));
	}

	/**
	 * (a) wp_handle_upload() defaults to a dated uploads/YYYY/MM subfolder;
	 * the upload_dir redirect (spec) must land the file directly in the
	 * private root instead, still under the bare 32-hex token.
	 *
	 * The private root itself is never a subdirectory of $this->sandbox
	 * (O3, Opus Minor 5): bootstrap.php pins mhmrentiva_contact_attachment_root
	 * to its own fixed temp directory for the whole run, and
	 * SandboxesUploads::sandbox_uploads() only redirects wp_upload_dir()'s
	 * basedir -- root() does not consult that once the ROOT_FILTER above it
	 * is registered. So nothing but this test's own input fixture ('in')
	 * should ever appear under the sandbox: no dated uploads/YYYY/MM
	 * subfolder core's default naming would have created before the
	 * redirect took effect, and no 'mhm-rentiva-private' tree either, since
	 * that tree lives entirely outside the sandbox.
	 */
	public function test_the_stored_file_is_the_bare_token_directly_in_the_private_root(): void
	{
		$r    = $this->upload('pdf', 'a.pdf');
		$root = (string) ContactAttachmentStore::root();

		$this->assertSame($root . '/' . $r['file'], ContactAttachmentStore::path($r));
		$this->assertStringNotContainsString('.', $r['file'], 'the stored name must carry no extension');

		$leftover = array_diff( (array) scandir($this->sandbox), array( '.', '..', 'in' ));
		$this->assertSame(array(), array_values($leftover));
	}

	/**
	 * (b) The upload_dir filter this class adds for the one call must not
	 * leak into the rest of the request -- including when the handler
	 * itself throws.
	 */
	public function test_the_upload_dir_filter_is_removed_after_success_and_after_the_handler_throws(): void
	{
		$before = wp_upload_dir();
		$this->upload('pdf', 'a.pdf');
		$after = wp_upload_dir();
		$this->assertSame($before['basedir'], $after['basedir']);
		$this->assertSame($before['path'], $after['path']);
		$this->assertSame($before['subdir'], $after['subdir']);

		try {
			ContactAttachmentStore::store_upload($this->fixture($this->in, 'pdf'), 'a.pdf', static function (): array {
				throw new \RuntimeException('handler exploded');
			});
			$this->fail('the handler exception must propagate, not be swallowed');
		} catch (\RuntimeException $e) {
			$this->assertSame('handler exploded', $e->getMessage());
		}

		$again = wp_upload_dir();
		$this->assertSame($before['basedir'], $again['basedir']);
		$this->assertSame($before['path'], $again['path']);
		$this->assertSame($before['subdir'], $again['subdir']);
	}

	/**
	 * (c) A handler is trusted to report where it put the file; this class
	 * must still refuse a report pointing outside the root, and must never
	 * delete a file it does not own just because a handler named it.
	 */
	public function test_a_handler_reporting_a_file_outside_the_root_is_refused_and_nothing_is_touched(): void
	{
		$root      = (string) ContactAttachmentStore::root();
		$before    = scandir($root);
		$elsewhere = $this->sandbox . '/elsewhere.bin';
		file_put_contents($elsewhere, 'not yours');

		$r = ContactAttachmentStore::store_upload(
			$this->fixture($this->in, 'pdf'),
			'a.pdf',
			static fn(array $f, array $o): array => array(
				'file' => $elsewhere,
				'url'  => '',
				'type' => 'application/pdf',
			)
		);

		$this->assertInstanceOf(\WP_Error::class, $r);
		$this->assertSame('The file could not be uploaded.', $r->get_error_message());
		$this->assertSame($before, scandir($root));
		$this->assertFileExists($elsewhere);
	}

	/**
	 * Fable I1: validate() runs on $tmp before core ever sees it, but a
	 * wp_handle_upload_prefilter-shaped hook (wp_handle_sideload_prefilter
	 * for this test seam -- _wp_handle_upload() names that filter after
	 * the $action it was called with, and wp_handle_sideload() passes
	 * 'wp_handle_sideload', confirmed against the mounted core's
	 * file.php:840) can rewrite tmp_name's bytes in place before the move.
	 * The stored file must be re-validated and its recorded size taken
	 * from it, not from the pre-upload figure.
	 */
	public function test_a_prefilter_rewriting_the_bytes_in_place_is_re_validated_and_sized_from_the_stored_file(): void
	{
		$rewritten = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\nEXTRA TRAILING BYTES THAT CHANGE THE LENGTH\n%%EOF\n";
		$filter    = static function (array $file) use ($rewritten): array {
			file_put_contents($file['tmp_name'], $rewritten);
			return $file;
		};
		add_filter('wp_handle_sideload_prefilter', $filter);
		try {
			$r = $this->upload('pdf', 'a.pdf');
		} finally {
			remove_filter('wp_handle_sideload_prefilter', $filter);
		}

		$this->assertSame(strlen($rewritten), $r['size']);
		$this->assertSame(strlen($rewritten), (int) filesize((string) ContactAttachmentStore::path($r)));
	}

	/**
	 * Fable I1, refusal side: a prefilter that rewrites the bytes into
	 * something the plugin's own content check refuses must still leave
	 * the private root exactly as it was.
	 */
	public function test_a_prefilter_rewriting_the_bytes_to_disallowed_content_is_refused_and_the_root_is_unchanged(): void
	{
		$root   = (string) ContactAttachmentStore::root();
		$before = scandir($root);
		$filter = static function (array $file): array {
			file_put_contents($file['tmp_name'], '<?php echo 1;');
			return $file;
		};
		add_filter('wp_handle_sideload_prefilter', $filter);
		try {
			$r = ContactAttachmentStore::store_upload($this->fixture($this->in, 'pdf'), 'a.pdf', $this->sideload());
		} finally {
			remove_filter('wp_handle_sideload_prefilter', $filter);
		}

		$this->assertInstanceOf(\WP_Error::class, $r);
		$this->assertSame($before, scandir($root));
	}

	/**
	 * Fable I2: a wp_handle_upload filter (the one core applies to its own
	 * RETURN value -- always named 'wp_handle_upload' regardless of
	 * whether wp_handle_upload() or wp_handle_sideload() was called,
	 * confirmed against the mounted core's file.php:1073-1081) can report
	 * a path outside the root even though core already moved the real
	 * upload into <root>/<token>. That token file is this request's own
	 * and must always be cleaned up on refusal, whatever the filter claims.
	 */
	public function test_a_wp_handle_upload_filter_redirecting_the_result_still_cleans_up_our_own_token_file(): void
	{
		$root      = (string) ContactAttachmentStore::root();
		$before    = scandir($root);
		$elsewhere = $this->sandbox . '/elsewhere.bin';
		file_put_contents($elsewhere, 'not yours');

		$filter = static fn(array $u): array => array( 'file' => $elsewhere ) + $u;
		add_filter('wp_handle_upload', $filter);
		try {
			$r = ContactAttachmentStore::store_upload($this->fixture($this->in, 'pdf'), 'a.pdf', $this->sideload());
		} finally {
			remove_filter('wp_handle_upload', $filter);
		}

		$this->assertInstanceOf(\WP_Error::class, $r);
		$this->assertSame($before, scandir($root));
		$this->assertFileExists($elsewhere, 'a file this class does not own must never be deleted');
	}

	/**
	 * Opus O1: this class only trusts a file that sits DIRECTLY in the
	 * root -- a handler reporting a subdirectory of the root must be
	 * refused, and the file it left behind removed (only the now-empty
	 * subdirectory itself, an artifact of this synthetic handler, remains).
	 */
	public function test_a_handler_writing_the_token_into_a_subdirectory_of_the_root_is_refused_and_removed(): void
	{
		$root   = (string) ContactAttachmentStore::root();
		$before = scandir($root);
		$dest   = null;

		$r = ContactAttachmentStore::store_upload(
			$this->fixture($this->in, 'pdf'),
			'a.pdf',
			static function (array $f, array $o) use (&$dest): array {
				$token = (string) call_user_func($o['unique_filename_callback']);
				$dir   = wp_upload_dir()['path'] . '/sub';
				wp_mkdir_p($dir);
				$dest  = $dir . '/' . $token;
				copy($f['tmp_name'], $dest);
				return array(
					'file' => $dest,
					'url'  => '',
					'type' => $f['type'],
				);
			}
		);

		$this->assertInstanceOf(\WP_Error::class, $r);
		$this->assertNotNull($dest);
		$this->assertFileDoesNotExist($dest);
		$this->assertSame($before, array_diff(scandir($root), array( 'sub' )));
	}

	/**
	 * Opus O1 (Minor 3): only the token THIS call generated is trusted --
	 * a handler reporting a different, validly-shaped file directly in the
	 * root must be refused and that file removed.
	 */
	public function test_a_handler_reporting_a_different_token_directly_in_the_root_is_refused_and_removed(): void
	{
		$root   = (string) ContactAttachmentStore::root();
		$before = scandir($root);

		$r = ContactAttachmentStore::store_upload(
			$this->fixture($this->in, 'pdf'),
			'a.pdf',
			static function (array $f, array $o): array {
				$other = bin2hex(random_bytes(16));
				$dest  = wp_upload_dir()['path'] . '/' . $other;
				copy($f['tmp_name'], $dest);
				return array(
					'file' => $dest,
					'url'  => '',
					'type' => $f['type'],
				);
			}
		);

		$this->assertInstanceOf(\WP_Error::class, $r);
		$this->assertSame($before, scandir($root));
	}

	/** Spec §4: refused content leaves nothing in the private folder. */
	public function test_a_refused_upload_leaves_no_file_behind(): void
	{
		$root   = (string) ContactAttachmentStore::root();
		$before = scandir($root);
		foreach (array( array( 'php', 'evil.php.pdf' ), array( 'svg', 'x.svg' ), array( 'html', 'x.html' ), array( 'zip', 'x.pdf' ), array( 'zip', 'x.docx' ) ) as [ $kind, $name ]) {
			$r = ContactAttachmentStore::store_upload($this->fixture($this->in, $kind), $name, $this->sideload());
			$this->assertInstanceOf(\WP_Error::class, $r, $name);
		}
		$this->assertSame($before, scandir($root));
	}

	public function test_a_handler_returning_an_error_reports_it_and_leaves_nothing(): void
	{
		$root   = (string) ContactAttachmentStore::root();
		$before = scandir($root);
		$r      = ContactAttachmentStore::store_upload($this->fixture($this->in, 'pdf'), 'a.pdf', static fn(array $f, array $o): array => array( 'error' => 'x' ));
		$this->assertSame('The file could not be uploaded.', $r->get_error_message());
		$this->assertSame($before, scandir($root));
	}

	public function test_the_default_handler_refuses_a_file_that_is_not_an_http_upload(): void
	{
		$r = ContactAttachmentStore::store_upload($this->fixture($this->in, 'pdf'), 'a.pdf');
		$this->assertInstanceOf(\WP_Error::class, $r, 'wp_handle_upload() must be the default -- its is_uploaded_file() check is what proves the source is an HTTP upload');
	}

	/** Review Focus 2. */
	public function test_two_uploads_of_the_same_bytes_get_separate_files(): void
	{
		$a = $this->contact_with($this->upload('pdf', 'same.pdf'));
		$b = $this->contact_with($this->upload('pdf', 'same.pdf'));
		$ra = ContactAttachmentStore::record($a);
		$rb = ContactAttachmentStore::record($b);
		$this->assertNotSame($ra['file'], $rb['file']);

		wp_delete_post($a, true);

		$this->assertNull(ContactAttachmentStore::path($ra));
		$this->assertNotNull(ContactAttachmentStore::path($rb));
	}

	public function test_permanent_delete_keeps_a_file_another_message_still_references(): void
	{
		$record = $this->upload('pdf', 'shared.pdf');
		$a      = $this->contact_with($record);
		$b      = $this->contact_with($record);

		wp_delete_post($a, true);
		$this->assertNotNull(ContactAttachmentStore::path($record));

		wp_delete_post($b, true);
		$this->assertNull(ContactAttachmentStore::path($record));
	}

	public function test_a_booking_carrying_the_same_token_does_not_count_as_a_reference(): void
	{
		$record  = $this->upload('pdf', 'x.pdf');
		$contact = $this->contact_with($record);
		$booking = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking' ));
		update_post_meta($booking, ContactAttachmentStore::FILE_META, $record['file']);

		wp_delete_post($contact, true);

		$this->assertNull(ContactAttachmentStore::path($record));
	}

	public function test_trashing_keeps_the_file(): void
	{
		$record = $this->upload('pdf', 'x.pdf');
		wp_trash_post($this->contact_with($record));
		$this->assertNotNull(ContactAttachmentStore::path($record));
	}

	/** Review Focus 5. */
	public function test_a_tampered_record_resolves_to_no_path(): void
	{
		remove_all_filters(ContactAttachmentStore::ROOT_FILTER); // root inside the sandbox, so ../victim stays in it too
		$root = (string) ContactAttachmentStore::root();
		file_put_contents(dirname($root) . '/victim', 'x');
		$id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_contact', 'post_status' => 'private' ));
		update_post_meta($id, ContactAttachmentStore::META_KEY, array( 'file' => '../victim', 'name' => 'a.pdf', 'mime' => 'application/pdf', 'size' => 1 ));
		update_post_meta($id, ContactAttachmentStore::FILE_META, '../victim');

		$this->assertNull(ContactAttachmentStore::record($id));
		$this->assertNull(ContactAttachmentStore::path(array( 'file' => '../victim' )));
		wp_delete_post($id, true);
		$this->assertFileExists(dirname($root) . '/victim');
	}

	public function test_a_record_with_an_unlisted_mime_is_not_trusted(): void
	{
		$record         = $this->upload('pdf', 'x.pdf');
		$record['mime'] = 'text/html';
		$id             = $this->contact_with($record);
		$this->assertNull(ContactAttachmentStore::record($id));
	}

	public function test_legacy_url_reads_only_a_string_meta(): void
	{
		$id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_contact', 'post_status' => 'private' ));
		update_post_meta($id, ContactAttachmentStore::META_KEY, 'http://example.org/wp-content/uploads/2026/08/a.pdf');
		$this->assertSame('http://example.org/wp-content/uploads/2026/08/a.pdf', ContactAttachmentStore::legacy_url($id));
		ContactAttachmentStore::attach($id, $this->upload('pdf', 'a.pdf'));
		$this->assertSame('', ContactAttachmentStore::legacy_url($id));
	}

	public function test_store_copy_leaves_the_source_and_verifies_the_copy(): void
	{
		$src = $this->fixture($this->in, 'pdf');
		$r   = ContactAttachmentStore::store_copy($src, 'old.pdf', 'application/pdf');
		$this->assertIsArray($r);
		$this->assertFileExists($src);
		$this->assertSame(hash_file('sha256', $src), hash_file('sha256', (string) ContactAttachmentStore::path($r)));
	}

	public function test_the_file_token_is_kept_by_the_database_cleaner(): void
	{
		$this->assertContains(ContactAttachmentStore::FILE_META, \MHMRentiva\Admin\Core\Utilities\DatabaseCleaner::valid_meta_keys());
		$this->assertContains(ContactAttachmentStore::META_KEY, \MHMRentiva\Admin\Core\Utilities\DatabaseCleaner::valid_meta_keys());
	}
}
