<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Core\Utilities;

use MHMRentiva\Admin\Core\Utilities\DatabaseCleaner;
use WP_UnitTestCase;

/**
 * A cleanup that says "WITH BACKUP" deletes nothing it has not backed up.
 *
 * All three backed-up cleanups -- invalid meta, orphaned postmeta, old log
 * rows -- ran `CREATE TABLE <backup> LIKE <source>`, then `INSERT INTO
 * <backup> SELECT ...`, then `DELETE`, and read the result of none of the
 * first two. A backup table that could not be created (a same-second name
 * collision, a name past MySQL's 64-character limit, a missing privilege) or
 * could not be filled left the DELETE to run anyway: the rows were gone and
 * the backup the admin was told about did not hold them.
 *
 * Each test breaks one statement of the backup through the `query` filter --
 * the same seam PrefixRenameMigrationTest uses for the merge-loser copy -- and
 * asserts the rows are still there and the result says why.
 *
 * 🔴 NEGATIVE CONTROLS. A test that breaks nothing must see the rows DELETED;
 * otherwise "the row is still there" would also pass for a fixture the cleanup
 * never matched.
 *
 * @covers \MHMRentiva\Admin\Core\Utilities\DatabaseCleaner::cleanup_invalid_meta_keys
 * @covers \MHMRentiva\Admin\Core\Utilities\DatabaseCleaner::cleanup_orphaned_postmeta
 * @covers \MHMRentiva\Admin\Core\Utilities\DatabaseCleaner::cleanup_old_logs
 */
final class DatabaseCleanerBackupFailClosedTest extends WP_UnitTestCase
{
	/** The backup statement to break: 'create' | 'insert' | ''. */
	private string $break = '';

	/** Name fragment of the backup table whose statement is broken. */
	private string $break_table_fragment = '';

	private int $broken = 0;

	/** @var list<string> Every DROP TABLE issued while the filter was on. */
	private array $drops = array();

	public function tearDown(): void
	{
		remove_filter( 'query', array( $this, 'break_the_backup' ) );
		parent::tearDown();
	}

	/**
	 * Rewrites the targeted backup statement into SQL MySQL rejects.
	 *
	 * @param string $query
	 */
	public function break_the_backup( $query ): string
	{
		$query   = (string) $query;
		if ( preg_match( '/^\s*DROP\s+(TEMPORARY\s+)?TABLE/i', $query ) ) {
			$this->drops[] = $query;
		}
		$pattern = 'create' === $this->break
			? '/^\s*CREATE\s+(TEMPORARY\s+)?TABLE\s+`?[a-z0-9_]*' . preg_quote( $this->break_table_fragment, '/' ) . '/i'
			: '/^\s*INSERT\s+INTO\s+`?[a-z0-9_]*' . preg_quote( $this->break_table_fragment, '/' ) . '/i';

		if ( '' !== $this->break && preg_match( $pattern, $query ) ) {
			++$this->broken;
			return 'SELECT * FROM `mhmrentiva_backup_statement_broken_by_test`';
		}

		return $query;
	}

	private function break_statement( string $statement, string $table_fragment ): void
	{
		$this->break                = $statement;
		$this->break_table_fragment = $table_fragment;
		add_filter( 'query', array( $this, 'break_the_backup' ) );
	}

	/**
	 * 🔴 A failed CREATE must not be followed by a DROP of that name: the
	 * CREATE may have failed BECAUSE the table exists -- a previous cleanup's
	 * backup from the same second -- and dropping it would destroy that copy.
	 */
	private function assertNoBackupWasDropped(): void
	{
		$this->assertSame( array(), preg_grep( '/' . preg_quote( $this->break_table_fragment, '/' ) . '/i', $this->drops ), 'a backup table was dropped after its CREATE failed' );
	}

	/** A table created but not filled is discarded, not left in the backup list. */
	private function assertTheUnfilledBackupWasDropped(): void
	{
		$this->assertCount( 1, preg_grep( '/' . preg_quote( $this->break_table_fragment, '/' ) . '/i', $this->drops ), 'the unfilled backup table was not dropped' );
	}

	/** @return array<string, mixed> The cleanup result, with the injected DB error silenced. */
	private static function quietly( callable $cleanup ): array
	{
		global $wpdb;

		$previous = $wpdb->suppress_errors( true );
		$result   = $cleanup();
		$wpdb->suppress_errors( $previous );

		return $result;
	}

	// ------------------------------------------------------------ invalid meta

	private function seed_invalid_meta(): int
	{
		$post_id = self::factory()->post->create( array( 'post_type' => 'post' ) );
		update_post_meta( $post_id, '_mhmprobe_fail_closed', 'keep-me' );

		return $post_id;
	}

	public function test_invalid_meta_is_not_deleted_when_the_backup_table_cannot_be_created(): void
	{
		$post_id = $this->seed_invalid_meta();
		$this->break_statement( 'create', 'postmeta_backup_invalid_' );

		$result = self::quietly( static fn() => DatabaseCleaner::cleanup_invalid_meta_keys( false ) );

		$this->assertSame( 1, $this->broken, 'premise: the backup CREATE was issued and broken' );
		$this->assertTrue( $result['aborted'] ?? false );
		$this->assertSame( 'backup_failed', $result['reason'] ?? '' );
		$this->assertSame( 0, $result['deleted'] ?? null );
		$this->assertSame( 'keep-me', get_post_meta( $post_id, '_mhmprobe_fail_closed', true ) );
		$this->assertNoBackupWasDropped();
	}

	public function test_invalid_meta_is_not_deleted_when_the_backup_copy_fails(): void
	{
		$post_id = $this->seed_invalid_meta();
		$this->break_statement( 'insert', 'postmeta_backup_invalid_' );

		$result = self::quietly( static fn() => DatabaseCleaner::cleanup_invalid_meta_keys( false ) );

		$this->assertSame( 1, $this->broken, 'premise: the backup INSERT was issued and broken' );
		$this->assertSame( 'backup_failed', $result['reason'] ?? '' );
		$this->assertSame( 'keep-me', get_post_meta( $post_id, '_mhmprobe_fail_closed', true ) );
		$this->assertTheUnfilledBackupWasDropped();
	}

	public function test_negative_control_invalid_meta_is_deleted_when_the_backup_succeeds(): void
	{
		$post_id = $this->seed_invalid_meta();

		$result = DatabaseCleaner::cleanup_invalid_meta_keys( false );

		$this->assertArrayNotHasKey( 'aborted', $result );
		$this->assertSame( '', get_post_meta( $post_id, '_mhmprobe_fail_closed', true ) );
	}

	// -------------------------------------------------------- orphaned postmeta

	private function seed_orphaned_meta(): int
	{
		global $wpdb;

		$missing_post_id = 2147480000;
		$wpdb->insert(
			$wpdb->postmeta,
			array(
				'post_id'    => $missing_post_id,
				'meta_key'   => '_mhmrentiva_orphan_fail_closed_probe',
				'meta_value' => 'keep-me',
			)
		);

		return (int) $wpdb->insert_id;
	}

	private static function meta_row_exists( int $meta_id ): bool
	{
		global $wpdb;

		return null !== $wpdb->get_var( $wpdb->prepare( 'SELECT meta_id FROM %i WHERE meta_id = %d', $wpdb->postmeta, $meta_id ) );
	}

	public function test_orphaned_meta_is_not_deleted_when_the_backup_table_cannot_be_created(): void
	{
		$meta_id = $this->seed_orphaned_meta();
		$this->break_statement( 'create', 'postmeta_backup_' );

		$result = self::quietly( static fn() => DatabaseCleaner::cleanup_orphaned_postmeta( false ) );

		$this->assertSame( 1, $this->broken, 'premise: the backup CREATE was issued and broken' );
		$this->assertTrue( $result['aborted'] ?? false );
		$this->assertSame( 'backup_failed', $result['reason'] ?? '' );
		$this->assertSame( 0, $result['deleted'] ?? null );
		$this->assertTrue( self::meta_row_exists( $meta_id ) );
		$this->assertNoBackupWasDropped();
	}

	public function test_orphaned_meta_is_not_deleted_when_the_backup_copy_fails(): void
	{
		$meta_id = $this->seed_orphaned_meta();
		$this->break_statement( 'insert', 'postmeta_backup_' );

		$result = self::quietly( static fn() => DatabaseCleaner::cleanup_orphaned_postmeta( false ) );

		$this->assertSame( 1, $this->broken, 'premise: the backup INSERT was issued and broken' );
		$this->assertSame( 'backup_failed', $result['reason'] ?? '' );
		$this->assertTrue( self::meta_row_exists( $meta_id ) );
		$this->assertTheUnfilledBackupWasDropped();
	}

	public function test_negative_control_orphaned_meta_is_deleted_when_the_backup_succeeds(): void
	{
		$meta_id = $this->seed_orphaned_meta();

		$result = DatabaseCleaner::cleanup_orphaned_postmeta( false );

		$this->assertArrayNotHasKey( 'aborted', $result );
		$this->assertFalse( self::meta_row_exists( $meta_id ) );
	}

	// ------------------------------------------------------------------ old logs

	private function seed_old_queue_row(): int
	{
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'mhmrentiva_queue',
			array(
				'job_type'   => 'fail_closed_probe',
				'job_data'   => '{}',
				'created_at' => '2000-01-01 00:00:00',
			)
		);

		return (int) $wpdb->insert_id;
	}

	private static function queue_row_exists( int $id ): bool
	{
		global $wpdb;

		return null !== $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE id = %d', $wpdb->prefix . 'mhmrentiva_queue', $id ) );
	}

	public function test_old_queue_rows_are_not_deleted_when_the_backup_table_cannot_be_created(): void
	{
		$row_id = $this->seed_old_queue_row();
		$this->break_statement( 'create', 'mhmrentiva_queue_backup_' );

		$results = self::quietly( static fn() => DatabaseCleaner::cleanup_old_logs( 30, false ) );

		$this->assertSame( 1, $this->broken, 'premise: the queue backup CREATE was issued and broken' );
		$this->assertTrue( $results['queue']['aborted'] ?? false );
		$this->assertSame( 'backup_failed', $results['queue']['reason'] ?? '' );
		$this->assertSame( 0, $results['queue']['deleted'] ?? null );
		$this->assertTrue( self::queue_row_exists( $row_id ) );
		$this->assertNoBackupWasDropped();
	}

	public function test_old_queue_rows_are_not_deleted_when_the_backup_copy_fails(): void
	{
		$row_id = $this->seed_old_queue_row();
		$this->break_statement( 'insert', 'mhmrentiva_queue_backup_' );

		$results = self::quietly( static fn() => DatabaseCleaner::cleanup_old_logs( 30, false ) );

		$this->assertSame( 1, $this->broken, 'premise: the queue backup INSERT was issued and broken' );
		$this->assertSame( 'backup_failed', $results['queue']['reason'] ?? '' );
		$this->assertTrue( self::queue_row_exists( $row_id ) );
		$this->assertTheUnfilledBackupWasDropped();
	}

	public function test_negative_control_old_queue_rows_are_deleted_when_the_backup_succeeds(): void
	{
		$row_id = $this->seed_old_queue_row();

		$results = DatabaseCleaner::cleanup_old_logs( 30, false );

		$this->assertArrayNotHasKey( 'aborted', $results['queue'] );
		$this->assertFalse( self::queue_row_exists( $row_id ) );
	}
}
