<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Addons;

use MHMRentiva\Admin\Addons\AddonListTable;
use WP_UnitTestCase;

/**
 * T8 F04 (independent audit): the admin add-ons list table's "Duplicate" row
 * action linked to admin-post.php?action=mhmrentiva_duplicate_addon, but no
 * handler for that action was registered anywhere in this plugin or its Pro
 * sibling -- an admin who clicked it landed on a dead admin-post.php request.
 * Owner decision: remove the action rather than add a handler (no new
 * features on the WP.org submission branch).
 *
 * There is no get_row_actions() method on this class; the row markup is
 * built inline inside column_title(), which is the seam this test pins.
 *
 * @covers \MHMRentiva\Admin\Addons\AddonListTable::column_title
 */
final class AddonDuplicateRowActionTest extends WP_UnitTestCase
{
	private int $addon_id;

	public function setUp(): void
	{
		parent::setUp();

		$this->addon_id = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_addon',
				'post_status' => 'publish',
				'post_title'  => 'Child Seat',
			)
		);

		// column_title() calls get_edit_post_link()/get_delete_post_link(),
		// which both check current_user_can() and answer null without it --
		// this screen is admin-only in production, so an admin user matches
		// how it actually renders.
		wp_set_current_user(
			self::factory()->user->create( array( 'role' => 'administrator' ) )
		);
	}

	public function test_column_title_does_not_link_the_handlerless_duplicate_action(): void
	{
		$list_table = new AddonListTable();
		$output     = $list_table->column_title( get_post( $this->addon_id ) );

		$this->assertStringNotContainsString(
			'mhmrentiva_duplicate_addon',
			$output,
			'column_title() must not emit a row action for admin-post.php?action=mhmrentiva_duplicate_addon -- no handler for it exists in this plugin or its Pro sibling.'
		);
	}

	/**
	 * Negative control: proves the assertion above measures the Duplicate
	 * action specifically, rather than something that would swallow all
	 * row-action output (e.g. row_actions() itself breaking).
	 */
	public function test_column_title_still_links_the_edit_and_delete_actions(): void
	{
		$list_table = new AddonListTable();
		$output     = $list_table->column_title( get_post( $this->addon_id ) );

		$this->assertStringContainsString( 'row-actions', $output );
		$this->assertStringContainsString( "class='edit'", $output );
		$this->assertStringContainsString( "class='delete'", $output );
		$this->assertStringContainsString( esc_html( 'Child Seat' ), $output );
	}
}
