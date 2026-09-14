<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Admin\Utilities\Database;

use MHMRentiva\Admin\Utilities\Database\DatabaseCleanupPage;
use WP_UnitTestCase;

/**
 * DatabaseCleaner::cleanup_invalid_meta_keys() can set 'aborted' => true for
 * two independent, unrelated causes -- the custom-field definitions could not
 * be read, or $wpdb->prefix is too long for the backup table's name to fit
 * MySQL's 64-character identifier limit. Round 1/2 of this fix added the
 * second cause; ajax_cleanup_invalid_meta() branched only on
 * `! empty($result['aborted'])` and always sent the first cause's hardcoded
 * message, so an admin hitting the second cause was told the wrong story.
 *
 * invalid_meta_cleanup_message() is the fix: a small, pure static method
 * (array $result -> string message) extracted specifically so both causes are
 * testable directly, without a seam through the AJAX handler itself (which
 * exits the request via wp_send_json_error()/wp_send_json_success()).
 *
 * @covers \MHMRentiva\Admin\Utilities\Database\DatabaseCleanupPage::invalid_meta_cleanup_message
 */
final class DatabaseCleanupPageTest extends WP_UnitTestCase
{
	/**
	 * The pre-existing cause and its pre-existing message: this must not
	 * regress just because a second cause was added.
	 */
	public function test_the_custom_fields_unreadable_reason_keeps_its_own_message(): void
	{
		$message = DatabaseCleanupPage::invalid_meta_cleanup_message(
			array(
				'aborted'      => true,
				'reason'       => 'custom_fields_unreadable',
				'deleted'      => 0,
				'keys_removed' => array(),
				'at_risk_keys' => array( '_mhmrentiva_engine_torque' ),
			)
		);

		$this->assertStringContainsString( 'custom field definitions could not be read', $message );
	}

	/**
	 * The new cause introduced alongside this fix must surface
	 * DatabaseCleaner's own specific, translated message (naming the measured
	 * prefix length) -- not the custom-field message, which would be actively
	 * wrong for this cause.
	 */
	public function test_the_table_prefix_too_long_reason_surfaces_its_own_error_message(): void
	{
		$message = DatabaseCleanupPage::invalid_meta_cleanup_message(
			array(
				'aborted'      => true,
				'reason'       => 'table_prefix_too_long',
				'deleted'      => 0,
				'keys_removed' => array(),
				'error'        => 'This site\'s table prefix (40 characters) is too long for the invalid-meta cleanup to name a backup table within MySQL\'s 64-character identifier limit (maximum supported: 29 characters). The cleanup was not run.',
			)
		);

		$this->assertSame(
			'This site\'s table prefix (40 characters) is too long for the invalid-meta cleanup to name a backup table within MySQL\'s 64-character identifier limit (maximum supported: 29 characters). The cleanup was not run.',
			$message
		);
		$this->assertStringNotContainsString(
			'custom field definitions',
			$message,
			'the wrong-cause message from before this fix must not appear for this reason'
		);
	}

	/**
	 * Belt-and-braces: if 'reason' => 'table_prefix_too_long' but 'error' is
	 * somehow absent, a generic message is still returned rather than an
	 * empty string or a fatal on the missing key.
	 */
	public function test_the_table_prefix_too_long_reason_falls_back_to_a_generic_message_if_error_is_missing(): void
	{
		$message = DatabaseCleanupPage::invalid_meta_cleanup_message(
			array(
				'aborted' => true,
				'reason'  => 'table_prefix_too_long',
			)
		);

		$this->assertNotSame( '', $message );
		$this->assertStringContainsString( 'table prefix', $message );
	}

	/**
	 * An unrecognised or missing 'reason' (e.g. a result shaped before
	 * 'reason' existed) must still get a real message -- the pre-existing one,
	 * since it was already correct for every abort before this fix.
	 */
	public function test_a_missing_reason_falls_back_to_the_custom_fields_message(): void
	{
		$message = DatabaseCleanupPage::invalid_meta_cleanup_message(
			array(
				'aborted' => true,
			)
		);

		$this->assertStringContainsString( 'custom field definitions could not be read', $message );
	}
}
