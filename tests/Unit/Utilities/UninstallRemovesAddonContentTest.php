<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Utilities;

use MHMRentiva\Admin\Utilities\Uninstall\Uninstaller;
use WP_UnitTestCase;

/**
 * Uninstall must take the add-on service catalogue with it.
 *
 * Measured 2026-08-24: the uninstaller deleted vehicles, bookings and the
 * contact/app_log/email_log trio, and left mhmrentiva_addon rows behind. That
 * was not a policy, it was an omission -- the sibling add-on plugin's ledger
 * tables ARE kept on purpose (append-only financial history, and a site removing
 * Lite may be about to reinstall it), but an additional service is catalogue
 * data with no such argument. One post type surviving out of four is an
 * inconsistency, and it leaves rows under a type that is unregistered the moment
 * the plugin is gone -- the same unreachable state step 3b was written to end
 * for contact messages.
 *
 * Step 4's `_mhmrentiva%` postmeta sweep is not enough on its own: it strips the
 * meta and leaves the wp_posts row, which still carries the service's name in
 * post_title.
 *
 * The negative half matters as much as the positive one. 'mhmrentiva_addon' is
 * this plugin's own prefixed slug, so unlike the legacy 'vehicle' /
 * 'vehicle_booking' branches it needs no meta-key guard -- but a test that only
 * proved deletion would pass just as well for an uninstaller that deleted
 * everything.
 *
 * @coversNothing
 */
final class UninstallRemovesAddonContentTest extends WP_UnitTestCase {

	public function test_an_addon_post_is_deleted(): void
	{
		$addon = self::factory()->post->create(
			array(
				'post_type'  => 'mhmrentiva_addon',
				'post_title' => 'Child seat',
			)
		);

		Uninstaller::uninstall_direct( false );

		$this->assertNull(
			get_post( $addon ),
			'uninstall left an mhmrentiva_addon row behind, so the service catalogue survives'
				. ' the plugin under a post type nothing registers any more.'
		);
	}

	/**
	 * Negative control: a post that is not ours must survive, so the assertion
	 * above cannot be satisfied by an uninstaller that simply deletes posts.
	 */
	public function test_a_foreign_post_is_left_alone(): void
	{
		$foreign = self::factory()->post->create(
			array(
				'post_type'  => 'post',
				'post_title' => 'Somebody elses article',
			)
		);

		Uninstaller::uninstall_direct( false );

		$this->assertNotNull(
			get_post( $foreign ),
			'uninstall deleted a post that does not belong to this plugin.'
		);
	}
}
