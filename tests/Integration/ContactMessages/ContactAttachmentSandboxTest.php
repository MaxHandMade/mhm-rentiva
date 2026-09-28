<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\ContactMessages;

use WP_UnitTestCase;

/**
 * Canary for R-3. The suite's ABSPATH is the dev site's own /var/www/html, so
 * without this filter every Uninstaller test would purge the dev site's real
 * contact attachments. If this fails, stop: do not run the suite.
 */
final class ContactAttachmentSandboxTest extends WP_UnitTestCase
{
	public function test_the_suite_never_points_the_contact_root_at_the_real_uploads(): void
	{
		$root = (string) apply_filters('mhmrentiva_contact_attachment_root', '/var/www/html/wp-content/uploads/mhm-rentiva-private/contact');
		$this->assertStringStartsWith(wp_normalize_path(sys_get_temp_dir()) . '/', wp_normalize_path($root));
	}
}
