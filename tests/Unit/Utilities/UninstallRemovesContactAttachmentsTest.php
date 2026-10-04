<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Utilities;

use MHMRentiva\Admin\Utilities\Uninstall\Uninstaller;
use MHMRentiva\Admin\ContactMessages\ContactAttachmentStore;
use MHMRentiva\Tests\Support\SandboxesUploads;
use WP_UnitTestCase;

/**
 * Uninstall removes contact attachments but never the add-on's shared parent folder.
 *
 * Contact attachments live outside the media library (spec §4.4.1), so deleting
 * the contact messages alone does not reach the files when uninstall.php runs
 * without the plugin's hooks. purge() removes only files this plugin names and
 * never touches the shared mhm-rentiva-private/ parent, where the add-on keeps
 * vendor identity documents.
 *
 * @coversNothing
 */
final class UninstallRemovesContactAttachmentsTest extends WP_UnitTestCase {
	use SandboxesUploads;

	public function setUp(): void
	{
		parent::setUp();
		$this->sandbox_uploads();
		remove_all_filters(ContactAttachmentStore::ROOT_FILTER);
	}

	public function tearDown(): void
	{
		parent::tearDown();
		$this->remove_sandbox();
	}

	/** Spec §4: the add-on's vendor documents share the parent folder. */
	public function test_uninstall_takes_contact_and_leaves_the_add_ons_vendor_docs(): void
	{
		$root   = (string) ContactAttachmentStore::root();
		$vendor = dirname($root) . '/vendor-docs';
		wp_mkdir_p($vendor);
		file_put_contents($vendor . '/x', 'identity document');
		file_put_contents($root . '/' . str_repeat('a', 32), 'pdf bytes');

		Uninstaller::uninstall_direct(false);

		$this->assertFileExists($vendor . '/x');
		$this->assertDirectoryDoesNotExist($root);
		$this->assertFileExists(dirname($root) . '/.htaccess', 'the shared parent guard is never removed');
	}

	/** R-2: whatever the root filter says, only files this plugin names go. */
	public function test_uninstall_never_deletes_a_foreign_file_in_a_filtered_root(): void
	{
		$elsewhere = $this->sandbox . '/elsewhere';
		wp_mkdir_p($elsewhere);
		add_filter(ContactAttachmentStore::ROOT_FILTER, static fn(): string => $elsewhere);
		file_put_contents($elsewhere . '/' . str_repeat('b', 32), 'ours');
		file_put_contents($elsewhere . '/report.pdf', 'not ours');

		Uninstaller::uninstall_direct(false);

		$this->assertFileDoesNotExist($elsewhere . '/' . str_repeat('b', 32));
		$this->assertFileExists($elsewhere . '/report.pdf');
		$this->assertDirectoryExists($elsewhere);
	}
}
