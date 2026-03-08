<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor;

use MHMRentiva\Admin\Vendor\VendorOwnershipEnforcer;

class VendorOwnershipEnforcerTest extends \WP_UnitTestCase {

	private int $vendor_a;
	private int $vendor_b;
	private int $vehicle_a;

	protected function setUp(): void {
		parent::setUp();

		$this->vendor_a = $this->factory()->user->create();
		$this->vendor_b = $this->factory()->user->create();

		( new \WP_User( $this->vendor_a ) )->add_role( 'rentiva_vendor' );
		( new \WP_User( $this->vendor_b ) )->add_role( 'rentiva_vendor' );

		$this->vehicle_a = $this->factory()->post->create( array(
			'post_type'   => 'vehicle',
			'post_status' => 'publish',
			'post_author' => $this->vendor_a,
		) );

		VendorOwnershipEnforcer::register();
	}

	public function test_vendor_can_edit_own_vehicle(): void {
		wp_set_current_user( $this->vendor_a );
		$this->assertTrue( user_can( $this->vendor_a, 'edit_post', $this->vehicle_a ) );
	}

	public function test_vendor_cannot_edit_other_vendors_vehicle(): void {
		wp_set_current_user( $this->vendor_b );
		$this->assertFalse( user_can( $this->vendor_b, 'edit_post', $this->vehicle_a ) );
	}

	public function test_vendor_cannot_delete_other_vendors_vehicle(): void {
		wp_set_current_user( $this->vendor_b );
		$this->assertFalse( user_can( $this->vendor_b, 'delete_post', $this->vehicle_a ) );
	}

	public function test_admin_can_edit_any_vehicle(): void {
		$admin = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$this->assertTrue( user_can( $admin, 'edit_post', $this->vehicle_a ) );
	}

	protected function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}
}
