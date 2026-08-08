<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Addons;

use MHMRentiva\Admin\Addons\AddonListTable;
use MHMRentiva\Admin\Addons\AddonManager;
use WP_UnitTestCase;

/**
 * The add-on CPT uses WordPress's native edit.php table. This class must only
 * register the two live enhancements for that screen, not carry an unreachable
 * second WP_List_Table implementation.
 */
final class AddonListTableRegistrationTest extends WP_UnitTestCase {

	protected function tearDown(): void {
		remove_action( 'admin_enqueue_scripts', array( AddonListTable::class, 'enqueue_scripts' ) );
		remove_action( 'admin_notices', array( AddonListTable::class, 'add_addon_stats_cards' ) );
		parent::tearDown();
	}

	public function test_addon_manager_registers_only_the_live_list_screen_enhancements(): void {
		AddonManager::admin_init();

		$this->assertSame( 10, has_action( 'admin_enqueue_scripts', array( AddonListTable::class, 'enqueue_scripts' ) ) );
		$this->assertSame( 10, has_action( 'admin_notices', array( AddonListTable::class, 'add_addon_stats_cards' ) ) );
		$this->assertFalse(
			is_subclass_of( AddonListTable::class, \WP_List_Table::class ),
			'The native add-on CPT screen must not ship an unreachable parallel WP_List_Table surface.'
		);
	}
}
