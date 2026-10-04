<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Migration;

use MHMRentiva\Admin\ContactMessages\ContactAttachmentMigration;
use MHMRentiva\Admin\ContactMessages\ContactAttachmentStore;
use MHMRentiva\Admin\Core\Utilities\DatabaseMigrator;
use MHMRentiva\Tests\Support\ContactAttachmentFixtures;
use MHMRentiva\Tests\Support\ForgetsMigrationLock;
use MHMRentiva\Tests\Support\SandboxesUploads;
use WP_UnitTestCase;

final class ContactAttachmentMigrationTest extends WP_UnitTestCase
{
	use SandboxesUploads;
	use ContactAttachmentFixtures;
	use ForgetsMigrationLock;

	public function setUp(): void
	{
		parent::setUp();
		$this->sandbox_uploads();
		update_option('uploads_use_yearmonth_folders', 1);
		delete_option(ContactAttachmentMigration::DONE_OPTION);
		delete_option(ContactAttachmentMigration::UNMIGRATED_OPTION);
		delete_option(ContactAttachmentMigration::PENDING_OPTION);
		self::forget_migration_lock();
	}

	public function tearDown(): void
	{
		parent::tearDown();
		self::forget_migration_lock();
		$this->remove_sandbox();
	}

	/** A real file at uploads/<rel> and its URL. */
	private function legacy(string $rel, string $kind = 'pdf'): string
	{
		$path = $this->sandbox . '/' . $rel;
		wp_mkdir_p(dirname($path));
		copy($this->fixture($this->sandbox . '/src', $kind), $path);
		return trailingslashit(wp_get_upload_dir()['baseurl']) . $rel;
	}

	private function contact(string $url, string $date = '2026-08-01 12:00:00'): int
	{
		$id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_contact', 'post_status' => 'private', 'post_date' => $date ));
		// update_post_meta() treats its value as "expected_slashed" and calls
		// wp_unslash() on the way in (meta.php's update_metadata()); without
		// wp_slash() here, a literal backslash in $url is silently eaten by
		// PHP's stripslashes() ("a\b" -> "ab") before it ever reaches the
		// migration -- measured directly against this test suite, not assumed.
		update_post_meta($id, ContactAttachmentStore::META_KEY, wp_slash($url));
		return $id;
	}

	private function unmigrated_reasons(): array
	{
		$state = get_option(ContactAttachmentMigration::UNMIGRATED_OPTION, array());
		return wp_list_pluck( (array) ( $state['items'] ?? array() ), 'reason', 'post_id');
	}

	public function test_a_legitimate_attachment_moves_and_its_old_url_stops_resolving(): void
	{
		$url = $this->legacy('2026/08/test-attachment.pdf');
		$id  = $this->contact($url);

		$this->assertTrue(ContactAttachmentMigration::run());

		$record = ContactAttachmentStore::record($id);
		$this->assertSame('test-attachment.pdf', $record['name']);
		$this->assertNotNull(ContactAttachmentStore::path($record));
		$this->assertFileDoesNotExist($this->sandbox . '/2026/08/test-attachment.pdf');
		$this->assertSame(array(), $this->unmigrated_reasons(), 'a clean move raises no notice');
		$this->assertSame('1', get_option(ContactAttachmentMigration::DONE_OPTION));
	}

	public function test_two_messages_sharing_a_url_share_one_private_file_and_the_source_goes_once(): void
	{
		$url = $this->legacy('2026/08/shared.pdf');
		$a   = $this->contact($url);
		$b   = $this->contact($url, '2026-08-20 09:00:00');

		ContactAttachmentMigration::run();

		$this->assertSame(ContactAttachmentStore::record($a)['file'], ContactAttachmentStore::record($b)['file']);
		$this->assertFileDoesNotExist($this->sandbox . '/2026/08/shared.pdf');
	}

	/** Uppercases the scheme://host[:port] authority, leaving the path untouched. */
	private function with_uppercase_host(string $url): string
	{
		return (string) preg_replace_callback('#^(https?://)([^/]+)#', static fn(array $m): string => $m[1] . strtoupper($m[2]), $url);
	}

	/**
	 * Review Focus 1 (round 3): classify()'s host check is case-insensitive
	 * (strcasecmp), so two DIFFERENT meta values -- differing only in host
	 * case -- both classify 'ok' to the exact same real file.
	 *
	 * Measured note: this specific variant does NOT actually discriminate
	 * round 2 from round 3. next_urls()'s and posts_for()'s WHERE/DISTINCT
	 * clauses run under the postmeta table's own (case-insensitive) default
	 * collation, so MySQL itself already folds these two meta values into one
	 * row before either version of the PHP code sees two distinct URLs at
	 * all (confirmed by a temporary debug dump: next_urls() returned exactly
	 * one entry for these two stored values, and posts_for() on it matched
	 * both posts). It is kept because it is still the exact scenario the
	 * review asked for and a genuine end-to-end guarantee -- the http/https
	 * pair below is the one that actually fails against round 2 (a scheme
	 * difference is real byte content, not something a collation folds).
	 */
	public function test_two_host_case_variants_of_one_url_share_one_private_file(): void
	{
		$url     = $this->legacy('2026/08/shared-case.pdf');
		$variant = $this->with_uppercase_host($url);
		$this->assertNotSame($url, $variant, 'the variant must actually differ from the original meta value');

		$a = $this->contact($url);
		$b = $this->contact($variant, '2026-08-20 09:00:00');

		$this->assertTrue(ContactAttachmentMigration::run());

		$record_a = ContactAttachmentStore::record($a);
		$record_b = ContactAttachmentStore::record($b);
		$this->assertNotNull($record_a);
		$this->assertNotNull($record_b);
		$this->assertSame($record_a['file'], $record_b['file'], 'both posts must share the same private file');
		$this->assertFileDoesNotExist($this->sandbox . '/2026/08/shared-case.pdf');
		$this->assertSame(array(), $this->unmigrated_reasons());
		$state = get_option(ContactAttachmentMigration::UNMIGRATED_OPTION, array());
		$this->assertEmpty($state['store_failed'] ?? false, 'the run must not report the folder as unwritable');
	}

	/**
	 * Review Focus 1 (round 3): classify() never compares scheme, only host
	 * and path -- an http/https pair of the same URL is the same bug class as
	 * the host-case variant above.
	 */
	public function test_an_http_https_pair_of_one_url_share_one_private_file(): void
	{
		$url_http = $this->legacy('2026/08/shared-scheme.pdf');
		$this->assertStringStartsWith('http://', $url_http, 'the sandboxed site URL is expected to be plain http in tests');
		$url_https = substr_replace($url_http, 'https', 0, 4);

		$a = $this->contact($url_http);
		$b = $this->contact($url_https, '2026-08-20 09:00:00');

		$this->assertTrue(ContactAttachmentMigration::run());

		$record_a = ContactAttachmentStore::record($a);
		$record_b = ContactAttachmentStore::record($b);
		$this->assertNotNull($record_a);
		$this->assertNotNull($record_b);
		$this->assertSame($record_a['file'], $record_b['file'], 'both posts must share the same private file');
		$this->assertFileDoesNotExist($this->sandbox . '/2026/08/shared-scheme.pdf');
		$this->assertSame(array(), $this->unmigrated_reasons());
		$state = get_option(ContactAttachmentMigration::UNMIGRATED_OPTION, array());
		$this->assertEmpty($state['store_failed'] ?? false, 'the run must not report the folder as unwritable');
	}

	/** @return array<string, array{0:string,1:string}> */
	public static function refused_urls(): array
	{
		return array(
			'foreign host'       => array( 'https://evil.example/wp-content/uploads/2026/08/a.pdf', 'host' ),
			'woocommerce_uploads' => array( '{base}/woocommerce_uploads/a.pdf', 'shape' ),
			'deeper path'        => array( '{base}/2026/08/sub/a.pdf', 'shape' ),
			'query string'       => array( '{base}/2026/08/a.pdf?x=1', 'shape' ),
			'month mismatch'     => array( '{base}/2026/07/a.pdf', 'month' ),
			'extension'          => array( '{base}/2026/08/a.zip', 'extension' ),
			'missing file'       => array( '{base}/2026/08/nope.pdf', 'missing' ),
			// R-19: before the fix this errors with ValueError from realpath().
			// A literal raw NUL byte here would be silently rewritten to "_" by
			// PHP's own parse_url() before classify() ever saw it (verified: PHP
			// 8.3.33, parse_url('http://x.test/2026/08/a' . "\0" . '.pdf') returns
			// path "/2026/08/a_.pdf") -- it would never reach the vulnerable
			// realpath() call at all. The URL-encoded form below is what actually
			// exercises the bug: parse_url() leaves the literal "%00" alone (it is
			// three ordinary characters), and rawurldecode() -- run on $rel, right
			// before the control-character guard -- turns it into a real NUL byte,
			// which realpath() rejects with a ValueError on PHP 8 without the fix.
			'control character'  => array( '{base}/2026/08/a%00.pdf', 'shape' ),
			// Review Focus 7: the counterpart to the case above -- a raw NUL byte
			// really is refused, just not for reason 'shape' (see the comment on
			// the case above for why): parse_url() rewrites it to "_" before
			// classify() sees it, so it reads as an ordinary, non-existent name.
			'raw NUL byte'        => array( "{base}/2026/08/a\0.pdf", 'missing' ),
			// rawurldecode() turns this into a literal "/" inside what must be a
			// single path segment; the shape regex has no slash-in-segment case.
			'percent-encoded slash' => array( '{base}/2026/08/a%2Fb.pdf', 'shape' ),
			// A literal backslash is barred from the filename segment by the
			// same character class that already excludes "/".
			'backslash'           => array( '{base}/2026/08/a\\b.pdf', 'shape' ),
		);
	}

	/** @dataProvider refused_urls */
	public function test_a_refused_url_clears_the_meta_leaves_the_file_and_is_listed(string $url, string $reason): void
	{
		$url = str_replace('{base}', untrailingslashit(wp_get_upload_dir()['baseurl']), $url);
		if (str_contains($url, '/2026/07/a.pdf')) {
			$this->legacy('2026/07/a.pdf');
		}
		$id = $this->contact($url);

		ContactAttachmentMigration::run();

		$this->assertSame('', get_post_meta($id, ContactAttachmentStore::META_KEY, true));
		$this->assertSame(array( $id => $reason ), $this->unmigrated_reasons());
		if (str_contains($url, '/2026/07/a.pdf')) {
			$this->assertFileExists($this->sandbox . '/2026/07/a.pdf', 'a refused file is never touched');
		}
	}

	/** Review Focus 7: a ".." segment is refused however the regex happens to reject it; nothing gets deleted either way. */
	public function test_a_dot_dot_segment_is_refused_and_nothing_is_deleted(): void
	{
		$outside = $this->sandbox . '/secret.pdf';
		copy($this->fixture($this->sandbox . '/src', 'pdf'), $outside);
		$url = trailingslashit(wp_get_upload_dir()['baseurl']) . '2026/08/../../secret.pdf';
		$id  = $this->contact($url);

		ContactAttachmentMigration::run();

		$reasons = $this->unmigrated_reasons();
		$this->assertArrayHasKey($id, $reasons);
		$this->assertContains($reasons[ $id ], array( 'shape', 'missing' ));
		$this->assertFileExists($outside);
	}

	/** Review Focus 2: a symlink resolving elsewhere on disk must not unlock a delete of its target. */
	public function test_a_symlink_is_refused_and_its_target_survives(): void
	{
		$this->legacy('2026/08/real-target.pdf');
		$target = $this->sandbox . '/2026/08/real-target.pdf';
		$link   = $this->sandbox . '/2026/08/linked.pdf';
		symlink($target, $link);
		$url = trailingslashit(wp_get_upload_dir()['baseurl']) . '2026/08/linked.pdf';
		$id  = $this->contact($url);

		ContactAttachmentMigration::run();

		$this->assertSame(array( $id => 'missing' ), $this->unmigrated_reasons());
		$this->assertFileExists($target, 'the symlink target must survive untouched');
	}

	/**
	 * Review Focus 2 (round 2): the ENTIRE uploads basedir is a symlink here,
	 * not one file inside it -- Bedrock/Capistrano's shared/uploads convention
	 * and mounted-volume deployments generally. The round-1 fix compared
	 * realpath() against a literal built from the UNRESOLVED basedir, so every
	 * legitimate file failed that comparison and was refused as 'missing'.
	 */
	public function test_a_symlinked_uploads_root_still_migrates_a_legitimate_file(): void
	{
		$real = $this->sandbox . '-symlink-target';
		$link = $this->sandbox . '-symlink-root';
		wp_mkdir_p($real . '/2026/08');
		copy($this->fixture($this->sandbox . '/src', 'pdf'), $real . '/2026/08/via-symlink.pdf');
		symlink($real, $link);

		add_filter('upload_dir', static function (array $dir) use ($link): array {
			$dir['basedir'] = $link;
			return $dir;
		});

		$url = trailingslashit(wp_get_upload_dir()['baseurl']) . '2026/08/via-symlink.pdf';
		$id  = $this->contact($url);

		try {
			$this->assertTrue(ContactAttachmentMigration::run());
			$this->assertNotNull(ContactAttachmentStore::record($id), 'a legitimate file under a symlinked uploads root must still migrate');
		} finally {
			unlink($link);
			$this->remove_tree($real);
		}
	}

	private function remove_tree(string $dir): void
	{
		if (! is_dir($dir)) {
			return;
		}
		$it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($it as $f) {
			$f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
		}
		rmdir($dir);
	}

	/**
	 * Review Focus 2 (round 2): the refusal must already be written to
	 * UNMIGRATED_OPTION at the exact moment its meta is about to be deleted
	 * -- delete_metadata() fires 'delete_post_meta' immediately before the
	 * actual DELETE (wp-includes/meta.php), which is the last observable
	 * point before a real fatal (timeout/memory -- uncatchable, so it would
	 * skip run()'s try/finally too) could strand a refusal that was cleared
	 * but never recorded.
	 */
	public function test_a_refusal_is_recorded_before_its_meta_is_cleared(): void
	{
		$id = $this->contact('https://evil.example/x.pdf'); // always refused: 'host'.

		$recorded_before_delete = null;
		$hook                   = function ($meta_ids, $object_id, $meta_key) use ($id, &$recorded_before_delete): void {
			if ($object_id !== $id || ContactAttachmentStore::META_KEY !== $meta_key) {
				return;
			}
			$state                  = get_option(ContactAttachmentMigration::UNMIGRATED_OPTION, array());
			$reasons                = wp_list_pluck( (array) ( $state['items'] ?? array() ), 'reason', 'post_id');
			$recorded_before_delete = $reasons[ $id ] ?? null;
		};
		add_action('delete_post_meta', $hook, 10, 3);

		ContactAttachmentMigration::run();

		remove_action('delete_post_meta', $hook, 10);

		$this->assertSame('host', $recorded_before_delete, 'the refusal must already be in the option before its meta is deleted');
	}

	/** Review Focus 2 (round 2): a refusal in the FIRST of two batches must still be recorded by the time run() finishes. */
	public function test_a_refusal_in_the_first_of_two_batches_still_ends_up_recorded(): void
	{
		// '0' sorts before 'b' in next_urls()'s ORDER BY meta_value, so this
		// lands in the first LIMIT-50 batch out of 55 total URLs.
		$bad_url = trailingslashit(wp_get_upload_dir()['baseurl']) . '2026/08/0-refused.zip';
		$bad_id  = $this->contact($bad_url);

		$ids = array();
		for ($i = 0; $i < 54; $i++) {
			$ids[] = $this->contact($this->legacy(sprintf('2026/08/b%02d.pdf', $i)));
		}

		ContactAttachmentMigration::run();

		$this->assertSame(array( $bad_id => 'extension' ), $this->unmigrated_reasons());
		foreach ($ids as $id) {
			$this->assertNotNull(ContactAttachmentStore::record($id));
		}
	}

	/** Review Focus 2 (round 2): persist_unmigrated()'s de-dup by post_id + reason. */
	public function test_a_repeated_refusal_after_the_meta_is_restored_produces_only_one_entry(): void
	{
		$url = trailingslashit(wp_get_upload_dir()['baseurl']) . '2026/08/repeat.zip';
		$id  = $this->contact($url);

		ContactAttachmentMigration::run();
		$state = get_option(ContactAttachmentMigration::UNMIGRATED_OPTION, array());
		$this->assertCount(1, (array) ( $state['items'] ?? array() ));

		// Simulate the meta reappearing (a restored revision, a bad import)
		// and the migration running again from scratch.
		update_post_meta($id, ContactAttachmentStore::META_KEY, wp_slash($url));
		delete_option(ContactAttachmentMigration::DONE_OPTION);
		ContactAttachmentMigration::run();

		$state = get_option(ContactAttachmentMigration::UNMIGRATED_OPTION, array());
		$items = (array) ( $state['items'] ?? array() );
		$this->assertCount(1, $items, 'a re-refusal of the same post for the same reason must not add a second entry');
		$this->assertSame($id, $items[0]['post_id']);
		$this->assertSame('extension', $items[0]['reason']);
	}

	/**
	 * M2: classify() itself never throws on planted input, so this reaches
	 * for the one seam it calls that a filter CAN make throw --
	 * wp_check_filetype_and_ext() -- rather than reproducing a defect. A
	 * Throwable from one row's classification must refuse only that row
	 * (meta cleared, file untouched, listed with reason 'error') and let the
	 * rest of the batch, including a second legitimate row, continue; the
	 * outer whole-body try/catch (R-19) is the second layer, not the only one.
	 */
	public function test_a_throwable_from_classify_refuses_only_that_row_and_the_batch_continues(): void
	{
		$bad_url  = $this->legacy('2026/08/boom.pdf');
		$bad_id   = $this->contact($bad_url);
		$good_url = $this->legacy('2026/08/fine.pdf');
		$good_id  = $this->contact($good_url, '2026-08-02 09:00:00');

		$throw_for_boom = static function ($data, $file, $filename) {
			if ('boom.pdf' === $filename) {
				throw new \RuntimeException('synthetic classify failure');
			}
			return $data;
		};
		add_filter('wp_check_filetype_and_ext', $throw_for_boom, 10, 3);

		try {
			$this->assertTrue(ContactAttachmentMigration::run());
		} finally {
			remove_filter('wp_check_filetype_and_ext', $throw_for_boom, 10);
		}

		$this->assertSame(array( $bad_id => 'error' ), $this->unmigrated_reasons());
		$this->assertSame('', get_post_meta($bad_id, ContactAttachmentStore::META_KEY, true));
		$this->assertFileExists($this->sandbox . '/2026/08/boom.pdf', 'a refused file is never touched');
		$this->assertNotNull(ContactAttachmentStore::record($good_id), 'a legitimate row in the same run still migrates');
		$state = get_option(ContactAttachmentMigration::UNMIGRATED_OPTION, array());
		$this->assertEmpty($state['store_failed'] ?? false, 'a per-row Throwable is not an IO failure');
	}

	public function test_content_that_does_not_prove_its_extension_is_refused(): void
	{
		$id = $this->contact($this->legacy('2026/08/fake.pdf', 'zip'));
		ContactAttachmentMigration::run();
		$this->assertSame(array( $id => 'content' ), $this->unmigrated_reasons());
		$this->assertFileExists($this->sandbox . '/2026/08/fake.pdf');
	}

	/** @return array<string, array{0:string,1:string}> attached file, URL the message points at */
	public static function library_files(): array
	{
		return array(
			'the attached file itself'         => array( '2026/08/photo.png', '2026/08/photo.png' ),
			'a -300x200 sub-size'              => array( '2026/08/photo.png', '2026/08/photo-300x200.png' ),
			'a -scaled original'               => array( '2026/08/big-scaled.png', '2026/08/big-scaled.png' ),
			'a sub-size of a -scaled original' => array( '2026/08/big-scaled.png', '2026/08/big-300x200.png' ),
			'a -rotated original'              => array( '2026/08/turn-rotated.png', '2026/08/turn-rotated.png' ),
			// Review Focus 1: names actually measured in the container's own
			// core (wp-admin/includes/image.php, image-edit.php), not guessed.
			// A -rotated original's own -WxH sub-size (image.php ~:412 EXIF
			// auto-rotate; sub-sizes are then generated from the rotated file).
			'a -rotated original\'s -WxH sub-size' => array( '2026/08/turn-rotated.png', '2026/08/turn-300x200.png' ),
			// ...and its own pre-rotation name, which core keeps registered too.
			'a -rotated original\'s own name'      => array( '2026/08/turn-rotated.png', '2026/08/turn.png' ),
			// image-edit.php's wp_save_image(): editing a library image backs up
			// the pre-edit file under -e<13+ digit millisecond timestamp>.
			'an edited image\'s pre-edit name'     => array( '2026/08/photo-e1727500000000.png', '2026/08/photo.png' ),
			// image.php ~:707: a PDF's own preview image is named <name>-pdf.<ext>
			// (png fixtures throughout; only the NAME exercises the PDF-preview shape).
			'a PDF\'s preview name'                => array( '2026/08/doc.pdf', '2026/08/doc-pdf.png' ),
		);
	}

	/** @dataProvider library_files Core stores the -scaled name in _wp_attached_file. */
	public function test_a_media_library_file_is_never_moved(string $attached, string $pointed): void
	{
		$url = $this->legacy($pointed, 'png');
		$att = (int) self::factory()->post->create(array( 'post_type' => 'attachment', 'post_mime_type' => 'image/png' ));
		update_post_meta($att, '_wp_attached_file', $attached);
		$id = $this->contact($url);

		ContactAttachmentMigration::run();

		$this->assertSame(array( $id => 'library' ), $this->unmigrated_reasons());
		$this->assertFileExists($this->sandbox . '/' . $pointed);
	}

	/** R-18: a filter answering "not in the library" must not unlock a delete. */
	public function test_a_library_file_is_found_even_when_the_lookup_filter_says_none(): void
	{
		add_filter('pre_attachment_url_to_postid', static fn() => 0);
		$url = $this->legacy('2026/08/lib.png', 'png');
		$att = (int) self::factory()->post->create(array( 'post_type' => 'attachment', 'post_mime_type' => 'image/png' ));
		update_post_meta($att, '_wp_attached_file', '2026/08/lib.png');
		$id = $this->contact($url);

		ContactAttachmentMigration::run();

		$this->assertSame(array( $id => 'library' ), $this->unmigrated_reasons());
		$this->assertFileExists($this->sandbox . '/2026/08/lib.png');
	}

	/** R-20: a public copy that will not go away stays listed and pending. */
	public function test_an_undeletable_source_is_listed_and_stays_pending(): void
	{
		$url  = $this->legacy('2026/08/stuck.pdf');
		$id   = $this->contact($url);
		$path = $this->sandbox . '/2026/08/stuck.pdf';
		add_filter('wp_delete_file', static fn(string $f): string => wp_normalize_path($f) === wp_normalize_path($path) ? '' : $f);

		ContactAttachmentMigration::run();

		$this->assertNotNull(ContactAttachmentStore::record($id), 'the move itself succeeded');
		$this->assertFileExists($path);
		$this->assertSame(array( $id => 'source' ), $this->unmigrated_reasons());
		$this->assertArrayHasKey($path, (array) get_option(ContactAttachmentMigration::PENDING_OPTION, array()));
	}

	public function test_without_year_month_folders_a_root_level_file_moves(): void
	{
		update_option('uploads_use_yearmonth_folders', 0);
		$id = $this->contact($this->legacy('flat.pdf'));
		ContactAttachmentMigration::run();
		$this->assertNotNull(ContactAttachmentStore::record($id));
	}

	/** Review Focus 4. */
	public function test_a_year_month_url_is_refused_once_the_setting_is_off(): void
	{
		$url = $this->legacy('2026/08/a.pdf');
		update_option('uploads_use_yearmonth_folders', 0);
		$id = $this->contact($url);
		ContactAttachmentMigration::run();
		$this->assertSame(array( $id => 'shape' ), $this->unmigrated_reasons());
		$this->assertFileExists($this->sandbox . '/2026/08/a.pdf');
	}

	public function test_a_site_without_legacy_attachments_gets_no_private_folder(): void
	{
		remove_all_filters(ContactAttachmentStore::ROOT_FILTER);
		$this->assertTrue(ContactAttachmentMigration::run());
		$this->assertDirectoryDoesNotExist($this->sandbox . '/mhm-rentiva-private');
	}

	public function test_a_second_run_is_a_no_op(): void
	{
		$id = $this->contact($this->legacy('2026/08/once.pdf'));
		ContactAttachmentMigration::run();
		$first = ContactAttachmentStore::record($id);
		ContactAttachmentMigration::run();
		$this->assertSame($first, ContactAttachmentStore::record($id));
	}

	public function test_an_unwritable_store_leaves_everything_for_the_next_run(): void
	{
		$url = $this->legacy('2026/08/wait.pdf');
		$id  = $this->contact($url);
		add_filter(ContactAttachmentStore::ROOT_FILTER, static fn(): string => '');

		$this->assertFalse(ContactAttachmentMigration::run());
		$this->assertSame($url, get_post_meta($id, ContactAttachmentStore::META_KEY, true));
		$this->assertFileExists($this->sandbox . '/2026/08/wait.pdf');
		$this->assertFalse(get_option(ContactAttachmentMigration::DONE_OPTION));

		// R-20: the administrator is told, not only the log.
		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'administrator' )));
		ob_start();
		ContactAttachmentMigration::render_unmigrated_notice();
		$this->assertStringContainsString('The private attachment folder could not be written.', (string) ob_get_clean());

		// Review Focus 7: once shown, it stays shown -- a second render prints nothing.
		ob_start();
		ContactAttachmentMigration::render_unmigrated_notice();
		$this->assertSame('', (string) ob_get_clean());
	}

	/** R-8: a request that died after writing the metas but before deleting the source. */
	public function test_a_pending_source_left_by_a_dead_run_is_deleted(): void
	{
		$url = $this->legacy('2026/08/orphan.pdf');
		update_option(ContactAttachmentMigration::PENDING_OPTION, array( $this->sandbox . '/2026/08/orphan.pdf' => $url ), false);

		ContactAttachmentMigration::run();

		$this->assertFileDoesNotExist($this->sandbox . '/2026/08/orphan.pdf');
		$this->assertSame(array(), get_option(ContactAttachmentMigration::PENDING_OPTION, array()));
	}

	public function test_a_pending_source_still_referenced_is_left_for_its_group(): void
	{
		$url = $this->legacy('2026/08/half.pdf');
		$id  = $this->contact($url);
		update_option(ContactAttachmentMigration::PENDING_OPTION, array( $this->sandbox . '/2026/08/half.pdf' => $url ), false);

		ContactAttachmentMigration::run();

		$this->assertNotNull(ContactAttachmentStore::record($id), 'the group re-ran and moved it');
		$this->assertFileDoesNotExist($this->sandbox . '/2026/08/half.pdf');
	}

	public function test_a_booking_with_the_same_meta_key_is_untouched(): void
	{
		$url     = $this->legacy('2026/08/booking.pdf');
		$booking = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_date' => '2026-08-01 12:00:00' ));
		update_post_meta($booking, ContactAttachmentStore::META_KEY, $url);
		ContactAttachmentMigration::run();
		$this->assertSame($url, get_post_meta($booking, ContactAttachmentStore::META_KEY, true));
		$this->assertFileExists($this->sandbox . '/2026/08/booking.pdf');
	}

	public function test_more_than_one_batch_is_migrated_in_one_run(): void
	{
		$ids = array();
		for ($i = 0; $i < 55; $i++) {
			$ids[] = $this->contact($this->legacy(sprintf('2026/08/b%02d.pdf', $i)));
		}
		ContactAttachmentMigration::run();
		foreach ($ids as $id) {
			$this->assertNotNull(ContactAttachmentStore::record($id));
		}
	}

	public function test_the_notice_prints_once_for_an_administrator(): void
	{
		$this->contact('https://evil.example/x.pdf');
		ContactAttachmentMigration::run();

		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'editor' )));
		ob_start();
		ContactAttachmentMigration::render_unmigrated_notice();
		$this->assertSame('', ob_get_clean(), 'editors see nothing');

		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'administrator' )));
		ob_start();
		ContactAttachmentMigration::render_unmigrated_notice();
		$first = (string) ob_get_clean();
		ob_start();
		ContactAttachmentMigration::render_unmigrated_notice();
		$second = (string) ob_get_clean();

		$this->assertStringContainsString('https://evil.example/x.pdf', $first);
		$this->assertSame('', $second);
	}

	public function test_the_migrator_runs_the_step_under_its_version_gate(): void
	{
		$id = $this->contact($this->legacy('2026/08/gate.pdf'));
		update_option('mhmrentiva_db_version', '4.4.0');
		DatabaseMigrator::run_migrations();
		$this->assertNotNull(ContactAttachmentStore::record($id));
		$this->assertSame('4.4.1', get_option('mhmrentiva_db_version'));
	}
}
