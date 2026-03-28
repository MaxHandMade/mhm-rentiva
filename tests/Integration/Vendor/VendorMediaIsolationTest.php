<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor;

use MHMRentiva\Admin\Vendor\VendorMediaIsolation;

class VendorMediaIsolationTest extends \WP_UnitTestCase
{
	public function test_filter_restricts_query_for_vendor(): void
	{
		$user_id = $this->factory->user->create();
		$user = new \WP_User($user_id);
		$user->add_role('rentiva_vendor');
		wp_set_current_user($user_id);

		$result = VendorMediaIsolation::isolate_vendor_media(array('orderby' => 'date'));

		$this->assertArrayHasKey('author', $result);
		$this->assertSame($user_id, $result['author']);
	}

	public function test_filter_does_not_restrict_for_admin(): void
	{
		$admin_id = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($admin_id);

		$result = VendorMediaIsolation::isolate_vendor_media(array('orderby' => 'date'));

		$this->assertArrayNotHasKey('author', $result);
	}

	public function test_filter_does_not_restrict_for_plain_customer(): void
	{
		$user_id = $this->factory->user->create(array('role' => 'subscriber'));
		wp_set_current_user($user_id);

		$result = VendorMediaIsolation::isolate_vendor_media(array('orderby' => 'date'));

		$this->assertArrayNotHasKey('author', $result);
	}

	public function tearDown(): void
	{
		wp_set_current_user(0);
		parent::tearDown();
	}
}
