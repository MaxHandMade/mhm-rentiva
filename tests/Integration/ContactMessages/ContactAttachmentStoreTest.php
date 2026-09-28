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
		$r = ContactAttachmentStore::store_upload($this->fixture($this->in, $kind), $name, static fn(string $from, string $to): bool => copy($from, $to));
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

	/** Spec §4: refused content leaves nothing in the private folder. */
	public function test_a_refused_upload_leaves_no_file_behind(): void
	{
		$root   = (string) ContactAttachmentStore::root();
		$before = scandir($root);
		foreach (array( array( 'php', 'evil.php.pdf' ), array( 'svg', 'x.svg' ), array( 'html', 'x.html' ), array( 'zip', 'x.pdf' ), array( 'zip', 'x.docx' ) ) as [ $kind, $name ]) {
			$r = ContactAttachmentStore::store_upload($this->fixture($this->in, $kind), $name, static fn(string $f, string $t): bool => copy($f, $t));
			$this->assertInstanceOf(\WP_Error::class, $r, $name);
		}
		$this->assertSame($before, scandir($root));
	}

	public function test_a_failed_move_reports_the_upload_error_and_leaves_nothing(): void
	{
		$root   = (string) ContactAttachmentStore::root();
		$before = scandir($root);
		$r      = ContactAttachmentStore::store_upload($this->fixture($this->in, 'pdf'), 'a.pdf', static fn(): bool => false);
		$this->assertSame('The file could not be uploaded.', $r->get_error_message());
		$this->assertSame($before, scandir($root));
	}

	public function test_the_default_mover_refuses_a_file_that_is_not_an_http_upload(): void
	{
		$r = ContactAttachmentStore::store_upload($this->fixture($this->in, 'pdf'), 'a.pdf');
		$this->assertInstanceOf(\WP_Error::class, $r, 'move_uploaded_file() must be the default -- it is what proves the source is an HTTP upload');
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
