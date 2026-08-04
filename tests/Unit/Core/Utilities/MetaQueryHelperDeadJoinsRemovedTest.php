<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Core\Utilities;

use MHMRentiva\Admin\Core\Utilities\MetaQueryHelper;
use WP_UnitTestCase;

/**
 * Görev 14 (T8, SlowDBQuery sweep): MetaQueryHelper::get_booking_meta_joins() and
 * ::get_vehicle_meta_joins() were removed as dead code, not "optimized."
 *
 * Evidence trail (see task-14-report.md for the full reconciliation):
 * - Neither method had a single caller anywhere in this repo or in mhm-rentiva-pro
 *   (`grep -rn "get_booking_meta_joins\|get_vehicle_meta_joins"` across both repos
 *   returned only the two definitions themselves before this change).
 * - get_booking_meta_joins()'s one and only historical caller,
 *   Customers\CustomersPage::get_customers_data(), was deleted in commit 622213fd
 *   ("remove legacy jQuery customers blocks") — the method itself was left behind,
 *   an orphan the same shape as task-13-report.md's DatabaseCleanupPage rows 26-27.
 * - get_vehicle_meta_joins() has zero callers in the entire tracked git history
 *   (`git log -S"get_vehicle_meta_joins"` shows only the two "initial commit"
 *   entries; no commit ever added or referenced a call site) — it shipped unused
 *   since v4.3.8.
 *
 * This closed 6 of the 34 Table C rows (MetaQueryHelper.php:178,183,188,193 —
 * get_booking_meta_joins' 4 meta_key literals — and :209,214 —
 * get_vehicle_meta_joins' 2 meta_key literals).
 *
 * build_meta_joins(), left_join_meta(), inner_join_meta() and
 * get_message_meta_joins() all stay: get_message_meta_joins() is live, called
 * from mhm-rentiva-pro's MessageQuery::getAdminMessages()/getCustomerMessages()
 * (src/Admin/Messages/REST/Helpers/MessageQuery.php:64,123).
 */
final class MetaQueryHelperDeadJoinsRemovedTest extends WP_UnitTestCase {

	/**
	 * Negative control: the two dead methods are gone, not just unreachable.
	 */
	public function test_dead_booking_and_vehicle_join_methods_no_longer_exist(): void {
		$this->assertFalse(
			method_exists( MetaQueryHelper::class, 'get_booking_meta_joins' ),
			'get_booking_meta_joins() was dead code (zero callers in Lite or Pro) and should have been deleted, not kept around.'
		);
		$this->assertFalse(
			method_exists( MetaQueryHelper::class, 'get_vehicle_meta_joins' ),
			'get_vehicle_meta_joins() was dead code (zero callers in Lite or Pro, unused since the initial commit) and should have been deleted.'
		);
	}

	/**
	 * Positive control: the surviving sibling that shares build_meta_joins() /
	 * left_join_meta() / inner_join_meta() with the deleted methods still works
	 * end to end (real $wpdb->prepare() calls, real JOIN/SELECT fragment shape).
	 * If deleting the two dead wrappers had disturbed the shared helpers, this
	 * would fail.
	 */
	public function test_surviving_get_message_meta_joins_still_builds_correct_sql_fragments(): void {
		$result = MetaQueryHelper::get_message_meta_joins();

		$this->assertArrayHasKey( 'joins', $result );
		$this->assertArrayHasKey( 'selects', $result );
		$this->assertCount( 8, $result['joins'], 'get_message_meta_joins() defines 8 meta fields.' );
		$this->assertCount( 8, $result['selects'] );

		$joins_sql = implode( ' ', $result['joins'] );
		$this->assertStringContainsString( "meta_key = '_mhmrentiva_customer_name'", $joins_sql );
		$this->assertStringContainsString( "meta_key = '_mhmrentiva_thread_id'", $joins_sql );
		$this->assertStringContainsString( 'LEFT JOIN', $joins_sql );

		$selects_sql = implode( ', ', $result['selects'] );
		$this->assertStringContainsString( 'as customer_name', $selects_sql );
		$this->assertStringContainsString( "COALESCE(pm_status.meta_value, 'pending') as status", $selects_sql );
	}

	/**
	 * build_meta_joins() itself (the method both deleted wrappers called into)
	 * is still callable directly and still produces a correct JOIN + SELECT pair
	 * for an arbitrary config -- proves the shared machinery, not just the one
	 * surviving caller's fixed input.
	 */
	public function test_build_meta_joins_still_works_directly(): void {
		$result = MetaQueryHelper::build_meta_joins(
			array(
				'x' => array(
					'meta_key'      => '_mhmrentiva_probe_key',
					'select_alias'  => 'probe',
					'default_value' => 'n/a',
				),
			)
		);

		$this->assertSame( array( 'joins', 'selects' ), array_keys( $result ) );
		$this->assertCount( 1, $result['joins'] );
		$this->assertStringContainsString( 'LEFT JOIN', $result['joins'][0] );
		$this->assertStringContainsString( "x.meta_key = '_mhmrentiva_probe_key'", $result['joins'][0] );
		$this->assertSame( "COALESCE(x.meta_value, 'n/a') as probe", $result['selects'][0] );
	}
}
