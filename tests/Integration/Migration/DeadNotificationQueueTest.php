<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Migration;

use MHMRentiva\Admin\Core\Utilities\DatabaseMigrator;
use WP_UnitTestCase;

/**
 * The scheduled-notification queue had no producer.
 *
 * `NotificationManager` registered an hourly cron, drained a queue table and
 * reported itself in Cron Monitor as "Scheduled Notifications — Sends scheduled
 * email notifications". Nothing ever wrote to that queue: `send_notification()`
 * and `queue_notification()` had zero callers in either edition, so the job
 * fired forever over an empty table while the monitor showed it healthy. That
 * is the same false-promise shape as the Security tab's protection toggles and
 * the API keys no endpoint honoured.
 *
 * Real email is unaffected — booking confirmations, reminders, refunds and test
 * sends all go through the Emails module (`Mailer`), synchronously, and never
 * touched this queue.
 *
 * Removing the code leaves two things behind on an existing install: the cron
 * event, which lives in the `cron` option and outlives the class that scheduled
 * it, and the queue table. Both go here.
 *
 * @covers \MHMRentiva\Admin\Core\Utilities\DatabaseMigrator::run_migrations
 */
final class DeadNotificationQueueTest extends WP_UnitTestCase
{
	private const HOOK        = 'mhm_rentiva_send_scheduled_notifications';
	private const LEGACY_HOOK = 'mhm_send_scheduled_notifications';

	private function queue_table(): string
	{
		global $wpdb;

		return $wpdb->prefix . 'mhm_notification_queue';
	}

	public function tearDown(): void
	{
		delete_option( 'mhm_rentiva_db_version' );
		wp_clear_scheduled_hook( self::HOOK );
		wp_clear_scheduled_hook( self::LEGACY_HOOK );
		parent::tearDown();
	}

	public function test_the_migration_unschedules_both_cron_names(): void
	{
		update_option( 'mhm_rentiva_db_version', '1.0.0' );
		wp_schedule_event( time() + 3600, 'hourly', self::HOOK );
		wp_schedule_event( time() + 3600, 'hourly', self::LEGACY_HOOK );

		DatabaseMigrator::run_migrations();

		$this->assertFalse(
			wp_next_scheduled( self::HOOK ),
			'The notification cron is still scheduled with no code left to handle it.'
		);
		$this->assertFalse(
			wp_next_scheduled( self::LEGACY_HOOK ),
			'The pre-5.2.0 notification cron is still scheduled.'
		);
	}

	public function test_the_migration_drops_the_queue_table(): void
	{
		global $wpdb;

		update_option( 'mhm_rentiva_db_version', '1.0.0' );

		$table = $this->queue_table();
		$wpdb->query( "CREATE TABLE IF NOT EXISTS `{$table}` ( id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, PRIMARY KEY (id) )" );

		DatabaseMigrator::run_migrations();

		$this->assertNull(
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ),
			'The queue table survived; nothing writes to it and nothing reads it.'
		);
	}

	/**
	 * And the migration must not recreate it, which is what the migrator did
	 * unconditionally on every upgrade.
	 */
	public function test_the_migration_does_not_recreate_the_queue_table(): void
	{
		global $wpdb;

		update_option( 'mhm_rentiva_db_version', '1.0.0' );

		DatabaseMigrator::run_migrations();

		$this->assertNull(
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->queue_table() ) ),
			'The migrator still creates the queue table.'
		);
	}

	public function test_the_class_is_gone(): void
	{
		$this->assertFalse(
			class_exists( '\MHMRentiva\Admin\Notifications\NotificationManager' ),
			'NotificationManager still ships.'
		);
	}
}
