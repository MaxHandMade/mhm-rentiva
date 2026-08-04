<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Maintenance;

use MHMRentiva\Admin\PostTypes\Logs\PostType;
use MHMRentiva\Admin\PostTypes\Maintenance\LogRetention;
use WP_UnitTestCase;

/**
 * The log purge must obey the two settings the UI offers for it.
 *
 * "Auto Cleanup Logs" and "Log Retention (Days)" are written into the
 * `mhmrentiva_settings` array, like every other setting on that screen.
 * `LogRetention::run()` read a *standalone* option of the same name that no code
 * has ever written, so it always fell back to its 30-day default and never
 * checked the toggle at all.
 *
 * The consequence is not cosmetic: `purge()` calls `wp_delete_post( $id, true )`
 * — force delete, no trash. An administrator who unticked the toggle to keep an
 * audit trail, or set retention to 365 days, still lost every log entry older
 * than thirty days, daily, with the settings screen showing their choice intact.
 *
 * `EmailLogRetention` reads both settings correctly through `SettingsCore::get`.
 * The pattern was known; this one path missed it.
 *
 * @covers \MHMRentiva\Admin\PostTypes\Maintenance\LogRetention::run
 */
final class LogRetentionHonoursSettingsTest extends WP_UnitTestCase
{
	public function tearDown(): void
	{
		delete_option( 'mhmrentiva_settings' );
		parent::tearDown();
	}

	private function settings( array $values ): void
	{
		update_option( 'mhmrentiva_settings', $values );
	}

	private function make_old_log( int $days_old ): int
	{
		return self::factory()->post->create(
			array(
				'post_type'   => PostType::TYPE,
				'post_status' => 'publish',
				'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( "-{$days_old} days" ) ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', strtotime( "-{$days_old} days" ) ),
			)
		);
	}

	public function test_nothing_is_purged_when_auto_cleanup_is_off(): void
	{
		$this->settings(
			array(
				'mhmrentiva_log_cleanup_enabled' => '0',
				'mhmrentiva_log_retention_days'  => 30,
			)
		);

		$log = $this->make_old_log( 200 );

		LogRetention::run();

		$this->assertNotNull(
			get_post( $log ),
			'A log was force-deleted while "Auto Cleanup Logs" was switched off.'
		);
	}

	public function test_the_configured_retention_period_is_respected(): void
	{
		$this->settings(
			array(
				'mhmrentiva_log_cleanup_enabled' => '1',
				'mhmrentiva_log_retention_days'  => 365,
			)
		);

		$log = $this->make_old_log( 200 );

		LogRetention::run();

		$this->assertNotNull(
			get_post( $log ),
			'A 200-day-old log was deleted although retention is set to 365 days.'
		);
	}

	/**
	 * And it must still do its job when the settings say so.
	 */
	public function test_logs_past_the_configured_period_are_purged(): void
	{
		$this->settings(
			array(
				'mhmrentiva_log_cleanup_enabled' => '1',
				'mhmrentiva_log_retention_days'  => 30,
			)
		);

		$old   = $this->make_old_log( 200 );
		$fresh = $this->make_old_log( 2 );

		LogRetention::run();

		$this->assertNull( get_post( $old ), 'An entry past the retention period survived.' );
		$this->assertNotNull( get_post( $fresh ), 'An entry inside the retention period was deleted.' );
	}
}
