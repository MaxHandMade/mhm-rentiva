<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vehicle;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Vehicle\ListTable\VehicleColumns;
use MHMRentiva\Admin\Vehicle\VehicleLifecycleStatus;
use WP_UnitTestCase;

/**
 * Admin vehicle-list lifecycle/archive filter — pure query-args helper.
 *
 * Withdrawn vehicles are moved to post_status=draft and expired ones stay publish-but-inactive,
 * so an "Archive" filter must widen post_status to include draft and target the lifecycle meta.
 *
 * @group vehicle-lifecycle
 */
final class VehicleArchiveFilterTest extends WP_UnitTestCase {

	public function test_archive_returns_expired_and_withdrawn_with_draft_status(): void {
		$args = VehicleColumns::lifecycle_filter_args( 'archive' );

		$this->assertArrayHasKey( 'meta_query', $args );
		$this->assertArrayHasKey( 'post_status', $args );
		$this->assertContains( 'draft', $args['post_status'], 'Archive view must include draft so withdrawn vehicles appear.' );

		$clause = $args['meta_query'][0];
		$this->assertSame( MetaKeys::VEHICLE_LIFECYCLE_STATUS, $clause['key'] );
		$this->assertSame( 'IN', $clause['compare'] );
		$this->assertContains( VehicleLifecycleStatus::EXPIRED, $clause['value'] );
		$this->assertContains( VehicleLifecycleStatus::WITHDRAWN, $clause['value'] );
	}

	public function test_single_lifecycle_value_targets_that_status(): void {
		$args = VehicleColumns::lifecycle_filter_args( VehicleLifecycleStatus::WITHDRAWN );

		$this->assertContains( 'draft', $args['post_status'] );
		$clause = $args['meta_query'][0];
		$this->assertSame( '=', $clause['compare'] );
		$this->assertSame( VehicleLifecycleStatus::WITHDRAWN, $clause['value'] );
	}

	public function test_empty_or_invalid_value_returns_no_filter(): void {
		$this->assertSame( array(), VehicleColumns::lifecycle_filter_args( '' ) );
		$this->assertSame( array(), VehicleColumns::lifecycle_filter_args( 'bogus' ) );
	}
}
